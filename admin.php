<?php
require 'config.php';
requireLogin();

$me = currentUser();
$username = $me['username'];
$isAdminView = ($me['role'] === 'admin' && !isSwitched());
$switched = isSwitched();
$adminInfo = $switched ? realAdmin() : null;

// POST: Multi-item sale
$popupError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sale_items'])) {
    $items = json_decode($_POST['sale_items'], true);
    $saleDate = $_POST['sale_date'] ?? date('Y-m-d');
    if (!$isAdminView) $saleDate = date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $saleDate)) $saleDate = date('Y-m-d');

    if (!is_array($items) || count($items) === 0) {
        $popupError = "Kam se kam ek product select karein.";
    } else {
        try {
            $pdo->beginTransaction();
            $totalQty = 0; $totalAmt = 0; $totalProfit = 0; $totalLoss = 0; $prepared = [];
            foreach ($items as $it) {
                $pid = (int)($it['product_id'] ?? 0);
                $qty = (int)($it['quantity'] ?? 0);
                $salePrice = (float)($it['sale_price'] ?? 0);
                if ($pid <= 0 || $qty <= 0) throw new Exception("Invalid item data.");
                $ps = $pdo->prepare("SELECT * FROM products WHERE id = ?");
                $ps->execute([$pid]);
                $prod = $ps->fetch(PDO::FETCH_ASSOC);
                if (!$prod) throw new Exception("Product not found.");
                if ($qty > (int)$prod['quantity']) throw new Exception("{$prod['name']}: Sirf {$prod['quantity']} available.");
                $costPrice = (float)$prod['cost_price'];
                $lineTotal = $salePrice * $qty;
                $linePL = ($salePrice - $costPrice) * $qty;
                $totalQty += $qty; $totalAmt += $lineTotal;
                if ($linePL >= 0) $totalProfit += $linePL; else $totalLoss += abs($linePL);
                $prepared[] = ['product_id'=>$pid,'product_name'=>$prod['name'],'quantity'=>$qty,'cost_price'=>$costPrice,'sale_price'=>$salePrice,'total'=>$lineTotal,'profit_loss'=>$linePL];
            }
            $insB = $pdo->prepare("INSERT INTO sales_batches (user_id, batch_date, total_quantity, total_amount, total_profit, total_loss) VALUES (?,?,?,?,?,?)");
            $insB->execute([$me['id'], $saleDate, $totalQty, $totalAmt, $totalProfit, $totalLoss]);
            $batchId = (int)$pdo->lastInsertId();
            $insI = $pdo->prepare("INSERT INTO sales_items (batch_id, product_id, product_name, quantity, cost_price, sale_price, total, profit_loss) VALUES (?,?,?,?,?,?,?,?)");
            $updStock = $pdo->prepare("UPDATE products SET quantity = quantity - ? WHERE id = ?");
            foreach ($prepared as $p) {
                $insI->execute([$batchId, $p['product_id'], $p['product_name'], $p['quantity'], $p['cost_price'], $p['sale_price'], $p['total'], $p['profit_loss']]);
                $updStock->execute([$p['quantity'], $p['product_id']]);
            }
            $pdo->commit();
            header("Location: admin.php?success=sale_added");
            exit;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $popupError = $e->getMessage();
        }
    }
}

$success = $_GET['success'] ?? '';
$profileError = $_GET['profile_error'] ?? '';
$memberError = $_GET['member_error'] ?? '';
$errorParam = $_GET['error'] ?? '';
$switchedAs = $_GET['as'] ?? '';

$totalProducts = (int) $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$totalStock = (int) $pdo->query("SELECT COALESCE(SUM(quantity),0) FROM products")->fetchColumn();
$stockValue = (float) $pdo->query("SELECT COALESCE(SUM(quantity * price),0) FROM products")->fetchColumn();
$lowStock = (int) $pdo->query("SELECT COUNT(*) FROM products WHERE quantity <= 5")->fetchColumn();
$totalMembers = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'member'")->fetchColumn();

