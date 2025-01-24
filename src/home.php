<?php
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home Page</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f0f8ff; /* AliceBlue */
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .container {
            text-align: center;
            background-color: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        h1 {
            color: #0078D7;
            margin-bottom: 30px;
        }
        .button {
            display: inline-block;
            background-color: #0078D7;
            color: white;
            text-decoration: none;
            font-size: 18px;
            padding: 10px 20px;
            margin: 10px;
            border-radius: 4px;
            border: none;
            cursor: pointer;
        }
        .button:hover {
            background-color: #005BB5;
        }
        .button:disabled {
            background-color: #cccccc;
            cursor: not-allowed;
        }
        .success-message {
            color: green;
            font-weight: bold;
            margin-bottom: 20px;
        }
        .mysql {
            color: red;
            font-weight: bold;
        }
        .mongodb {
            color: purple;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        
    <?php if (isset($_SESSION['use_mongodb']) && $_SESSION['use_mongodb']): ?>
        <p>Currently using: <strong class="mongodb">MongoDB</strong></p>
    <?php else: ?>
        <p>Currently using: <strong class="mysql">MySQL</strong></p>
    <?php endif; ?>
        
    <?php
    if (isset($_GET['message'])) {
        echo '<div class="success-message">' . htmlspecialchars($_GET['message']) . '</div>';
    }
    ?>
        
    <h1>Welcome to the Inventory Management System</h1>
    <a href="view_products.php" class="button">View Products</a>
    <a href="view_transfers.php" class="button">View Transfers</a>
    <a href="view_warehouses.php" class="button">View Warehouses</a>
    <a href="report_item_transfers.php" class="button">Item Transfer Report</a>
        
    <form action="generate_data_using_faker.php" method="POST" style="display:inline-block;">
        <button type="submit" class="button">Generate Data</button>
    </form>

    <form action="migrate_to_mongodb.php" method="POST" style="display:inline-block;">
        <button type="submit" class="button" <?php if (isset($_SESSION['use_mongodb']) && $_SESSION['use_mongodb']) echo 'disabled'; ?>>Migrate Data to MongoDB</button>
    </form>
    </div>
</body>
</html>