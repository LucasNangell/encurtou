<?php
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
require_once 'db.php';

function secColumnExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function secAddColumn(PDO $pdo, string $table, string $column, string $definition): void
{
    if (!secColumnExists($pdo, $table, $column)) {
        $pdo->exec("ALTER TABLE `$table` ADD COLUMN $definition");
        echo "Added $table.$column\n";
    } else {
        echo "$table.$column already exists\n";
    }
}

function secTableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
}

try {
    secAddColumn($pdo, 'users', 'session_invalidated_at', '`session_invalidated_at` DATETIME NULL DEFAULT NULL');
    secAddColumn($pdo, 'users', 'email_verified_at', '`email_verified_at` DATETIME NULL DEFAULT NULL');
    secAddColumn($pdo, 'users', 'email_verify_token_hash', '`email_verify_token_hash` VARCHAR(64) NULL DEFAULT NULL');
    secAddColumn($pdo, 'users', 'email_verify_sent_at', '`email_verify_sent_at` DATETIME NULL DEFAULT NULL');
    secAddColumn($pdo, 'users', 'abuse_strike_count', '`abuse_strike_count` INT(11) NOT NULL DEFAULT 0');
    secAddColumn($pdo, 'users', 'abuse_strike_window_start', '`abuse_strike_window_start` DATETIME NULL DEFAULT NULL');
    secAddColumn($pdo, 'users', 'signup_ip', '`signup_ip` VARCHAR(45) NULL DEFAULT NULL');
    secAddColumn($pdo, 'users', 'signup_user_agent', '`signup_user_agent` VARCHAR(500) NULL DEFAULT NULL');
    secAddColumn($pdo, 'users', 'can_moderate', '`can_moderate` TINYINT(1) NOT NULL DEFAULT 0');

    secAddColumn($pdo, 'links', 'security_paused', '`security_paused` TINYINT(1) NOT NULL DEFAULT 0');
    secAddColumn($pdo, 'links', 'security_verdict', '`security_verdict` VARCHAR(32) NULL DEFAULT NULL');
    secAddColumn($pdo, 'links', 'security_scanned_at', '`security_scanned_at` DATETIME NULL DEFAULT NULL');

    secAddColumn($pdo, 'bio_pages', 'admin_ban_paused', '`admin_ban_paused` TINYINT(1) NOT NULL DEFAULT 0');

    $sqlFile = __DIR__ . '/security_abuse_migration.sql';
    if (is_readable($sqlFile)) {
        $sql = file_get_contents($sqlFile);
        $createStatements = [];
        if (preg_match_all('/CREATE TABLE IF NOT EXISTS `([^`]+)`[^;]+;/s', $sql, $matches)) {
            foreach ($matches[0] as $statement) {
                if (stripos($statement, 'CREATE TABLE') !== false) {
                    $pdo->exec($statement);
                    if (preg_match('/CREATE TABLE IF NOT EXISTS `([^`]+)`/', $statement, $m)) {
                        echo "Ensured table {$m[1]}\n";
                    }
                }
            }
        }
    }

    if (secTableExists($pdo, 'abuse_keyword_rules')) {
        $count = (int)$pdo->query('SELECT COUNT(*) FROM abuse_keyword_rules')->fetchColumn();
        if ($count === 0) {
            require_once 'abuse_policy_helpers.php';
            $seedTypes = ['slug', 'subdomain', 'destination', 'any'];
            $insert = $pdo->prepare(
                'INSERT INTO abuse_keyword_rules (term, rule_type, severity, is_active) VALUES (?, ?, ?, 1)'
            );
            foreach ($seedTypes as $type) {
                foreach (abuseDefaultKeywordRules($type === 'any' ? 'slug' : $type) as $term) {
                    $insert->execute([$term, $type === 'destination' ? 'destination' : ($type === 'any' ? 'any' : $type), 'block']);
                }
            }
            echo "Seeded abuse_keyword_rules\n";
        }
    }

    if (secTableExists($pdo, 'abuse_domain_blocklist')) {
        $count = (int)$pdo->query('SELECT COUNT(*) FROM abuse_domain_blocklist')->fetchColumn();
        if ($count === 0) {
            require_once 'abuse_policy_helpers.php';
            $insert = $pdo->prepare(
                'INSERT INTO abuse_domain_blocklist (pattern, match_type, reason, is_active) VALUES (?, ?, ?, 1)'
            );
            foreach (abuseDefaultDomainBlocklist() as $row) {
                $insert->execute([$row['pattern'], $row['match_type'], $row['reason']]);
            }
            echo "Seeded abuse_domain_blocklist\n";
        }
    }

    if (secColumnExists($pdo, 'users', 'can_moderate')) {
        $pdo->exec("UPDATE users SET can_moderate = 1 WHERE email = 'nangellbusiness@gmail.com'");
        echo "Granted can_moderate to master admin email\n";
    }

    echo "Security abuse migration completed.\n";
} catch (PDOException $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
    exit(1);
}
