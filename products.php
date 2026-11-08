<?php
require 'config.php';
requireLogin();

$me = currentUser();
$isAdmin = $me['role'] === 'admin' && !isSwitched();

$error = '';
$success = $_GET['success'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['name'], $_POST['quantity'], $_POST['price']) && !isset($_POST['edit_id'])) {
    if (!$isAdmin) { header("Location: admin.php?error=no_permission"); exit; }
    $name     = trim($_POST['name']);
    $quantity = $_POST['quantity'];
    $price    = $_POST['price'];
    if ($name === '' || $quantity === '' || $price === '') $error = "Saare fields bharein.";
    elseif (!is_numeric($quantity) || (int)$quantity < 0) $error = "Quantity valid number honi chahiye.";
    elseif (!is_numeric($price) || (float)$price < 0) $error = "Price valid number honi chahiye.";
    else {
        $stmt = $pdo->prepare("INSERT INTO products (name, quantity, price, cost_price) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, (int)$quantity, (float)$price, (float)$price]);
        $newId = (int)$pdo->lastInsertId();
        $pdo->prepare("INSERT INTO purchases (product_id, quantity, cost_price, selling_price, profit_per_item, user_id) VALUES (?, ?, ?, ?, ?, ?)")
            ->execute([$newId, (int)$quantity, (float)$price, (float)$price, 0, $me['id'] ?? null]);
        header("Location: products.php?success=added"); exit;
    }
}

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

            if ($addQty > 0) {
                $profit = (float)$price - (float)$product['cost_price'];
                $pdo->prepare("INSERT INTO purchases (product_id, quantity, cost_price, selling_price, profit_per_item, user_id) VALUES (?, ?, ?, ?, ?, ?)")
                    ->execute([$id, $addQty, (float)$product['cost_price'], (float)$price, $profit, $me['id'] ?? null]);
            }

            header("Location: products.php?success=updated"); exit;
        }
    }
}

