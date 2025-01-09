<?php
session_start();

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

    // Fetch all available items
    $itemsStmt = $pdo->query("SELECT ProductID, Name FROM Product ORDER BY ProductID");
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

    $transfers = [];
    $chosenItem = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['item_no'])) {
        $itemNo = $_POST['item_no'];

        // Fetch the chosen item name
        $itemStmt = $pdo->prepare("SELECT Name FROM Product WHERE ProductID = :item_no");
        $itemStmt->execute([':item_no' => $itemNo]);
        $chosenItem = $itemStmt->fetchColumn();

        // Fetch transfers for the given item number
        $stmt = $pdo->prepare("
            SELECT th.TransferID, th.TransferDate, 
                   ow.WarehouseName AS OriginWarehouseName, oa.AisleName AS OriginAisleName, 
                   dw.WarehouseName AS DestinationWarehouseName, da.AisleName AS DestinationAisleName,
                   tl.Quantity
              FROM TransferLines tl
              JOIN TransferHeader th ON tl.TransferID = th.TransferID
              JOIN Warehouse ow ON th.OriginWarehouseID = ow.WarehouseID
              JOIN Aisle oa ON th.OriginAisle = oa.AisleNr AND th.OriginWarehouseID = oa.WarehouseID
              JOIN Warehouse dw ON th.DestinationWarehouseID = dw.WarehouseID
              JOIN Aisle da ON th.DestinationAisle = da.AisleNr AND th.DestinationWarehouseID = da.WarehouseID
             WHERE tl.ProductID = :item_no
             ORDER BY th.TransferID
        ");
        $stmt->execute([':item_no' => $itemNo]);
        $transfers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
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
    <title>Item Transfer Report</title>
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
        .form-container {
            max-width: 600px;
            margin: 0 auto;
        }
        .home-btn-container {
            text-align: left;
            margin-bottom: 20px;
        }
        .home-btn-container a {
            display: inline-flex;
            align-items: center;
            padding: 10px 20px;
            background-color: #0078D7; /* Vibrant Blue */
            color: white;
            text-decoration: none;
            font-size: 16px;
            font-weight: bold;
            border-radius: 5px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            transition: all 0.2s ease-in-out;
        }
        .home-btn-container a:hover {
            background-color: #005BB5; /* Darker Blue */
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }
        .home-btn-container a svg {
            margin-right: 8px;
        }
        form {
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
        }
        select {
            width: calc(100% - 20px);
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .btn {
            background-color: #0078D7; /* Vibrant Blue */
            color: white;
            border: none;
            cursor: pointer;
            padding: 8px 15px;
            border-radius: 5px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            transition: all 0.2s ease-in-out;
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
            font-weight: bold;
        }
        .btn:hover {
            background-color: #005BB5; /* Darker Blue */
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }
        .table-container {
            max-width: 1400px; /* Increase max-width */
            margin: 0 auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            overflow: hidden;
            font-size: 16px; /* Adjust font size */
        }
        th, td {
            padding: 16px 20px; /* Adjust padding */
            text-align: center;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #0078D7; /* Vibrant Blue */
            color: white;
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
        a.transfer-link {
            color: #0078D7;
            text-decoration: none;
            font-weight: bold;
        }
        a.transfer-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <h1>Item Transfer Report</h1>
    <div class="form-container">
        <div class="home-btn-container">
            <a href="home.php" class="btn">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18px" height="18px">
                    <path fill="currentColor" d="M21 11H6.414l5.293-5.293-1.414-1.414L3.586 12l6.707 6.707 1.414-1.414L6.414 13H21v-2z"/>
                </svg>
                Home
            </a>
        </div>
        <form method="POST">
            <label for="item_no">Item Number:</label>
            <select id="item_no" name="item_no" required>
                <option value="">Select an item</option>
                <?php foreach ($items as $item): ?>
                    <option value="<?= htmlspecialchars($item['ProductID']) ?>"><?= htmlspecialchars($item['Name']) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="submit" value="Generate Report" class="btn">
        </form>
    </div>

    <?php if (!empty($transfers)): ?>
        <div class="table-container">
            <h2>Transfers for item: <?= htmlspecialchars($chosenItem) ?></h2>
            <table>
                <thead>
                    <tr>
                        <th>Transfer ID</th>
                        <th>Transfer Date</th>
                        <th>Origin Warehouse</th>
                        <th>Origin Aisle</th>
                        <th>Destination Warehouse</th>
                        <th>Destination Aisle</th>
                        <th>Quantity</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transfers as $transfer): ?>
                        <tr>
                            <td><a href="edit_transfer.php?TransferID=<?= htmlspecialchars($transfer['TransferID']) ?>" class="transfer-link"><?= htmlspecialchars($transfer['TransferID']) ?></a></td>
                            <td><?= htmlspecialchars($transfer['TransferDate']) ?></td>
                            <td><?= htmlspecialchars($transfer['OriginWarehouseName']) ?></td>
                            <td><?= htmlspecialchars($transfer['OriginAisleName']) ?></td>
                            <td><?= htmlspecialchars($transfer['DestinationWarehouseName']) ?></td>
                            <td><?= htmlspecialchars($transfer['DestinationAisleName']) ?></td>
                            <td><?= htmlspecialchars($transfer['Quantity']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
        <p>No transfers found for the given item number.</p>
    <?php endif; ?>
</body>
</html>