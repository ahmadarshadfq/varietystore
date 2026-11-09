<?php
require 'config.php';
requireAdmin();

$success = $_GET['success'] ?? '';
$error   = $_GET['error'] ?? '';
$editId  = (int)($_GET['edit'] ?? 0);

// Edit mode: load product
$editProduct = null;
if ($editId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$editId]);
    $editProduct = $stmt->fetch(PDO::FETCH_ASSOC);
}

// All products (with cost + profit info)
$products = $pdo->query("
    SELECT p.*,
           (p.price - p.cost_price) AS profit_per_item,
           CASE WHEN p.cost_price > 0 THEN ((p.price - p.cost_price) / p.cost_price * 100) ELSE 0 END AS profit_pct
    FROM products p
    ORDER BY p.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

$activePage = 'wholesale';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Wholesale • Inventory</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="style.css">
</head>
<body class="dash-body">

<div class="dashboard">
    <?php include 'sidebar.php'; ?>

    <main class="main-content">
        <header class="topbar">
            <div>
                <h1>Wholesale</h1>
                <p class="subtitle" style="text-align:left;margin:0;">Purchase price + profit set karke product add karein</p>
            </div>
        </header>

        <?php if ($success === 'added'): ?>
            <div class="alert alert-success" style="margin-bottom:16px;">
                <i class="fa-solid fa-circle-check"></i>
                <span>Wholesale product add ho gaya aur Products page pe bhi show ho raha hai!</span>
            </div>
        <?php elseif ($success === 'updated'): ?>
            <div class="alert alert-success" style="margin-bottom:16px;">
                <i class="fa-solid fa-circle-check"></i>
                <span>Wholesale product update ho gaya!</span>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error" style="margin-bottom:16px;">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <!-- ============ WHOLESALE FORM ============ -->
        <div class="glass panel" style="margin-bottom:20px;">
            <div class="panel-header" style="margin-bottom:16px;">
                <h2><i class="fa-solid fa-warehouse"></i> <?= $editProduct ? 'Edit Wholesale Product' : 'Add Wholesale Product' ?></h2>
                <?php if ($editProduct): ?>
                    <a href="wholesale.php" class="link-sm">Cancel edit <i class="fa-solid fa-xmark"></i></a>
                <?php endif; ?>
            </div>

            <form method="POST" action="wholesale-add.php" autocomplete="off" id="wholesaleForm">
                <?php if ($editProduct): ?>
                    <input type="hidden" name="edit_id" value="<?= (int)$editProduct['id'] ?>">
                <?php endif; ?>

                <div class="wholesale-grid">
                    <div>
                        <label class="form-label">Product Name</label>
                        <div class="input-group">
                            <i class="fa-solid fa-box input-icon"></i>
                            <input type="text" name="name" placeholder="e.g. Sugar 1kg"
                                   value="<?= htmlspecialchars($editProduct['name'] ?? '') ?>" required>
                        </div>
                    </div>

                    <div>
                        <label class="form-label">Quantity</label>
                        <div class="input-group">
                            <i class="fa-solid fa-cubes input-icon"></i>
                            <input type="number" name="quantity" id="wQuantity" placeholder="e.g. 100"
                                   value="<?= htmlspecialchars($editProduct['quantity'] ?? '') ?>" min="0" step="1" required>
                        </div>
                    </div>

                    <div>
                        <label class="form-label">Purchase Price / Item (Rs)</label>
                        <div class="input-group">
                            <i class="fa-solid fa-tag input-icon"></i>
                            <input type="number" name="cost_price" id="wCostPrice" placeholder="e.g. 100.00"
                                   value="<?= htmlspecialchars($editProduct['cost_price'] ?? '') ?>" min="0" step="0.01" required>
                        </div>
                    </div>

                    <div>
                        <label class="form-label">Profit Type</label>
                        <div class="input-group">
                            <i class="fa-solid fa-percent input-icon"></i>
                            <select name="profit_type" id="wProfitType" required>
                                <option value="percent">Percentage (%)</option>
                                <option value="flat">Flat Amount (Rs)</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="form-label">Profit Value</label>
                        <div class="input-group">
                            <i class="fa-solid fa-chart-line input-icon"></i>
                            <input type="number" name="profit_value" id="wProfitValue" placeholder="e.g. 20"
                                   min="0" step="0.01" required>
                        </div>
                    </div>

                    <div>
                        <label class="form-label">Selling Price / Item (Rs)</label>
                        <div class="input-group">
                            <i class="fa-solid fa-sack-dollar input-icon"></i>
                            <input type="number" name="price" id="wSellingPrice" placeholder="Auto"
                                   value="<?= htmlspecialchars($editProduct['price'] ?? '') ?>" min="0" step="0.01" required>
                        </div>
                    </div>
                </div>

                <div class="wholesale-summary" id="wsSummary">
                    <div class="ws-item">
                        <span>Purchase</span>
                        <strong id="wsCost">Rs 0.00</strong>
                    </div>
                    <div class="ws-item">
                        <span>Profit / Item</span>
                        <strong id="wsProfit" style="color:var(--success);">Rs 0.00</strong>
                    </div>
                    <div class="ws-item">
                        <span>Selling Price</span>
                        <strong id="wsSell">Rs 0.00</strong>
                    </div>
                    <div class="ws-item">
                        <span>Total Stock Value</span>
                        <strong id="wsStockVal">Rs 0.00</strong>
                    </div>
                    <div class="ws-item">
                        <span>Total Potential Profit</span>
                        <strong id="wsTotProfit" style="color:var(--success);">Rs 0.00</strong>
                    </div>
                </div>

                <div style="display:flex;gap:10px;margin-top:16px;">
                    <button type="submit" class="btn-primary" style="width:auto;padding:11px 20px;margin:0;">
                        <i class="fa-solid fa-check"></i> <?= $editProduct ? 'Update Product' : 'Add to Products' ?>
                    </button>
                    <?php if ($editProduct): ?>
                        <a href="wholesale.php" class="btn-secondary">
                            <i class="fa-solid fa-xmark"></i> Cancel
                        </a>
                    <?php endif; ?>
                </div>

                <p class="qty-hint" style="margin-top:16px;">
                    <i class="fa-solid fa-circle-info"></i>
                    Ye product add hote hi <strong>Products page</strong> pe automatically show ho jayega. Selling price auto-calculate hoti hai, lekin aap manually bhi override kar sakte hain.
                </p>
            </form>
        </div>

        <!-- ============ PRODUCTS LIST WITH PROFIT ============ -->
        <div class="glass panel" style="padding:0; overflow:hidden;">
            <div style="padding:20px;">
                <h2 style="font-size:15px;font-weight:600;display:flex;align-items:center;gap:8px;margin-bottom:14px;">
                    <i class="fa-solid fa-list" style="color:var(--primary);"></i> All Products & Profit
                </h2>
            </div>

            <?php if (empty($products)): ?>
                <p class="empty" style="padding:32px 20px;">Koi product nahi hai. Upar se pehla wholesale product add karein.</p>
            <?php else: ?>
            <div class="wholesale-table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Product</th>
                            <th class="text-right">Qty</th>
                            <th class="text-right">Purchase</th>
                            <th class="text-right">Selling</th>
                            <th class="text-right">Profit/Item</th>
                            <th class="text-right">Profit %</th>
                            <th class="text-right">Total Profit Potential</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $i => $p):
                            $profit = (float)$p['profit_per_item'];
                            $pct    = (float)$p['profit_pct'];
                            $totalProfit = $profit * (int)$p['quantity'];
                        ?>
                        <tr>
                            <td class="muted"><?= $i + 1 ?></td>
                            <td><strong><?= htmlspecialchars($p['name']) ?></strong></td>
                            <td class="text-right"><?= (int)$p['quantity'] ?></td>
                            <td class="text-right">Rs <?= number_format($p['cost_price'], 2) ?></td>
                            <td class="text-right">Rs <?= number_format($p['price'], 2) ?></td>
                            <td class="text-right" style="color:var(--success);font-weight:600;">
                                Rs <?= number_format($profit, 2) ?>
                            </td>
                            <td class="text-right" style="color:var(--success);font-weight:600;">
                                <?= number_format($pct, 1) ?>%
                            </td>
                            <td class="text-right"><strong>Rs <?= number_format($totalProfit, 2) ?></strong></td>
                            <td class="text-center">
                                <div class="action-group">
                                    <a href="wholesale.php?edit=<?= $p['id'] ?>" class="btn-icon" title="Edit wholesale">
                                        <i class="fa-solid fa-pen"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<script>
