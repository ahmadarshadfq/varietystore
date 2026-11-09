<?php
require 'config.php';
requireLogin();

$me = currentUser();
$isAdminView = ($me['role'] === 'admin' && !isSwitched());

// ---------- POST: Multi-item sale ----------
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
                if ($qty > (int)$prod['quantity']) throw new Exception("{$prod['name']}: Sirf {$prod['quantity']} available hain.");
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
            header("Location: sales.php?success=added");
            exit;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $popupError = $e->getMessage();
        }
    }
}

// ---------- Date filter ----------
$filterType = $_GET['filter'] ?? 'month';
$dateParam  = $_GET['date'] ?? date('Y-m-d');
$monthParam = $_GET['month'] ?? date('Y-m');

if ($filterType === 'date') {
    $whereSql = "WHERE DATE(b.batch_date) = :d";
    $params = [':d' => $dateParam];
    $rangeLabel = "Date: " . date('d M Y', strtotime($dateParam));
} elseif ($filterType === 'all') {
    $whereSql = ""; $params = []; $rangeLabel = "All time";
} else {
    $filterType = 'month';
    $whereSql = "WHERE DATE_FORMAT(b.batch_date, '%Y-%m') = :m";
    $params = [':m' => $monthParam];
    $rangeLabel = "Month: " . date('F Y', strtotime($monthParam . '-01'));
}