$products = $pdo->query("
    SELECT p.*,
           (p.price - p.cost_price) AS profit_per_item,
           CASE WHEN p.cost_price > 0 THEN ((p.price - p.cost_price) / p.cost_price * 100) ELSE 0 END AS profit_pct
    FROM products p
    ORDER BY p.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

$msgMap = [
    'added'   => 'Product add ho gaya!',
    'updated' => 'Product update ho gaya!',
    'deleted' => 'Product delete ho gaya!',
];

// Preload history for each product
$productIds = array_column($products, 'id');
$purchasesByProduct = [];
$salesByProduct = [];

if (!empty($productIds)) {
    $in = implode(',', array_map('intval', $productIds));

    $purchases = $pdo->query("
        SELECT p.*, u.username AS buyer_name, u.color AS buyer_color
        FROM purchases p
        LEFT JOIN users u ON u.id = p.user_id
        WHERE p.product_id IN ($in)
        ORDER BY p.purchased_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($purchases as $row) {
        $purchasesByProduct[(int)$row['product_id']][] = $row;
    }

    $sales = $pdo->query("
        SELECT s.*, u.username AS seller_name, u.color AS seller_color
        FROM sales s
        LEFT JOIN users u ON u.id = s.user_id
        WHERE s.product_id IN ($in)
        ORDER BY s.sold_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($sales as $row) {
        $salesByProduct[(int)$row['product_id']][] = $row;
    }
}

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
            <a href="wholesale.php" class="btn-primary" style="width:auto;padding:10px 18px;margin:0;text-decoration:none;">
                <i class="fa-solid fa-warehouse"></i> Add via Wholesale
            </a>
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
                    <p class="muted" style="margin-bottom:16px;">Wholesale page se pehla product add karein.</p>
                    <a href="wholesale.php" class="btn-primary" style="width:auto;display:inline-flex;padding:10px 20px;margin:0;text-decoration:none;">
                        <i class="fa-solid fa-warehouse"></i> Go to Wholesale
                    </a>
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

        <div class="glass panel" style="padding:0;overflow:auto;margin-top:14px;" id="tableWrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Product Name</th>
                        <th class="text-right">Quantity</th>
                        <th class="text-right">Purchase</th>
                        <th class="text-right">Selling</th>
                        <?php if ($isAdmin): ?>
                            <th class="text-right">Profit/Item</th>
                            <th class="text-right">Profit %</th>
                        <?php endif; ?>
                        <th class="text-right">Stock Value</th>
                        <?php if ($isAdmin): ?><th class="text-center">Actions</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody id="productTableBody">
                    <?php foreach ($products as $i => $p):
                        $stock = $p['quantity'] * $p['price'];
                        $low = $p['quantity'] <= 5;
                        $profit = (float)$p['profit_per_item'];
                        $pct = (float)$p['profit_pct'];
                        $totalProfit = $profit * (int)$p['quantity'];

                        $pid = (int)$p['id'];
                        $purchaseHistory = $purchasesByProduct[$pid] ?? [];
                        $salesHistory = $salesByProduct[$pid] ?? [];

                        // Compute totals
                        $totalPurchasedQty = 0;
                        $totalPurchaseCost = 0;
                        foreach ($purchaseHistory as $ph) {
                            $totalPurchasedQty += (int)$ph['quantity'];
                            $totalPurchaseCost += (int)$ph['quantity'] * (float)$ph['cost_price'];
                        }

                        $totalSoldQty = 0;
                        $totalSoldRevenue = 0;
                        $totalSoldProfit = 0;
                        $totalSoldLoss = 0;
                        foreach ($salesHistory as $sh) {
                            $totalSoldQty += (int)$sh['quantity'];
                            $totalSoldRevenue += (float)$sh['total'];

                            $pl = (float)($sh['profit_loss'] ?? 0);
                            if ($pl > 0) {
                                $totalSoldProfit += $pl;
                            } elseif ($pl < 0) {
                                $totalSoldLoss += abs($pl);
                            }
                        }

                        $productDetailJson = json_encode([
                            'id'             => $pid,
                            'name'           => $p['name'],
                            'quantity'       => (int)$p['quantity'],
                            'price'          => (float)$p['price'],
                            'cost_price'     => (float)$p['cost_price'],
                            'profit_per_item'=> round($profit, 2),
                            'profit_pct'     => round($pct, 2),
                            'stock_value'    => round($stock, 2),
                            'total_profit'   => round($totalProfit, 2),
                            'created_at'     => date('d M Y, h:i A', strtotime($p['created_at'])),
                            'low_stock'      => $low,
                            'is_admin'       => $isAdmin,
                            'purchase_history' => array_map(function($r) {
                                return [
                                    'quantity'     => (int)$r['quantity'],
                                    'cost_price'   => (float)$r['cost_price'],
                                    'selling_price'=> (float)$r['selling_price'],
                                    'profit'       => (float)$r['profit_per_item'],
                                    'date'         => date('d M Y, h:i A', strtotime($r['purchased_at'])),
                                    'buyer'        => $r['buyer_name'] ?? 'Admin',
                                    'buyer_color'  => $r['buyer_color'] ?? null,
                                ];
                            }, $purchaseHistory),
                            'sales_history' => array_map(function($r) {
                                return [
                                    'quantity'    => (int)$r['quantity'],
                                    'price'       => (float)$r['price_per_item'],
                                    'total'       => (float)$r['total'],
                                    'profit_loss' => (float)($r['profit_loss'] ?? 0),
                                    'date'        => date('d M Y, h:i A', strtotime($r['sold_at'])),
                                    'seller'      => $r['seller_name'] ?? 'Unknown',
                                    'seller_color'=> $r['seller_color'] ?? null,
                                ];
                            }, $salesHistory),
                            'summary' => [
                                'total_purchased_qty'   => $totalPurchasedQty,
                                'total_purchase_cost'   => round($totalPurchaseCost, 2),
                                'total_sold_qty'        => $totalSoldQty,
                                'total_sold_revenue'    => round($totalSoldRevenue, 2),
                                'total_sold_profit'     => round($totalSoldProfit, 2),
                                'total_sold_loss'       => round($totalSoldLoss, 2),
                            ],
                        ], JSON_HEX_APOS | JSON_HEX_QUOT);
                    ?>
                    <tr data-name="<?= htmlspecialchars(strtolower($p['name'])) ?>">
                        <td class="muted row-num"><?= $i + 1 ?></td>
                        <td>
                            <button type="button" class="product-name-btn"
                                    data-popup-form="product-detail"
                                    data-product='<?= htmlspecialchars($productDetailJson, ENT_QUOTES, "UTF-8") ?>'>
                                <?= htmlspecialchars($p['name']) ?>
                            </button>
                            <?php if ($low): ?><span class="badge badge-danger" style="margin-left:6px;">Low</span><?php endif; ?>
                        </td>
                        <td class="text-right"><span class="<?= $low ? 'text-danger' : '' ?>"><strong><?= $p['quantity'] ?></strong></span></td>
                        <td class="text-right">Rs <?= number_format($p['cost_price'], 2) ?></td>
                        <td class="text-right">Rs <?= number_format($p['price'], 2) ?></td>
                        <?php if ($isAdmin): ?>
                            <td class="text-right" style="color:var(--success);font-weight:600;">
                                Rs <?= number_format($profit, 2) ?>
                            </td>
                            <td class="text-right" style="color:var(--success);font-weight:600;">
                                <?= number_format($pct, 1) ?>%
                            </td>
                        <?php endif; ?>
                        <td class="text-right"><strong>Rs <?= number_format($stock, 2) ?></strong></td>
                        <?php if ($isAdmin): ?>
                        <td class="text-center">
                            <div class="action-group">
                                <a href="wholesale.php?edit=<?= $p['id'] ?>" class="btn-icon" title="Edit wholesale">
                                    <i class="fa-solid fa-pen"></i>
                                </a>
                                <a href="product-delete.php?id=<?= $p['id'] ?>" class="btn-icon danger" title="Delete"
                                   data-confirm="This product will be permanently deleted."
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