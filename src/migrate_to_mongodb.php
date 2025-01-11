<?php
session_start();

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json');

require_once '/var/www/html/vendor/autoload.php';

// MongoDB Configuration
$uri = 'mongodb://Irdi:Password1@MyMongoDBContainer:27017';
$mongoClient = new MongoDB\Client($uri);
$mongoDb = $mongoClient->selectDatabase('IMSE_MS2');

// Drop existing collections to avoid duplicates
// Drop existing collections to avoid duplicates
$mongoDb->Warehouse->drop();
$mongoDb->Aisle->drop();
$mongoDb->Vendor->drop();
$mongoDb->Customer->drop();
$mongoDb->Product->drop();
$mongoDb->PurchaseOrder->drop();
$mongoDb->SalesOrder->drop();
$mongoDb->SalesOrderDetails->drop();
$mongoDb->TransferHeader->drop();
$mongoDb->TransferLines->drop();
$mongoDb->WarehouseInventory->drop();

// MySQL Configuration
$mysqli = new mysqli("MySQLDockerContainer", "root", "IMSEMS2", "IMSE_MS2");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Transform and migrate data
function transformWarehouseData($warehouseRow, $aisles) {
    return [
        "_id" => (string)$warehouseRow["WarehouseID"],
        "name" => $warehouseRow["WarehouseName"],
        "address" => $warehouseRow["Address"],
        "category" => $warehouseRow["Category"],
        "aisles" => $aisles
    ];
}

function transformAisleData($aisleRow, $inventory) {
    return [
        "aisleNr" => (int)$aisleRow["AisleNr"],
        "name" => $aisleRow["AisleName"],
        "fireExtinguisher" => (bool)$aisleRow["FireExtingusher"],
        "description" => $aisleRow["Description"],
        "inventory" => $inventory
    ];
}

function transformSalesOrderData($orderRow, $details) {
    return [
        "_id" => $orderRow["OrderID"],
        "customerID" => $orderRow["CustID"],
        "totalOrderPrice" => (float)$orderRow["TotalOrderPrice"],
        "items" => $details
    ];
}

function transformTransferHeaderData($headerRow, $lines) {
    return [
        "_id" => (string)$headerRow["TransferID"],
        "originWarehouseID" => (string)$headerRow["OriginWarehouseID"],
        "destinationWarehouseID" => (string)$headerRow["DestinationWarehouseID"],
        "transferDate" => new MongoDB\BSON\UTCDateTime(strtotime($headerRow["TransferDate"]) * 1000),
        "lines" => $lines
    ];
}

// Migrate Warehouse and Aisle
$sql = "SELECT * FROM Warehouse";
$result = $mysqli->query($sql);

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $warehouseID = $row["WarehouseID"];
        
        // Fetch Aisles for this warehouse
        $aisleSql = "SELECT * FROM Aisle WHERE WarehouseID = ?";
        $aisleStmt = $mysqli->prepare($aisleSql);
        $aisleStmt->bind_param("i", $warehouseID);
        $aisleStmt->execute();
        $aisleResult = $aisleStmt->get_result();
        
        $aisles = [];
        while ($aisleRow = $aisleResult->fetch_assoc()) {
            $aisleNr = $aisleRow["AisleNr"];
            
            // Fetch Inventory for each Aisle
            $inventorySql = "SELECT * FROM WarehouseInventory WHERE WarehouseID = ? AND AisleNr = ?";
            $inventoryStmt = $mysqli->prepare($inventorySql);
            $inventoryStmt->bind_param("ii", $warehouseID, $aisleNr);
            $inventoryStmt->execute();
            $inventoryResult = $inventoryStmt->get_result();
            
            $inventory = [];
            while ($inventoryRow = $inventoryResult->fetch_assoc()) {
                $inventory[] = [
                    "productID" => (string)$inventoryRow["ProductID"],
                    "quantity" => (int)$inventoryRow["Quantity"]
                ];
            }
            
            $aisles[] = transformAisleData($aisleRow, $inventory);
        }
        
        $warehouse = transformWarehouseData($row, $aisles);
        $mongoDb->Warehouse->insertOne($warehouse);
    }
}

// Migrate SalesOrder and SalesOrderDetails
$sql = "SELECT * FROM SalesOrder";
$result = $mysqli->query($sql);

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $orderID = $row["OrderID"];
        
        // Fetch Order Details
        $detailsSql = "SELECT * FROM SalesOrderDetails WHERE OrderID = ?";
        $detailsStmt = $mysqli->prepare($detailsSql);
        $detailsStmt->bind_param("s", $orderID);
        $detailsStmt->execute();
        $detailsResult = $detailsStmt->get_result();
        
        $details = [];
        while ($detailsRow = $detailsResult->fetch_assoc()) {
            $details[] = [
                "productID" => (string)$detailsRow["ProductID"],
                "quantity" => (int)$detailsRow["Quantity"],
                "price" => (float)$detailsRow["Price"]
            ];
        }
        
        $order = transformSalesOrderData($row, $details);
        $mongoDb->SalesOrder->insertOne($order);
    }
}

// Migrate TransferHeader and TransferLines
$sql = "SELECT * FROM TransferHeader";
$result = $mysqli->query($sql);

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $transferID = $row["TransferID"];
        
        // Fetch Transfer Lines
        $linesSql = "SELECT * FROM TransferLines WHERE TransferID = ?";
        $linesStmt = $mysqli->prepare($linesSql);
        $linesStmt->bind_param("i", $transferID);
        $linesStmt->execute();
        $linesResult = $linesStmt->get_result();
        
        $lines = [];
        while ($lineRow = $linesResult->fetch_assoc()) {
            $lines[] = [
                "productID" => (string)$lineRow["ProductID"],
                "quantity" => (int)$lineRow["Quantity"]
            ];
        }
        
        $transferHeader = transformTransferHeaderData($row, $lines);
        $mongoDb->TransferHeader->insertOne($transferHeader);
    }
}

// Migrate standalone collections (Vendor, Customer, Product)
$standaloneTables = ['Vendor', 'Customer', 'Product'];
foreach ($standaloneTables as $table) {
    $sql = "SELECT * FROM $table";
    $result = $mysqli->query($sql);

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $mongoDb->$table->insertOne($row);
        }
    }
}

$mysqli->close();

// Set session variable to indicate migration to MongoDB
$_SESSION['use_mongodb'] = true;

// Redirect back to home.php with a success message
header("Location: home.php?message=Data%20migration%20completed%20successfully");
exit;