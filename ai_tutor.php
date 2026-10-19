<?php
header('Content-Type: application/json');
require_once 'db.php';
require_once 'ai_tutor_context.php';
require_once 'ai_tutor_intents.php';
require_once 'billing/EntitlementService.php';

function tutorBuildGeminiEndpoint(string $model): string
{
    return 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent';
}

function tutorCallGemini(string $prompt): ?string
{
    $apiKey = envOrDefault('GEMINI_API_KEY', '');
    $model = envOrDefault('GEMINI_MODEL', 'gemini-1.5-flash');
    if ($apiKey === '' || $model === '') {
        return null;
    }

    $endpoint = tutorBuildGeminiEndpoint($model) . '?key=' . rawurlencode($apiKey);
    $payload = json_encode([
        'contents' => [[
            'parts' => [['text' => $prompt]]
        ]],
        'generationConfig' => [
            'temperature' => 0.2,
            'maxOutputTokens' => 900
        ]
    ], JSON_UNESCAPED_UNICODE);

    if ($payload === false) {
        return null;
    }

    $raw = null;
    if (function_exists('curl_init')) {
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT => 12
        ]);
        $raw = curl_exec($ch);
        curl_close($ch);
    } else {
        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => $payload,
                'timeout' => 12
            ]
        ]);
        $raw = @file_get_contents($endpoint, false, $ctx);
    }

    if (!is_string($raw) || $raw === '') {
        return null;
    }

    $data = json_decode($raw, true);
    $text = trim((string)($data['candidates'][0]['content']['parts'][0]['text'] ?? ''));
    return $text !== '' ? $text : null;
}

function tutorNormalizeQuestion(string $question): string
{
    $value = trim(mb_strtolower($question, 'UTF-8'));
    $value = preg_replace('/\s+/u', ' ', $value);
    return (string)$value;
}

function tutorPickRelevantFeatureDocs(string $question, array $knowledgeBase): array
{
    $normalized = tutorNormalizeQuestion($question);
    $scores = [];
    $features = $knowledgeBase['features'] ?? [];
    foreach ($features as $index => $feature) {
        if (!is_array($feature)) {
            continue;
        }
        $title = mb_strtolower((string)($feature['title'] ?? ''), 'UTF-8');
        $where = mb_strtolower((string)($feature['where'] ?? ''), 'UTF-8');
        $score = 1;
        foreach (($feature['keywords'] ?? []) as $keyword) {
            $k = mb_strtolower((string)$keyword, 'UTF-8');
            if ($k !== '' && mb_strpos($normalized, $k) !== false) {
                $score += 3;
            }
        }
        if ($title !== '' && mb_strpos($normalized, $title) !== false) {
            $score += 4;
        }
        if ($where !== '' && mb_strpos($normalized, $where) !== false) {
            $score += 2;
        }
        $scores[$index] = $score;
    }

    if (mb_strpos($normalized, 'pixel') !== false || mb_strpos($normalized, 'tracking') !== false || mb_strpos($normalized, 'rastreamento') !== false) {
        foreach ($features as $index => $feature) {
            if (($feature['id'] ?? '') === 'tracking_campaign') {
                $scores[$index] = ($scores[$index] ?? 1) + 30;
            }
        }
    }

    arsort($scores);
    $selectedIndexes = array_slice(array_keys($scores), 0, 7);
    $docs = [];
    foreach ($selectedIndexes as $idx) {
        if (isset($features[$idx]) && is_array($features[$idx])) {
            $docs[] = $features[$idx];
        }
    }
    return $docs;
}

