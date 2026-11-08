<?php
require 'config.php';
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$error = '';
$name = '';
$quantity = '';
$price = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $quantity = $_POST['quantity'] ?? '';
    $price    = $_POST['price'] ?? '';

    if ($name === '' || $quantity === '' || $price === '') {
        $error = "Saare fields bharein.";
    } elseif (!is_numeric($quantity) || (int)$quantity < 0) {
        $error = "Quantity valid number honi chahiye.";
    } elseif (!is_numeric($price) || (float)$price < 0) {
        $error = "Price valid number honi chahiye.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO products (name, quantity, price) VALUES (?, ?, ?)");
        $stmt->execute([$name, (int)$quantity, (float)$price]);
        header("Location: products.php?success=added");
        exit;
    }
}
$activePage = 'product-add';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Add Product • Inventory</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="style.css">
</head>
<body class="dash-body">

<div class="dashboard">
    <?php include 'sidebar.php'; ?>

    <main class="main-content">
        <header class="topbar">
            <div>
                <h1>Add Product</h1>
                <p class="subtitle" style="text-align:left;margin:0;">Naya product inventory mein add karein</p>
            </div>
            <a href="products.php" class="btn-secondary">
                <i class="fa-solid fa-arrow-left"></i> Back
            </a>
        </header>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <div class="glass panel" style="max-width:520px;">
            <form method="POST" autocomplete="off">
                <label class="form-label">Product Name</label>
                <div class="input-group">
                    <i class="fa-solid fa-box input-icon"></i>
                    <input type="text" name="name" placeholder="e.g. Sugar 1kg" value="<?= htmlspecialchars($name) ?>" required>
                </div>

                <label class="form-label">Quantity</label>
                <div class="input-group">
                    <i class="fa-solid fa-cubes input-icon"></i>
                    <input type="number" name="quantity" placeholder="e.g. 100" value="<?= htmlspecialchars($quantity) ?>" min="0" step="1" required>
                </div>

                <label class="form-label">Price per Item (Rs)</label>
                <div class="input-group">
                    <i class="fa-solid fa-tag input-icon"></i>
                    <input type="number" name="price" placeholder="e.g. 120.00" value="<?= htmlspecialchars($price) ?>" min="0" step="0.01" required>
                </div>

                <button type="submit" class="btn-primary">
                    <i class="fa-solid fa-check"></i> Save Product
                </button>
            </form>
        </div>
    </main>
</div>

<script src="popup.js"></script>
</body>
</html>