<!-- This was used for testing the performance of MongoDB queries using indexes and not using them. -->

<!-- <?php
session_start();

require_once '/var/www/html/vendor/autoload.php';

$mongoUri   = 'mongodb://Irdi:Password1@MyMongoDBContainer:27017';
$mongoClient = new MongoDB\Client($mongoUri);
$mongoDb     = $mongoClient->selectDatabase('IMSE_MS2');

function printExecutionStats($stats) {
    echo "<pre>";
    echo "Execution Stats:\n";
    echo "Documents Examined: "
        . (isset($stats->totalDocsExamined) ? $stats->totalDocsExamined : 'N/A') . "\n";
    echo "Keys Examined: "
        . (isset($stats->totalKeysExamined) ? $stats->totalKeysExamined : 'N/A') . "\n";
    echo "Execution Time (ms): "
        . (isset($stats->executionTimeMillis) ? $stats->executionTimeMillis : 'N/A') . "\n";

    if (isset($stats->winningPlan)) {
        echo "Query Plan: "
            . json_encode($stats->winningPlan, JSON_PRETTY_PRINT) . "\n";

        $indexUsed = 'No Index';
        // Check if the winning plan has an 'inputStage' with an 'indexName'
        if (isset($stats->winningPlan->inputStage)
            && isset($stats->winningPlan->inputStage->indexName)) {
            $indexUsed = $stats->winningPlan->inputStage->indexName;
        }
        echo "Index Used: " . $indexUsed . "\n";
    }
    echo "</pre>";
}

function runExplainQuery($manager, $collectionName, $filter, $dbName = 'IMSE_MS2') {
    try {
        $command = new MongoDB\Driver\Command([
            'explain' => [
                'find'   => $collectionName,
                'filter' => $filter
            ],
            'verbosity' => 'executionStats'
        ]);

        $cursor = $manager->executeCommand($dbName, $command);
        $result = current($cursor->toArray());

        if (isset($result->executionStats)) {
            printExecutionStats($result->executionStats);
        } else {
            echo "<p>No execution stats returned.</p>";
        }
    } catch (Exception $e) {
        echo "<p>Error analyzing query: " . $e->getMessage() . "</p>";
    }
}

function runAllTests($mongoClient) {
    $manager = $mongoClient->getManager();

    echo "<h2>Test Case 1: Finding Warehouse by ID</h2>";
    runExplainQuery($manager, 'Warehouse', ['warehouseID' => 1]);

    echo "<h2>Test Case 2: Finding Product in Warehouse Aisle</h2>";
    runExplainQuery($manager, 'Warehouse', [
        'warehouseID'                => 1,
        'aisles.AisleNr'             => 1,
        'aisles.inventory.ProductID' => 1
    ]);

    echo "<h2>Test Case 3: Finding Transfers by Date Range (Last 7 Days)</h2>";
    $endDate   = new MongoDB\BSON\UTCDateTime(time() * 1000);
    $startDate = new MongoDB\BSON\UTCDateTime((time() - (7 * 24 * 60 * 60)) * 1000);
    runExplainQuery($manager, 'TransferHeader', [
        'transferDate' => [
            '$gte' => $startDate,
            '$lte' => $endDate
        ]
    ]);

    echo "<h2>Test Case 4: Finding All Locations of a Product</h2>";
    runExplainQuery($manager, 'Warehouse', [
        'aisles.inventory.ProductID' => 1
    ]);
}

function dropAllIndexes($mongoDb) {
    echo "<h3>Dropping all user-defined indexes...</h3>";

    $mongoDb->Warehouse->dropIndexes();
    $mongoDb->Product->dropIndexes();
    $mongoDb->TransferHeader->dropIndexes();

}

function createAllIndexes($mongoDb) {
    echo "<h3>Recreating indexes...</h3>";

    $mongoDb->Warehouse->createIndex(['warehouseID' => 1], ['unique' => true]);
    $mongoDb->Warehouse->createIndex(['aisles.AisleNr' => 1]);
    $mongoDb->Warehouse->createIndex(['aisles.inventory.ProductID' => 1]);


    $mongoDb->Product->createIndex(['ProductID' => 1], ['unique' => true]);


    $mongoDb->TransferHeader->createIndex(['TransferID' => 1], ['unique' => true]);
    $mongoDb->TransferHeader->createIndex(['originWarehouseID' => 1]);
    $mongoDb->TransferHeader->createIndex(['destinationWarehouseID' => 1]);

    $mongoDb->TransferHeader->createIndex(['transferDate' => 1]);
}

echo "<h1>Comparing Query Performance Without and With Indexes</h1>";


dropAllIndexes($mongoDb);


echo "<hr><h2>Performance WITHOUT Indexes</h2>";
runAllTests($mongoClient);


createAllIndexes($mongoDb);


echo "<hr><h2>Performance WITH Indexes</h2>";
runAllTests($mongoClient);


echo "<h2>Current Indexes in Each Collection (After Recreating)</h2>";
try {
    foreach (['Warehouse', 'Product', 'TransferHeader'] as $collection) {
        echo "<h3>$collection Collection Indexes:</h3>";
        $indexes = iterator_to_array($mongoDb->$collection->listIndexes());
        echo "<pre>" . json_encode($indexes, JSON_PRETTY_PRINT) . "</pre>";
    }
} catch (Exception $e) {
    echo "<p>Error listing indexes: " . $e->getMessage() . "</p>";
}
?> -->
