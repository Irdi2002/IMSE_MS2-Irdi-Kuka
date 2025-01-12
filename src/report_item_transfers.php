<?php
session_start();

// MongoDB Configuration
require_once '/var/www/html/vendor/autoload.php';
$mongoUri = 'mongodb://Irdi:Password1@MyMongoDBContainer:27017';
$mongoClient = new MongoDB\Client($mongoUri);
$mongoDb = $mongoClient->selectDatabase('IMSE_MS2');

// MySQL Configuration
$host = 'MySQLDockerContainer';
$db = 'IMSE_MS2';
$user = 'root';
$pass = 'IMSEMS2';
$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";

try {
    // Determine whether to use MongoDB or MySQL based on session
    $useMongoDb = isset($_SESSION['use_mongodb']) && $_SESSION['use_mongodb'] === true;

    $items = [];
    $transfers = [];
    $chosenItem = '';

    if ($useMongoDb) {
        // MongoDB logic
        $productsCursor = $mongoDb->Product->find([], [
            'projection' => ['ProductID' => 1, 'Name' => 1],
            'sort' => ['ProductID' => 1]
        ]);
        $items = iterator_to_array($productsCursor);

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['item_no'])) {
            $itemNo = (int)$_POST['item_no'];

            $chosenItemData = $mongoDb->Product->findOne(['ProductID' => $itemNo], ['projection' => ['Name' => 1]]);
            $chosenItem = $chosenItemData['Name'] ?? '';

            $transferData = $mongoDb->TransferHeader->find([
                'lines.ProductID' => $itemNo
            ], [
                'projection' => [
                    'TransferID' => 1,
                    'transferDate' => 1,
                    'originWarehouseID' => 1,
                    'destinationWarehouseID' => 1,
                    'originAisle' => 1,
                    'destinationAisle' => 1,
                    'lines' => 1
                ],
                'sort' => ['TransferID' => 1]
            ]);

            foreach ($transferData as $transfer) {
                foreach ($transfer['lines'] as $line) {
                    if ($line['ProductID'] === $itemNo) {
                        $originWarehouse = $mongoDb->Warehouse->findOne(['warehouseID' => $transfer['originWarehouseID']], ['projection' => ['name' => 1, 'aisles' => 1]]);
                        $destinationWarehouse = $mongoDb->Warehouse->findOne(['warehouseID' => $transfer['destinationWarehouseID']], ['projection' => ['name' => 1, 'aisles' => 1]]);

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

                        $transfers[] = [
                            'TransferID' => $transfer['TransferID'],
                            'TransferDate' => $transfer['transferDate']->toDateTime()->format('Y-m-d'),
                            'OriginWarehouseName' => $originWarehouse['name'] ?? 'Unknown',
                            'OriginAisleName' => $originAisleName,
                            'DestinationWarehouseName' => $destinationWarehouse['name'] ?? 'Unknown',
                            'DestinationAisleName' => $destinationAisleName,
                            'Quantity' => $line['quantity']
                        ];
                    }
                }
            }
        }
    } else {
        // MySQL logic
        $pdo = new PDO($dsn, $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $itemsStmt = $pdo->query("SELECT ProductID, Name FROM Product ORDER BY ProductID");
        $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['item_no'])) {
            $itemNo = $_POST['item_no'];

            $itemStmt = $pdo->prepare("SELECT Name FROM Product WHERE ProductID = :item_no");
            $itemStmt->execute([':item_no' => $itemNo]);
            $chosenItem = $itemStmt->fetchColumn();

            $stmt = $pdo->prepare("
                SELECT th.TransferID, th.TransferDate, 
                       ow.WarehouseName AS OriginWarehouseName, oa.AisleName AS OriginAisleName, 
                       dw.WarehouseName AS DestinationWarehouseName, da.AisleName AS DestinationAisleName,
                       tl.Quantity
                  FROM TransferLines tl
                  JOIN TransferHeader th ON tl.TransferID = th.TransferID
                  JOIN Warehouse ow ON th.OriginWarehouseID = ow.WarehouseID
                  JOIN Aisle oa ON th.OriginAisle = oa.AisleNr AND th.OriginWarehouseID = oa.WarehouseID
                  JOIN Warehouse dw ON th.DestinationWarehouseID = dw.WarehouseID
                  JOIN Aisle da ON th.DestinationAisle = da.AisleNr AND th.DestinationWarehouseID = da.WarehouseID
                 WHERE tl.ProductID = :item_no
                 ORDER BY th.TransferID
            ");
            $stmt->execute([':item_no' => $itemNo]);
            $transfers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} catch (Exception $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
    die();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Item Transfer Report</title>
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
        .form-container {
            max-width: 600px;
            margin: 0 auto;
        }
        form {
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
        }
        select {
            width: calc(100% - 20px);
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .btn {
            background-color: #0078D7; /* Vibrant Blue */
            color: white;
            border: none;
            cursor: pointer;
            padding: 8px 15px;
            border-radius: 5px;
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
            font-weight: bold;
        }
        .btn:hover {
            background-color: #005BB5; /* Darker Blue */
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            margin-top: 20px;
        }
        th, td {
            padding: 12px 15px;
            text-align: center;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #0078D7;
            color: white;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
    </style>
</head>
<body>
    <h1>Item Transfer Report</h1>
    <div class="form-container">
        <form method="POST">
            <label for="item_no">Item Number:</label>
            <select id="item_no" name="item_no" required>
                <option value="">Select an item</option>
                <?php foreach ($items as $item): ?>
                    <option value="<?= htmlspecialchars($item['ProductID']) ?>">
                        <?= htmlspecialchars($item['Name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="submit" value="Generate Report" class="btn">
        </form>
    </div>

    <?php if (!empty($transfers)): ?>
        <table>
            <thead>
                <tr>
                    <th>Transfer ID</th>
                    <th>Transfer Date</th>
                    <th>Origin Warehouse</th>
                    <th>Origin Aisle</th>
                    <th>Destination Warehouse</th>
                    <th>Destination Aisle</th>
                    <th>Quantity</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transfers as $transfer): ?>
                    <tr>
                        <td><?= htmlspecialchars($transfer['TransferID']) ?></td>
                        <td><?= htmlspecialchars($transfer['TransferDate']) ?></td>
                        <td><?= htmlspecialchars($transfer['OriginWarehouseName']) ?></td>
                        <td><?= htmlspecialchars($transfer['OriginAisleName']) ?></td>
                        <td><?= htmlspecialchars($transfer['DestinationWarehouseName']) ?></td>
                        <td><?= htmlspecialchars($transfer['DestinationAisleName']) ?></td>
                        <td><?= htmlspecialchars($transfer['Quantity']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
        <p>No transfers found for the given item number.</p>
    <?php endif; ?>
</body>
</html>
