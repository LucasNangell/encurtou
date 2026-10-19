<?php
header('Content-Type: application/json');
require_once 'db.php';
require_once 'billing/EntitlementService.php';

/*
 * api_bio_templates.php
 * Scans the templates/bio/ directory and returns metadata + thumbnail HTML.
 */

$templatesDir = 'templates/bio';
$results = [];
$templateEnrichment = [
    1 => [
        'category' => 'Creator',
        'description' => 'Estrutura central com foco em marca pessoal e links de conversao.',
        'ideal_for' => 'Criadores, experts e consultores',
        'page_types' => ['bio', 'landing'],
        'premium' => false,
    ],
    2 => [
        'category' => 'Premium',
        'description' => 'Template refinado para paginas mais comerciais e premium.',
        'ideal_for' => 'Captacao, lancamentos e vitrines premium',
        'page_types' => ['bio', 'landing', 'campaign'],
        'premium' => true,
    ],
    3 => [
        'category' => 'Editorial',
        'description' => 'Visual forte para experts, conteudo e mini-sites.',
        'ideal_for' => 'Autoridade, conteudo e campanhas',
        'page_types' => ['landing', 'campaign', 'blog'],
        'premium' => true,
    ],
    4 => [
        'category' => 'Commerce',
        'description' => 'Blocos em grade para catalogos, menus e galerias de links.',
        'ideal_for' => 'Lojas, catalogos e vitrines',
        'page_types' => ['bio', 'landing', 'campaign'],
        'premium' => false,
    ],
    5 => [
        'category' => 'Institucional',
        'description' => 'Cara de negocio local, servicos e paginas institucionais.',
        'ideal_for' => 'Empresas, servicos e atendimento local',
        'page_types' => ['landing', 'campaign'],
        'premium' => false,
    ],
    6 => [
        'category' => 'Impacto',
        'description' => 'Template com profundidade e destaque para CTAs.',
        'ideal_for' => 'Ofertas, campanhas e lancamentos',
        'page_types' => ['landing', 'campaign'],
        'premium' => true,
    ],
    7 => [
        'category' => 'Bold',
        'description' => 'Linguagem visual intensa para creators e campanhas.',
        'ideal_for' => 'Creators, eventos e campanhas',
        'page_types' => ['bio', 'campaign'],
        'premium' => false,
    ],
    8 => [
        'category' => 'Soft UI',
        'description' => 'Estetica clean para portifolio, branding e servicos criativos.',
        'ideal_for' => 'Portifolio e design',
        'page_types' => ['bio', 'landing'],
        'premium' => false,
    ],
    9 => [
        'category' => 'Noir',
        'description' => 'Minimalismo autoral para conteudo, blog e posicionamento.',
        'ideal_for' => 'Blog, conteudo e autoridade',
        'page_types' => ['bio', 'blog'],
        'premium' => true,
    ],
    10 => [
        'category' => 'Conversão',
        'description' => 'Foco em coleta de respostas com formulário integrado à Bio Page.',
        'ideal_for' => 'Pesquisas rápidas, cadastros, check-ins e leads',
        'page_types' => ['bio', 'landing', 'campaign'],
        'premium' => false,
    ],
    11 => [
        'category' => 'Executivo',
        'description' => 'Template premium para presença institucional e proposta de valor clara.',
        'ideal_for' => 'Consultorias, escritórios e negócios B2B',
        'page_types' => ['bio', 'landing', 'campaign'],
        'premium' => true,
    ],
    12 => [
        'category' => 'Portfólio',
        'description' => 'Layout elegante para destacar projetos, serviços e provas visuais.',
        'ideal_for' => 'Criadores, designers e estúdios',
        'page_types' => ['bio', 'landing', 'blog'],
        'premium' => false,
    ],
    13 => [
        'category' => 'Produto',
        'description' => 'Estrutura focada em conversão para ofertas e campanhas de venda.',
        'ideal_for' => 'Lançamentos, infoprodutos e campanhas comerciais',
        'page_types' => ['landing', 'campaign'],
        'premium' => true,
    ],
    14 => [
        'category' => 'Minimal',
        'description' => 'Visual clean com capa suave e área de perfil. Foto recomendada: capa 16:9 e perfil 1:1.',
        'ideal_for' => 'Designers, criadores e marca pessoal',
        'page_types' => ['bio', 'landing'],
        'premium' => true,
    ],
    15 => [
        'category' => 'Jurídico',
        'description' => 'Template elegante para presença profissional. Foto recomendada: perfil 1:1.',
        'ideal_for' => 'Advocacia, consultoria e serviços premium',
        'page_types' => ['bio', 'landing', 'campaign'],
        'premium' => true,
    ],
    16 => [
        'category' => 'Impacto',
        'description' => 'Modelo de alto contraste para campanhas fortes. Foto recomendada: perfil 1:1.',
        'ideal_for' => 'Campanhas, lançamentos e creators',
        'page_types' => ['bio', 'campaign', 'landing'],
        'premium' => true,
    ],
    17 => [
        'category' => 'Editorial',
        'description' => 'Estética sofisticada com foco em imagem e serviços. Foto recomendada: capa 4:5.',
        'ideal_for' => 'Beleza, moda, estética e branding',
        'page_types' => ['bio', 'landing'],
        'premium' => true,
    ],
    18 => [
        'category' => 'Creator',
        'description' => 'Template vibrante com identidade digital. Foto recomendada: capa 16:9 e perfil 1:1.',
        'ideal_for' => 'Marketing, social media e creators',
        'page_types' => ['bio', 'landing', 'campaign'],
        'premium' => true,
    ],
];

