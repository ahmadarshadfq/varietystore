<?php
require 'config.php';
requireLogin();

$me = currentUser();
$isAdmin = $me['role'] === 'admin';

$error = '';
$success = $_GET['success'] ?? '';

// Only admin can add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['name'], $_POST['quantity'], $_POST['price']) && !isset($_POST['edit_id'])) {
    if (!$isAdmin) { header("Location: admin.php?error=no_permission"); exit; }
    $name     = trim($_POST['name']);
    $quantity = $_POST['quantity'];
    $price    = $_POST['price'];
    if ($name === '' || $quantity === '' || $price === '') $error = "Saare fields bharein.";
    elseif (!is_numeric($quantity) || (int)$quantity < 0) $error = "Quantity valid number honi chahiye.";
    elseif (!is_numeric($price) || (float)$price < 0) $error = "Price valid number honi chahiye.";
    else {
        $stmt = $pdo->prepare("INSERT INTO products (name, quantity, price) VALUES (?, ?, ?)");
        $stmt->execute([$name, (int)$quantity, (float)$price]);
        header("Location: products.php?success=added"); exit;
    }
}

// Only admin can edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id'])) {
    if (!$isAdmin) { header("Location: admin.php?error=no_permission"); exit; }
    $id        = (int)$_POST['edit_id'];
    $name      = trim($_POST['name'] ?? '');
    $price     = $_POST['price'] ?? '';
    $addQty    = trim($_POST['add_qty'] ?? '');
    $removeQty = trim($_POST['remove_qty'] ?? '');
    $addQty    = $addQty === '' ? 0 : (int)$addQty;
    $removeQty = $removeQty === '' ? 0 : (int)$removeQty;

    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) $error = "Product nahi mila.";
    elseif ($name === '' || $price === '') $error = "Name aur Price bharein.";
    elseif (!is_numeric($price) || (float)$price < 0) $error = "Price valid number honi chahiye.";
    elseif ($addQty < 0 || $removeQty < 0) $error = "Add/Remove negative nahi ho sakti.";
    elseif ($addQty > 0 && $removeQty > 0) $error = "Ek waqt mein Add ya Remove mein se ek choose karein.";
    else {
        $currentQty = (int)$product['quantity'];
        $newQty = $currentQty + $addQty - $removeQty;
        if ($newQty < 0) $error = "Remove zyada hai! Sirf {$currentQty} available hain.";
        else {
            $upd = $pdo->prepare("UPDATE products SET name=?, quantity=?, price=? WHERE id=?");
            $upd->execute([$name, $newQty, (float)$price, $id]);
            header("Location: products.php?success=updated"); exit;
        }
    }
}

