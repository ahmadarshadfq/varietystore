<?php
require 'config.php';
requireAdmin();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id       = (int)($_POST['id'] ?? 0);
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $color    = trim($_POST['color'] ?? '#16a34a');

    if (!isValidHexColor($color)) $color = '#16a34a';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $error = "User nahi mila.";
    } elseif ($user['role'] === 'admin') {
        $error = "Admin ko yahan se edit nahi kar sakte.";
    } elseif ($username === '' || $email === '') {
        $error = "Username aur email zaroori hain.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Valid email dalein.";
    } else {
        $chk = $pdo->prepare("SELECT COUNT(*) FROM users WHERE (email = ? OR username = ?) AND id != ?");
        $chk->execute([$email, $username, $id]);
        if ($chk->fetchColumn() > 0) {
            $error = "Ye email ya username already use ho raha hai.";
        } elseif ($password !== '' && strlen($password) < 6) {
            $error = "Password kam se kam 6 characters ka ho.";
        } else {
            if ($password !== '') {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $upd = $pdo->prepare("UPDATE users SET username = ?, email = ?, password = ?, color = ? WHERE id = ?");
                $upd->execute([$username, $email, $hash, $color, $id]);
            } else {
                $upd = $pdo->prepare("UPDATE users SET username = ?, email = ?, color = ? WHERE id = ?");
                $upd->execute([$username, $email, $color, $id]);
            }
            header("Location: admin.php?success=member_updated");
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