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

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $warehouseName = $_POST['WarehouseName'] ?? null;
        $address = $_POST['address'] ?? null;
        $category = $_POST['category'] ?? null;
        $aisleNames = $_POST['aisle_name'] ?? [];
        $aisleDescriptions = $_POST['aisle_description'] ?? [];
        $fireExtinguishers = $_POST['fire_extinguisher'] ?? [];

        if ($useMongoDb) {
            // Get the next warehouse ID
            $lastWarehouse = $mongoDb->Warehouse->findOne(
                [],
                [
                    'sort' => ['warehouseID' => -1],
                    'projection' => ['warehouseID' => 1]
                ]
            );
            $nextWarehouseId = ($lastWarehouse ? $lastWarehouse['warehouseID'] : 0) + 1;

            // Prepare aisles array
            $aisles = [];
            for ($i = 0; $i < count($aisleNames); $i++) {
                $aisles[] = [
                    'AisleNr' => $i + 1,
                    'Name' => $aisleNames[$i],
                    'description' => $aisleDescriptions[$i] ?? '',
                    'fireExtinguisher' => isset($fireExtinguishers[$i]),
                    'inventory' => []
                ];
            }

            // Insert warehouse with aisles
            $mongoDb->Warehouse->insertOne([
                'warehouseID' => $nextWarehouseId,
                'name' => $warehouseName,
                'address' => $address,
                'category' => $category,
                'aisles' => $aisles
            ]);

            echo "<p style='color:green;'>New warehouse and aisles added successfully</p>";
        } else {
            // MySQL logic
            $pdo = new PDO($dsn, $user, $pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Insert the warehouse
            $stmt = $pdo->prepare("
                INSERT INTO Warehouse (WarehouseName, Address, Category)
                VALUES (:warehouse_name, :address, :category)
            ");
            $stmt->execute(['warehouse_name' => $warehouseName, 'address' => $address, 'category' => $category]);

            // Get the last inserted warehouse ID
            $warehouseID = $pdo->lastInsertId();

            // Insert aisles
            for ($i = 0; $i < count($aisleNames); $i++) {
                $aisleName = $aisleNames[$i];
                $aisleDescription = $aisleDescriptions[$i] ?? '';
                $fireExtinguisher = isset($fireExtinguishers[$i]) ? 1 : 0;

                $stmt = $pdo->prepare("
                    INSERT INTO Aisle (WarehouseID, AisleName, FireExtingusher, Description)
                    VALUES (:warehouse_id, :aisle_name, :fire_extinguisher, :description)
                ");
                $stmt->execute([
                    'warehouse_id' => $warehouseID,
                    'aisle_name' => $aisleName,
                    'fire_extinguisher' => $fireExtinguisher,
                    'description' => $aisleDescription
                ]);
            }

            echo "<p style='color:green;'>New warehouse and aisles added successfully</p>";
        }
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
    <title>Insert New Warehouse</title>
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
        form {
            max-width: 700px;
            margin: 0 auto;
            padding: 20px;
            background-color: #ffffff;
            border: 1px solid #dddddd;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 6px;
            font-weight: bold;
            color: #333;
        }
        input[type="text"], input[type="number"], input[type="email"] {
            width: 100%;
            padding: 10px;
            margin-top: 4px;
            border: 1px solid #cccccc;
            border-radius: 5px;
            font-size: 14px;
        }
        .btn-container {
            margin-bottom: 20px;
            text-align: left;
            max-width: 700px;
            margin: 20px auto; /* Align container with form */
        }
        .btn-container a {
            display: inline-block;
            padding: 10px;
            background-color: #0078D7;
            color: white;
            text-decoration: none;
            font-size: 16px;
            font-weight: bold;
            border-radius: 5px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            transition: all 0.2s ease-in-out;
        }
        .btn-container a:hover {
            background-color: #005BB5; /* Darker Blue */
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }
        .aisles-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .aisle-container {
            margin-bottom: 20px;
            padding: 15px;
            border: 1px solid #e0e0e0;
            border-radius: 5px;
            background-color: #f9f9f9;
        }
        button {
            display: inline-block;
            padding: 12px 20px;
            font-size: 16px;
            color: white;
            background-color: #0078D7;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        button.add-aisle-btn {
            background-color: #28a745; /* Green for Add Aisle */
        }
        button:hover {
            background-color: #005bb5;
        }
        button.add-aisle-btn:hover {
            background-color: #218838; /* Darker Green */
        }
    </style>
    <script>
        function addAisleRow() {
            const aisleContainer = document.getElementById('aisles-container');
            const newAisleRow = `
                <div class="aisle-container">
                    <div class="form-group">
                        <label for="aisle_name">Aisle Name:</label>
                        <input type="text" name="aisle_name[]" placeholder="Enter aisle name" required>
                    </div>
                    <div class="form-group">
                        <label for="aisle_description">Description:</label>
                        <input type="text" name="aisle_description[]" placeholder="Enter aisle description">
                    </div>
                    <div class="form-group fire-extinguisher-container">
                        <label>
                            <input type="checkbox" name="fire_extinguisher[]" value="1"> Fire Extinguisher Available
                        </label>
                    </div>
                </div>
            `;
            aisleContainer.insertAdjacentHTML('beforeend', newAisleRow);
        }
    </script>
</head>
<body>
    <h1>Insert New Warehouse</h1>
    <div class="btn-container">
        <a href="view_warehouses.php">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18px" height="18px" style="vertical-align: middle;">
                <path fill="currentColor" d="M21 11H6.414l5.293-5.293-1.414-1.414L3.586 12l6.707 6.707 1.414-1.414L6.414 13H21v-2z"/>
            </svg>
            Warehouse List
        </a>
    </div>
    <form method="POST">
        <div class="form-group">
            <label for="WarehouseName">Warehouse Name:</label>
            <input type="text" id="WarehouseName" name="WarehouseName" placeholder="Enter warehouse name" required>
        </div>
        <div class="form-group">
            <label for="address">Address:</label>
            <input type="text" id="address" name="address" placeholder="Enter warehouse address" required>
        </div>
        <div class="form-group">
            <label for="category">Category:</label>
            <input type="text" id="category" name="category" placeholder="Enter warehouse category" required>
        </div>

        <h2 class="aisles-header">
            Aisles
            <button type="button" class="add-aisle-btn" onclick="addAisleRow()">+ Add Aisle</button>
        </h2>
        <div id="aisles-container">
            <div class="aisle-container">
                <div class="form-group">
                    <label for="aisle_name">Aisle Name:</label>
                    <input type="text" name="aisle_name[]" placeholder="Enter aisle name" required>
                </div>
                <div class="form-group">
                    <label for="aisle_description">Description:</label>
                    <input type="text" name="aisle_description[]" placeholder="Enter aisle description">
                </div>
                <div class="form-group fire-extinguisher-container">
                    <label>
                        <input type="checkbox" name="fire_extinguisher[]" value="1"> Fire Extinguisher Available
                    </label>
                </div>
            </div>
        </div>

        <button type="submit" name="submit">Submit</button>
    </form>
</body>
</html>
