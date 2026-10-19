<?php
// Desativa exibição de erros para não quebrar o recebimento do JSON
ini_set('display_errors', 0);
require_once 'db.php';
require_once 'config_asaas.php';
require_once __DIR__ . '/billing/FounderService.php';
require_once __DIR__ . '/billing/AsaasPaymentStateService.php';
require_once 'affiliate_helpers.php';

$FOUNDER_PLAN_ID = 5;

function webhookAsaasUsersColumnExists(PDO $pdo, string $column): bool {
    static $cache = [];
    if (array_key_exists($column, $cache)) {
        return $cache[$column];
    }
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = ?");
    $stmt->execute([$column]);
    $cache[$column] = ((int)$stmt->fetchColumn()) > 0;
    return $cache[$column];
}

function webhookAsaasSyncPlanAutoRenew(PDO $pdo, int $uid): void {
    if (!webhookAsaasUsersColumnExists($pdo, 'plan_auto_renew')) {
        return;
    }
    $pdo->prepare("UPDATE users SET plan_auto_renew = 1 WHERE id = ?")->execute([$uid]);
}

function webhookConfirmFounderSlot(int $userId, string $description): void {
    $isAnnual = stripos($description, 'Anual') !== false;
    $cycle = $isAnnual ? 'annual' : 'monthly';
    $prices = FounderService::resolveCheckoutPrices($userId, FounderService::FOUNDER_PLAN_ID, $cycle);
    FounderService::confirmSlot($userId, $prices['monthly'], $prices['annual']);
}

function mapPlanSlugToIdWebhook(string $planSlug): int {
    global $FOUNDER_PLAN_ID;
    return ($planSlug === 'founder') ? $FOUNDER_PLAN_ID : (($planSlug === 'business') ? 4 : (($planSlug === 'pro') ? 3 : (($planSlug === 'creator') ? 2 : 1)));
}

function registerAffiliateCommissionFromAsaasPayment(PDO $pdo, int $userId, array $payment): void {
    if ($userId <= 0) {
        return;
    }
    if (!affiliateTableExists($pdo, 'affiliate_referrals') || !affiliateTableExists($pdo, 'affiliate_commissions')) {
        return;
    }

    $stmtRef = $pdo->prepare("SELECT affiliate_id FROM affiliate_referrals WHERE referred_user_id = ? LIMIT 1");
    $stmtRef->execute([$userId]);
    $affiliateId = (int)$stmtRef->fetchColumn();
    if ($affiliateId <= 0) {
        return;
    }

    $paymentId = (string)($payment['id'] ?? '');
    if ($paymentId === '') {
        return;
    }

    $alreadyStmt = $pdo->prepare("SELECT id FROM affiliate_commissions WHERE asaas_payment_id = ? LIMIT 1");
    $alreadyStmt->execute([$paymentId]);
    if ($alreadyStmt->fetch(PDO::FETCH_ASSOC)) {
        return;
    }

    $billingType = strtoupper((string)($payment['billingType'] ?? ''));
    $amount = (float)($payment['value'] ?? 0);
    if ($amount <= 0) {
        return;
    }

    $paidCount = 0;
    if (affiliateTableExists($pdo, 'payments_history')) {
        $paidCountStmt = $pdo->prepare("SELECT COUNT(*) FROM payments_history WHERE user_id = ? AND status = 'CONFIRMED'");
        $paidCountStmt->execute([$userId]);
        $paidCount = (int)$paidCountStmt->fetchColumn();
    }
    $installmentNumber = $paidCount > 0 ? $paidCount : 1;
    $commissionRate = affiliateCommissionRateForInstallment($installmentNumber);
    $commissionAmount = round($amount * ($commissionRate / 100), 2);

    $confirmedAt = new DateTimeImmutable('now');
    $expectedRelease = affiliateExpectedReleaseAt($billingType, $confirmedAt);

    $stmtComm = $pdo->prepare("
        INSERT INTO affiliate_commissions (
            affiliate_id, referred_user_id, asaas_payment_id, payment_method,
            installment_number, gross_amount, commission_rate, commission_amount,
            payment_confirmed_at, expected_release_at, payout_status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
    ");
    $stmtComm->execute([
        $affiliateId,
        $userId,
        $paymentId,
        $billingType !== '' ? $billingType : 'UNKNOWN',
        $installmentNumber,
        $amount,
        $commissionRate,
        $commissionAmount,
        $confirmedAt->format('Y-m-d H:i:s'),
        $expectedRelease->format('Y-m-d H:i:s'),
    ]);
}

// Obtém o corpo da requisição
$body = file_get_contents('php://input');
$event = json_decode($body, true);

// Validação de Segurança
$headers = function_exists('getallheaders') ? getallheaders() : [];
$webhookToken = $headers['asaas-access-token'] ?? $headers['Asaas-Access-Token'] ?? ($_SERVER['HTTP_ASAAS_ACCESS_TOKEN'] ?? '');

if (trim((string)ASAAS_WEBHOOK_TOKEN) === '') {
    http_response_code(503);
    exit('Webhook token não configurado');
}

if ($webhookToken !== ASAAS_WEBHOOK_TOKEN) {
    http_response_code(401);
    exit('Não autorizado');
}

if (!$event) {
    http_response_code(400);
    exit('Evento inválido');
}

$eventName = (string)($event['event'] ?? '');
$payment = $event['payment'] ?? [];
$paymentId = (string)($payment['id'] ?? '');
$externalRef = $payment['externalReference'] ?? ''; // USER_X_PLAN_Y
$eventId = (string)($event['id'] ?? '');

if ($eventName !== '') {
    $userId = 0;
    $assignedPlan = 'free';

    // Tenta identificar via External Reference
    if (preg_match('/USER_(\d+)_PLAN_(\w+)/', $externalRef, $matches)) {
        $userId = (int)$matches[1];
        $assignedPlan = $matches[2];
    } else {
        // Fallback: Busca via asaas_customer_id
        $customerId = $payment['customer'] ?? '';
        $stmt = $pdo->prepare("SELECT id FROM users WHERE asaas_customer_id = ?");
        $stmt->execute([$customerId]);
        $userId = (int)$stmt->fetchColumn();
    }

    if ($userId > 0 && $paymentId !== '') {
        $normalizedStatus = AsaasPaymentStateService::normalizeStatusFromWebhookEvent($eventName);
        $result = AsaasPaymentStateService::apply(
            $pdo,
            $userId,
            (string)$assignedPlan,
            $normalizedStatus,
            $paymentId,
            (float)($payment['value'] ?? 0),
            (string)($payment['billingType'] ?? ''),
            (string)($payment['description'] ?? ''),
            $eventId
        );

        if (!empty($result['success']) && ($result['normalized_status'] ?? '') === 'CONFIRMED') {
            webhookAsaasSyncPlanAutoRenew($pdo, $userId);
            try {
                registerAffiliateCommissionFromAsaasPayment($pdo, $userId, $payment);
            } catch (Throwable $e) {
                error_log('affiliate commission webhook error: ' . $e->getMessage());
            }
            if ($assignedPlan === 'founder') {
                webhookConfirmFounderSlot($userId, (string)($payment['description'] ?? ''));
            }
        } elseif (in_array((string)($result['normalized_status'] ?? ''), ['REFUNDED', 'CHARGEBACK', 'CANCELED'], true) && $assignedPlan === 'founder') {
            FounderService::releaseSlot($userId);
        }
    }
}

// Responde com 200 para o Asaas parar de tentar enviar
http_response_code(200);
echo "OK";
?>
