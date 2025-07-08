<?php
session_start();

// Allow CORS (if needed)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json');

require_once __DIR__ . '/db.php';

// MongoDB Configuration
$mongoDb = getMongoDb();

$mongoDb->Warehouse->drop();
$mongoDb->Aisle->drop();
$mongoDb->Vendor->drop();
$mongoDb->Customer->drop();
$mongoDb->Product->drop();
$mongoDb->PurchaseOrder->drop();
$mongoDb->SalesOrder->drop();
$mongoDb->TransferHeader->drop();
$mongoDb->TransferLines->drop();
$mongoDb->WarehouseInventory->drop();

$mysqli = getMySQLi();

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

function transformWarehouseData($warehouseRow, $aisles) {
    return [
        "warehouseID"     => (int)$warehouseRow["WarehouseID"],
        "name"    => $warehouseRow["WarehouseName"],
        "address" => $warehouseRow["Address"],
        "category"=> $warehouseRow["Category"],
        "aisles"  => $aisles
    ];
}

function transformAisleData($aisleRow, $inventory) {
    return [
        "AisleNr"         => (int)$aisleRow["AisleNr"],
        "Name"            => $aisleRow["AisleName"],
        "fireExtinguisher"=> (bool)$aisleRow["FireExtingusher"],
        "description"     => $aisleRow["Description"],
        "inventory"       => $inventory
    ];
}

function transformTransferHeaderData($headerRow, $lines) {
    return [
        "TransferID"                    => (int)$headerRow["TransferID"],
        "originWarehouseID"      => (int)$headerRow["OriginWarehouseID"],
        "destinationWarehouseID" => (int)$headerRow["DestinationWarehouseID"],
        "originAisle"            => (int)$headerRow["OriginAisle"],
        "destinationAisle"       => (int)$headerRow["DestinationAisle"],
        "transferDate"           => new MongoDB\BSON\UTCDateTime(strtotime($headerRow["TransferDate"]) * 1000),
        "lines"                  => $lines
    ];
}

$sql = "SELECT * FROM Warehouse";
$result = $mysqli->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $warehouseID = $row["WarehouseID"];

        $aisleSql = "SELECT * FROM Aisle WHERE WarehouseID = ?";
        $aisleStmt = $mysqli->prepare($aisleSql);
        $aisleStmt->bind_param("i", $warehouseID);
        $aisleStmt->execute();
        $aisleResult = $aisleStmt->get_result();
        
        $aisles = [];
        while ($aisleRow = $aisleResult->fetch_assoc()) {
            $aisleNr = $aisleRow["AisleNr"];
            
            $inventorySql = "SELECT * FROM WarehouseInventory WHERE WarehouseID = ? AND AisleNr = ?";
            $inventoryStmt = $mysqli->prepare($inventorySql);
            $inventoryStmt->bind_param("ii", $warehouseID, $aisleNr);
            $inventoryStmt->execute();
            $inventoryResult = $inventoryStmt->get_result();
            
            $inventory = [];
            while ($inventoryRow = $inventoryResult->fetch_assoc()) {
                $inventory[] = [
                    "ProductID" => (int)$inventoryRow["ProductID"],
                    "quantity"  => (int)$inventoryRow["Quantity"]
                ];
            }
            
            $aisles[] = transformAisleData($aisleRow, $inventory);
        }
        
        $warehouseDoc = transformWarehouseData($row, $aisles);
        $mongoDb->Warehouse->insertOne($warehouseDoc);
    }
}

$sql = "SELECT * FROM TransferHeader";
$result = $mysqli->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $transferID = $row["TransferID"];
        
        $linesSql = "SELECT * FROM TransferLines WHERE TransferID = ?";
        $linesStmt = $mysqli->prepare($linesSql);
        $linesStmt->bind_param("i", $transferID);
        $linesStmt->execute();
        $linesResult = $linesStmt->get_result();
        
        $lines = [];
        while ($lineRow = $linesResult->fetch_assoc()) {
            $lines[] = [
                "TransferID" => (int)$lineRow["TransferID"], 
                "ProductID"  => (int)$lineRow["ProductID"],
                "quantity"   => (int)$lineRow["Quantity"]
            ];
        }
        
        $transferHeader = transformTransferHeaderData($row, $lines);
        $mongoDb->TransferHeader->insertOne($transferHeader);
    }
}

$standaloneTables = ['Vendor', 'Customer', 'Product', 'PurchaseOrder', 'SalesOrder'];
foreach ($standaloneTables as $table) {
    $sql = "SELECT * FROM $table";
    $result = $mysqli->query($sql);

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            if ($table === 'Product') {
                $row['ProductID'] = (int)$row['ProductID'];
            }
            $mongoDb->$table->insertOne($row);
        }
    }
}

$mysqli->close();

// Create indexes
try {    
    $mongoDb->Warehouse->createIndex(['warehouseID' => 1], ['unique' => true]);
    $mongoDb->Warehouse->createIndex(['aisles.AisleNr' => 1]);
    $mongoDb->Warehouse->createIndex(['aisles.inventory.ProductID' => 1]);
    
    $mongoDb->Product->createIndex(['ProductID' => 1], ['unique' => true]);
    
    $mongoDb->TransferHeader->createIndex(['TransferID' => 1], ['unique' => true]);
    $mongoDb->TransferHeader->createIndex(['originWarehouseID' => 1]);
    $mongoDb->TransferHeader->createIndex(['destinationWarehouseID' => 1]);
    
} catch (Exception $e) {
    echo "Error creating indexes: " . $e->getMessage() . "\n";
}

$_SESSION['use_mongodb'] = true;

// Redirect to home
header("Location: home.php?message=Data%20migration%20completed%20successfully");
exit;
