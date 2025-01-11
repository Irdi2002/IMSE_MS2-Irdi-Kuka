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

    // Handle deletion if triggered
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
        $deleteID = $_POST['delete_id'];

        try {
            // Delete all references in related tables (e.g., SalesOrderDetails)
            $deleteDetailsStmt = $pdo->prepare("DELETE FROM SalesOrderDetails WHERE ProductID = :ProductID");
            $deleteDetailsStmt->execute([':ProductID' => $deleteID]);

            // Delete the product itself
            $deleteStmt = $pdo->prepare("DELETE FROM Product WHERE ProductID = :ProductID");
            $deleteStmt->execute([':ProductID' => $deleteID]);

            // Refresh the page after successful deletion
            header("Location: view_products.php");
            exit;
        } catch (PDOException $e) {
            // Display an error message if unexpected error occurs
            echo "<p style='color: red; text-align: center;'>Error: Unable to delete the product.</p>";
        }
    }

    // Fetch all products
    $stmt = $pdo->query("SELECT * FROM Product");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    <title>Product List</title>
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
        .delete-btn {
            background: none;
            border: none;
            color: #ff6b6b; /* Red */
            cursor: pointer;
        }
        .delete-btn:hover {
            color: #ff4c4c; /* Darker Red */
        }
    </style>
    <script>
        function confirmDeletion(productID) {
            // Show confirmation dialog
            if (confirm("Do you want to proceed deleting this record?")) {
                // Submit the form if user agrees
                document.getElementById('delete-form-' + productID).submit();
            }
        }
    </script>
</head>
<body>
    <h1>Product List</h1>
    <div class="btn-container">
        <a href="home.php" class="new-product-btn">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18px" height="18px" style="vertical-align: middle;">
                <path fill="currentColor" d="M21 11H6.414l5.293-5.293-1.414-1.414L3.586 12l6.707 6.707 1.414-1.414L6.414 13H21v-2z"/>
            </svg>
            Home
        </a>
        <a href="insert_product.php" class="new-product-btn">+ New Product</a>
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
                                <button type="button" class="delete-btn" onclick="confirmDeletion(<?= htmlspecialchars($product['ProductID']) ?>)">
                                    🗑️
                                </button>
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
