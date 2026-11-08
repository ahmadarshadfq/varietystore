<?php
require 'config.php';

if (!isAdmin()) {
    header("Location: admin.php?error=no_permission");
    exit;
}

$userId = (int)$_SESSION['user_id'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';
    $color    = trim($_POST['color'] ?? '#4f46e5');

    if (!isValidHexColor($color)) $color = '#4f46e5';

    if ($username === '' || $email === '') {
        $errors[] = "Username aur email zaroori hain.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Valid email dalein.";
    }

    if (empty($errors)) {
        $chk = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND id != ?");
        $chk->execute([$email, $userId]);
        if ($chk->fetchColumn() > 0) $errors[] = "Ye email kisi aur user ke paas hai.";
    }

    if (empty($errors)) {
        $chk = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND id != ?");
        $chk->execute([$username, $userId]);
        if ($chk->fetchColumn() > 0) $errors[] = "Ye username already taken hai.";
    }

    if ($password !== '') {
        if (strlen($password) < 6) $errors[] = "New password kam se kam 6 characters ka ho.";
        elseif ($password !== $confirm) $errors[] = "Passwords match nahi karte.";
    }

    if (empty($errors)) {
        if ($password !== '') {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $upd = $pdo->prepare("UPDATE users SET username = ?, email = ?, password = ?, color = ? WHERE id = ?");
            $upd->execute([$username, $email, $hash, $color, $userId]);
        } else {
            $upd = $pdo->prepare("UPDATE users SET username = ?, email = ?, color = ? WHERE id = ?");
            $upd->execute([$username, $email, $color, $userId]);
        }
        $_SESSION['username'] = $username;
        $_SESSION['email']    = $email;
        header("Location: admin.php?success=profile");
        exit;
    }
}

if ($errors) {
    header("Location: admin.php?profile_error=" . urlencode(implode(' ', $errors)));
    exit;
}

header("Location: admin.php");
exit;
?>