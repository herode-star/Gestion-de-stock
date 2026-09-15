<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'America/Toronto');

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = getenv('DB_HOST') ?: 'localhost';
    $name = getenv('DB_NAME') ?: 'Otechnologie';
    $user = getenv('DB_USER') ?: 'root';
    $password = getenv('DB_PASSWORD') ?: '';

    try {
        $pdo = new PDO(
            "mysql:host={$host};dbname={$name};charset=utf8mb4",
            $user,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    } catch (PDOException $exception) {
        http_response_code(503);
        exit('Bazdone a poko pare. Tann kèk segond epi rafrechi paj la.');
    }

    migrate($pdo);
    return $pdo;
}

function migrate(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS app_settings (
        setting_key VARCHAR(80) PRIMARY KEY,
        setting_value TEXT NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS sales (
        sale_id INT AUTO_INCREMENT PRIMARY KEY,
        customer_name VARCHAR(120) NOT NULL DEFAULT 'Kliyan comptoir',
        payment_method VARCHAR(40) NOT NULL DEFAULT 'Lajan kach',
        total DECIMAL(12,2) NOT NULL DEFAULT 0,
        created_by INT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX(created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS sale_items (
        item_id INT AUTO_INCREMENT PRIMARY KEY,
        sale_id INT NOT NULL,
        product_id INT NOT NULL,
        quantity INT NOT NULL,
        unit_price DECIMAL(12,2) NOT NULL,
        FOREIGN KEY (sale_id) REFERENCES sales(sale_id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES produit(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS stock_movements (
        movement_id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        movement_type ENUM('in','out','adjustment') NOT NULL,
        quantity INT NOT NULL,
        note VARCHAR(255) NOT NULL DEFAULT '',
        created_by INT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX(product_id), INDEX(created_at),
        FOREIGN KEY (product_id) REFERENCES produit(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $version = $pdo->query("SELECT setting_value FROM app_settings WHERE setting_key = 'schema_version'")->fetchColumn();
    if ((int) $version < 1) {
        $alterations = [
            'ALTER TABLE users MODIFY user_pass VARCHAR(255) NOT NULL',
            'ALTER TABLE users MODIFY user_email VARCHAR(120) NOT NULL',
            'ALTER TABLE users MODIFY num_tel VARCHAR(30) NOT NULL',
            'ALTER TABLE fournisseur MODIFY num_tel VARCHAR(30) NULL',
            'ALTER TABLE produit MODIFY model VARCHAR(120) NOT NULL, MODIFY marque VARCHAR(120) NOT NULL, MODIFY categorie VARCHAR(80) NOT NULL, MODIFY referance VARCHAR(80) NOT NULL',
        ];
        foreach ($alterations as $sql) {
            try {
                $pdo->exec($sql);
            } catch (PDOException $ignored) {
            }
        }
        $stmt = $pdo->prepare("INSERT INTO app_settings(setting_key, setting_value) VALUES('schema_version', '1') ON DUPLICATE KEY UPDATE setting_value='1'");
        $stmt->execute();
    }
    $done = true;
}

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function setting(string $key, string $default = ''): string
{
    $stmt = db()->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ?');
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();
    return $value === false ? $default : (string) $value;
}

function save_setting(string $key, string $value): void
{
    $stmt = db()->prepare('INSERT INTO app_settings(setting_key, setting_value) VALUES(?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
    $stmt->execute([$key, $value]);
}

function setup_complete(): bool
{
    return setting('setup_complete') === '1';
}

function current_user(): ?array
{
    if (empty($_SESSION['admin_id'])) {
        return null;
    }
    $stmt = db()->prepare('SELECT user_id, user_name, user_prenom, user_email, personnalite FROM users WHERE user_id = ?');
    $stmt->execute([(int) $_SESSION['admin_id']]);
    return $stmt->fetch() ?: null;
}

function require_auth(): void
{
    if (!setup_complete()) {
        redirect('setup.php');
    }
    if (!current_user()) {
        redirect('login.php');
    }
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function verify_csrf(): void
{
    if (!isset($_POST['csrf']) || !hash_equals(csrf_token(), (string) $_POST['csrf'])) {
        http_response_code(419);
        exit('Sesyon an ekspire. Retounen sou paj anvan an epi eseye ankò.');
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function take_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

function money($amount): string
{
    return number_format((float) $amount, 2, '.', ',') . ' ' . setting('currency', 'CAD');
}

function post(string $key, string $default = ''): string
{
    return trim((string) ($_POST[$key] ?? $default));
}
