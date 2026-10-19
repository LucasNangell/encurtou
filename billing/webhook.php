<?php
// Webhook Recebedor (Handler de Eventos do Stripe)
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/configs.php';
require_once __DIR__ . '/SubscriptionService.php';

$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

header('Content-Type: application/json');

function parseStripeSignature(string $header): array
{
    $parts = array_filter(array_map('trim', explode(',', $header)));
    $parsed = [];
    foreach ($parts as $part) {
        [$k, $v] = array_pad(explode('=', $part, 2), 2, null);
        if ($k !== null && $v !== null) {
            $parsed[$k] = $v;
        }
    }
    return $parsed;
}

function verifyStripeSignature(string $payload, string $header, string $secret, int $toleranceSeconds = 300): bool
{
    if ($payload === '' || $header === '' || $secret === '') {
        return false;
    }

    $sig = parseStripeSignature($header);
    $timestamp = isset($sig['t']) ? (int)$sig['t'] : 0;
    $received = $sig['v1'] ?? '';
    if ($timestamp <= 0 || $received === '') {
        return false;
    }

    if (abs(time() - $timestamp) > $toleranceSeconds) {
        return false;
    }

    $signedPayload = $timestamp . '.' . $payload;
    $expected = hash_hmac('sha256', $signedPayload, $secret);
    return hash_equals($expected, $received);
}

if (!verifyStripeSignature($payload, $sig_header, STRIPE_WEBHOOK_SECRET)) {
    error_log('Billing webhook signature invalid.');
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Invalid signature']);
    exit;
}

$event = json_decode($payload, true);

if (!$event || !isset($event['id'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid payload']);
    exit;
}

$eventId = $event['id'];
$type = $event['type'];

// Idempotency Lock: Garantir que não rodemos o mesmo webhook 2 vezes
$check = $pdo->prepare("SELECT id FROM billing_events_log WHERE gateway_event_id = ?");
$check->execute([$eventId]);
if ($check->rowCount() > 0) {
    echo json_encode(['status' => 'ignored', 'reason' => 'Duplicate event']);
    http_response_code(200);
    exit;
}

// Salvar cópia fidedigna do payload
$log = $pdo->prepare("INSERT INTO billing_events_log (event_type, gateway_event_id, payload) VALUES (?, ?, ?)");
$log->execute([$type, $eventId, $payload]);

// Tratar os eventos centrais do Billing
switch ($type) {
    case 'checkout.session.completed':
        $session = $event['data']['object'];
        
        // Em producão os custom metadatas trazem user_id
        $userId = $session['metadata']['user_id'] ?? null;
        $planId = $session['metadata']['plan_id'] ?? null;
        $customerId = $session['customer'] ?? null;
        $subId = $session['subscription'] ?? null;
        $planSlug = strtolower((string)($session['metadata']['plan_slug'] ?? ''));
        $isFounder = ($planId == 5) || ($planSlug === 'founder');

        if ($userId) {
            SubscriptionService::activateSubscription($userId, $planId, $subId, $customerId, $isFounder);
        }
        break;

    case 'customer.subscription.deleted':
    case 'invoice.payment_failed':
        $sub = $event['data']['object']; // Subscription or Invoice
        // Busca qual o user_id dessa sub e cancela
        // $stmt = $pdo->prepare("SELECT user_id FROM user_subscriptions WHERE stripe_subscription_id = ?"); ...
        // $userId = ...
        // SubscriptionService::handleCancellation($userId);
        break;
}

echo json_encode(['status' => 'success']);
http_response_code(200);
