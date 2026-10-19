<?php
header('Content-Type: application/json');
require_once 'db.php';
require_once 'config_asaas.php';
require_once __DIR__ . '/billing/FounderService.php';
require_once __DIR__ . '/billing/AsaasPaymentStateService.php';
require_once 'auth_guard.php';

$userId = requireAuthenticatedUserNotBanned($pdo, true, false);
$FOUNDER_PLAN_ID = 5;

function checkoutUsersColumnExists(PDO $pdo, string $column): bool {
    static $cache = [];
    if (array_key_exists($column, $cache)) {
        return $cache[$column];
    }
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = ?");
    $stmt->execute([$column]);
    $cache[$column] = ((int)$stmt->fetchColumn()) > 0;
    return $cache[$column];
}

function checkoutSyncPlanAutoRenew(PDO $pdo, int $uid): void {
    if (!checkoutUsersColumnExists($pdo, 'plan_auto_renew')) {
        return;
    }
    $pdo->prepare("UPDATE users SET plan_auto_renew = 1 WHERE id = ?")->execute([$uid]);
}

function checkoutConfirmFounderSlot(int $userId, string $cycle): void {
    $prices = FounderService::resolveCheckoutPrices($userId, FounderService::FOUNDER_PLAN_ID, $cycle);
    FounderService::confirmSlot($userId, $prices['monthly'], $prices['annual']);
}

function mapPlanSlugToId(string $planSlug): int {
    global $FOUNDER_PLAN_ID;
    return ($planSlug === 'founder') ? $FOUNDER_PLAN_ID : (($planSlug === 'business') ? 4 : (($planSlug === 'pro') ? 3 : (($planSlug === 'creator') ? 2 : 1)));
}

// SUPORTE A JSON (Prorrata e Cartão)
$input = json_decode(file_get_contents('php://input'), true);
if ($input) {
    $_POST = array_merge($_POST, $input);
}

$action = $_GET['action'] ?? '';

// 0. BUSCA INFORMAÇÕES DO CLIENTE
if ($action === 'get_customer_info') {
    $stmt = $pdo->prepare("SELECT billing_name, cpf_cnpj, address, complement, province, city, state, postal_code, address_number FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $info = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'info' => $info]);
    exit;
}

// NOVO: CALCULA DETALHES DE UPGRADE (PRORRATA)
if ($action === 'get_upgrade_details') {
    $newPlanId = (int)$_GET['plan_id'];
    $newCycle = $_GET['cycle'] ?? 'monthly';
    
    $details = calculateProration($userId, $newPlanId, $newCycle);
    echo json_encode(['success' => true, 'details' => $details]);
    exit;
}

function calculateProration($userId, $newPlanId, $newCycle) {
    global $pdo;

    $stmtU = $pdo->prepare("SELECT current_plan_id, plan_expires_at, plan_cycle FROM users WHERE id = ?");
    $stmtU->execute([$userId]);
    $user = $stmtU->fetch(PDO::FETCH_ASSOC);

    $resolved = FounderService::resolveCheckoutPrices((int)$userId, (int)$newPlanId, $newCycle);
    $newPrice = $resolved['charge'];
    $credit = 0;
    $daysRemaining = 0;

    if ($user && $user['current_plan_id'] > 1 && !empty($user['plan_expires_at'])) {
        $expiresAt = strtotime($user['plan_expires_at']);
        $now = time();

        if ($expiresAt > $now) {
            $currCycle = ($user['plan_cycle'] === 'annual') ? 'annual' : 'monthly';
            $currResolved = FounderService::resolveCheckoutPrices((int)$userId, (int)$user['current_plan_id'], $currCycle);
            $currPrice = ($user['plan_cycle'] === 'annual') ? $currResolved['annual'] : $currResolved['monthly'];
            $cycleDays = ($user['plan_cycle'] === 'annual') ? 365 : 30;

            $daysRemaining = floor(($expiresAt - $now) / 86400);
            if ($daysRemaining > 0) {
                $credit = ($currPrice / $cycleDays) * $daysRemaining;
            }
        }
    }

    $finalAmount = max(5.00, $newPrice - $credit);

    return [
        'original_price' => (float)$newPrice,
        'credit_amount' => round((float)$credit, 2),
        'final_amount' => round((float)$finalAmount, 2),
        'days_remaining' => $daysRemaining,
        'is_upgrade' => ($credit > 0)
    ];
}

// 1. POLLING DE STATUS
if ($action === 'check') {
    $paymentId = $_GET['id'] ?? '';
    if (!$paymentId) exit;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, ASAAS_API_URL . "/payments/" . $paymentId);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "access_token: " . ASAAS_API_KEY,
        "User-Agent: EncurtouPro"
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($response, true);

    $normalizedStatus = AsaasPaymentStateService::normalizeStatusFromPaymentStatus((string)($data['status'] ?? ''));
    $isConfirmed = ($normalizedStatus === 'CONFIRMED');
    
    if ($isConfirmed) {
        $externalRef = $data['externalReference'] ?? ''; // USER_X_PLAN_Y
        if (preg_match('/USER_(\d+)_PLAN_(\w+)/', $externalRef, $matches)) {
            $uId = (int)$matches[1];
            $pName = $matches[2]; 
            
            AsaasPaymentStateService::apply(
                $pdo,
                $uId,
                (string)$pName,
                $normalizedStatus,
                $paymentId,
                (float)($data['value'] ?? 0),
                (string)($data['billingType'] ?? ''),
                (string)($data['description'] ?? ''),
                'checkout_check_' . $paymentId
            );
            checkoutSyncPlanAutoRenew($pdo, $uId);
            if ((string)$pName === 'founder') {
                $isAnnual = stripos((string)($data['description'] ?? ''), 'Anual') !== false;
                checkoutConfirmFounderSlot($uId, $isAnnual ? 'annual' : 'monthly');
            }
        }
    }

    echo json_encode(['confirmed' => $isConfirmed]);
    exit;
}

