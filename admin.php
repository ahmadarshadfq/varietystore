<?php
require 'config.php';
requireLogin();

$me = currentUser();
$username  = $me['username'];
$isAdmin   = $me['role'] === 'admin' && !isSwitched();
$switched  = isSwitched();
$adminInfo = $switched ? realAdmin() : null;

// Sale POST handler — custom price support
$popupError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_id'], $_POST['quantity'])) {
    $selectedId = (int)$_POST['product_id'];
    $quantity   = (int)$_POST['quantity'];
    $customPrice = isset($_POST['custom_price']) && $_POST['custom_price'] !== ''
        ? (float)$_POST['custom_price'] : null;

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
            $salePrice = $customPrice !== null ? $customPrice : (float)$prod['price'];
            $costPrice = (float)$prod['cost_price'];
            $total     = $quantity * $salePrice;
            $profitLoss = ($salePrice - $costPrice) * $quantity;

            $pdo->beginTransaction();
            try {
                $ins = $pdo->prepare("INSERT INTO sales (product_id, user_id, product_name, quantity, price_per_item, cost_at_sale, total, profit_loss) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $ins->execute([
                    $prod['id'], $me['id'], $prod['name'], $quantity,
                    $salePrice, $costPrice, $total, $profitLoss
                ]);
                $upd = $pdo->prepare("UPDATE products SET quantity = quantity - ? WHERE id = ?");
                $upd->execute([$quantity, $prod['id']]);
                $pdo->commit();
                header("Location: admin.php?success=sale_added");
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $popupError = "Sale save nahi hui: " . $e->getMessage();
            }
        }
    }
}

$success      = $_GET['success'] ?? '';
$profileError = $_GET['profile_error'] ?? '';
$memberError  = $_GET['member_error'] ?? '';
$errorParam   = $_GET['error'] ?? '';
$switchedAs   = $_GET['as'] ?? '';

// Stats
$totalProducts = (int) $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$totalStock    = (int) $pdo->query("SELECT COALESCE(SUM(quantity),0) FROM products")->fetchColumn();
$stockValue    = (float) $pdo->query("SELECT COALESCE(SUM(quantity * price),0) FROM products")->fetchColumn();
$lowStock      = (int) $pdo->query("SELECT COUNT(*) FROM products WHERE quantity <= 5")->fetchColumn();
$totalUsers    = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalMembers  = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'member'")->fetchColumn();

