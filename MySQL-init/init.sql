DROP TABLE IF EXISTS `WarehouseInventory`;
DROP TABLE IF EXISTS `TransferLines`;
DROP TABLE IF EXISTS `TransferHeader`;
DROP TABLE IF EXISTS `SalesOrder`;
DROP TABLE IF EXISTS `PurchaseOrder`;
DROP TABLE IF EXISTS `Aisle`;
DROP TABLE IF EXISTS `Vendor`;
DROP TABLE IF EXISTS `Customer`;
DROP TABLE IF EXISTS `Product`;
DROP TABLE IF EXISTS `Warehouse`;

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