<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/FounderService.php';

class SubscriptionService {
    public static function activateSubscription(int $userId, int $planId, string $subId, string $customerId, bool $isFounder, string $cycle = 'monthly') {
        global $pdo;

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO user_subscriptions 
                (user_id, plan_id, stripe_customer_id, stripe_subscription_id, status, is_founder) 
                VALUES (?, ?, ?, ?, 'active', ?)
                ON DUPLICATE KEY UPDATE 
                plan_id = VALUES(plan_id), status = 'active', is_founder = VALUES(is_founder)");
            $stmt->execute([$userId, $planId, $customerId, $subId, $isFounder ? 1 : 0]);

            $upd = $pdo->prepare("UPDATE users SET current_plan_id = ?, billing_status = 'active' WHERE id = ?");
            $upd->execute([$planId, $userId]);

            if ($isFounder) {
                $prices = FounderService::resolveCheckoutPrices($userId, $planId, $cycle);
                FounderService::confirmSlot($userId, $prices['monthly'], $prices['annual']);
            }

            $pdo->commit();
        } catch(Exception $e) {
            $pdo->rollBack();
            error_log("Subscription Activation Failed: " . $e->getMessage());
        }
    }

    public static function handleCancellation(int $userId) {
        global $pdo;
        $upd = $pdo->prepare("UPDATE users SET current_plan_id = 1, billing_status = 'freemium' WHERE id = ?");
        $upd->execute([$userId]);
        
        FounderService::releaseSlot($userId);
    }
}
