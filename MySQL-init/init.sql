-- -----------------------------------------------------
-- 1) DROP TABLES in reverse dependency order
--    (Children dropped before Parents)
-- -----------------------------------------------------
DROP TABLE IF EXISTS `WarehouseInventory`;
DROP TABLE IF EXISTS `TransferLines`;
DROP TABLE IF EXISTS `TransferHeader`;
DROP TABLE IF EXISTS `SalesOrderDetails`;
DROP TABLE IF EXISTS `SalesOrder`;
DROP TABLE IF EXISTS `PurchaseOrder`;
DROP TABLE IF EXISTS `Aisle`;
DROP TABLE IF EXISTS `Vendor`;
DROP TABLE IF EXISTS `Customer`;
DROP TABLE IF EXISTS `Product`;
DROP TABLE IF EXISTS `Warehouse`;

-- -----------------------------------------------------
-- 2) CREATE TABLES in correct dependency order
--    (Parents created before Children)
-- -----------------------------------------------------

-- 2.1) Warehouse
CREATE TABLE `Warehouse` (
  `WarehouseID` int NOT NULL AUTO_INCREMENT,
  `WarehouseName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `Address` varchar(255) DEFAULT NULL,
  `Category` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`WarehouseID`),
  UNIQUE KEY `idx_WarehouseNr` (`WarehouseName`)
) ENGINE=InnoDB 
  DEFAULT CHARSET=utf8mb4 
  COLLATE=utf8mb4_0900_ai_ci;

-- 2.2) Aisle (references Warehouse)
CREATE TABLE `Aisle` (
  `WarehouseID` int NOT NULL,
  `AisleNr` int NOT NULL,
  `AisleName` varchar(20) NOT NULL,
  `FireExtingusher` tinyint NOT NULL,
  `Description` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`WarehouseID`,`AisleNr`),
  KEY `idx_WarehouseID` (`WarehouseID`),
  CONSTRAINT `Aisle_ibfk_1` 
    FOREIGN KEY (`WarehouseID`) REFERENCES `Warehouse` (`WarehouseID`) ON DELETE CASCADE
) ENGINE=InnoDB 
  DEFAULT CHARSET=utf8mb4 
  COLLATE=utf8mb4_0900_ai_ci;

-- Create the trigger for Aisle AFTER the Aisle table is created
DELIMITER ;;
CREATE TRIGGER `auto_increment_aislenr`
BEFORE INSERT ON `Aisle`
FOR EACH ROW
BEGIN
  DECLARE max_aisle INT;
  SELECT COALESCE(MAX(AisleNr), 0) + 1
    INTO max_aisle
    FROM `Aisle`
   WHERE WarehouseID = NEW.WarehouseID;

  SET NEW.AisleNr = max_aisle;
END;;
DELIMITER ;

-- 2.3) Vendor
CREATE TABLE `Vendor` (
  `VendorID` varchar(20) NOT NULL,
  `Name` varchar(100) NOT NULL,
  `Address` varchar(255) DEFAULT NULL,
  `PhoneNo` varchar(15) DEFAULT NULL,
  `Email` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`VendorID`)
) ENGINE=InnoDB 
  DEFAULT CHARSET=utf8mb4 
  COLLATE=utf8mb4_0900_ai_ci;

-- 2.4) Customer
CREATE TABLE `Customer` (
  `CustID` varchar(20) NOT NULL,
  `Name` varchar(100) NOT NULL,
  `Address` varchar(255) DEFAULT NULL,
  `PhoneNo` varchar(15) DEFAULT NULL,
  `Email` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`CustID`)
) ENGINE=InnoDB 
  DEFAULT CHARSET=utf8mb4 
  COLLATE=utf8mb4_0900_ai_ci;

-- 2.5) Product
CREATE TABLE `Product` (
  `ProductID` int NOT NULL AUTO_INCREMENT,
  `Name` varchar(100) NOT NULL,
  `Description` varchar(255) DEFAULT NULL,
  `Weight` decimal(10,2) DEFAULT NULL,
  `UnitOfMeasure` varchar(50) DEFAULT NULL,
  `Price` decimal(10,2) DEFAULT NULL,
  `Currency` varchar(10) DEFAULT NULL,
  PRIMARY KEY (`ProductID`)
) ENGINE=InnoDB 
  DEFAULT CHARSET=utf8mb4 
  COLLATE=utf8mb4_0900_ai_ci;

-- 2.6) PurchaseOrder (references Vendor)
CREATE TABLE `PurchaseOrder` (
  `OrderID` varchar(20) NOT NULL,
  `VendorID` varchar(20) DEFAULT NULL,
  `Quantity` int DEFAULT NULL,
  `UnitOfMeasure` varchar(50) DEFAULT NULL,
  `Price` decimal(10,2) DEFAULT NULL,
  `Currency` varchar(10) DEFAULT NULL,
  PRIMARY KEY (`OrderID`),
  KEY `VendorID` (`VendorID`),
  CONSTRAINT `PurchaseOrder_ibfk_1`
    FOREIGN KEY (`VendorID`) REFERENCES `Vendor` (`VendorID`)
) ENGINE=InnoDB 
  DEFAULT CHARSET=utf8mb4 
  COLLATE=utf8mb4_0900_ai_ci;

-- 2.7) SalesOrder (references Customer)
CREATE TABLE `SalesOrder` (
  `OrderID` varchar(20) NOT NULL,
  `CustID` varchar(20) DEFAULT NULL,
  `Quantity` int DEFAULT NULL,
  `UnitOfMeasure` varchar(50) DEFAULT NULL,
  `Price` decimal(10,2) DEFAULT NULL,
  `Currency` varchar(10) DEFAULT NULL,
  `TotalOrderPrice` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`OrderID`),
  KEY `CustID` (`CustID`),
  CONSTRAINT `SalesOrder_ibfk_1`
    FOREIGN KEY (`CustID`) REFERENCES `Customer` (`CustID`)
) ENGINE=InnoDB 
  DEFAULT CHARSET=utf8mb4 
  COLLATE=utf8mb4_0900_ai_ci;

-- 2.8) SalesOrderDetails (references SalesOrder & Product)
CREATE TABLE `SalesOrderDetails` (
  `OrderID` varchar(20) NOT NULL,
  `ProductID` int NOT NULL,
  `Quantity` int DEFAULT NULL,
  `Price` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`OrderID`,`ProductID`),
  KEY `ProductID` (`ProductID`),
  CONSTRAINT `SalesOrderDetails_ibfk_1`
    FOREIGN KEY (`OrderID`) REFERENCES `SalesOrder` (`OrderID`),
  CONSTRAINT `SalesOrderDetails_ibfk_2`
    FOREIGN KEY (`ProductID`) REFERENCES `Product` (`ProductID`)
) ENGINE=InnoDB 
  DEFAULT CHARSET=utf8mb4 
  COLLATE=utf8mb4_0900_ai_ci;

-- 2.9) TransferHeader (references Warehouse)
CREATE TABLE `TransferHeader` (
  `TransferID` int NOT NULL AUTO_INCREMENT,
  `OriginWarehouseID` int DEFAULT NULL,
  `OriginAisle` int DEFAULT NULL,
  `DestinationWarehouseID` int DEFAULT NULL,
  `DestinationAisle` int DEFAULT NULL,
  `TransferDate` date DEFAULT NULL,
  PRIMARY KEY (`TransferID`),
  KEY `OriginWarehouseID` (`OriginWarehouseID`),
  KEY `DestinationWarehouseID` (`DestinationWarehouseID`),
  CONSTRAINT `TransferHeader_ibfk_1`
    FOREIGN KEY (`OriginWarehouseID`) REFERENCES `Warehouse` (`WarehouseID`),
  CONSTRAINT `TransferHeader_ibfk_2`
    FOREIGN KEY (`DestinationWarehouseID`) REFERENCES `Warehouse` (`WarehouseID`)
) ENGINE=InnoDB 
  DEFAULT CHARSET=utf8mb4 
  COLLATE=utf8mb4_0900_ai_ci;

-- 2.10) TransferLines (references TransferHeader & Product)
CREATE TABLE `TransferLines` (
  `TransferLineID` int NOT NULL AUTO_INCREMENT,
  `TransferID` int DEFAULT NULL,
  `ProductID` int DEFAULT NULL,
  `Quantity` int DEFAULT NULL,
  PRIMARY KEY (`TransferLineID`),
  KEY `TransferID` (`TransferID`),
  KEY `ProductID` (`ProductID`),
  CONSTRAINT `TransferLines_ibfk_1`
    FOREIGN KEY (`TransferID`) REFERENCES `TransferHeader` (`TransferID`) ON DELETE CASCADE,
  CONSTRAINT `TransferLines_ibfk_2`
    FOREIGN KEY (`ProductID`) REFERENCES `Product` (`ProductID`)
) ENGINE=InnoDB 
  DEFAULT CHARSET=utf8mb4 
  COLLATE=utf8mb4_0900_ai_ci;

-- 2.11) WarehouseInventory (references Aisle & Product)
CREATE TABLE `WarehouseInventory` (
  `InventoryID` int NOT NULL AUTO_INCREMENT,
  `WarehouseID` int NOT NULL,
  `AisleNr` int NOT NULL,
  `ProductID` int NOT NULL,
  `Quantity` int DEFAULT NULL,
  PRIMARY KEY (`InventoryID`),
  UNIQUE KEY `unique_inventory` (`WarehouseID`,`AisleNr`,`ProductID`),
  KEY `idx_WarehouseID` (`WarehouseID`),
  KEY `idx_AisleNr` (`AisleNr`),
  KEY `idx_ProductID` (`ProductID`),
  KEY `WarehouseInventory_ibfk_1` (`WarehouseID`,`AisleNr`),
  CONSTRAINT `WarehouseInventory_ibfk_1`
    FOREIGN KEY (`WarehouseID`, `AisleNr`)
    REFERENCES `Aisle` (`WarehouseID`, `AisleNr`) ON DELETE CASCADE,
  CONSTRAINT `WarehouseInventory_ibfk_2`
    FOREIGN KEY (`ProductID`) REFERENCES `Product` (`ProductID`)
) ENGINE=InnoDB 
  DEFAULT CHARSET=utf8mb4 
  COLLATE=utf8mb4_0900_ai_ci;



-- Insert into Warehouse
INSERT INTO `Warehouse` (`WarehouseID`, `WarehouseName`, `Address`, `Category`) VALUES
(1, 'Central Warehouse', '123 Main St, Vienna', 'Storage'),
(2, 'North Warehouse', '456 North Ave, Vienna', 'Distribution'),
(3, 'South Warehouse', '789 South Blvd, Vienna', 'Cold Storage'),
(4, 'East Warehouse', '321 East Rd, Vienna', 'Retail'),
(5, 'West Warehouse', '654 West Pkwy, Vienna', 'E-commerce');

-- Insert into Aisle
INSERT INTO `Aisle` (`WarehouseID`, `AisleNr`, `AisleName`, `FireExtingusher`, `Description`) VALUES
(1, 1, 'Aisle 1', 1, 'General goods'),
(1, 2, 'Aisle 2', 0, 'Electronics'),
(2, 1, 'Aisle 3', 1, 'Furniture'),
(3, 1, 'Aisle 4', 0, 'Perishables'),
(4, 1, 'Aisle 5', 1, 'Clothing');

-- Insert into Vendor
INSERT INTO `Vendor` (`VendorID`, `Name`, `Address`, `PhoneNo`, `Email`) VALUES
('V001', 'Supply Co', '10 Supplier Lane, Vienna', '1231231234', 'supply@example.com'),
('V002', 'Parts Inc', '20 Parts Way, Vienna', '3213214321', 'parts@example.com'),
('V003', 'Equipment Ltd', '30 Equip Dr, Vienna', '4564566543', 'equip@example.com'),
('V004', 'Goods Co', '40 Goods Blvd, Vienna', '7897899876', 'goods@example.com'),
('V005', 'Services GmbH', '50 Service Rd, Vienna', '9879876789', 'service@example.com');

-- Insert into Customer
INSERT INTO `Customer` (`CustID`, `Name`, `Address`, `PhoneNo`, `Email`) VALUES
('C001', 'John Doe', '101 First St, Vienna', '1234567890', 'john.doe@example.com'),
('C002', 'Jane Smith', '202 Second St, Vienna', '0987654321', 'jane.smith@example.com'),
('C003', 'Alice Brown', '303 Third St, Vienna', '5678901234', 'alice.brown@example.com'),
('C004', 'Bob White', '404 Fourth St, Vienna', '4567890123', 'bob.white@example.com'),
('C005', 'Charlie Black', '505 Fifth St, Vienna', '3456789012', 'charlie.black@example.com');

-- Insert into Product
INSERT INTO `Product` (`ProductID`, `Name`, `Description`, `Weight`, `UnitOfMeasure`, `Price`, `Currency`) VALUES
(1, 'Widget A', 'A versatile widget', 2.50, 'kg', 10.00, 'EUR'),
(2, 'Widget B', 'A specialized widget', 1.20, 'kg', 15.00, 'EUR'),
(3, 'Gadget C', 'A compact gadget', 0.80, 'kg', 20.00, 'EUR'),
(4, 'Tool D', 'A handy tool', 3.00, 'kg', 25.00, 'EUR'),
(5, 'Tool E', 'An advanced tool', 2.75, 'kg', 30.00, 'EUR');

-- Insert into PurchaseOrder
INSERT INTO `PurchaseOrder` (`OrderID`, `VendorID`, `Quantity`, `UnitOfMeasure`, `Price`, `Currency`) VALUES
('PO001', 'V001', 100, 'pcs', 1000.00, 'EUR'),
('PO002', 'V002', 200, 'pcs', 1500.00, 'EUR'),
('PO003', 'V003', 300, 'pcs', 2000.00, 'EUR'),
('PO004', 'V004', 400, 'pcs', 2500.00, 'EUR'),
('PO005', 'V005', 500, 'pcs', 3000.00, 'EUR');

-- Insert into SalesOrder
INSERT INTO `SalesOrder` (`OrderID`, `CustID`, `Quantity`, `UnitOfMeasure`, `Price`, `Currency`, `TotalOrderPrice`) VALUES
('SO001', 'C001', 10, 'pcs', 100.00, 'EUR', 1000.00),
('SO002', 'C002', 20, 'pcs', 200.00, 'EUR', 2000.00),
('SO003', 'C003', 30, 'pcs', 300.00, 'EUR', 3000.00),
('SO004', 'C004', 40, 'pcs', 400.00, 'EUR', 4000.00),
('SO005', 'C005', 50, 'pcs', 500.00, 'EUR', 5000.00);

-- Insert into SalesOrderDetails
INSERT INTO `SalesOrderDetails` (`OrderID`, `ProductID`, `Quantity`, `Price`) VALUES
('SO001', 1, 10, 100.00),
('SO002', 2, 20, 200.00),
('SO003', 3, 30, 300.00),
('SO004', 4, 40, 400.00),
('SO005', 5, 50, 500.00);

-- Insert into TransferHeader
INSERT INTO `TransferHeader` (`TransferID`, `OriginWarehouseID`, `OriginAisle`, `DestinationWarehouseID`, `DestinationAisle`, `TransferDate`) VALUES
(1, 1, 1, 2, 1, '2025-01-01'),
(2, 2, 1, 3, 1, '2025-01-02'),
(3, 3, 1, 4, 1, '2025-01-03'),
(4, 4, 1, 5, 1, '2025-01-04'),
(5, 5, 1, 1, 1, '2025-01-05'),
(6, 1, 1, 2, 1, '2025-01-09');

-- Insert into TransferLines
INSERT INTO `TransferLines` (`TransferLineID`, `TransferID`, `ProductID`, `Quantity`) VALUES
(1, 1, 1, 10),
(2, 2, 2, 20),
(3, 3, 3, 30),
(4, 4, 4, 40),
(5, 5, 5, 50),
(6, 6, 1, 50);

-- Insert into WarehouseInventory
INSERT INTO `WarehouseInventory` (`InventoryID`, `WarehouseID`, `AisleNr`, `ProductID`, `Quantity`) VALUES
(1, 1, 1, 1, 50),
(2, 1, 2, 2, 200),
(3, 2, 1, 3, 300),
(4, 3, 1, 4, 400),
(5, 4, 1, 5, 500),
(6, 2, 1, 1, 50);