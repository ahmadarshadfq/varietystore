<?php
ini_set('session.cookie_lifetime', 0);
ini_set('session.gc_maxlifetime', 3600);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host   = 'localhost';
$dbname = 'auth_system';
$dbuser = 'root';
$dbpass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $dbuser, $dbpass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("DB Connection failed: " . $e->getMessage());
}

/* ---------- USER HELPERS ---------- */
function currentUser() {
    global $pdo;
    static $cached = null;
    if ($cached !== null) return $cached;
    if (!isset($_SESSION['user_id'])) return null;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([(int)$_SESSION['user_id']]);
    $cached = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    return $cached;
}

function isAdmin() {
    $u = currentUser();
    return $u && $u['role'] === 'admin';
}
function isLoggedIn() { return isset($_SESSION['user_id']); }
function isSwitched() { return !empty($_SESSION['switched_from_admin']); }
function realAdmin() { return isSwitched() ? $_SESSION['switched_from_admin'] : null; }

function requireLogin() {
    if (!isLoggedIn()) { header("Location: login.php"); exit; }
}
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) { header("Location: admin.php?error=no_permission"); exit; }
}

/* ---------- COLOR ---------- */
function isValidHexColor($c) { return is_string($c) && preg_match('/^#[0-9A-Fa-f]{6}$/', $c) === 1; }

function lightenColor($hex, $mix = 0.85) {
    if (!isValidHexColor($hex)) $hex = '#4f46e5';
    $hex = ltrim($hex, '#');
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    $r = (int) round($r * (1 - $mix) + 255 * $mix);
    $g = (int) round($g * (1 - $mix) + 255 * $mix);
    $b = (int) round($b * (1 - $mix) + 255 * $mix);
    return sprintf('#%02x%02x%02x', $r, $g, $b);
}

/* ---------- WHOLESALE ---------- */
function calcSellingPrice($costPrice, $profitType, $profitValue) {
    $cost = (float)$costPrice;
    if ($profitType === 'percent') return round($cost * (1 + ((float)$profitValue / 100)), 2);
    return round($cost + (float)$profitValue, 2);
}
function calcProfit($costPrice, $sellingPrice) {
    return max(0, (float)$sellingPrice - (float)$costPrice);
}
?>