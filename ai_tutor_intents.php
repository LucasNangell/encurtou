<?php

function getTutorIntentLibrary(): array
{
    return [
        [
            'id' => 'tracking_campaign_setup',
            'priority' => 100,
            'patterns' => [
                '/\b(como|onde)\b.*\b(configurar|ativar|usar|habilitar)\b.*\b(pixel|pixels|tracking|rastreamento)\b/u',
                '/\b(pixel|pixels|tracking|rastreamento)\b.*\b(campanha|campanhas)\b/u',
            ],
            'response' => "Para configurar Pixels/Tracking no fluxo atual:\n"
                . "1) Acesse `Campanhas`.\n"
                . "2) No card **Pixels e Conversão da Campanha**, selecione o link da campanha.\n"
                . "3) Clique em **Ativar rastreamento**.\n"
                . "4) Cadastre os eventos e copie os snippets pelo botão de código.\n"
                . "5) Instale o script base `tracker.js` no site de destino.\n\n"
                . "Se esse bloco não aparecer ou não habilitar, seu plano pode não ter a feature `tracking` liberada.",
        ],
        [
            'id' => 'utm_campaign_setup',
            'priority' => 90,
            'patterns' => [
                '/\b(utm|campanha|campanhas)\b.*\b(como|configurar|aplicar|criar)\b/u',
                '/\b(utm_source|utm_medium|utm_campaign|utm_term|utm_content)\b/u',
            ],
            'response' => "Para configurar campanha UTM:\n"
                . "1) Vá em `Campanhas`.\n"
                . "2) Selecione/crie a campanha.\n"
                . "3) Escolha o link e preencha os campos UTM (`source`, `medium`, `campaign`, opcionalmente `term` e `content`).\n"
                . "4) Clique em **Aplicar Tags e Salvar**.\n"
                . "5) Use o painel de analytics da campanha para acompanhar resultados.",
        ],
        [
            'id' => 'create_dynamic_qr',
            'priority' => 80,
            'patterns' => [
                '/\b(como|onde)\b.*\b(criar|gerar|fazer)\b.*\b(qr|qrcode|qr code)\b/u',
                '/\b(qr|qrcode|qr code)\b.*\b(dinamico|dinâmico)\b/u',
            ],
            'response' => "Para criar QR dinâmico:\n"
                . "1) Acesse `Início` > aba **QR Codes**.\n"
                . "2) Escolha o tipo de destino (link, rede social, vCard, telefone ou e-mail).\n"
                . "3) Defina slug/subdomínio se necessário.\n"
                . "4) Clique em **Salvar QR Code**.\n"
                . "5) Baixe em PNG ou SVG.",
        ],
        [
            'id' => 'link_ab_test',
            'priority' => 75,
            'patterns' => [
                '/\b(teste a\/b|teste ab|a\/b|variantes?)\b/u',
            ],
            'response' => "Para usar Teste A/B:\n"
                . "1) Em `Início` > **Gerar Links**, ative o modo **Teste A/B**.\n"
                . "2) Cadastre as variantes com URL e peso.\n"
                . "3) Garanta que os pesos das variantes ativas somem 100%.\n"
                . "4) Gere o link e acompanhe o desempenho no analytics.\n\n"
                . "Obs.: a feature pode depender do plano (`ab_testing`).",
        ],
        [
            'id' => 'bio_pages_basics',
            'priority' => 70,
            'patterns' => [
                '/\b(como|onde)\b.*\b(criar|editar|configurar)\b.*\b(bio|bio page|biopage)\b/u',
            ],
            'response' => "Para configurar Bio Pages:\n"
                . "1) Abra `Bio Pages`.\n"
                . "2) Ajuste as abas conforme objetivo: Perfil, Templates, Links/Builder, Respostas e Design.\n"
                . "3) Salve a página e publique.\n"
                . "4) Para alterações futuras, liste a página e edite/duplique/exclua conforme necessário.",
        ],
    ];
}