$premiumFeatureUnlocked = null;
if (isset($_SESSION['user_id'])) {
    try {
        $premiumFeatureUnlocked = EntitlementService::canAccessFeature((int)$_SESSION['user_id'], 'premium_bio_templates');
    } catch (Throwable $e) {
        $premiumFeatureUnlocked = false;
    }
}

if (is_dir($templatesDir)) {
    $folders = array_diff(scandir($templatesDir), ['.', '..']);
    
    foreach ($folders as $folder) {
        $path = $templatesDir . '/' . $folder;
        if (is_dir($path)) {
            $configFile = $path . '/config.json';
            $thumbFile = $path . '/thumb.html';
            
            if (file_exists($configFile)) {
                $config = json_decode(file_get_contents($configFile), true);
                if ($config) {
                    $configId = (int) ($config['id'] ?? 0);
                    $enrichment = $templateEnrichment[$configId] ?? [];

                    // Provide folder name for path identification
                    $config['folder'] = $folder;
                    
                    // Use slug from config if defined, else fallback to folder name
                    if (!isset($config['slug'])) {
                        $config['slug'] = $folder;
                    }
                    
                    // Load thumbnail if exists
                    if (file_exists($thumbFile)) {
                        $config['thumb_html'] = file_get_contents($thumbFile);
                    } else {
                        $config['thumb_html'] = '<div class="tpl-thumb"><span>IMG</span></div>';
                    }

                    $config['category'] = $config['category'] ?? ($enrichment['category'] ?? 'Visual');
                    $config['description'] = $config['description'] ?? ($enrichment['description'] ?? 'Template visual para Bio Pages.');
                    $config['ideal_for'] = $config['ideal_for'] ?? ($enrichment['ideal_for'] ?? 'Uso geral');
                    $config['page_types'] = $config['page_types'] ?? ($enrichment['page_types'] ?? ['bio']);
                    $config['premium'] = isset($config['premium']) ? (bool) $config['premium'] : (bool) ($enrichment['premium'] ?? false);
                    $config['locked_by_plan'] = ($premiumFeatureUnlocked === null)
                        ? false
                        : ((bool)$config['premium'] && !$premiumFeatureUnlocked);
                    
                    $results[] = $config;
                }
            }
        }
    }
}

// Sort by ID to maintain logical order
usort($results, function($a, $b) {
    return ($a['id'] ?? 99) - ($b['id'] ?? 99);
});

echo json_encode([
    'success' => true,
    'templates_path' => $templatesDir,
    'templates' => $results
]);
