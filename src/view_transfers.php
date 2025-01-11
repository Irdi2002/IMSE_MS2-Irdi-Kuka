<?php
// Database credentials
$host = 'MySQLDockerContainer'; // MySQL container name
$db = 'IMSE_MS2';               // Updated database name
$user = 'root';                 // MySQL username
$pass = 'IMSEMS2';              // MySQL root password

try {
    // Create a new PDO connection
    $dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass);

    // Set error mode to exceptions
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Fetch transfers with warehouse and aisle names, ordered by TransferID
    $stmt = $pdo->prepare("
        SELECT th.TransferID, th.TransferDate, 
               ow.WarehouseName AS OriginWarehouseName, oa.AisleName AS OriginAisleName, 
               dw.WarehouseName AS DestinationWarehouseName, da.AisleName AS DestinationAisleName
          FROM TransferHeader th
          JOIN Warehouse ow ON th.OriginWarehouseID = ow.WarehouseID
          JOIN Aisle oa ON th.OriginAisle = oa.AisleNr AND th.OriginWarehouseID = oa.WarehouseID
          JOIN Warehouse dw ON th.DestinationWarehouseID = dw.WarehouseID
          JOIN Aisle da ON th.DestinationAisle = da.AisleNr AND th.DestinationWarehouseID = da.WarehouseID
         ORDER BY th.TransferID
    ");
    $stmt->execute();
    $transfers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Display an error message if connection fails
    echo "<p>Error: " . $e->getMessage() . "</p>";
    die();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transfer List</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            padding: 20px;
            background-color: #f0f8ff; /* AliceBlue */
        }
        h1 {
            text-align: center;
            color: #0078D7; /* Vibrant Blue */
        }
        .btn-container {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        .btn-container a {
            display: inline-block;
            padding: 10px;
            background-color: #0078D7;
            color: white;
            text-decoration: none;
            font-size: 16px;
            font-weight: bold;
            border-radius: 5px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            transition: all 0.2s ease-in-out;
        }
        .btn-container a:hover {
            background-color: #005BB5; /* Darker Blue */
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            overflow: hidden;
        }
        th, td {
            padding: 12px 15px;
            text-align: center;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #0078D7; /* Vibrant Blue */
            color: white;
            /* text-transform: uppercase; */
            font-weight: bold;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        tr:hover {
            background-color: #f1f1f1;
            transform: scale(1.01);
            transition: all 0.2s ease-in-out;
        }
        td {
            color: #555; /* Subtle Gray */
        }
        td a {
            text-decoration: none;
            color: #0078D7;
        }
        td a:hover {
            color: #005BB5; /* Darker Blue */
        }
    </style>
</head>
<body>
    <h1>Transfer List</h1>
    <div class="btn-container">
        <a href="home.php" class="new-transfer-btn">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18px" height="18px" style="vertical-align: middle;">
                <path fill="currentColor" d="M21 11H6.414l5.293-5.293-1.414-1.414L3.586 12l6.707 6.707 1.414-1.414L6.414 13H21v-2z"/>
            </svg>
            Home
        </a>
        <a href="insert_transfer_form.php" class="new-transfer-btn">+ New Transfer</a>
    </div>
    <?php if (!empty($transfers)): ?>
        <table>
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Date</th>
                    <th>Origin Warehouse</th>
                    <th>Origin Aisle</th>
                    <th>Destination Warehouse</th>
                    <th>Destination Aisle</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transfers as $transfer): ?>
                    <tr>
                        <td>
                            <a href="view_transfer.php?TransferID=<?= htmlspecialchars($transfer['TransferID']) ?>">
                                <?= htmlspecialchars($transfer['TransferID']) ?>
                            </a>
                        </td>
                        <td><?= htmlspecialchars($transfer['TransferDate']) ?></td>
                        <td>
                            <a href="view_warehouse.php?WarehouseName=<?= htmlspecialchars($transfer['OriginWarehouseName']) ?>">
                                <?= htmlspecialchars($transfer['OriginWarehouseName']) ?>
                            </a>
                        </td>
                        <td><?= htmlspecialchars($transfer['OriginAisleName']) ?></td>
                        <td>
                            <a href="view_warehouse.php?WarehouseName=<?= htmlspecialchars($transfer['DestinationWarehouseName']) ?>">
                                <?= htmlspecialchars($transfer['DestinationWarehouseName']) ?>
                            </a>
                        </td>
                        <td><?= htmlspecialchars($transfer['DestinationAisleName']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="no-data">No transfers found.</p>
    <?php endif; ?>
</body>
</html>