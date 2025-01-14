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
    $useMongoDb = isset($_SESSION['use_mongodb']) && $_SESSION['use_mongodb'] === true;

    if ($useMongoDb) {
        $warehousesCursor = $mongoDb->Warehouse->find([], [
            'sort' => ['warehouseID' => 1]
        ]);
        $warehouses = iterator_to_array($warehousesCursor);
    } else {
        $pdo = new PDO($dsn, $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $stmt = $pdo->query("SELECT * FROM Warehouse ORDER BY WarehouseID");
        $warehouses = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    <title>Warehouse List</title>
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
            transform: scale(1.01);
            transition: all 0.2s ease-in-out;
        }
        td {
            color: #555; /* Subtle Gray */
        }
        td a {
            text-decoration: none;
            color: #0078D7;
        }
        td a:hover {
            color: #005BB5; /* Darker Blue */
        }
    </style>
</head>
<body>
    <h1>Warehouse List</h1>
    <div class="btn-container">
        <a href="home.php" class="new-transfer-btn">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18px" height="18px">
                <path fill="currentColor" d="M21 11H6.414l5.293-5.293-1.414-1.414L3.586 12l6.707 6.707 1.414-1.414L6.414 13H21v-2z"/>
            </svg>
            Home
        </a>
        <a href="insert_warehouse.php" class="new-transfer-btn">+ New Warehouse</a>
    </div>
    <?php if (!empty($warehouses)): ?>
        <table>
            <thead>
                <tr>
                    <th>Warehouse Name</th>
                    <th>Address</th>
                    <th>Category</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($warehouses as $warehouse): ?>
                    <tr>
                        <td>
                            <?php if ($useMongoDb): ?>
                                <a href="view_warehouse.php?WarehouseName=<?= htmlspecialchars($warehouse['warehouseID']) ?>">
                                    <?= htmlspecialchars($warehouse['name']) ?>
                                </a>
                            <?php else: ?>
                                <a href="view_warehouse.php?WarehouseName=<?= htmlspecialchars($warehouse['WarehouseName']) ?>">
                                    <?= htmlspecialchars($warehouse['WarehouseName']) ?>
                                </a>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($useMongoDb ? $warehouse['address'] : $warehouse['Address']) ?></td>
                        <td><?= htmlspecialchars($useMongoDb ? $warehouse['category'] : $warehouse['Category']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="no-data">No warehouses found.</p>
    <?php endif; ?>
</body>
</html>