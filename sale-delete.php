<?php
require 'config.php';
requireAdmin();

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    header("Location: sales.php?error=invalid");
    exit;
}

// Fetch batch + items
$stmt = $pdo->prepare("SELECT * FROM sales_batches WHERE id = ?");
$stmt->execute([$id]);
$batch = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$batch) {
    header("Location: sales.php?error=not_found");
    exit;
}

try {
    $pdo->beginTransaction();

    // Restore stock from sales_items
    $itemsStmt = $pdo->prepare("SELECT product_id, quantity FROM sales_items WHERE batch_id = ?");
    $itemsStmt->execute([$id]);
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

    $restoreStock = $pdo->prepare("UPDATE products SET quantity = quantity + ? WHERE id = ?");
    foreach ($items as $it) {
        if (!empty($it['product_id'])) {
            $restoreStock->execute([(int)$it['quantity'], (int)$it['product_id']]);
        }
    }

    // Delete batch (sales_items auto-delete via FK CASCADE)
    $del = $pdo->prepare("DELETE FROM sales_batches WHERE id = ?");
    $del->execute([$id]);

    $pdo->commit();

    // Redirect back to where the delete came from
    $redirect = $_GET['from'] ?? 'sales';
    if ($redirect === 'admin') {
        header("Location: admin.php?success=sale_deleted");
    } else {
        header("Location: sales.php?success=deleted");
    }
    exit;
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    header("Location: sales.php?error=" . urlencode($e->getMessage()));
    exit;
}
?>