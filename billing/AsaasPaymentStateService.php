<?php

class AsaasPaymentStateService
{
    public static function mapPlanSlugToId(string $planSlug): int
    {
        return ($planSlug === 'founder') ? 5 : (($planSlug === 'business') ? 4 : (($planSlug === 'pro') ? 3 : (($planSlug === 'creator') ? 2 : 1)));
    }

    public static function normalizeStatusFromWebhookEvent(string $eventName): string
    {
        $event = strtoupper(trim($eventName));
        $mapping = [
            'PAYMENT_RECEIVED' => 'CONFIRMED',
            'PAYMENT_CONFIRMED' => 'CONFIRMED',
            'PAYMENT_OVERDUE' => 'OVERDUE',
            'PAYMENT_DELETED' => 'CANCELED',
            'PAYMENT_REFUNDED' => 'REFUNDED',
            'PAYMENT_CHARGEBACK_REQUESTED' => 'CHARGEBACK',
            'PAYMENT_CHARGEBACK_DISPUTE' => 'CHARGEBACK',
            'PAYMENT_AWAITING_CHARGEBACK_REVERSAL' => 'CHARGEBACK',
            'PAYMENT_DUNNING_REQUESTED' => 'OVERDUE',
            'PAYMENT_DUNNING_RECEIVED' => 'CONFIRMED',
        ];
        return $mapping[$event] ?? 'UNKNOWN';
    }

    public static function normalizeStatusFromPaymentStatus(string $paymentStatus): string
    {
        $status = strtoupper(trim($paymentStatus));
        $mapping = [
            'RECEIVED' => 'CONFIRMED',
            'CONFIRMED' => 'CONFIRMED',
            'RECEIVED_IN_CASH' => 'CONFIRMED',
            'OVERDUE' => 'OVERDUE',
            'REFUNDED' => 'REFUNDED',
            'REFUND_REQUESTED' => 'REFUND_REQUESTED',
            'CHARGEBACK_REQUESTED' => 'CHARGEBACK',
            'CHARGEBACK_DISPUTE' => 'CHARGEBACK',
            'AWAITING_CHARGEBACK_REVERSAL' => 'CHARGEBACK',
            'DELETED' => 'CANCELED',
            'PENDING' => 'PENDING',
        ];
        return $mapping[$status] ?? $status;
    }

    public static function apply(
        PDO $pdo,
        int $userId,
        string $planSlug,
        string $normalizedStatus,
        string $paymentId,
        float $amount,
        string $billingType = '',
        string $description = '',
        string $eventId = ''
    ): array {
        if ($userId <= 0 || $paymentId === '') {
            return ['success' => false, 'reason' => 'invalid_payload'];
        }

        $normalizedStatus = strtoupper(trim($normalizedStatus));
        if ($normalizedStatus === '') {
            $normalizedStatus = 'UNKNOWN';
        }

        try {
            $pdo->beginTransaction();

            $alreadyProcessed = self::wasStatusAlreadyProcessed($pdo, $paymentId, $normalizedStatus);
            if ($alreadyProcessed) {
                $pdo->commit();
                return ['success' => true, 'already_processed' => true, 'normalized_status' => $normalizedStatus];
            }

            self::insertPaymentHistory($pdo, $userId, $paymentId, $amount, $normalizedStatus, $billingType, $planSlug, $eventId);
            self::applyUserAccessState($pdo, $userId, $planSlug, $normalizedStatus, $description);

            $pdo->commit();
            return ['success' => true, 'already_processed' => false, 'normalized_status' => $normalizedStatus];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('asaas_state_apply_error: ' . $e->getMessage());
            return ['success' => false, 'reason' => 'apply_error'];
        }
    }

    private static function usersColumnExists(PDO $pdo, string $column): bool
    {
        static $cache = [];
        if (array_key_exists($column, $cache)) {
            return $cache[$column];
        }
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = ?");
        $stmt->execute([$column]);
        $cache[$column] = ((int)$stmt->fetchColumn()) > 0;
        return $cache[$column];
    }

    private static function tableExists(PDO $pdo, string $tableName): bool
    {
        static $cache = [];
        if (array_key_exists($tableName, $cache)) {
            return $cache[$tableName];
        }
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
        $stmt->execute([$tableName]);
        $cache[$tableName] = ((int)$stmt->fetchColumn()) > 0;
        return $cache[$tableName];
    }

    private static function wasStatusAlreadyProcessed(PDO $pdo, string $paymentId, string $status): bool
    {
        if (!self::tableExists($pdo, 'payments_history')) {
            return false;
        }
        $stmt = $pdo->prepare("SELECT id FROM payments_history WHERE asaas_payment_id = ? AND status = ? LIMIT 1");
        $stmt->execute([$paymentId, $status]);
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    }

