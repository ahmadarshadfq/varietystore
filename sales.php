<?php
require 'config.php';
requireLogin();

$me = currentUser();

// Sale POST handler (from popup)
$popupError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_id'], $_POST['quantity'])) {
    $selectedId = (int)$_POST['product_id'];
    $quantity   = (int)$_POST['quantity'];
    if ($selectedId <= 0 || $quantity <= 0) {
        $popupError = "Product aur quantity dono select karein.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$selectedId]);
        $prod = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$prod) {
            $popupError = "Product nahi mila.";
        } elseif ($quantity > (int)$prod['quantity']) {
            $popupError = "Stock kam hai! Sirf {$prod['quantity']} available hain.";
        } else {
            $total = $quantity * (float)$prod['price'];
            $pdo->beginTransaction();
            try {
                $ins = $pdo->prepare("INSERT INTO sales (product_id, user_id, product_name, quantity, price_per_item, total) VALUES (?, ?, ?, ?, ?, ?)");
                $ins->execute([$prod['id'], $me['id'], $prod['name'], $quantity, $prod['price'], $total]);
                $upd = $pdo->prepare("UPDATE products SET quantity = quantity - ? WHERE id = ?");
                $upd->execute([$quantity, $prod['id']]);
                $pdo->commit();
                header("Location: sales.php?success=added");
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $popupError = "Sale save nahi hui: " . $e->getMessage();
            }
        }
    }
}

// Date filter
$filterType = $_GET['filter'] ?? 'month';
$dateParam  = $_GET['date'] ?? date('Y-m-d');
$monthParam = $_GET['month'] ?? date('Y-m');

if ($filterType === 'date') {
    $whereSql   = "WHERE DATE(s.sold_at) = :d";
    $params     = [':d' => $dateParam];
    $rangeLabel = "Date: " . date('d M Y', strtotime($dateParam));
} elseif ($filterType === 'all') {
    $whereSql   = "";
    $params     = [];
    $rangeLabel = "All time";
} else {
    $filterType = 'month';
    $whereSql   = "WHERE DATE_FORMAT(s.sold_at, '%Y-%m') = :m";
    $params     = [':m' => $monthParam];
    $rangeLabel = "Month: " . date('F Y', strtotime($monthParam . '-01'));
}

$stmt = $pdo->prepare("
    SELECT s.*, u.color AS user_color, u.username AS seller_name
    FROM sales s
    LEFT JOIN users u ON u.id = s.user_id
    $whereSql
    ORDER BY s.sold_at DESC
");
$stmt->execute($params);
$sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

$monthRevenue = 0;
$monthItems   = 0;
foreach ($sales as $s) {
    $monthRevenue += (float)$s['total'];
    $monthItems   += (int)$s['quantity'];
}

$productsForSale = $pdo->query("SELECT id, name, quantity, price FROM products WHERE quantity > 0 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$productsJson    = json_encode($productsForSale, JSON_HEX_APOS | JSON_HEX_QUOT);

$success = $_GET['success'] ?? '';
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
                    data-popup-form="sale"
                    data-action="sales.php"
                    data-products='<?= htmlspecialchars($productsJson, ENT_QUOTES, "UTF-8") ?>'
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

        <div class="cards" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
            <div class="card glass">
                <div class="card-icon green"><i class="fa-solid fa-sack-dollar"></i></div>
                <div>
                    <h3>Revenue (<?= $filterType === 'month' ? 'This Month' : ($filterType === 'date' ? 'Selected Date' : 'All Time') ?>)</h3>
                    <p class="big">Rs <?= number_format($monthRevenue, 0) ?></p>
                </div>
            </div>
            <div class="card glass">
                <div class="card-icon purple"><i class="fa-solid fa-cubes"></i></div>
                <div><h3>Items Sold</h3><p class="big"><?= $monthItems ?></p></div>
            </div>
        </div>

        <?php if (empty($sales)): ?>
            <div class="glass panel" style="text-align:center;padding:48px 20px;margin-top:16px;">
                <i class="fa-solid fa-receipt" style="font-size:40px;color:var(--text-light);margin-bottom:14px;"></i>
                <h3 style="margin-bottom:8px;">Koi sale record nahi mila</h3>
                <p class="muted" style="margin-bottom:16px;"><?= htmlspecialchars($rangeLabel) ?> ke liye koi data nahi hai.</p>
            </div>
        <?php else: ?>
        <div class="glass panel" style="padding:0;overflow:hidden;margin-top:16px;">
            <table class="table colored-sales-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Product</th>
                        <th>Sold By</th>
                        <th class="text-right">Quantity</th>
                        <th class="text-right">Price/Item</th>
                        <th class="text-right">Total</th>
                        <th>Date &amp; Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sales as $i => $s):
                        // Determine row background — solid tinted color
                        $userColor = $s['user_color'] ?? null;
                        if (!isValidHexColor($userColor)) {
                            // Unknown / no user → neutral gray
                            $rowBg = '#f1f5f9';
                            $dotColor = '#94a3b8';
                            $leftBar = '#cbd5e1';
                        } else {
                            // Light tinted version for readability
                            $rowBg = lightenColor($userColor, 0.82); // more saturated than 0.88
                            $dotColor = $userColor;
                            $leftBar = $userColor;
                        }
                    ?>
                    <tr style="background:<?= $rowBg ?>; box-shadow: inset 4px 0 0 <?= $leftBar ?>;">
                        <td class="muted"><?= $i + 1 ?></td>
                        <td><strong><?= htmlspecialchars($s['product_name']) ?></strong></td>
                        <td>
                            <span class="seller-chip">
                                <span class="seller-dot" style="background:<?= $dotColor ?>;"></span>
                                <?= htmlspecialchars($s['seller_name'] ?? 'Unknown') ?>
                            </span>
                        </td>
                        <td class="text-right"><?= $s['quantity'] ?></td>
                        <td class="text-right">Rs <?= number_format($s['price_per_item'], 2) ?></td>
                        <td class="text-right"><strong>Rs <?= number_format($s['total'], 2) ?></strong></td>
                        <td class="muted"><?= date('d M Y, h:i A', strtotime($s['sold_at'])) ?></td>
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
</script>

<script src="popup.js"></script>
</body>
</html>