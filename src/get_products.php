<?php
$host = 'MySQLDockerContainer';
$db = 'IMSE_MS2';
$user = 'root';
$pass = 'IMSEMS2';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if (isset($_GET['warehouse_id']) && isset($_GET['aisle_nr'])) {
        $warehouseID = $_GET['warehouse_id'];
        $aisleNr = $_GET['aisle_nr'];

        $stmt = $pdo->prepare("
            SELECT P.ProductID, P.Name, WI.Quantity
              FROM WarehouseInventory WI
              JOIN Product P ON WI.ProductID = P.ProductID
             WHERE WI.WarehouseID = :warehouse_id
               AND WI.AisleNr = :aisle_nr
        ");
        $stmt->execute(['warehouse_id' => $warehouseID, 'aisle_nr' => $aisleNr]);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode($products);
    } else {
        echo json_encode(['error' => 'Warehouse ID and Aisle Nr not provided']);
    }
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>