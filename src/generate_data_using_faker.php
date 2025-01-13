<?php
require_once __DIR__ . '/vendor/autoload.php'; // Composer autoload

use Faker\Factory as FakerFactory;

// ----------------------
// 1) Connect to the database
// ----------------------
$host   = 'MySQLDockerContainer'; // MySQL container name
$dbname = 'IMSE_MS2';             // Database name
$user   = 'root';                 // MySQL username
$pass   = 'IMSEMS2';              // MySQL root password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (Exception $e) {
    die("Could not connect to the database. Error: " . $e->getMessage());
}

// Create Faker instance
$faker = FakerFactory::create();
$fakerUnique = $faker->unique(true); // Use unique generator

// ----------------------
// Helper arrays to store inserted IDs (to use as FKs)
// ----------------------
$warehouseIDs   = [];
$aisleMap       = []; // Map WarehouseID => array of AisleNrs
$vendorIDs      = [];
$customerIDs    = [];
$productIDs     = [];
$salesOrderIDs  = [];
$transferIDs    = [];

// --------------------------------------------------
// 2) Insert into Warehouse (AUTO_INCREMENT PK)
// --------------------------------------------------
$numWarehouses = 10; // Increase to 10 warehouses
for ($i = 0; $i < $numWarehouses; $i++) {
    $warehouseName = $fakerUnique->company . ' Warehouse';
    $address       = $faker->address;
    $category      = $faker->randomElement(['Storage', 'Distribution', 'Cold Storage', 'Retail', 'E-commerce']);

    $stmt = $pdo->prepare("
        INSERT IGNORE INTO Warehouse (WarehouseName, Address, Category) 
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$warehouseName, $address, $category]);

    $newId = $pdo->lastInsertId();
    if ($newId) {
        $warehouseIDs[] = $newId;
    }
}

// --------------------------------------------------
// 3) Insert into Aisle (Composite PK: WarehouseID + AisleNr)
// --------------------------------------------------
foreach ($warehouseIDs as $whID) {
    $aisleMap[$whID] = [];
    $numAisles = 5; // Create 5 aisles per warehouse
    for ($j = 0; $j < $numAisles; $j++) {
        $aisleName       = 'Aisle ' . $faker->word();
        $fireExtingusher = $faker->boolean() ? 1 : 0;
        $description     = $faker->sentence();

        $stmt = $pdo->prepare("
            INSERT INTO Aisle (WarehouseID, AisleName, FireExtingusher, Description)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$whID, $aisleName, $fireExtingusher, $description]);

        $stmt2 = $pdo->prepare("
            SELECT AisleNr
              FROM Aisle
             WHERE WarehouseID = ?
             ORDER BY AisleNr DESC
             LIMIT 1
        ");
        $stmt2->execute([$whID]);
        $newAisleNr = $stmt2->fetchColumn();

        $aisleMap[$whID][] = $newAisleNr;
    }
}

// --------------------------------------------------
// 4) Insert into Vendor (VendorID PK)
// --------------------------------------------------
$numVendors = 15; // Increase to 15 vendors
for ($i = 1; $i <= $numVendors; $i++) {
    $vendorID = 'V' . str_pad($i, 3, '0', STR_PAD_LEFT);
    $name     = $faker->company;
    $address  = $faker->address;
    $phone    = $faker->phoneNumber;
    $email    = $faker->unique()->safeEmail;

    $stmt = $pdo->prepare("
        INSERT IGNORE INTO Vendor (VendorID, Name, Address, PhoneNo, Email)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$vendorID, $name, $address, $phone, $email]);

    $vendorIDs[] = $vendorID;
}

// --------------------------------------------------
// 5) Insert into Customer (CustID PK)
// --------------------------------------------------
$numCustomers = 20; // Increase to 20 customers
for ($i = 1; $i <= $numCustomers; $i++) {
    $custID  = 'C' . str_pad($i, 3, '0', STR_PAD_LEFT);
    $name    = $faker->unique()->name;
    $address = $faker->address;
    $phone   = $faker->phoneNumber;
    $email   = $faker->unique()->safeEmail;

    $stmt = $pdo->prepare("
        INSERT IGNORE INTO Customer (CustID, Name, Address, PhoneNo, Email)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$custID, $name, $address, $phone, $email]);

    $customerIDs[] = $custID;
}

// --------------------------------------------------
// 6) Insert into Product (ProductID AUTO_INCREMENT)
// --------------------------------------------------
$numProducts = 50; // Create 50 products
for ($i = 0; $i < $numProducts; $i++) {
    $prodName  = $faker->unique()->word . ' ' . strtoupper($faker->randomLetter);
    $desc      = $faker->sentence(6);
    $weight    = $faker->randomFloat(2, 0.5, 5.0);
    $uom       = $faker->randomElement(['kg', 'pcs']);
    $price     = $faker->randomFloat(2, 5, 100);
    $currency  = 'EUR';

    $stmt = $pdo->prepare("
        INSERT INTO Product (Name, Description, Weight, UnitOfMeasure, Price, Currency)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$prodName, $desc, $weight, $uom, $price, $currency]);

    $productIDs[] = $pdo->lastInsertId();
}

