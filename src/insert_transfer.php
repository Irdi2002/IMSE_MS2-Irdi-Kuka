<?php
session_start();

// Database credentials
$host = 'MySQLDockerContainer'; // MySQL container name
$db = 'IMSE_MS2';               // Updated database name
$user = 'root';                 // MySQL username
$pass = 'IMSEMS2';              // MySQL root password

try {
    // Create a new PDO connection
    $dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass);

    // Set error mode to exceptions
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Insert the transfer header
        $originWarehouse = $_POST['origin_warehouse'];
        $originAisle = $_POST['origin_aisle'];
        $destinationWarehouse = $_POST['destination_warehouse'];
        $destinationAisle = $_POST['destination_aisle'];
        $transferDate = $_POST['transfer_date'];

        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare("
                INSERT INTO TransferHeader (OriginWarehouseID, OriginAisle, DestinationWarehouseID, DestinationAisle, TransferDate)
                VALUES (:origin_warehouse, :origin_aisle, :destination_warehouse, :destination_aisle, :transfer_date)
            ");
            $stmt->execute([
                ':origin_warehouse' => $originWarehouse,
                ':origin_aisle' => $originAisle,
                ':destination_warehouse' => $destinationWarehouse,
                ':destination_aisle' => $destinationAisle,
                ':transfer_date' => $transferDate
            ]);

            // Get the TransferID of the newly inserted transfer header
            $transferID = $pdo->lastInsertId();

            // Insert the transfer lines and update inventory
            $productIDs = $_POST['product_id'];
            $quantities = $_POST['quantity'];

            $lineStmt = $pdo->prepare("
                INSERT INTO TransferLines (TransferID, ProductID, Quantity)
                VALUES (:transfer_id, :product_id, :quantity)
            ");

            $updateOriginInventoryStmt = $pdo->prepare("
                UPDATE WarehouseInventory
                SET Quantity = Quantity - :quantity
                WHERE WarehouseID = :warehouse_id AND AisleNr = :aisle_nr AND ProductID = :product_id
            ");

            $updateDestinationInventoryStmt = $pdo->prepare("
                INSERT INTO WarehouseInventory (WarehouseID, AisleNr, ProductID, Quantity)
                VALUES (:warehouse_id, :aisle_nr, :product_id, :quantity)
                ON DUPLICATE KEY UPDATE Quantity = Quantity + VALUES(Quantity)
            ");

            foreach ($productIDs as $index => $productID) {
                $quantity = $quantities[$index];

                // Check if the quantity is available in the origin warehouse and aisle
                $checkStmt = $pdo->prepare("
                    SELECT Quantity
                    FROM WarehouseInventory
                    WHERE WarehouseID = :warehouse_id AND AisleNr = :aisle_nr AND ProductID = :product_id
                ");
                $checkStmt->execute([
                    ':warehouse_id' => $originWarehouse,
                    ':aisle_nr' => $originAisle,
                    ':product_id' => $productID
                ]);
                $availableQuantity = $checkStmt->fetchColumn();

                if ($availableQuantity === false || $availableQuantity < $quantity) {
                    throw new Exception("Insufficient quantity for product ID $productID in the origin warehouse and aisle. Available quantity: $availableQuantity.");
                }

                // Insert transfer line
                $lineStmt->execute([
                    ':transfer_id' => $transferID,
                    ':product_id' => $productID,
                    ':quantity' => $quantity
                ]);

                // Update origin inventory
                $updateOriginInventoryStmt->execute([
                    ':warehouse_id' => $originWarehouse,
                    ':aisle_nr' => $originAisle,
                    ':product_id' => $productID,
                    ':quantity' => $quantity
                ]);

                // Update destination inventory
                $updateDestinationInventoryStmt->execute([
                    ':warehouse_id' => $destinationWarehouse,
                    ':aisle_nr' => $destinationAisle,
                    ':product_id' => $productID,
                    ':quantity' => $quantity
                ]);
            }

            $pdo->commit();
            $_SESSION['success_message'] = "Transfer created successfully.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error_message'] = $e->getMessage();
            $_SESSION['form_data'] = $_POST;
        }
        header('Location: insert_transfer_form.php');
        exit;
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>