// 2. GERAÇÃO DE COBRANÇA
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $planId = (int)$_POST['plan_id'];
    $cycle = $_POST['cycle'] ?? 'monthly';
    $method = $_POST['method'] ?? 'PIX';
    $assignedPlan = '';

    try {
        // Busca dados do usuário
        $stmt = $pdo->prepare("SELECT name, email, phone, asaas_customer_id, plan FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Busca dados do plano
        $stmtPlan = $pdo->prepare("SELECT id, name, monthly_price, annual_price FROM plans WHERE id = ?");
        $stmtPlan->execute([$planId]);
        $plan = $stmtPlan->fetch(PDO::FETCH_ASSOC);

        if (!$plan) {
            echo json_encode(['success' => false, 'message' => 'Plano inválido.']);
            exit;
        }

        // CÁLCULO DE PRORRATA NO BACKEND (Garante integridade)
        $proration = calculateProration($userId, $planId, $cycle);
        $amount = $proration['final_amount'];
        $creditDescription = ($proration['credit_amount'] > 0) ? " (Com desconto de upgrade R$ ".number_format($proration['credit_amount'], 2, ',', '.').")" : "";

        $dueDate = date('Y-m-d', strtotime('+3 days'));
        
        $planSuffix = ($planId == 2) ? 'creator' : (($planId == 3) ? 'pro' : (($planId == 4) ? 'business' : (($planId == $FOUNDER_PLAN_ID) ? 'founder' : 'free')));
        $assignedPlan = $planSuffix; 

        if ($assignedPlan === 'founder') {
            $reserved = FounderService::reserveSlot($userId);
            if (!$reserved) {
                echo json_encode(['success' => false, 'message' => 'Não há mais vagas de founder disponíveis ou você já possui reserva ativa pendente.']);
                exit;
            }
        }

        // Dados Recebidos do Frontend
        $billingName = $_POST['billingName'] ?? $user['name'];
        $cpfCnpj = $_POST['cpfCnpj'] ?? '';
        $postalCode = $_POST['postalCode'] ?? '';
        $address = $_POST['address'] ?? '';
        $addressNumber = $_POST['addressNumber'] ?? '';
        $complement = $_POST['complement'] ?? '';
        $province = $_POST['province'] ?? '';
        $city = $_POST['city'] ?? '';
        $state = $_POST['state'] ?? '';

        // Atualiza dados locais para futuras compras
        $pdo->prepare("UPDATE users SET billing_name = ?, cpf_cnpj = ?, postal_code = ?, address = ?, address_number = ?, complement = ?, province = ?, city = ?, state = ? WHERE id = ?")
            ->execute([$billingName, $cpfCnpj, $postalCode, $address, $addressNumber, $complement, $province, $city, $state, $userId]);

        // Prepara dados para o Asaas
        $customerData = [
            "name" => $billingName,
            "email" => $user['email'],
            "mobilePhone" => $user['phone'],
            "cpfCnpj" => $cpfCnpj,
            "postalCode" => $postalCode,
            "address" => $address,
            "addressNumber" => $addressNumber,
            "complement" => $complement,
            "province" => $province,
            "city" => $city,
            "state" => $state,
            "notificationDisabled" => true
        ];

        // Sincronização com Asaas (Criar ou Atualizar)
        $needCreate = !$user['asaas_customer_id'];
        $customerId = $user['asaas_customer_id'];

        if (!$needCreate) {
            // Tenta atualizar/validar se o cliente existe no ambiente atual
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, ASAAS_API_URL . "/customers/" . $customerId);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($customerData));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Content-Type: application/json", 
                "access_token: " . ASAAS_API_KEY,
                "User-Agent: EncurtouPro"
            ]);
            $res = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            $resData = json_decode($res, true);

            if ($httpCode === 404 || (isset($resData['errors'][0]['code']) && $resData['errors'][0]['code'] === 'invalid_customer')) {
                // Cliente não existe neste ambiente (Sandbox vs Produção)
                $needCreate = true;
            } else if ($httpCode !== 200) {
                // Outro erro (ex: CPF imutável), mas o cliente existe, então seguimos para tentar a cobrança
            }
        }

        if ($needCreate) {
            // Criar Novo (ou recriar se o anterior era de outro ambiente)
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, ASAAS_API_URL . "/customers");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($customerData));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Content-Type: application/json", 
                "access_token: " . ASAAS_API_KEY,
                "User-Agent: EncurtouPro"
            ]);
            $res = curl_exec($ch);
            curl_close($ch);
            $customer = json_decode($res, true);

            if (isset($customer['id'])) {
                $customerId = $customer['id'];
                $pdo->prepare("UPDATE users SET asaas_customer_id = ? WHERE id = ?")->execute([$customerId, $userId]);
            } else {
                $msg = $customer['errors'][0]['description'] ?? 'Erro ao criar cliente.';
                echo json_encode(['success' => false, 'message' => "Erro no Asaas: $msg"]);
                exit;
            }
        }

        // Prepara Cobrança
        $billingCycleLabel = ($cycle == 'annual') ? 'Anual' : 'Mensal';
        $paymentData = [
            "customer" => $customerId,
            "billingType" => $method,
            "dueDate" => $dueDate,
            "value" => $amount,
            "description" => "Assinatura " . $billingCycleLabel . " Encurtou.pro - Plano " . $plan['name'] . $creditDescription,
            "externalReference" => "USER_".$userId."_PLAN_".$assignedPlan,
            "remoteIp" => $_SERVER['REMOTE_ADDR']
        ];

        if ($method === 'CREDIT_CARD') {
            if (!empty($_POST['creditCardToken'])) {
                $paymentData['creditCardToken'] = trim($_POST['creditCardToken']);
            } else {
                // Fallback (Temporário até atualização completa do JS do Asaas no Front)
                $expiryParts = explode('/', $_POST['expiry'] ?? '');
                $cvcValue = trim($_POST['cvc'] ?? '');
                
                $paymentData['creditCard'] = [
                    "holderName" => $billingName,
                    "number" => trim($_POST['number'] ?? ''),
                    "expiryMonth" => isset($expiryParts[0]) ? trim($expiryParts[0]) : '',
                    "expiryYear" => isset($expiryParts[1]) ? "20" . trim($expiryParts[1]) : '',
                    "ccv" => $cvcValue
                ];
            }
            $paymentData['creditCardHolderInfo'] = [
                "name" => $billingName,
                "email" => $user['email'],
                "cpfCnpj" => $cpfCnpj,
                "postalCode" => $postalCode,
                "address" => $address,
                "addressNumber" => $addressNumber,
                "mobilePhone" => $user['phone']
            ];
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, ASAAS_API_URL . "/payments");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($paymentData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json", 
            "access_token: " . ASAAS_API_KEY,
            "User-Agent: EncurtouPro"
        ]);
        $res = curl_exec($ch);
        curl_close($ch);
        $payment = json_decode($res, true);

        if (isset($payment['id'])) {
            $responseData = ['success' => true, 'paymentId' => $payment['id'], 'amount' => $amount];

            if ($method === 'PIX') {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, ASAAS_API_URL . "/payments/" . $payment['id'] . "/pixQrCode");
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    "Content-Type: application/json", 
                    "access_token: " . ASAAS_API_KEY,
                    "User-Agent: EncurtouPro"
                ]);
                $resPix = curl_exec($ch);
                curl_close($ch);
                $responseData['pix'] = json_decode($resPix, true);
            }

            $cardStatus = AsaasPaymentStateService::normalizeStatusFromPaymentStatus((string)($payment['status'] ?? ''));
            if ($method === 'CREDIT_CARD' && $cardStatus === 'CONFIRMED') {
                AsaasPaymentStateService::apply(
                    $pdo,
                    (int)$userId,
                    (string)$assignedPlan,
                    $cardStatus,
                    (string)$payment['id'],
                    (float)($payment['value'] ?? $amount),
                    (string)($payment['billingType'] ?? 'CREDIT_CARD'),
                    (string)($payment['description'] ?? ''),
                    'checkout_card_' . (string)$payment['id']
                );
                checkoutSyncPlanAutoRenew($pdo, (int)$userId);
                if ((string)$assignedPlan === 'founder') {
                    checkoutConfirmFounderSlot((int)$userId, $cycle);
                }
                $_SESSION['plan'] = $assignedPlan;
            }

            echo json_encode($responseData);
        } else {
            if ($assignedPlan === 'founder') {
                FounderService::releaseSlot($userId);
            }
            $msg = $payment['errors'][0]['description'] ?? 'Erro desconhecido ao gerar cobrança.';
            echo json_encode(['success' => false, 'message' => $msg]);
        }

    } catch (PDOException $e) {
        if ($assignedPlan === 'founder') {
            FounderService::releaseSlot($userId);
        }
        echo json_encode(['success' => false, 'message' => 'Erro interno de servidor.']);
    }
}
?>
