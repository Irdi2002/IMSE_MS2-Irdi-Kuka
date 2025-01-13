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

    // Get the warehouse name from URL
    $warehouseName = $_GET['WarehouseName'] ?? null;

    error_log("Value from WarehouseName used for filtering: " . json_encode($warehouseName));
    if (!$warehouseName) {
        throw new Exception("WarehouseName not provided.");
    }

    if ($useMongoDb) {
        // Find the warehouse in MongoDB using warehouseID
        $warehouseId = (int)$warehouseName; // The URL parameter contains the warehouseID
        $warehouse = $mongoDb->Warehouse->findOne(['warehouseID' => $warehouseId]);
        
        if (!$warehouse) {
            throw new Exception("Warehouse not found with ID: " . $warehouseId);
        }

        error_log("Found warehouse: " . json_encode($warehouse));

        // Format warehouse data to match MySQL structure
        $formattedWarehouse = [
            'WarehouseName' => $warehouse['name'],
            'Address' => $warehouse['address'],
            'Category' => $warehouse['category']
        ];

        // Prepare aisles data with inventory
        $aisles = [];
        if (isset($warehouse['aisles'])) {
            foreach ($warehouse['aisles'] as $aisle) {
                error_log("Processing aisle: " . json_encode($aisle));
                
                // Check if inventory exists and has at least one item
                if (!empty($aisle['inventory']) && isset($aisle['inventory'][0])) {
                    error_log("Aisle has inventory: " . json_encode($aisle['inventory']));
                    // For aisles with inventory, show the first product
                    $item = $aisle['inventory'][0];
                    if (isset($item['ProductID'])) {
                        error_log("Found product ID: " . $item['ProductID']);
                        $product = $mongoDb->Product->findOne(
                            ['ProductID' => (int)$item['ProductID']]
                        );
                        error_log("Found product: " . json_encode($product));
                        
                        $aisles[] = [
                            'AisleNr' => $aisle['AisleNr'],
                            'AisleName' => $aisle['Name'],
                            'ProductName' => $product ? $product['Name'] : 'Unknown Product',
                            'Quantity' => isset($item['quantity']) ? (int)$item['quantity'] : 0
                        ];
                    } else {
                        error_log("No ProductID in inventory item");
                        // Invalid inventory item
                        $aisles[] = [
                            'AisleNr' => $aisle['AisleNr'],
                            'AisleName' => $aisle['Name'],
                            'ProductName' => 'No Product',
                            'Quantity' => 0
                        ];
                    }
                } else {
                    error_log("No inventory for aisle");
                    // For aisles without inventory
                    $aisles[] = [
                        'AisleNr' => $aisle['AisleNr'],
                        'AisleName' => $aisle['Name'],
                        'ProductName' => 'No Product',
                        'Quantity' => 0
                    ];
                }
            }
        }
        
        error_log("Final aisles array: " . json_encode($aisles));

        $warehouse = $formattedWarehouse;
    } else {
        // MySQL logic
        $pdo = new PDO($dsn, $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Get the Warehouse record
        $stmt = $pdo->prepare("SELECT * FROM Warehouse WHERE WarehouseName = :WarehouseName");
        $stmt->execute([':WarehouseName' => $warehouseName]);
        $warehouse = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$warehouse) {
            throw new Exception("Warehouse not found.");
        }

        // Extract the actual PK for further queries
        $warehouseID = $warehouse['WarehouseID'];

        // Fetch all Aisles with inventory
        $aisleStmt = $pdo->prepare("
            SELECT 
                A.AisleNr,
                A.AisleName,
                A.Description,
                COALESCE(P.Name, 'No Product') AS ProductName,
                COALESCE(WI.Quantity, 0) AS Quantity
            FROM Aisle A
            LEFT JOIN WarehouseInventory WI
                   ON A.WarehouseID = WI.WarehouseID
                  AND A.AisleNr = WI.AisleNr
            LEFT JOIN Product P
                   ON WI.ProductID = P.ProductID
            WHERE A.WarehouseID = :warehouseID
            ORDER BY A.AisleNr, P.Name
        ");
        $aisleStmt->execute([':warehouseID' => $warehouseID]);
        $aisles = $aisleStmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Warehouse</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            padding: 20px;
            background-color: #f0f8ff; /* AliceBlue */
        }
        h1, h2 {
            text-align: center;
            color: #0078D7; /* Vibrant Blue */
        }
        .details {
            background-color: #fff;
            max-width: 800px;
            margin: 0 auto 20px auto;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        .details p {
            font-size: 16px;
            margin: 8px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        table th, table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: center;
        }
        table th {
            background-color: #0078D7;
            color: white;
        }
        .back-arrow {
            margin-bottom: 20px;
            text-align: left;
            margin-left: calc(50% - 400px); /* Align with details section */
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
        }
        .back-arrow a svg {
            margin-right: 5px;
        }
    </style>
</head>
<body>
    <h1>Warehouse Details</h1>
    <div class="back-arrow">
        <a href="view_warehouses.php">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18px" height="18px">
                <path fill="currentColor" d="M21 11H6.414l5.293-5.293-1.414-1.414L3.586 12l6.707 6.707 1.414-1.414L6.414 13H21v-2z"/>
            </svg>
            Warehouse List
        </a>
    </div>

    <div class="details">
        <h2>Warehouse Details</h2>
        <p><strong>Name:</strong> <?= htmlspecialchars($warehouse['WarehouseName']) ?></p>
        <p><strong>Address:</strong> <?= htmlspecialchars($warehouse['Address']) ?></p>
        <p><strong>Category:</strong> <?= htmlspecialchars($warehouse['Category']) ?></p>

        <h3>Aisles and Products</h3>
        <?php if (!empty($aisles)): ?>
            <table border="1">
                <tr>
                    <th>Aisle Number</th>
                    <th>Aisle Name</th>
                    <th>Product</th>
                    <th>Quantity</th>
                </tr>
                <?php foreach ($aisles as $aisle): ?>
                    <tr>
                        <td><?= htmlspecialchars((string)$aisle['AisleNr']) ?></td>
                        <td><?= htmlspecialchars($aisle['AisleName']) ?></td>
                        <td><?= htmlspecialchars($aisle['ProductName']) ?></td>
                        <td><?= htmlspecialchars((string)$aisle['Quantity']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php else: ?>
            <p>No aisles found for this warehouse.</p>
        <?php endif; ?>
    </div>
</body>
</html>