function tutorFormatFeatureDocsForPrompt(array $docs): string
{
    if (empty($docs)) {
        return '- Sem bloco específico encontrado.';
    }
    $parts = [];
    foreach ($docs as $feature) {
        $title = (string)($feature['title'] ?? 'Funcionalidade');
        $where = (string)($feature['where'] ?? 'Não informado');
        $details = $feature['details'] ?? [];
        $body = [];
        if (is_array($details)) {
            foreach ($details as $d) {
                $line = trim((string)$d);
                if ($line !== '') {
                    $body[] = $line;
                }
            }
        }
        $parts[] = "[{$title}] Onde: {$where}. Detalhes: " . implode(' ', $body);
    }
    return '- ' . implode("\n- ", $parts);
}

function tutorGetSharedCachePath(): string
{
    return __DIR__ . '/uploads/ai_tutor_cache.json';
}

function tutorLoadSharedCache(): array
{
    $path = tutorGetSharedCachePath();
    if (!is_file($path)) {
        return [];
    }
    $raw = @file_get_contents($path);
    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function tutorSaveSharedCache(array $cache): void
{
    $path = tutorGetSharedCachePath();
    $json = json_encode($cache, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($json === false) {
        return;
    }
    @file_put_contents($path, $json, LOCK_EX);
}

function tutorCacheKey(string $normalizedQuestion, string $knowledgeVersion): string
{
    return hash('sha256', $knowledgeVersion . '|' . $normalizedQuestion);
}

function tutorGetCachedAnswer(string $normalizedQuestion, string $knowledgeVersion): ?string
{
    $cache = tutorLoadSharedCache();
    $key = tutorCacheKey($normalizedQuestion, $knowledgeVersion);
    $entry = $cache[$key] ?? null;
    if (!is_array($entry)) {
        return null;
    }
    $answer = trim((string)($entry['answer'] ?? ''));
    return $answer !== '' ? $answer : null;
}

function tutorPutCachedAnswer(string $normalizedQuestion, string $knowledgeVersion, string $answer): void
{
    $answer = trim($answer);
    if ($answer === '') {
        return;
    }
    $cache = tutorLoadSharedCache();
    $key = tutorCacheKey($normalizedQuestion, $knowledgeVersion);
    $cache[$key] = [
        'answer' => $answer,
        'updated_at' => date('c'),
    ];
    // Limite simples para evitar crescimento sem controle
    if (count($cache) > 400) {
        $cache = array_slice($cache, -300, null, true);
    }
    tutorSaveSharedCache($cache);
}

function tutorMatchIntent(string $question): ?array
{
    $library = getTutorIntentLibrary();
    $best = null;
    $bestScore = -1;

    foreach ($library as $intent) {
        if (!is_array($intent)) {
            continue;
        }
        $patterns = $intent['patterns'] ?? [];
        $priority = (int)($intent['priority'] ?? 0);
        $matched = 0;
        foreach ($patterns as $regex) {
            if (@preg_match($regex, $question) === 1) {
                $matched++;
            }
        }
        if ($matched <= 0) {
            continue;
        }
        $score = $priority + ($matched * 10);
        if ($score > $bestScore) {
            $bestScore = $score;
            $best = $intent;
        }
    }

    if (!is_array($best)) {
        return null;
    }
    $response = trim((string)($best['response'] ?? ''));
    if ($response === '') {
        return null;
    }
    return [
        'id' => (string)($best['id'] ?? ''),
        'response' => $response,
    ];
}

function tutorIsOutOfScope(string $question): bool
{
    $q = tutorNormalizeQuestion($question);
    $systemTerms = [
        'encurtou', 'link', 'qr', 'bio', 'campanha', 'utm', 'analytics',
        'plano', 'assinatura', 'subdominio', 'subdomínio', 'equipe',
        'cadastro', 'login', 'senha', 'dashboard', 'pixel', 'pixels',
        'tracking', 'rastreamento', 'conversao', 'conversão'
    ];
    foreach ($systemTerms as $term) {
        if (mb_strpos($q, $term) !== false) {
            return false;
        }
    }
    return true;
}

function tutorGetChatHistory(): array
{
    $history = $_SESSION['ai_tutor_history'] ?? [];
    if (!is_array($history)) {
        return [];
    }
    $normalized = [];
    foreach ($history as $item) {
        if (!is_array($item)) {
            continue;
        }
        $role = (string)($item['role'] ?? '');
        $text = trim((string)($item['text'] ?? ''));
        if (($role === 'user' || $role === 'assistant') && $text !== '') {
            $normalized[] = ['role' => $role, 'text' => $text];
        }
    }
    return $normalized;
}

function tutorPushHistory(string $role, string $text): void
{
    if ($text === '' || ($role !== 'user' && $role !== 'assistant')) {
        return;
    }
    if (!isset($_SESSION['ai_tutor_history']) || !is_array($_SESSION['ai_tutor_history'])) {
        $_SESSION['ai_tutor_history'] = [];
    }
    $_SESSION['ai_tutor_history'][] = ['role' => $role, 'text' => $text];
    if (count($_SESSION['ai_tutor_history']) > 16) {
        $_SESSION['ai_tutor_history'] = array_slice($_SESSION['ai_tutor_history'], -16);
    }
}

function tutorFormatHistoryForPrompt(array $history): string
{
    if (empty($history)) {
        return 'Sem histórico anterior.';
    }
    $slice = array_slice($history, -10);
    $lines = [];
    foreach ($slice as $item) {
        $prefix = $item['role'] === 'assistant' ? 'Tutor' : 'Usuário';
        $lines[] = $prefix . ': ' . $item['text'];
    }
    return implode("\n", $lines);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

$requireAuth = envOrDefault('AI_TUTOR_REQUIRE_AUTH', isLocalEnvironment() ? '0' : '1') === '1';
if ($requireAuth && !isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Você precisa estar logado para usar o Tutor IA.']);
    exit;
}

// Para usuários autenticados, exige CSRF com fallback same-origin para manter
// compatibilidade com fluxos já existentes no frontend.
if (isset($_SESSION['user_id'])) {
    requireCsrfToken(false);
}

if (isset($_SESSION['user_id'])) {
    try {
        if (!EntitlementService::canAccessFeature((int)$_SESSION['user_id'], 'ai_tutor')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'feature_locked', 'message' => 'Seu plano atual não permite acesso ao Tutor IA.']);
            exit;
        }
    } catch (Throwable $e) {
        // Ambientes sem esquema de billing: mantém tutor disponível.
    }
}

$action = trim((string)($_POST['action'] ?? ''));
if ($action === 'reset_context') {
    unset($_SESSION['ai_tutor_history']);
    echo json_encode(['success' => true, 'message' => 'Contexto do tutor resetado.']);
    exit;
}

$question = trim((string)($_POST['question'] ?? ''));
if ($question === '') {
    echo json_encode(['success' => false, 'message' => 'Envie uma pergunta.']);
    exit;
}
if (mb_strlen($question, 'UTF-8') > 450) {
    echo json_encode(['success' => false, 'message' => 'Pergunta muito longa. Resuma em até 450 caracteres.']);
    exit;
}

if (!isset($_SESSION['ai_tutor_req_count'])) {
    $_SESSION['ai_tutor_req_count'] = 0;
}
$_SESSION['ai_tutor_req_count'] = (int)$_SESSION['ai_tutor_req_count'] + 1;
if ($_SESSION['ai_tutor_req_count'] > 60) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Muitas solicitações ao Tutor IA. Tente novamente em alguns minutos.']);
    exit;
}

