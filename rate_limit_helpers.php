<?php

function checkRateLimit(PDO $pdo, string $actionName, int $maxAttempts = 5, int $lockoutMinutes = 15): bool
{
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS rate_limits (
            ip VARCHAR(45) NOT NULL,
            action VARCHAR(80) NOT NULL,
            attempts INT DEFAULT 1,
            last_attempt DATETIME NOT NULL,
            PRIMARY KEY (ip, action)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $ip = getRealIP();
        $stmt = $pdo->prepare("SELECT attempts, last_attempt FROM rate_limits WHERE ip = ? AND action = ?");
        $stmt->execute([$ip, $actionName]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $lastTime = strtotime($row['last_attempt']);
            $diffMinutes = (time() - $lastTime) / 60;

            if ($diffMinutes > $lockoutMinutes) {
                $pdo->prepare("UPDATE rate_limits SET attempts = 1, last_attempt = NOW() WHERE ip = ? AND action = ?")
                    ->execute([$ip, $actionName]);
                return true;
            }

            if ((int)$row['attempts'] >= $maxAttempts) {
                return false;
            }

            $pdo->prepare("UPDATE rate_limits SET attempts = attempts + 1, last_attempt = NOW() WHERE ip = ? AND action = ?")
                ->execute([$ip, $actionName]);
        } else {
            $pdo->prepare("INSERT INTO rate_limits (ip, action, attempts, last_attempt) VALUES (?, ?, 1, NOW())")
                ->execute([$ip, $actionName]);
        }
        return true;
    } catch (Throwable $e) {
        error_log('rate_limit_error_' . $actionName . ': ' . $e->getMessage());
        return false;
    }
}

function checkUserRateLimit(PDO $pdo, int $userId, string $actionName, int $maxAttempts, int $lockoutMinutes = 60): bool
{
    $key = 'user_' . $userId . '_' . $actionName;
    return checkRateLimit($pdo, $key, $maxAttempts, $lockoutMinutes);
}
