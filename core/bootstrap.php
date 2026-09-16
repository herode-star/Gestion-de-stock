<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
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
    $pdo->exec("CREATE TABLE IF NOT EXISTS audit_log (
        audit_id BIGINT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        action VARCHAR(80) NOT NULL,
        entity_type VARCHAR(50) NOT NULL,
        entity_id VARCHAR(80) NULL,
        details VARCHAR(500) NOT NULL DEFAULT '',
        ip_address VARCHAR(45) NOT NULL DEFAULT '',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX(created_at), INDEX(user_id), INDEX(entity_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $version = $pdo->query("SELECT setting_value FROM app_settings WHERE setting_key = 'schema_version'")->fetchColumn();
    if ((int) $version < 1) {
        $alterations = [
            'ALTER TABLE users MODIFY user_pass VARCHAR(255) NOT NULL',
            'ALTER TABLE users MODIFY user_email VARCHAR(120) NOT NULL',
            'ALTER TABLE users MODIFY num_tel VARCHAR(30) NOT NULL',
            'ALTER TABLE fournisseur MODIFY num_tel VARCHAR(30) NULL',
            'ALTER TABLE produit MODIFY model VARCHAR(120) NOT NULL, MODIFY marque VARCHAR(120) NOT NULL, MODIFY categorie VARCHAR(80) NOT NULL',
        ];
        foreach ($alterations as $sql) {
            try {
                $pdo->exec($sql);
            } catch (PDOException $error) {
                throw $error;
            }
        }
        $stmt = $pdo->prepare("INSERT INTO app_settings(setting_key, setting_value) VALUES('schema_version', '1') ON DUPLICATE KEY UPDATE setting_value='1'");
        $stmt->execute();
    }
    if ((int) $version < 2) {
        foreach ([
            'ALTER TABLE produit ADD COLUMN cost_price DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER prix',
            'ALTER TABLE sale_items ADD COLUMN cost_price DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER unit_price',
        ] as $sql) {
            try { $pdo->exec($sql); } catch (PDOException $error) {
                if ((int)($error->errorInfo[1] ?? 0) !== 1060) throw $error;
            }
        }
        $pdo->exec("INSERT INTO app_settings(setting_key,setting_value) VALUES('schema_version','2') ON DUPLICATE KEY UPDATE setting_value='2'");
    }
    if ((int) $version < 3) {
        $pdo->exec("ALTER TABLE produit MODIFY prix DECIMAL(12,2) NOT NULL, MODIFY model VARCHAR(120) NOT NULL, MODIFY marque VARCHAR(120) NOT NULL, MODIFY categorie VARCHAR(80) NOT NULL");
        // Replace the historical cascading delete in one atomic ALTER.
        $constraintExists = $pdo->query("SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='produit' AND CONSTRAINT_NAME='product_supplier_restrict'")->fetchColumn();
        if (!$constraintExists) {
            $pdo->exec("ALTER TABLE produit DROP FOREIGN KEY produit_ibfk_1, ADD CONSTRAINT product_supplier_restrict FOREIGN KEY (four_id) REFERENCES fournisseur(fourn_id) ON DELETE RESTRICT ON UPDATE CASCADE");
        }
        $pdo->exec("INSERT INTO app_settings(setting_key,setting_value) VALUES('schema_version','3') ON DUPLICATE KEY UPDATE setting_value='3'");
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
    $stmt = db()->prepare('SELECT user_id, user_name, user_prenom, user_email, personnalite FROM users WHERE user_id = ? AND personnalite = "admin"');
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
        http_response_code(403);
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

function log_activity(string $action, string $entityType, $entityId = null, string $details = ''): void
{
    try {
        $userId = isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : null;
        $ip = substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
        $stmt = db()->prepare('INSERT INTO audit_log(user_id,action,entity_type,entity_id,details,ip_address) VALUES(?,?,?,?,?,?)');
        $stmt->execute([$userId, $action, $entityType, $entityId === null ? null : (string) $entityId, substr($details, 0, 500), $ip]);
    } catch (Throwable $ignored) {
        // A logging problem must never block a sale or stock operation.
    }
}

function percent_change(float $current, float $previous): ?float
{
    if ($previous == 0.0) return $current == 0.0 ? 0.0 : null;
    return (($current - $previous) / $previous) * 100;
}

function business_snapshot(int $days = 30): array
{
    $days = max(7, min(365, $days));
    $pdo = db();
    $summary = $pdo->query("SELECT COUNT(*) sales_count, COALESCE(SUM(total),0) revenue, COALESCE(AVG(total),0) average_sale FROM sales WHERE created_at >= DATE_SUB(NOW(), INTERVAL {$days} DAY)")->fetch();
    $summary['gross_profit'] = (float)$pdo->query("SELECT COALESCE(SUM(i.quantity*(i.unit_price-i.cost_price)),0) FROM sale_items i JOIN sales s ON s.sale_id=i.sale_id WHERE s.created_at >= DATE_SUB(NOW(), INTERVAL {$days} DAY)")->fetchColumn();
    $inventory = $pdo->query('SELECT COUNT(*) product_count, COALESCE(SUM(quantite),0) units, COALESCE(SUM(quantite*prix),0) retail_value, COALESCE(SUM(quantite*cost_price),0) cost_value, SUM(quantite=0) out_of_stock FROM produit')->fetch();
    $top = $pdo->query("SELECT p.id,p.model,p.marque,p.referance,p.quantite,p.prix,COALESCE(SUM(CASE WHEN s.created_at >= DATE_SUB(NOW(), INTERVAL {$days} DAY) THEN i.quantity ELSE 0 END),0) sold_units,COALESCE(SUM(CASE WHEN s.created_at >= DATE_SUB(NOW(), INTERVAL {$days} DAY) THEN i.quantity*i.unit_price ELSE 0 END),0) revenue FROM produit p LEFT JOIN sale_items i ON i.product_id=p.id LEFT JOIN sales s ON s.sale_id=i.sale_id GROUP BY p.id ORDER BY sold_units DESC,revenue DESC LIMIT 10")->fetchAll();
    $low = $pdo->query('SELECT id,model,marque,referance,quantite,prix FROM produit WHERE quantite <= '.max(0,(int)setting('low_stock_limit','5')).' ORDER BY quantite ASC,model LIMIT 20')->fetchAll();
    return ['period_days'=>$days,'summary'=>$summary,'inventory'=>$inventory,'top_products'=>$top,'low_stock'=>$low,'generated_at'=>date(DATE_ATOM)];
}

function local_business_insights(array $snapshot): array
{
    $insights = [];
    $summary = $snapshot['summary']; $inventory = $snapshot['inventory'];
    if ((int)$inventory['out_of_stock'] > 0) $insights[] = ['danger','Stock fini',(int)$inventory['out_of_stock'].' pwodwi fini nèt. Verifye yo avan ou pèdi lavant.'];
    if (count($snapshot['low_stock']) > 0) $insights[] = ['warning','Prepare kòmann',count($snapshot['low_stock']).' pwodwi rive nan nivo stock ki ba.'];
    if ((int)$summary['sales_count'] === 0) $insights[] = ['info','Pa gen lavant nan peryòd la','Anrejistre chak vant pou analiz ak prediksyon yo vin pi presi.'];
    $leader = $snapshot['top_products'][0] ?? null;
    if ($leader && (int)$leader['sold_units'] > 0) $insights[] = ['success','Pwodwi vedèt',trim($leader['marque'].' '.$leader['model']).' se pwodwi ki vann plis: '.$leader['sold_units'].' inite.'];
    if ((float)$inventory['retail_value'] > 0 && (int)$summary['sales_count'] > 0) $insights[] = ['info','Lajan nan stock',money($inventory['retail_value']).' se valè vant estimatif tout stock ou genyen kounye a.'];
    if (!$insights) $insights[] = ['success','Biznis la pare','Kontinye antre pwodwi ak lavant pou sistèm nan konstwi analiz ou.'];
    return $insights;
}

function ask_openai_about_business(string $question, array $snapshot): array
{
    $apiKey = trim((string)getenv('OPENAI_API_KEY'));
    if ($apiKey === '') return ['ok'=>false,'message'=>'AI avanse a poko aktive. Analiz otomatik lokal yo disponib anba a.'];
    if (!function_exists('curl_init')) return ['ok'=>false,'message'=>'Modil koneksyon AI a pa disponib sou sèvè a.'];
    $payload = [
        'model' => getenv('OPENAI_MODEL') ?: 'gpt-5-mini',
        'instructions' => 'Ou se yon konseye biznis pou yon ti biznis. Reponn an kreyòl ayisyen klè, kout, ak etap konkrè. Sèvi sèlman ak done estatistik yo bay la. Pa envante done. Fè konnen lè done yo pa sifi.',
        'input' => "Kesyon pwopriyetè a: {$question}\n\nRezime biznis (JSON):\n".json_encode($snapshot, JSON_UNESCAPED_UNICODE),
        'max_output_tokens' => 700,
    ];
    $curl = curl_init('https://api.openai.com/v1/responses');
    curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>40,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$apiKey,'Content-Type: application/json'],CURLOPT_POSTFIELDS=>json_encode($payload)]);
    $raw=curl_exec($curl);$status=(int)curl_getinfo($curl,CURLINFO_HTTP_CODE);$error=curl_error($curl);curl_close($curl);
    if($raw===false||$status<200||$status>=300)return ['ok'=>false,'message'=>'AI a pa t ka reponn kounye a. '.($error?:'Verifye kle API a ak koneksyon entènèt la.')];
    $data=json_decode($raw,true);$text=(string)($data['output_text']??'');
    if($text===''){foreach(($data['output']??[]) as $item){foreach(($item['content']??[]) as $content){if(($content['type']??'')==='output_text')$text.=(string)($content['text']??'');}}}
    return $text!==''?['ok'=>true,'message'=>$text]:['ok'=>false,'message'=>'AI a pa retounen yon repons lizib.'];
}
