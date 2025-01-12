<?php
session_start();

// Check if connected to MongoDB
$useMongoDB = isset($_SESSION['use_mongodb']) && $_SESSION['use_mongodb'];

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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Transfer</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            padding: 20px;
            background-color: #f0f8ff;
        }
        h1 {
            text-align: center;
            color: #0078D7;
        }
        form {
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
        }
        select, input[type="date"], input[type="number"] {
            width: calc(100% - 20px);
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .btn {
            background-color: #0078D7;
            color: white;
            border: none;
            cursor: pointer;
            padding: 8px 15px;
            border-radius: 5px;
            font-size: 14px;
            font-weight: bold;
        }
        .btn:hover {
            background-color: #005BB5;
        }
        .product-line {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }
        .product-line select {
            width: 50%;
        }
        .transfer-lines {
            margin-bottom: 20px;
        }
        .success-message {
            color: green;
            text-align: center;
            margin-bottom: 20px;
        }
        .error-message {
            color: red;
            text-align: center;
            margin-bottom: 20px;
        }
    </style>
    <script>
        function updateDropdowns() {
            const originSelect = document.getElementById('origin_warehouse');
            const destinationSelect = document.getElementById('destination_warehouse');
            const selectedOrigin = originSelect.value;

            Array.from(destinationSelect.options).forEach(option => {
                option.disabled = option.value === selectedOrigin;
            });

            const selectedDestination = destinationSelect.value;

            Array.from(originSelect.options).forEach(option => {
                option.disabled = option.value === selectedDestination;
            });

            updateAisleDropdowns('origin_warehouse', 'origin_aisle');
            updateAisleDropdowns('destination_warehouse', 'destination_aisle');
        }

        function updateAisleDropdowns(warehouseSelectId, aisleSelectId) {
            const warehouseSelect = document.getElementById(warehouseSelectId);
            const aisleSelect = document.getElementById(aisleSelectId);
            const warehouseID = warehouseSelect.value; // Ensure this matches your MongoDB _id format

            fetch(`get_aisles.php?warehouse_id=${warehouseID}&useMongoDB=<?php echo $useMongoDB ? 'true' : 'false'; ?>`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        console.error(data.error);
                    } else {
                        aisleSelect.innerHTML = '<option value="">Select Aisle</option>';
                        data.forEach(aisle => {
                            const option = document.createElement('option');
                            option.value = aisle.AisleNr;
                            option.textContent = `${aisle.AisleNr} - ${aisle.AisleName}`;
                            aisleSelect.appendChild(option);
                        });
                        updateProductDropdowns();
                    }
                })
                .catch(error => console.error('Error fetching aisles:', error));
        }

        function updateProductDropdowns() {
            const originWarehouseSelect = document.getElementById('origin_warehouse');
            const originAisleSelect = document.getElementById('origin_aisle');
            const originWarehouseID = originWarehouseSelect.value;
            const originAisleNr = originAisleSelect.value;

            if (originWarehouseID && originAisleNr) {
                fetch(`get_products.php?warehouse_id=${originWarehouseID}&aisle_nr=${originAisleNr}&useMongoDB=<?php echo $useMongoDB ? 'true' : 'false'; ?>`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.error) {
                            console.error('Error:', data.error);
                            return;
                        }
                        const productSelects = document.querySelectorAll('select[name="product_id[]"]');
                        productSelects.forEach(select => {
                            const selectedProduct = select.value;
                            select.innerHTML = '<option value="">Select Product</option>';
                            data.forEach(product => {
                                const option = document.createElement('option');
                                option.value = product.ProductID;
                                option.textContent = `${product.ProductID} - ${product.Name} (${product.Quantity})`;
                                if (product.ProductID == selectedProduct) {
                                    option.selected = true;
                                }
                                select.appendChild(option);
                            });
                        });
                    })
                    .catch(error => console.error('Error fetching products:', error));
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            document.getElementById('origin_aisle').addEventListener('change', updateProductDropdowns);

            document.getElementById('add-line').addEventListener('click', function () {
                const transferLinesContainer = document.getElementById('transfer-lines');
                const lineFields = `
                    <div class="product-line">
                        <label for="product_id">Product:</label>
                        <select name="product_id[]" required>
                            <option value="">Select Product</option>
                        </select>
                        <label for="quantity">Quantity:</label>
                        <input type="number" name="quantity[]" placeholder="Enter Quantity" min="1" required>
                    </div>
                `;
                transferLinesContainer.insertAdjacentHTML('beforeend', lineFields);
                updateProductDropdowns();
            });

            updateDropdowns();
        });
    </script>
