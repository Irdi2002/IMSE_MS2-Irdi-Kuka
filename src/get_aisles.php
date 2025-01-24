<?php
require_once '/var/www/html/vendor/autoload.php';

$host = 'MySQLDockerContainer';
$db = 'IMSE_MS2';
$user = 'root';
$pass = 'IMSEMS2';

header('Content-Type: application/json');

if (isset($_GET['warehouse_id'])) {
    $warehouseID = $_GET['warehouse_id'];
    $useMongoDB = isset($_GET['useMongoDB']) && $_GET['useMongoDB'] === 'true';

    if ($useMongoDB) {
        try {
            $mongoUri = 'mongodb://Irdi:Password1@MyMongoDBContainer:27017';
            $mongoClient = new MongoDB\Client($mongoUri);
            $mongoDb = $mongoClient->selectDatabase('IMSE_MS2');

            $warehouse = $mongoDb->Warehouse->findOne(['warehouseID' => (int)$warehouseID]);

            if (!$warehouse) {
                echo json_encode(['error' => 'Warehouse not found']);
                exit;
            }

            $aisleList = [];
            foreach ($warehouse['aisles'] as $aisle) {
                $aisleList[] = [
                    'AisleNr' => (int)$aisle['AisleNr'],
                    'AisleName' => $aisle['Name']
                ];
            }

            echo json_encode($aisleList);
        } catch (Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    } else {
        try {
            $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
            $stmt = $pdo->prepare("SELECT AisleNr, AisleName FROM Aisle WHERE WarehouseID = :warehouse_id");
            $stmt->execute(['warehouse_id' => $warehouseID]);

            $aisles = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode($aisles);
        } catch (PDOException $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
} else {
    echo json_encode(['error' => 'Warehouse ID not provided']);
}
?>
