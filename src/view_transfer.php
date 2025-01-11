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

    // Fetch transfer details
    if (isset($_GET['TransferID'])) {
        $transferID = $_GET['TransferID'];

        $stmt = $pdo->prepare("
            SELECT th.TransferID, th.TransferDate, 
                   th.OriginWarehouseID, th.OriginAisle, 
                   th.DestinationWarehouseID, th.DestinationAisle,
                   ow.WarehouseName AS OriginWarehouseName, 
                   dw.WarehouseName AS DestinationWarehouseName
              FROM TransferHeader th
              JOIN Warehouse ow ON th.OriginWarehouseID = ow.WarehouseID
              JOIN Warehouse dw ON th.DestinationWarehouseID = dw.WarehouseID
             WHERE th.TransferID = :transfer_id
        ");
        $stmt->execute([':transfer_id' => $transferID]);
        $transfer = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$transfer) {
            throw new Exception("Transfer not found.");
        }

        // Fetch transfer lines
        $lineStmt = $pdo->prepare("
            SELECT tl.ProductID, tl.Quantity, p.Name AS ProductName
              FROM TransferLines tl
              JOIN Product p ON tl.ProductID = p.ProductID
             WHERE tl.TransferID = :transfer_id
        ");
        $lineStmt->execute([':transfer_id' => $transferID]);
        $lines = $lineStmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        throw new Exception("TransferID not provided.");
    }
} catch (Exception $e) {
    $_SESSION['error_message'] = $e->getMessage();
    header('Location: view_transfers.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Transfer</title>
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
        .container {
            background-color: #fff;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
        }
        .info {
            margin-bottom: 15px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background-color: #f9f9f9;
        }
        .back-arrow {
            margin-bottom: 20px;
            text-align: left;
            margin-left: calc(50% - 300px); /* Align with form start */
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
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }
        .back-arrow a svg {
            margin-right: 5px;
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
    </style>
</head>
<body>
    <h1>View Transfer</h1>
    <div class="back-arrow">
        <a href="view_transfers.php">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18px" height="18px">
                <path fill="currentColor" d="M21 11H6.414l5.293-5.293-1.414-1.414L3.586 12l6.707 6.707 1.414-1.414L6.414 13H21v-2z"/>
            </svg>
            Transfer List
        </a>
    </div>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="success-message"><?= htmlspecialchars($_SESSION['success_message']) ?></div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="error-message"><?= htmlspecialchars($_SESSION['error_message']) ?></div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <div class="container">
        <label>Origin Warehouse:</label>
        <div class="info"><?= htmlspecialchars($transfer['OriginWarehouseName']) ?></div>

        <label>Origin Aisle:</label>
        <div class="info"><?= htmlspecialchars($transfer['OriginAisle']) ?></div>

        <label>Destination Warehouse:</label>
        <div class="info"><?= htmlspecialchars($transfer['DestinationWarehouseName']) ?></div>

        <label>Destination Aisle:</label>
        <div class="info"><?= htmlspecialchars($transfer['DestinationAisle']) ?></div>

        <label>Transfer Date:</label>
        <div class="info"><?= htmlspecialchars($transfer['TransferDate']) ?></div>

        <h2>Transfer Lines</h2>
        <table>
            <thead>
                <tr>
                    <th>Product ID</th>
                    <th>Product Name</th>
                    <th>Quantity</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lines as $line): ?>
                    <tr>
                        <td><?= htmlspecialchars($line['ProductID']) ?></td>
                        <td><?= htmlspecialchars($line['ProductName']) ?></td>
                        <td><?= htmlspecialchars($line['Quantity']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>