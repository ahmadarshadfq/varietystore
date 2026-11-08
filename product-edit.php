<?php
require 'config.php';
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) { header("Location: products.php"); exit; }

$error = '';
$name     = $product['name'];
$price    = $product['price'];
$addQty   = '';
$removeQty= '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name      = trim($_POST['name'] ?? '');
    $price     = $_POST['price'] ?? '';
    $addQty    = trim($_POST['add_qty'] ?? '');
    $removeQty = trim($_POST['remove_qty'] ?? '');

    $addQty    = $addQty    === '' ? 0 : (int)$addQty;
    $removeQty = $removeQty === '' ? 0 : (int)$removeQty;

    if ($name === '' || $price === '') {
        $error = "Name aur Price bharein.";
    } elseif (!is_numeric($price) || (float)$price < 0) {
        $error = "Price valid number honi chahiye.";
    } elseif ($addQty < 0 || $removeQty < 0) {
        $error = "Add/Remove quantity negative nahi ho sakti.";
    } elseif ($addQty > 0 && $removeQty > 0) {
        $error = "Ek hi waqt mein Add ya Remove mein se ek choose karein.";
    } else {
        $currentQty = (int)$product['quantity'];
        $newQty = $currentQty + $addQty - $removeQty;

        if ($newQty < 0) {
            $error = "Remove quantity zyada hai! Sirf {$currentQty} available hain.";
        } else {
            $stmt = $pdo->prepare("UPDATE products SET name=?, quantity=?, price=? WHERE id=?");
            $stmt->execute([$name, $newQty, (float)$price, $id]);
            header("Location: products.php?success=updated");
            exit;
        }
    }
}
$activePage = 'products';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Edit Product • Inventory</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="style.css">
</head>
<body class="dash-body">

<div class="dashboard">
    <?php include 'sidebar.php'; ?>

    <main class="main-content">
        <header class="topbar">
            <div>
                <h1>Edit Product</h1>
                <p class="subtitle" style="text-align:left;margin:0;">Product details update karein</p>
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
                    <input type="text" name="name" value="<?= htmlspecialchars($name) ?>" required>
                </div>

                <label class="form-label">Price per Item (Rs)</label>
                <div class="input-group">
                    <i class="fa-solid fa-tag input-icon"></i>
                    <input type="number" name="price" value="<?= htmlspecialchars($price) ?>" min="0" step="0.01" required>
                </div>

                <label class="form-label">Current Quantity (read-only)</label>
                <div class="input-group">
                    <i class="fa-solid fa-cubes input-icon"></i>
                    <input type="number" value="<?= (int)$product['quantity'] ?>" readonly
                           style="background:var(--surface-alt);color:var(--text-muted);cursor:not-allowed;">
                </div>

                <div class="qty-actions">
                    <div>
                        <label class="form-label">
                            <i class="fa-solid fa-plus" style="color:var(--success);margin-right:4px;"></i>
                            Add Quantity
                        </label>
                        <div class="input-group">
                            <i class="fa-solid fa-circle-plus input-icon"></i>
                            <input type="number" name="add_qty" placeholder="e.g. 20" value="<?= htmlspecialchars($addQty) ?>" min="0" step="1">
                        </div>
                    </div>

                    <div>
                        <label class="form-label">
                            <i class="fa-solid fa-minus" style="color:var(--danger);margin-right:4px;"></i>
                            Remove from Quantity
                        </label>
                        <div class="input-group">
                            <i class="fa-solid fa-circle-minus input-icon"></i>
                            <input type="number" name="remove_qty" placeholder="e.g. 5" value="<?= htmlspecialchars($removeQty) ?>" min="0" step="1">
                        </div>
                    </div>
                </div>

                <p class="qty-hint">
                    <i class="fa-solid fa-circle-info"></i>
                    New quantity = Current <strong><?= (int)$product['quantity'] ?></strong>
                    + Add
                    − Remove
                </p>

                <button type="submit" class="btn-primary">
                    <i class="fa-solid fa-check"></i> Update Product
                </button>
            </form>
        </div>
    </main>
</div>

<script src="popup.js"></script>
</body>
</html>