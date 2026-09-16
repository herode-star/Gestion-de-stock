<?php
require_once __DIR__ . '/core/bootstrap.php';

if (setup_complete()) {
    redirect(current_user() ? 'index.php' : 'login.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $business = post('business_name');
    $name = post('name');
    $email = strtolower(post('email'));
    $password = post('password');
    if ($business === '' || $name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
        $error = 'Ranpli tout chan yo. Modpas la dwe gen omwen 8 karaktè.';
    } else {
        $pdo = db();
        $pdo->query("SELECT GET_LOCK('stock_first_setup', 10)")->fetchColumn() == 1 or exit('Eseye ankò nan kèk segond.');
        try {
        if (setup_complete()) redirect('login.php');
        $pdo->beginTransaction();
        $parts = preg_split('/\s+/', $name, 2);
        // The historical demo database contains a known plaintext administrator.
        // Disable every legacy administrator before creating the real owner account.
        db()->exec("UPDATE users SET personnalite = 'legacy' WHERE personnalite = 'admin'");
        $stmt = db()->prepare("INSERT INTO users(user_name,user_prenom,user_email,user_pass,user_date,user_img,user_sexe,personnalite,societe,adresse,ville,etat,cod_postal,pays,num_tel) VALUES(?,?,?,?,CURDATE(),'default.png','','admin',?,'','','',0,'',0)");
        $stmt->execute([$parts[0], $parts[1] ?? '', $email, password_hash($password, PASSWORD_DEFAULT), $business]);
        $id = (int) db()->lastInsertId();
        save_setting('business_name', $business);
        save_setting('currency', post('currency', 'CAD'));
        save_setting('low_stock_limit', '5');
        save_setting('setup_complete', '1');
        $pdo->commit();
        session_regenerate_id(true);
        $_SESSION['admin_id'] = $id;
        flash('success', 'Byenvini! Aplikasyon an pare pou sèvi.');
        redirect('index.php');
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = 'Konfigirasyon an pa sove. Verifye enfòmasyon yo epi eseye ankò.';
        } finally {
            $pdo->query("SELECT RELEASE_LOCK('stock_first_setup')");
        }
    }
}
?>
<!doctype html><html lang="ht"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Prepare aplikasyon an</title><link rel="stylesheet" href="assets/app.css"></head>
<body class="auth-page"><div class="auth-card"><div class="auth-logo">S</div><h1>Prepare boutik ou</h1><p>Yon sèl etap. Ou pa bezwen konnen anyen nan enfòmatik.</p>
<?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
<form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
<div class="field"><label>Non biznis la</label><input name="business_name" required autofocus placeholder="Egzanp: Ti Boutik Ayiti" value="<?= e(post('business_name')) ?>"></div>
<div class="field"><label>Non konplè administratè a</label><input name="name" required placeholder="Non ak siyati" value="<?= e(post('name')) ?>"></div>
<div class="field"><label>Imèl pou konekte</label><input type="email" name="email" required autocomplete="username" placeholder="ou@biznis.com" value="<?= e(post('email')) ?>"></div>
<div class="field"><label>Kreye yon modpas</label><input type="password" name="password" required minlength="8" autocomplete="new-password"><small>Omwen 8 karaktè.</small></div>
<div class="field"><label>Lajan ou itilize</label><select name="currency"><option value="CAD">CAD — Dola Kanadyen</option><option value="HTG">HTG — Goud</option><option value="USD">USD — Dola Ameriken</option><option value="EUR">EUR — Ewo</option></select></div>
<button class="primary" type="submit">Kòmanse itilize aplikasyon an</button></form></div></body></html>
