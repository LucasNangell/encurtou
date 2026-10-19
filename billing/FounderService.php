<?php
require_once __DIR__ . '/../db.php';

class FounderService {
    const MAX_VAGAS = 100;
    const FOUNDER_PLAN_ID = 5;

    private static function columnExists(string $column): bool {
        global $pdo;
        static $cache = [];
        if (array_key_exists($column, $cache)) {
            return $cache[$column];
        }
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'founder_allocations' AND COLUMN_NAME = ?");
        $stmt->execute([$column]);
        $cache[$column] = ((int)$stmt->fetchColumn()) > 0;
        return $cache[$column];
    }

    public static function getMaxSeats(): int {
        global $pdo;
        try {
            $stmt = $pdo->prepare("SELECT config_value FROM system_config WHERE config_key = 'founder_max_seats' LIMIT 1");
            $stmt->execute();
            $val = (int)$stmt->fetchColumn();
            return $val > 0 ? $val : self::MAX_VAGAS;
        } catch (Exception $e) {
            return self::MAX_VAGAS;
        }
    }

    public static function isFounderPlanId(int $planId): bool {
        return $planId === self::FOUNDER_PLAN_ID;
    }

    public static function reserveSlot(int $userId): bool {
        global $pdo;
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->query("SELECT COUNT(*) FROM founder_allocations FOR UPDATE");
            $count = $stmt->fetchColumn();

            if ($count >= self::getMaxSeats()) {
                $pdo->rollBack();
                return false;
            }

            $insert = $pdo->prepare("INSERT IGNORE INTO founder_allocations (user_id, status) VALUES (?, 'pending_payment')");
            $insert->execute([$userId]);

            $pdo->commit();
            return true;
        } catch (Exception $e) {
            $pdo->rollBack();
            return false;
        }
    }

    public static function confirmSlot(int $userId, ?float $monthly = null, ?float $annual = null): void {
        global $pdo;
        $hasLocked = self::columnExists('locked_monthly_price') && self::columnExists('locked_annual_price');

        if ($hasLocked && $monthly !== null && $annual !== null) {
            $existing = self::getLockedPrices($userId);
            if ($existing !== null) {
                $stmt = $pdo->prepare("UPDATE founder_allocations SET status = 'active' WHERE user_id = ?");
                $stmt->execute([$userId]);
                return;
            }
            $stmt = $pdo->prepare("UPDATE founder_allocations SET status = 'active', locked_monthly_price = ?, locked_annual_price = ? WHERE user_id = ?");
            $stmt->execute([$monthly, $annual, $userId]);
            return;
        }

        $stmt = $pdo->prepare("UPDATE founder_allocations SET status = 'active' WHERE user_id = ?");
        $stmt->execute([$userId]);
    }

    public static function releaseSlot(int $userId): void {
        global $pdo;
        $stmt = $pdo->prepare("DELETE FROM founder_allocations WHERE user_id = ?");
        $stmt->execute([$userId]);
    }

    public static function getAvailableSlots(): int {
        global $pdo;
        $stmt = $pdo->query("SELECT COUNT(*) FROM founder_allocations");
        $count = $stmt->fetchColumn();
        return max(0, self::getMaxSeats() - (int)$count);
    }

    public static function getLockedPrices(int $userId): ?array {
        global $pdo;
        if (!self::columnExists('locked_monthly_price') || !self::columnExists('locked_annual_price')) {
            return null;
        }
        $stmt = $pdo->prepare("SELECT locked_monthly_price, locked_annual_price FROM founder_allocations WHERE user_id = ? AND status = 'active' LIMIT 1");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || $row['locked_monthly_price'] === null || $row['locked_annual_price'] === null) {
            return null;
        }
        return [
            'monthly' => (float)$row['locked_monthly_price'],
            'annual' => (float)$row['locked_annual_price'],
        ];
    }

    public static function isActiveFounder(int $userId): bool {
        global $pdo;
        $stmt = $pdo->prepare("SELECT id FROM founder_allocations WHERE user_id = ? AND status = 'active' LIMIT 1");
        $stmt->execute([$userId]);
        return (bool)$stmt->fetchColumn();
    }

    /**
     * Resolve preços para checkout: novos founders usam tabela plans;
     * founders ativos renovando usam preços congelados.
     */
    public static function resolveCheckoutPrices(int $userId, int $planId, string $cycle): array {
        global $pdo;

        $stmt = $pdo->prepare("SELECT monthly_price, annual_price FROM plans WHERE id = ? LIMIT 1");
        $stmt->execute([$planId]);
        $plan = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$plan) {
            return ['monthly' => 0.0, 'annual' => 0.0, 'charge' => 0.0];
        }

        $monthly = (float)$plan['monthly_price'];
        $annual = (float)$plan['annual_price'];

        if (self::isFounderPlanId($planId)) {
            $locked = self::getLockedPrices($userId);
            if ($locked !== null) {
                $monthly = $locked['monthly'];
                $annual = $locked['annual'];
            }
        }

        $charge = ($cycle === 'annual') ? $annual : $monthly;
        return [
            'monthly' => $monthly,
            'annual' => $annual,
            'charge' => $charge,
        ];
    }
}
