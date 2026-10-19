<?php

function getTutorKnowledgeBase(): array
{
    return [
        'global' => [
            'product' => 'Encurtou.pro',
            'scope' => 'Plataforma de links curtos, QR dinâmico, campanhas UTM, analytics, conversões/tracking, bio pages, equipe e planos.',
            'navigation' => 'Menu principal: Início, Painel de Controle, Meus Links, Meus QR Codes, Analytics, Campanhas, Bio Pages, Minha Equipe, Assinatura e Admin (apenas para admins).',
            'guardrails' => [
                'Sempre orientar com caminho de menu explícito.',
                'Quando uma funcionalidade não aparecer, considerar bloqueio por plano/permissão.',
                'Não inventar tela, botão ou fluxo que não exista no sistema atual.',
            ],
        ],
        'features' => [
            [
                'id' => 'create_link',
                'title' => 'Criar link curto',
                'keywords' => ['link', 'encurtar', 'slug', 'subdominio', 'subdomínio', 'personalizar', 'terminacao', 'terminação'],
                'where' => 'Início > aba Gerar Links',
                'details' => [
                    'Preencher URL de destino e gerar link.',
                    'Opcional: personalizar terminação (slug) e subdomínio.',
                    'Subdomínio personalizado e custom path podem depender do plano.',
                ],
            ],
            [
                'id' => 'ab_test',
                'title' => 'Teste A/B de links',
                'keywords' => ['ab', 'a/b', 'variantes', 'peso', 'teste a b'],
                'where' => 'Início > aba Gerar Links > botão Teste A/B',
                'details' => [
                    'Permite múltiplas variantes de destino com pesos.',
                    'A soma dos pesos das variantes ativas deve ser 100%.',
                    'Recurso pode depender da feature ab_testing do plano.',
                ],
            ],
            [
                'id' => 'edit_link',
                'title' => 'Editar link e deep linking',
                'keywords' => ['editar link', 'ios', 'android', 'windows', 'deep link', 'destino por dispositivo'],
                'where' => 'Meus Links > expandir item > aba Editar',
                'details' => [
                    'Atualiza URL padrão e destinos por dispositivo (iOS/Android/Windows).',
                    'Também permite pausar/ativar e excluir o link na listagem.',
                ],
            ],
            [
                'id' => 'rules',
                'title' => 'Regras de validade e gatilho',
                'keywords' => ['regras', 'expirar', 'agendar', 'fallback', 'gatilho', 'trigger', 'expiração'],
                'where' => 'Meus Links > expandir item > aba Regras',
                'details' => [
                    'Configura início/fim, expiração por cliques e URL de fallback.',
                    'Pode trocar destino automaticamente após X cliques.',
                ],
            ],
            [
                'id' => 'create_qr',
                'title' => 'Criar QR dinâmico',
                'keywords' => ['qr', 'qrcode', 'qr code', 'vcard', 'telefone', 'email', 'rede social', 'social'],
                'where' => 'Início > aba QR Codes',
                'details' => [
                    'QR pode ser de link, rede social, vCard, telefone ou e-mail.',
                    'Suporta customização visual (cor, módulos, cantos, logo) e download PNG/SVG.',
                    'Destino do QR pode ser editado depois sem trocar o slug.',
                ],
            ],
            [
                'id' => 'my_qr',
                'title' => 'Gerenciar QR Codes',
                'keywords' => ['meus qr', 'editar qr', 'qr dinamico', 'qr dinâmico'],
                'where' => 'Meus QR Codes > expandir item',
                'details' => [
                    'Permite editar destino dinâmico do QR existente.',
                    'Permite ver analytics, pausar/ativar e excluir.',
                ],
            ],
            [
                'id' => 'campaigns_utm',
                'title' => 'Campanhas e UTM',
                'keywords' => ['campanha', 'campanhas', 'utm', 'source', 'medium', 'term', 'content', 'bulk utm'],
                'where' => 'Campanhas',
                'details' => [
                    'Cria campanha e aplica parâmetros UTM em links.',
                    'Lista campanhas e abre analytics por campanha.',
                    'Acesso pode depender da feature bulk_utm.',
                ],
            ],
            [
                'id' => 'tracking_campaign',
                'title' => 'Pixels e conversão (tracking)',
                'keywords' => ['pixel', 'pixels', 'tracking', 'rastreamento', 'conversao', 'conversão', 'evento'],
                'where' => 'Campanhas > card "Pixels e Conversão da Campanha"',
                'details' => [
                    'Seleciona um link da campanha e ativa/desativa rastreamento.',
                    'Permite cadastrar eventos de conversão e copiar snippets.',
                    'Mostra script base tracker.js para instalação no site de destino.',
                    'Se não aparecer/funcionar, verificar bloqueio de plano na feature tracking.',
                ],
            ],
            [
                'id' => 'analytics',
                'title' => 'Analytics e insights',
                'keywords' => ['analytics', 'insights', 'cliques', 'conversao', 'conversão', 'origem', 'dispositivo', 'cidade', 'pais', 'país'],
                'where' => 'Analytics e Painel de Controle',
                'details' => [
                    'Exibe métricas de cliques/conversões, tendência, origem, geolocalização e dispositivos.',
                    'Permite filtros por período e por link/campanha em áreas específicas.',
                ],
            ],
            [
                'id' => 'bio_pages',
                'title' => 'Bio Pages',
                'keywords' => ['bio', 'biopage', 'microsite', 'template', 'design', 'builder', 'blocos', 'pix', 'whatsapp', 'formulario', 'formulário'],
                'where' => 'Bio Pages',
                'details' => [
                    'Abas principais: Perfil, Templates, Links/Builder, Respostas e Design.',
                    'Permite salvar, listar, duplicar, excluir e publicar páginas.',
                    'Pode incluir blocos de links, WhatsApp, Pix e formulários.',
                ],
            ],
            [
                'id' => 'team',
                'title' => 'Minha Equipe',
                'keywords' => ['equipe', 'time', 'membro', 'convite', 'owner'],
                'where' => 'Minha Equipe',
                'details' => [
                    'Permite convidar membro por e-mail, listar e remover membros.',
                    'Limites de equipe dependem do plano.',
                ],
            ],
            [
                'id' => 'plans_subscription',
                'title' => 'Assinatura e limites',
                'keywords' => ['plano', 'assinatura', 'upgrade', 'limite', 'quota', 'feature', 'recurso premium'],
                'where' => 'Assinatura',
                'details' => [
                    'Mostra plano atual e limites de links, QR, bio pages e subdomínios.',
                    'Controla liberação de features como analytics, bulk_utm, tracking, custom_domain e outras.',
                ],
            ],
            [
                'id' => 'auth',
                'title' => 'Acesso e autenticação',
                'keywords' => ['login', 'cadastro', 'senha', 'google', 'logout', 'recuperar senha'],
                'where' => 'Fluxo de autenticação',
                'details' => [
                    'Cadastro e login por e-mail/senha.',
                    'Login com Google e recuperação de senha por código.',
                ],
            ],
            [
                'id' => 'admin',
                'title' => 'Área Admin',
                'keywords' => ['admin', 'planos', 'override', 'configuração', 'configuracao'],
                'where' => 'Admin (somente para usuários administradores)',
                'details' => [
                    'Gerencia planos, configurações e overrides de conta.',
                    'Se usuário comum tentar acessar, a API bloqueia por permissão.',
                ],
            ],
        ],
    ];
}
