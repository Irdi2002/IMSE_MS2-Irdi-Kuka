<?php
session_start();
require_once __DIR__ . '/db.php';

try {
    $useMongoDb = isset($_SESSION['use_mongodb']) && $_SESSION['use_mongodb'] === true;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = $_POST['name'] ?? null;
        $description = $_POST['description'] ?? null;
        $weight = $_POST['weight'] ?? null;
        $unitOfMeasure = $_POST['unit_of_measure'] ?? null;
        $price = $_POST['price'] ?? null;
        $currency = $_POST['currency'] ?? null;

        if ($useMongoDb) {
            $mongoDb = getMongoDb();
            $lastProduct = $mongoDb->Product->findOne([], [
                'sort' => ['ProductID' => -1],
                'projection' => ['ProductID' => 1]
            ]);
        
            $lastProductID = isset($lastProduct['ProductID']) ? (int)$lastProduct['ProductID'] : 0;
            $nextProductID = $lastProductID + 1;
        
            $result = $mongoDb->Product->insertOne([
                'ProductID' => $nextProductID,
                'Name' => $name,
                'Description' => $description,
                'Weight' => $weight,
                'UnitOfMeasure' => $unitOfMeasure,
                'Price' => $price,
                'Currency' => $currency
            ]);
        
            $productID = $nextProductID;
        } else {
            $pdo = getPDO();

            $stmt = $pdo->prepare(<<<SQL
                INSERT INTO Product (Name, Description, Weight, UnitOfMeasure, Price, Currency)
                VALUES (:name, :description, :weight, :unit_of_measure, :price, :currency)
            SQL);
            $stmt->execute([
                ':name' => $name,
                ':description' => $description,
                ':weight' => $weight,
                ':unit_of_measure' => $unitOfMeasure,
                ':price' => $price,
                ':currency' => $currency
            ]);

            $productID = $pdo->lastInsertId();
        }

        header("Location: view_product.php?ProductID=$productID&message=Product%20inserted%20successfully!");
        exit;
    }
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Insert Product</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            padding: 20px;
            background-color: #f0f8ff;
        }
        h1 {
            text-align: center;
            color: #0078D7;
        }
        form {
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
        input[type="text"],
        input[type="number"],
        input[type="submit"] {
            width: calc(100% - 20px);
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        input[type="submit"] {
            background-color: #0078D7;
            color: white;
            border: none;
            cursor: pointer;
        }
        input[type="submit"]:hover {
            background-color: #005BB5;
        }
        .btn-container {
            margin-bottom: 20px;
            text-align: left;
            max-width: 600px;
            margin: 20px auto;
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
            background-color: #005BB5;
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }
    </style>
</head>
<body>
    <h1>Insert New Product</h1>
    <div class="btn-container">
        <a href="view_products.php?refresh=true">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18px" height="18px" style="vertical-align: middle;">
                <path fill="currentColor" d="M21 11H6.414l5.293-5.293-1.414-1.414L3.586 12l6.707 6.707 1.414-1.414L6.414 13H21v-2z"/>
            </svg>
            Product List
        </a>
    </div>
    <form action="" method="POST">
        <label for="name">Name:</label>
        <input type="text" id="name" name="name" placeholder="Enter product name" required>

        <label for="description">Description:</label>
        <input type="text" id="description" name="description" placeholder="Enter product description">

        <label for="weight">Weight:</label>
        <input type="number" step="0.01" id="weight" name="weight" placeholder="Enter product weight">

        <label for="unit_of_measure">Unit of Measure:</label>
        <input type="text" id="unit_of_measure" name="unit_of_measure" placeholder="e.g., kg, pcs">

        <label for="price">Price:</label>
        <input type="number" step="0.01" id="price" name="price" placeholder="Enter product price" required>

        <label for="currency">Currency:</label>
        <input type="text" id="currency" name="currency" placeholder="e.g., USD, EUR" required>

        <input type="submit" value="Insert Product">
    </form>
</body>
</html>
