<?php
require 'config.php';
requireAdmin();

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) { header("Location: admin.php"); exit; }

// Cannot delete self
if ($id === (int)$_SESSION['user_id']) {
    header("Location: admin.php?member_error=" . urlencode("Aap khud ko delete nahi kar sakte."));
    exit;
}

// Cannot delete admin
$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$id]);
$role = $stmt->fetchColumn();
if ($role === 'admin') {
    header("Location: admin.php?member_error=" . urlencode("Admin ko delete nahi kar sakte."));
    exit;
}

$del = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'member'");
$del->execute([$id]);

header("Location: admin.php?success=member_deleted");
exit;
?>