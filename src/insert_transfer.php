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

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $originWarehouse = $_POST['origin_warehouse'];
        $originAisle = $_POST['origin_aisle'];
        $destinationWarehouse = $_POST['destination_warehouse'];
        $destinationAisle = $_POST['destination_aisle'];
        $transferDate = $_POST['transfer_date'];

        if ($useMongoDb) {
            try {
                // Insert the transfer header into MongoDB
                $transferHeader = [
                    'originWarehouseID' => $originWarehouse,
                    'originAisle' => $originAisle,
                    'destinationWarehouseID' => $destinationWarehouse,
                    'destinationAisle' => $destinationAisle,
                    'transferDate' => new MongoDB\BSON\UTCDateTime(strtotime($transferDate) * 1000),
                    'lines' => []
                ];
        
                $insertResult = $mongoDb->TransferHeader->insertOne($transferHeader);
                $transferID = (string)$insertResult->getInsertedId();
        
                // Insert the transfer lines and update inventory
                $productIDs = $_POST['product_id'];
                $quantities = $_POST['quantity'];
        
                $session = $mongoClient->startSession();
                $session->startTransaction();
        
                try {
                    foreach ($productIDs as $index => $productID) {
                        $quantity = (int)$quantities[$index]; // Ensure quantity is an integer
        
                        // Check origin inventory
                        $originInventory = $mongoDb->WarehouseInventory->findOne([
                            'WarehouseID' => $originWarehouse,
                            'aisleNr' => $originAisle,
                            'productID' => (int)$productID // Ensure productID is an integer
                        ]);
        
                        if (!$originInventory || $originInventory['quantity'] < $quantity) {
                            throw new Exception("Insufficient quantity for product ID $productID in the origin warehouse and aisle. Available: " . ($originInventory['quantity'] ?? 0) . ", Requested: $quantity");
                        }
        
                        // Update origin inventory
                        $result = $mongoDb->WarehouseInventory->updateOne(
                            [
                                'WarehouseID' => $originWarehouse,
                                'aisleNr' => $originAisle,
                                'productID' => (int)$productID
                            ],
                            ['$inc' => ['quantity' => -$quantity]]
                        );
        
                        if ($result->getModifiedCount() === 0) {
                            // No document was updated, meaning the inventory was altered since our check
                            throw new Exception("Inventory for product ID $productID has changed since last check.");
                        }
        
                        // Update destination inventory
                        $mongoDb->WarehouseInventory->updateOne(
                            [
                                'WarehouseID' => $destinationWarehouse,
                                'aisleNr' => $destinationAisle,
                                'productID' => (int)$productID
                            ],
                            ['$inc' => ['quantity' => $quantity]],
                            ['upsert' => true]
                        );
        
                        // Add transfer line
                        $mongoDb->TransferHeader->updateOne(
                            ['_id' => new MongoDB\BSON\ObjectId($transferID)],
                            ['$push' => ['lines' => [
                                'productID' => (int)$productID,
                                'quantity' => $quantity
                            ]]]
                        );
                    }
        
                    $session->commitTransaction();
                    $_SESSION['success_message'] = "Transfer created successfully.";
                } catch (Exception $e) {
                    $session->abortTransaction();
                    throw $e; // Re-throw to be caught outside the transaction loop
                }
            } catch (Exception $e) {
                $_SESSION['error_message'] = $e->getMessage();
                $_SESSION['form_data'] = $_POST;
            }
        } else {
            // MySQL logic
            $pdo = new PDO($dsn, $user, $pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
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

                $transferID = $pdo->lastInsertId();

                // Insert transfer lines and update inventory
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

                    // Check origin inventory
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
        }

        header('Location: insert_transfer_form.php');
        exit;
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
