<?php
require 'config.php';

if (!isLoggedIn() || !isAdmin()) {
    header("Location: login.php");
    exit;
}

$memberId = (int)($_GET['id'] ?? 0);
if ($memberId <= 0) {
    header("Location: admin.php?error=no_permission");
    exit;
}

// Fetch target member
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$memberId]);
$target = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$target) {
    header("Location: admin.php?member_error=" . urlencode("Member nahi mila."));
    exit;
}

if ($target['role'] === 'admin') {
    header("Location: admin.php?member_error=" . urlencode("Admin account mein switch nahi kar sakte."));
    exit;
}

if ((int)$target['id'] === (int)$_SESSION['user_id']) {
    header("Location: admin.php?member_error=" . urlencode("Aap already is account mein hain."));
    exit;
}

// Save admin identity (only if not already switched)
$admin = currentUser();
$_SESSION['switched_from_admin'] = [
    'user_id'  => $admin['id'],
    'username' => $admin['username'],
    'email'    => $admin['email'],
    'role'     => $admin['role'],
];

// Switch to member
$_SESSION['user_id']  = $target['id'];
$_SESSION['username'] = $target['username'];
$_SESSION['email']    = $target['email'];
$_SESSION['role']     = $target['role'];

header("Location: admin.php?success=switched&as=" . urlencode($target['username']));
exit;
?>