if (tutorIsOutOfScope($question)) {
    echo json_encode([
        'success' => true,
        'answer' => 'Eu ajudo apenas com dúvidas sobre o uso do Encurtou.pro (links, QR, Bio Pages, campanhas, analytics, planos e equipe). Envie sua dúvida sobre alguma dessas funcionalidades.'
    ]);
    exit;
}

$knowledgeBase = getTutorKnowledgeBase();
$knowledgeVersion = 'v3';
$normalizedQuestion = tutorNormalizeQuestion($question);

// 1) Cache compartilhado: resposta idêntica para pergunta normalizada idêntica
$cached = tutorGetCachedAnswer($normalizedQuestion, $knowledgeVersion);
if (is_string($cached) && $cached !== '') {
    tutorPushHistory('user', $question);
    tutorPushHistory('assistant', $cached);
    echo json_encode(['success' => true, 'answer' => $cached, 'source' => 'cache']);
    exit;
}

// 2) Intent library: respostas canônicas para dúvidas recorrentes com alta precisão
$intent = tutorMatchIntent($question);
if (is_array($intent)) {
    $intentAnswer = (string)$intent['response'];
    tutorPushHistory('user', $question);
    tutorPushHistory('assistant', $intentAnswer);
    tutorPutCachedAnswer($normalizedQuestion, $knowledgeVersion, $intentAnswer);
    echo json_encode(['success' => true, 'answer' => $intentAnswer, 'source' => 'intent']);
    exit;
}

