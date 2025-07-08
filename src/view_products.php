<?php
session_start();
require_once __DIR__ . '/db.php';

try {
    $useMongoDb = isset($_SESSION['use_mongodb']) && $_SESSION['use_mongodb'] === true;
    if ($useMongoDb) {
        $mongoDb = getMongoDb();
    } else {
        $pdo = getPDO();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
        $deleteID = $_POST['delete_id'];

        if ($useMongoDb) {
            try {
                $mongoDb->Product->deleteOne(['_id' => $deleteID]);

                header("Location: view_products.php");
                exit;
            } catch (Exception $e) {
                echo "<p style='color: red; text-align: center;'>Error: Unable to delete the product.</p>";
            }
        } else {
            try {

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
        $pdo = getPDO();
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
            text-decoration: none;
            font-size: 16px;
            color: white;
            background-color: #0078D7;
            padding: 10px 15px;
            border-radius: 5px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            transition: all 0.2s ease-in-out;
            display: inline-flex;
            align-items: center;
        }
        .btn-container a:hover {
            background-color: #005BB5;
        }
        .btn-container a svg {
            margin-right: 5px;
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
        <a href="home.php">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18px" height="18px">
                <path fill="currentColor" d="M21 11H6.414l5.293-5.293-1.414-1.414L3.586 12l6.707 6.707 1.414-1.414L6.414 13H21v-2z"/>
            </svg>
            Home
        </a>
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