(function () {
    const costEl = document.getElementById('wCostPrice');
    const typeEl = document.getElementById('wProfitType');
    const profitValEl = document.getElementById('wProfitValue');
    const sellEl = document.getElementById('wSellingPrice');
    const qtyEl = document.getElementById('wQuantity');

    const wsCost = document.getElementById('wsCost');
    const wsProfit = document.getElementById('wsProfit');
    const wsSell = document.getElementById('wsSell');
    const wsStockVal = document.getElementById('wsStockVal');
    const wsTotProfit = document.getElementById('wsTotProfit');

    if (!costEl) return;

    let manualSellOverride = false;

    function fmt(n) { return 'Rs ' + (parseFloat(n) || 0).toFixed(2); }

    function calcSell() {
        const cost = parseFloat(costEl.value) || 0;
        const val = parseFloat(profitValEl.value) || 0;
        const type = typeEl.value;
        if (type === 'percent') return cost * (1 + val / 100);
        return cost + val;
    }

    function update() {
        const cost = parseFloat(costEl.value) || 0;
        const val = parseFloat(profitValEl.value) || 0;
        const type = typeEl.value;
        const qty = parseInt(qtyEl.value) || 0;

        let sell = manualSellOverride ? (parseFloat(sellEl.value) || 0) : calcSell();
        if (!manualSellOverride) sellEl.value = sell.toFixed(2);

        const profit = Math.max(0, sell - cost);

        wsCost.textContent = fmt(cost);
        wsProfit.textContent = fmt(profit);
        wsSell.textContent = fmt(sell);
        wsStockVal.textContent = fmt(sell * qty);
        wsTotProfit.textContent = fmt(profit * qty);
    }

    [costEl, typeEl, profitValEl, qtyEl].forEach(el => el && el.addEventListener('input', update));
    if (sellEl) {
        sellEl.addEventListener('input', function () {
            manualSellOverride = true;
            update();
        });
    }
    update();
})();
</script>

<script src="popup.js"></script>
</body>
</html>