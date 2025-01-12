<?php
session_start();

// Check if connected to MongoDB
$useMongoDB = isset($_SESSION['use_mongodb']) && $_SESSION['use_mongodb'];

if ($useMongoDB) {
    require_once '/var/www/html/vendor/autoload.php';

    // MongoDB Configuration
    $uri = 'mongodb://Irdi:Password1@MyMongoDBContainer:27017';
    $mongoClient = new MongoDB\Client($uri);
    $mongoDb = $mongoClient->selectDatabase('IMSE_MS2');

    // Fetch the product by ProductID
    $productID = (int)($_GET['ProductID'] ?? null);
    $message = $_GET['message'] ?? null; // Get the success message if available

    if ($productID === null || $productID === '') {
        echo "<p>Error: Invalid ProductID provided.</p>";
        exit;
    }

    // Fetch product data from MongoDB
    try {
        $product = $mongoDb->Product->findOne(['ProductID' => $productID]);

        if (!$product) {
            echo "<p>Error: Product not found. ProductID: " . htmlspecialchars($productID) . "</p>";
            exit;
        }
    } catch (MongoDB\Driver\Exception\Exception $e) {
        echo "MongoDB Connection Error: " . $e->getMessage();
        exit;
    }
} else {
    // Database credentials
    $host = 'MySQLDockerContainer'; // MySQL container name
    $db = 'IMSE_MS2';               // Updated database name
    $user = 'root';                 // MySQL username
    $pass = 'IMSEMS2';              // MySQL root password

    try {
        // Create a new PDO connection
        $dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Fetch the product by ProductID
        $productID = (int)($_GET['ProductID'] ?? null);
        $message = $_GET['message'] ?? null; // Get the success message if available

        if ($productID === null || $productID === '') {
            echo "<p>Error: Invalid ProductID provided.</p>";
            exit;
        }

        // Fetch product data from MySQL
        $stmt = $pdo->prepare("SELECT * FROM Product WHERE ProductID = :ProductID");
        $stmt->execute([':ProductID' => $productID]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            echo "<p>Error: Product not found. ProductID: " . htmlspecialchars($productID) . "</p>";
            exit;
        }
    } catch (PDOException $e) {
        echo "<p>Error: " . $e->getMessage() . "</p>";
        die();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Product</title>
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
        .success-message {
            text-align: center;
            color: green;
            font-weight: bold;
            margin-bottom: 20px;
        }
        .btn-container {
            margin-bottom: 20px;
            text-align: left;
            max-width: 600px;
            margin: 20px auto; /* Align container with form */
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
        .details {
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
        input[type="number"] {
            width: calc(100% - 20px);
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
            background-color: #f9f9f9; /* Light gray for read-only fields */
        }
        input[readonly] {
            color: #555; /* Slightly darker text color for read-only */
        }
    </style>
</head>
<body>
    <?php if ($message): ?>
        <div class="success-message"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <h1>View Product</h1>
    <div class="btn-container">
        <a href="view_products.php">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18px" height="18px" style="vertical-align: middle;">
                <path fill="currentColor" d="M21 11H6.414l5.293-5.293-1.414-1.414L3.586 12l6.707 6.707 1.414-1.414L6.414 13H21v-2z"/>
            </svg>
            Product List
        </a>
    </div>

    <div class="details">
        <label for="name">Name:</label>
        <input type="text" id="name" value="<?= htmlspecialchars($product['Name']) ?>" readonly>

        <label for="description">Description:</label>
        <input type="text" id="description" value="<?= htmlspecialchars($product['Description']) ?>" readonly>

        <label for="weight">Weight:</label>
        <input type="number" step="0.01" id="weight" value="<?= htmlspecialchars($product['Weight']) ?>" readonly>

        <label for="unit_of_measure">Unit of Measure:</label>
        <input type="text" id="unit_of_measure" value="<?= htmlspecialchars($product['UnitOfMeasure']) ?>" readonly>

        <label for="price">Price:</label>
        <input type="number" step="0.01" id="price" value="<?= htmlspecialchars($product['Price']) ?>" readonly>

        <label for="currency">Currency:</label>
        <input type="text" id="currency" value="<?= htmlspecialchars($product['Currency']) ?>" readonly>
    </div>
</body>
</html>