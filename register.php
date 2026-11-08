<?php
require 'config.php';

if (isset($_SESSION['user_id'])) { header("Location: admin.php"); exit; }

$error = '';
$success = '';
$userCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($userCount >= 1) {
        $error = "Registration band hai! Sirf pehla admin allow hai.";
    } else {
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '' || $email === '' || $password === '') {
            $error = "Saare fields bharein.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Valid email dalein.";
        } elseif (strlen($password) < 6) {
            $error = "Password kam se kam 6 characters ka ho.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            try {
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'admin')");
                $stmt->execute([$username, $email, $hash]);
                $success = "Admin account ban gaya! Ab login karein.";
            } catch (PDOException $e) {
                $error = "Error: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Register • Inventory</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="style.css">
</head>
<body class="auth-body">

<div class="auth-card">
    <div class="brand">
        <i class="fa-solid fa-cube"></i>
        <h1>Inventory</h1>
    </div>
    <p class="subtitle">Create your admin account (one-time)</p>

    <?php if ($userCount >= 1 && !$success): ?>
        <div class="alert alert-info">
            <i class="fa-solid fa-circle-info"></i>
            <span>Registration band hai. Admin se login karein.</span>
        </div>
        <a href="login.php" class="btn-primary">
            <i class="fa-solid fa-right-to-bracket"></i> Go to Login
        </a>
    <?php else: ?>
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fa-solid fa-circle-check"></i>
                <span><?= htmlspecialchars($success) ?></span>
            </div>
            <a href="login.php" class="btn-primary">
                <i class="fa-solid fa-right-to-bracket"></i> Login Now
            </a>
        <?php else: ?>
        <form method="POST" autocomplete="off">
            <div class="input-group">
                <i class="fa-solid fa-user input-icon"></i>
                <input type="text" name="username" placeholder="Username" required>
            </div>
            <div class="input-group">
                <i class="fa-solid fa-envelope input-icon"></i>
                <input type="email" name="email" placeholder="Email address" required>
            </div>
            <div class="input-group">
                <i class="fa-solid fa-lock input-icon"></i>
                <input type="password" name="password" id="password" placeholder="Password (min 6 chars)" required minlength="6">
                <button type="button" class="eye-btn" onclick="togglePass('password', this)" tabindex="-1">
                    <i class="fa-solid fa-eye"></i>
                </button>
            </div>
            <button type="submit" class="btn-primary">
                <i class="fa-solid fa-user-plus"></i> Create Admin Account
            </button>
        </form>
        <p class="switch-link">Already registered? <a href="login.php">Sign in <i class="fa-solid fa-arrow-right"></i></a></p>
        <?php endif; ?>
    <?php endif; ?>
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