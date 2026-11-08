<?php
require 'config.php';
if (isset($_SESSION['user_id'])) { header("Location: admin.php"); exit; }
$userCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
header("Location: " . ($userCount >= 1 ? "login.php" : "register.php"));
exit;
?>