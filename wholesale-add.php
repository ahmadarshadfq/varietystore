<?php
require 'config.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: wholesale.php");
    exit;
}

$editId     = (int)($_POST['edit_id'] ?? 0);
$name       = trim($_POST['name'] ?? '');
$quantity   = $_POST['quantity'] ?? '';
$costPrice  = $_POST['cost_price'] ?? '';
$profitType = $_POST['profit_type'] ?? 'percent';
$profitVal  = $_POST['profit_value'] ?? '';
$selling    = $_POST['price'] ?? '';

$me = currentUser();
$error = '';

if ($name === '' || $quantity === '' || $costPrice === '' || $profitVal === '' || $selling === '') {
    $error = "Saare fields bharein.";
} elseif (!is_numeric($quantity) || (int)$quantity < 0) {
    $error = "Quantity valid number honi chahiye.";
} elseif (!is_numeric($costPrice) || (float)$costPrice < 0) {
    $error = "Purchase price valid honi chahiye.";
} elseif (!is_numeric($profitVal) || (float)$profitVal < 0) {
    $error = "Profit value valid honi chahiye.";
} elseif (!is_numeric($selling) || (float)$selling < 0) {
    $error = "Selling price valid honi chahiye.";
} elseif ((float)$selling < (float)$costPrice) {
    $error = "Selling price purchase price se kam nahi ho sakti (loss ho jayega).";
}

if ($error) {
    header("Location: wholesale.php?error=" . urlencode($error) . ($editId ? "&edit=$editId" : ""));
    exit;
}

$profitPerItem = (float)$selling - (float)$costPrice;

try {
    if ($editId > 0) {
        // Fetch old product to know previous quantity
        $old = $pdo->prepare("SELECT quantity, cost_price FROM products WHERE id = ?");
        $old->execute([$editId]);
        $oldProduct = $old->fetch(PDO::FETCH_ASSOC);

        $upd = $pdo->prepare("UPDATE products SET name = ?, quantity = ?, cost_price = ?, price = ? WHERE id = ?");
        $upd->execute([$name, (int)$quantity, (float)$costPrice, (float)$selling, $editId]);

        // Record a purchase entry if quantity increased or cost changed
        $oldQty = (int)($oldProduct['quantity'] ?? 0);
        $oldCost = (float)($oldProduct['cost_price'] ?? 0);
        $newQty = (int)$quantity;
        $newCost = (float)$costPrice;

        $qtyAdded = $newQty - $oldQty;
        $costChanged = abs($oldCost - $newCost) > 0.001;

        if ($qtyAdded > 0 || $costChanged) {
            $ins = $pdo->prepare("INSERT INTO purchases (product_id, quantity, cost_price, selling_price, profit_per_item, user_id) VALUES (?, ?, ?, ?, ?, ?)");
            // If only cost changed (no qty added), log with qty=0 for the record
            $ins->execute([
                $editId,
                max(0, $qtyAdded),
                $newCost,
                (float)$selling,
                $profitPerItem,
                $me['id'] ?? null
            ]);
        }

        header("Location: wholesale.php?success=updated");
    } else {
        // Insert new product
        $ins = $pdo->prepare("INSERT INTO products (name, quantity, price, cost_price) VALUES (?, ?, ?, ?)");
        $ins->execute([$name, (int)$quantity, (float)$selling, (float)$costPrice]);
        $newProductId = (int)$pdo->lastInsertId();

        // Record baseline purchase entry
        $pur = $pdo->prepare("INSERT INTO purchases (product_id, quantity, cost_price, selling_price, profit_per_item, user_id) VALUES (?, ?, ?, ?, ?, ?)");
        $pur->execute([
            $newProductId,
            (int)$quantity,
            (float)$costPrice,
            (float)$selling,
            $profitPerItem,
            $me['id'] ?? null
        ]);

        header("Location: wholesale.php?success=added");
    }
    exit;
} catch (Exception $e) {
    header("Location: wholesale.php?error=" . urlencode($e->getMessage()));
    exit;
}
?>