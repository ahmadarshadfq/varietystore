<?php
$activePage = $activePage ?? '';
$me = currentUser();
$currentUsername = $me['username'] ?? 'User';
$currentEmail    = $me['email'] ?? '';
$currentRole     = $me['role'] ?? 'member';
$currentColor    = $me['color'] ?? '#10b981';
$switched        = isSwitched();
$adminInfo       = $switched ? realAdmin() : null;
$isRealAdmin     = !$switched && $currentRole === 'admin';

$membersJson = '[]';
if ($isRealAdmin) {
    $all = $pdo->query("SELECT id, username, email, role, color, created_at FROM users ORDER BY role DESC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
    $membersJson = json_encode($all, JSON_HEX_APOS | JSON_HEX_QUOT);
}

$userData = json_encode([
    'id'             => (int)($me['id'] ?? 0),
    'username'       => $currentUsername,
    'email'          => $currentEmail,
    'role'           => $currentRole,
    'color'          => $currentColor,
    'switched'       => $switched,
    'admin_username' => $adminInfo['username'] ?? null,
], JSON_HEX_APOS | JSON_HEX_QUOT);
?>

<!-- Mobile hamburger (only visible on mobile) -->
<button type="button" class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Open menu">
    <i class="fa-solid fa-bars"></i>
</button>

<!-- Overlay (mobile only, when menu open) -->
<div class="mobile-menu-overlay" id="mobileMenuOverlay"></div>

<aside class="sidebar" id="appSidebar">

    <!-- Mobile close button (only visible on mobile) -->
    <button type="button" class="mobile-menu-close" id="mobileMenuClose" aria-label="Close menu">
        <i class="fa-solid fa-xmark"></i>
    </button>

    <?php if ($switched): ?>
    <a href="member-switch-back.php" class="switch-back-banner"
       data-confirm="Admin panel wapas jana hai?"
       data-confirm-title="Back to Admin?"
       data-confirm-btn="Yes, Switch Back"
       data-cancel-btn="Stay"
       data-type="info"
       data-icon='<i class="fa-solid fa-user-shield"></i>'>
        <i class="fa-solid fa-arrow-left"></i>
        <div style="flex:1;min-width:0;text-align:left;">
            <div style="font-size:13px;font-weight:600;">Back to Admin</div>
            <div style="font-size:11px;opacity:0.8;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                <?= htmlspecialchars($adminInfo['username'] ?? 'Admin') ?>
            </div>
        </div>
    </a>
    <?php endif; ?>

    <div class="sidebar-brand">
        <i class="fa-solid fa-cube"></i>
        <span>Inventory</span>
    </div>

    <ul class="nav">
        <li><a href="admin.php" class="<?= $activePage === 'dashboard' ? 'active' : '' ?>">
            <i class="fa-solid fa-house"></i> Dashboard
        </a></li>
        <li><a href="products.php" class="<?= $activePage === 'products' ? 'active' : '' ?>">
            <i class="fa-solid fa-box"></i> Products
        </a></li>

        <?php if ($isRealAdmin): ?>
        <li><a href="wholesale.php" class="<?= $activePage === 'wholesale' ? 'active' : '' ?>">
            <i class="fa-solid fa-warehouse"></i> Wholesale
        </a></li>
        <?php endif; ?>

        <li><a href="sales.php" class="<?= $activePage === 'sales' ? 'active' : '' ?>">
            <i class="fa-solid fa-receipt"></i> Sales
        </a></li>
        <li>
            <a href="logout.php" class="logout-link"
               data-confirm="You will be signed out of your account."
               data-confirm-title="Sign out?"
               data-confirm-btn="Yes, Logout"
               data-cancel-btn="Stay"
               data-danger="true"
               data-type="danger"
               data-icon='<i class="fa-solid fa-right-from-bracket"></i>'>
                <i class="fa-solid fa-right-from-bracket"></i> Logout
            </a>
        </li>
    </ul>

    <button type="button" class="sidebar-footer sidebar-profile-btn"
            data-popup-form="profiles"
            data-user='<?= htmlspecialchars($userData, ENT_QUOTES, "UTF-8") ?>'
            data-members='<?= htmlspecialchars($membersJson, ENT_QUOTES, "UTF-8") ?>'>
        <div class="avatar" style="background:<?= htmlspecialchars($currentColor) ?>22;color:<?= htmlspecialchars($currentColor) ?>;border:2px solid <?= htmlspecialchars($currentColor) ?>;">
            <i class="fa-solid fa-user"></i>
        </div>
        <div style="text-align:left;flex:1;min-width:0;">
            <div class="sf-name"><?= htmlspecialchars($currentUsername) ?></div>
            <div class="sf-role"><?= $switched ? 'Switched View' : ($currentRole === 'admin' ? 'Administrator' : 'Team Member') ?></div>
        </div>
        <i class="fa-solid fa-chevron-right" style="font-size:11px;color:var(--text-light);"></i>
    </button>
</aside>