<?php
require 'config.php';
requireLogin();

header('Content-Type: application/json');

$batchId = (int)($_GET['id'] ?? 0);
if ($batchId <= 0) {
    echo json_encode(['error' => 'Invalid batch']);
    exit;
}

$me = currentUser();

$stmt = $pdo->prepare("
    SELECT b.*, u.username AS seller_name, u.color AS seller_color
    FROM sales_batches b
    LEFT JOIN users u ON u.id = b.user_id
    WHERE b.id = ?
");
$stmt->execute([$batchId]);
$batch = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$batch) {
    echo json_encode(['error' => 'Sale not found']);
    exit;
}

$itemsStmt = $pdo->prepare("
    SELECT si.*, p.cost_price AS current_cost
    FROM sales_items si
    LEFT JOIN products p ON p.id = si.product_id
    WHERE si.batch_id = ?
    ORDER BY si.id ASC
");
$itemsStmt->execute([$batchId]);
$items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

$isAdminView = ($me['role'] === 'admin' && !isSwitched());

echo json_encode([
    'id'             => (int)$batch['id'],
    'seller_name'    => $batch['seller_name'] ?? 'Unknown',
    'seller_color'   => $batch['seller_color'] ?? '#4f46e5',
    'batch_date'     => date('d M Y', strtotime($batch['batch_date'])),
    'created_at'     => date('d M Y, h:i A', strtotime($batch['created_at'])),
    'total_quantity' => (int)$batch['total_quantity'],
    'total_amount'   => (float)$batch['total_amount'],
    'total_profit'   => (float)$batch['total_profit'],
    'total_loss'     => (float)$batch['total_loss'],
    'items'          => array_map(function($it) use ($isAdminView) {
        return [
            'product_name' => $it['product_name'],
            'quantity'     => (int)$it['quantity'],
            'cost_price'   => $isAdminView ? (float)$it['cost_price'] : null,
            'sale_price'   => (float)$it['sale_price'],
            'total'        => (float)$it['total'],
            'profit_loss'  => $isAdminView ? (float)$it['profit_loss'] : null,
        ];
    }, $items),
    'is_admin_view' => $isAdminView,
]);
?>