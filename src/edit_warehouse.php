<?php
// Database credentials
$host = 'MySQLDockerContainer'; // MySQL container or host
$db   = 'IMSE_MS2';             // Database name
$user = 'root';                 // MySQL username
$pass = 'IMSEMS2';             // MySQL password

try {
    // Create a new PDO connection
    $dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Fetch the warehouse details by WarehouseName
    $WarehouseName = $_GET['WarehouseName'] ?? null;
    if (!$WarehouseName) {
        echo "<p>Error: WarehouseName not provided.</p>";
        exit;
    }

    // 1) Get the Warehouse record (including its integer PK WarehouseID)
    $stmt = $pdo->prepare("
        SELECT * 
          FROM Warehouse 
         WHERE WarehouseName = :WarehouseName
    ");
    $stmt->execute([':WarehouseName' => $WarehouseName]);
    $warehouse = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$warehouse) {
        echo "<p>Error: Warehouse not found.</p>";
        exit;
    }

    // Extract the actual PK for further queries
    $warehouseID = $warehouse['WarehouseID'];

    // 2) Fetch all Aisles (left join with WarehouseInventory & Product)
    //    This ensures you see all aisles, even if no products or zero quantity
    $aisleStmt = $pdo->prepare("
        SELECT 
            A.AisleNr,
            A.AisleName,
            A.Description,
            COALESCE(P.Name, 'No Product') AS ProductName,
            WI.Quantity
        FROM Aisle A
        LEFT JOIN WarehouseInventory WI
               ON A.WarehouseID = WI.WarehouseID
              AND A.AisleNr = WI.AisleNr
        LEFT JOIN Product P
               ON WI.ProductID = P.ProductID
        WHERE A.WarehouseID = :warehouseID
        ORDER BY A.AisleNr, P.Name
    ");
    $aisleStmt->execute([':warehouseID' => $warehouseID]);
    $aisles = $aisleStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
    die();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Warehouse</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            padding: 20px;
            background-color: #f0f8ff; /* AliceBlue */
        }
        h1, h2 {
            text-align: center;
            color: #0078D7; /* Vibrant Blue */
        }
        .details {
            background-color: #fff;
            max-width: 800px;
            margin: 0 auto 20px auto;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        .details p {
            font-size: 16px;
            margin: 8px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        table th, table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: center;
        }
        table th {
            background-color: #0078D7;
            color: white;
        }
        .back-arrow {
            margin-bottom: 20px;
            text-align: left;
            margin-left: calc(50% - 400px); /* Align with details section */
        }
        .back-arrow a {
            text-decoration: none;
            font-size: 16px;
            color: white;
            background-color: #0078D7; /* Vibrant Blue */
            padding: 10px 15px;
            border-radius: 5px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            transition: all 0.2s ease-in-out;
            display: inline-flex;
            align-items: center;
        }
        .back-arrow a:hover {
            background-color: #005BB5; /* Darker Blue */
        }
        .back-arrow a svg {
            margin-right: 5px;
        }
    </style>
</head>
<body>
    <h1>Warehouse Details</h1>
    <div class="back-arrow">
        <a href="view_warehouses.php">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18px" height="18px">
                <path fill="currentColor" d="M21 11H6.414l5.293-5.293-1.414-1.414L3.586 12l6.707 6.707 1.414-1.414L6.414 13H21v-2z"/>
            </svg>
            Warehouse List
        </a>
    </div>

    <div class="details">
        <p><strong>Warehouse Name:</strong> <?= htmlspecialchars($warehouse['WarehouseName']) ?></p>
        <p><strong>Address:</strong> <?= htmlspecialchars($warehouse['Address']) ?></p>
        <p><strong>Category:</strong> <?= htmlspecialchars($warehouse['Category'] ?? '') ?></p>
    </div>

    <h2>Aisles and Products</h2>
    <table>
        <thead>
            <tr>
                <th>Aisle Nr</th>
                <th>Aisle Name</th>
                <th>Product Name</th>
                <th>Quantity</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($aisles)): ?>
                <?php foreach ($aisles as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['AisleNr']) ?></td>
                        <td><?= htmlspecialchars($row['AisleName']) ?></td>
                        <td><?= htmlspecialchars($row['ProductName']) ?></td>
                        <td><?= htmlspecialchars($row['Quantity'] ?? '0') ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4">No aisles found for this warehouse.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>