$recentBatches = $pdo->query("
    SELECT b.*,
           u.color AS user_color,
           u.username AS seller_name,
           (SELECT COUNT(*) FROM sales_items WHERE batch_id = b.id) AS items_count,
           (SELECT COUNT(DISTINCT product_name) FROM sales_items WHERE batch_id = b.id) AS unique_products,
           (SELECT product_name FROM sales_items WHERE batch_id = b.id ORDER BY id ASC LIMIT 1) AS first_product_name
    FROM sales_batches b
    LEFT JOIN users u ON u.id = b.user_id
    ORDER BY b.created_at DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

$lowStockItems = $pdo->query("SELECT * FROM products WHERE quantity <= 5 ORDER BY quantity ASC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
$productsForSale = $pdo->query("SELECT id, name, quantity, price, cost_price FROM products WHERE quantity > 0 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$productsJson = json_encode($productsForSale, JSON_HEX_APOS | JSON_HEX_QUOT);

$activePage = 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dashboard • Inventory</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="style.css">
</head>
<body class="dash-body">

<div class="dashboard">
    <?php include 'sidebar.php'; ?>

    <main class="main-content">
        <header class="topbar">
            <div>
                <h1>Dashboard</h1>
                <p class="subtitle" style="text-align:left;margin:0;">
                    Welcome back, <?= htmlspecialchars($username) ?>
                    <?php if ($switched): ?>
                        <span class="badge" style="margin-left:6px;background:var(--info-bg);color:var(--info);">Switched View</span>
                    <?php elseif ($isAdminView): ?>
                        <span class="badge badge-success" style="margin-left:6px;">Admin</span>
                    <?php else: ?>
                        <span class="badge" style="margin-left:6px;background:var(--info-bg);color:var(--info);">Team Member</span>
                    <?php endif; ?>
                </p>
            </div>
            <button type="button"
                    class="btn-primary"
                    data-popup-form="multi-sale"
                    data-action="admin.php"
                    data-products='<?= htmlspecialchars($productsJson, ENT_QUOTES, "UTF-8") ?>'
                    data-is-admin="<?= $isAdminView ? '1' : '0' ?>"
                    style="width:auto;padding:10px 18px;margin:0;border:none;cursor:pointer;">
                <i class="fa-solid fa-cart-plus"></i> New Sale
            </button>
        </header>

        <?php if ($success === 'sale_added'): ?>
            <div class="alert alert-success" style="margin-bottom:16px;"><i class="fa-solid fa-circle-check"></i><span>Sale record ho gayi!</span></div>
        <?php endif; ?>
        <?php if ($success === 'sale_deleted'): ?>
            <div class="alert alert-success" style="margin-bottom:16px;"><i class="fa-solid fa-circle-check"></i><span>Sale delete ho gayi aur stock wapas add ho gaya.</span></div>
        <?php endif; ?>
        <?php if ($success === 'profile'): ?>
            <div class="alert alert-success" style="margin-bottom:16px;"><i class="fa-solid fa-circle-check"></i><span>Profile update ho gayi!</span></div>
        <?php endif; ?>
        <?php if ($success === 'member_added'): ?>
            <div class="alert alert-success" style="margin-bottom:16px;"><i class="fa-solid fa-circle-check"></i><span>Team member add ho gaya!</span></div>
        <?php endif; ?>
        <?php if ($success === 'member_updated'): ?>
            <div class="alert alert-success" style="margin-bottom:16px;"><i class="fa-solid fa-circle-check"></i><span>Team member update ho gaya!</span></div>
        <?php endif; ?>
        <?php if ($success === 'member_deleted'): ?>
            <div class="alert alert-success" style="margin-bottom:16px;"><i class="fa-solid fa-circle-check"></i><span>Team member delete ho gaya!</span></div>
        <?php endif; ?>
        <?php if ($success === 'switched'): ?>
            <div class="alert alert-success" style="margin-bottom:16px;"><i class="fa-solid fa-right-left"></i><span>Account switched to <strong><?= htmlspecialchars($switchedAs ?: 'member') ?></strong>. Sidebar mein "Back to Admin" dabayein.</span></div>
        <?php endif; ?>
        <?php if ($success === 'back_to_admin'): ?>
            <div class="alert alert-success" style="margin-bottom:16px;"><i class="fa-solid fa-user-shield"></i><span>Welcome back, Admin!</span></div>
        <?php endif; ?>
        <?php if ($errorParam === 'no_permission'): ?>
            <div class="alert alert-error" style="margin-bottom:16px;"><i class="fa-solid fa-circle-exclamation"></i><span>Aap ke paas permission nahi hai.</span></div>
        <?php endif; ?>
        <?php if ($profileError): ?>
            <div class="alert alert-error" style="margin-bottom:16px;"><i class="fa-solid fa-circle-exclamation"></i><span><?= htmlspecialchars($profileError) ?></span></div>
        <?php endif; ?>
        <?php if ($memberError): ?>
            <div class="alert alert-error" style="margin-bottom:16px;"><i class="fa-solid fa-circle-exclamation"></i><span><?= htmlspecialchars($memberError) ?></span></div>
        <?php endif; ?>
        <?php if ($popupError): ?>
            <div class="alert alert-error" style="margin-bottom:16px;"><i class="fa-solid fa-circle-exclamation"></i><span><?= htmlspecialchars($popupError) ?></span></div>
        <?php endif; ?>

        <div class="cards">
            <div class="card glass"><div class="card-icon blue"><i class="fa-solid fa-box"></i></div><div><h3>Products</h3><p class="big"><?= $totalProducts ?></p></div></div>
            <div class="card glass"><div class="card-icon green"><i class="fa-solid fa-cubes"></i></div><div><h3>Total Stock</h3><p class="big"><?= $totalStock ?></p></div></div>
            <div class="card glass"><div class="card-icon purple"><i class="fa-solid fa-sack-dollar"></i></div><div><h3>Stock Value</h3><p class="big">Rs <?= number_format($stockValue, 0) ?></p></div></div>
            <div class="card glass"><div class="card-icon red"><i class="fa-solid fa-triangle-exclamation"></i></div><div><h3>Low Stock</h3><p class="big"><?= $lowStock ?></p></div></div>
            <?php if ($isAdminView): ?>
            <div class="card glass"><div class="card-icon blue"><i class="fa-solid fa-users"></i></div><div><h3>Team Members</h3><p class="big"><?= $totalMembers ?></p></div></div>
            <?php endif; ?>
        </div>

        <div class="grid-2">
            <div class="glass panel">
                <div class="panel-header">
                    <h2><i class="fa-solid fa-receipt"></i> Recent Sales</h2>
                    <a href="sales.php" class="link-sm">View all <i class="fa-solid fa-arrow-right"></i></a>
                </div>
                <?php if (empty($recentBatches)): ?>
                    <p class="empty">Abhi tak koi sale nahi hui.</p>
                <?php else: ?>
                    <ul class="mini-list">
                        <?php foreach ($recentBatches as $b):
                            $rowColor = isValidHexColor($b['user_color'] ?? null) ? lightenColor($b['user_color'], 0.15) : '#1a1f2e';
                            $uniqueProducts = (int)$b['unique_products'];
                            $isMulti = $uniqueProducts > 1;
                            $singleName = $b['first_product_name'] ?? 'Unknown';
                        ?>
                            <li style="background:<?= $rowColor ?>; padding:8px; border-radius:6px; margin-bottom:4px;">
                                <div style="flex:1;min-width:0;<?= $isMulti ? 'cursor:pointer;' : '' ?>"
                                     class="<?= $isMulti ? 'sale-batch-row' : '' ?>"
                                     <?php if ($isMulti): ?>data-sale-id="<?= (int)$b['id'] ?>"<?php endif; ?>>
                                    <?php if ($isMulti): ?>
                                        <strong><i class="fa-solid fa-layer-group" style="color:var(--primary);margin-right:4px;"></i> Multi Sale #<?= (int)$b['id'] ?></strong>
                                        <span class="muted"><?= $uniqueProducts ?> products · <?= (int)$b['total_quantity'] ?> qty<?php if (!empty($b['seller_name'])): ?> · by <?= htmlspecialchars($b['seller_name']) ?><?php endif; ?></span>
                                    <?php else: ?>
                                        <strong><?= htmlspecialchars($singleName) ?></strong>
                                        <span class="muted"><?= (int)$b['total_quantity'] ?> qty<?php if (!empty($b['seller_name'])): ?> · by <?= htmlspecialchars($b['seller_name']) ?><?php endif; ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-right" style="display:flex;align-items:center;gap:8px;">
                                    <div>
                                        <strong>Rs <?= number_format($b['total_amount'], 2) ?></strong>
                                        <span class="muted"><?= date('d M, h:i A', strtotime($b['created_at'])) ?></span>
                                    </div>
                                    <?php if ($isAdminView): ?>
                                    <a href="sale-delete.php?id=<?= (int)$b['id'] ?>&from=admin"
                                       class="btn-icon danger" title="Delete sale"
                                       data-confirm="Is sale ko delete karein? Poori quantity stock mein wapas add ho jayegi."
                                       data-confirm-title="Delete Sale #<?= (int)$b['id'] ?>?"
                                       data-confirm-btn="Yes, Delete"
                                       data-cancel-btn="Cancel"
                                       data-danger="true"
                                       data-type="danger"
                                       data-icon='<i class="fa-solid fa-trash-can"></i>'>
                                        <i class="fa-solid fa-trash"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <div class="glass panel">
                <div class="panel-header">
                    <h2><i class="fa-solid fa-triangle-exclamation"></i> Low Stock Alert</h2>
                    <a href="products.php" class="link-sm">All products <i class="fa-solid fa-arrow-right"></i></a>
                </div>
                <?php if (empty($lowStockItems)): ?>
                    <p class="empty">Sab theek hai! Koi low stock product nahi.</p>
                <?php else: ?>
                    <ul class="mini-list">
                        <?php foreach ($lowStockItems as $p): ?>
                            <li>
                                <div>
                                    <strong><?= htmlspecialchars($p['name']) ?></strong>
                                    <span class="muted">Rs <?= number_format($p['price'], 2) ?> / item</span>
                                </div>
                                <span class="badge badge-danger"><?= $p['quantity'] ?> left</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<script>
document.querySelectorAll('.sale-batch-row').forEach(row => {
    row.addEventListener('click', function(e) {
        // Agar delete button pe click hua to popup na khule
        if (e.target.closest('a[data-confirm]')) return;
        const id = this.dataset.saleId;
        fetch('sales-detail.php?id=' + id)
            .then(r => r.json())
            .then(data => {
                if (data.error) { alert(data.error); return; }
                if (window.showSaleDetailPopup) window.showSaleDetailPopup(data);
            });
    });
});
</script>

<script src="popup.js"></script>
</body>
</html>