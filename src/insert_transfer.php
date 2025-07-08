<?php
session_start();
require_once __DIR__ . '/db.php';

try {
    $useMongoDb = isset($_SESSION['use_mongodb']) && $_SESSION['use_mongodb'] === true;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        $originWarehouse      = isset($_POST['origin_warehouse']) ? (int)$_POST['origin_warehouse'] : 0;
        $originAisle          = isset($_POST['origin_aisle']) ? (int)$_POST['origin_aisle'] : 0;
        $destinationWarehouse = isset($_POST['destination_warehouse']) ? (int)$_POST['destination_warehouse'] : 0;
        $destinationAisle     = isset($_POST['destination_aisle']) ? (int)$_POST['destination_aisle'] : 0;
        $transferDate         = isset($_POST['transfer_date']) ? $_POST['transfer_date'] : '';

        if ($useMongoDb) {

            try {
                $mongoDb = getMongoDb();
                $productIDs = isset($_POST['product_id']) ? $_POST['product_id'] : [];   // e.g. [101, 102, ...]
                $quantities = isset($_POST['quantity']) ? $_POST['quantity'] : [];       // e.g. [10,  5,   ...]

                if (empty($productIDs) || empty($quantities) || count($productIDs) !== count($quantities)) {
                    throw new Exception("Invalid product IDs or quantities provided.");
                }

                $allSuccess = true;
                $errorMessages = [];

                $validationResults = [];
                foreach ($productIDs as $index => $productID) {
                    $productID = (int)$productID;
                    $quantity  = (int)$quantities[$index];
                    if ($quantity <= 0) {
                        $errorMessages[] = "Quantity must be greater than 0 for Product ID $productID.";
                        $allSuccess = false;
                        continue;
                    }

                    $originWarehouseDoc = $mongoDb->Warehouse->findOne(['warehouseID' => $originWarehouse]);
                    if (!$originWarehouseDoc) {
                        $errorMessages[] = "Origin Warehouse ID $originWarehouse not found.";
                        $allSuccess = false;
                        break;
                    }

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
                        break;
                    }

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
                        continue;
                    }

                    if ($foundOriginProduct['quantity'] < $quantity) {
                        $errorMessages[] = "Insufficient quantity for Product ID $productID in Origin (have {$foundOriginProduct['quantity']}, need $quantity).";
                        $allSuccess = false;
                        continue;
                    }

                    $validationResults[] = [
                        'productID' => $productID,
                        'quantity'  => $quantity
                    ];
                }

                if (!$allSuccess) {
                    throw new Exception(implode(" ", $errorMessages));
                }


                $transferCount = $mongoDb->TransferHeader->countDocuments();
                $nextTransferID = $transferCount + 1;

                $transferHeaderDoc = [
                    'TransferID'             => $nextTransferID,
                    'originWarehouseID'      => $originWarehouse,
                    'originAisle'            => $originAisle,
                    'destinationWarehouseID' => $destinationWarehouse,
                    'destinationAisle'       => $destinationAisle,
                    'transferDate'           => new MongoDB\BSON\UTCDateTime(strtotime($transferDate) * 1000),
                    'lines'                  => []
                ];

                $insertResult = $mongoDb->TransferHeader->insertOne($transferHeaderDoc);
                $transferID = $insertResult->getInsertedId();

                foreach ($validationResults as $result) {
                    $productID = $result['productID'];
                    $quantity  = $result['quantity'];

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
                        $errorMessages[] = "Failed to update origin inventory for Product ID $productID.";
                        $allSuccess = false;
                        break; 
                    }

                    $destinationWarehouseDoc = $mongoDb->Warehouse->findOne(['warehouseID' => $destinationWarehouse]);
                    if (!$destinationWarehouseDoc) {
                        $errorMessages[] = "Destination Warehouse ID $destinationWarehouse not found.";
                        $allSuccess = false;
                        break;
                    }

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
                        break;
                    }

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
                            break;
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
                            break;
                        }
                    }

                    $transferLine = [
                        'TransferID' => $nextTransferID,
                        'ProductID' => $productID,
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
                        break;
                    }
                }


                if ($allSuccess) {
                    $_SESSION['success_message'] = "Transfer created successfully (MongoDB).";
                    header('Location: view_transfer.php?TransferID=' . $nextTransferID);
                    exit;
                } else {
                    $_SESSION['error_message'] = implode(" ", $errorMessages);
                    header('Location: insert_transfer_form.php');
                    exit;
                }
            } catch (Exception $e) {

                $_SESSION['error_message'] = "Transfer failed: " . $e->getMessage();
                $_SESSION['form_data'] = $_POST;
                header('Location: insert_transfer_form.php');
                exit;
            }
        } else {
            try {
                $pdo = getPDO();
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

                    $lineStmt->execute([
                        ':transfer_id' => $transferID,
                        ':product_id'  => $productID,
                        ':quantity'    => $quantity
                    ]);

                    $updateOriginInventoryStmt->execute([
                        ':warehouse_id' => $originWarehouse,
                        ':aisle_nr'     => $originAisle,
                        ':product_id'   => $productID,
                        ':quantity'     => $quantity
                    ]);

                    $updateDestinationInventoryStmt->execute([
                        ':warehouse_id' => $destinationWarehouse,
                        ':aisle_nr'     => $destinationAisle,
                        ':product_id'   => $productID,
                        ':quantity'     => $quantity
                    ]);
                }

                $pdo->commit();
                $_SESSION['success_message'] = "Transfer created successfully.";
                header('Location: view_transfer.php?TransferID=' . $transferID);
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $_SESSION['error_message'] = $e->getMessage();
                $_SESSION['form_data'] = $_POST;
                header('Location: insert_transfer_form.php');
                exit;
            }
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
