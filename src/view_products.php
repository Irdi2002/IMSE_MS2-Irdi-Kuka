<?php
session_start();

// MongoDB Configuration
require_once '/var/www/html/vendor/autoload.php';
$mongoUri = 'mongodb://Irdi:Password1@MyMongoDBContainer:27017';
$mongoClient = new MongoDB\Client($mongoUri);
$mongoDb = $mongoClient->selectDatabase('IMSE_MS2');

// MySQL Configuration
$host = 'MySQLDockerContainer';
$db = 'IMSE_MS2';
$user = 'root';
$pass = 'IMSEMS2';
$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";

try {
    // Determine whether to use MongoDB or MySQL based on session
    $useMongoDb = isset($_SESSION['use_mongodb']) && $_SESSION['use_mongodb'] === true;

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
        $deleteID = $_POST['delete_id'];

        if ($useMongoDb) {
            try {
                // Delete product references in related collections
                $mongoDb->SalesOrderDetails->deleteMany(['productID' => $deleteID]);

                // Delete the product itself
                $mongoDb->Product->deleteOne(['_id' => $deleteID]);

                header("Location: view_products.php");
                exit;
            } catch (Exception $e) {
                echo "<p style='color: red; text-align: center;'>Error: Unable to delete the product.</p>";
            }
        } else {
            try {
                $pdo = new PDO($dsn, $user, $pass);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                // Delete product references in related tables
                $deleteDetailsStmt = $pdo->prepare("DELETE FROM SalesOrderDetails WHERE ProductID = :ProductID");
                $deleteDetailsStmt->execute([':ProductID' => $deleteID]);

                // Delete the product itself
                $deleteStmt = $pdo->prepare("DELETE FROM Product WHERE ProductID = :ProductID");
                $deleteStmt->execute([':ProductID' => $deleteID]);

                header("Location: view_products.php");
                exit;
            } catch (PDOException $e) {
                echo "<p style='color: red; text-align: center;'>Error: Unable to delete the product.</p>";
            }
        }
    }

    if ($useMongoDb) {
        $productsCursor = $mongoDb->Product->find();
        $products = [];
        foreach ($productsCursor as $product) {
            $products[] = [
                'ProductID' => (int)$product['ProductID'],
                'Name' => $product['Name'] ?? '',
                'Weight' => $product['Weight'] ?? '',
                'UnitOfMeasure' => $product['UnitOfMeasure'] ?? '',
                'Price' => $product['Price'] ?? '',
                'Currency' => $product['Currency'] ?? ''
            ];
        }
    } else {
        $pdo = new PDO($dsn, $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $stmt = $pdo->query("SELECT * FROM Product");
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
    die();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product List</title>
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
        }
        .btn-container a:hover {
            background-color: #005BB5;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
        }
        th, td {
            padding: 12px 15px;
            text-align: center;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #0078D7;
            color: white;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        td {
            color: #555;
        }
        td a {
            text-decoration: none;
            color: #0078D7;
        }
        td a:hover {
            color: #005BB5;
        }
        .delete-btn {
            background: none;
            border: none;
            color: #ff6b6b;
            cursor: pointer;
        }
        .delete-btn:hover {
            color: #ff4c4c;
        }
    </style>
    <script>
        function confirmDeletion(productID) {
            if (confirm("Do you want to proceed deleting this record?")) {
                document.getElementById('delete-form-' + productID).submit();
            }
        }
    </script>
</head>
<body>
    <h1>Product List</h1>
    <div class="btn-container">
        <a href="home.php">Home</a>
        <a href="insert_product.php">+ New Product</a>
    </div>
    <?php if (!empty($products)): ?>
        <table>
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Description</th>
                    <th>Weight</th>
                    <th>Unit of Measure</th>
                    <th>Price</th>
                    <th>Currency</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $product): ?>
                    <tr>
                        <td>
                            <a href="view_product.php?ProductID=<?= htmlspecialchars($product['ProductID']) ?>">
                                <?= htmlspecialchars($product['ProductID']) ?>
                            </a>
                        </td>
                        <td><?= htmlspecialchars($product['Name']) ?></td>
                        <td><?= htmlspecialchars($product['Weight']) ?></td>
                        <td><?= htmlspecialchars($product['UnitOfMeasure']) ?></td>
                        <td><?= htmlspecialchars($product['Price']) ?></td>
                        <td><?= htmlspecialchars($product['Currency']) ?></td>
                        <td>
                            <form method="POST" id="delete-form-<?= htmlspecialchars($product['ProductID']) ?>" style="display: inline;">
                                <input type="hidden" name="delete_id" value="<?= htmlspecialchars($product['ProductID']) ?>">
                                <button type="button" class="delete-btn" onclick="confirmDeletion('<?= htmlspecialchars($product['ProductID']) ?>')">🗑️</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="no-data">No products found.</p>
    <?php endif; ?>
</body>
</html>