// 3) Fallback IA: usa contexto detalhado + histórico
$global = $knowledgeBase['global'] ?? [];
$globalText = "Produto: " . (string)($global['product'] ?? 'Encurtou.pro') . ". "
    . "Escopo: " . (string)($global['scope'] ?? '') . " "
    . "Navegação: " . (string)($global['navigation'] ?? '');
$guardrails = $global['guardrails'] ?? [];
$guardrailsText = '';
if (is_array($guardrails) && !empty($guardrails)) {
    $guardrailsText = "- " . implode("\n- ", array_map(static fn($v) => (string)$v, $guardrails));
}
$featureDocs = tutorPickRelevantFeatureDocs($question, $knowledgeBase);
$featureDocsText = tutorFormatFeatureDocsForPrompt($featureDocs);
$history = tutorGetChatHistory();
$historyText = tutorFormatHistoryForPrompt($history);
$prompt = "Você é o Tutor IA do Encurtou.pro.\n"
    . "Responda SOMENTE perguntas sobre como usar funcionalidades do sistema.\n"
    . "Nunca negue uma funcionalidade se ela estiver descrita nos blocos de funcionalidade abaixo.\n"
    . "Se houver dúvida, responda com cautela e oriente o caminho dentro do sistema.\n"
    . "Se o usuário disser que não encontrou um recurso, ofereça checklist de diagnóstico (onde clicar, bloqueio de plano/permissão e seção correta).\n"
    . "Se a pergunta fugir desse tema, recuse brevemente e peça para reformular.\n"
    . "Não invente funcionalidades.\n"
    . "Resposta em português-BR, objetiva e completa, preferindo frases curtas e claras.\n"
    . "Quando explicar passos, use caminho de menu explícito.\n\n"
    . "Contexto global do sistema:\n" . $globalText . "\n\n"
    . "Regras operacionais:\n" . ($guardrailsText !== '' ? $guardrailsText : '- Sem regras adicionais.') . "\n\n"
    . "Blocos de funcionalidade relevantes:\n" . $featureDocsText . "\n\n"
    . "Histórico recente do chat ativo:\n" . $historyText . "\n\n"
    . "Pergunta do usuário: " . $question;

tutorPushHistory('user', $question);
$answer = tutorCallGemini($prompt);
if (!is_string($answer) || trim($answer) === '') {
    echo json_encode([
        'success' => true,
        'answer' => 'No momento não consegui processar sua dúvida. Tente novamente em instantes. Posso ajudar com links, QR Codes, Bio Pages, campanhas e analytics.'
    ]);
    exit;
}

$answer = trim($answer);
tutorPushHistory('assistant', $answer);
tutorPutCachedAnswer($normalizedQuestion, $knowledgeVersion, $answer);
echo json_encode(['success' => true, 'answer' => $answer, 'source' => 'ai']);
