<?php
require_once __DIR__ . '/../config_asaas.php';

class AsaasRefundService
{
    private static function request(string $method, string $path, ?array $payload = null): array
    {
        $ch = curl_init();
        $url = ASAAS_API_URL . $path;
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'access_token: ' . ASAAS_API_KEY,
            'User-Agent: EncurtouPro'
        ]);

        if (is_array($payload)) {
            $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $encoded !== false ? $encoded : '{}');
        }

        $raw = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
        return [
            'http_code' => $httpCode,
            'raw' => $raw,
            'data' => $decoded
        ];
    }

    public static function getLatestRefundablePaymentByCustomer(string $customerId): ?array
    {
        $customerId = trim($customerId);
        if ($customerId === '') {
            return null;
        }

        $query = '/payments?customer=' . rawurlencode($customerId) . '&limit=50&offset=0';
        $res = self::request('GET', $query, null);
        if ($res['http_code'] < 200 || $res['http_code'] >= 300 || !is_array($res['data'])) {
            return null;
        }

        $rows = $res['data']['data'] ?? [];
        if (!is_array($rows) || empty($rows)) {
            return null;
        }

        $validStatuses = ['CONFIRMED', 'RECEIVED', 'RECEIVED_IN_CASH'];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $status = strtoupper((string)($row['status'] ?? ''));
            $value = (float)($row['value'] ?? 0);
            $paymentId = (string)($row['id'] ?? '');
            if ($paymentId !== '' && $value > 0 && in_array($status, $validStatuses, true)) {
                return [
                    'asaas_payment_id' => $paymentId,
                    'amount' => $value,
                    'paid_ts' => $row['paymentDate'] ?? ($row['confirmedDate'] ?? ($row['dateCreated'] ?? null)),
                ];
            }
        }

        return null;
    }

    public static function refundPayment(string $paymentId, float $amount, string $reason): array
    {
        $paymentId = trim($paymentId);
        if ($paymentId === '') {
            return [
                'success' => false,
                'http_code' => 0,
                'message' => 'Pagamento inválido para estorno.',
                'response' => null,
            ];
        }

        $payload = json_encode([
            'value' => round(max(0, $amount), 2),
            'description' => substr(trim($reason), 0, 250),
        ], JSON_UNESCAPED_UNICODE);

        if ($payload === false) {
            return [
                'success' => false,
                'http_code' => 0,
                'message' => 'Falha ao montar payload de estorno.',
                'response' => null,
            ];
        }

        $decodedPayload = json_decode($payload, true);
        $res = self::request('POST', '/payments/' . rawurlencode($paymentId) . '/refund', is_array($decodedPayload) ? $decodedPayload : []);
        $httpCode = (int)($res['http_code'] ?? 0);
        $decoded = is_array($res['data']) ? $res['data'] : null;

        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'success' => true,
                'http_code' => $httpCode,
                'message' => 'Estorno solicitado com sucesso.',
                'response' => $decoded,
            ];
        }

        $err = $decoded['errors'][0]['description'] ?? 'Não foi possível solicitar o estorno no Asaas.';
        return [
            'success' => false,
            'http_code' => $httpCode,
            'message' => (string)$err,
            'response' => $decoded,
        ];
    }
}
