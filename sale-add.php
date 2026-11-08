<?php
require 'config.php';
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$products = $pdo->query("SELECT * FROM products WHERE quantity > 0 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

$error = '';
$selectedId = (int)($_POST['product_id'] ?? ($_GET['product_id'] ?? 0));
$quantity   = $_POST['quantity'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedId = (int)($_POST['product_id'] ?? 0);
    $quantity   = $_POST['quantity'] ?? '';

    if ($selectedId <= 0 || $quantity === '') {
        $error = "Product aur quantity dono select karein.";
    } elseif (!is_numeric($quantity) || (int)$quantity <= 0) {
        $error = "Quantity positive number honi chahiye.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$selectedId]);
        $prod = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$prod) {
            $error = "Product nahi mila.";
        } elseif ((int)$quantity > (int)$prod['quantity']) {
            $error = "Stock kam hai! Sirf {$prod['quantity']} available hain.";
        } else {
            $total = (int)$quantity * (float)$prod['price'];
            $pdo->beginTransaction();
            try {
                $ins = $pdo->prepare("INSERT INTO sales (product_id, product_name, quantity, price_per_item, total) VALUES (?, ?, ?, ?, ?)");
                $ins->execute([$prod['id'], $prod['name'], (int)$quantity, $prod['price'], $total]);

                $upd = $pdo->prepare("UPDATE products SET quantity = quantity - ? WHERE id = ?");
                $upd->execute([(int)$quantity, $prod['id']]);

                $pdo->commit();
                header("Location: sales.php?success=added");
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Sale save nahi hui: " . $e->getMessage();
            }
        }
    }
}
$activePage = 'sale-add';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>New Sale • Inventory</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="style.css">
</head>
<body class="dash-body">

<div class="dashboard">
    <?php include 'sidebar.php'; ?>

    <main class="main-content">
        <header class="topbar">
            <div>
                <h1>Record Sale</h1>
                <p class="subtitle" style="text-align:left;margin:0;">Sale hone par quantity update karein</p>
            </div>
            <a href="sales.php" class="btn-secondary">
                <i class="fa-solid fa-arrow-left"></i> Back
            </a>
        </header>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <?php if (empty($products)): ?>
            <div class="glass panel" style="text-align:center;padding:48px 20px;">
                <i class="fa-solid fa-box-open" style="font-size:40px;color:var(--text-light);margin-bottom:14px;"></i>
                <h3 style="margin-bottom:8px;">Koi product stock mein nahi</h3>
                <p class="muted">Pehle product add karein.</p>
            </div>
        <?php else: ?>
        <div class="glass panel" style="max-width:520px;">
            <form method="POST" autocomplete="off">
                <label class="form-label">Select Product</label>
                <div class="input-group">
                    <i class="fa-solid fa-box input-icon"></i>
                    <select name="product_id" id="productSelect" required>
                        <option value="">-- Choose product --</option>
                        <?php foreach ($products as $p): ?>
                            <option value="<?= $p['id'] ?>"
                                    data-price="<?= $p['price'] ?>"
                                    data-stock="<?= $p['quantity'] ?>"
                                    <?= $selectedId == $p['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($p['name']) ?> (Stock: <?= $p['quantity'] ?> | Rs <?= number_format($p['price'], 2) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <label class="form-label">Quantity Sold</label>
                <div class="input-group">
                    <i class="fa-solid fa-hashtag input-icon"></i>
                    <input type="number" name="quantity" id="qtyInput" placeholder="e.g. 5" value="<?= htmlspecialchars($quantity) ?>" min="1" step="1" required>
                </div>

                <div class="sale-summary" id="summary">
                    <div><span>Price / item</span><strong id="sumPrice">Rs 0.00</strong></div>
                    <div><span>Total</span><strong id="sumTotal">Rs 0.00</strong></div>
                </div>

                <button type="submit" class="btn-primary">
                    <i class="fa-solid fa-check"></i> Save Sale
                </button>
            </form>
        </div>
        <?php endif; ?>
    </main>
</div>

<script>
const sel = document.getElementById('productSelect');
const qty = document.getElementById('qtyInput');
const sumPrice = document.getElementById('sumPrice');
const sumTotal = document.getElementById('sumTotal');

function update() {
    if (!sel) return;
    const opt = sel.options[sel.selectedIndex];
    const price = parseFloat(opt?.dataset?.price || 0);
    const q = parseInt(qty.value || 0);
    const total = price * q;
    sumPrice.textContent = 'Rs ' + price.toFixed(2);
    sumTotal.textContent = 'Rs ' + total.toFixed(2);
}
if (sel) {
    sel.addEventListener('change', update);
    qty.addEventListener('input', update);
    update();
}
</script>

<script src="popup.js"></script>
</body>
</html>