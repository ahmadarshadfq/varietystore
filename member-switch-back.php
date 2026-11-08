<?php
require 'config.php';

if (!isSwitched()) {
    header("Location: login.php");
    exit;
}

$admin = $_SESSION['switched_from_admin'];

// Verify admin still exists and is admin
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'admin'");
$stmt->execute([(int)$admin['user_id']]);
$adminRow = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$adminRow) {
    // Admin gone — force logout
    $_SESSION = [];
    session_destroy();
    header("Location: login.php");
    exit;
}

// Restore admin session
$_SESSION['user_id']  = $adminRow['id'];
$_SESSION['username'] = $adminRow['username'];
$_SESSION['email']    = $adminRow['email'];
$_SESSION['role']     = $adminRow['role'];
unset($_SESSION['switched_from_admin']);

header("Location: admin.php?success=back_to_admin");
exit;
?>