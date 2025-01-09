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
    </style>
</head>
<body>
    <div class="container">
        <h1>Welcome to the Inventory Management System</h1>
        <a href="view_products.php" class="button">View Products</a>
        <a href="view_transfers.php" class="button">View Transfers</a>
        <a href="view_warehouses.php" class="button">View Warehouses</a>
        <a href="report_item_transfers.php" class="button">Item Transfer Report</a>
    </div>
</body>
</html>
