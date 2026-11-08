<?php
require 'config.php';

if (isset($_SESSION['user_id'])) { header("Location: admin.php"); exit; }

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = "Email aur password dalein.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']  = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email']    = $user['email'];
            $_SESSION['role']     = $user['role'];
            header("Location: admin.php");
            exit;
        } else {
            $error = "Galat email ya password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login • Inventory</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="style.css">
</head>
<body class="auth-body">

<div class="auth-card">
    <div class="brand">
        <i class="fa-solid fa-cube"></i>
        <h1>Inventory</h1>
    </div>
    <p class="subtitle">Welcome back, sign in to continue</p>

    <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fa-solid fa-circle-exclamation"></i>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
        <div class="input-group">
            <i class="fa-solid fa-envelope input-icon"></i>
            <input type="email" name="email" placeholder="Email address" required>
        </div>
        <div class="input-group">
            <i class="fa-solid fa-lock input-icon"></i>
            <input type="password" name="password" id="password" placeholder="Password" required>
            <button type="button" class="eye-btn" onclick="togglePass('password', this)" tabindex="-1">
                <i class="fa-solid fa-eye"></i>
            </button>
        </div>
        <button type="submit" class="btn-primary">
            <i class="fa-solid fa-right-to-bracket"></i> Sign In
        </button>
    </form>

    <p class="switch-link">Naya user? <a href="register.php">Register <i class="fa-solid fa-arrow-right"></i></a></p>
</div>

<script>
function togglePass(id, btn) {
    const inp = document.getElementById(id);
    const icon = btn.querySelector('i');
    if (inp.type === 'password') { inp.type = 'text'; icon.classList.remove('fa-eye'); icon.classList.add('fa-eye-slash'); }
    else { inp.type = 'password'; icon.classList.remove('fa-eye-slash'); icon.classList.add('fa-eye'); }
}
</script>
</body>
</html>