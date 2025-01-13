<?php
session_start();

// MongoDB Configuration
require_once '/var/www/html/vendor/autoload.php';
$mongoUri = 'mongodb://Irdi:Password1@MyMongoDBContainer:27017';
$mongoClient = new MongoDB\Client($mongoUri);
$mongoDb = $mongoClient->selectDatabase('IMSE_MS2');

// MySQL Configuration
$host = 'MySQLDockerContainer'; // MySQL container name
$db = 'IMSE_MS2';               // Database name
$user = 'root';                 // MySQL username
$pass = 'IMSEMS2';              // MySQL root password
$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";

try {
    // Determine whether to use MongoDB or MySQL based on session
    $useMongoDb = isset($_SESSION['use_mongodb']) && $_SESSION['use_mongodb'] === true;

    if ($useMongoDb) {
        // Step 1: Fetch transfers from MongoDB
        $transfers = $mongoDb->TransferHeader->find([], [
            'sort' => ['TransferID' => 1],
        ]);

        $transferList = [];
        foreach ($transfers as $transfer) {
            $originWarehouse = $mongoDb->Warehouse->findOne(
                ['warehouseID' => $transfer['originWarehouseID']], 
                ['projection' => ['name' => 1, 'aisles' => 1]]
            );
            $destinationWarehouse = $mongoDb->Warehouse->findOne(
                ['warehouseID' => $transfer['destinationWarehouseID']], 
                ['projection' => ['name' => 1, 'aisles' => 1]]
            );

            $originAisleName = 'Unknown';
            if ($originWarehouse && isset($originWarehouse['aisles'])) {
                foreach ($originWarehouse['aisles'] as $aisle) {
                    if ($aisle['AisleNr'] == $transfer['originAisle']) {
                        $originAisleName = $aisle['Name'];
                        break;
                    }
                }
            }

            $destinationAisleName = 'Unknown';
            if ($destinationWarehouse && isset($destinationWarehouse['aisles'])) {
                foreach ($destinationWarehouse['aisles'] as $aisle) {
                    if ($aisle['AisleNr'] == $transfer['destinationAisle']) {
                        $destinationAisleName = $aisle['Name'];
                        break;
                    }
                }
            }

            $transferItem = [
                'TransferID' => $transfer['TransferID'],
                'TransferDate' => $transfer['transferDate']->toDateTime()->format('Y-m-d'),
                'OriginWarehouseName' => $originWarehouse['name'] ?? 'Unknown Warehouse',
                'OriginAisleName' => $originAisleName,
                'DestinationWarehouseName' => $destinationWarehouse['name'] ?? 'Unknown Warehouse',
                'DestinationAisleName' => $destinationAisleName,
            ];

            $transferList[] = $transferItem;
        }
    } else {
        // MySQL logic
        $pdo = new PDO($dsn, $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $stmt = $pdo->prepare("
            SELECT th.TransferID, th.TransferDate, 
                   ow.WarehouseName AS OriginWarehouseName, oa.AisleName AS OriginAisleName, 
                   dw.WarehouseName AS DestinationWarehouseName, da.AisleName AS DestinationAisleName
              FROM TransferHeader th
              JOIN Warehouse ow ON th.OriginWarehouseID = ow.WarehouseID
              JOIN Aisle oa ON th.OriginAisle = oa.AisleNr AND th.OriginWarehouseID = oa.WarehouseID
              JOIN Warehouse dw ON th.DestinationWarehouseID = dw.WarehouseID
              JOIN Aisle da ON th.DestinationAisle = da.AisleNr AND th.DestinationWarehouseID = da.WarehouseID
             ORDER BY th.TransferID
        ");
        $stmt->execute();
        $transferList = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    die();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transfer List</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            padding: 20px;
            background-color: #f0f8ff; /* AliceBlue */
        }
        h1 {
            text-align: center;
            color: #0078D7; /* Vibrant Blue */
        }
        .btn-container {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        .btn-container a {
            text-decoration: none;
            font-size: 16px;
            color: white;
            background-color: #0078D7;
            padding: 10px 15px;
            border-radius: 5px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            transition: all 0.2s ease-in-out;
            display: inline-flex;
            align-items: center;
        }
        .btn-container a:hover {
            background-color: #005BB5;
        }
        .btn-container a svg {
            margin-right: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            overflow: hidden;
        }
        th, td {
            padding: 12px 15px;
            text-align: center;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #0078D7; /* Vibrant Blue */
            color: white;
            font-weight: bold;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        tr:hover {
            background-color: #f1f1f1;
        }
        td a {
            text-decoration: none;
            color: #0078D7;
        }
        td a:hover {
            color: #005BB5; /* Darker Blue */
        }
        .no-data {
            text-align: center;
            color: #777;
            font-size: 18px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <h1>Transfer List</h1>
    <div class="btn-container">
        <a href="home.php" class="new-transfer-btn">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18px" height="18px">
                <path fill="currentColor" d="M21 11H6.414l5.293-5.293-1.414-1.414L3.586 12l6.707 6.707 1.414-1.414L6.414 13H21v-2z"/>
            </svg>
            Home
        </a>
        <a href="insert_transfer_form.php" class="new-transfer-btn">+ New Transfer</a>
    </div>
    <?php if (!empty($transferList)): ?>
        <table>
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Date</th>
                    <th>Origin Warehouse</th>
                    <th>Origin Aisle</th>
                    <th>Destination Warehouse</th>
                    <th>Destination Aisle</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transferList as $transfer): ?>
                    <tr>
                        <td>
                            <a href="view_transfer.php?TransferID=<?= htmlspecialchars($transfer['TransferID']) ?>">
                                <?= htmlspecialchars($transfer['TransferID']) ?>
                            </a>
                        </td>
                        <td><?= htmlspecialchars($transfer['TransferDate']) ?></td>
                        <td><?= htmlspecialchars($transfer['OriginWarehouseName']) ?></td>
                        <td><?= htmlspecialchars($transfer['OriginAisleName']) ?></td>
                        <td><?= htmlspecialchars($transfer['DestinationWarehouseName']) ?></td>
                        <td><?= htmlspecialchars($transfer['DestinationAisleName']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="no-data">No transfers found.</p>
    <?php endif; ?>
</body>
</html>