$stmt = $pdo->prepare("
    SELECT b.*,
           u.color AS user_color,
           u.username AS seller_name,
           (SELECT COUNT(*) FROM sales_items WHERE batch_id = b.id) AS items_count,
           (SELECT COUNT(DISTINCT product_name) FROM sales_items WHERE batch_id = b.id) AS unique_products,
           (SELECT product_name FROM sales_items WHERE batch_id = b.id ORDER BY id ASC LIMIT 1) AS first_product_name
    FROM sales_batches b
    LEFT JOIN users u ON u.id = b.user_id
    $whereSql
    ORDER BY b.batch_date DESC, b.created_at DESC
");
$stmt->execute($params);
$batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

$monthRevenue = 0; $monthItems = 0; $monthProfit = 0; $monthLoss = 0;
foreach ($batches as $b) {
    $monthRevenue += (float)$b['total_amount'];
    $monthItems += (int)$b['total_quantity'];
    $monthProfit += (float)$b['total_profit'];
    $monthLoss += (float)$b['total_loss'];
}

$productsForSale = $pdo->query("SELECT id, name, quantity, price, cost_price FROM products WHERE quantity > 0 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$productsJson = json_encode($productsForSale, JSON_HEX_APOS | JSON_HEX_QUOT);

$success = $_GET['success'] ?? '';
$errorParam = $_GET['error'] ?? '';
$activePage = 'sales';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sales • Inventory</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="style.css">
</head>
<body class="dash-body">

<div class="dashboard">
    <?php include 'sidebar.php'; ?>

    <main class="main-content">
        <header class="topbar">
            <div>
                <h1>Sales</h1>
                <p class="subtitle" style="text-align:left;margin:0;">Showing: <strong><?= htmlspecialchars($rangeLabel) ?></strong></p>
            </div>
            <button type="button"
                    class="btn-primary"
                    data-popup-form="multi-sale"
                    data-action="sales.php"
                    data-products='<?= htmlspecialchars($productsJson, ENT_QUOTES, "UTF-8") ?>'
                    data-is-admin="<?= $isAdminView ? '1' : '0' ?>"
                    style="width:auto;padding:10px 18px;margin:0;border:none;cursor:pointer;">
                <i class="fa-solid fa-cart-plus"></i> New Sale
            </button>
        </header>

        <?php if ($success === 'added'): ?>
            <div class="alert alert-success" style="margin-bottom:16px;">
                <i class="fa-solid fa-circle-check"></i>
                <span>Sale record ho gayi aur stock update ho gaya.</span>
            </div>
        <?php endif; ?>
        <?php if ($success === 'deleted'): ?>
            <div class="alert alert-success" style="margin-bottom:16px;">
                <i class="fa-solid fa-circle-check"></i>
                <span>Sale delete ho gayi aur stock wapas add ho gaya.</span>
            </div>
        <?php endif; ?>
        <?php if ($errorParam === 'not_found'): ?>
            <div class="alert alert-error" style="margin-bottom:16px;">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span>Sale nahi mili.</span>
            </div>
        <?php endif; ?>
        <?php if ($errorParam && $errorParam !== 'not_found'): ?>
            <div class="alert alert-error" style="margin-bottom:16px;">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span><?= htmlspecialchars($errorParam) ?></span>
            </div>
        <?php endif; ?>
        <?php if ($popupError): ?>
            <div class="alert alert-error" style="margin-bottom:16px;">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span><?= htmlspecialchars($popupError) ?></span>
            </div>
        <?php endif; ?>

        <div class="filter-bar">
            <form method="GET" class="filter-form">
                <div class="filter-tabs">
                    <label class="tab <?= $filterType === 'month' ? 'active' : '' ?>">
                        <input type="radio" name="filter" value="month" <?= $filterType === 'month' ? 'checked' : '' ?> onchange="this.form.submit()">
                        <i class="fa-solid fa-calendar"></i> This Month
                    </label>
                    <label class="tab <?= $filterType === 'date' ? 'active' : '' ?>">
                        <input type="radio" name="filter" value="date" <?= $filterType === 'date' ? 'checked' : '' ?> onchange="this.form.submit()">
                        <i class="fa-solid fa-calendar-day"></i> Specific Date
                    </label>
                    <label class="tab <?= $filterType === 'all' ? 'active' : '' ?>">
                        <input type="radio" name="filter" value="all" <?= $filterType === 'all' ? 'checked' : '' ?> onchange="this.form.submit()">
                        <i class="fa-solid fa-list"></i> All Time
                    </label>
                </div>
                <div class="filter-inputs">
                    <div class="filter-input-group" id="monthGroup" style="<?= $filterType !== 'month' ? 'display:none' : '' ?>">
                        <i class="fa-solid fa-calendar input-icon"></i>
                        <input type="month" name="month" value="<?= htmlspecialchars($monthParam) ?>">
                    </div>
                    <div class="filter-input-group" id="dateGroup" style="<?= $filterType !== 'date' ? 'display:none' : '' ?>">
                        <i class="fa-solid fa-calendar-day input-icon"></i>
                        <input type="date" name="date" value="<?= htmlspecialchars($dateParam) ?>">
                    </div>
                    <button type="submit" class="btn-primary" style="width:auto;padding:11px 18px;margin:0;">
                        <i class="fa-solid fa-filter"></i> Apply
                    </button>
                </div>
            </form>
        </div>

        <div class="cards" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
            <div class="card glass">
                <div class="card-icon green"><i class="fa-solid fa-sack-dollar"></i></div>
                <div><h3>Revenue</h3><p class="big">Rs <?= number_format($monthRevenue, 0) ?></p></div>
            </div>
            <div class="card glass">
                <div class="card-icon purple"><i class="fa-solid fa-cubes"></i></div>
                <div><h3>Items Sold</h3><p class="big"><?= $monthItems ?></p></div>
            </div>
            <?php if ($isAdminView): ?>
            <div class="card glass">
                <div class="card-icon green"><i class="fa-solid fa-chart-line"></i></div>
                <div><h3>Total Profit</h3><p class="big" style="color:var(--success);">Rs <?= number_format($monthProfit, 0) ?></p></div>
            </div>
            <div class="card glass">
                <div class="card-icon red"><i class="fa-solid fa-arrow-trend-down"></i></div>
                <div><h3>Total Loss</h3><p class="big" style="color:var(--danger);">Rs <?= number_format($monthLoss, 0) ?></p></div>
            </div>
            <?php endif; ?>
        </div>

        <?php if (empty($batches)): ?>
            <div class="glass panel" style="text-align:center;padding:48px 20px;margin-top:16px;">
                <i class="fa-solid fa-receipt" style="font-size:40px;color:var(--text-light);margin-bottom:14px;"></i>
                <h3 style="margin-bottom:8px;">Koi sale record nahi mila</h3>
                <p class="muted"><?= htmlspecialchars($rangeLabel) ?> ke liye koi data nahi hai.</p>
            </div>
        <?php else: ?>
        <div class="glass panel" style="padding:0;overflow:auto;margin-top:16px;">
            <table class="table colored-sales-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Product</th>
                        <th>Sold By</th>
                        <th class="text-right">Qty</th>
                        <th class="text-right">Total</th>
                        <?php if ($isAdminView): ?><th class="text-right">P / L</th><?php endif; ?>
                        <th>Date</th>
                        <?php if ($isAdminView): ?><th class="text-center">Action</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($batches as $i => $b):
                        $userColor = $b['user_color'] ?? null;
                        if (!isValidHexColor($userColor)) {
                            $rowBg = '#1a1f2e'; $dotColor = '#64748b'; $leftBar = '#475569';
                        } else {
                            $rowBg = lightenColor($userColor, 0.15);
                            $dotColor = $userColor;
                            $leftBar = $userColor;
                        }
                        $pl = (float)$b['total_profit'] - (float)$b['total_loss'];
                        $uniqueProducts = (int)$b['unique_products'];
                        $isMulti = $uniqueProducts > 1;
                        $singleName = $b['first_product_name'] ?? 'Unknown';
                    ?>
                    <tr style="background:<?= $rowBg ?>; box-shadow: inset 4px 0 0 <?= $leftBar ?>;">
                        <td class="muted"><?= $i + 1 ?></td>
                        <td>
                            <?php if ($isMulti): ?>
                                <button type="button" class="sale-batch-btn" data-sale-id="<?= (int)$b['id'] ?>">
                                    <i class="fa-solid fa-layer-group"></i>
                                    Multi Sale #<?= (int)$b['id'] ?>
                                </button>
                            <?php else: ?>
                                <strong><?= htmlspecialchars($singleName) ?></strong>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="seller-chip">
                                <span class="seller-dot" style="background:<?= $dotColor ?>;"></span>
                                <?= htmlspecialchars($b['seller_name'] ?? 'Unknown') ?>
                            </span>
                        </td>
                        <td class="text-right"><?= (int)$b['total_quantity'] ?></td>
                        <td class="text-right"><strong>Rs <?= number_format($b['total_amount'], 2) ?></strong></td>
                        <?php if ($isAdminView): ?>
                        <td class="text-right">
                            <?php if ($pl > 0): ?>
                                <span style="color:var(--success);font-weight:600;">+Rs <?= number_format($pl, 0) ?></span>
                            <?php elseif ($pl < 0): ?>
                                <span style="color:var(--danger);font-weight:600;">-Rs <?= number_format(abs($pl), 0) ?></span>
                            <?php else: ?>
                                <span class="muted">Rs 0</span>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                        <td class="muted"><?= date('d M Y', strtotime($b['batch_date'])) ?></td>
                        <?php if ($isAdminView): ?>
                        <td class="text-center">
                            <a href="sale-delete.php?id=<?= (int)$b['id'] ?>&from=sales"
                               class="btn-icon danger" title="Delete sale"
                               data-confirm="Is sale ko delete karein? Sale ki poori quantity products ke stock mein wapas add ho jayegi. Ye action undo nahi ho sakta."
                               data-confirm-title="Delete Sale #<?= (int)$b['id'] ?>?"
                               data-confirm-btn="Yes, Delete"
                               data-cancel-btn="Cancel"
                               data-danger="true"
                               data-type="danger"
                               data-icon='<i class="fa-solid fa-trash-can"></i>'>
                                <i class="fa-solid fa-trash"></i>
                            </a>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </main>
</div>

<script>
document.querySelectorAll('.filter-tabs input[type="radio"]').forEach(function(radio) {
    radio.addEventListener('change', function() {
        const val = this.value;
        document.getElementById('monthGroup').style.display = val === 'month' ? '' : 'none';
        document.getElementById('dateGroup').style.display  = val === 'date'  ? '' : 'none';
    });
});

document.querySelectorAll('.sale-batch-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const id = this.dataset.saleId;
        fetch('sales-detail.php?id=' + id)
            .then(r => r.json())
            .then(data => {
                if (data.error) { alert(data.error); return; }
                if (window.showSaleDetailPopup) window.showSaleDetailPopup(data);
            })
            .catch(err => alert('Error loading sale detail'));
    });
});
</script>

<script src="popup.js"></script>
</body>
</html>