// Recent sales WITH user color
$recentSales = $pdo->query("
    SELECT s.*, u.color AS user_color, u.username AS seller_name
    FROM sales s
    LEFT JOIN users u ON u.id = s.user_id
    ORDER BY s.sold_at DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

$lowStockItems = $pdo->query("SELECT * FROM products WHERE quantity <= 5 ORDER BY quantity ASC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

$productsForSale = $pdo->query("SELECT id, name, quantity, price, cost_price FROM products WHERE quantity > 0 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$productsJson    = json_encode($productsForSale, JSON_HEX_APOS | JSON_HEX_QUOT);

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
                    <?php elseif ($isAdmin): ?>
                        <span class="badge badge-success" style="margin-left:6px;">Admin</span>
                    <?php else: ?>
                        <span class="badge" style="margin-left:6px;background:var(--info-bg);color:var(--info);">Team Member</span>
                    <?php endif; ?>
                </p>
            </div>
            <button type="button"
                    class="btn-primary"
                    data-popup-form="sale"
                    data-action="admin.php"
                    data-products='<?= htmlspecialchars($productsJson, ENT_QUOTES, "UTF-8") ?>'
                    style="width:auto;padding:10px 18px;margin:0;border:none;cursor:pointer;">
                <i class="fa-solid fa-cart-plus"></i> New Sale
            </button>
        </header>

        <?php if ($success === 'sale_added'): ?>
            <div class="alert alert-success" style="margin-bottom:16px;">
                <i class="fa-solid fa-circle-check"></i><span>Sale record ho gayi aur stock update ho gaya.</span>
            </div>
        <?php endif; ?>
        <?php if ($success === 'profile'): ?>
            <div class="alert alert-success" style="margin-bottom:16px;">
                <i class="fa-solid fa-circle-check"></i><span>Profile update ho gayi!</span>
            </div>
        <?php endif; ?>
        <?php if ($success === 'member_added'): ?>
            <div class="alert alert-success" style="margin-bottom:16px;">
                <i class="fa-solid fa-circle-check"></i><span>Team member add ho gaya!</span>
            </div>
        <?php endif; ?>
        <?php if ($success === 'member_updated'): ?>
            <div class="alert alert-success" style="margin-bottom:16px;">
                <i class="fa-solid fa-circle-check"></i><span>Team member update ho gaya!</span>
            </div>
        <?php endif; ?>
        <?php if ($success === 'member_deleted'): ?>
            <div class="alert alert-success" style="margin-bottom:16px;">
                <i class="fa-solid fa-circle-check"></i><span>Team member delete ho gaya!</span>
            </div>
        <?php endif; ?>
        <?php if ($success === 'switched'): ?>
            <div class="alert alert-success" style="margin-bottom:16px;">
                <i class="fa-solid fa-right-left"></i>
                <span>Account switched — ab aap <strong><?= htmlspecialchars($switchedAs ?: 'member') ?></strong> ke view mein hain. Admin panel wapas jane ke liye sidebar mein <strong>"Back to Admin"</strong> dabayein.</span>
            </div>
        <?php endif; ?>
        <?php if ($success === 'back_to_admin'): ?>
            <div class="alert alert-success" style="margin-bottom:16px;">
                <i class="fa-solid fa-user-shield"></i><span>Welcome back, Admin!</span>
            </div>
        <?php endif; ?>
        <?php if ($errorParam === 'no_permission'): ?>
            <div class="alert alert-error" style="margin-bottom:16px;">
                <i class="fa-solid fa-circle-exclamation"></i><span>Aap ke paas permission nahi hai.</span>
            </div>
        <?php endif; ?>
        <?php if ($profileError): ?>
            <div class="alert alert-error" style="margin-bottom:16px;">
                <i class="fa-solid fa-circle-exclamation"></i><span><?= htmlspecialchars($profileError) ?></span>
            </div>
        <?php endif; ?>
        <?php if ($memberError): ?>
            <div class="alert alert-error" style="margin-bottom:16px;">
                <i class="fa-solid fa-circle-exclamation"></i><span><?= htmlspecialchars($memberError) ?></span>
            </div>
        <?php endif; ?>
        <?php if ($popupError): ?>
            <div class="alert alert-error" style="margin-bottom:16px;">
                <i class="fa-solid fa-circle-exclamation"></i><span><?= htmlspecialchars($popupError) ?></span>
            </div>
        <?php endif; ?>

        <div class="cards">
            <div class="card glass">
                <div class="card-icon blue"><i class="fa-solid fa-box"></i></div>
                <div><h3>Products</h3><p class="big"><?= $totalProducts ?></p></div>
            </div>
            <div class="card glass">
                <div class="card-icon green"><i class="fa-solid fa-cubes"></i></div>
                <div><h3>Total Stock</h3><p class="big"><?= $totalStock ?></p></div>
            </div>
            <div class="card glass">
                <div class="card-icon purple"><i class="fa-solid fa-sack-dollar"></i></div>
                <div><h3>Stock Value</h3><p class="big">Rs <?= number_format($stockValue, 0) ?></p></div>
            </div>
            <div class="card glass">
                <div class="card-icon red"><i class="fa-solid fa-triangle-exclamation"></i></div>
                <div><h3>Low Stock</h3><p class="big"><?= $lowStock ?></p></div>
            </div>
            <?php if ($isAdmin): ?>
            <div class="card glass">
                <div class="card-icon blue"><i class="fa-solid fa-users"></i></div>
                <div><h3>Team Members</h3><p class="big"><?= $totalMembers ?></p></div>
            </div>
            <?php endif; ?>
        </div>

        <div class="grid-2">
            <div class="glass panel">
                <div class="panel-header">
                    <h2><i class="fa-solid fa-receipt"></i> Recent Sales</h2>
                    <a href="sales.php" class="link-sm">View all <i class="fa-solid fa-arrow-right"></i></a>
                </div>
                <?php if (empty($recentSales)): ?>
                    <p class="empty">Abhi tak koi sale nahi hui.</p>
                <?php else: ?>
                    <ul class="mini-list colored-mini-list">
                        <?php foreach ($recentSales as $s):
                            $userColor = $s['user_color'] ?? null;
                            if (!isValidHexColor($userColor)) {
                                $rowBg = '#1a212b';
                                $leftBar = '#30363d';
                            } else {
                                $rowBg = lightenColor($userColor, 0.35);
                                $leftBar = $userColor;
                            }
                        ?>
                            <li style="background:<?= $rowBg ?>; box-shadow: inset 3px 0 0 <?= $leftBar ?>; padding:10px 10px 10px 12px; border-radius:8px; margin-bottom:6px; border-bottom:none;">
                                <div>
                                    <strong style="color:#ffffff;"><?= htmlspecialchars($s['product_name']) ?></strong>
                                    <span style="display:flex;align-items:center;gap:5px;margin-top:3px;color:#ffffff;font-size:12px;opacity:0.95;">
                                        × <?= $s['quantity'] ?> @ Rs <?= number_format($s['price_per_item'], 2) ?>
                                        <?php if (!empty($s['seller_name'])): ?> • <?= htmlspecialchars($s['seller_name']) ?><?php endif; ?>
                                        <?php $pl = (float)($s['profit_loss'] ?? 0); ?>
                                        <?php if ($pl > 0): ?>
                                            <span style="color:#3fb950;font-weight:600;margin-left:6px;">+Rs <?= number_format($pl, 2) ?></span>
                                        <?php elseif ($pl < 0): ?>
                                            <span style="color:#f85149;font-weight:600;margin-left:6px;">-Rs <?= number_format(abs($pl), 2) ?></span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <div class="text-right">
                                    <strong style="color:#ffffff;">Rs <?= number_format($s['total'], 2) ?></strong>
                                    <span style="display:block;color:#ffffff;font-size:12px;opacity:0.9;"><?= date('d M, h:i A', strtotime($s['sold_at'])) ?></span>
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

<script src="popup.js"></script>
</body>
</html>