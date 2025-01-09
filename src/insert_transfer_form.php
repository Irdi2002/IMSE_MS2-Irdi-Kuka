<?php
session_start();
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
            background-color: #f0f8ff; /* AliceBlue */
        }
        h1 {
            text-align: center;
            color: #0078D7; /* Vibrant Blue */
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
            color: #333;
        }
        select,
        input[type="date"],
        input[type="number"] {
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
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            transition: all 0.2s ease-in-out;
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
            font-weight: bold;
        }
        .btn:hover {
            background-color: #005BB5; /* Darker Blue */
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
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
        .delete-row {
            background-color: #ff4d4d; /* Red */
            color: white;
            border: none;
            cursor: pointer;
            padding: 5px 10px;
            border-radius: 4px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            transition: all 0.2s ease-in-out;
            font-size: 12px;
        }
        .delete-row:hover {
            background-color: #cc0000; /* Darker Red */
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
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
            const warehouseID = warehouseSelect.value;

            fetch(`get_aisles.php?warehouse_id=${warehouseID}`)
                .then(response => response.json())
                .then(data => {
                    const selectedAisle = aisleSelect.value;
                    aisleSelect.innerHTML = '<option value="">Select Aisle</option>';
                    data.forEach(aisle => {
                        const option = document.createElement('option');
                        option.value = aisle.AisleNr;
                        option.textContent = `${aisle.AisleNr} - ${aisle.AisleName}`;
                        if (aisle.AisleNr == selectedAisle) {
                            option.selected = true;
                        }
                        aisleSelect.appendChild(option);
                    });
                    updateProductDropdowns();
                })
                .catch(error => console.error('Error fetching aisles:', error));
        }

        function updateProductDropdowns() {
            const originWarehouseSelect = document.getElementById('origin_warehouse');
            const originAisleSelect = document.getElementById('origin_aisle');
            const originWarehouseID = originWarehouseSelect.value;
            const originAisleNr = originAisleSelect.value;

            if (originWarehouseID && originAisleNr) {
                fetch(`get_products.php?warehouse_id=${originWarehouseID}&aisle_nr=${originAisleNr}`)
                    .then(response => response.json())
                    .then(data => {
                        const productSelects = document.querySelectorAll('select[name="product_id[]"]');
                        productSelects.forEach(select => {
                            const selectedProduct = select.value;
                            select.innerHTML = '<option value="">Select Product</option>';
                            data.forEach(product => {
                                const option = document.createElement('option');
                                option.value = product.ProductID;
                                option.textContent = `${product.ProductID} - ${product.Name} - ${product.Quantity}`;
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

        function deleteRow(button) {
            const row = button.parentElement;
            row.remove();
        }

        document.addEventListener('DOMContentLoaded', () => {
            document.getElementById('origin_aisle').addEventListener('change', updateProductDropdowns);
        });

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
                    <button type="button" class="delete-row" onclick="deleteRow(this)">Delete</button>
                </div>
            `;
            transferLinesContainer.insertAdjacentHTML('beforeend', lineFields);
            updateProductDropdowns();
        });

        updateDropdowns();
    </script>
</head>
<body>
    <h1>New Transfer</h1>
    <div class="back-arrow">
        <a href="view_transfers.php">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18px" height="18px">
                <path fill="currentColor" d="M21 11H6.414l5.293-5.293-1.414-1.414L3.586 12l6.707 6.707 1.414-1.414L6.414 13H21v-2z"/>
            </svg>
            Transfer List
        </a>
    </div>

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
                $host = 'MySQLDockerContainer';
                $db = 'IMSE_MS2';
                $user = 'root';
                $pass = 'IMSEMS2';

                try {
                    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
                    $stmt = $pdo->query("SELECT WarehouseID, WarehouseName FROM Warehouse");
                    $warehouses = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    foreach ($warehouses as $row) {
                        echo "<option value=\"" . htmlspecialchars($row['WarehouseID']) . "\">" . htmlspecialchars($row['WarehouseName']) . "</option>";
                    }
                } catch (PDOException $e) {
                    echo "<p>Error: " . $e->getMessage() . "</p>";
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
                foreach ($warehouses as $row) {
                    echo "<option value=\"" . htmlspecialchars($row['WarehouseID']) . "\">" . htmlspecialchars($row['WarehouseName']) . "</option>";
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
        <button type="button" id="add-line" class="btn" style="margin-bottom: 10px;">+ New Row</button>
        <div class="transfer-lines" id="transfer-lines">
            <div class="product-line">
                <label for="product_id">Product:</label>
                <select id="product_id" name="product_id[]" required>
                    <option value="">Select Product</option>
                </select>
                <label for="quantity">Quantity:</label>
                <input type="number" id="quantity" name="quantity[]" placeholder="Enter Quantity" min="1" required>
                <button type="button" class="delete-row" onclick="deleteRow(this)">Delete</button>
            </div>
        </div>

        <input type="submit" value="Create Transfer" class="btn">
    </form>

    <script>
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
                    <button type="button" class="delete-row" onclick="deleteRow(this)">Delete</button>
                </div>
            `;
            transferLinesContainer.insertAdjacentHTML('beforeend', lineFields);
            updateProductDropdowns();
        });

        updateDropdowns();
    </script>
</body>
</html>