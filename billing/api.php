<?php
// API do Billing (Checkout Session, Status, etc)
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth_guard.php';
require_once __DIR__ . '/configs.php';
require_once __DIR__ . '/PlanService.php';
require_once __DIR__ . '/FounderService.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json');

function isFounderPlanId(int $planId): bool
{
    $plan = PlanService::getPlanDetails($planId);
    if (!$plan) {
        return false;
    }
    return stripos((string)($plan['name'] ?? ''), 'founder') !== false;
}

$userId = $_SESSION['user_id'] ?? null;
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

if ($action === 'plans') {
    $plans = PlanService::getAllPlans();
    $founderSlotsInfo = [
        'available' => FounderService::getAvailableSlots(),
        'total' => FounderService::getMaxSeats()
    ];
    echo json_encode(['success' => true, 'plans' => $plans, 'founder_slots' => $founderSlotsInfo]);
    exit;
}

$userId = requireAuthenticatedUserNotBanned($pdo, true, false);

if ($action === 'status') {
    $stmt = $pdo->prepare("SELECT u.current_plan_id, u.billing_status, p.name as plan_name 
                           FROM users u LEFT JOIN plans p ON u.current_plan_id = p.id 
                           WHERE u.id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'data' => $user]);
    exit;
}

if ($action === 'checkout') {
    if (!BILLING_STRIPE_CHECKOUT_ENABLED) {
        http_response_code(503);
        echo json_encode([
            'success' => false,
            'message' => 'Checkout Stripe desabilitado neste ambiente.',
            'code' => 'stripe_checkout_disabled'
        ]);
        exit;
    }

    $stripeConfigured = STRIPE_SECRET_KEY !== ''
        && STRIPE_WEBHOOK_SECRET !== ''
        && strpos(STRIPE_SECRET_KEY, 'sk_live_123') === false
        && strpos(STRIPE_WEBHOOK_SECRET, 'whsec_live') === false;

    if (!$stripeConfigured && !BILLING_ALLOW_MOCK_CHECKOUT) {
        http_response_code(503);
        echo json_encode([
            'success' => false,
            'message' => 'Stripe não configurado para checkout neste ambiente.',
            'code' => 'stripe_not_configured'
        ]);
        exit;
    }

    $planId = (int)($_POST['plan_id'] ?? 0);
    $cycle = $_POST['cycle'] ?? 'monthly';
    
    if ($planId > 0 && isFounderPlanId($planId)) {
        $reserved = FounderService::reserveSlot($userId);
        if (!$reserved) {
            echo json_encode(['success' => false, 'message' => 'Não há mais vagas de founder disponíveis ou você já possui reserva ativa pendente.']);
            exit;
        }
    }
    
    if (!BILLING_ALLOW_MOCK_CHECKOUT) {
        http_response_code(501);
        echo json_encode([
            'success' => false,
            'message' => 'Checkout Stripe ainda não implementado via SDK neste ambiente.',
            'code' => 'stripe_sdk_pending'
        ]);
        exit;
    }

    // Modo mock permitido apenas localmente via BILLING_ALLOW_MOCK_CHECKOUT=1.
    echo json_encode(['success' => true, 'url' => 'https://checkout.stripe.com/mocked_session_local_dev']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Ação inválida']);
