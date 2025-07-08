<?php
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

if (isset($_GET['warehouse_id'])) {
    $warehouseID = $_GET['warehouse_id'];
    $useMongoDB = isset($_GET['useMongoDB']) && $_GET['useMongoDB'] === 'true';

    if ($useMongoDB) {
        try {
            $mongoDb = getMongoDb();

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
            $pdo = getPDO();
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
