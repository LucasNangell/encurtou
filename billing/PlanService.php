<?php
require_once __DIR__ . '/configs.php';
require_once __DIR__ . '/../db.php';

class PlanService {
    public static function getPlanDetails(int $planId) {
        global $pdo;
        $stmt = $pdo->prepare("SELECT * FROM plans WHERE id = ? AND is_active = 1");
        $stmt->execute([$planId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function getAllPlans() {
        global $pdo;
        $stmt = $pdo->query("SELECT * FROM plans WHERE is_active = 1 ORDER BY tier_level ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
