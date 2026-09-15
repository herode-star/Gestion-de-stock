<?php
require_once __DIR__ . '/core/bootstrap.php';
if (!setup_complete()) redirect('setup.php');
if (current_user()) redirect('index.php');
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $stmt = db()->prepare("SELECT * FROM users WHERE user_email = ? AND personnalite = 'admin' LIMIT 1");
    $stmt->execute([strtolower(post('email'))]);
    $user = $stmt->fetch();
    $valid = false;
    if ($user) {
        $hash = (string) $user['user_pass'];
        $valid = password_verify(post('password'), $hash);
    }
    if ($valid) {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int) $user['user_id'];
        log_activity('login', 'session', null, 'Koneksyon reyisi');
        redirect('index.php');
    }
    $error = 'Imèl oswa modpas la pa kòrèk.';
}
?>
<!doctype html><html lang="ht"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Konekte</title><link rel="stylesheet" href="assets/app.css"></head>
<body class="auth-page"><div class="auth-card"><div class="auth-logo">S</div><h1><?= e(setting('business_name', 'Gestion de Stock')) ?></h1><p>Konekte pou jere biznis ou.</p>
<?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
<form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><div class="field"><label>Imèl</label><input type="email" name="email" required autofocus autocomplete="username"></div><div class="field"><label>Modpas</label><input type="password" name="password" required autocomplete="current-password"></div><button class="primary" type="submit">Konekte</button></form>
<div class="help-box" style="margin-top:20px">Si ou bliye modpas la, mande administratè prensipal la kreye yon lòt kont pou ou.</div></div></body></html>
