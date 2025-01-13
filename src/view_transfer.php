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
        if (!isset($_GET['TransferID'])) {
            throw new Exception("TransferID not provided.");
        }

        $transferID = (int)$_GET['TransferID'];

        // Fetch transfer header from MongoDB
        $transfer = $mongoDb->TransferHeader->findOne(["TransferID" => $transferID]);

        if (!$transfer) {
            throw new Exception("Transfer not found.");
        }

        // Fetch warehouse and aisle information
        $originWarehouse = $mongoDb->Warehouse->findOne(
            ['warehouseID' => $transfer['originWarehouseID']], 
            ['projection' => ['name' => 1, 'aisles' => 1]]
        );
        $destinationWarehouse = $mongoDb->Warehouse->findOne(
            ['warehouseID' => $transfer['destinationWarehouseID']], 
            ['projection' => ['name' => 1, 'aisles' => 1]]
        );

        // Get aisle names
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

        // Add warehouse and aisle names to transfer object
        $transfer['OriginWarehouseName'] = $originWarehouse['name'] ?? 'Unknown Warehouse';
        $transfer['DestinationWarehouseName'] = $destinationWarehouse['name'] ?? 'Unknown Warehouse';
        $transfer['OriginAisle'] = $originAisleName;
        $transfer['DestinationAisle'] = $destinationAisleName;

        // Fetch transfer lines from MongoDB (already part of the TransferHeader)
        $lines = $transfer['lines'];

        // Fetch product names for the lines
        $formattedLines = [];
        foreach ($lines as $line) {
            $product = $mongoDb->Product->findOne(
                ['ProductID' => (int)$line['ProductID']], 
                ['projection' => ['Name' => 1]]
            );
            $formattedLines[] = [
                'ProductID' => $line['ProductID'],
                'ProductName' => $product['Name'] ?? 'Unknown Product',
                'Quantity' => $line['quantity']
            ];
        }
        $lines = $formattedLines;
    } else {
        // Use MySQL
        $pdo = new PDO($dsn, $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        if (!isset($_GET['TransferID'])) {
            throw new Exception("TransferID not provided.");
        }

        $transferID = $_GET['TransferID'];

        // Fetch transfer header from MySQL
        $stmt = $pdo->prepare("
            SELECT th.TransferID, th.TransferDate, 
                   th.OriginWarehouseID, th.OriginAisle, 
                   th.DestinationWarehouseID, th.DestinationAisle,
                   ow.WarehouseName AS OriginWarehouseName, 
                   dw.WarehouseName AS DestinationWarehouseName
              FROM TransferHeader th
              JOIN Warehouse ow ON th.OriginWarehouseID = ow.WarehouseID
              JOIN Warehouse dw ON th.DestinationWarehouseID = dw.WarehouseID
             WHERE th.TransferID = :transfer_id
        ");
        $stmt->execute([':transfer_id' => $transferID]);
        $transfer = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$transfer) {
            throw new Exception("Transfer not found.");
        }

        // Fetch transfer lines from MySQL
        $lineStmt = $pdo->prepare("
            SELECT tl.ProductID, tl.Quantity, p.Name AS ProductName
              FROM TransferLines tl
              JOIN Product p ON tl.ProductID = p.ProductID
             WHERE tl.TransferID = :transfer_id
        ");
        $lineStmt->execute([':transfer_id' => $transferID]);
        $lines = $lineStmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $_SESSION['error_message'] = $e->getMessage();
    header('Location: view_transfers.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Transfer</title>
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
        .container {
            background-color: #fff;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
        }
        .info {
            margin-bottom: 15px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background-color: #f9f9f9;
        }
        .back-arrow {
            margin-bottom: 20px;
            text-align: left;
            margin-left: calc(50% - 300px); /* Align with form start */
        }
        .back-arrow a {
            text-decoration: none;
            font-size: 16px;
            color: white;
            background-color: #0078D7; /* Vibrant Blue */
            padding: 10px 15px;
            border-radius: 5px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            transition: all 0.2s ease-in-out;
            display: inline-flex;
            align-items: center;
        }
        .back-arrow a:hover {
            background-color: #005BB5; /* Darker Blue */
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }
        .back-arrow a svg {
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
            transform: scale(1.01);
            transition: all 0.2s ease-in-out;
        }
        td {
            color: #555; /* Subtle Gray */
        }
        .success-message {
            color: #28a745;
            text-align: center;
            margin: 20px auto;
            padding: 10px;
            max-width: 600px;
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 4px;
            font-weight: bold;
        }
        .error-message {
            color: #dc3545;
            text-align: center;
            margin: 20px auto;
            padding: 10px;
            max-width: 600px;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 4px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <h1>View Transfer</h1>
    <div class="back-arrow">
        <a href="view_transfers.php">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18px" height="18px">
                <path fill="currentColor" d="M21 11H6.414l5.293-5.293-1.414-1.414L3.586 12l6.707 6.707 1.414-1.414L6.414 13H21v-2z"/>
            </svg>
            Transfer List
        </a>
    </div>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="success-message"><?= htmlspecialchars($_SESSION['success_message']) ?></div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="error-message"><?= htmlspecialchars($_SESSION['error_message']) ?></div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <div class="container">
        <label>Origin Warehouse:</label>
        <div class="info">
            <?= htmlspecialchars($transfer['OriginWarehouseName'] ?? $transfer['originWarehouseID']) ?>
        </div>

        <label>Origin Aisle:</label>
        <div class="info">
            <?= htmlspecialchars($transfer['OriginAisle'] ?? $transfer['originAisle']) ?>
        </div>

        <label>Destination Warehouse:</label>
        <div class="info">
            <?= htmlspecialchars($transfer['DestinationWarehouseName'] ?? $transfer['destinationWarehouseID']) ?>
        </div>

        <label>Destination Aisle:</label>
        <div class="info">
            <?= htmlspecialchars($transfer['DestinationAisle'] ?? $transfer['destinationAisle']) ?>
        </div>

        <label>Transfer Date:</label>
        <div class="info">
            <?= htmlspecialchars($transfer['TransferDate'] ?? $transfer['transferDate']->toDateTime()->format('Y-m-d H:i:s')) ?>
        </div>

        <h2>Transfer Lines</h2>
        <table>
            <thead>
                <tr>
                    <th>Product ID</th>
                    <th>Product Name</th>
                    <th>Quantity</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lines as $line): ?>
                    <tr>
                        <td><?= htmlspecialchars($line['ProductID'] ?? $line['ProductID']) ?></td>
                        <td><?= htmlspecialchars($line['ProductName'] ?? $line['Name'] ?? 'Unknown Product') ?></td>
                        <td><?= htmlspecialchars($line['Quantity'] ?? $line['quantity']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