    private static function insertPaymentHistory(
        PDO $pdo,
        int $userId,
        string $paymentId,
        float $amount,
        string $status,
        string $billingType,
        string $planSlug,
        string $eventId
    ): void {
        if (!self::tableExists($pdo, 'payments_history')) {
            return;
        }
        $hasGatewayEvent = self::paymentsHistoryColumnExists($pdo, 'gateway_event_id');
        if ($hasGatewayEvent) {
            $stmt = $pdo->prepare("
                INSERT INTO payments_history (user_id, asaas_payment_id, amount, status, payment_method, plan_assigned, gateway_event_id, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$userId, $paymentId, $amount, $status, $billingType, $planSlug, $eventId !== '' ? $eventId : null]);
            return;
        }
        $stmt = $pdo->prepare("
            INSERT INTO payments_history (user_id, asaas_payment_id, amount, status, payment_method, plan_assigned)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $paymentId, $amount, $status, $billingType, $planSlug]);
    }

    private static function paymentsHistoryColumnExists(PDO $pdo, string $column): bool
    {
        static $cache = [];
        if (array_key_exists($column, $cache)) {
            return $cache[$column];
        }
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments_history' AND COLUMN_NAME = ?");
        $stmt->execute([$column]);
        $cache[$column] = ((int)$stmt->fetchColumn()) > 0;
        return $cache[$column];
    }

    private static function applyUserAccessState(PDO $pdo, int $userId, string $planSlug, string $status, string $description): void
    {
        $positive = in_array($status, ['CONFIRMED'], true);
        $downgradeImmediate = in_array($status, ['REFUNDED', 'CHARGEBACK', 'CANCELED'], true);
        $pastDue = in_array($status, ['OVERDUE', 'PENDING', 'REFUND_REQUESTED'], true);

        if ($positive) {
            $planId = self::mapPlanSlugToId($planSlug);
            $isAnnual = stripos($description, 'Anual') !== false;
            $cycle = $isAnnual ? 'annual' : 'monthly';
            $days = $isAnnual ? 365 : 30;

            $set = [];
            $params = [];
            if (self::usersColumnExists($pdo, 'plan')) {
                $set[] = 'plan = ?';
                $params[] = $planSlug;
            }
            if (self::usersColumnExists($pdo, 'current_plan_id')) {
                $set[] = 'current_plan_id = ?';
                $params[] = $planId;
            }
            if (self::usersColumnExists($pdo, 'plan_started_at')) {
                $set[] = 'plan_started_at = NOW()';
            }
            if (self::usersColumnExists($pdo, 'plan_expires_at')) {
                $set[] = 'plan_expires_at = DATE_ADD(NOW(), INTERVAL ? DAY)';
                $params[] = $days;
            }
            if (self::usersColumnExists($pdo, 'plan_cycle')) {
                $set[] = 'plan_cycle = ?';
                $params[] = $cycle;
            }
            if (self::usersColumnExists($pdo, 'billing_status')) {
                $set[] = "billing_status = 'active'";
            }
            if (self::usersColumnExists($pdo, 'plan_auto_renew')) {
                $set[] = 'plan_auto_renew = 1';
            }
            if (!empty($set)) {
                $params[] = $userId;
                $pdo->prepare("UPDATE users SET " . implode(', ', $set) . " WHERE id = ?")->execute($params);
            }
            return;
        }

        if ($downgradeImmediate) {
            $set = [];
            $params = [];
            if (self::usersColumnExists($pdo, 'plan')) {
                $set[] = "plan = 'free'";
            }
            if (self::usersColumnExists($pdo, 'current_plan_id')) {
                $set[] = 'current_plan_id = 1';
            }
            if (self::usersColumnExists($pdo, 'plan_expires_at')) {
                $set[] = 'plan_expires_at = NOW()';
            }
            if (self::usersColumnExists($pdo, 'billing_status')) {
                $set[] = "billing_status = 'freemium'";
            }
            if (self::usersColumnExists($pdo, 'plan_auto_renew')) {
                $set[] = 'plan_auto_renew = 0';
            }
            if (!empty($set)) {
                $params[] = $userId;
                $pdo->prepare("UPDATE users SET " . implode(', ', $set) . " WHERE id = ?")->execute($params);
            }
            return;
        }

        if ($pastDue && self::usersColumnExists($pdo, 'billing_status')) {
            $pdo->prepare("UPDATE users SET billing_status = 'past_due' WHERE id = ?")->execute([$userId]);
        }
    }
}

