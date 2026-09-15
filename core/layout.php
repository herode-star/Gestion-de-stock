<?php
require_once __DIR__ . '/bootstrap.php';

function page_header(string $title, string $active = ''): void
{
    $user = current_user();
    $business = setting('business_name', 'Boutik mwen');
    $flash = take_flash();
    $links = [
        'dashboard' => ['index.php', 'Akèy', '⌂'],
        'products' => ['products.php', 'Pwodwi', '□'],
        'sales' => ['sales.php', 'Lavant', '$'],
        'analytics' => ['analytics.php', 'AI & Estatistik', '✦'],
        'activity' => ['activity.php', 'Aktivite', '◎'],
        'suppliers' => ['suppliers.php', 'Founisè', '♧'],
        'users' => ['users.php', 'Itilizatè', '♙'],
        'settings' => ['settings.php', 'Paramèt', '⚙'],
    ];
    ?>
<!doctype html>
<html lang="ht">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#166534">
    <title><?= e($title) ?> · <?= e($business) ?></title>
    <link rel="stylesheet" href="assets/app.css">
    <link rel="stylesheet" href="assets/analytics.css">
    <link rel="stylesheet" href="assets/uploads.css">
</head>
<body>
<div class="app-shell">
    <aside class="sidebar" id="sidebar">
        <a class="brand" href="index.php"><span class="brand-mark">S</span><span><?= e($business) ?></span></a>
        <nav>
            <?php foreach ($links as $key => $link): ?>
                <a href="<?= e($link[0]) ?>" class="<?= $active === $key ? 'active' : '' ?>"><span><?= $link[2] ?></span><?= e($link[1]) ?></a>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-foot">
            <small>Konekte kòm</small>
            <strong><?= e(($user['user_name'] ?? '') . ' ' . ($user['user_prenom'] ?? '')) ?></strong>
            <a href="logout.php">Dekonekte</a>
        </div>
    </aside>
    <main class="main">
        <header class="topbar">
            <button class="menu-button" type="button" aria-label="Ouvri meni" onclick="document.body.classList.toggle('menu-open')">☰</button>
            <div><h1><?= e($title) ?></h1><p><?= e(date('l, d M Y')) ?></p></div>
            <a class="button primary quick-sale" href="sales.php?new=1">+ Nouvo vant</a>
        </header>
        <section class="content">
            <?php if ($flash): ?><div class="alert <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endif; ?>
    <?php
}

function page_footer(): void
{
    ?>
        </section>
        <footer>Gestion de Stock · fèt pou itilize fasil sou telefòn ak òdinatè</footer>
    </main>
</div>
<script src="assets/app.js"></script>
</body>
</html>
    <?php
}
