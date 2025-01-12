<?php
require_once '/var/www/html/vendor/autoload.php';

$host = 'MySQLDockerContainer';
$db = 'IMSE_MS2';
$user = 'root';
$pass = 'IMSEMS2';

header('Content-Type: application/json');

if (isset($_GET['warehouse_id']) && isset($_GET['aisle_nr'])) {
    $warehouseID = $_GET['warehouse_id'];
    $aisleNr = $_GET['aisle_nr'];
    $useMongoDB = isset($_GET['useMongoDB']) && $_GET['useMongoDB'] === 'true';

    if ($useMongoDB) {
        try {
            $mongoUri = 'mongodb://Irdi:Password1@MyMongoDBContainer:27017';
            $mongoClient = new MongoDB\Client($mongoUri);
            $mongoDb = $mongoClient->selectDatabase('IMSE_MS2');

            // Fetch the warehouse to get the specific aisle
            $warehouse = $mongoDb->Warehouse->findOne(['_id' => $warehouseID]);

            if (!$warehouse) {
                echo json_encode(['error' => 'Warehouse not found']);
                exit;
            }

            $targetAisle = null;
            foreach ($warehouse['aisles'] as $aisle) {
                if ($aisle['aisleNr'] == $aisleNr) {
                    $targetAisle = $aisle;
                    break;
                }
            }

            if (!$targetAisle) {
                echo json_encode(['error' => 'Aisle not found']);
                exit;
            }

            $productList = [];
            foreach ($targetAisle['inventory'] as $item) {
                $product = $mongoDb->Product->findOne(['ProductID' => (int)$item['productID']]);
                if ($product) {
                    $productList[] = [
                        'ProductID' => $item['productID'],
                        'Name' => $product['Name'],
                        'Quantity' => $item['quantity']
                    ];
                }
            }

            echo json_encode($productList);
        } catch (Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    } else {
        try {
            $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
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
        } catch (PDOException $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
} else {
    echo json_encode(['error' => 'Warehouse ID and Aisle Nr not provided']);
}
?>
