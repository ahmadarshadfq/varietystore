<?php
require 'config.php';
requireAdmin();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $color    = trim($_POST['color'] ?? '#16a34a');

    if (!isValidHexColor($color)) $color = '#16a34a';

    if ($username === '' || $email === '' || $password === '') {
        $error = "Saare fields bharein.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Valid email dalein.";
    } elseif (strlen($password) < 6) {
        $error = "Password kam se kam 6 characters ka ho.";
    } else {
        $chk = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? OR username = ?");
        $chk->execute([$email, $username]);
        if ($chk->fetchColumn() > 0) {
            $error = "Ye email ya username already use ho raha hai.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role, color) VALUES (?, ?, ?, 'member', ?)");
            $stmt->execute([$username, $email, $hash, $color]);
            header("Location: admin.php?success=member_added");
            exit;
        }
    }
}

if ($error) {
    header("Location: admin.php?member_error=" . urlencode($error));
    exit;
}

header("Location: admin.php");
exit;
?>