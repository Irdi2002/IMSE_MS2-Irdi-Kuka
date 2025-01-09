<?php
$host = 'MySQLDockerContainer';
$db = 'IMSE_MS2';
$user = 'root';
$pass = 'IMSEMS2';

header('Content-Type: application/json');

if (isset($_GET['warehouse_id'])) {
    $warehouseID = $_GET['warehouse_id'];

    try {
        $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
        $stmt = $pdo->prepare("SELECT AisleNr, AisleName FROM Aisle WHERE WarehouseID = :warehouse_id");
        $stmt->execute(['warehouse_id' => $warehouseID]);
        $aisles = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode($aisles);
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'Warehouse ID not provided']);
}
?>