// --------------------------------------------------
// 7) Insert into PurchaseOrder (OrderID PK)
// --------------------------------------------------
$numPurchaseOrders = 50; // Create 50 purchase orders
for ($i = 1; $i <= $numPurchaseOrders; $i++) {
    $orderID  = 'PO' . str_pad($i, 3, '0', STR_PAD_LEFT);
    $vendorID = $faker->randomElement($vendorIDs);
    $quantity = $faker->numberBetween(50, 500);
    $uom      = 'pcs';
    $price    = $faker->randomFloat(2, 500, 5000);
    $currency = 'EUR';

    $stmt = $pdo->prepare("
        INSERT IGNORE INTO PurchaseOrder (OrderID, VendorID, Quantity, UnitOfMeasure, Price, Currency)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$orderID, $vendorID, $quantity, $uom, $price, $currency]);
}

// --------------------------------------------------
// 8) Insert into SalesOrder (OrderID PK)
// --------------------------------------------------
$numSalesOrders = 50; // Create 50 sales orders
for ($i = 1; $i <= $numSalesOrders; $i++) {
    $orderID         = 'SO' . str_pad($i, 3, '0', STR_PAD_LEFT);
    $custID          = $faker->randomElement($customerIDs);
    $quantity        = $faker->numberBetween(10, 100);
    $uom             = 'pcs';
    $price           = $faker->randomFloat(2, 10, 200);
    $currency        = 'EUR';
    $totalOrderPrice = $quantity * $price;

    $stmt = $pdo->prepare("
        INSERT IGNORE INTO SalesOrder (OrderID, CustID, Quantity, UnitOfMeasure, Price, Currency, TotalOrderPrice)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$orderID, $custID, $quantity, $uom, $price, $currency, $totalOrderPrice]);

    $salesOrderIDs[] = $orderID;
}

// --------------------------------------------------
// 10) Insert into TransferHeader (AUTO_INCREMENT PK)
// --------------------------------------------------
$numTransfers = 50; // Create 50 transfers
for ($i = 0; $i < $numTransfers; $i++) {
    $originWarehouseID      = $faker->randomElement($warehouseIDs);
    $destinationWarehouseID = $faker->randomElement($warehouseIDs);
    while ($destinationWarehouseID === $originWarehouseID) {
        $destinationWarehouseID = $faker->randomElement($warehouseIDs);
    }

    $originAisle      = $faker->randomElement($aisleMap[$originWarehouseID]);
    $destinationAisle = $faker->randomElement($aisleMap[$destinationWarehouseID]);
    $transferDate     = $faker->dateTimeBetween('-1 month', '+1 month')->format('Y-m-d');

    $stmt = $pdo->prepare("
        INSERT INTO TransferHeader (OriginWarehouseID, OriginAisle, DestinationWarehouseID, DestinationAisle, TransferDate)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $originWarehouseID,
        $originAisle,
        $destinationWarehouseID,
        $destinationAisle,
        $transferDate
    ]);

    $transferIDs[] = $pdo->lastInsertId();
}

// --------------------------------------------------
// 11) Insert into TransferLines (TransferLineID AUTO_INCREMENT PK)
// --------------------------------------------------
$numTransferLines = 50; // Create 50 transfer lines
for ($i = 0; $i < $numTransferLines; $i++) {
    $transferID = $faker->randomElement($transferIDs);
    $productID  = $faker->randomElement($productIDs);
    $quantity   = $faker->numberBetween(1, 100);

    $stmt = $pdo->prepare("
        INSERT INTO TransferLines (TransferID, ProductID, Quantity)
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$transferID, $productID, $quantity]);
}

// --------------------------------------------------
// 12) Insert into WarehouseInventory (UNIQUE KEY: WarehouseID + AisleNr + ProductID)
// --------------------------------------------------
$numWarehouseInventories = 50; // Create 50 inventory records
for ($i = 0; $i < $numWarehouseInventories; $i++) {
    $randomWarehouseID = $faker->randomElement($warehouseIDs);
    $randomAisleNr     = $faker->randomElement($aisleMap[$randomWarehouseID]);
    $randomProductID   = $faker->randomElement($productIDs);
    $quantity          = $faker->numberBetween(10, 1000);

    $stmt = $pdo->prepare("
        INSERT INTO WarehouseInventory (WarehouseID, AisleNr, ProductID, Quantity)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            Quantity = Quantity + VALUES(Quantity)
    ");
    $stmt->execute([
        $randomWarehouseID,
        $randomAisleNr,
        $randomProductID,
        $quantity
    ]);
}

// Redirect back to home.php with a success message
header("Location: home.php?message=Data%20generation%20completed%20successfully");
exit;