</head>
<body>
    <h1>New Transfer</h1>

    <?php
    if (isset($_SESSION['success_message'])) {
        echo "<p class='success-message'>" . $_SESSION['success_message'] . "</p>";
        unset($_SESSION['success_message']);
    }
    if (isset($_SESSION['error_message'])) {
        echo "<p class='error-message'>" . $_SESSION['error_message'] . "</p>";
        unset($_SESSION['error_message']);
    }
    ?>

    <form action="insert_transfer.php" method="POST">
        <label for="origin_warehouse">Origin Warehouse:</label>
        <select id="origin_warehouse" name="origin_warehouse" onchange="updateDropdowns()" required>
            <option value="">Select Origin Warehouse</option>
            <?php
            if ($useMongoDB) {
                $warehouses = $mongoDb->Warehouse->find();
                foreach ($warehouses as $warehouse) {
                    echo "<option value='" . htmlspecialchars($warehouse['_id']) . "'>" . htmlspecialchars($warehouse['name']) . "</option>";
                }
            } else {
                try {
                    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
                    $stmt = $pdo->query("SELECT WarehouseID, WarehouseName FROM Warehouse");
                    foreach ($stmt as $row) {
                        echo "<option value='" . htmlspecialchars($row['WarehouseID']) . "'>" . htmlspecialchars($row['WarehouseName']) . "</option>";
                    }
                } catch (Exception $e) {
                    echo "<option>Error loading warehouses</option>";
                }
            }
            ?>
        </select>

        <label for="origin_aisle">Origin Aisle:</label>
        <select id="origin_aisle" name="origin_aisle" required>
            <option value="">Select Origin Aisle</option>
        </select>

        <label for="destination_warehouse">Destination Warehouse:</label>
        <select id="destination_warehouse" name="destination_warehouse" onchange="updateDropdowns()" required>
            <option value="">Select Destination Warehouse</option>
            <?php
            if ($useMongoDB) {
                $warehouses = $mongoDb->Warehouse->find();
                foreach ($warehouses as $warehouse) {
                    echo "<option value='" . htmlspecialchars($warehouse['_id']) . "'>" . htmlspecialchars($warehouse['name']) . "</option>";
                }
            } else {
                try {
                    $stmt = $pdo->query("SELECT WarehouseID, WarehouseName FROM Warehouse");
                    foreach ($stmt as $row) {
                        echo "<option value='" . htmlspecialchars($row['WarehouseID']) . "'>" . htmlspecialchars($row['WarehouseName']) . "</option>";
                    }
                } catch (Exception $e) {
                    echo "<option>Error loading warehouses</option>";
                }
            }
            ?>
        </select>

        <label for="destination_aisle">Destination Aisle:</label>
        <select id="destination_aisle" name="destination_aisle" required>
            <option value="">Select Destination Aisle</option>
        </select>

        <label for="transfer_date">Transfer Date:</label>
        <input type="date" id="transfer_date" name="transfer_date" required>

        <h2>Transfer Lines</h2>
        <button type="button" id="add-line" class="btn">+ Add Line</button>
        <div id="transfer-lines" class="transfer-lines">
            <div class="product-line">
                <label for="product_id">Product:</label>
                <select name="product_id[]" required>
                    <option value="">Select Product</option>
                </select>
                <label for="quantity">Quantity:</label>
                <input type="number" name="quantity[]" placeholder="Enter Quantity" min="1" required>
            </div>
        </div>

        <input type="submit" value="Create Transfer" class="btn">
    </form>
</body>
</html>
