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
        // Correctly cast input values to integers
        $originWarehouse      = isset($_POST['origin_warehouse']) ? (int)$_POST['origin_warehouse'] : 0;
        $originAisle          = isset($_POST['origin_aisle']) ? (int)$_POST['origin_aisle'] : 0;
        $destinationWarehouse = isset($_POST['destination_warehouse']) ? (int)$_POST['destination_warehouse'] : 0;
        $destinationAisle     = isset($_POST['destination_aisle']) ? (int)$_POST['destination_aisle'] : 0;
        $transferDate         = isset($_POST['transfer_date']) ? $_POST['transfer_date'] : '';

        if ($useMongoDb) {
            // -----------------------------
            //  MONGODB LOGIC WITHOUT TRANSACTIONS
            // -----------------------------
            try {
                // 1) Prepare arrays for productIDs and quantities
                $productIDs = isset($_POST['product_id']) ? $_POST['product_id'] : [];   // e.g. [101, 102, ...]
                $quantities = isset($_POST['quantity']) ? $_POST['quantity'] : [];       // e.g. [10,  5,   ...]

                if (empty($productIDs) || empty($quantities) || count($productIDs) !== count($quantities)) {
                    throw new Exception("Invalid product IDs or quantities provided.");
                }

                // Initialize a flag to track overall success
                $allSuccess = true;
                $errorMessages = [];

                // 2) Validation Phase: Check all products for sufficient quantity
                $validationResults = [];
                foreach ($productIDs as $index => $productID) {
                    $productID = (int)$productID;
                    $quantity  = (int)$quantities[$index];
                    if ($quantity <= 0) {
                        $errorMessages[] = "Quantity must be greater than 0 for Product ID $productID.";
                        $allSuccess = false;
                        continue; // Skip to the next product
                    }

                    // 2.1) Check Origin Warehouse and Aisle
                    $originWarehouseDoc = $mongoDb->Warehouse->findOne(['warehouseID' => $originWarehouse]);
                    if (!$originWarehouseDoc) {
                        $errorMessages[] = "Origin Warehouse ID $originWarehouse not found.";
                        $allSuccess = false;
                        break; // Critical error, abort validation
                    }

                    // 2.2) Find the specific origin aisle
                    $foundOriginAisle = null;
                    foreach ($originWarehouseDoc['aisles'] as $aisle) {
                        if ($aisle['AisleNr'] == $originAisle) {
                            $foundOriginAisle = $aisle;
                            break;
                        }
                    }

                    if (!$foundOriginAisle) {
                        $errorMessages[] = "Origin Aisle $originAisle not found in Warehouse $originWarehouse.";
                        $allSuccess = false;
                        break; // Critical error, abort validation
                    }

                    // 2.3) Find the product in the origin aisle's inventory
                    $foundOriginProduct = null;
                    foreach ($foundOriginAisle['inventory'] as $invItem) {
                        if ($invItem['ProductID'] == $productID) {
                            $foundOriginProduct = $invItem;
                            break;
                        }
                    }

                    if (!$foundOriginProduct) {
                        $errorMessages[] = "Product ID $productID not found in Origin Aisle $originAisle.";
                        $allSuccess = false;
                        continue; // Skip to the next product
                    }

                    // 2.4) Check if the origin has sufficient quantity
                    if ($foundOriginProduct['quantity'] < $quantity) {
                        $errorMessages[] = "Insufficient quantity for Product ID $productID in Origin (have {$foundOriginProduct['quantity']}, need $quantity).";
                        $allSuccess = false;
                        continue; // Skip to the next product
                    }

                    // Store validation result
                    $validationResults[] = [
                        'productID' => $productID,
                        'quantity'  => $quantity
                    ];
                }

                // If any validation failed, abort the entire transfer
                if (!$allSuccess) {
                    throw new Exception(implode(" ", $errorMessages));
                }

                // 3) Execution Phase: Perform updates since all validations passed

                // Ensure ProductID is treated as an integer
                $transferCount = $mongoDb->TransferHeader->countDocuments();
                $nextTransferID = $transferCount + 1;
                // 3.1) Insert the TransferHeader first
                $transferHeaderDoc = [
                    'TransferID'             => $nextTransferID,
                    'originWarehouseID'      => $originWarehouse,
                    'originAisle'            => $originAisle,
                    'destinationWarehouseID' => $destinationWarehouse,
                    'destinationAisle'       => $destinationAisle,
                    // Convert transferDate to a MongoDB UTCDateTime
                    'transferDate'           => new MongoDB\BSON\UTCDateTime(strtotime($transferDate) * 1000),
                    'lines'                  => []  // We'll push lines into this later
                ];

                $insertResult = $mongoDb->TransferHeader->insertOne($transferHeaderDoc);
                // This will be a MongoDB\BSON\ObjectId
                $transferID = $insertResult->getInsertedId();

                // 3.2) Perform updates for each validated product
                foreach ($validationResults as $result) {
                    $productID = $result['productID'];
                    $quantity  = $result['quantity'];

                    //
                    // --- A) Subtract from Origin ---
                    //
                    $updateOriginResult = $mongoDb->Warehouse->updateOne(
                        [
                            'warehouseID'       => $originWarehouse,
                            'aisles.AisleNr'    => $originAisle,
                            'aisles.inventory.ProductID' => $productID
                        ],
                        [
                            '$inc' => ['aisles.$.inventory.$[p].quantity' => -$quantity]
                        ],
                        [
                            'arrayFilters' => [
                                ['p.ProductID' => $productID]
                            ]
                        ]
                    );

                    if ($updateOriginResult->getModifiedCount() === 0) {
                        // Log the error, attempt to rollback previous changes
                        $errorMessages[] = "Failed to update origin inventory for Product ID $productID.";
                        $allSuccess = false;
                        break; // Stop execution
                    }

                    //
                    // --- B) Add to Destination ---
                    //
                    // Check if the destination warehouse exists
                    $destinationWarehouseDoc = $mongoDb->Warehouse->findOne(['warehouseID' => $destinationWarehouse]);
                    if (!$destinationWarehouseDoc) {
                        $errorMessages[] = "Destination Warehouse ID $destinationWarehouse not found.";
                        $allSuccess = false;
                        break; // Critical error, abort execution
                    }

                    // Find the specific destination aisle
                    $foundDestinationAisle = null;
                    foreach ($destinationWarehouseDoc['aisles'] as $aisle) {
                        if ($aisle['AisleNr'] == $destinationAisle) {
                            $foundDestinationAisle = $aisle;
                            break;
                        }
                    }

                    if (!$foundDestinationAisle) {
                        $errorMessages[] = "Destination Aisle $destinationAisle not found in Warehouse $destinationWarehouse.";
                        $allSuccess = false;
                        break; // Critical error, abort execution
                    }

                    // Check if the product exists in destination aisle's inventory
                    $productExistsInDestination = false;
                    foreach ($foundDestinationAisle['inventory'] as $invItem) {
                        if ($invItem['ProductID'] == $productID) {
                            $productExistsInDestination = true;
                            break;
                        }
                    }

                    if ($productExistsInDestination) {
                        // Product exists => increment
                        $updateDestinationResult = $mongoDb->Warehouse->updateOne(
                            [
                                'warehouseID'       => $destinationWarehouse,
                                'aisles.AisleNr'    => $destinationAisle,
                                'aisles.inventory.ProductID' => $productID
                            ],
                            [
                                '$inc' => ['aisles.$.inventory.$[p].quantity' => $quantity]
                            ],
                            [
                                'arrayFilters' => [
                                    ['p.ProductID' => $productID]
                                ]
                            ]
                        );

                        if ($updateDestinationResult->getModifiedCount() === 0) {
                            $errorMessages[] = "Failed to update destination inventory for Product ID $productID.";
                            $allSuccess = false;
                            break; // Stop execution
                        }
                    } else {
                        // Product does not exist => push new inventory item
                        $updateDestinationResult = $mongoDb->Warehouse->updateOne(
                            [
                                'warehouseID'    => $destinationWarehouse,
                                'aisles.AisleNr' => $destinationAisle
                            ],
                            [
                                '$push' => [
                                    'aisles.$.inventory' => [
                                        'ProductID' => $productID,
                                        'quantity'  => $quantity
                                    ]
                                ]
                            ]
                        );

                        if ($updateDestinationResult->getModifiedCount() === 0) {
                            $errorMessages[] = "Failed to add Product ID $productID to destination inventory.";
                            $allSuccess = false;
                            break; // Stop execution
                        }
                    }

                    //
                    // --- C) Insert Transfer Line into TransferHeader (for reference) ---
                    //
                    $transferLine = [
                        'productID' => $productID,
                        'quantity'  => $quantity
                    ];

                    $updateTransferHeaderResult = $mongoDb->TransferHeader->updateOne(
                        ['_id' => $transferID],
                        [
                            '$push' => [
                                'lines' => $transferLine
                            ]
                        ]
                    );

                    if ($updateTransferHeaderResult->getModifiedCount() === 0) {
                        $errorMessages[] = "Failed to add transfer line for Product ID $productID.";
                        $allSuccess = false;
                        break; // Stop execution
                    }
                }

                // 4) Post-Execution Handling
                if ($allSuccess) {
                    $_SESSION['success_message'] = "Transfer created successfully (MongoDB).";
                } else {
                    // Attempt to rollback if possible
                    // Note: Without transactions, rollback is not guaranteed
                    // For simplicity, inform the user and log the error
                    $_SESSION['error_message'] = implode(" ", $errorMessages);
                    // Optionally, you can implement manual rollback here
                }
            } catch (Exception $e) {
                // Handle any unexpected errors
                $_SESSION['error_message'] = "Transfer failed: " . $e->getMessage();
                $_SESSION['form_data'] = $_POST;
            }
        } else {
            // ----------------
            //  MYSQL LOGIC
            // ----------------
            try {
                $pdo = new PDO($dsn, $user, $pass);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("
                    INSERT INTO TransferHeader (OriginWarehouseID, OriginAisle, DestinationWarehouseID, DestinationAisle, TransferDate)
                    VALUES (:origin_warehouse, :origin_aisle, :destination_warehouse, :destination_aisle, :transfer_date)
                ");
                $stmt->execute([
                    ':origin_warehouse'      => $originWarehouse,
                    ':origin_aisle'         => $originAisle,
                    ':destination_warehouse'=> $destinationWarehouse,
                    ':destination_aisle'    => $destinationAisle,
                    ':transfer_date'        => $transferDate
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
                    $quantity = (int)$quantities[$index];

                    // Check origin inventory
                    $checkStmt = $pdo->prepare("
                        SELECT Quantity
                        FROM WarehouseInventory
                        WHERE WarehouseID = :warehouse_id AND AisleNr = :aisle_nr AND ProductID = :product_id
                    ");
                    $checkStmt->execute([
                        ':warehouse_id' => $originWarehouse,
                        ':aisle_nr'     => $originAisle,
                        ':product_id'   => $productID
                    ]);
                    $availableQuantity = $checkStmt->fetchColumn();

                    if ($availableQuantity === false || $availableQuantity < $quantity) {
                        throw new Exception("Insufficient quantity for product ID $productID in the origin warehouse and aisle. Available quantity: $availableQuantity.");
                    }

                    // Insert transfer line
                    $lineStmt->execute([
                        ':transfer_id' => $transferID,
                        ':product_id'  => $productID,
                        ':quantity'    => $quantity
                    ]);

                    // Update origin inventory
                    $updateOriginInventoryStmt->execute([
                        ':warehouse_id' => $originWarehouse,
                        ':aisle_nr'     => $originAisle,
                        ':product_id'   => $productID,
                        ':quantity'     => $quantity
                    ]);

                    // Update destination inventory
                    $updateDestinationInventoryStmt->execute([
                        ':warehouse_id' => $destinationWarehouse,
                        ':aisle_nr'     => $destinationAisle,
                        ':product_id'   => $productID,
                        ':quantity'     => $quantity
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