$products = $pdo->query("SELECT * FROM products ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

$msgMap = [
    'added'   => 'Product add ho gaya!',
    'updated' => 'Product update ho gaya!',
    'deleted' => 'Product delete ho gaya!',
];
$activePage = 'products';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Products • Inventory</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="style.css">
</head>
<body class="dash-body">

<div class="dashboard">
    <?php include 'sidebar.php'; ?>

    <main class="main-content">
        <header class="topbar">
            <div>
                <h1>Products</h1>
                <p class="subtitle" style="text-align:left;margin:0;">
                    <?= $isAdmin ? 'Manage your shop inventory' : 'View products only (read-only)' ?>
                </p>
            </div>
            <?php if ($isAdmin): ?>
            <button type="button" class="btn-primary" data-popup-form="product" style="width:auto;padding:10px 18px;margin:0;border:none;cursor:pointer;">
                <i class="fa-solid fa-plus"></i> Add Product
            </button>
            <?php endif; ?>
        </header>

        <?php if (isset($msgMap[$success])): ?>
            <div class="alert alert-success" style="margin-bottom:16px;">
                <i class="fa-solid fa-circle-check"></i><span><?= $msgMap[$success] ?></span>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error" style="margin-bottom:16px;">
                <i class="fa-solid fa-circle-exclamation"></i><span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <?php if (empty($products)): ?>
            <div class="glass panel" style="text-align:center;padding:48px 20px;">
                <i class="fa-solid fa-box-open" style="font-size:40px;color:var(--text-light);margin-bottom:14px;"></i>
                <h3 style="margin-bottom:8px;">Koi product nahi hai</h3>
                <?php if ($isAdmin): ?>
                    <p class="muted" style="margin-bottom:16px;">Apna pehla product add karein.</p>
                    <button type="button" class="btn-primary" data-popup-form="product" style="width:auto;display:inline-flex;padding:10px 20px;margin:0;border:none;cursor:pointer;">
                        <i class="fa-solid fa-plus"></i> Add Product
                    </button>
                <?php else: ?>
                    <p class="muted">Admin se products add karwayein.</p>
                <?php endif; ?>
            </div>
        <?php else: ?>

        <div class="search-bar">
            <i class="fa-solid fa-magnifying-glass search-icon"></i>
            <input type="text" id="productSearch" placeholder="Search product by name..." autocomplete="off">
            <button type="button" class="search-clear" id="searchClear" title="Clear">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="glass panel" style="padding:0;overflow:hidden;margin-top:14px;" id="tableWrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Product Name</th>
                        <th class="text-right">Quantity</th>
                        <th class="text-right">Price/Item</th>
                        <th class="text-right">Stock Value</th>
                        <?php if ($isAdmin): ?><th class="text-center">Actions</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody id="productTableBody">
                    <?php foreach ($products as $i => $p):
                        $stock = $p['quantity'] * $p['price'];
                        $low = $p['quantity'] <= 5;
                        $productJson = json_encode([
                            'id' => (int)$p['id'],
                            'name' => $p['name'],
                            'quantity' => (int)$p['quantity'],
                            'price' => (float)$p['price'],
                        ], JSON_HEX_APOS | JSON_HEX_QUOT);
                    ?>
                    <tr data-name="<?= htmlspecialchars(strtolower($p['name'])) ?>">
                        <td class="muted row-num"><?= $i + 1 ?></td>
                        <td>
                            <strong><?= htmlspecialchars($p['name']) ?></strong>
                            <?php if ($low): ?><span class="badge badge-danger" style="margin-left:6px;">Low</span><?php endif; ?>
                        </td>
                        <td class="text-right"><span class="<?= $low ? 'text-danger' : '' ?>"><strong><?= $p['quantity'] ?></strong></span></td>
                        <td class="text-right">Rs <?= number_format($p['price'], 2) ?></td>
                        <td class="text-right"><strong>Rs <?= number_format($stock, 2) ?></strong></td>
                        <?php if ($isAdmin): ?>
                        <td class="text-center">
                            <div class="action-group">
                                <button type="button" class="btn-icon" title="Edit"
                                        data-popup-form="product-edit"
                                        data-product='<?= htmlspecialchars($productJson, ENT_QUOTES, "UTF-8") ?>'>
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                                <a href="product-delete.php?id=<?= $p['id'] ?>" class="btn-icon danger" title="Delete"
                                   data-confirm="This product will be permanently deleted along with its sales history."
                                   data-confirm-title="Delete &quot;<?= htmlspecialchars($p['name']) ?>&quot;?"
                                   data-confirm-btn="Yes, Delete"
                                   data-cancel-btn="Cancel"
                                   data-danger="true"
                                   data-type="danger"
                                   data-icon='<i class="fa-solid fa-trash-can"></i>'>
                                    <i class="fa-solid fa-trash"></i>
                                </a>
                            </div>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="no-result" id="noResult" style="display:none;">
                <i class="fa-solid fa-magnifying-glass"></i>
                <h3>Koi product nahi mila</h3>
                <p class="muted">Try a different search term.</p>
            </div>
        </div>
        <?php endif; ?>
    </main>
</div>

<script>
(function () {
    const input = document.getElementById('productSearch');
    if (!input) return;
    const clearBtn = document.getElementById('searchClear');
    const tbody    = document.getElementById('productTableBody');
    const noResult = document.getElementById('noResult');
    const rows     = Array.from(tbody.querySelectorAll('tr'));

    function filterRows() {
        const q = input.value.trim().toLowerCase();
        let visible = 0;
        rows.forEach(row => {
            const name = row.dataset.name || '';
            const match = q === '' || name.includes(q);
            row.style.display = match ? '' : 'none';
            row.classList.toggle('row-match', q !== '' && match);
            if (match) visible++;
        });
        let n = 1;
        rows.forEach(row => {
            if (row.style.display !== 'none') {
                const numCell = row.querySelector('.row-num');
                if (numCell) numCell.textContent = n++;
            }
        });
        clearBtn.classList.toggle('show', q.length > 0);
        noResult.style.display = (visible === 0) ? 'flex' : 'none';
    }

    input.addEventListener('input', filterRows);
    clearBtn.addEventListener('click', () => { input.value = ''; input.focus(); filterRows(); });
    input.addEventListener('keydown', e => { if (e.key === 'Escape') { input.value = ''; filterRows(); } });
})();
</script>

<script src="popup.js"></script>
</body>
</html>