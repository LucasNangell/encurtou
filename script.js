// --- VARIÁVEIS GLOBAIS ---
const domain = window.location.hostname;
const protocol = window.location.protocol; // Detecta http ou https
let isLoggedIn = false;
let currentEmailVerified = true;
let captchaSiteKey = '';
let userFeatures = {}; // Permissões do plano do usuário
let userUsageData = null;
let charts = {};
let myLinksData = []; // Armazena os links para acesso fácil via ID
console.log("LinkNeo Dashboard JS carregado");
let currentBioPage = { id: null, links: [], template_id: 1, theme: {} };
let analyticsRefreshTimer = null;
let pendingSectionAfterLogin = null;
const guestFallbackSection = 'section-home';
let csrfToken = null;
let googleClientId = null;
let forgotResendTimer = null;
let forgotResendRemainingSeconds = 0;
let currentUserIsAdmin = false;
let currentUserIsMasterAdminDashboard = false;
let currentUserIsAffiliate = false;
let currentAffiliateLink = '';
let currentTeamProfile = 'editor';
let currentTeamCanManageMembers = false;
let currentTeamCanEditContent = true;
let currentAdminAffiliateId = 0;
let dashboardAIRequested = false;
let campaignTrackingLinksCache = [];
let campaignLinksDetailsCache = [];
let campaignLinksFilterText = '';
let campaignLinksCompactMode = false;
let campaignOpenPixelLinkId = null;
let adminGlobalUsersCache = [];
let adminUserLinksModalUserId = 0;
let adminUserLinksSearchTimer = null;
const AFFILIATE_TRACK_COOKIE = 'enc_aff_code';
const AFFILIATE_TRACK_STORAGE = 'enc_aff_code';
const featureSectionMap = {
    analytics: 'section-analytics',
    bulk_utm: 'section-campaigns',
    bio_pages: 'section-biopages'
};
const sectionFeatureMap = {
    'section-analytics': 'analytics',
    'section-campaigns': 'bulk_utm',
    'section-biopages': 'bio_pages'
};
const featureDisplayLabels = {
    analytics: 'Analytics Detalhado',
    custom_domain: 'Domínios Customizados',
    deep_linking: 'Deep Linking (Apps)',
    bulk_utm: 'UTM em Massa',
    tracking: 'Tracking Interno e Retargeting',
    dwell_monitor: 'Monitoramento de Permanência',
    ab_testing: 'Testes A/B',
    rules: 'Regras de Agendamento',
    bio_pages: 'Páginas de Bio',
    custom_path: 'Terminação Personalizada',
    redirect: 'Redirecionamento sem recriar',
    team: 'Colaboração em Equipe',
    priority_support: 'Suporte Prioritário',
    folders_tags: 'Pastas e Tags',
    social_preview: 'Preview Social',
    premium_bio_templates: 'Templates Premium de Bio',
    bio_form_exports: 'Exportação de Formulários da Bio',
    ai_tutor: 'Tutor IA'
};
const RETARGETING_PLATFORMS = [
    { key: 'facebook', label: 'Facebook Pixel', placeholder: 'Ex: 123456789012345' },
    { key: 'google_ads', label: 'Google Ads', placeholder: 'Ex: AW-123456789' },
    { key: 'linkedin', label: 'LinkedIn Insight', placeholder: 'Ex: 1234567' },
    { key: 'twitter', label: 'Twitter/X Pixel', placeholder: 'Ex: o1234' },
    { key: 'pinterest', label: 'Pinterest Tag', placeholder: 'Ex: 2612345678901' },
    { key: 'tiktok', label: 'TikTok Pixel', placeholder: 'Ex: C3A1BC2DEFGHIJKLMN0' }
];
const authRequiredSections = [
    'section-mylinks',
    'section-myqrs',
    'section-analytics',
    'section-campaigns',
    'section-account-settings',
    'section-admin',
    'section-affiliate'
];
let tutorSending = false;
let linksFoldersByType = { link: [], qr: [] };
let linksAvailableTagsByType = { link: [], qr: [] };
let listFiltersState = {
    link: { createdFrom: '', createdTo: '', status: 'all', tagIds: [] },
    qr: { createdFrom: '', createdTo: '', status: 'all', tagIds: [] }
};
let openFolderState = {};
let tagEditorContext = { id: 0, type: 'link' };
const THEME_STORAGE_KEY = 'enc_theme_preference';
let manualThemePreference = false;
let themeMediaQuery = null;
let themeInlineObserver = null;

function isTeamViewerProfile() {
    return String(currentTeamProfile || '').toLowerCase() === 'viewer';
}

function canCurrentUserEditContent() {
    return Boolean(currentTeamCanEditContent) || !isLoggedIn;
}

function canCurrentUserManageMembers() {
    return Boolean(currentTeamCanManageMembers);
}

function showViewerPermissionMessage(customMessage = '') {
    const message = customMessage || 'Seu perfil na equipe é Visualizador e não permite esta ação.';
    if (typeof Swal !== 'undefined' && Swal.fire) {
        Swal.fire('Ação bloqueada', message, 'warning');
    } else {
        alert(message);
    }
}

function guardTeamEditPermission(customMessage = '') {
    if (canCurrentUserEditContent()) return true;
    showViewerPermissionMessage(customMessage);
    return false;
}

function applyTeamRoleUiRestrictions() {
    const isViewer = isLoggedIn && !canCurrentUserEditContent();
    document.body.classList.toggle('team-viewer-mode', isViewer);

    const selectors = [
        '#btnMain',
        '#btnSaveQR',
        '#btnSaveBio',
        '#btnInviteMember',
        '#inviteEmail'
    ];
    selectors.forEach((selector) => {
        const el = document.querySelector(selector);
        if (!el) return;
        if (isViewer) {
            el.setAttribute('disabled', 'disabled');
            if (el.tagName.toLowerCase() === 'button') {
                el.title = 'Seu perfil de Visualizador não permite editar.';
            }
        } else {
            el.removeAttribute('disabled');
        }
    });

    const clickableActions = [
        "button[onclick*=\"addBioLink\"]",
        "button[onclick*=\"addSpecialBlock\"]",
        "button[onclick*=\"openAddSubdomainModal\"]",
        "button[onclick*=\"createCampaign\"]",
        "button[onclick*=\"applyBulkUTM\"]"
    ];
    clickableActions.forEach((selector) => {
        document.querySelectorAll(selector).forEach((el) => {
            if (isViewer) {
                el.setAttribute('disabled', 'disabled');
                el.title = 'Seu perfil de Visualizador não permite editar.';
            } else {
                el.removeAttribute('disabled');
            }
        });
    });
}

function isFeatureLocked(featureName) {
    return userFeatures[featureName] === false;
}

function renderSectionDemoBanner(sectionId, featureName) {
    const sectionEl = document.getElementById(sectionId);
    if (!sectionEl) return;
    const label = featureDisplayLabels[featureName] || 'este recurso';
    const existing = sectionEl.querySelector('.feature-demo-banner');
    if (existing) existing.remove();
    const banner = document.createElement('div');
    banner.className = 'feature-demo-banner';
    banner.innerHTML = `
        <div class="feature-demo-banner-content">
            <i class="fas fa-flask"></i>
            <div>
                <strong>Modo demonstração ativo</strong>
                <p>Você está visualizando dados fictícios de ${label}. Faça upgrade para liberar dados reais em tempo real.</p>
            </div>
        </div>
        <button onclick="showSection('section-subscription', event)" class="btn-primary feature-demo-cta">
            <i class="fas fa-rocket"></i> Ver planos
        </button>
    `;
    const hero = sectionEl.querySelector('header.hero');
    if (hero && hero.parentNode === sectionEl) {
        hero.insertAdjacentElement('afterend', banner);
    } else {
        sectionEl.prepend(banner);
    }
}

function clearSectionDemoBanner(sectionId) {
    const sectionEl = document.getElementById(sectionId);
    if (!sectionEl) return;
    const existing = sectionEl.querySelector('.feature-demo-banner');
    if (existing) existing.remove();
}

function updateSectionDemoBanner(sectionId) {
    const feature = sectionFeatureMap[sectionId];
    if (!feature) return;
    if (isFeatureLocked(feature)) {
        renderSectionDemoBanner(sectionId, feature);
    } else {
        clearSectionDemoBanner(sectionId);
    }
}

function buildAnalyticsDemoDataset(days) {
    const labels = [];
    const engagement = [];
    const conversions = [];
    for (let i = days - 1; i >= 0; i--) {
        const d = new Date();
        d.setDate(d.getDate() - i);
        labels.push(d.toLocaleDateString('pt-BR', { day: '2-digit', month: 'short' }));
        const clicks = 220 + Math.floor(Math.random() * 380);
        engagement.push(clicks);
        conversions.push(Math.max(8, Math.floor(clicks * (0.08 + Math.random() * 0.07))));
    }
    return { labels, engagement, conversions };
}

function loadAnalyticsDemoData(setAnalyticsStatus) {
    const period = Number(document.getElementById('analyticsPeriod')?.value || 14);
    const days = [7, 14, 30, 90].includes(period) ? period : 14;
    const demo = buildAnalyticsDemoDataset(days);
    const totalClicks = demo.engagement.reduce((acc, n) => acc + n, 0);
    const totalConv = demo.conversions.reduce((acc, n) => acc + n, 0);
    const cvr = totalClicks > 0 ? (totalConv / totalClicks) * 100 : 0;
    const growth = 8.5 + Math.random() * 11.5;
    const avgConv = 13 + Math.random() * 16;
    const campaigns = ['Lançamento Q2', 'Remarketing', 'Stories Bio'];
    const campaignEff = campaigns.map(name => ({ campaign: name, efficiency: 16 + Math.random() * 19 }));

    const setText = (id, value) => {
        const el = document.getElementById(id);
        if (el) el.textContent = value;
    };
    setText('analyticsKpiClicks', totalClicks.toLocaleString('pt-BR'));
    setText('analyticsKpiClicksDelta', `${growth.toFixed(1)}% vs período anterior (demo)`);
    setText('analyticsKpiConversions', totalConv.toLocaleString('pt-BR'));
    setText('analyticsKpiCvr', `CVR ${cvr.toFixed(2)}% (demo)`);
    setText('analyticsKpiAvgConvTime', `${avgConv.toFixed(0)} min`);
    setText('analyticsKpiTopCampaign', campaignEff[0].campaign);
    setText('analyticsKpiTopCampaignRate', `Eficiência ${campaignEff[0].efficiency.toFixed(2)}%`);

    renderChart('chartEngagement', 'line', demo.labels, demo.engagement, {
        label: 'Cliques:',
        borderColor: getBrandTokens().primary,
        fill: true,
        backgroundColor: getBrandTokens().chartFillPrimary
    });
    renderChart('chartLocations', 'bar', ['Brasil', 'Portugal', 'EUA', 'Espanha'], [4200, 980, 730, 410], {
        label: 'Quantidade de Cliques (demo)',
        backgroundColor: chartVisualTheme.colors,
        horizontal: true
    });
    renderChart('chartOS', 'bar', ['Android', 'iOS', 'Windows', 'macOS'], [3900, 1650, 480, 320], {
        label: 'Cliques por SO (demo)',
        backgroundColor: chartVisualTheme.colors.slice(0, 4),
        horizontal: true
    });
    renderChart('chartReferrers', 'bar', ['Instagram', 'WhatsApp', 'Google', 'Direto'], [2500, 1700, 900, 600], {
        backgroundColor: chartVisualTheme.colors,
        horizontal: true
    });
    renderChart('chartSources', 'pie', ['Link Digital', 'QR Code'], [73, 27]);
    renderChart('chartConversionsTimeline', 'line', demo.labels, demo.conversions, {
        label: 'Conversões (demo)',
        borderColor: getBrandTokens().accent,
        fill: true,
        backgroundColor: getBrandTokens().chartFillAccent
    });
    renderChart('chartHourlyPerformance', 'bar', ['08h', '10h', '12h', '14h', '16h', '18h', '20h', '22h'], [120, 240, 360, 470, 520, 430, 310, 180], {
        label: 'Cliques (demo)',
        backgroundColor: getBrandTokens().primary
    });
    renderChart('chartAnalyticsCampaignEfficiency', 'bar', campaignEff.map(i => i.campaign), campaignEff.map(i => i.efficiency), {
        label: 'Eficiência (%) (demo)',
        backgroundColor: '#f6b64f',
        horizontal: true
    });

    renderInsightListById('analyticsAlerts', [
        'Dado fictício: sua campanha de remarketing está com pico de cliques nas últimas 24h.',
        'Dado fictício: tráfego mobile representa mais de 80% dos acessos.'
    ], 'Sem alertas no momento.');
    renderInsightListById('analyticsRecommendations', [
        'Dado fictício: aumente investimento nas campanhas com maior eficiência.',
        'Dado fictício: teste novas variações de CTA para elevar conversões.'
    ], 'Sem recomendações no momento.');

    setAnalyticsStatus('Visualização em modo demonstração com dados fictícios. Faça upgrade para ver dados reais.', 'warning');
}

function renderCampaignsDemoData() {
    const campSelect = document.getElementById('campaignSelect');
    const linkSelect = document.getElementById('linkToCampaignSelect');
    const analyticsArea = document.getElementById('campaignAnalyticsArea');
    const demoCampaigns = [
        {
            name: 'Lançamento Produto X',
            links: [
                { id: 9101, path: 'lp-produto-x', subdomain: 'go', original_url: 'https://site.com/produto-x?utm_source=instagram&utm_medium=stories&utm_campaign=Lançamento Produto X&utm_content=criativo_a', tracking_enabled: 1, utm_source: 'instagram', utm_medium: 'stories', utm_campaign: 'Lançamento Produto X', utm_term: 'lookalike', utm_content: 'criativo_a' },
                { id: 9102, path: 'oferta-produto-x', subdomain: 'go', original_url: 'https://site.com/oferta?utm_source=google&utm_medium=cpc&utm_campaign=Lançamento Produto X&utm_content=keyword_marca', tracking_enabled: 1, utm_source: 'google', utm_medium: 'cpc', utm_campaign: 'Lançamento Produto X', utm_term: 'produto x', utm_content: 'keyword_marca' },
                { id: 9103, path: 'whats-produto-x', subdomain: 'encurtou', original_url: 'https://wa.me/5511999999999?text=quero%20saber%20mais&utm_source=whatsapp&utm_medium=direct&utm_campaign=Lançamento Produto X&utm_content=suporte', tracking_enabled: 0, utm_source: 'whatsapp', utm_medium: 'direct', utm_campaign: 'Lançamento Produto X', utm_term: '', utm_content: 'suporte' }
            ],
            utms: {
                sources: [{ label: 'instagram', value: 420 }, { label: 'google', value: 290 }, { label: 'whatsapp', value: 180 }, { label: 'email', value: 110 }],
                mediums: [{ label: 'stories', value: 260 }, { label: 'cpc', value: 290 }, { label: 'direct', value: 180 }, { label: 'email', value: 110 }]
            }
        },
        {
            name: 'Promoção Semana',
            links: [
                { id: 9201, path: 'cupom-semana', subdomain: 'go', original_url: 'https://site.com/cupom?utm_source=facebook&utm_medium=cpc&utm_campaign=Promoção Semana&utm_content=carrossel1', tracking_enabled: 1, utm_source: 'facebook', utm_medium: 'cpc', utm_campaign: 'Promoção Semana', utm_term: 'desconto', utm_content: 'carrossel1' },
                { id: 9202, path: 'lista-vip', subdomain: 'vip', original_url: 'https://site.com/lista?utm_source=instagram&utm_medium=reels&utm_campaign=Promoção Semana&utm_content=video_curto', tracking_enabled: 1, utm_source: 'instagram', utm_medium: 'reels', utm_campaign: 'Promoção Semana', utm_term: 'vip', utm_content: 'video_curto' },
                { id: 9203, path: 'black-friday-alerta', subdomain: 'encurtou', original_url: 'https://site.com/alerta?utm_source=telegram&utm_medium=community&utm_campaign=Promoção Semana&utm_content=grupo', tracking_enabled: 0, utm_source: 'telegram', utm_medium: 'community', utm_campaign: 'Promoção Semana', utm_term: '', utm_content: 'grupo' }
            ],
            utms: {
                sources: [{ label: 'facebook', value: 360 }, { label: 'instagram', value: 330 }, { label: 'telegram', value: 140 }, { label: 'google', value: 90 }],
                mediums: [{ label: 'cpc', value: 360 }, { label: 'reels', value: 330 }, { label: 'community', value: 140 }, { label: 'search', value: 90 }]
            }
        },
        {
            name: 'Influencers Julho',
            links: [
                { id: 9301, path: 'creator-ana', subdomain: 'go', original_url: 'https://site.com/influencer/ana?utm_source=instagram&utm_medium=influencer&utm_campaign=Influencers Julho&utm_content=ana', tracking_enabled: 1, utm_source: 'instagram', utm_medium: 'influencer', utm_campaign: 'Influencers Julho', utm_term: 'creator', utm_content: 'ana' },
                { id: 9302, path: 'creator-beto', subdomain: 'go', original_url: 'https://site.com/influencer/beto?utm_source=tiktok&utm_medium=influencer&utm_campaign=Influencers Julho&utm_content=beto', tracking_enabled: 1, utm_source: 'tiktok', utm_medium: 'influencer', utm_campaign: 'Influencers Julho', utm_term: 'creator', utm_content: 'beto' },
                { id: 9303, path: 'creator-camila', subdomain: 'go', original_url: 'https://site.com/influencer/camila?utm_source=youtube&utm_medium=influencer&utm_campaign=Influencers Julho&utm_content=camila', tracking_enabled: 1, utm_source: 'youtube', utm_medium: 'influencer', utm_campaign: 'Influencers Julho', utm_term: 'creator', utm_content: 'camila' }
            ],
            utms: {
                sources: [{ label: 'instagram', value: 310 }, { label: 'tiktok', value: 280 }, { label: 'youtube', value: 210 }, { label: 'kwai', value: 95 }],
                mediums: [{ label: 'influencer', value: 800 }, { label: 'stories', value: 65 }, { label: 'bio', value: 30 }]
            }
        }
    ];
    const campaigns = demoCampaigns.map(item => ({ name: item.name, link_count: item.links.length }));
    const allDemoLinks = demoCampaigns.flatMap(item => item.links);
    campaignLinksDetailsCache = allDemoLinks.map(link => ({ ...link, campaign_name: link.utm_campaign }));
    campaignTrackingLinksCache = allDemoLinks.map(link => ({
        id: String(link.id),
        label: `/${link.path} (${link.subdomain})`,
        campaignName: (link.utm_campaign || '').trim(),
        trackingEnabled: parseInt(link.tracking_enabled || 0, 10) === 1 ? 1 : 0
    }));

    if (campSelect) {
        campSelect.innerHTML = '<option value="">Selecione uma Campanha</option>';
        campaigns.forEach(c => {
            const opt = document.createElement('option');
            opt.value = c.name;
            opt.innerText = `${c.name} (demo)`;
            campSelect.appendChild(opt);
        });
        if (campaigns.length > 0) {
            campSelect.value = campaigns[0].name;
        }
    }
    if (linkSelect) {
        linkSelect.innerHTML = '<option value="">Selecione o Link</option>';
        allDemoLinks.forEach(link => {
            const opt = document.createElement('option');
            opt.value = String(link.id);
            opt.innerText = `/${link.path} (${link.subdomain})`;
            linkSelect.appendChild(opt);
        });
    }
    renderCampaignList(campaigns);
    if (analyticsArea) analyticsArea.classList.remove('hidden');
    if (campaigns.length > 0) {
        loadCampaignAnalytics(campaigns[0].name);
    }
}

function renderBioPagesDemoData() {
    const container = document.getElementById('bioPagesList');
    if (!container) return;
    container.innerHTML = `
        <div class="link-card">
            <div class="link-card-header">
                <div class="link-card-icon" style="background:#111827; border-radius:50%; width:40px; height:40px; display:flex; align-items:center; justify-content:center; color:var(--primary);">
                    <i class="fas fa-mobile-alt"></i>
                </div>
                <div class="link-card-title-group">
                    <span class="link-card-title">Portfólio Criador (demo)</span>
                    <span class="link-card-date">/criador-pro</span>
                </div>
            </div>
            <div class="link-card-body">
                <a href="#" class="link-card-short">linkneo.pro/bio/criador-pro</a>
            </div>
            <div class="link-card-footer">
                <div class="link-card-actions" style="width:100%; justify-content:flex-end; gap:10px;">
                    <button class="btn-action btn-edit" title="Editar demo"><i class="fas fa-pen"></i></button>
                    <button class="btn-action btn-delete" title="Excluir demo"><i class="fas fa-trash"></i></button>
                </div>
            </div>
        </div>
        <div class="link-card">
            <div class="link-card-header">
                <div class="link-card-icon" style="background:var(--page-surface-soft); border-radius:50%; width:40px; height:40px; display:flex; align-items:center; justify-content:center; color:var(--accent);">
                    <i class="fas fa-mobile-alt"></i>
                </div>
                <div class="link-card-title-group">
                    <span class="link-card-title">Página de Vendas (demo)</span>
                    <span class="link-card-date">/oferta-premium</span>
                </div>
            </div>
            <div class="link-card-body">
                <a href="#" class="link-card-short">linkneo.pro/bio/oferta-premium</a>
            </div>
            <div class="link-card-footer">
                <div class="link-card-actions" style="width:100%; justify-content:flex-end; gap:10px;">
                    <button class="btn-action btn-edit" title="Editar demo"><i class="fas fa-pen"></i></button>
                    <button class="btn-action btn-delete" title="Excluir demo"><i class="fas fa-trash"></i></button>
                </div>
            </div>
        </div>
    `;
}

function createTutorMessage(type, text) {
    const box = document.getElementById('tutorMessages');
    if (!box) return;
    const el = document.createElement('article');
    el.className = `tutor-msg ${type === 'user' ? 'tutor-msg-user' : 'tutor-msg-bot'}`;
    el.textContent = text;
    box.appendChild(el);
    box.scrollTop = box.scrollHeight;
}

function setTutorOpen(isOpen) {
    const panel = document.getElementById('tutorChatPanel');
    if (!panel) return;
    panel.classList.toggle('hidden', !isOpen);
}

function resetTutorUiMessages() {
    const box = document.getElementById('tutorMessages');
    if (!box) return;
    box.innerHTML = `
        <article class="tutor-msg tutor-msg-bot">
            Olá! Eu respondo apenas dúvidas sobre como usar as funcionalidades do LinkNeo.pro. Como posso ajudar?
        </article>
    `;
}

async function resetTutorServerContext() {
    const payload = new FormData();
    payload.append('action', 'reset_context');
    try {
        await fetch('ai_tutor.php', {
            method: 'POST',
            body: payload,
            credentials: 'include'
        });
    } catch (e) {
        // Falha de rede não impede fechamento local do chat.
    }
}

async function closeTutorAndReset() {
    setTutorOpen(false);
    resetTutorUiMessages();
    const input = document.getElementById('tutorQuestionInput');
    if (input) {
        input.value = '';
        input.disabled = false;
    }
    const sendButton = document.getElementById('tutorSendButton');
    if (sendButton) {
        sendButton.disabled = false;
    }
    tutorSending = false;
    await resetTutorServerContext();
}

async function sendTutorQuestion(question) {
    const payload = new FormData();
    payload.append('question', question);

    const res = await fetch('ai_tutor.php', {
        method: 'POST',
        body: payload,
        credentials: 'include'
    });
    const data = await res.json();
    if (!res.ok || !data.success) {
        throw new Error(data.message || 'Não foi possível consultar o Tutor IA agora.');
    }
    return data.answer || '';
}

function initTutorIA() {
    const floatButton = document.getElementById('tutorFloatButton');
    const closeButton = document.getElementById('tutorCloseButton');
    const panel = document.getElementById('tutorChatPanel');
    const form = document.getElementById('tutorChatForm');
    const input = document.getElementById('tutorQuestionInput');
    const sendButton = document.getElementById('tutorSendButton');
    if (!floatButton || !closeButton || !panel || !form || !input || !sendButton) return;

    floatButton.addEventListener('click', () => {
        const willOpen = panel.classList.contains('hidden');
        if (willOpen) {
            setTutorOpen(true);
            input.focus();
        } else {
            closeTutorAndReset();
        }
    });

    closeButton.addEventListener('click', () => {
        closeTutorAndReset();
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (tutorSending) return;
        const question = (input.value || '').trim();
        if (!question) return;
        if (question.length > 450) {
            createTutorMessage('bot', 'A pergunta está muito longa. Tente resumir em até 450 caracteres.');
            return;
        }

        tutorSending = true;
        input.disabled = true;
        sendButton.disabled = true;
        createTutorMessage('user', question);
        input.value = '';
        createTutorMessage('bot', 'Analisando sua dúvida...');

        try {
            const answer = await sendTutorQuestion(question);
            const messages = document.getElementById('tutorMessages');
            if (messages && messages.lastElementChild) {
                messages.lastElementChild.textContent = answer || 'Não consegui gerar uma resposta agora. Tente novamente.';
            }
        } catch (error) {
            const messages = document.getElementById('tutorMessages');
            if (messages && messages.lastElementChild) {
                messages.lastElementChild.textContent = error.message || 'Erro ao responder. Tente novamente.';
            }
        } finally {
            tutorSending = false;
            input.disabled = false;
            sendButton.disabled = false;
            input.focus();
        }
    });
}

const chartThemePalettes = {
    dark: {
        colors: ['#1A8CFF', '#9932CC', '#f6b64f', '#B366FF', '#4dc7ff', '#fb7185'],
        text: '#eef2fa',
        muted: '#89a0bd',
        grid: 'rgba(137, 160, 189, 0.14)',
        border: 'rgba(137, 160, 189, 0.22)',
        tooltipBg: 'rgba(10, 10, 18, 0.96)',
        tooltipBorder: 'rgba(153, 50, 204, 0.22)'
    },
    light: {
        colors: ['#0078E8', '#7B2CBF', '#d97706', '#9932CC', '#0ea5e9', '#db2777'],
        text: '#0f172a',
        muted: '#475569',
        grid: 'rgba(100, 116, 139, 0.22)',
        border: 'rgba(100, 116, 139, 0.28)',
        tooltipBg: 'rgba(248, 250, 252, 0.97)',
        tooltipBorder: 'rgba(148, 163, 184, 0.5)'
    }
};

function getBrandTokens() {
    const style = getComputedStyle(document.documentElement);
    const pick = (name, fallback) => (style.getPropertyValue(name).trim() || fallback);
    return {
        primary: pick('--primary', '#1A8CFF'),
        accent: pick('--accent', '#9932CC'),
        primarySurface: pick('--primary-surface', 'rgba(26, 140, 255, 0.05)'),
        primarySurfaceStrong: pick('--primary-surface-strong', 'rgba(26, 140, 255, 0.12)'),
        primaryBorder: pick('--primary-border', 'rgba(26, 140, 255, 0.2)'),
        primaryShadow: pick('--primary-shadow', 'rgba(26, 140, 255, 0.2)'),
        chartFillPrimary: pick('--chart-fill-primary', 'rgba(26, 140, 255, 0.12)'),
        chartFillAccent: pick('--chart-fill-accent', 'rgba(153, 50, 204, 0.15)'),
        pageSurface: pick('--page-surface', '#12121C'),
        text: pick('--text', '#e8f0fb'),
        chartColors: chartVisualTheme.colors
    };
}

let chartVisualTheme = chartThemePalettes.dark;

function getCurrentTheme() {
    return document.documentElement.getAttribute('data-theme') === 'light' ? 'light' : 'dark';
}

function updateChartDefaults(theme) {
    chartVisualTheme = chartThemePalettes[theme] || chartThemePalettes.dark;
    if (!window.Chart) return;
    Chart.defaults.color = chartVisualTheme.muted;
    Chart.defaults.borderColor = chartVisualTheme.border;
    Chart.defaults.font.family = "'Manrope', sans-serif";
    Chart.defaults.plugins.legend.labels.usePointStyle = true;
    Chart.defaults.plugins.legend.labels.boxWidth = 10;
    Chart.defaults.plugins.legend.labels.boxHeight = 10;
}

function refreshRenderedChartsTheme() {
    Object.values(charts).forEach((chart) => {
        if (!chart || !chart.options) return;
        if (chart.options.plugins?.legend?.labels) {
            chart.options.plugins.legend.labels.color = chartVisualTheme.muted;
        }
        if (chart.options.plugins?.tooltip) {
            chart.options.plugins.tooltip.backgroundColor = chartVisualTheme.tooltipBg;
            chart.options.plugins.tooltip.titleColor = chartVisualTheme.text;
            chart.options.plugins.tooltip.bodyColor = chartVisualTheme.text;
            chart.options.plugins.tooltip.borderColor = chartVisualTheme.tooltipBorder;
        }
        if (chart.options.scales) {
            ['x', 'y'].forEach(axis => {
                if (chart.options.scales[axis]?.ticks) {
                    chart.options.scales[axis].ticks.color = chartVisualTheme.muted;
                }
                if (chart.options.scales[axis]?.grid) {
                    chart.options.scales[axis].grid.color = chartVisualTheme.grid;
                }
            });
        }
        chart.update('none');
    });
}

function updateThemeToggleA11y(theme) {
    const btn = document.getElementById('themeToggleBtn');
    const icon = document.getElementById('themeToggleIcon');
    if (!btn || !icon) return;
    const isLight = theme === 'light';
    btn.setAttribute('aria-pressed', String(isLight));
    btn.setAttribute('aria-label', isLight ? 'Ativar modo escuro' : 'Ativar modo claro');
    btn.setAttribute('title', isLight ? 'Ativar modo escuro' : 'Ativar modo claro');
    icon.classList.toggle('fa-sun', isLight);
    icon.classList.toggle('fa-moon', !isLight);
}

const inlineThemeReplacementRules = [
    { pattern: /background:\s*#0f172a/gi, value: 'background: var(--page-surface-soft)' },
    { pattern: /background:\s*#111827/gi, value: 'background: var(--page-surface-soft)' },
    { pattern: /background:\s*#1e293b/gi, value: 'background: var(--page-surface-soft)' },
    { pattern: /background:\s*rgba\(\s*0\s*,\s*0\s*,\s*0\s*,\s*0\.(15|18|2|25|3)\s*\)/gi, value: 'background: var(--interactive-soft)' },
    { pattern: /border:\s*1px\s+solid\s+rgba\(\s*255\s*,\s*255\s*,\s*255\s*,\s*0\.(05|06|08|1)\s*\)/gi, value: 'border: 1px solid var(--border)' },
    { pattern: /color:\s*white/gi, value: 'color: var(--text)' },
    { pattern: /color:\s*#fff/gi, value: 'color: var(--text)' },
    { pattern: /color:\s*#e2e8f0/gi, value: 'color: var(--text)' },
    { pattern: /rgba\(\s*43\s*,\s*246\s*,\s*209[^)]*\)/gi, value: 'var(--primary-surface)' },
    { pattern: /rgba\(\s*72\s*,\s*229\s*,\s*194[^)]*\)/gi, value: 'var(--primary-surface-strong)' },
    { pattern: /#2BF6D1/gi, value: 'var(--primary)' },
    { pattern: /#48e5c2/gi, value: 'var(--primary)' },
    { pattern: /#7aa2ff/gi, value: 'var(--accent)' }
];

function normalizeInlineThemeStyles(rootNode = document) {
    const nodes = [];
    if (rootNode instanceof Element) {
        if (rootNode.hasAttribute('style')) nodes.push(rootNode);
        nodes.push(...rootNode.querySelectorAll('[style]'));
    } else {
        nodes.push(...document.querySelectorAll('[style]'));
    }

    nodes.forEach((node) => {
        const styleAttr = node.getAttribute('style');
        if (!styleAttr) return;
        let nextStyle = styleAttr;
        inlineThemeReplacementRules.forEach((rule) => {
            nextStyle = nextStyle.replace(rule.pattern, rule.value);
        });
        if (nextStyle !== styleAttr) {
            node.setAttribute('style', nextStyle);
        }
    });
}

function initInlineThemeObserver() {
    if (themeInlineObserver || !document.body) return;
    themeInlineObserver = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            mutation.addedNodes.forEach((node) => {
                if (node.nodeType === 1) {
                    normalizeInlineThemeStyles(node);
                }
            });
            if (mutation.type === 'attributes' && mutation.target instanceof Element) {
                normalizeInlineThemeStyles(mutation.target);
            }
        });
    });
    themeInlineObserver.observe(document.body, {
        childList: true,
        subtree: true,
        attributes: true,
        attributeFilter: ['style']
    });
}

function applyTheme(theme, options = {}) {
    const selected = theme === 'light' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', selected);
    normalizeInlineThemeStyles(document);
    updateChartDefaults(selected);
    refreshRenderedChartsTheme();
    updateThemeToggleA11y(selected);
    if (options.persist) {
        manualThemePreference = true;
        try {
            localStorage.setItem(THEME_STORAGE_KEY, selected);
        } catch (e) { /* ignore */ }
    }
}

function resolveInitialTheme() {
    let stored = null;
    try {
        stored = localStorage.getItem(THEME_STORAGE_KEY);
    } catch (e) {
        stored = null;
    }
    manualThemePreference = stored === 'light' || stored === 'dark';
    if (manualThemePreference) return stored;
    return 'light';
}

function initThemeSystem() {
    initInlineThemeObserver();
    const initialTheme = resolveInitialTheme();
    applyTheme(initialTheme, { persist: false });

    const toggleBtn = document.getElementById('themeToggleBtn');
    if (toggleBtn) {
        toggleBtn.addEventListener('click', () => {
            const next = getCurrentTheme() === 'light' ? 'dark' : 'light';
            applyTheme(next, { persist: true });
        });
    }
}

function getSwalThemeOptions() {
    const tokens = getBrandTokens();
    return getCurrentTheme() === 'light'
        ? { background: tokens.pageSurface, color: tokens.text }
        : { background: tokens.pageSurface, color: tokens.text };
}

function toggleTheme() {
    const next = getCurrentTheme() === 'light' ? 'dark' : 'light';
    applyTheme(next, { persist: true });
}

// CSRF: injeta token automaticamente em requests POST same-origin (quando disponível).
// O enforcement no backend é controlado por env CSRF_ENFORCE=1.
(function patchFetchForCsrf() {
    const originalFetch = window.fetch.bind(window);
    window.fetch = function (input, init) {
        try {
            const opts = init ? { ...init } : {};
            const method = (opts.method || 'GET').toUpperCase();
            const isWrite = method !== 'GET' && method !== 'HEAD' && method !== 'OPTIONS';

            let url = '';
            if (typeof input === 'string') url = input;
            else if (input && typeof input.url === 'string') url = input.url;

            const isAbsolute = /^https?:\/\//i.test(url);
            const sameOrigin = !isAbsolute || url.startsWith(window.location.origin);

            if (sameOrigin && isWrite && csrfToken) {
                opts.headers = new Headers(opts.headers || {});
                if (!opts.headers.has('X-CSRF-Token')) {
                    opts.headers.set('X-CSRF-Token', csrfToken);
                }
            }

            return originalFetch(input, opts);
        } catch (e) {
            return originalFetch(input, init);
        }
    };
})();

// A/B TEST STATE
let currentLinkMode = 'simple';
let abVariants = [
    { id: 1, name: 'Variante A', url: '', weight: 50, status: 'active' },
    { id: 2, name: 'Variante B', url: '', weight: 50, status: 'active' }
];
let abVariantIdCounter = 3;

function toggleLinkMode(mode) {
    currentLinkMode = mode;
    const btnSimple = document.getElementById('btnLinkModeSimple');
    const btnAb = document.getElementById('btnLinkModeAB');
    const modeSimple = document.getElementById('linkModeSimple');
    const modeAb = document.getElementById('linkModeAB');

    if (btnSimple) {
        if (mode === 'simple') {
            btnSimple.classList.add('active');
            btnAb.classList.remove('active');
            modeSimple.classList.remove('hidden');
            modeAb.classList.add('hidden');
        } else {
            btnAb.classList.add('active');
            btnSimple.classList.remove('active');
            modeAb.classList.remove('hidden');
            modeSimple.classList.add('hidden');
            renderAbVariants();
        }
    }
}

function renderAbVariants() {
    const list = document.getElementById('abVariantsList');
    if (!list) return;
    list.innerHTML = '';

    let totalWeight = 0;

    abVariants.forEach((v) => {
        if (v.status === 'active') totalWeight += parseInt(v.weight);

        list.innerHTML += `
            <div style="background: var(--card); border: 1px solid rgba(255,255,255,0.05); padding: 15px; border-radius: 10px; display: flex; flex-direction: column; gap: 10px;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <input type="text" value="${v.name}" onchange="updateAbVariant(${v.id}, 'name', this.value)" placeholder="Nome (Ex: Variante A)" style="background: transparent; border: none; color: white; font-weight: bold; outline: none; width: 60%; font-size: 0.9rem;">
                    <div style="display: flex; gap: 10px;">
                        <button onclick="toggleAbVariantStatus(${v.id})" class="btn-action" title="${v.status === 'active' ? 'Pausar' : 'Ativar'}" style="color: ${v.status === 'active' ? 'var(--primary)' : 'var(--text-dim)'};">
                            <i class="fas ${v.status === 'active' ? 'fa-eye' : 'fa-eye-slash'}"></i>
                        </button>
                        ${abVariants.length > 2 ? `<button onclick="removeAbVariant(${v.id})" class="btn-action btn-delete"><i class="fas fa-trash"></i></button>` : ''}
                    </div>
                </div>
                <div style="display: flex; gap: 10px; opacity: ${v.status === 'active' ? '1' : '0.5'};">
                    <div style="flex: 1;">
                        <input type="url" value="${v.url}" onchange="updateAbVariant(${v.id}, 'url', this.value)" placeholder="https://" style="width: 100%; border: 1px solid var(--border); background: var(--page-surface-soft); padding: 8px; border-radius: 6px; color: var(--text);" ${v.status === 'inactive' ? 'disabled' : ''}>
                    </div>
                    <div style="width: 80px; position: relative;">
                        <input type="number" value="${v.weight}" onchange="updateAbVariant(${v.id}, 'weight', this.value)" min="1" max="100" style="width: 100%; text-align: center; padding-right: 20px; border: 1px solid var(--border); background: var(--page-surface-soft); padding: 8px; border-radius: 6px; color: var(--text);" ${v.status === 'inactive' ? 'disabled' : ''}>
                        <span style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); color: var(--text-dim); font-size: 0.8rem;">%</span>
                    </div>
                </div>
            </div>
        `;
    });

    const badge = document.getElementById('abTotalWeightBadge');
    if (badge) {
        badge.innerText = `Total: ${Math.round(totalWeight)}%`;
        if (Math.round(totalWeight) !== 100) {
            badge.style.color = '#ef4444';
            badge.style.border = '1px solid rgba(239, 68, 68, 0.3)';
        } else {
            badge.style.color = 'var(--primary)';
            badge.style.border = '1px solid var(--primary-border)';
        }
    }
}

function updateAbVariant(id, field, value) {
    const v = abVariants.find(x => x.id === id);
    if (!v) return;
    v[field] = value;
    if (field === 'weight') renderAbVariants();
}

function toggleAbVariantStatus(id) {
    const v = abVariants.find(x => x.id === id);
    if (!v) return;
    v.status = v.status === 'active' ? 'inactive' : 'active';
    renderAbVariants();
}

function removeAbVariant(id) {
    abVariants = abVariants.filter(x => x.id !== id);
    renderAbVariants();
}

function addAbVariant() {
    abVariants.push({
        id: abVariantIdCounter++,
        name: `Variante ${String.fromCharCode(64 + abVariants.length + 1)}`,
        url: '',
        weight: 10,
        status: 'active'
    });
    renderAbVariants();
}

// --- INICIALIZAÇÃO ---
window.onload = async () => {
    initThemeSystem();
    captureAffiliateCodeFromUrl();

    // Preenche os textos de ".linkneo.pro" na interface
    document.querySelectorAll('.host-display').forEach(el => el.innerText = "." + domain);
    generateRandomSlug();

    // Listeners para imagem da Bio (carregamento imediato)
    const bioAvatarInput = document.getElementById('bio-avatar');
    if (bioAvatarInput) {
        ['input', 'paste', 'change'].forEach(evt => {
            bioAvatarInput.addEventListener(evt, () => updateBioPreview());
        });
    }

    // Inicializa Google Sign-In
    initGoogleSignIn();

    // Aguarda autenticação antes de restaurar a seção
    await checkAuth();

    if (isLoggedIn) {
        await loadUserSubdomains();
    }

    // Restaura seção: Prioridade 1: Hash da URL (#section-id) | Prioridade 2: Boot Section (PHP) | Prioridade 3: LocalStorage
    const urlHash = window.location.hash ? window.location.hash.substring(1) : '';
    const encBootSection = typeof window.__ENC_BOOT_SECTION__ === 'string' ? window.__ENC_BOOT_SECTION__.trim() : '';

    if (urlHash && document.getElementById(urlHash)) {
        showSection(urlHash);
    } else if (encBootSection && document.getElementById(encBootSection)) {
        showSection(encBootSection);
    } else {
        const savedSection = localStorage.getItem('activeSection');
        if (savedSection) {
            if (authRequiredSections.includes(savedSection) && !isLoggedIn) {
                showSection(guestFallbackSection);
            } else {
                showSection(savedSection);
            }
        }
    }

    // Listener para mudanças de hash sem recarregar a página
    window.addEventListener('hashchange', () => {
        const newHash = window.location.hash ? window.location.hash.substring(1) : '';
        if (newHash && document.getElementById(newHash)) {
            showSection(newHash);
        }
    });

    // Restaura Aba da Home
    const savedHomeTab = localStorage.getItem('activeHomeTab');
    if (savedHomeTab) {
        switchHomeTab(savedHomeTab);
    }

    // Listeners para verificação de slug em tempo real
    const slugInput = document.getElementById('pathSlug');
    const subInput = document.getElementById('subdomain');
    if (slugInput) {
        slugInput.addEventListener('input', () => {
            debounce(() => checkSlugAvailability(), 500);
        });
    }
    if (subInput) {
        subInput.addEventListener('input', () => {
            debounce(() => checkSlugAvailability(), 500);
        });
    }

    initTutorIA();

};

let debounceTimer;
function debounce(func, delay) {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(func, delay);
}

async function checkSlugAvailability() {
    const slug = document.getElementById('pathSlug').value.trim();
    const subdomain = document.getElementById('subdomain').value.trim();
    const statusEl = document.getElementById('slugStatus');
    const pathBox = document.querySelector('.path-box');

    if (slug.length < 2) {
        statusEl.innerHTML = '';
        pathBox.classList.remove('invalid');
        return;
    }

    try {
        const res = await fetch(`api.php?action=check_slug&slug=${slug}&subdomain=${subdomain}`, { credentials: 'include' });
        const data = await res.json();

        if (!data.available) {
            statusEl.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Esta terminação já está em uso.';
            statusEl.className = 'slug-status unavailable';
            pathBox.classList.add('invalid');
        } else {
            statusEl.innerHTML = '<i class="fas fa-check-circle"></i> Terminação disponível!';
            statusEl.className = 'slug-status available';
            pathBox.classList.remove('invalid');
        }
    } catch (e) { }
}

function switchHomeTab(tab) {
    resetAllInputs(); // Limpa ao trocar de funcionalidade
    document.querySelectorAll('.home-tab-content').forEach(c => c.classList.add('hidden'));
    document.querySelectorAll('.home-tabs button').forEach(b => b.classList.remove('active'));

    const content = document.getElementById(`home-content-${tab}`);
    if (content) content.classList.remove('hidden');

    if (tab === 'links') {
        const btn = document.getElementById('tabHomeLinks');
        if (btn) btn.classList.add('active');
    } else {
        const btn = document.getElementById('tabHomeQR');
        if (btn) btn.classList.add('active');
        loadQRLinks(); // Garante o carregamento dos links ao entrar na aba
    }
    localStorage.setItem('activeHomeTab', tab);
}


function toggleCustomPath() {
    document.getElementById('customPathToggle').classList.add('hidden');
    document.getElementById('customPathGroup').classList.remove('hidden');
}



function generateRandomSlug() {
    const chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
    let result = '';
    for (let i = 0; i < 6; i++) {
        result += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    document.getElementById('pathSlug').value = result;
}

// --- NAVEGAÇÃO DA SIDEBAR ---
function toggleSidebar() {
    document.querySelector('.sidebar').classList.toggle('open');
    document.querySelector('.sidebar-overlay').classList.toggle('active');
}

function setSidebarActiveLink(id) {
    document.querySelectorAll('.sidebar-nav a').forEach(a => a.classList.remove('active'));
    const sidebarLink = document.querySelector(`.sidebar-nav a[onclick*='${id}']`);
    if (sidebarLink) sidebarLink.classList.add('active');
}

function normalizeAffiliateCode(value) {
    return String(value || '')
        .toUpperCase()
        .trim()
        .replace(/[^A-Z0-9\-_]/g, '');
}

function setAffiliateTrackingCode(code) {
    const normalized = normalizeAffiliateCode(code);
    if (!normalized) return '';
    localStorage.setItem(AFFILIATE_TRACK_STORAGE, normalized);
    document.cookie = `${AFFILIATE_TRACK_COOKIE}=${encodeURIComponent(normalized)}; path=/; max-age=${60 * 60 * 24 * 30}; samesite=lax`;
    return normalized;
}

function readAffiliateCodeFromCookie() {
    const key = `${AFFILIATE_TRACK_COOKIE}=`;
    const parts = document.cookie.split(';');
    for (const part of parts) {
        const trimmed = part.trim();
        if (trimmed.startsWith(key)) {
            return normalizeAffiliateCode(decodeURIComponent(trimmed.substring(key.length)));
        }
    }
    return '';
}

function getAffiliateTrackingCode() {
    const fromStorage = normalizeAffiliateCode(localStorage.getItem(AFFILIATE_TRACK_STORAGE) || '');
    if (fromStorage) return fromStorage;
    return readAffiliateCodeFromCookie();
}

function captureAffiliateCodeFromUrl() {
    const params = new URLSearchParams(window.location.search);
    const queryCode = normalizeAffiliateCode(params.get('aff') || params.get('affiliate') || '');
    if (queryCode) {
        setAffiliateTrackingCode(queryCode);
    }
}

function appendReferralCodeToFormData(fd) {
    const code = getAffiliateTrackingCode();
    if (code) {
        fd.append('referral_code', code);
    }
}

function closeSidebarOnMobile() {
    if (window.innerWidth <= 900) {
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.querySelector('.sidebar-overlay');
        if (sidebar) sidebar.classList.remove('open');
        if (overlay) overlay.classList.remove('active');
    }
}

function restoreGuestView() {
    const fallback = document.getElementById(guestFallbackSection);
    if (!fallback) return;

    document.querySelectorAll('.view-section').forEach(section => section.classList.add('hidden'));
    fallback.classList.remove('hidden');
    setSidebarActiveLink(guestFallbackSection);
    localStorage.setItem('activeSection', guestFallbackSection);
    closeSidebarOnMobile();
}

function clearFeatureLocks() {
    document.querySelectorAll('.locked-feature').forEach(el => {
        el.classList.remove('locked-feature');
        el.removeAttribute('data-original-click');
    });
}

function showSection(id, event) {
    if (event) event.preventDefault();

    if (authRequiredSections.includes(id) && !isLoggedIn) {
        pendingSectionAfterLogin = id;
        restoreGuestView();
        openModal('modalLogin');
        return;
    }

    closeModals();
    pendingSectionAfterLogin = null;

    const target = document.getElementById(id);
    if (!target) return;
    updateSectionDemoBanner(id);

    document.querySelectorAll('.view-section').forEach(s => s.classList.add('hidden'));
    target.classList.remove('hidden');

    // Ativa o link na sidebar (via evento ou busca manual por id da seção)
    setSidebarActiveLink(id);
    if (event && event.currentTarget && event.currentTarget.classList) {
        event.currentTarget.classList.add('active');
    }

    // Fechar sidebar no mobile após clique
    closeSidebarOnMobile();

    if (id === 'section-dashboard') {
        loadDashboardData();
        setupAutoRefresh('dashboard');
    }
    if (id === 'section-analytics') {
        loadAnalytics();
        setupAutoRefresh('analytics');
    }
    if (id !== 'section-dashboard' && id !== 'section-analytics') {
        stopAutoRefresh();
    }
    if (id === 'section-campaigns') loadCampaigns();
    if (id === 'section-mylinks') {
        loadMyLinks();
        applyStoredViewMode('myLinksCards');
    }
    if (id === 'section-team') loadTeamMembers();

    if (id === 'section-qrcodes') {
        resetAllInputs();
        loadQRLinks();
    }
    if (id === 'section-myqrs') {
        loadQRLinks();
        applyStoredViewMode('myQRsCards');
    }
    if (id === 'section-biopages') {
        loadBioPages();
        loadBioTemplates();
    }
    if (id === 'section-subscription') {
        if (typeof loadSubscriptionTab === 'function') loadSubscriptionTab();
        if (typeof loadUpgradePlans === 'function') loadUpgradePlans();
    }
    if (id === 'section-affiliate') {
        loadAffiliateDashboard();
    }
    if (id === 'section-account-settings') {
        loadAccountSettings();
    }

    localStorage.setItem('activeSection', id);
    applyTeamRoleUiRestrictions();
}

function handlePostLoginNavigation() {
    if (!pendingSectionAfterLogin) return;

    const targetSection = pendingSectionAfterLogin;
    pendingSectionAfterLogin = null;
    showSection(targetSection);
}

function resetAllInputs() {
    // Links (Corrigido para longUrl)
    const longUrl = document.getElementById('longUrl');
    const pathSlug = document.getElementById('pathSlug');
    const subElement = document.getElementById('subdomain');

    // UTM Builder Fields
    const utmInputs = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'utmPreview'];
    utmInputs.forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = '';
    });

    if (longUrl) longUrl.value = '';
    if (subElement) subElement.value = '';
    if (pathSlug) generateRandomSlug();

    // Reset A/B Test
    abVariants = [
        { id: 1, name: 'Variante A', url: '', weight: 50, status: 'active' },
        { id: 2, name: 'Variante B', url: '', weight: 50, status: 'active' }
    ];
    toggleLinkMode('simple');

    // QR
    const qrNewTarget = document.getElementById('qrNewTarget');
    const qrNewPath = document.getElementById('qrNewPath');
    const qrSubdomain = document.getElementById('qrSubdomain');
    if (qrNewTarget) qrNewTarget.value = '';
    if (qrSubdomain) qrSubdomain.value = '';
    if (qrNewPath) generateQRPath();

    // Novos campos QR
    const qrFields = ['qrSocialInput', 'qrVcardName', 'qrVcardPhone', 'qrVcardEmail', 'qrVcardOrg', 'qrPhoneInput', 'qrEmailTo', 'qrEmailSubject', 'qrEmailBody'];
    qrFields.forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = '';
    });
    switchQRType('link');

    // Status
    const linkStatus = document.getElementById('status-link');
    const qrStatus = document.getElementById('status-qr');
    if (linkStatus) linkStatus.remove();
    if (qrStatus) qrStatus.remove();

    updateQRPreview();
}

function toggleViewMode(containerId, mode) {
    const container = document.getElementById(containerId);
    if (!container) return;

    // Default para lista se não houver modo definido
    if (!mode) mode = 'list';

    const section = containerId === 'myLinksCards' ? 'Links' : 'QRs';
    const btnGrid = document.getElementById(`btnViewGrid${section}`);
    const btnList = document.getElementById(`btnViewList${section}`);

    if (mode === 'list') {
        container.classList.add('list-view');
        if (btnList) btnList.classList.add('active');
        if (btnGrid) btnGrid.classList.remove('active');
    } else {
        container.classList.remove('list-view');
        if (btnGrid) btnGrid.classList.add('active');
        if (btnList) btnList.classList.remove('active');
    }
    localStorage.setItem(`viewMode_${containerId}`, mode);
}

function applyStoredViewMode(containerId) {
    // Tenta pegar o salvo, se não existir, usa 'list'
    const storedMode = localStorage.getItem(`viewMode_${containerId}`) || 'list';
    toggleViewMode(containerId, storedMode);
}

// --- GOOGLE SIGN-IN ---
function isLocalhostEnvironment() {
    return ['localhost', '127.0.0.1'].includes(window.location.hostname);
}

function disableGoogleButtonsForLocalEnv() {
    ['googleBtn', 'googleBtnSignup'].forEach((id) => {
        const el = document.getElementById(id);
        if (!el) return;

        el.innerHTML = '<div style="width:100%; text-align:center; padding:12px 14px; border:1px solid rgba(255,255,255,0.08); border-radius:14px; color:var(--text-dim); font-size:0.85rem;">Login com Google indisponível em localhost</div>';
    });
}

function initGoogleSignIn() {
    if (isLocalhostEnvironment()) {
        disableGoogleButtonsForLocalEnv();
        return;
    }

    if (typeof google === 'undefined') {
        setTimeout(initGoogleSignIn, 100);
        return;
    }

    const resolvedGoogleClientId = googleClientId || "4942397873-238uvchi00mv3i59annesaj1kpa0o9ed.apps.googleusercontent.com";
    google.accounts.id.initialize({
        client_id: resolvedGoogleClientId,
        callback: handleCredentialResponse
    });

    google.accounts.id.renderButton(
        document.getElementById("googleBtn"),
        { theme: "filled_blue", size: "large", width: 320 }
    );

    google.accounts.id.renderButton(
        document.getElementById("googleBtnSignup"),
        { theme: "filled_blue", size: "large", width: 320 }
    );
}

async function handleCredentialResponse(response) {
    const fd = new FormData();
    fd.append('action', 'google_login');
    fd.append('id_token', response.credential);
    appendReferralCodeToFormData(fd);
    appendCsrfToFormData(fd);

    try {
        const res = await fetch('auth.php', { method: 'POST', body: fd, credentials: 'include' });
        const data = await res.json();

        if (data.success) {
            isLoggedIn = true;
            localStorage.setItem('nick', data.name);
            await checkAuth();
            closeModals();
            handlePostLoginNavigation();
            // Notificação de sucesso simplificada usando console ou alert dependendo da interface
            console.log("Login com Google realizado com sucesso!");
        } else {
            console.error(data.message || "Erro na autenticação com Google.");
        }
    } catch (err) {
        console.error("Erro de conexão ao processar login Google.");
    }
}

// --- CONTROLE DOS MODAIS ---
function openModal(id) {
    const overlay = document.getElementById('modalOverlay');
    if (!overlay) return;

    if (overlay.parentElement !== document.body) {
        document.body.appendChild(overlay);
    }

    document.body.classList.add('modal-open');
    overlay.classList.remove('hidden');
    overlay.scrollTop = 0;
    document.querySelectorAll('.modal-content').forEach(m => m.classList.add('hidden'));
    document.getElementById(id).classList.remove('hidden');
    if (id === 'modalReset') {
        ensureResetResendControls();
        updateForgotResendUI();
    }
}

function closeModals() {
    const overlay = document.getElementById('modalOverlay');
    if (overlay) {
        overlay.classList.add('hidden');
    }
    const legalModal = document.getElementById('modalLegal');
    if (legalModal) {
        legalModal.classList.add('hidden');
    }
    document.body.classList.remove('modal-open');
    document.querySelectorAll('.modal-content').forEach(m => m.classList.add('hidden'));
}

function formatForgotTimer(seconds) {
    const safe = Math.max(0, Number(seconds) || 0);
    const m = Math.floor(safe / 60).toString().padStart(2, '0');
    const s = (safe % 60).toString().padStart(2, '0');
    return `${m}:${s}`;
}

function ensureResetResendControls() {
    const modalReset = document.getElementById('modalReset');
    if (!modalReset) return;

    const hasCountdown = document.getElementById('resetResendCountdown');
    const hasResend = document.getElementById('btnResendResetCode');
    if (hasCountdown && hasResend) return;

    const links = document.createElement('div');
    links.className = 'modal-links';
    links.style.marginTop = '10px';

    const countdown = document.createElement('span');
    countdown.id = 'resetResendCountdown';
    countdown.style.color = 'var(--text-dim)';
    countdown.style.fontSize = '0.85rem';
    countdown.textContent = 'Você poderá reenviar em 02:00';

    const resendLink = document.createElement('a');
    resendLink.href = '#';
    resendLink.id = 'btnResendResetCode';
    resendLink.classList.add('disabled');
    resendLink.style.pointerEvents = 'none';
    resendLink.style.opacity = '0.5';
    resendLink.textContent = 'Reenviar código';
    resendLink.addEventListener('click', (event) => resendForgotCode(event));

    links.appendChild(countdown);
    links.appendChild(resendLink);
    modalReset.appendChild(links);
}

function updateForgotResendUI() {
    const countdownEl = document.getElementById('resetResendCountdown');
    const resendBtn = document.getElementById('btnResendResetCode');
    if (!countdownEl || !resendBtn) return;

    if (forgotResendRemainingSeconds > 0) {
        countdownEl.textContent = `Você poderá reenviar em ${formatForgotTimer(forgotResendRemainingSeconds)}`;
        resendBtn.style.pointerEvents = 'none';
        resendBtn.style.opacity = '0.5';
        resendBtn.classList.add('disabled');
    } else {
        countdownEl.textContent = 'Não recebeu? Reenvie o código.';
        resendBtn.style.pointerEvents = 'auto';
        resendBtn.style.opacity = '1';
        resendBtn.classList.remove('disabled');
    }
}

function startForgotResendTimer(seconds = 120) {
    forgotResendRemainingSeconds = Math.max(0, Number(seconds) || 0);
    if (forgotResendTimer) {
        clearInterval(forgotResendTimer);
        forgotResendTimer = null;
    }
    updateForgotResendUI();
    if (forgotResendRemainingSeconds <= 0) return;

    forgotResendTimer = setInterval(() => {
        forgotResendRemainingSeconds = Math.max(0, forgotResendRemainingSeconds - 1);
        updateForgotResendUI();
        if (forgotResendRemainingSeconds <= 0 && forgotResendTimer) {
            clearInterval(forgotResendTimer);
            forgotResendTimer = null;
        }
    }, 1000);
}

async function requestForgotCode(email, openResetAfterSuccess = true) {
    const cleanEmail = (email || '').trim();
    if (!cleanEmail) {
        alert('Informe um e-mail válido.');
        return false;
    }

    const fd = new FormData();
    fd.append('action', 'forgot');
    fd.append('email', cleanEmail);
    appendCsrfToFormData(fd);

    const res = await fetch('auth.php', { method: 'POST', body: fd, credentials: 'include' });
    const data = await res.json();
    if (data.success) {
        window.resetEmail = cleanEmail;
        ensureResetResendControls();
        startForgotResendTimer(120);
        if (openResetAfterSuccess) {
            openModal('modalReset');
        }
        announceStatus('Se o e-mail existir, enviamos um código de redefinição.');
        return true;
    }

    alert(data.message || 'Não foi possível enviar o código agora.');
    announceStatus(data.message || 'Não foi possível enviar o código agora.');
    return false;
}

function openPlanRestrictionModal(options = {}) {
    const titleEl = document.getElementById('planRestrictionTitle');
    const messageEl = document.getElementById('planRestrictionMessage');
    const ctaEl = document.getElementById('planRestrictionCta');
    const featureKey = options.feature || 'recurso';
    const title = options.title || 'Recurso indisponível no seu plano';
    const message = options.message || 'Este recurso não está disponível no seu plano atual. Faça upgrade para liberar o acesso.';
    const ctaText = options.ctaText || 'Ver planos e fazer upgrade';

    if (titleEl) titleEl.innerText = title;
    if (messageEl) messageEl.innerText = message;
    if (ctaEl) {
        ctaEl.innerHTML = `<i class="fas fa-rocket"></i> ${ctaText}`;
        ctaEl.setAttribute('data-feature', featureKey);
    }
    openModal('modalPlanRestriction');
}

function goToSubscriptionFromRestriction() {
    closeModals();
    showSection('section-subscription', new Event('click'));
}

function handlePlanRestrictionResponse(data, options = {}) {
    if (!data || (data.error !== 'quota_exceeded' && data.error !== 'feature_locked')) {
        return false;
    }
    openPlanRestrictionModal({
        feature: options.feature || 'recurso',
        title: options.title || (data.error === 'quota_exceeded' ? 'Limite do plano atingido' : 'Recurso bloqueado no seu plano'),
        message: data.message || options.message || 'Seu plano atual não permite continuar esta ação.',
        ctaText: options.ctaText || 'Ver planos'
    });
    return true;
}

// --- AUTENTICAÇÃO E INTERFACE ---
function updateUIMenu(name) {
    const menus = [document.getElementById('authMenuHeader'), document.getElementById('authMenuSidebar')];
    menus.forEach(menu => {
        if (!menu) return;
        const userWrap = document.createElement('div');
        userWrap.className = 'user-greeting-header';
        userWrap.appendChild(document.createTextNode('Olá, '));
        const nameSpan = document.createElement('span');
        nameSpan.textContent = (typeof name === 'string' && name.trim() !== '') ? name : 'Usuário';
        userWrap.appendChild(nameSpan);

        const settingsBtn = document.createElement('button');
        settingsBtn.type = 'button';
        settingsBtn.className = 'btn-settings-header';
        settingsBtn.title = 'Minha conta';
        settingsBtn.innerHTML = '<i class="fas fa-cog"></i>';
        settingsBtn.addEventListener('click', (event) => showSection('section-account-settings', event));

        const logoutBtn = document.createElement('button');
        logoutBtn.className = 'btn-logout-header';
        logoutBtn.textContent = 'Sair';
        logoutBtn.addEventListener('click', logout);

        menu.replaceChildren(userWrap, settingsBtn, logoutBtn);
    });
}

function appendCsrfToFormData(fd) {
    if (typeof csrfToken === 'string' && csrfToken) {
        fd.append('csrf_token', csrfToken);
    }
    return fd;
}

async function loadAccountSettings() {
    if (!isLoggedIn) return;
    const summaryEl = document.getElementById('settingsPlanSummary');
    const founderPriceEl = document.getElementById('settingsFounderPriceNote');
    const renewalBox = document.getElementById('settingsRenewalActions');
    const badgeEl = document.getElementById('settingsRenewalBadge');
    const btnCancel = document.getElementById('settingsBtnCancelRenewal');
    const btnCancelPurchase = document.getElementById('settingsBtnCancelPurchase');
    const btnResume = document.getElementById('settingsBtnResumeRenewal');

    try {
        const res = await fetch('auth.php?action=account_settings&t=' + Date.now(), { credentials: 'include' });
        const data = await res.json();
        if (!data.success || !data.user) {
            if (summaryEl) summaryEl.textContent = data.message || 'Não foi possível carregar suas configurações.';
            return;
        }
        const u = data.user;
        const nameInput = document.getElementById('settingsName');
        const nickInput = document.getElementById('settingsNickname');
        const emailInput = document.getElementById('settingsEmail');
        const phoneInput = document.getElementById('settingsPhone');
        if (nameInput) nameInput.value = u.name || '';
        if (nickInput) nickInput.value = u.nickname || '';
        if (emailInput) emailInput.value = u.email || '';
        if (phoneInput) phoneInput.value = u.phone || '';

        const planLabel = u.plan_name || u.plan || '—';
        let expText = '';
        if (u.plan_expires_at) {
            try {
                const d = new Date(u.plan_expires_at);
                if (!Number.isNaN(d.getTime())) {
                    expText = d.toLocaleDateString('pt-BR', { day: '2-digit', month: 'short', year: 'numeric' });
                }
            } catch (e) { /* ignore */ }
        }
        const cycleLabel = u.plan_cycle === 'annual' ? 'anual' : (u.plan_cycle === 'monthly' ? 'mensal' : '');
        if (summaryEl) {
            if (!u.current_plan_id || u.current_plan_id <= 1) {
                summaryEl.textContent = 'Plano atual: gratuito. Faça upgrade na área de planos quando quiser.';
            } else {
                summaryEl.innerHTML = `Plano atual: <strong>${planLabel}</strong>${cycleLabel ? ` · ciclo ${cycleLabel}` : ''}${expText ? ` · acesso até <strong>${expText}</strong>` : ''}.`;
            }
        }

        if (founderPriceEl) {
            if (u.is_founder && u.founder_locked_monthly != null) {
                const lockedM = Number(u.founder_locked_monthly).toFixed(2).replace('.', ',');
                const lockedA = u.founder_locked_annual != null ? Number(u.founder_locked_annual).toFixed(2).replace('.', ',') : null;
                founderPriceEl.innerHTML = `<i class="fas fa-lock"></i> Seu preço Founder garantido: <strong>R$ ${lockedM}/mês</strong>${lockedA ? ` (ou R$ ${lockedA}/ano)` : ''} — sem reajustes enquanto sua assinatura estiver ativa.`;
                founderPriceEl.classList.remove('hidden');
            } else {
                founderPriceEl.classList.add('hidden');
            }
        }

        if (renewalBox && badgeEl && btnCancel && btnResume && btnCancelPurchase) {
            if (u.can_manage_renewal) {
                renewalBox.classList.remove('hidden');
                renewalBox.style.display = 'flex';
                const on = Boolean(u.plan_auto_renew);
                badgeEl.textContent = on ? 'Renovação automática: ativa' : 'Renovação automática: desativada';
                btnCancel.style.display = on ? 'inline-flex' : 'none';
                btnCancelPurchase.style.display = 'inline-flex';
                btnResume.style.display = on ? 'none' : 'inline-flex';
            } else {
                renewalBox.classList.add('hidden');
                renewalBox.style.display = 'none';
            }
        }
    } catch (e) {
        console.error(e);
        if (summaryEl) summaryEl.textContent = 'Erro ao carregar configurações.';
    }
}

async function saveAccountProfile(e) {
    e.preventDefault();
    const fd = new FormData();
    fd.append('action', 'update_profile');
    fd.append('name', document.getElementById('settingsName')?.value?.trim() || '');
    fd.append('nickname', document.getElementById('settingsNickname')?.value?.trim() || '');
    fd.append('email', document.getElementById('settingsEmail')?.value?.trim() || '');
    fd.append('phone', document.getElementById('settingsPhone')?.value?.trim() || '');
    appendCsrfToFormData(fd);
    try {
        const res = await fetch('auth.php', { method: 'POST', body: fd, credentials: 'include' });
        const data = await res.json();
        if (data.success) {
            if (data.name) {
                localStorage.setItem('nick', data.name);
                updateUIMenu(data.name);
            }
            announceStatus('Dados salvos com sucesso.');
        } else {
            alert(data.message || 'Não foi possível salvar.');
        }
    } catch (err) {
        alert('Erro de conexão.');
    }
}

async function saveAccountPassword(e) {
    e.preventDefault();
    const cur = document.getElementById('settingsCurrentPassword')?.value || '';
    const nw = document.getElementById('settingsNewPassword')?.value || '';
    const cf = document.getElementById('settingsNewPasswordConfirm')?.value || '';
    if (nw !== cf) {
        alert('A confirmação da nova senha não confere.');
        return;
    }
    const fd = new FormData();
    fd.append('action', 'change_password');
    fd.append('current_password', cur);
    fd.append('new_password', nw);
    fd.append('new_password_confirm', cf);
    appendCsrfToFormData(fd);
    try {
        const res = await fetch('auth.php', { method: 'POST', body: fd, credentials: 'include' });
        const data = await res.json();
        if (data.success) {
            document.getElementById('settingsCurrentPassword').value = '';
            document.getElementById('settingsNewPassword').value = '';
            document.getElementById('settingsNewPasswordConfirm').value = '';
            announceStatus('Senha atualizada com sucesso.');
        } else {
            alert(data.message || 'Não foi possível alterar a senha.');
        }
    } catch (err) {
        alert('Erro de conexão.');
    }
}

function isTechnicalCancelReason(reasonCode) {
    return reasonCode === 'contains_errors' || reasonCode === 'missing_feature';
}

async function openCancellationFeedbackForm() {
    const options = [
        { code: 'expectations', label: 'Não atendeu minhas expectativas' },
        { code: 'contains_errors', label: 'Contém erros' },
        { code: 'missing_feature', label: 'Falta funcionalidade importante' },
        { code: 'high_price', label: 'Preço alto' },
        { code: 'hard_to_use', label: 'Dificuldade de uso' },
        { code: 'support_issues', label: 'Suporte não atendeu' },
        { code: 'others', label: 'Outros' },
    ];

    const reasonsHtml = options.map((opt) => `
        <label style="display:flex; align-items:flex-start; gap:8px; margin-bottom:8px; color:var(--text); text-align:left;">
            <input type="radio" name="cancelReason" value="${opt.code}" style="margin-top:3px;">
            <span>${opt.label}</span>
        </label>
    `).join('');

    const result = await Swal.fire({
        title: 'Antes de cancelar, queremos te ouvir',
        width: 760,
        showCancelButton: true,
        showDenyButton: true,
        confirmButtonText: 'Cancelar agora',
        denyButtonText: 'Testar mais um pouco',
        cancelButtonText: 'Fechar',
        confirmButtonColor: '#ef4444',
        denyButtonColor: getBrandTokens().primary,
        cancelButtonColor: '#64748b',
        background: getCurrentTheme() === 'light' ? '#ffffff' : '#0f172a',
        color: getCurrentTheme() === 'light' ? '#0f172a' : '#e2e8f0',
        html: `
            <div style="text-align:left;">
                <p style="color:var(--text-dim); margin-bottom:14px;">
                    Selecione o motivo principal do cancelamento:
                </p>
                <div id="cancelReasonList">${reasonsHtml}</div>
                <div id="cancelReasonDetailsWrap" style="display:none; margin-top:12px;">
                    <label for="cancelReasonDetails" style="display:block; color:var(--text); margin-bottom:8px; text-transform:none; letter-spacing:normal; font-size:0.9rem; font-weight:600;">Conte um pouco mais sobre o motivo:</label>
                    <textarea id="cancelReasonDetails" rows="4" style="width:100%; resize:vertical;" placeholder="Descreva o motivo do cancelamento..."></textarea>
                </div>
                <div id="cancelTechnicalMessage" style="display:none; margin-top:12px; background:var(--primary-dim); border:1px solid var(--primary-border); border-radius:10px; padding:12px; color:var(--text-soft);">
                    <strong style="color:var(--primary);">Podemos te oferecer 15 dias extras de tolerância.</strong><br>
                    Seu relato será analisado e os problemas serão corrigidos em até 72h.
                    Se em 15 dias você ainda quiser cancelar, terá reembolso integral do valor pago.
                </div>
            </div>
        `,
        didOpen: () => {
            const popup = Swal.getPopup();
            if (popup) {
                popup.querySelectorAll('#cancelReasonList label').forEach((label) => {
                    label.style.display = 'flex';
                    label.style.alignItems = 'center';
                    label.style.gap = '10px';
                    label.style.marginBottom = '10px';
                    label.style.textTransform = 'none';
                    label.style.letterSpacing = 'normal';
                    label.style.fontSize = '0.95rem';
                    label.style.fontWeight = '500';
                    label.style.color = 'var(--text)';
                    label.style.cursor = 'pointer';
                });
                popup.querySelectorAll('#cancelReasonList input[type="radio"]').forEach((radio) => {
                    radio.style.width = '18px';
                    radio.style.height = '18px';
                    radio.style.margin = '0';
                    radio.style.flex = '0 0 auto';
                    radio.style.appearance = 'auto';
                });
                popup.querySelectorAll('#cancelReasonList span').forEach((text) => {
                    text.style.flex = '1';
                    text.style.textAlign = 'left';
                    text.style.textTransform = 'none';
                    text.style.letterSpacing = 'normal';
                });
            }

            const reasonInputs = document.querySelectorAll('input[name="cancelReason"]');
            const detailsWrap = document.getElementById('cancelReasonDetailsWrap');
            const technicalMessage = document.getElementById('cancelTechnicalMessage');
            reasonInputs.forEach((input) => {
                input.addEventListener('change', () => {
                    if (detailsWrap) detailsWrap.style.display = 'block';
                    const current = document.querySelector('input[name="cancelReason"]:checked')?.value || '';
                    if (technicalMessage) {
                        technicalMessage.style.display = isTechnicalCancelReason(current) ? 'block' : 'none';
                    }
                });
            });
        },
        preConfirm: () => {
            const reasonCode = document.querySelector('input[name="cancelReason"]:checked')?.value || '';
            const reasonText = (document.getElementById('cancelReasonDetails')?.value || '').trim();
            if (!reasonCode) {
                Swal.showValidationMessage('Selecione um motivo para continuar.');
                return false;
            }
            if (reasonText.length < 5) {
                Swal.showValidationMessage('Descreva o motivo com pelo menos 5 caracteres.');
                return false;
            }
            return { reasonCode, reasonText };
        },
        preDeny: () => {
            const reasonCode = document.querySelector('input[name="cancelReason"]:checked')?.value || '';
            const reasonText = (document.getElementById('cancelReasonDetails')?.value || '').trim();
            if (!reasonCode) {
                Swal.showValidationMessage('Selecione um motivo para continuar.');
                return false;
            }
            if (reasonText.length < 5) {
                Swal.showValidationMessage('Descreva o motivo com pelo menos 5 caracteres.');
                return false;
            }
            return { reasonCode, reasonText };
        }
    });

    if (result.isConfirmed && result.value) {
        return { decision: 'cancel_now', ...result.value };
    }
    if (result.isDenied && result.value) {
        return { decision: 'test_more', ...result.value };
    }
    return null;
}

async function cancelPlanAutoRenewal() {
    if (!confirm('Desativar a renovação automática? Você mantém o acesso até o fim do período já pago.')) return;

    const fd = new FormData();
    fd.append('action', 'cancel_plan_renewal');
    appendCsrfToFormData(fd);
    try {
        const res = await fetch('auth.php', { method: 'POST', body: fd, credentials: 'include' });
        const data = await res.json();
        if (data.success) {
            await Swal.fire({
                icon: 'success',
                title: 'Renovação desativada',
                text: data.message || 'A renovação automática foi desativada com sucesso.',
                confirmButtonColor: getBrandTokens().primary,
                ...getSwalThemeOptions()
            });
            announceStatus(data.message || 'Renovação desativada.');
            loadAccountSettings();
        } else {
            await Swal.fire({
                icon: 'error',
                title: 'Não foi possível concluir',
                text: data.message || 'Não foi possível concluir.',
                confirmButtonColor: '#ef4444',
                ...getSwalThemeOptions()
            });
        }
    } catch (e) {
        await Swal.fire({
            icon: 'error',
            title: 'Erro de conexão',
            text: 'Não foi possível concluir sua solicitação agora.',
            confirmButtonColor: '#ef4444',
            ...getSwalThemeOptions()
        });
    }
}

async function cancelPurchaseWithRefund() {
    const feedback = await openCancellationFeedbackForm();
    if (!feedback) return;

    const fd = new FormData();
    fd.append('action', 'submit_cancellation_request');
    fd.append('reason_code', feedback.reasonCode);
    fd.append('reason_text', feedback.reasonText);
    fd.append('decision', feedback.decision);
    appendCsrfToFormData(fd);

    try {
        const res = await fetch('auth.php', { method: 'POST', body: fd, credentials: 'include' });
        const data = await res.json();
        if (data.success) {
            await Swal.fire({
                icon: 'success',
                title: 'Solicitação processada',
                text: data.message || 'Sua solicitação foi processada com sucesso.',
                confirmButtonColor: getBrandTokens().primary,
                ...getSwalThemeOptions()
            });
            announceStatus(data.message || 'Solicitação enviada com sucesso.');
            loadAccountSettings();
        } else {
            await Swal.fire({
                icon: 'error',
                title: 'Não foi possível concluir',
                text: data.message || 'Não foi possível concluir.',
                confirmButtonColor: '#ef4444',
                ...getSwalThemeOptions()
            });
        }
    } catch (e) {
        await Swal.fire({
            icon: 'error',
            title: 'Erro de conexão',
            text: 'Não foi possível concluir sua solicitação agora.',
            confirmButtonColor: '#ef4444',
            ...getSwalThemeOptions()
        });
    }
}

async function resumePlanAutoRenewal() {
    const fd = new FormData();
    fd.append('action', 'resume_plan_renewal');
    appendCsrfToFormData(fd);
    try {
        const res = await fetch('auth.php', { method: 'POST', body: fd, credentials: 'include' });
        const data = await res.json();
        if (data.success) {
            announceStatus(data.message || 'Renovação reativada.');
            loadAccountSettings();
        } else {
            alert(data.message || 'Não foi possível concluir.');
        }
    } catch (e) {
        alert('Erro de conexão.');
    }
}

function updateUILoggedOut() {
    const menus = [document.getElementById('authMenuHeader'), document.getElementById('authMenuSidebar')];
    menus.forEach(menu => {
        if (!menu) return;
        menu.innerHTML = `
            <a href="#" onclick="openModal('modalLogin')" class="btn-login-header"><i class="fas fa-sign-in-alt"></i> <span>Entrar</span></a>
            <a href="#" onclick="openModal('modalSignup')" class="btn-signup-header"><i class="fas fa-user-plus"></i> <span>Criar Conta</span></a>
        `;
    });
}

function isCurrentUserFreePlan() {
    if (!isLoggedIn) return false;
    const planName = String(userUsageData?.plan_name || '').toLowerCase().trim();
    if (!planName) return false;
    return planName === 'free' || planName === 'start' || planName === 'freemium';
}

function updateHomePersuasiveCard() {
    const card = document.getElementById('homePersuasiveCard');
    const badge = document.getElementById('homePersuasiveBadge');
    const title = document.getElementById('homePersuasiveTitle');
    const description = document.getElementById('homePersuasiveDescription');
    const benefits = document.getElementById('homePersuasiveBenefits');
    const ctaBtn = document.getElementById('homePersuasiveCtaBtn');
    const icon = document.getElementById('homePersuasiveIcon');

    if (!card || !badge || !title || !description || !benefits || !ctaBtn || !icon) return;

    if (!isLoggedIn) {
        card.classList.remove('hidden');
        icon.className = 'fas fa-bolt';
        badge.textContent = 'Acesso gratuito imediato';
        title.textContent = 'Crie sua conta e comece agora, sem custo';
        description.textContent = 'Ganhe velocidade para divulgar seus links, fortalecer sua marca e acompanhar desempenho com uma plataforma pronta para escalar.';
        benefits.innerHTML = `
            <li>Links curtos profissionais para campanhas, redes sociais e WhatsApp</li>
            <li>QR Codes dinâmicos para alterar o destino sem trocar o material impresso</li>
            <li>Dashboard simples com dados para otimizar cliques e conversões</li>
        `;
        ctaBtn.innerHTML = 'Criar conta grátis <i class="fas fa-user-plus"></i>';
        ctaBtn.onclick = () => openModal('modalSignup');
        return;
    }

    if (isCurrentUserFreePlan()) {
        card.classList.remove('hidden');
        icon.className = 'fas fa-crown';
        badge.textContent = 'Destrave resultados maiores';
        title.textContent = 'Seu plano free já prova valor. Agora é hora de acelerar.';
        description.textContent = 'Assine para liberar recursos avançados, ganhar produtividade e aumentar a previsibilidade das suas campanhas digitais.';
        benefits.innerHTML = `
            <li>Mais capacidade para links e QR Codes, sem travar crescimento</li>
            <li>Recursos premium para campanhas, analytics e personalização profissional</li>
            <li>Melhor experiência para operar, analisar e vender com mais confiança</li>
        `;
        ctaBtn.innerHTML = 'Conhecer planos <i class="fas fa-rocket"></i>';
        ctaBtn.onclick = (event) => showSection('section-subscription', event);
        return;
    }

    card.classList.add('hidden');
}

async function checkAuth() {
    try {
        const res = await fetch('auth.php?action=status&t=' + new Date().getTime(), { credentials: 'include' });
        const data = await res.json();
        isLoggedIn = data.logged_in;
        csrfToken = data.csrf_token || csrfToken;
        googleClientId = data.google_client_id || googleClientId;
        captchaSiteKey = data.captcha_site_key || captchaSiteKey;
        if (data.logged_in) {
            currentEmailVerified = data.email_verified !== false;
            currentUserIsAdmin = Boolean(data.is_admin);
            currentUserIsMasterAdminDashboard = Boolean(data.is_master_admin_dashboard);
            currentUserIsAffiliate = Boolean(data.is_affiliate);
            currentAffiliateLink = data.affiliate_link || '';
            currentTeamProfile = data.team_profile || 'editor';
            currentTeamCanManageMembers = Boolean(data.team_can_manage_members);
            currentTeamCanEditContent = (data.team_can_edit_content !== false);
            localStorage.setItem('nick', data.name);
            updateUIMenu(data.name);
            userFeatures = data.features || {};

            if (data.is_admin) {
                document.getElementById('menuBtnAdminHeader')?.classList.remove('hidden');
                document.getElementById('menuBtnAdminSidebar')?.classList.remove('hidden');
            } else {
                document.getElementById('menuBtnAdminHeader')?.classList.add('hidden');
                document.getElementById('menuBtnAdminSidebar')?.classList.add('hidden');
            }

            if (currentUserIsMasterAdminDashboard) {
                document.getElementById('btnAdminGlobalDashboardTab')?.classList.remove('hidden');
            } else {
                document.getElementById('btnAdminGlobalDashboardTab')?.classList.add('hidden');
            }

            if (currentUserIsAffiliate) {
                document.getElementById('menuBtnAffiliateHeader')?.classList.remove('hidden');
                document.getElementById('menuBtnAffiliateSidebar')?.classList.remove('hidden');
                const affInput = document.getElementById('affiliateLinkInput');
                if (affInput) affInput.value = currentAffiliateLink;
            } else {
                document.getElementById('menuBtnAffiliateHeader')?.classList.add('hidden');
                document.getElementById('menuBtnAffiliateSidebar')?.classList.add('hidden');
            }

            applyFeatureLocks();
            applyTeamRoleUiRestrictions();
            loadUsageSummary();
            updateHomePersuasiveCard();
            updateEmailVerificationBanner();
        } else {
            currentEmailVerified = true;
            currentUserIsAdmin = false;
            currentUserIsMasterAdminDashboard = false;
            currentUserIsAffiliate = false;
            currentAffiliateLink = '';
            currentTeamProfile = 'editor';
            currentTeamCanManageMembers = false;
            currentTeamCanEditContent = true;
            userFeatures = {};
            userUsageData = null;
            pendingSectionAfterLogin = null;
            clearFeatureLocks();
            localStorage.removeItem('nick');
            updateUILoggedOut();
            document.getElementById('menuBtnAdminHeader')?.classList.add('hidden');
            document.getElementById('menuBtnAdminSidebar')?.classList.add('hidden');
            document.getElementById('btnAdminGlobalDashboardTab')?.classList.add('hidden');
            document.getElementById('menuBtnAffiliateHeader')?.classList.add('hidden');
            document.getElementById('menuBtnAffiliateSidebar')?.classList.add('hidden');
            const panel = document.getElementById('userUsagePanel');
            if (panel) panel.innerHTML = '';
            restoreGuestView();
            applyTeamRoleUiRestrictions();
            updateHomePersuasiveCard();
            updateEmailVerificationBanner(false);
            if (data.banned && data.message) {
                alert(data.message);
            }
        }
    } catch (e) {
        currentUserIsAdmin = false;
        currentUserIsMasterAdminDashboard = false;
        currentUserIsAffiliate = false;
        currentAffiliateLink = '';
        currentTeamProfile = 'editor';
        currentTeamCanManageMembers = false;
        currentTeamCanEditContent = true;
        userFeatures = {};
        userUsageData = null;
        clearFeatureLocks();
        const sn = localStorage.getItem('nick');
        if (sn) { isLoggedIn = true; updateUIMenu(sn); }
        else {
            isLoggedIn = false;
            pendingSectionAfterLogin = null;
            updateUILoggedOut();
            restoreGuestView();
        }
        applyTeamRoleUiRestrictions();
        updateHomePersuasiveCard();
    }
}

async function handleAuth(e, action) {
    e.preventDefault();
    const fd = new FormData();
    fd.append('action', action);

    if (action === 'signup') {
        fd.append('name', document.getElementById('signName').value);
        fd.append('nickname', document.getElementById('signNickname').value);
        fd.append('email', document.getElementById('signEmail').value);
        fd.append('phone', document.getElementById('signPhone').value);
        fd.append('password', document.getElementById('signPass').value);
        appendReferralCodeToFormData(fd);
    } else {
        fd.append('email', document.getElementById('loginEmail').value);
        fd.append('password', document.getElementById('loginPass').value);
    }
    appendCsrfToFormData(fd);
    if (captchaSiteKey && window.hcaptcha) {
        const widget = document.querySelector('.h-captcha');
        const token = widget ? hcaptcha.getResponse(widget) : '';
        if (token) fd.append('captcha_token', token);
    }

    try {
        const res = await fetch('auth.php', { method: 'POST', body: fd, credentials: 'include' });
        const data = await res.json();
        if (data.success) {
            isLoggedIn = true;
            localStorage.setItem('nick', data.name);
            await checkAuth(); // Carrega permissões e resumo de uso
            closeModals();
            handlePostLoginNavigation();
            if (action === 'signup') {
                if (data.email_verification_required) {
                    alert('Conta criada! Verifique seu e-mail antes de criar links.');
                }
            }
            announceStatus('Autenticação concluída com sucesso.');
        } else {
            alert(data.message || 'Não foi possível concluir a autenticação.');
            console.error(data.message);
            announceStatus(data.message || 'Não foi possível concluir a autenticação.');
        }
    } catch (err) {
        alert('Erro de conexão com o servidor.');
        console.error("Erro de conexão com o servidor.");
        announceStatus('Erro de conexão com o servidor.');
    }
}

function announceStatus(message) {
    const region = document.getElementById('a11yStatus');
    if (!region || !message) return;
    region.textContent = '';
    // Pequeno delay para garantir anúncio repetido em leitores de tela.
    setTimeout(() => {
        region.textContent = message;
    }, 30);
}

async function handleForgot(e) {
    e.preventDefault();
    const btn = document.getElementById('btnForgot');
    const email = document.getElementById('forgotEmail').value;
    btn.innerHTML = 'Enviando...'; btn.disabled = true;

    try {
        await requestForgotCode(email, true);
    } catch (err) {
        alert('Erro de conexão ao solicitar o código.');
    } finally {
        btn.innerHTML = 'Enviar Código'; btn.disabled = false;
    }
}

async function resendForgotCode(e) {
    if (e && typeof e.preventDefault === 'function') e.preventDefault();
    if (forgotResendRemainingSeconds > 0) return;
    if (!window.resetEmail) {
        alert('Informe seu e-mail para receber o código.');
        openModal('modalForgot');
        return;
    }
    try {
        await requestForgotCode(window.resetEmail, false);
    } catch (err) {
        alert('Erro de conexão ao reenviar o código.');
    }
}

async function handleReset(e) {
    e.preventDefault();
    const fd = new FormData();
    fd.append('action', 'reset'); fd.append('email', window.resetEmail);
    fd.append('code', document.getElementById('resetCode').value);
    fd.append('new_password', document.getElementById('resetPass').value);
    appendCsrfToFormData(fd);

    const res = await fetch('auth.php', { method: 'POST', body: fd, credentials: 'include' });
    const data = await res.json();
    if (data.success) {
        openModal('modalLogin');
    } else { console.error(data.message); }
}

async function logout() {
    await fetch('auth.php?action=logout', { credentials: 'include' });
    isLoggedIn = false;
    currentUserIsAffiliate = false;
    currentAffiliateLink = '';
    userFeatures = {};
    userUsageData = null;
    pendingSectionAfterLogin = null;
    clearFeatureLocks();
    localStorage.removeItem('nick');
    updateUILoggedOut();
    document.getElementById('menuBtnAdminHeader')?.classList.add('hidden');
    document.getElementById('btnAdminGlobalDashboardTab')?.classList.add('hidden');
    document.getElementById('menuBtnAffiliateHeader')?.classList.add('hidden');
    document.getElementById('menuBtnAffiliateSidebar')?.classList.add('hidden');
    const panel = document.getElementById('userUsagePanel');
    if (panel) panel.innerHTML = '';
    showSection(guestFallbackSection);
}

async function loadUsageSummary() {
    if (!isLoggedIn) return;
    try {
        const res = await fetch('auth.php?action=usage_summary', { credentials: 'include' });
        const data = await res.json();
        if (data.success) {
            userUsageData = data.usage;
            renderUsagePanel(data.usage);
        }
    } catch (e) { console.error("Erro ao carregar resumo de uso", e); }
    updateHomePersuasiveCard();
}

function renderUsagePanel(usage) {
    const panel = document.getElementById('userUsagePanel');
    if (!panel) return;

    if (usage.is_admin) {
        panel.innerHTML = `
            <div class="usage-admin-box">
                <span><i class="fas fa-user-shield"></i> ADMINISTRADOR</span>
                <p style="font-size: 0.75rem; color: var(--text-dim); margin-top: 5px;">Acesso Ilimitado</p>
            </div>
        `;
        return;
    }

    const getStatusClass = (percent) => {
        if (percent >= 90) return 'danger';
        if (percent >= 70) return 'warning';
        return '';
    };

    const linksStatus = getStatusClass(usage.links.percent);
    const qrStatus = getStatusClass(usage.qr_codes.percent);

    const remainingLinksText = usage.links.total >= 9999 ? 'Ilimitado' : (usage.links.remaining === 0 ? 'Limite atingido' : `Restam <b>${usage.links.remaining}</b>`);
    const remainingQrsText = usage.qr_codes.total >= 9999 ? 'Ilimitado' : (usage.qr_codes.remaining === 0 ? 'Limite atingido' : `Restam <b>${usage.qr_codes.remaining}</b>`);

    panel.innerHTML = `
        <div class="usage-card">
            <div class="usage-card-header">
                <h4>Seu Plano</h4>
                <span class="usage-plan-badge">${usage.plan_name}</span>
            </div>
            
            <div class="usage-item">
                <div class="usage-info">
                    <span class="usage-label">Links</span>
                    <span class="usage-count"><span>${usage.links.used}</span> / ${usage.links.total >= 9999 ? '∞' : usage.links.total}</span>
                </div>
                <div class="usage-progress-bg">
                    <div class="usage-progress-fill ${linksStatus}" style="width: ${usage.links.percent}%"></div>
                </div>
                <div class="usage-remaining ${usage.links.remaining === 0 ? 'limit-reached' : ''}">${remainingLinksText}</div>
            </div>

            <div class="usage-item">
                <div class="usage-info">
                    <span class="usage-label">QR Codes</span>
                    <span class="usage-count"><span>${usage.qr_codes.used}</span> / ${usage.qr_codes.total >= 9999 ? '∞' : usage.qr_codes.total}</span>
                </div>
                <div class="usage-progress-bg">
                    <div class="usage-progress-fill ${qrStatus}" style="width: ${usage.qr_codes.percent}%"></div>
                </div>
                <div class="usage-remaining ${usage.qr_codes.remaining === 0 ? 'limit-reached' : ''}">${remainingQrsText}</div>
            </div>

            <button onclick="showSection('section-subscription', event)" class="btn-upgrade-sidebar">
                <i class="fas fa-rocket"></i> Fazer upgrade
            </button>
        </div>
    `;
}

// --- ENCURTADOR DE LINKS ---
function updateEmailVerificationBanner(show = true) {
    let banner = document.getElementById('emailVerifyBanner');
    if (!banner) {
        banner = document.createElement('div');
        banner.id = 'emailVerifyBanner';
        banner.className = 'hidden';
        banner.style.cssText = 'background:#7c2d12;color:#fff;padding:12px 16px;border-radius:8px;margin:12px 0;text-align:center;';
        const host = document.getElementById('section-home') || document.querySelector('.main-content');
        if (host) host.prepend(banner);
    }
    if (!show || currentEmailVerified) {
        banner.classList.add('hidden');
        banner.innerHTML = '';
        return;
    }
    banner.classList.remove('hidden');
    banner.innerHTML = 'Verifique seu e-mail para criar links. <button type="button" id="btnResendVerifyEmail" style="margin-left:8px;padding:4px 10px;border:0;border-radius:6px;cursor:pointer;">Reenviar</button>';
    document.getElementById('btnResendVerifyEmail')?.addEventListener('click', resendVerifyEmail, { once: true });
}

async function resendVerifyEmail() {
    const fd = new FormData();
    fd.append('action', 'resend_verify_email');
    appendCsrfToFormData(fd);
    try {
        const res = await fetch('auth.php', { method: 'POST', body: fd, credentials: 'include' });
        const data = await res.json();
        alert(data.message || 'Solicitação enviada.');
    } catch (e) {
        alert('Erro ao reenviar e-mail.');
    }
}

async function processLink() {
    if (!isLoggedIn) return openModal('modalLogin');
    if (!currentEmailVerified) {
        alert('Verifique seu e-mail antes de criar links.');
        updateEmailVerificationBanner(true);
        return;
    }
    if (!guardTeamEditPermission()) return;

    let longInput = document.getElementById('longUrl');
    let longValue = longInput ? longInput.value.trim() : '';

    if (currentLinkMode === 'simple' && !longValue) return;

    if (currentLinkMode === 'simple') {
        // Auto Adiciona https:// se não houver protocolo
        if (!longValue.startsWith('http://') && !longValue.startsWith('https://')) {
            longValue = 'https://' + longValue;
            longInput.value = longValue;
        }
    }

    let long = longValue; // Captura a URL pura
    const subSelect = document.getElementById('subdomainSelect');
    const sub = subSelect ? subSelect.value : 'ROOT';
    const path = document.getElementById('pathSlug') ? document.getElementById('pathSlug').value.toLowerCase().trim() : '';
    const btn = document.getElementById('btnMain');

    if (currentLinkMode === 'simple' && (!long || !path)) return;
    if (currentLinkMode === 'ab' && !path) return;

    btn.disabled = true; btn.innerHTML = 'Gerando... <i class="fas fa-spinner fa-spin"></i>';

    const fd = new FormData();

    if (currentLinkMode === 'ab') {
        const totalW = abVariants.filter(x => x.status === 'active').reduce((acc, curr) => acc + parseInt(curr.weight), 0);
        if (totalW !== 100) {
            showStatus('link', 'A soma dos pesos das variantes ativas deve ser exatamente 100%.', 'error');
            return;
        }
        const activeCount = abVariants.filter(x => x.status === 'active' && x.url.trim() !== '').length;
        if (activeCount < 2) {
            showStatus('link', 'Preencha as URLs de pelo menos 2 variantes ativas.', 'error');
            return;
        }

        // Define fallback URL as the first active variant URL logic
        const fallbackVar = abVariants.find(x => x.status === 'active');
        long = fallbackVar ? fallbackVar.url : '';

        fd.append('is_ab_test', '1');
        fd.append('variants', JSON.stringify(abVariants));
    }

    fd.append('url', long);
    fd.append('subdomain', sub);
    fd.append('path', path);
    fd.append('type', 'link');

    try {
        const res = await fetch('api.php', { method: 'POST', body: fd, credentials: 'include' });
        const data = await res.json();
        if (data.success) {
            const hasSub = sub && sub !== 'ROOT';
            const subPrefix = hasSub ? sub + '.' : '';
            const generatedShortUrl = data.short_url || `${protocol}//${subPrefix}${domain}/${path}`;
            const generatedTitle = data.title || 'Página sem título';
            const generatedDestination = data.original_url || long;
            openCreatedLinkModal({
                title: generatedTitle,
                destination: generatedDestination,
                shortUrl: generatedShortUrl
            });
            clearLinkCreationFields();
            showStatus('link', 'Link gerado com sucesso!', 'success');
            loadUsageSummary(); // Atualiza painel de limites
        } else {
            console.error(data.message);
            showStatus('link', data.message, 'error');
            if (data.error === 'quota_exceeded' || data.error === 'feature_locked') {
                openPlanRestrictionModal({
                    feature: 'custom_domain',
                    title: data.error === 'quota_exceeded' ? 'Limite do plano atingido' : 'Recurso bloqueado no seu plano',
                    message: data.message || 'Seu plano atual não permite continuar esta ação.',
                    ctaText: 'Ver planos'
                });
            }
        }
    } catch (e) {
        console.error("Erro ao salvar o link.", e);
        showStatus('link', 'Erro de conexão com o servidor.', 'error');
    }
    finally { btn.disabled = false; btn.innerHTML = 'Gerar Link Agora <i class="fas fa-magic"></i>'; }
}

function clearLinkCreationFields() {
    const longInput = document.getElementById('longUrl');
    const subSelect = document.getElementById('subdomainSelect');

    if (currentLinkMode === 'simple' && longInput) {
        longInput.value = '';
    }

    if (currentLinkMode === 'ab') {
        abVariants = abVariants.map(v => ({ ...v, url: '' }));
        renderAbVariants();
    }

    if (subSelect) {
        subSelect.value = 'ROOT';
    }

    generateRandomSlug();
    updateShortPreview();
}

function showStatus(type, msg, status) {
    let btnId = 'btnMain';
    if (type === 'link') btnId = 'btnMain';
    else if (type === 'qr') btnId = 'btnSaveQR';
    else if (type === 'campaign') btnId = 'btnApplyCampaign';

    const btn = document.getElementById(btnId);
    if (!btn) return;

    // Remove status anterior se houver
    const oldStatus = document.getElementById(`status-${type}`);
    if (oldStatus) oldStatus.remove();

    const statusEl = document.createElement('div');
    statusEl.id = `status-${type}`;
    statusEl.style.marginTop = '10px';
    statusEl.style.fontSize = '0.85rem';
    statusEl.style.fontWeight = '600';
    statusEl.style.textAlign = 'center';
    statusEl.style.padding = '10px';
    statusEl.style.borderRadius = '8px';

    if (status === 'success') {
        statusEl.style.background = 'var(--primary-surface-strong)';
        statusEl.style.color = 'var(--primary)';
        statusEl.innerHTML = `<i class="fas fa-check-circle"></i> ${msg}`;
    } else {
        statusEl.style.background = 'rgba(239, 68, 68, 0.1)';
        statusEl.style.color = '#ef4444';
        statusEl.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${msg}`;
    }

    btn.parentNode.insertBefore(statusEl, btn.nextSibling);

    // Some após 4 segundos se for sucesso, ou mantém se for erro crítico
    if (status === 'success') {
        setTimeout(() => { if (statusEl) statusEl.remove(); }, 4000);
    }
}

function copyLink() {
    const input = document.getElementById("shortUrl");
    input.select(); navigator.clipboard.writeText(input.value);
    const btn = document.getElementById('btnCopy');
    btn.innerText = "Copiado!"; setTimeout(() => btn.innerText = "Copiar", 2000);
}

function openCreatedLinkModal(payload) {
    const titleEl = document.getElementById('createdLinkTitle');
    const destinationEl = document.getElementById('createdLinkDestination');
    const shortEl = document.getElementById('createdLinkShort');
    if (!titleEl || !destinationEl || !shortEl) return;

    titleEl.textContent = payload.title || 'Título não disponível';
    destinationEl.textContent = payload.destination || '-';
    shortEl.textContent = payload.shortUrl || '-';

    const copyBtn = document.getElementById('btnCopyCreatedLink');
    if (copyBtn) {
        copyBtn.innerHTML = '<i class="fas fa-copy"></i> Copiar';
    }

    openModal('modalLinkCreated');
}

function copyCreatedLink() {
    const shortEl = document.getElementById('createdLinkShort');
    const copyBtn = document.getElementById('btnCopyCreatedLink');
    if (!shortEl || !copyBtn) return;

    const text = (shortEl.textContent || '').trim();
    if (!text || text === '-') return;

    navigator.clipboard.writeText(text).then(() => {
        copyBtn.innerHTML = '<i class="fas fa-check"></i> Copiado!';
        setTimeout(() => {
            copyBtn.innerHTML = '<i class="fas fa-copy"></i> Copiar';
        }, 2000);
    }).catch(() => { });
}
 
function shareCreatedLink() {
    const shortEl = document.getElementById('createdLinkShort');
    if (!shortEl) return;

    const url = (shortEl.textContent || '').trim();
    if (!url || url === '-') return;

    if (navigator.share) {
        navigator.share({ title: 'LinkNeo.pro', url: url }).catch(() => { });
        return;
    }

    navigator.clipboard.writeText(url).then(() => {
        showStatus('link', 'Link copiado para a área de transferência.', 'success');
    }).catch(() => { });
}

function goToMyLinksFromCreatedModal() {
    closeModals();
    showSection('section-mylinks');
}


// --- GERENCIAMENTO: MEUS LINKS ---
function isItemExpiredVisual(link) {
    const nowTime = new Date().getTime();
    const endDate = link.end_date ? new Date(link.end_date).getTime() : null;
    const linkClicks = parseInt(link.clicks || 0, 10) || 0;
    const expireClicks = parseInt(link.expire_clicks || 0, 10) || 0;
    return (endDate && endDate < nowTime) || (expireClicks > 0 && linkClicks >= expireClicks);
}

function buildRuleBadges(link) {
    let ruleBadges = '';
    const nowTime = new Date().getTime();
    const startDate = link.start_date ? new Date(link.start_date).getTime() : null;
    const endDate = link.end_date ? new Date(link.end_date).getTime() : null;
    const linkClicks = parseInt(link.clicks || 0, 10) || 0;
    const expireClicks = parseInt(link.expire_clicks || 0, 10) || 0;
    const triggerClicks = parseInt(link.trigger_clicks || 0, 10) || 0;

    if (startDate && startDate > nowTime) {
        ruleBadges += `<span style="font-size: 0.65rem; background: rgba(59, 130, 246, 0.2); color: #3b82f6; padding: 2px 8px; border-radius: 12px; margin-left: 5px; font-weight: bold;"><i class="fas fa-clock"></i> Agendado</span> `;
    }
    if ((endDate && endDate < nowTime) || (expireClicks && linkClicks >= expireClicks)) {
        ruleBadges += `<span style="font-size: 0.65rem; background: rgba(239, 68, 68, 0.2); color: #ef4444; padding: 2px 8px; border-radius: 12px; margin-left: 5px; font-weight: bold;"><i class="fas fa-times-circle"></i> Expirado</span> `;
    } else if (endDate && endDate >= nowTime) {
        ruleBadges += `<span style="font-size: 0.65rem; background: rgba(245, 158, 11, 0.2); color: #f59e0b; padding: 2px 8px; border-radius: 12px; margin-left: 5px; font-weight: bold;"><i class="fas fa-hourglass-half"></i> Expira em breve</span> `;
    }
    if (link.status === 'paused') {
        ruleBadges += `<span class="badge-paused"><i class="fas fa-pause-circle"></i> Pausado</span> `;
    }
    if (triggerClicks && linkClicks >= triggerClicks) {
        ruleBadges += `<span style="font-size: 0.65rem; background: rgba(139, 92, 246, 0.2); color: #8b5cf6; padding: 2px 8px; border-radius: 12px; margin-left: 5px; font-weight: bold;"><i class="fas fa-exchange-alt"></i> Destino Trocado</span> `;
    }
    return ruleBadges;
}

function renderItemTagsChips(link) {
    const tags = Array.isArray(link.tags) ? link.tags : [];
    if (tags.length === 0) return '';
    const tagsHtml = tags.map(tag => `<span class="item-tag-chip">#${tag.name}</span>`).join('');
    return `<div class="item-tags-row">${tagsHtml}</div>`;
}

function getFilterElementsByType(type) {
    if (type === 'link') {
        return {
            from: document.getElementById('linksFilterDateFrom'),
            to: document.getElementById('linksFilterDateTo'),
            status: document.getElementById('linksFilterStatus'),
            tags: document.getElementById('linksFilterTags')
        };
    }
    return {
        from: document.getElementById('qrsFilterDateFrom'),
        to: document.getElementById('qrsFilterDateTo'),
        status: document.getElementById('qrsFilterStatus'),
        tags: document.getElementById('qrsFilterTags')
    };
}

function readListFiltersFromUI(type) {
    const controls = getFilterElementsByType(type);
    if (!controls) return listFiltersState[type];

    const selectedTagIds = [];
    if (controls.tags) {
        Array.from(controls.tags.selectedOptions || []).forEach(opt => {
            const id = parseInt(opt.value, 10);
            if (id > 0) selectedTagIds.push(id);
        });
    }

    listFiltersState[type] = {
        createdFrom: controls.from ? controls.from.value : '',
        createdTo: controls.to ? controls.to.value : '',
        status: controls.status ? controls.status.value : 'all',
        tagIds: selectedTagIds
    };
    return listFiltersState[type];
}

function populateTagFilters(type, tags) {
    const controls = getFilterElementsByType(type);
    if (!controls || !controls.tags) return;
    const select = controls.tags;
    const previous = new Set((listFiltersState[type]?.tagIds || []).map(String));
    const rows = Array.isArray(tags) ? tags : [];

    select.innerHTML = '';
    rows.forEach(row => {
        const option = document.createElement('option');
        option.value = String(row.id);
        option.textContent = `${row.name} (${row.usage_count || 0})`;
        if (previous.has(String(row.id))) option.selected = true;
        select.appendChild(option);
    });
    readListFiltersFromUI(type);
}

function applyListFilters(type) {
    readListFiltersFromUI(type);
    if (type === 'qr') {
        loadQRLinks();
    } else {
        loadMyLinks();
    }
}

function clearListFilters(type) {
    const controls = getFilterElementsByType(type);
    if (controls.from) controls.from.value = '';
    if (controls.to) controls.to.value = '';
    if (controls.status) controls.status.value = 'all';
    if (controls.tags) Array.from(controls.tags.options).forEach(opt => { opt.selected = false; });
    listFiltersState[type] = { createdFrom: '', createdTo: '', status: 'all', tagIds: [] };
    applyListFilters(type);
}

function getFullShortUrl(link) {
    const hasSub = link.subdomain !== 'ROOT' && link.subdomain !== '';
    return `${protocol}//${hasSub ? link.subdomain + '.' : ''}${domain}/${link.path}`;
}

function renderLinkCardItem(link) {
    const canEdit = canCurrentUserEditContent();
    const fullUrl = getFullShortUrl(link);
    const hostname = (() => {
        try { return new URL(link.original_url).hostname; } catch (e) { return domain; }
    })();
    const favicon = `https://www.google.com/s2/favicons?domain=${hostname}&sz=64`;
    const ruleBadges = buildRuleBadges(link);

    let badgeAB = '';
    if (link.is_ab_test == 1) {
        const numVariants = link.variants ? link.variants.length : 0;
        badgeAB = `<span style="font-size: 0.65rem; background: var(--primary-surface-strong); color: var(--primary); padding: 2px 8px; border-radius: 12px; margin-left: 10px; font-weight: bold; border: 1px solid var(--primary);"><i class="fas fa-vial"></i> A/B (${numVariants})</span>`;
    }

    return `
        <div class="link-card ${link.status === 'paused' ? 'paused' : ''}" id="card-link-${link.id}" oncontextmenu="openItemContextMenu(event, ${link.id}, 'link')">
            <div class="list-item-main" onclick="toggleItemExpansion(${link.id}, 'link')">
                <div class="link-card-header">
                    <div class="link-card-icon">
                        <img src="${favicon}" alt="icon" onerror="this.src='https://cdn-icons-png.flaticon.com/512/1243/1243933.png'">
                    </div>
                    <div class="link-card-title-group">
                        <span class="link-card-title">${link.title || 'Página sem título'} ${badgeAB} ${ruleBadges}</span>
                        <span class="link-card-date"><i class="far fa-calendar-alt"></i> ${link.data_criacao}</span>
                    </div>
                </div>

                <div class="link-card-body">
                    <a href="${fullUrl}" target="_blank" onclick="event.stopPropagation()" class="link-card-short">${fullUrl.replace('https://', '').replace('http://', '')}</a>
                    <span class="link-card-dest" title="${link.original_url}">${link.original_url}</span>
                    ${renderItemTagsChips(link)}
                </div>

                <div class="link-card-footer">
                    <div class="badge-clicks">${link.clicks_digital} <i class="fas fa-mouse-pointer"></i></div>
                    <div class="link-card-actions">
                        ${canEdit ? `<button onclick="event.stopPropagation(); editItemTagsPrompt(${link.id}, 'link')" class="btn-action" style="color:#14b8a6;" title="Editar tags"><i class="fas fa-tags"></i></button>` : ''}
                        <button onclick="event.stopPropagation(); toggleItemExpansion(${link.id}, 'link', 'rules')" class="btn-action" style="color:#8b5cf6;" title="Regras e Validade" data-feature="rules"><i class="fas fa-robot"></i></button>
                        <button onclick="event.stopPropagation(); toggleItemExpansion(${link.id}, 'link', 'tracking')" class="btn-action" style="color:#f59e0b;" title="Tracking Interno e Retargeting" data-feature="tracking"><i class="fas fa-bullseye"></i></button>
                        ${canEdit ? `<button onclick="event.stopPropagation(); toggleItemExpansion(${link.id}, 'link', 'edit')" class="btn-action btn-edit" title="Editar"><i class="fas fa-pen"></i></button>` : ''}
                        <button onclick="event.stopPropagation(); toggleItemExpansion(${link.id}, 'link', 'stats')" class="btn-action btn-play" title="Analytics" data-feature="analytics"><i class="fas fa-chart-line"></i></button>
                        ${canEdit ? `<button onclick="event.stopPropagation(); toggleStatus(${link.id}, '${link.status}')" class="btn-action" style="color:${link.status === 'paused' ? '#10b981' : '#f59e0b'};" title="${link.status === 'paused' ? 'Ativar Link' : 'Pausar Link'}"><i class="fas ${link.status === 'paused' ? 'fa-play' : 'fa-pause'}"></i></button>` : ''}
                        ${canEdit ? `<button onclick="event.stopPropagation(); deleteLink(${link.id})" class="btn-action btn-delete" title="Excluir"><i class="fas fa-trash"></i></button>` : ''}
                    </div>
                </div>
            </div>
            <div id="expand-link-${link.id}" class="item-expand-container"></div>
        </div>
    `;
}

function renderQrCardItem(link) {
    const canEdit = canCurrentUserEditContent();
    const fullUrl = getFullShortUrl(link);
    const qrUrl = fullUrl + '?src=qr';
    const ruleBadges = buildRuleBadges(link);
    return `
        <div class="link-card ${link.status === 'paused' ? 'paused' : ''}" id="card-qr-${link.id}" oncontextmenu="openItemContextMenu(event, ${link.id}, 'qr')">
            <div class="list-item-main" onclick="toggleItemExpansion(${link.id}, 'qr')">
                <div class="qr-card-layout">
                    <div class="qr-card-preview">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=${encodeURIComponent(qrUrl)}" alt="QR Code">
                    </div>
                    <div class="qr-card-info">
                        <div class="link-card-header">
                            <div class="link-card-title-group">
                                <span class="link-card-title">${link.title || 'QR Code Dinâmico'} ${ruleBadges}</span>
                                <span class="link-card-date"><i class="far fa-calendar-alt"></i> ${link.data_criacao}</span>
                            </div>
                        </div>
                        <div class="link-card-body">
                            <a href="${fullUrl}" target="_blank" onclick="event.stopPropagation()" class="link-card-short">${fullUrl.replace('https://', '').replace('http://', '')}</a>
                            <span class="link-card-dest" title="${link.original_url}">${link.original_url}</span>
                            ${renderItemTagsChips(link)}
                        </div>
                        <div class="link-card-footer">
                            <div class="badge-clicks">${link.clicks_qr} <i class="fas fa-qrcode"></i></div>
                            <div class="link-card-actions">
                                ${canEdit ? `<button onclick="event.stopPropagation(); editItemTagsPrompt(${link.id}, 'qr')" class="btn-action" style="color:#14b8a6;" title="Editar tags"><i class="fas fa-tags"></i></button>` : ''}
                                <button onclick="event.stopPropagation(); focusQR('${qrUrl}')" class="btn-action btn-copy" title="Personalizar"><i class="fas fa-paint-brush"></i></button>
                                <button onclick="event.stopPropagation(); toggleItemExpansion(${link.id}, 'qr', 'rules')" class="btn-action" style="color:#8b5cf6;" title="Regras e Validade" data-feature="rules"><i class="fas fa-robot"></i></button>
                                <button onclick="event.stopPropagation(); toggleItemExpansion(${link.id}, 'qr', 'tracking')" class="btn-action" style="color:#f59e0b;" title="Tracking Interno e Retargeting" data-feature="tracking"><i class="fas fa-bullseye"></i></button>
                                ${canEdit ? `<button onclick="event.stopPropagation(); toggleItemExpansion(${link.id}, 'qr', 'edit')" class="btn-action btn-edit" title="Editar"><i class="fas fa-pen"></i></button>` : ''}
                                <button onclick="event.stopPropagation(); toggleItemExpansion(${link.id}, 'qr', 'stats')" class="btn-action btn-play" title="Analytics" data-feature="analytics"><i class="fas fa-chart-line"></i></button>
                                ${canEdit ? `<button onclick="event.stopPropagation(); toggleStatus(${link.id}, '${link.status}')" class="btn-action" style="color:${link.status === 'paused' ? '#10b981' : '#f59e0b'};" title="${link.status === 'paused' ? 'Ativar QR' : 'Pausar QR'}"><i class="fas ${link.status === 'paused' ? 'fa-play' : 'fa-pause'}"></i></button>` : ''}
                                <button onclick="event.stopPropagation(); downloadQRFromList('${qrUrl}', '${link.path}')" class="btn-action" style="color:var(--primary);" title="Baixar QR Code"><i class="fas fa-download"></i></button>
                                ${canEdit ? `<button onclick="event.stopPropagation(); deleteLink(${link.id})" class="btn-action btn-delete" title="Excluir"><i class="fas fa-trash"></i></button>` : ''}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div id="expand-qr-${link.id}" class="item-expand-container"></div>
        </div>
    `;
}

function renderFolderedItems(container, type, items, folders, renderer) {
    const rows = Array.isArray(items) ? items : [];
    const folderRows = Array.isArray(folders) ? folders : [];
    const itemsByFolder = {};
    const noFolder = [];

    rows.forEach(item => {
        const folderId = parseInt(item.folder_id || 0, 10) || 0;
        if (folderId > 0) {
            if (!itemsByFolder[folderId]) itemsByFolder[folderId] = [];
            itemsByFolder[folderId].push(item);
        } else {
            noFolder.push(item);
        }
    });

    folderRows.forEach(folder => {
        const folderId = parseInt(folder.id, 10);
        const folderItems = itemsByFolder[folderId] || [];
        const folderKey = `${type}_${folderId}`;
        const isOpen = openFolderState[folderKey] !== false;
        container.innerHTML += `
            <div class="folder-group-card ${isOpen ? 'open' : ''}" id="folder-group-${type}-${folderId}">
                <div class="folder-group-header" onclick="toggleFolderGroup('${type}', ${folderId})">
                    <div>
                        <strong><i class="fas fa-folder-open"></i> ${folder.name}</strong>
                        <div class="folder-group-meta">${folderItems.length} item(ns)</div>
                    </div>
                    <i class="fas ${isOpen ? 'fa-chevron-up' : 'fa-chevron-down'}"></i>
                </div>
                <div class="folder-group-items">
                    ${folderItems.length > 0 ? folderItems.map(renderer).join('') : '<div class="folder-group-meta">Nenhum item nesta pasta.</div>'}
                </div>
            </div>
        `;
    });

    noFolder.forEach(item => {
        container.innerHTML += renderer(item);
    });
}

function toggleFolderGroup(type, folderId) {
    const key = `${type}_${folderId}`;
    const el = document.getElementById(`folder-group-${type}-${folderId}`);
    if (!el) return;
    const opening = !el.classList.contains('open');
    el.classList.toggle('open', opening);
    openFolderState[key] = opening;
}

function itemMatchesFilters(item, filters) {
    const createdAt = item.created_at ? new Date(item.created_at) : null;
    const status = filters.status || 'all';
    if (status === 'paused' && item.status !== 'paused') return false;
    if (status === 'active' && (item.status !== 'active' || isItemExpiredVisual(item))) return false;
    if (status === 'expired' && !isItemExpiredVisual(item)) return false;

    if (filters.createdFrom && createdAt) {
        const minDate = new Date(`${filters.createdFrom}T00:00:00`);
        if (createdAt < minDate) return false;
    }
    if (filters.createdTo && createdAt) {
        const maxDate = new Date(`${filters.createdTo}T23:59:59`);
        if (createdAt > maxDate) return false;
    }

    if (Array.isArray(filters.tagIds) && filters.tagIds.length > 0) {
        const itemTagIds = (item.tags || []).map(tag => parseInt(tag.id, 10));
        const hasTag = filters.tagIds.some(tagId => itemTagIds.includes(tagId));
        if (!hasTag) return false;
    }
    return true;
}

async function loadMyLinks() {
    if (!isLoggedIn) return;
    try {
        const filters = readListFiltersFromUI('link');
        const params = new URLSearchParams();
        params.set('action', 'list');
        params.set('type', 'link');
        if (filters.createdFrom) params.set('created_from', filters.createdFrom);
        if (filters.createdTo) params.set('created_to', filters.createdTo);
        if (filters.status && filters.status !== 'all') params.set('status', filters.status);
        if (filters.tagIds && filters.tagIds.length > 0) params.set('tag_ids', filters.tagIds.join(','));

        const res = await fetch(`manage_links.php?${params.toString()}`, { credentials: 'include' });
        const data = await res.json();
        const container = document.getElementById('myLinksCards');
        if (!container) return;
        container.innerHTML = '';
        const links = data.links || [];
        myLinksData = links;
        linksFoldersByType.link = Array.isArray(data.folders) ? data.folders.filter(folder => folder.type === 'link') : [];
        linksAvailableTagsByType.link = Array.isArray(data.available_tags) ? data.available_tags : [];
        populateTagFilters('link', linksAvailableTagsByType.link);

        if (!data.success || links.length === 0) {
            container.innerHTML = `<div style="grid-column: 1/-1; text-align:center; padding:60px; color:#94a3b8; background:var(--card); border-radius:20px;">Nenhum link criado ainda.</div>`;
            return;
        }

        renderFolderedItems(container, 'link', links, linksFoldersByType.link, renderLinkCardItem);
        applyFeatureLocks();
    } catch (e) { console.error("Erro ao carregar links", e); }
}

function shareLink(url) {
    if (navigator.share) {
        navigator.share({ title: 'LinkNeo.pro', url: url }).catch(() => { });
    } else {
        navigator.clipboard.writeText(url);
    }
}

function showLinkDetails(id) {
    const link = myLinksData.find(l => l.id == id);
    if (!link) return;
    const content = document.getElementById('detailsContent');
    content.innerHTML = `
        <p><strong>Título:</strong> ${link.title || '---'}</p>
        <p><strong>Slug:</strong> /${link.path}</p>
        <p><strong>Subdomínio:</strong> ${link.subdomain}</p>
        <p><strong>URL Original:</strong> <span style="word-break:break-all;">${link.original_url}</span></p>
        <p><strong>Criado em:</strong> ${link.data_criacao}</p>
        <p><strong>Cliques totais:</strong> ${link.clicks}</p>
        <hr style="border:0; border-top:1px solid var(--border); margin:15px 0;">
        <p><i class="fab fa-apple"></i> iOS: ${link.url_ios || 'Padrão'}</p>
        <p><i class="fab fa-android"></i> Android: ${link.url_android || 'Padrão'}</p>
        <p><i class="fab fa-windows"></i> Windows: ${link.url_windows || 'Padrão'}</p>
    `;
    openModal('modalDetails');
}

function copyTableLink(text, btnElement) {
    navigator.clipboard.writeText(text).then(() => {
        const originalHTML = btnElement.innerHTML;

        // Efeito de sucesso na cópia
        btnElement.innerHTML = '<i class="fas fa-check"></i>';
        btnElement.style.background = 'var(--primary)';
        btnElement.style.color = 'var(--dark)';

        // Retorna ao normal após 2 segundos
        setTimeout(() => {
            btnElement.innerHTML = originalHTML;
            btnElement.style.background = ''; // Volta a usar a classe CSS
            btnElement.style.color = '';
        }, 2000);
    });
}

async function toggleStatus(id, currentStatus) {
    if (!guardTeamEditPermission()) return;
    const newStatus = currentStatus === 'paused' ? 'active' : 'paused';
    const fd = new FormData(); fd.append('action', 'toggle_status'); fd.append('id', id); fd.append('status', newStatus);
    await fetch('manage_links.php', { method: 'POST', body: fd, credentials: 'include' });
    loadMyLinks();
}

async function deleteLink(id) {
    if (!guardTeamEditPermission()) return;
    if (!confirm("Tem certeza que deseja excluir este link definitivamente?")) return;
    const fd = new FormData(); fd.append('action', 'delete'); fd.append('id', id);
    await fetch('manage_links.php', { method: 'POST', body: fd, credentials: 'include' });
    loadMyLinks();
    loadQRLinks();
}

function closeItemContextMenu() {
    const existing = document.getElementById('linkContextMenu');
    if (existing) existing.remove();
}

async function moveItemToFolder(id, type, folderId) {
    if (!guardTeamEditPermission()) return;
    closeItemContextMenu();
    const fd = new FormData();
    fd.append('action', 'move_to_folder');
    fd.append('id', String(id));
    fd.append('folder_id', folderId ? String(folderId) : '');
    const res = await fetch('manage_links.php', { method: 'POST', body: fd, credentials: 'include' });
    const data = await res.json();
    if (!data.success) {
        showStatus(type === 'qr' ? 'qr' : 'link', data.message || 'Não foi possível mover o item.', 'error');
        return;
    }
    if (type === 'qr') {
        loadQRLinks();
    } else {
        loadMyLinks();
    }
}

function openItemContextMenu(event, id, type) {
    event.preventDefault();
    event.stopPropagation();
    closeItemContextMenu();

    const menu = document.createElement('div');
    menu.className = 'link-context-menu';
    menu.id = 'linkContextMenu';
    menu.style.left = `${event.clientX}px`;
    menu.style.top = `${event.clientY}px`;

    const folders = (linksFoldersByType[type] || []).slice();
    const folderButtons = folders.map(folder => `
        <button type="button" onclick="moveItemToFolder(${id}, '${type}', ${folder.id})">
            <i class="fas fa-folder"></i> Mover para: ${folder.name}
        </button>
    `).join('');

    menu.innerHTML = `
        <button type="button" onclick="moveItemToFolder(${id}, '${type}', 0)">
            <i class="fas fa-folder-minus"></i> Remover da pasta
        </button>
        ${folderButtons || '<button type="button" onclick="createFolderPrompt(\'' + type + '\')"><i class="fas fa-folder-plus"></i> Criar nova pasta</button>'}
        <button type="button" onclick="editItemTagsPrompt(${id}, '${type}')">
            <i class="fas fa-tags"></i> Editar tags
        </button>
    `;

    document.body.appendChild(menu);
    setTimeout(() => {
        document.addEventListener('click', closeItemContextMenu, { once: true });
    }, 0);
}

async function createFolderPrompt(type) {
    if (!guardTeamEditPermission()) return;
    const currentType = type === 'qr' ? 'qr' : 'link';
    const name = prompt(`Nome da nova pasta (${currentType === 'qr' ? 'QR Codes' : 'Links'}):`);
    if (!name) return;
    const fd = new FormData();
    fd.append('action', 'create_folder');
    fd.append('type', currentType);
    fd.append('name', name);
    const res = await fetch('manage_links.php', { method: 'POST', body: fd, credentials: 'include' });
    const data = await res.json();
    if (!data.success) {
        showStatus(currentType === 'qr' ? 'qr' : 'link', data.message || 'Não foi possível criar a pasta.', 'error');
        return;
    }
    if (currentType === 'qr') {
        loadQRLinks();
    } else {
        loadMyLinks();
    }
}

function ensureTagEditorModal() {
    if (document.getElementById('modalTagEditor')) return;
    const overlay = document.getElementById('modalOverlay');
    if (!overlay) return;

    const div = document.createElement('div');
    div.id = 'modalTagEditor';
    div.className = 'modal-content hidden';
    div.onclick = (e) => e.stopPropagation();
    div.style.maxWidth = '520px';
    div.innerHTML = `
        <div class="modal-header">
            <h2>Editar Tags</h2>
            <button type="button" onclick="closeModals()" class="close-btn"><i class="fas fa-times"></i></button>
        </div>
        <form onsubmit="saveTagEditorModal(event)" style="display:flex; flex-direction:column; gap:14px;">
            <input type="hidden" id="tagEditorItemId">
            <input type="hidden" id="tagEditorItemType">
            <div class="form-group">
                <label for="tagEditorInput">Tags (separadas por vírgula)</label>
                <textarea id="tagEditorInput" rows="4" placeholder="ex: lancamento, instagram, campanha_julho" style="width:100%; resize:vertical;"></textarea>
                <small style="color:var(--text-dim); font-size:0.78rem;">Use vírgula para separar as tags. Limite de 20 tags por item.</small>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" class="btn-outline" onclick="closeModals()">Cancelar</button>
                <button type="submit" class="btn-primary">Salvar tags</button>
            </div>
        </form>
    `;
    overlay.appendChild(div);
}

function openTagEditorModal(id, type) {
    ensureTagEditorModal();
    tagEditorContext = { id: Number(id), type: type === 'qr' ? 'qr' : 'link' };
    const item = myLinksData.find(row => Number(row.id) === Number(id));
    const currentTags = (item?.tags || []).map(tag => tag.name).join(', ');
    const input = document.getElementById('tagEditorInput');
    const hiddenId = document.getElementById('tagEditorItemId');
    const hiddenType = document.getElementById('tagEditorItemType');
    if (input) input.value = currentTags;
    if (hiddenId) hiddenId.value = String(id);
    if (hiddenType) hiddenType.value = tagEditorContext.type;
    openModal('modalTagEditor');
}

async function saveTagEditorModal(e) {
    e.preventDefault();
    if (!guardTeamEditPermission()) return;
    const itemId = Number(document.getElementById('tagEditorItemId')?.value || tagEditorContext.id || 0);
    const itemType = document.getElementById('tagEditorItemType')?.value || tagEditorContext.type || 'link';
    const nextTags = document.getElementById('tagEditorInput')?.value || '';
    if (itemId <= 0) return;

    const fd = new FormData();
    fd.append('action', 'save_item_tags');
    fd.append('id', String(itemId));
    fd.append('tags', nextTags);

    const submitBtn = e.target.querySelector('button[type="submit"]');
    const oldText = submitBtn ? submitBtn.innerHTML : '';
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = 'Salvando...';
    }

    try {
        const res = await fetch('manage_links.php', { method: 'POST', body: fd, credentials: 'include' });
        const data = await res.json();
        if (!data.success) {
            showStatus(itemType === 'qr' ? 'qr' : 'link', data.message || 'Não foi possível salvar as tags.', 'error');
            return;
        }
        closeModals();
        if (itemType === 'qr') {
            await loadQRLinks();
        } else {
            await loadMyLinks();
        }
    } finally {
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = oldText;
        }
    }
}

async function editItemTagsPrompt(id, type) {
    if (!guardTeamEditPermission()) return;
    closeItemContextMenu();
    openTagEditorModal(id, type);
}

function openEdit(id, currentUrl, ios, android, windows) {
    document.getElementById('editLinkId').value = id;
    document.getElementById('editLinkUrl').value = currentUrl;
    document.getElementById('editLinkIos').value = ios;
    document.getElementById('editLinkAndroid').value = android;
    document.getElementById('editLinkWindows').value = windows;
    openModal('modalEditLink');
}

async function saveEditLink(e) {
    e.preventDefault();
    const id = document.getElementById('editLinkId').value;
    const url = document.getElementById('editLinkUrl').value;
    const ios = document.getElementById('editLinkIos').value;
    const android = document.getElementById('editLinkAndroid').value;
    const windows = document.getElementById('editLinkWindows').value;

    const fd = new FormData();
    fd.append('action', 'edit_url');
    fd.append('id', id);
    fd.append('url', url);
    fd.append('url_ios', ios);
    fd.append('url_android', android);
    fd.append('url_windows', windows);

    try {
        const res = await fetch('manage_links.php', { method: 'POST', body: fd, credentials: 'include' });
        const data = await res.json();

        if (data.success) {
            closeModals();
            loadMyLinks();
        } else {
            console.error(data.message || "Erro ao salvar as configurações.");
        }
    } catch (e) {
        console.error("Erro de conexão ao salvar o link.");
    }
}

// --- ANALYTICS (CHART.JS) ---
let currentDrilldown = { country: null, region: null, city: null };

function hexToRgba(hex, alpha) {
    if (!hex || typeof hex !== 'string' || !hex.startsWith('#')) return hex;

    let normalized = hex.replace('#', '');
    if (normalized.length === 3) {
        normalized = normalized.split('').map(char => char + char).join('');
    }

    const value = parseInt(normalized, 16);
    const red = (value >> 16) & 255;
    const green = (value >> 8) & 255;
    const blue = value & 255;

    return `rgba(${red}, ${green}, ${blue}, ${alpha})`;
}

function getLineGradient(ctx) {
    const tokens = getBrandTokens();
    const gradient = ctx.createLinearGradient(0, 0, 0, 320);
    gradient.addColorStop(0, hexToRgba(tokens.primary, 0.35));
    gradient.addColorStop(0.55, hexToRgba(tokens.primary, 0.12));
    gradient.addColorStop(1, hexToRgba(tokens.primary, 0.02));
    return gradient;
}

function renderInsightListById(id, items, fallbackText) {
    const el = document.getElementById(id);
    if (!el) return;
    if (!Array.isArray(items) || items.length === 0) {
        el.innerHTML = `<li>${fallbackText}</li>`;
        return;
    }
    el.innerHTML = items.map(item => `<li>${item}</li>`).join('');
}

function populateAnalyticsSelect(selectEl, rows, defaultOptions, getValueLabel) {
    if (!selectEl) return;
    const selected = selectEl.value || 'all';
    const fragment = document.createDocumentFragment();
    defaultOptions.forEach(optionCfg => {
        const option = document.createElement('option');
        option.value = optionCfg.value;
        option.text = optionCfg.label;
        fragment.appendChild(option);
    });
    (Array.isArray(rows) ? rows : []).forEach(row => {
        const valueLabel = getValueLabel(row);
        if (!valueLabel || !valueLabel.value) return;
        const option = document.createElement('option');
        option.value = String(valueLabel.value);
        option.text = valueLabel.label;
        fragment.appendChild(option);
    });
    selectEl.innerHTML = '';
    selectEl.appendChild(fragment);
    const hasSelected = Array.from(selectEl.options).some(opt => opt.value === selected);
    selectEl.value = hasSelected ? selected : 'all';
}

function populateAnalyticsFiltersFromPayload(data) {
    const linksSelect = document.getElementById('analyticsFilter');
    const foldersSelect = document.getElementById('analyticsFolderFilter');
    const tagsSelect = document.getElementById('analyticsTagFilter');
    const subdomainsSelect = document.getElementById('analyticsSubdomainFilter');

    const pathCounts = {};
    (Array.isArray(data.links) ? data.links : []).forEach((row) => {
        const path = String(row?.path || '').trim();
        if (!path) return;
        pathCounts[path] = (pathCounts[path] || 0) + 1;
    });

    populateAnalyticsSelect(
        linksSelect,
        data.links,
        [{ value: 'all', label: 'Todos os meus links' }],
        (row) => {
            const id = row?.id ? String(row.id) : '';
            const path = String(row?.path || '').trim();
            if (!id) return null;
            if ((pathCounts[path] || 0) > 1) {
                const subdomainRaw = String(row?.subdomain || '').trim();
                const subdomain = (subdomainRaw === '' || subdomainRaw.toUpperCase() === 'ROOT') ? 'root' : subdomainRaw;
                return { value: id, label: `/${path} (${subdomain})` };
            }
            return { value: id, label: `/${path}` };
        }
    );

    populateAnalyticsSelect(
        foldersSelect,
        data.filter_folders,
        [{ value: 'all', label: 'Todas as pastas' }],
        (row) => {
            const id = row?.id ? String(row.id) : '';
            const name = String(row?.name || '').trim();
            return (id && name) ? { value: id, label: name } : null;
        }
    );

    populateAnalyticsSelect(
        tagsSelect,
        data.filter_tags,
        [{ value: 'all', label: 'Todas as tags' }],
        (row) => {
            const id = row?.id ? String(row.id) : '';
            const name = String(row?.name || '').trim();
            return (id && name) ? { value: id, label: `#${name}` } : null;
        }
    );

    populateAnalyticsSelect(
        subdomainsSelect,
        data.filter_subdomains,
        [
            { value: 'all', label: 'Todos subdomínios' },
            { value: 'none', label: 'Sem subdomínio' }
        ],
        (row) => {
            const value = String(row || '').trim();
            return value ? { value, label: value } : null;
        }
    );
}

async function loadAnalyticsDeepInsights(filterValue, folderValue, tagValue, subdomainValue, setAnalyticsStatus) {
    const period = document.getElementById('analyticsPeriod')?.value || '14';
    let url = `analytics.php?action=deep_insights&link_id=${encodeURIComponent(filterValue)}&period=${encodeURIComponent(period)}&t=${Date.now()}`;
    if (folderValue && folderValue !== 'all') url += `&folder_id=${encodeURIComponent(folderValue)}`;
    if (tagValue && tagValue !== 'all') url += `&tag_id=${encodeURIComponent(tagValue)}`;
    if (subdomainValue && subdomainValue !== 'all') url += `&subdomain=${encodeURIComponent(subdomainValue)}`;
    const res = await fetch(url, { credentials: 'include' });
    const data = await res.json();
    if (!data.success) {
        setAnalyticsStatus(data.message || 'Falha ao carregar insights avançados.', 'warning');
        return;
    }

    const summary = data.summary || {};
    const clicks = Number(summary.clicks || 0);
    const conversions = Number(summary.conversions || 0);
    const growth = Number(summary.growth || 0);
    const cvr = Number(summary.cvr || 0);
    const avgMinutes = Number(summary.avg_conversion_minutes || 0);
    const topCampaign = summary.top_campaign || { campaign: '-', efficiency: 0 };

    const setText = (id, value) => {
        const el = document.getElementById(id);
        if (el) el.textContent = value;
    };

    setText('analyticsKpiClicks', clicks.toLocaleString('pt-BR'));
    setText('analyticsKpiClicksDelta', `${growth.toFixed(1)}% vs período anterior`);
    setText('analyticsKpiConversions', conversions.toLocaleString('pt-BR'));
    setText('analyticsKpiCvr', `CVR ${cvr.toFixed(2)}%`);
    setText('analyticsKpiAvgConvTime', `${avgMinutes.toFixed(0)} min`);
    setText('analyticsKpiTopCampaign', topCampaign.campaign || '-');
    setText('analyticsKpiTopCampaignRate', `Eficiência ${(Number(topCampaign.efficiency || 0)).toFixed(2)}%`);

    const timeline = Array.isArray(data.charts?.conversions_timeline) ? data.charts.conversions_timeline : [];
    renderChart(
        'chartConversionsTimeline',
        'line',
        timeline.map(i => new Date(i.day + 'T00:00:00').toLocaleDateString('pt-BR', { day: '2-digit', month: 'short' })),
        timeline.map(i => Number(i.total || 0)),
        { label: 'Conversões', borderColor: getBrandTokens().accent, fill: true, backgroundColor: getBrandTokens().chartFillAccent }
    );

    const hourly = Array.isArray(data.charts?.hourly_performance) ? data.charts.hourly_performance : [];
    renderChart(
        'chartHourlyPerformance',
        'bar',
        hourly.map(i => `${String(i.hour).padStart(2, '0')}h`),
        hourly.map(i => Number(i.clicks || 0)),
        { label: 'Cliques', backgroundColor: getBrandTokens().primary }
    );

    const campaignEfficiency = Array.isArray(data.charts?.campaign_efficiency) ? data.charts.campaign_efficiency : [];
    renderChart(
        'chartAnalyticsCampaignEfficiency',
        'bar',
        campaignEfficiency.map(i => i.campaign),
        campaignEfficiency.map(i => Number(i.efficiency || 0)),
        { label: 'Eficiência (%)', backgroundColor: '#f6b64f', horizontal: true }
    );

    renderInsightListById('analyticsAlerts', data.alerts, 'Sem alertas no momento.');
    renderInsightListById('analyticsRecommendations', data.recommendations, 'Sem recomendações no momento.');
}

async function loadAnalytics() {
    if (!isLoggedIn) { showSection('section-home'); openModal('modalLogin'); return; }

    const filterEl = document.getElementById('analyticsFilter');
    if (!filterEl) {
        console.warn('[Analytics] Filtro não encontrado no DOM.');
        return;
    }

    const statusEl = document.getElementById('analyticsStatus');
    const setAnalyticsStatus = (message, type = 'warning') => {
        if (!statusEl) return;
        statusEl.classList.remove('hidden', 'error', 'warning');
        if (type) statusEl.classList.add(type);
        statusEl.textContent = message;
    };
    const clearAnalyticsStatus = () => {
        if (!statusEl) return;
        statusEl.classList.add('hidden');
        statusEl.classList.remove('error', 'warning');
        statusEl.textContent = '';
    };

    const filterValue = filterEl.value;
    const period = document.getElementById('analyticsPeriod')?.value || '30';
    const folderValue = document.getElementById('analyticsFolderFilter')?.value || 'all';
    const tagValue = document.getElementById('analyticsTagFilter')?.value || 'all';
    const subdomainValue = document.getElementById('analyticsSubdomainFilter')?.value || 'all';
    const { country, region, city } = currentDrilldown;
    if (isFeatureLocked('analytics')) {
        loadAnalyticsDemoData(setAnalyticsStatus);
        return;
    }

    const renderDwellBlock = (dwell, setAnalyticsStatus) => {
        if (!dwell || typeof dwell !== 'object') return;
        const setText = (id, value) => {
            const el = document.getElementById(id);
            if (el) el.textContent = value;
        };
        setText('analyticsKpiDwellAvg', `${Number(dwell.avg_seconds || 0).toFixed(1)}s`);
        const validLinksCount = Number(dwell.links_with_valid || 0);
        const validSessionsCount = Number(dwell.valid_sessions || 0);
        const collectedSessionsCount = validSessionsCount + Number(dwell.discarded_sessions || 0);
        setText(
            'analyticsKpiDwellSupport',
            `${validLinksCount.toLocaleString('pt-BR')} link(s) com permanência válida • ${validSessionsCount.toLocaleString('pt-BR')} acesso(s) válido(s) de ${collectedSessionsCount.toLocaleString('pt-BR')} coletado(s)`
        );
        const validPoints = Array.isArray(dwell.valid_points) ? dwell.valid_points : [];
        renderDwellScatterChart('chartDwellAverage', validPoints, Number(dwell.avg_seconds || 0));
        if (validPoints.length === 0) {
            setAnalyticsStatus('Sem dados válidos de permanência para o período/filtro atual. Verifique se o tracker.js está instalado na página de destino do link e se houve sessões acima de 1s.', 'warning');
        }
    };

    try {
        let url = `analytics.php?link_id=${filterValue}&period=${encodeURIComponent(period)}&t=${new Date().getTime()}`;
        if (folderValue !== 'all') url += `&folder_id=${encodeURIComponent(folderValue)}`;
        if (tagValue !== 'all') url += `&tag_id=${encodeURIComponent(tagValue)}`;
        if (subdomainValue !== 'all') url += `&subdomain=${encodeURIComponent(subdomainValue)}`;
        if (country) url += `&country=${encodeURIComponent(country)}`;
        if (region) url += `&region=${encodeURIComponent(region)}`;
        if (city) url += `&city=${encodeURIComponent(city)}`;

        const res = await fetch(url, { credentials: 'include' });
        const data = await res.json();
        if (!data.success) {
            setAnalyticsStatus(data.message || 'Não foi possível carregar os dados de analytics.', 'error');
            return;
        }
        clearAnalyticsStatus();

        const links = Array.isArray(data.links) ? data.links : [];
        const engagement = Array.isArray(data.engagement) ? data.engagement : [];
        const locations = Array.isArray(data.locations) ? data.locations : [];
        const referrers = Array.isArray(data.referrers) ? data.referrers : [];
        const sources = Array.isArray(data.sources) ? data.sources : [];
        const osDataRaw = data.os && typeof data.os === 'object' ? data.os : {};
        const dwell = data.dwell && typeof data.dwell === 'object' ? data.dwell : null;
        populateAnalyticsFiltersFromPayload({ ...data, links });

        // 1. Engajamento por Período
        renderChart('chartEngagement', 'line', engagement.map(i => i.label), engagement.map(i => i.value), {
            label: 'Cliques:',
            borderColor: getBrandTokens().primary,
            fill: true,
            backgroundColor: getBrandTokens().chartFillPrimary
        });

        // 2. Localização com Drilldown (Ordenado DESC + Horizontal)
        const sortedLocations = [...locations].sort((a, b) => b.value - a.value);
        const neighborhoodDrill = data.location_neighborhood_drilldown === true;
        let locationChartClick = true;
        if (country && region && city) {
            locationChartClick = false;
        } else if (country && region && !neighborhoodDrill) {
            locationChartClick = false;
        }
        renderChart('chartLocations', 'bar', sortedLocations.map(i => i.label), sortedLocations.map(i => i.value), {
            label: 'Quantidade de Cliques',
            backgroundColor: chartVisualTheme.colors.slice(0, 6),
            horizontal: true
        }, locationChartClick);

        // 3. Sistemas Operacionais (Ordenado DESC + Horizontal)
        const osArray = Object.entries(osDataRaw).map(([label, value]) => ({ label, value })).sort((a, b) => b.value - a.value);
        renderChart('chartOS', 'bar', osArray.map(i => i.label), osArray.map(i => i.value), {
            label: 'Cliques por SO',
            backgroundColor: chartVisualTheme.colors.slice(0, 5),
            horizontal: true
        });

        const sortedReferrers = [...referrers].sort((a, b) => b.value - a.value);
        renderChart('chartReferrers', 'bar', sortedReferrers.map(i => i.label), sortedReferrers.map(i => i.value), {
            backgroundColor: chartVisualTheme.colors.slice(0, 6),
            horizontal: true
        });

        const sourceLabels = sources.map(s => s.label === 'qr' ? 'QR Code' : 'Link Digital');
        renderChart('chartSources', 'pie', sourceLabels, sources.map(i => i.value));

        renderDwellBlock(dwell, setAnalyticsStatus);

        try {
            const dwellRes = await fetch(`analytics.php?action=dwell_summary&link_id=${encodeURIComponent(filterValue)}&period=${encodeURIComponent(period)}&t=${Date.now()}`, { credentials: 'include' });
            const dwellData = await dwellRes.json();
            if (dwellData && dwellData.success && dwellData.dwell) {
                renderDwellBlock(dwellData.dwell, setAnalyticsStatus);
            }
        } catch (e) {
            // Mantém a renderização já feita com o payload principal.
        }

        await loadAnalyticsDeepInsights(filterValue, folderValue, tagValue, subdomainValue, setAnalyticsStatus);

        if (engagement.every(i => Number(i.value || 0) === 0) && sortedLocations.length === 0) {
            setAnalyticsStatus('Nenhum dado encontrado para os filtros atuais. Tente outro link/período.', 'warning');
        }

    } catch (e) {
        console.error("Erro ao carregar Analytics", e);
        if (statusEl) {
            statusEl.classList.remove('hidden', 'warning');
            statusEl.classList.add('error');
            statusEl.textContent = 'Falha ao carregar analytics. Verifique conexão/sessão e tente novamente.';
        }
    }
}

function renderChart(id, type, labels, data, config = {}, enableClick = false) {
    const canvas = document.getElementById(id);
    if (!canvas) return;

    if (charts[id]) {
        charts[id].destroy();
        delete charts[id];
    }

    const ctx = canvas.getContext('2d');

    const defaultColors = chartVisualTheme.colors;
    const lineColor = config.borderColor || chartVisualTheme.colors[0];
    const resolvedBackgroundColor = config.backgroundColor || (type === 'line'
        ? getLineGradient(ctx)
        : (type === 'pie' || type === 'doughnut')
            ? defaultColors
            : defaultColors.map(color => hexToRgba(color, 0.88)));
    const resolvedBorderColor = config.borderColor || (type === 'line' ? chartVisualTheme.colors[0] : 'transparent');

    const safeData = Array.isArray(data) ? data : [];
    const isSinglePointLine = type === 'line' && safeData.length <= 1;

    charts[id] = new Chart(ctx, {
        type: type,
        data: {
            labels: labels,
            datasets: [{
                label: config.label || '',
                data: safeData,
                backgroundColor: resolvedBackgroundColor,
                borderColor: resolvedBorderColor,
                borderWidth: type === 'line' ? 3 : 0,
                borderRadius: type === 'bar' ? 12 : 0,
                borderSkipped: false,
                maxBarThickness: type === 'bar' ? 28 : undefined,
                pointBackgroundColor: getCurrentTheme() === 'light' ? '#ffffff' : '#07111f',
                pointBorderColor: lineColor,
                pointBorderWidth: 2,
                pointHoverBackgroundColor: lineColor,
                pointHoverBorderColor: getCurrentTheme() === 'light' ? '#ffffff' : '#ffffff',
                // Com apenas 1 ponto em série de linha, manter ponto visível.
                pointRadius: type === 'line' ? (isSinglePointLine ? 5 : 0) : 2,
                pointHoverRadius: type === 'line' ? (isSinglePointLine ? 7 : 6) : 5,
                tension: 0.38,
                fill: config.fill !== undefined ? config.fill : type === 'line'
            }]
        },
        options: {
            indexAxis: config.horizontal ? 'y' : 'x',
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                intersect: false,
                mode: type === 'pie' || type === 'doughnut' ? 'nearest' : 'index',
                axis: config.horizontal ? 'y' : 'x'
            },
            plugins: {
                legend: {
                    display: (type === 'doughnut' || type === 'pie'),
                    position: 'bottom',
                    labels: {
                        color: chartVisualTheme.muted,
                        padding: 18,
                        font: { family: 'Manrope', size: 12, weight: '600' }
                    }
                },
                tooltip: {
                    backgroundColor: chartVisualTheme.tooltipBg,
                    titleColor: chartVisualTheme.text,
                    bodyColor: chartVisualTheme.text,
                    borderColor: chartVisualTheme.tooltipBorder,
                    borderWidth: 1,
                    padding: 14,
                    cornerRadius: 14,
                    titleFont: { family: 'Sora', size: 13, weight: '700' },
                    bodyFont: { family: 'Manrope', size: 12, weight: '600' },
                    displayColors: true
                }
            },
            elements: {
                arc: {
                    borderWidth: 2,
                    borderColor: getCurrentTheme() === 'light' ? 'rgba(255, 255, 255, 0.95)' : 'rgba(7, 17, 31, 0.9)',
                    hoverOffset: 8
                }
            },
            onClick: (e) => {
                if (enableClick) {
                    const chart = charts[id];
                    const activePoints = chart.getElementsAtEventForMode(e, 'index', {
                        intersect: false,
                        axis: config.horizontal ? 'y' : 'x'
                    }, true);
                    if (activePoints.length > 0) {
                        const index = activePoints[0].index;
                        const label = labels[index];
                        handleLocationClick(label);
                    }
                }
            },
            scales: (type === 'bar' || type === 'line') ? {
                y: {
                    beginAtZero: !config.horizontal,
                    grid: { color: chartVisualTheme.grid, drawBorder: false },
                    border: { display: false },
                    ticks: { color: chartVisualTheme.muted, font: { size: 11, weight: '600' }, padding: 10 }
                },
                x: {
                    beginAtZero: !!config.horizontal,
                    grid: { color: type === 'line' ? chartVisualTheme.grid : 'rgba(0,0,0,0)', drawBorder: false },
                    border: { display: false },
                    ticks: { color: chartVisualTheme.muted, font: { size: 11, weight: '600' }, padding: 8 }
                }
            } : {}
        }
    });
}

function renderDwellScatterChart(id, validPoints, avgSeconds) {
    const canvas = document.getElementById(id);
    if (!canvas) return;

    if (charts[id]) {
        charts[id].destroy();
        delete charts[id];
    }

    const ctx = canvas.getContext('2d');
    const source = Array.isArray(validPoints) ? validPoints : [];
    const points = source.map((p, idx) => ({
        x: idx + 1,
        y: Number(p.seconds || 0)
    }));
    const avg = Number(avgSeconds || 0);
    const maxX = Math.max(1, points.length);

    charts[id] = new Chart(ctx, {
        type: 'scatter',
        data: {
            datasets: [
                {
                    label: 'Acessos válidos (>1s)',
                    data: points,
                    backgroundColor: hexToRgba(getBrandTokens().accent, 0.78),
                    borderColor: getBrandTokens().accent,
                    pointRadius: 4,
                    pointHoverRadius: 5
                },
                {
                    type: 'line',
                    label: `Média (${avg.toFixed(1)}s)`,
                    data: [{ x: 1, y: avg }, { x: maxX, y: avg }],
                    borderColor: 'rgba(246, 182, 79, 1)',
                    borderWidth: 2,
                    pointRadius: 0,
                    fill: false,
                    tension: 0
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'bottom',
                    labels: {
                        color: chartVisualTheme.muted
                    }
                },
                tooltip: {
                    backgroundColor: chartVisualTheme.tooltipBg,
                    titleColor: chartVisualTheme.text,
                    bodyColor: chartVisualTheme.text,
                    borderColor: chartVisualTheme.tooltipBorder,
                    borderWidth: 1,
                    callbacks: {
                        label: (tooltipItem) => {
                            const x = Number(tooltipItem.raw?.x || 0);
                            const y = Number(tooltipItem.raw?.y || 0);
                            if (tooltipItem.datasetIndex === 1) return `Média: ${y.toFixed(2)}s`;
                            return `Acesso #${x}: ${y.toFixed(2)}s`;
                        }
                    }
                }
            },
            scales: {
                x: {
                    type: 'linear',
                    title: {
                        display: true,
                        text: 'Acessos válidos (ordem cronológica)',
                        color: chartVisualTheme.muted
                    },
                    ticks: { color: chartVisualTheme.muted },
                    grid: { color: chartVisualTheme.grid }
                },
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Permanência (segundos)',
                        color: chartVisualTheme.muted
                    },
                    ticks: { color: chartVisualTheme.muted },
                    grid: { color: chartVisualTheme.grid }
                }
            }
        }
    });
}

// LÓGICA DE DRILLDOWN DE LOCALIZAÇÃO
function handleLocationClick(label) {
    if (!currentDrilldown.country) {
        currentDrilldown.country = label;
        document.getElementById('locationChartTitle').innerText = `Cliques em: ${label}`;
        document.getElementById('btnResetLocation').style.display = 'block';
    } else if (!currentDrilldown.region) {
        currentDrilldown.region = label;
        document.getElementById('locationChartTitle').innerText = `Cliques em: ${currentDrilldown.country} > ${label}`;
    } else if (!currentDrilldown.city) {
        currentDrilldown.city = label;
        document.getElementById('locationChartTitle').innerText = `Cliques em: ${currentDrilldown.country} > ${currentDrilldown.region} > ${label} · bairros`;
    } else {
        return;
    }
    loadAnalytics();
}

function resetLocationDrilldown() {
    currentDrilldown = { country: null, region: null, city: null };
    document.getElementById('locationChartTitle').innerText = 'Cliques por País';
    document.getElementById('btnResetLocation').style.display = 'none';
    loadAnalytics();
}

// LÓGICA DE EXPANSÃO DE ITENS NA LISTA
async function toggleItemExpansion(id, section, tab = 'stats') {
    const container = document.getElementById(`expand-${section}-${id}`);
    const card = document.getElementById(`card-${section}-${id}`);

    // Se já estiver aberto e for na mesma aba, fecha
    if (container.classList.contains('open') && container.dataset.activeTab === tab) {
        container.classList.remove('open');
        setTimeout(() => { container.innerHTML = ''; }, 400);
        return;
    }

    // Fecha outros containers abertos na mesma seção (opcional para UX mais limpa)
    document.querySelectorAll('.item-expand-container.open').forEach(el => {
        if (el.id !== `expand-${section}-${id}`) {
            el.classList.remove('open');
            setTimeout(() => { el.innerHTML = ''; }, 400);
        }
    });

    container.dataset.activeTab = tab;
    container.innerHTML = `<div style="text-align:center; padding:20px;"><i class="fas fa-spinner fa-spin fa-2x" style="color:var(--primary);"></i></div>`;
    container.classList.add('open');

    // Carrega o conteúdo (Simulado ou via Fetch)
    await loadExpandedContent(id, section, tab);
}

async function loadExpandedContent(id, section, tab) {
    const container = document.getElementById(`expand-${section}-${id}`);
    const link = myLinksData.find(l => l.id == id);
    if (!link) return;

    // --- CHECK FEATURE LOCKS ---
    const featureMap = {
        'stats': 'analytics',
        'rules': 'rules',
        'tracking': 'tracking'
    };

    const feature = featureMap[tab];
    if (feature && userFeatures[feature] === false) {
        if (tab === 'stats' || tab === 'rules' || tab === 'tracking') {
            container.innerHTML = `
                <div style="padding:24px; border-radius:14px; background:var(--page-surface-soft); border:1px solid var(--border);">
                    <div style="display:flex; justify-content:space-between; gap:18px; align-items:center; flex-wrap:wrap;">
                        <div>
                            <h4 style="margin:0 0 8px 0; color:var(--text);">Modo demonstração (${featureDisplayLabels[feature] || 'recurso'})</h4>
                            <p style="margin:0; color:#94a3b8;">Você está vendo uma prévia fictícia desta área. Faça upgrade para liberar dados reais.</p>
                        </div>
                        <button onclick="showSection('section-subscription', event)" class="btn-primary" style="min-width:180px;">
                            <i class="fas fa-rocket"></i> Fazer upgrade
                        </button>
                    </div>
                    <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:10px; margin-top:16px;">
                        <div style="background:rgba(122,162,255,0.1); border:1px solid rgba(122,162,255,0.25); border-radius:10px; padding:10px;">Cliques (demo): <strong>${(900 + Math.floor(Math.random() * 300)).toLocaleString('pt-BR')}</strong></div>
                        <div style="background:var(--primary-surface-strong); border:1px solid var(--primary-border); border-radius:10px; padding:10px;">Conversões (demo): <strong>${(80 + Math.floor(Math.random() * 40)).toLocaleString('pt-BR')}</strong></div>
                        <div style="background:rgba(246,182,79,0.1); border:1px solid rgba(246,182,79,0.25); border-radius:10px; padding:10px;">Eficiência (demo): <strong>${(12 + Math.random() * 10).toFixed(1)}%</strong></div>
                    </div>
                </div>
            `;
            return;
        }
        container.innerHTML = `
            <div style="text-align:center; padding:50px 20px; background:var(--interactive-soft); border-radius:15px; border:1px dashed var(--border); margin:10px auto; max-width:600px;">
                <div class="icon-ring-primary" style="width:70px; height:70px; margin:0 auto 20px auto;">
                    <i class="fas fa-lock fa-2x"></i>
                </div>
                <h3 style="color:var(--text); margin-bottom:12px; font-size:1.4rem;">Recurso Premium</h3>
                <p style="color:var(--text-dim); margin-bottom:25px; line-height:1.6; font-size:1.05rem;">
                    Essa função requer que você faça um upgrade do plano atual para ser acessada e liberar todo o potencial do seu dashboard.
                </p>
                <button onclick="showSection('section-subscription', event)" class="btn-primary shadow-primary-soft" style="width:auto; min-width:200px; padding:12px 30px; margin:0 auto; font-size:1rem;">
                    <i class="fas fa-rocket"></i> Fazer upgrade
                </button>
            </div>
        `;
        return;
    }

    if (tab === 'stats') {
        const res = await fetch(`analytics.php?link_id=${id}`, { credentials: 'include' });
        const data = await res.json();

        if (!data.success) {
            container.innerHTML = `<div style="text-align:center; padding:30px; color:#ef4444;"><i class="fas fa-exclamation-triangle"></i> Falha ao carregar métricas: ${data.message || 'Erro Interno'}</div>`;
            return;
        }

        try {
            const period = document.getElementById('analyticsPeriod')?.value || '30';
            const dwellRes = await fetch(`analytics.php?action=dwell_summary&link_id=${encodeURIComponent(id)}&period=${encodeURIComponent(period)}&t=${Date.now()}`, { credentials: 'include' });
            const dwellData = await dwellRes.json();
            if (dwellData && dwellData.success && dwellData.dwell) {
                data.dwell = dwellData.dwell;
            }
        } catch (e) {
            // Mantém o payload principal em caso de falha no endpoint dedicado.
        }

        let abChartHtml = '';
        if (data.ab_test && data.ab_test.length > 0) {
            abChartHtml = `
                <div class="mini-chart-box" style="grid-column: 1/-1; height: 300px;">
                    <h4>Cliques Recentes por Variante</h4>
                    <canvas id="chart-exp-ab-${id}"></canvas>
                </div>
            `;
        }

        let convChartHtml = '';
        let conversionChartCanvasHtml = '';
        if (data.conversions && Array.isArray(data.conversions)) {
            let convItems = '';
            if (data.conversions.length === 0) {
                convItems = '<div style="color:var(--text-dim); text-align:center; padding:10px; font-size:0.85em;">Nenhuma conversão registrada ainda. As configurações de Tracking de Conversões estão ativas.</div>';
            } else {
                convItems = data.conversions.map(c => `
                  <div style="display:flex; justify-content:space-between; padding:8px; border-bottom:1px solid rgba(255,255,255,0.05); font-size:0.85rem;">
                     <span><i class="fas fa-check-circle" style="color:#10b981;"></i> ${c.action_name} <span style="opacity:0.5; font-size: 0.7em;">(${c.variant_name || 'Link Padrão'})</span></span>
                     <span style="font-weight:bold; color:#f59e0b;">${c.total_conversions} conv.</span>
                  </div>
               `).join('');
                conversionChartCanvasHtml = `
                    <div style="height:220px; margin-top:10px;">
                        <canvas id="chart-exp-conv-${id}"></canvas>
                    </div>
                `;
            }

            convChartHtml = `
                <div class="mini-chart-box" style="grid-column: 1/-1; height: auto;">
                    <h4 style="color:#f59e0b;"><i class="fas fa-bullseye"></i> Resultados de Conversão e Tracking</h4>
                    <div style="background:var(--interactive-soft); border-radius:8px; padding:10px; margin-top:10px;">
                        ${convItems}
                    </div>
                    ${conversionChartCanvasHtml}
                </div>
            `;
        }

        container.innerHTML = `
            <div class="expand-content-grid">
                ${convChartHtml}
                <div class="mini-chart-box" style="grid-column: 1 / -1; height: 250px;">
                    <h4>Engajamento Mensal</h4>
                    <canvas id="chart-exp-eng-${id}"></canvas>
                </div>
                <div class="mini-chart-box" style="grid-column: 1 / -1; height: 250px;">
                    <h4>Permanência (Dispersão de acessos válidos)</h4>
                    <canvas id="chart-exp-dwell-${id}"></canvas>
                </div>
                <div class="mini-chart-box" style="height: 300px;">
                    <h4>Localizações</h4>
                    <canvas id="chart-exp-loc-${id}"></canvas>
                </div>
                <div class="mini-chart-box" style="height: 300px;">
                    <h4>Dispositivos</h4>
                    <canvas id="chart-exp-os-${id}"></canvas>
                </div>
                ${abChartHtml}
            </div>
        `;

        // Renderiza gráficos pequenos específicos do item
        setTimeout(() => {
            try {
                const sortedLoc = (data.locations || []).sort((a, b) => b.value - a.value);
                const osArr = Object.entries(data.os || {}).map(([label, value]) => ({ label, value })).sort((a, b) => b.value - a.value);
                const engagementData = data.engagement || [];
                const dwellPoints = Array.isArray(data?.dwell?.valid_points) ? data.dwell.valid_points : [];

                renderChart(`chart-exp-eng-${id}`, 'line', engagementData.map(i => i.label), engagementData.map(i => i.value), { borderColor: getBrandTokens().primary, fill: true, backgroundColor: getBrandTokens().chartFillPrimary });
                renderDwellScatterChart(`chart-exp-dwell-${id}`, dwellPoints, Number(data?.dwell?.avg_seconds || 0));
                renderChart(`chart-exp-loc-${id}`, 'bar', sortedLoc.map(i => i.label), sortedLoc.map(i => i.value), { backgroundColor: '#3b82f6', horizontal: true });
                renderChart(`chart-exp-os-${id}`, 'bar', osArr.map(i => i.label), osArr.map(i => i.value), { backgroundColor: [getBrandTokens().primary, getBrandTokens().accent, '#6366f1', '#4b5563'], horizontal: true });

                if (data.ab_test && data.ab_test.length > 0) {
                    const abLabels = data.ab_test.map(v => `${v.name} (${v.clicks} clicks)`);
                    const abData = data.ab_test.map(v => v.clicks);
                    renderChart(`chart-exp-ab-${id}`, 'pie', abLabels, abData);
                }
                if (data.conversions && Array.isArray(data.conversions) && data.conversions.length > 0) {
                    const convByAction = {};
                    data.conversions.forEach(item => {
                        const key = item.action_name || 'Evento';
                        convByAction[key] = (convByAction[key] || 0) + Number(item.total_conversions || 0);
                    });
                    const convLabels = Object.keys(convByAction);
                    const convValues = convLabels.map(label => convByAction[label]);
                    renderChart(`chart-exp-conv-${id}`, 'bar', convLabels, convValues, {
                        backgroundColor: '#f59e0b',
                        borderColor: '#f59e0b'
                    });
                }
            } catch (err) {
                console.error("Erro renderizando charts:", err);
            }
        }, 150);

    } else if (tab === 'edit') {
        // QR Codes dinâmicos usam o builder reutilizável com abas de tipo de destino.
        if (section === 'qr') {
            renderQREditPanel(container, link);
            return;
        }
        if (link.is_ab_test == 1) {
            const requestPreciseAb = parseInt(link.request_precise_location || 0, 10) === 1;
            let abRows = '';
            if (link.variants) {
                link.variants.forEach(v => {
                    abRows += `
                    <div style="display:flex; flex-direction:column; gap:5px; margin-bottom:15px; background:var(--interactive-soft); padding:10px; border-radius:8px; border:1px solid var(--border-light);">
                        <div style="display:flex; gap:10px;">
                            <input type="text" id="editAbName-${id}-${v.id}" value="${v.name}" placeholder="Nome (A/B)" style="flex:1; border:1px solid var(--border); background:var(--page-surface-soft); padding:8px; border-radius:6px; color:var(--text);">
                            <input type="number" id="editAbWeight-${id}-${v.id}" value="${v.weight}" style="width:80px; border:1px solid var(--border); background:var(--page-surface-soft); padding:8px; border-radius:6px; color:var(--text);">
                            <select id="editAbStatus-${id}-${v.id}" style="width:120px; border:1px solid var(--border); background:var(--page-surface-soft); padding:8px; border-radius:6px; color:var(--text);">
                                <option value="active" ${v.status === 'active' ? 'selected' : ''}>Ativo</option>
                                <option value="inactive" ${v.status === 'inactive' ? 'selected' : ''}>Pausado</option>
                            </select>
                        </div>
                        <input type="url" id="editAbUrl-${id}-${v.id}" value="${v.url}" style="width:100%; border:1px solid var(--border); background:var(--page-surface-soft); padding:8px; border-radius:6px; color:var(--text);" placeholder="https://...">
                    </div>
                    `;
                });
            }
            container.innerHTML = `
                <div class="expand-content-grid" style="grid-template-columns: 1fr;">
                    <form onsubmit="saveEditAbTest(event, ${id}, '${section}')">
                        <div class="form-group" style="grid-column: 1/-1;">
                            <label><i class="fas fa-vial"></i> Edição Rápida: Teste A/B</label>
                            <p style="font-size:0.8rem; color:var(--text-dim); margin-bottom:15px;">Para adicionar ou remover novas variantes, recrie um teste. A soma dos pesos ativos de ser 100%.</p>
                            ${abRows}
                        </div>
                        <div class="form-group" style="grid-column: 1/-1; background:rgba(59,130,246,0.06); border:1px solid rgba(96,165,250,0.22); border-radius:10px; padding:12px 14px;">
                            <label style="display:flex; align-items:flex-start; gap:10px; margin:0;">
                                <input type="checkbox" id="editPreciseAb-${id}" ${requestPreciseAb ? 'checked' : ''} style="width:18px; height:18px; margin-top:1px; accent-color:var(--primary);">
                                <span style="display:flex; flex-direction:column; gap:4px;">
                                    <span style="font-weight:600;">Solicitar localização exata (opcional)</span>
                                    <small style="color:var(--text-dim);">Opcional para visitantes; incompatível com retargeting intermediário no mesmo link.</small>
                                </span>
                            </label>
                        </div>
                        <button type="submit" class="btn-primary" style="grid-column: 1/-1; width:100%;">Salvar Teste A/B</button>
                    </form>
                </div>
            `;
        } else {
            const deepLinkingEnabled = parseInt(link.deep_linking_enabled || 0, 10) === 1;
            const deepLinkingAllowed = userFeatures.deep_linking === true;
            const requestPreciseEnabled = parseInt(link.request_precise_location || 0, 10) === 1;
            container.innerHTML = `
                <div class="expand-content-grid" style="grid-template-columns: 1fr;">
                    <form onsubmit="saveEditLinkExpanded(event, ${id}, '${section}')" style="display:flex; flex-direction:column; gap:14px;">
                        <div style="background:rgba(0,0,0,0.18); border:1px solid rgba(255,255,255,0.06); border-radius:12px; padding:14px;">
                            <div class="form-group" style="margin:0;">
                                <label>URL de Destino Padrão</label>
                                <input type="url" value="${link.original_url}" id="editUrl-${id}" required class="qr-config-panel input">
                            </div>
                        </div>

                        <div style="background:rgba(0,0,0,0.18); border:1px solid rgba(255,255,255,0.06); border-radius:12px; padding:14px;">
                            <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:12px;">
                                <div class="form-group" style="margin:0;">
                                    <label><i class="fab fa-apple"></i> iOS</label>
                                    <input type="url" value="${link.url_ios || ''}" id="editIos-${id}" placeholder="Vazio = padrão">
                                </div>
                                <div class="form-group" style="margin:0;">
                                    <label><i class="fab fa-android"></i> Android</label>
                                    <input type="url" value="${link.url_android || ''}" id="editAndroid-${id}" placeholder="Vazio = padrão">
                                </div>
                                <div class="form-group" style="margin:0;">
                                    <label><i class="fab fa-windows"></i> Windows</label>
                                    <input type="url" value="${link.url_windows || ''}" id="editWindows-${id}" placeholder="Vazio = padrão">
                                </div>
                            </div>
                        </div>

                        <div style="background:rgba(0,0,0,0.18); border:1px solid rgba(255,255,255,0.06); border-radius:12px; padding:14px;">
                            <div style="display:grid; grid-template-columns:1fr; gap:12px;">
                                <div class="form-group" style="margin:0;">
                                    <label><i class="fas fa-heading"></i> Título do Preview Social</label>
                                    <input type="text" value="${link.social_title || ''}" id="editSocialTitle-${id}" placeholder="Opcional (WhatsApp/Facebook/X)">
                                </div>
                                <div class="form-group" style="margin:0;">
                                    <label><i class="fas fa-align-left"></i> Descrição do Preview Social</label>
                                    <textarea id="editSocialDescription-${id}" rows="3" placeholder="Opcional">${link.social_description || ''}</textarea>
                                </div>
                                <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:12px;">
                                    <div class="form-group" style="margin:0;">
                                        <label><i class="fas fa-image"></i> Imagem de Preview (URL)</label>
                                        <input type="url" value="${link.social_image_url || ''}" id="editSocialImage-${id}" placeholder="https://...">
                                    </div>
                                    <div class="form-group" style="margin:0;">
                                        <label><i class="fas fa-globe"></i> Favicon Personalizado (URL)</label>
                                        <input type="url" value="${link.favicon_url || ''}" id="editFavicon-${id}" placeholder="https://...">
                                    </div>
                                </div>
                                <small style="color:var(--text-dim);">
                                    Esses dados são opcionais e não alteram o destino do link. Se vazio, o comportamento atual é mantido.
                                </small>
                            </div>
                        </div>

                        <div style="background:rgba(0,0,0,0.18); border:1px solid rgba(255,255,255,0.06); border-radius:12px; padding:14px;">
                            <div style="display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:10px;">
                                <strong style="font-size:0.9rem;"><i class="fas fa-eye"></i> Preview nos Apps</strong>
                                <small style="color:var(--text-dim);">Pré-visualização aproximada</small>
                            </div>
                            <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(210px,1fr)); gap:12px;">
                                <div style="border:1px solid var(--border-light); border-radius:10px; overflow:hidden; background:var(--interactive-soft);">
                                    <div style="padding:8px 10px; border-bottom:1px solid rgba(255,255,255,0.06); font-size:0.78rem; color:#25D366; font-weight:600;">WhatsApp</div>
                                    <div id="socialPreviewImgWrap-wa-${id}" style="display:none; width:100%; aspect-ratio:1.91/1; background:var(--page-surface-soft);">
                                        <img id="socialPreviewImg-wa-${id}" alt="Preview WhatsApp" style="width:100%; height:100%; object-fit:cover;">
                                    </div>
                                    <div style="padding:10px;">
                                        <div style="display:flex; align-items:center; gap:8px; margin-bottom:6px;">
                                            <img id="socialPreviewFav-wa-${id}" alt="Favicon" style="width:16px; height:16px; border-radius:3px;">
                                            <span id="socialPreviewDomain-wa-${id}" style="font-size:0.72rem; color:var(--text-dim);"></span>
                                        </div>
                                        <div id="socialPreviewTitle-wa-${id}" style="font-weight:700; font-size:0.82rem; line-height:1.3;"></div>
                                        <div id="socialPreviewDesc-wa-${id}" style="margin-top:4px; font-size:0.76rem; color:var(--text-dim); line-height:1.35;"></div>
                                        <div id="socialPreviewUrl-wa-${id}" style="margin-top:6px; font-size:0.72rem; color:#60a5fa;"></div>
                                    </div>
                                </div>

                                <div style="border:1px solid var(--border-light); border-radius:10px; overflow:hidden; background:var(--interactive-soft);">
                                    <div style="padding:8px 10px; border-bottom:1px solid rgba(255,255,255,0.06); font-size:0.78rem; color:#1877F2; font-weight:600;">Facebook</div>
                                    <div id="socialPreviewImgWrap-fb-${id}" style="display:none; width:100%; aspect-ratio:1.91/1; background:var(--page-surface-soft);">
                                        <img id="socialPreviewImg-fb-${id}" alt="Preview Facebook" style="width:100%; height:100%; object-fit:cover;">
                                    </div>
                                    <div style="padding:10px;">
                                        <div style="display:flex; align-items:center; gap:8px; margin-bottom:6px;">
                                            <img id="socialPreviewFav-fb-${id}" alt="Favicon" style="width:16px; height:16px; border-radius:3px;">
                                            <span id="socialPreviewDomain-fb-${id}" style="font-size:0.72rem; color:var(--text-dim);"></span>
                                        </div>
                                        <div id="socialPreviewTitle-fb-${id}" style="font-weight:700; font-size:0.82rem; line-height:1.3;"></div>
                                        <div id="socialPreviewDesc-fb-${id}" style="margin-top:4px; font-size:0.76rem; color:var(--text-dim); line-height:1.35;"></div>
                                        <div id="socialPreviewUrl-fb-${id}" style="margin-top:6px; font-size:0.72rem; color:#60a5fa;"></div>
                                    </div>
                                </div>

                                <div style="border:1px solid var(--border-light); border-radius:10px; overflow:hidden; background:var(--interactive-soft);">
                                    <div style="padding:8px 10px; border-bottom:1px solid rgba(255,255,255,0.06); font-size:0.78rem; color:#cbd5e1; font-weight:600;">X (Twitter)</div>
                                    <div id="socialPreviewImgWrap-x-${id}" style="display:none; width:100%; aspect-ratio:1.91/1; background:var(--page-surface-soft);">
                                        <img id="socialPreviewImg-x-${id}" alt="Preview X" style="width:100%; height:100%; object-fit:cover;">
                                    </div>
                                    <div style="padding:10px;">
                                        <div style="display:flex; align-items:center; gap:8px; margin-bottom:6px;">
                                            <img id="socialPreviewFav-x-${id}" alt="Favicon" style="width:16px; height:16px; border-radius:3px;">
                                            <span id="socialPreviewDomain-x-${id}" style="font-size:0.72rem; color:var(--text-dim);"></span>
                                        </div>
                                        <div id="socialPreviewTitle-x-${id}" style="font-weight:700; font-size:0.82rem; line-height:1.3;"></div>
                                        <div id="socialPreviewDesc-x-${id}" style="margin-top:4px; font-size:0.76rem; color:var(--text-dim); line-height:1.35;"></div>
                                        <div id="socialPreviewUrl-x-${id}" style="margin-top:6px; font-size:0.72rem; color:#60a5fa;"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div style="background:rgba(0,0,0,0.18); border:1px solid rgba(255,255,255,0.06); border-radius:12px; padding:12px 14px;">
                            <label style="display:flex; align-items:flex-start; gap:10px; margin:0;">
                                <input
                                    type="checkbox"
                                    id="editDeepLinking-${id}"
                                    ${deepLinkingEnabled ? 'checked' : ''}
                                    ${deepLinkingAllowed ? '' : 'disabled'}
                                    style="width:18px; height:18px; margin-top:1px; accent-color:var(--primary); cursor:${deepLinkingAllowed ? 'pointer' : 'not-allowed'};"
                                >
                                <span style="display:flex; flex-direction:column; gap:4px;">
                                    <span style="font-weight:600;">Ativar deep linking neste item</span>
                                    <small style="color:var(--text-dim);">
                                        ${deepLinkingAllowed
                    ? 'Quando ativo, o redirecionamento por dispositivo (iOS/Android/Windows) é aplicado.'
                    : 'Seu plano atual não permite Deep Linking (Apps).'}
                                    </small>
                                </span>
                            </label>
                        </div>

                        <div style="background:rgba(59,130,246,0.06); border:1px solid rgba(96,165,250,0.22); border-radius:10px; padding:12px 14px;">
                            <label style="display:flex; align-items:flex-start; gap:10px; margin:0;">
                                <input type="checkbox" id="editPreciseLink-${id}" ${requestPreciseEnabled ? 'checked' : ''} style="width:18px; height:18px; margin-top:1px; accent-color:var(--primary); cursor:pointer;">
                                <span style="display:flex; flex-direction:column; gap:4px;">
                                    <span style="font-weight:600;">Solicitar localização exata (opcional)</span>
                                    <small style="color:var(--text-dim);">O visitante verá um pedido de permissão do navegador. Se negar, o redirecionamento ocorre normalmente. Não combina com pixels de retargeting intermediários neste link.</small>
                                </span>
                            </label>
                        </div>

                        <div style="display:flex; justify-content:flex-end;">
                            <button type="submit" class="btn-primary" style="width:auto; min-width:220px; padding:10px 22px;">
                                Salvar Alterações
                            </button>
                        </div>
                    </form>
                </div>
            `;
            initSocialPreviewEditor(id, link);
        }
    } else if (tab === 'rules') {
        const startVal = link.start_date ? link.start_date.replace(' ', 'T') : '';
        const endVal = link.end_date ? link.end_date.replace(' ', 'T') : '';
        const expClicks = link.expire_clicks || '';
        const fallback = link.fallback_url || '';
        const triggerClicks = link.trigger_clicks || '';
        const triggerUrl = link.trigger_url || '';

        container.innerHTML = `
            <div class="expand-content-grid" style="grid-template-columns: 1fr;">
                <form onsubmit="saveEditRules(event, ${id}, '${section}')" style="display:flex; flex-direction:column; gap:20px;">
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; background:var(--interactive-soft); padding:15px; border-radius:10px; border:1px solid var(--border-light);">
                        <div style="grid-column: 1/-1;">
                            <h4 style="margin:0; color:#3b82f6;"><i class="fas fa-clock"></i> Agendamento e Expiração</h4>
                            <p style="font-size:0.8rem; color:var(--text-dim); margin-top:5px;">Define quando o link funcionará. Se deixado em branco, funcionará sempre.</p>
                        </div>
                        <div class="form-group">
                            <label>Ativar a partir de:</label>
                            <input type="datetime-local" id="ruleStart-${id}" value="${startVal}">
                        </div>
                        <div class="form-group">
                            <label>Expirar em (Data Final):</label>
                            <input type="datetime-local" id="ruleEnd-${id}" value="${endVal}">
                        </div>
                        <div class="form-group" style="grid-column: 1/-1;">
                            <label>Expirar após X cliques totais:</label>
                            <input type="number" id="ruleExpClicks-${id}" value="${expClicks}" placeholder="Ex: 1000" min="1" style="max-width: 250px;">
                        </div>
                        <div class="form-group" style="grid-column: 1/-1;">
                            <label>URL de Redirecionamento (Fallback) ao Expirar:</label>
                            <input type="url" id="ruleFallback-${id}" value="${fallback}" placeholder="https://..." title="Para onde vão os acessos após a expiração.">
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; background:var(--interactive-soft); padding:15px; border-radius:10px; border:1px solid var(--border-light);">
                        <div style="grid-column: 1/-1;">
                            <h4 style="margin:0; color:#8b5cf6;"><i class="fas fa-exchange-alt"></i> Gatilho: Mudar Destino por Volume</h4>
                            <p style="font-size:0.8rem; color:var(--text-dim); margin-top:5px;">Muda a URL de destino automaticamente ao atingir determinado número de cliques.</p>
                        </div>
                        <div class="form-group" style="grid-column: 1/-1;">
                            <label>Mudar ao atingir X cliques totais:</label>
                            <input type="number" id="ruleTrigClicks-${id}" value="${triggerClicks}" placeholder="Ex: 500" min="1" style="max-width: 250px;">
                        </div>
                        <div class="form-group" style="grid-column: 1/-1;">
                            <label>Nova URL de Destino (Após Gatilho):</label>
                            <input type="url" id="ruleTrigUrl-${id}" value="${triggerUrl}" placeholder="https://...">
                        </div>
                    </div>

                    <div style="display:flex; gap:10px; justify-content:flex-end;">
                        <button type="submit" class="btn-primary" style="padding:12px 30px;"><i class="fas fa-save"></i> Salvar Regras</button>
                    </div>
                </form>
                    </div>
                </form>
            </div>
        `;
    } else if (tab === 'tracking') {
        const isTracking = parseInt(link.tracking_enabled) === 1;
        const isDwellMonitoring = parseInt(link.monitor_dwell_enabled || 0, 10) === 1;
        const toggleChecked = isTracking ? 'checked' : '';
        const dwellToggleChecked = isDwellMonitoring ? 'checked' : '';
        const retargetingState = parseRetargetingPixelsState(link.retargeting_pixels_json);

        container.innerHTML = `
            <div class="expand-content-grid" style="grid-template-columns: 1fr;">
                <div class="surface-primary-panel" style="padding:15px; border-radius:10px;">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <h4 style="margin:0; color:var(--primary);"><i class="fas fa-chart-line"></i> Tracking Interno (Encurtou)</h4>
                            <p style="font-size:0.8rem; color:var(--text-dim); margin-top:5px; margin-bottom:0;">
                                Mapeie compras, leads ou cliques no site e conecte-os de volta a este link (e à Variante A/B original).
                            </p>
                        </div>
                        <label class="switch">
                            <input type="checkbox" id="toggleTrack-${id}" ${toggleChecked} onchange="toggleTracking(${id}, this.checked, '${section}')">
                            <span class="slider round"></span>
                        </label>
                    </div>
                </div>

                <div style="background:rgba(139, 92, 246, 0.07); border:1px solid rgba(139, 92, 246, 0.25); padding:15px; border-radius:10px;">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <h4 style="margin:0; color:#a78bfa;"><i class="fas fa-hourglass-half"></i> Monitorar permanência</h4>
                            <p style="font-size:0.8rem; color:var(--text-dim); margin-top:5px; margin-bottom:0;">
                                Registra o tempo de permanência no destino quando o script <code>tracker.js</code> estiver instalado na página final.
                            </p>
                            <p style="font-size:0.78rem; color:#c4b5fd; margin-top:8px; margin-bottom:0; line-height:1.45;">
                                Checklist rápido: 1) ativar este toggle no link, 2) instalar <code>&lt;script src="/tracker.js" defer&gt;&lt;/script&gt;</code> na página destino (ex.: <code>/marketing.php</code>), 3) acessar pela URL encurtada (com <code>ep_cid</code>), 4) permanecer por mais de 1s.
                            </p>
                        </div>
                        <label class="switch">
                            <input type="checkbox" id="toggleDwell-${id}" ${dwellToggleChecked} onchange="toggleDwellMonitor(${id}, this.checked, '${section}')">
                            <span class="slider round"></span>
                        </label>
                    </div>
                </div>

                ${isTracking ? `
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-top: 10px;">
                    
                    <div style="background:var(--card); padding:20px; border-radius:10px; border:1px solid rgba(255,255,255,0.05);">
                        <h4 style="margin-bottom:15px; color:#8b5cf6;"><i class="fas fa-boxes"></i> Eventos Cadastrados</h4>
                        <div id="trackingActionsList-${id}" style="display:flex; flex-direction:column; gap:10px; margin-bottom:15px; max-height: 250px; overflow-y:auto; padding-right:5px;">
                            <div style="text-align:center; padding:10px; color:var(--text-dim);">Carregando eventos... <i class="fas fa-spinner fa-spin"></i></div>
                        </div>
                        <button onclick="openTrackingActionModal(${id})" class="btn-text" style="width:100%; border:1px dashed rgba(255,255,255,0.2); padding:10px; border-radius:6px;"><i class="fas fa-plus"></i> Novo Evento</button>
                    </div>

                    <div style="background:var(--card); padding:20px; border-radius:10px; border:1px solid rgba(255,255,255,0.05);">
                        <h4 style="margin-bottom:15px; color:#3b82f6;"><i class="fas fa-code"></i> Script de Instalação</h4>
                        <p style="font-size:0.8rem; color:var(--text-dim);">1. Coloque este script Base no <code>&lt;head&gt;</code> de <b>todas</b> as páginas (incluindo a página de destino de entrada).</p>
                        <div style="position:relative; margin-top:10px;">
                            <textarea readonly id="baseTrackerCode" style="width:100%; height:60px; font-family:monospace; font-size:0.75rem; background:var(--interactive-soft); border:1px solid var(--border); border-radius:6px; padding:10px; color:var(--text-soft);"><script src="https://${window.location.hostname}/tracker.js" async></script></textarea>
                            <button onclick="navigator.clipboard.writeText(document.getElementById('baseTrackerCode').value); alert('Copiado!')" class="btn-action" style="position:absolute; top:5px; right:5px; background:var(--primary); color:#000; font-size:0.7rem;"><i class="fas fa-copy"></i></button>
                        </div>
                        <p style="font-size:0.8rem; color:var(--text-dim); margin-top:15px;">2. Após criar um Evento ao lado, clique no botão <i class="fas fa-code"></i> dele para obter o código de disparo e usar num Botão ou Página de Obrigado.</p>
                    </div>

                </div>
                ` : `
                <div style="text-align:center; padding:40px; color:var(--text-dim);">
                    <i class="fas fa-power-off" style="font-size:2rem; margin-bottom:10px; opacity:0.5;"></i><br>
                    O rastreamento de conversões está desativado.<br>Ative na chave para injetar o Tracker ID deste link.
                    <div style="margin-top:12px; font-size:0.78rem; color:#93c5fd;">
                        Para permanência funcionar, a página destino (ex.: <code>/marketing.php</code>) também precisa carregar o <code>tracker.js</code>.
                    </div>
                </div>
                `}
                <div style="margin-top:12px; background:rgba(59,130,246,0.08); border:1px solid rgba(96,165,250,0.28); padding:15px; border-radius:10px;">
                    <h4 style="margin:0 0 6px 0; color:#93c5fd;"><i class="fas fa-bullseye"></i> Pixels de Retargeting Externo (Ads)</h4>
                    <p style="font-size:0.8rem; color:var(--text-dim); margin:0 0 10px 0;">
                        Use esta área para enviar a visita deste link para plataformas de anúncios, mesmo quando o destino final for de terceiros.
                    </p>
                    <p style="font-size:0.76rem; color:#cbd5e1; margin:0 0 12px 0;">
                        Dica: informe um ID por linha. Esses pixels são independentes do Tracking Interno acima.
                    </p>
                    ${renderRetargetingPixelsEditor(id, retargetingState, section)}
                </div>
            </div>
        `;
        if (isTracking) {
            loadTrackingActions(id);
        }
    }
}

async function saveEditRules(e, id, section) {
    e.preventDefault();
    const btn = e.target.querySelector('button[type="submit"]');
    const oldText = btn.innerHTML;
    btn.innerHTML = 'Salvando... <i class="fas fa-spinner fa-spin"></i>';
    btn.disabled = true;

    const formatDT = (v) => v ? v.replace('T', ' ') + ':00' : '';

    const start_date = formatDT(document.getElementById(`ruleStart-${id}`).value);
    const end_date = formatDT(document.getElementById(`ruleEnd-${id}`).value);
    const expire_clicks = document.getElementById(`ruleExpClicks-${id}`).value;
    const fallback_url = document.getElementById(`ruleFallback-${id}`).value;
    const trigger_clicks = document.getElementById(`ruleTrigClicks-${id}`).value;
    const trigger_url = document.getElementById(`ruleTrigUrl-${id}`).value;

    const fd = new FormData();
    fd.append('action', 'edit_rules');
    fd.append('id', id);
    fd.append('start_date', start_date);
    fd.append('end_date', end_date);
    fd.append('expire_clicks', expire_clicks);
    fd.append('fallback_url', fallback_url);
    fd.append('trigger_clicks', trigger_clicks);
    fd.append('trigger_url', trigger_url);

    try {
        const res = await fetch('manage_links.php', { method: 'POST', body: fd, credentials: 'include' });
        const data = await res.json();
        if (data.success) {
            if (section === 'link') loadMyLinks(); else loadQRLinks();
        } else {
            if (handlePlanRestrictionResponse(data, { feature: 'rules', title: 'Regras avançadas bloqueadas' })) {
                return;
            }
            showStatus(section === 'link' ? 'link' : 'qr', data.message || 'Erro ao salvar regras.', 'error');
        }
    } catch (err) {
        console.error(err);
        showStatus(section === 'link' ? 'link' : 'qr', 'Erro de conexão ao salvar regras.', 'error');
    } finally {
        btn.innerHTML = oldText; btn.disabled = false;
    }
}

async function saveEditAbTest(e, id, section) {
    e.preventDefault();
    const link = myLinksData.find(l => l.id == id);
    if (!link || !link.variants) return;

    let newVariants = [];
    link.variants.forEach(v => {
        newVariants.push({
            id: v.id,
            name: document.getElementById(`editAbName-${id}-${v.id}`).value,
            url: document.getElementById(`editAbUrl-${id}-${v.id}`).value,
            weight: document.getElementById(`editAbWeight-${id}-${v.id}`).value,
            status: document.getElementById(`editAbStatus-${id}-${v.id}`).value
        });
    });

    const fd = new FormData();
    fd.append('action', 'edit_ab_test');
    fd.append('id', id);
    fd.append('variants', JSON.stringify(newVariants));
    const preciseAb = document.getElementById(`editPreciseAb-${id}`);
    fd.append('request_precise_location', preciseAb && preciseAb.checked ? '1' : '0');

    const res = await fetch('manage_links.php', { method: 'POST', body: fd, credentials: 'include' });
    const data = await res.json();
    if (data.success) {
        if (section === 'link') loadMyLinks(); else loadQRLinks();
    } else {
        if (handlePlanRestrictionResponse(data, { feature: 'ab_testing', title: 'Teste A/B bloqueado no plano' })) {
            return;
        }
        showStatus(section === 'link' ? 'link' : 'qr', data.message || 'Erro ao salvar teste A/B', 'error');
    }
}

function truncatePreviewText(value, maxLen) {
    const text = (value || '').trim();
    if (text.length <= maxLen) return text;
    return text.slice(0, Math.max(0, maxLen - 1)).trimEnd() + '…';
}

function safeHostnameFromUrl(value) {
    try {
        return new URL(value).hostname || '';
    } catch (e) {
        return '';
    }
}

function buildAutoFaviconUrl(urlValue) {
    const host = safeHostnameFromUrl(urlValue);
    if (!host) return '';
    return `https://www.google.com/s2/favicons?domain=${host}&sz=64`;
}

function initSocialPreviewEditor(id, link) {
    const titleInput = document.getElementById(`editSocialTitle-${id}`);
    const descInput = document.getElementById(`editSocialDescription-${id}`);
    const imageInput = document.getElementById(`editSocialImage-${id}`);
    const faviconInput = document.getElementById(`editFavicon-${id}`);
    const destinationInput = document.getElementById(`editUrl-${id}`);
    if (!titleInput || !descInput || !imageInput || !faviconInput || !destinationInput) return;

    const shortUrl = getFullShortUrl(link);
    const defaultTitle = (link?.title || 'Link LinkNeo.pro').trim() || 'Link LinkNeo.pro';
    const defaultDescription = 'Abrir conteúdo do link encurtado.';
    const networks = ['wa', 'fb', 'x'];

    const updatePreview = () => {
        const title = truncatePreviewText(titleInput.value || defaultTitle, 80) || defaultTitle;
        const description = truncatePreviewText(descInput.value || defaultDescription, 160) || defaultDescription;
        const imageUrl = (imageInput.value || '').trim();
        const faviconUrl = (faviconInput.value || '').trim() || buildAutoFaviconUrl(destinationInput.value || '');
        const displayUrl = shortUrl;
        const displayDomain = safeHostnameFromUrl(shortUrl) || domain;

        networks.forEach((network) => {
            const titleEl = document.getElementById(`socialPreviewTitle-${network}-${id}`);
            const descEl = document.getElementById(`socialPreviewDesc-${network}-${id}`);
            const urlEl = document.getElementById(`socialPreviewUrl-${network}-${id}`);
            const domainEl = document.getElementById(`socialPreviewDomain-${network}-${id}`);
            const imgEl = document.getElementById(`socialPreviewImg-${network}-${id}`);
            const imgWrapEl = document.getElementById(`socialPreviewImgWrap-${network}-${id}`);
            const favEl = document.getElementById(`socialPreviewFav-${network}-${id}`);

            if (titleEl) titleEl.textContent = title;
            if (descEl) descEl.textContent = description;
            if (urlEl) urlEl.textContent = displayUrl;
            if (domainEl) domainEl.textContent = displayDomain;

            if (favEl) {
                favEl.src = faviconUrl || '';
                favEl.style.display = faviconUrl ? 'inline-block' : 'none';
            }

            if (imgEl && imgWrapEl) {
                if (imageUrl) {
                    imgWrapEl.style.display = 'block';
                    imgEl.src = imageUrl;
                } else {
                    imgWrapEl.style.display = 'none';
                    imgEl.src = '';
                }
            }
        });
    };

    const watched = [titleInput, descInput, imageInput, faviconInput, destinationInput];
    watched.forEach((el) => {
        el.addEventListener('input', updatePreview);
        el.addEventListener('change', updatePreview);
    });
    updatePreview();
}

async function saveEditLinkExpanded(e, id, section) {
    e.preventDefault();
    const url = document.getElementById(`editUrl-${id}`).value;
    const ios = document.getElementById(`editIos-${id}`).value;
    const android = document.getElementById(`editAndroid-${id}`).value;
    const windows = document.getElementById(`editWindows-${id}`).value;
    const socialTitle = document.getElementById(`editSocialTitle-${id}`)?.value || '';
    const socialDescription = document.getElementById(`editSocialDescription-${id}`)?.value || '';
    const socialImageUrl = document.getElementById(`editSocialImage-${id}`)?.value || '';
    const faviconUrl = document.getElementById(`editFavicon-${id}`)?.value || '';
    const deepLinkingInput = document.getElementById(`editDeepLinking-${id}`);
    const deepLinkingEnabled = deepLinkingInput && deepLinkingInput.checked ? '1' : '0';
    const preciseLinkInput = document.getElementById(`editPreciseLink-${id}`);
    const requestPreciseLocation = preciseLinkInput && preciseLinkInput.checked ? '1' : '0';

    const fd = new FormData();
    fd.append('action', 'edit_url');
    fd.append('id', id);
    fd.append('url', url);
    fd.append('url_ios', ios);
    fd.append('url_android', android);
    fd.append('url_windows', windows);
    fd.append('social_title', socialTitle);
    fd.append('social_description', socialDescription);
    fd.append('social_image_url', socialImageUrl);
    fd.append('favicon_url', faviconUrl);
    fd.append('deep_linking_enabled', deepLinkingEnabled);
    fd.append('request_precise_location', requestPreciseLocation);

    const res = await fetch('manage_links.php', { method: 'POST', body: fd, credentials: 'include' });
    const data = await res.json();
    if (data.success) {
        if (section === 'link') loadMyLinks(); else loadQRLinks();
    }
}

/**
 * Converte o row vindo do banco em um estado inicial para o builder de QR.
 * Faz fallback inteligente para QR Codes "legacy" (criados antes da migração)
 * a partir do padrão do `original_url` atual (wa.me, mailto:, tel:, vcard, etc).
 */
function qrBuilderStateFromLink(link) {
    let metadata = {};
    if (link.destination_metadata) {
        if (typeof link.destination_metadata === 'string') {
            try { metadata = JSON.parse(link.destination_metadata) || {}; }
            catch (e) { metadata = {}; }
        } else if (typeof link.destination_metadata === 'object') {
            metadata = link.destination_metadata;
        }
    }

    const declared = (link.destination_type || '').toLowerCase();
    if (['link', 'social', 'vcard', 'phone', 'email'].includes(declared)) {
        return { type: declared, metadata };
    }

    // Inferência para registros legados: deduz o tipo pela `original_url`.
    const url = (link.original_url || '').trim();
    if (/^mailto:/i.test(url)) {
        const m = url.match(/^mailto:([^?]+)(?:\?(.*))?$/i);
        const params = new URLSearchParams(m && m[2] ? m[2] : '');
        return {
            type: 'email', metadata: {
                to: m ? m[1] : '',
                subject: params.get('subject') || '',
                body: params.get('body') || ''
            }
        };
    }
    if (/^tel:/i.test(url)) {
        return { type: 'phone', metadata: { phone: url.replace(/^tel:/i, '') } };
    }
    if (/^BEGIN:VCARD/i.test(url)) {
        const get = (field) => {
            const r = new RegExp('^' + field + ':(.*)$', 'im');
            const m = url.match(r);
            return m ? m[1].trim() : '';
        };
        return {
            type: 'vcard', metadata: {
                name: get('FN'), phone: get('TEL'), email: get('EMAIL'), org: get('ORG')
            }
        };
    }
    const socialMap = [
        [/wa\.me\/(\d+)/i, 'whatsapp', 1],
        [/instagram\.com\/([^\/?#]+)/i, 'instagram', 1],
        [/facebook\.com\/([^\/?#]+)/i, 'facebook', 1],
        [/linkedin\.com\/in\/([^\/?#]+)/i, 'linkedin', 1],
        [/tiktok\.com\/@?([^\/?#]+)/i, 'tiktok', 1]
    ];
    for (const [re, platform] of socialMap) {
        const m = url.match(re);
        if (m) return { type: 'social', metadata: { platform, value: m[1] } };
    }
    return { type: 'link', metadata: { url } };
}

function renderQREditPanel(container, link) {
    const id = link.id;
    const state = qrBuilderStateFromLink(link);
    const deepLinkingEnabled = parseInt(link.deep_linking_enabled || 0, 10) === 1;
    const deepLinkingAllowed = userFeatures.deep_linking === true;
    const requestPreciseQr = parseInt(link.request_precise_location || 0, 10) === 1;
    const fullUrlBase = (link.subdomain && link.subdomain !== 'ROOT')
        ? `${link.subdomain}.${domain}/${link.path}`
        : `${domain}/${link.path}`;

    container.innerHTML = `
        <div class="expand-content-grid" style="grid-template-columns: 1fr;">
            <form onsubmit="saveEditQRDestination(event, ${id})" style="display:flex; flex-direction:column; gap:15px;">
                <div class="surface-primary-panel" style="padding:12px 15px; border-radius:10px;">
                    <div style="display:flex; align-items:center; gap:10px;">
                        <i class="fas fa-qrcode" style="color:var(--primary); font-size:1.5rem;"></i>
                        <div>
                            <strong style="color:var(--primary); font-size:0.85rem;">Edição de QR Code Dinâmico</strong>
                            <div style="font-size:0.8rem; color:var(--text-dim); margin-top:2px;">
                                Troque o <b>tipo</b> (Link, Social, vCard…) sem reimprimir o código. O slug <code>/${link.path}</code> permanece o mesmo.
                            </div>
                        </div>
                    </div>
                </div>

                ${qrBuilderMarkup(`ed${id}_`, state)}

                <div style="background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.08); border-radius:10px; padding:12px;">
                    <label style="display:flex; align-items:center; gap:10px; margin:0;">
                        <input type="checkbox" id="qrDeepLinking-${id}" ${deepLinkingEnabled ? 'checked' : ''} ${deepLinkingAllowed ? '' : 'disabled'}>
                        <span>Ativar deep linking neste QR Code</span>
                    </label>
                    <small style="color:var(--text-dim); display:block; margin-top:6px;">
                        ${deepLinkingAllowed
            ? 'Quando ativo, o redirecionamento por dispositivo (iOS/Android/Windows) também será aplicado para este QR Code.'
            : 'Seu plano atual não permite Deep Linking (Apps).'}
                    </small>
                </div>

                <div style="background:rgba(59,130,246,0.06); border:1px solid rgba(96,165,250,0.22); border-radius:10px; padding:12px;">
                    <label style="display:flex; align-items:flex-start; gap:10px; margin:0;">
                        <input type="checkbox" id="qrPrecise-${id}" ${requestPreciseQr ? 'checked' : ''} style="width:18px; height:18px; margin-top:1px; accent-color:var(--primary);">
                        <span style="display:flex; flex-direction:column; gap:4px;">
                            <span style="font-weight:600;">Solicitar localização exata (opcional)</span>
                            <small style="color:var(--text-dim);">Pedido de permissão no navegador; opcional para o visitante.</small>
                        </span>
                    </label>
                </div>

                <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:10px;">
                    <button type="submit" class="btn-primary" style="padding:12px 30px;">
                        <i class="fas fa-save"></i> Salvar Novo Destino
                    </button>
                </div>
                <div id="qrEditStatus-${id}" style="font-size:0.85rem; color:var(--text-dim); text-align:right;"></div>
            </form>
        </div>
    `;
}

async function saveEditQRDestination(e, id) {
    e.preventDefault();
    const btn = e.target.querySelector('button[type="submit"]');
    const statusEl = document.getElementById(`qrEditStatus-${id}`);
    const oldText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = 'Salvando... <i class="fas fa-spinner fa-spin"></i>';

    const state = qrBuilderReadState(`ed${id}_`);
    const deepLinkingInput = document.getElementById(`qrDeepLinking-${id}`);
    const deepLinkingEnabled = deepLinkingInput && deepLinkingInput.checked ? '1' : '0';
    if (!state.data) {
        if (statusEl) statusEl.innerHTML = '<span style="color:#ef4444;">Preencha os campos do destino antes de salvar.</span>';
        btn.disabled = false; btn.innerHTML = oldText;
        return;
    }

    const fd = new FormData();
    fd.append('action', 'edit_qr_destination');
    fd.append('id', id);
    fd.append('destination_type', state.type);
    fd.append('destination_metadata', JSON.stringify(state.metadata));
    fd.append('deep_linking_enabled', deepLinkingEnabled);
    const qrPrecise = document.getElementById(`qrPrecise-${id}`);
    fd.append('request_precise_location', qrPrecise && qrPrecise.checked ? '1' : '0');

    try {
        const res = await fetch('manage_links.php', { method: 'POST', body: fd, credentials: 'include' });
        const data = await res.json();
        if (data.success) {
            if (statusEl) statusEl.innerHTML = '<span style="color:#10b981;"><i class="fas fa-check"></i> Destino atualizado com sucesso.</span>';
            await loadQRLinks();
        } else {
            if (statusEl) statusEl.innerHTML = `<span style="color:#ef4444;">${data.message || 'Erro ao salvar.'}</span>`;
        }
    } catch (err) {
        console.error(err);
        if (statusEl) statusEl.innerHTML = '<span style="color:#ef4444;">Erro de conexão ao salvar.</span>';
    } finally {
        btn.disabled = false;
        btn.innerHTML = oldText;
    }
}

// --- CONVERSION TRACKING FUNCTIONS ---

function parseRetargetingPixelsState(rawValue) {
    const base = {
        facebook: [],
        google_ads: [],
        linkedin: [],
        twitter: [],
        pinterest: [],
        tiktok: []
    };

    let parsed = rawValue;
    if (typeof rawValue === 'string' && rawValue.trim() !== '') {
        try {
            parsed = JSON.parse(rawValue);
        } catch (e) {
            parsed = {};
        }
    }

    if (!parsed || typeof parsed !== 'object') {
        return base;
    }

    Object.keys(base).forEach((key) => {
        const incoming = parsed[key];
        if (Array.isArray(incoming)) {
            base[key] = incoming.map(v => String(v || '').trim()).filter(Boolean).slice(0, 10);
        } else if (typeof incoming === 'string' && incoming.trim() !== '') {
            base[key] = [incoming.trim()];
        }
    });

    return base;
}

function renderRetargetingPixelsEditor(linkId, state, section) {
    const fields = RETARGETING_PLATFORMS.map((platform) => {
        const values = Array.isArray(state[platform.key]) ? state[platform.key] : [];
        const text = values.join('\n');
        return `
            <div class="form-group" style="margin:0;">
                <label style="font-size:0.78rem; color:#bfdbfe;">${platform.label}</label>
                <textarea id="retarget-${platform.key}-${linkId}" rows="2" placeholder="${platform.placeholder}" style="width:100%; resize:vertical;">${text}</textarea>
            </div>
        `;
    }).join('');

    return `
        <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(210px,1fr)); gap:10px;">
            ${fields}
        </div>
        <div style="display:flex; justify-content:flex-end; margin-top:12px;">
            <button type="button" class="btn-primary" onclick="saveRetargetingPixels(${linkId}, '${section}')" style="min-width:220px;">
                <i class="fas fa-save"></i> Salvar Pixels Externos
            </button>
        </div>
    `;
}

function collectRetargetingPixelsPayload(linkId) {
    const payload = {};
    RETARGETING_PLATFORMS.forEach((platform) => {
        const input = document.getElementById(`retarget-${platform.key}-${linkId}`);
        const raw = (input?.value || '')
            .split(/\r?\n|,|;|\|/)
            .map(v => v.trim())
            .filter(Boolean);
        payload[platform.key] = Array.from(new Set(raw)).slice(0, 10);
    });
    return payload;
}

async function saveRetargetingPixels(linkId, section) {
    const fd = new FormData();
    fd.append('action', 'save_retargeting_pixels');
    fd.append('id', String(linkId));
    fd.append('pixels', JSON.stringify(collectRetargetingPixelsPayload(linkId)));

    try {
        const response = await fetch('manage_links.php', { method: 'POST', body: fd, credentials: 'include' });
        const data = await response.json();
        if (!data.success) {
            if (handlePlanRestrictionResponse(data, { feature: 'tracking', title: 'Retargeting bloqueado no plano' })) {
                return;
            }
            showStatus(section === 'link' ? 'link' : 'qr', data.message || 'Não foi possível salvar os pixels externos.', 'error');
            return;
        }

        if (section === 'link') {
            await loadMyLinks();
        } else {
            await loadQRLinks();
        }
        showStatus(section === 'link' ? 'link' : 'qr', 'Pixels de retargeting salvos com sucesso.', 'success');
        toggleItemExpansion(linkId, section, 'tracking');
    } catch (err) {
        console.error(err);
        showStatus(section === 'link' ? 'link' : 'qr', 'Erro de conexão ao salvar pixels de retargeting.', 'error');
    }
}

async function toggleTracking(id, status, section) {
    const fd = new FormData();
    fd.append('action', 'toggle_tracking');
    fd.append('id', id);
    fd.append('status', status ? 1 : 0);
    const response = await fetch('manage_links.php', { method: 'POST', body: fd, credentials: 'include' });
    const data = await response.json();
    if (!data.success) {
        if (handlePlanRestrictionResponse(data, { feature: 'tracking', title: 'Tracking bloqueado no plano' })) {
            return;
        }
        showStatus(section === 'link' ? 'link' : 'qr', data.message || 'Não foi possível atualizar o rastreamento.', 'error');
        return;
    }
    if (section === 'link') await loadMyLinks(); else await loadQRLinks();
    toggleItemExpansion(id, section, 'tracking');
}

async function toggleDwellMonitor(id, status, section) {
    const fd = new FormData();
    fd.append('action', 'toggle_dwell_monitor');
    fd.append('id', id);
    fd.append('status', status ? 1 : 0);
    const response = await fetch('manage_links.php', { method: 'POST', body: fd, credentials: 'include' });
    const data = await response.json();
    if (!data.success) {
        if (handlePlanRestrictionResponse(data, { feature: 'dwell_monitor', title: 'Monitoramento de permanência bloqueado no plano' })) {
            return;
        }
        showStatus(section === 'link' ? 'link' : 'qr', data.message || 'Não foi possível atualizar o monitoramento de permanência.', 'error');
        return;
    }
    if (section === 'link') await loadMyLinks(); else await loadQRLinks();
    toggleItemExpansion(id, section, 'tracking');
}

async function loadTrackingActions(linkId) {
    const container = document.getElementById(`trackingActionsList-${linkId}`);
    if (!container) return;
    try {
        const res = await fetch(`manage_links.php?action=get_actions&link_id=${linkId}`, { credentials: 'include' });
        const data = await res.json();
        if (data.success) {
            if (data.actions.length === 0) {
                container.innerHTML = `<div style="text-align:center; padding:10px; font-size:0.8rem; color:var(--text-dim);">Nenhum evento mapeado.<br>Crie um novo abaixo.</div>`;
                return;
            }
            const actionsHtml = data.actions.map(a => `
                <div style="background:rgba(255,255,255,0.02); border:1px solid rgba(255,255,255,0.05); border-radius:8px; padding:10px; display:flex; justify-content:space-between; align-items:center;">
                    <div style="flex:1;">
                        <div style="font-weight:600; font-size:0.9rem; color:${a.is_active == 1 ? '#fff' : '#666'};">${a.name}</div>
                        <div style="font-size:0.7rem; color:var(--text-dim);">Slug: <code>${a.ref_slug}</code></div>
                        <div style="font-size:0.75rem; color:#f59e0b; margin-top:3px;"><i class="fas fa-check-circle"></i> ${a.total_conversions} conv.</div>
                    </div>
                    <div style="display:flex; gap:5px; margin-left:10px;">
                        <button onclick="viewTrackingSnippet('${a.name}', '${a.ref_slug}')" class="btn-action" style="background:#3b82f6;" title="Ver Código"><i class="fas fa-code"></i></button>
                        <button onclick="toggleTrackingAction(${a.id}, ${linkId}, ${a.is_active == 1 ? 0 : 1})" class="btn-action" style="background:${a.is_active == 1 ? '#10b981' : '#6b7280'};" title="${a.is_active == 1 ? 'Desativar' : 'Ativar'}"><i class="fas fa-power-off"></i></button>
                        <button onclick="deleteTrackingAction(${a.id}, ${linkId})" class="btn-action btn-delete"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
            `).join('');

            const chartId = `chartTrackingActions-${linkId}`;
            container.innerHTML = `
                ${actionsHtml}
                <div style="margin-top:12px; padding-top:12px; border-top:1px solid rgba(148,163,184,0.2);">
                    <div style="font-size:0.8rem; color:var(--text-dim); margin-bottom:8px;">
                        Conversões por evento (tracking interno deste link)
                    </div>
                    <div style="height:220px;">
                        <canvas id="${chartId}"></canvas>
                    </div>
                </div>
            `;

            const labels = data.actions.map(a => a.name);
            const values = data.actions.map(a => Number(a.total_conversions || 0));
            renderChart(chartId, 'bar', labels, values, {
                backgroundColor: '#1A8CFF',
                borderColor: getBrandTokens().primary
            });
        }
    } catch (e) {
        container.innerHTML = `<div style="color:red; font-size:0.8em;">Erro ao buscar ações.</div>`;
    }
}

function openTrackingActionModal(linkId) {
    if (!document.getElementById('modalTrackingAction')) {
        const div = document.createElement('div');
        div.id = 'modalTrackingAction';
        div.className = 'modal-content hidden';
        div.onclick = (e) => e.stopPropagation();
        div.style.maxWidth = '400px';
        div.innerHTML = `
            <div class="modal-header" style="margin-bottom:15px;">
                <h3>Novo Evento de Conversão</h3>
                <button type="button" onclick="closeModals()" class="close-btn"><i class="fas fa-times"></i></button>
            </div>
            <form onsubmit="saveTrackingAction(event)" style="display:flex; flex-direction:column; gap:15px;">
                <input type="hidden" id="trkLinkId">
                <div class="form-group">
                    <label>Nome Amigável (ex: Compra Confirmada)</label>
                    <input type="text" id="trkName" required style="width:100%;" class="input">
                </div>
                <div class="form-group">
                    <label>Slug Técnico (letras, nums, sem espaço)</label>
                    <input type="text" id="trkSlug" placeholder="ex: compra_confirmada" required pattern="[A-Za-z0-9_-]+" style="width:100%;" class="input">
                </div>
                <button type="submit" class="btn-primary" style="padding:12px; margin-top:10px;">Salvar Evento</button>
            </form>
        `;
        document.getElementById('modalOverlay').appendChild(div);
    }
    document.getElementById('trkLinkId').value = linkId;
    document.getElementById('trkName').value = '';
    document.getElementById('trkSlug').value = '';
    openModal('modalTrackingAction');
}

async function saveTrackingAction(e) {
    e.preventDefault();
    const btn = e.target.querySelector('button');
    btn.disabled = true;
    const linkId = document.getElementById('trkLinkId').value;
    const fd = new FormData();
    fd.append('action', 'save_action');
    fd.append('link_id', linkId);
    fd.append('name', document.getElementById('trkName').value);
    fd.append('ref_slug', document.getElementById('trkSlug').value);

    try {
        const res = await fetch('manage_links.php', { method: 'POST', body: fd, credentials: 'include' });
        const data = await res.json();
        if (data.success) {
            closeModals();
            loadTrackingActions(linkId);
        } else {
            if (handlePlanRestrictionResponse(data, { feature: 'tracking', title: 'Eventos de tracking bloqueados' })) {
                return;
            }
            Swal.fire('Erro', data.message || 'Erro ao salvar. Tente outro slug.', 'error');
        }
    } finally {
        btn.disabled = false;
    }
}

async function toggleTrackingAction(actionId, linkId, status) {
    const fd = new FormData();
    fd.append('action', 'toggle_action');
    fd.append('action_id', actionId);
    fd.append('link_id', linkId);
    fd.append('status', status);
    await fetch('manage_links.php', { method: 'POST', body: fd, credentials: 'include' });
    loadTrackingActions(linkId);
}

async function deleteTrackingAction(actionId, linkId) {
    if (!confirm("Atenção: Deletar a ação não afetará os metadados dos eventos já registrados fisicamente, mas apagará a configuração da janela. Prosseguir?")) return;
    const fd = new FormData();
    fd.append('action', 'delete_action');
    fd.append('action_id', actionId);
    fd.append('link_id', linkId);
    await fetch('manage_links.php', { method: 'POST', body: fd, credentials: 'include' });
    loadTrackingActions(linkId);
}

function viewTrackingSnippet(name, slug) {
    const overlay = document.getElementById('modalOverlay');
    if (!overlay) return;

    let div = document.getElementById('modalTrackingSnippet');
    if (!div) {
        div = document.createElement('div');
        div.id = 'modalTrackingSnippet';
        overlay.appendChild(div);
    }
    div.className = 'modal-content modal-content--tracking-snippet hidden';
    div.onclick = (e) => e.stopPropagation();
    div.innerHTML = `
            <div class="modal-header">
                <h3 style="color:var(--primary);"><i class="fas fa-code"></i> Snippet: <span id="snipName"></span></h3>
                <button type="button" onclick="closeModals()" class="close-btn" aria-label="Fechar"><i class="fas fa-times"></i></button>
            </div>
            <p class="tracking-snippet-intro">Use uma das três abordagens abaixo na página ou botão de destino final, garantindo que o Script Base foi colado no HEAD.</p>
            <div class="tracking-snippet-callout tracking-snippet-callout--info">
                <div class="tracking-snippet-callout-title"><i class="fas fa-graduation-cap"></i> Como funciona o <code>ep_cid</code></div>
                <div class="tracking-snippet-callout-body">
                    Quando alguém entra pelo seu link encurtado, o Encurtou adiciona e salva um identificador único da visita (<code>ep_cid</code>).<br>
                    Depois, quando este snippet dispara, o sistema usa esse código para ligar a conversão ao clique correto.
                </div>
            </div>
            <div class="tracking-snippet-callout tracking-snippet-callout--warn">
                <div class="tracking-snippet-callout-title"><i class="fas fa-triangle-exclamation"></i> Sem <code>ep_cid</code> não há rastreamento</div>
                <div class="tracking-snippet-callout-body">
                    Se o usuário abrir a página direto (sem passar pelo link encurtado), o evento será ignorado.<br>
                    Exemplo de URL válida para teste: <code>https://seusite.com/obrigado?ep_cid=abc123</code>
                </div>
            </div>
            <div class="tracking-snippet-callout tracking-snippet-callout--success">
                <div class="tracking-snippet-callout-title"><i class="fas fa-list-check"></i> Checklist rápido (didático)</div>
                <div class="tracking-snippet-callout-body">
                    1) Ative o tracking do link.<br>
                    2) Crie o evento (pixel).<br>
                    3) Instale o Script Base no <code>&lt;head&gt;</code>.<br>
                    4) Cole o snippet de disparo na ação final (obrigado/botão).<br>
                    5) Teste acessando pela URL encurtada, não direto na página final.
                </div>
            </div>

            <div class="tracking-snippet-options">
                <div class="tracking-snippet-option">
                    <strong class="tracking-snippet-option-title">Opção 1: Página de Obrigado (Auto Disparo)</strong>
                    <span class="tracking-snippet-option-desc">Ideal para checkout, formulário enviado e páginas de confirmação.</span>
                    <textarea id="snipOption1" readonly class="tracking-snippet-code" rows="4" spellcheck="false"></textarea>
                </div>
                <div class="tracking-snippet-option">
                    <strong class="tracking-snippet-option-title">Opção 2: Ao Clicar num Botão (HTML)</strong>
                    <span class="tracking-snippet-option-desc">Use quando a confirmação acontece no clique de um botão específico.</span>
                    <textarea id="snipOption2" readonly class="tracking-snippet-code" rows="3" spellcheck="false"></textarea>
                </div>
                <div class="tracking-snippet-option">
                    <strong class="tracking-snippet-option-title">Opção 3: Usando Metadados (JavaScript Puro)</strong>
                    <span class="tracking-snippet-option-desc">Recomendado quando você precisa enviar valor, plano, produto, etc.</span>
                    <textarea id="snipOption3" readonly class="tracking-snippet-code" rows="5" spellcheck="false"></textarea>
                </div>
            </div>
        `;

    document.getElementById('snipName').innerText = name;

    document.getElementById('snipOption1').value = `<script>\n(function trackWhenReady(attempts){\n    if (window.Encurtou && typeof window.Encurtou.track === 'function') {\n        window.Encurtou.track('${slug}');\n        return;\n    }\n    if ((attempts || 0) >= 20) {\n        console.warn('Encurtou.track indisponível para ${slug}');\n        return;\n    }\n    setTimeout(function(){ trackWhenReady((attempts || 0) + 1); }, 250);\n})(0);\n</script>`;

    document.getElementById('snipOption2').value = `<button onclick="if(window.Encurtou && typeof window.Encurtou.track === 'function') window.Encurtou.track('${slug}')">\n    Confirmar\n</button>`;

    document.getElementById('snipOption3').value = `// Recomenda-se tratar a chamada\n(function trackWithMetadata(attempts){\n    if (window.Encurtou && typeof window.Encurtou.track === 'function') {\n        window.Encurtou.track('${slug}', { valor_brl: 199.90, desc: 'plano anual' });\n        return;\n    }\n    if ((attempts || 0) >= 20) return;\n    setTimeout(function(){ trackWithMetadata((attempts || 0) + 1); }, 250);\n})(0);`;

    openModal('modalTrackingSnippet');
}

// --- LOGICA DE QR CODES ---
let qrCode = null;
let qrCreatedModalCode = null;
let lastCreatedQRUrl = '';
let createdQRPreviewObjectUrl = '';
let currentQRMode = 'create'; // Global para controlar a fonte de dados do QR

function toggleQRMode(mode) {
    currentQRMode = mode;
    document.getElementById('qrModeSelect').classList.add('hidden');
    document.getElementById('qrModeCreate').classList.add('hidden');
    document.getElementById('tabQrSelect').classList.remove('active');
    document.getElementById('tabQrCreate').classList.remove('active');

    if (mode === 'select') {
        document.getElementById('qrModeSelect').classList.remove('hidden');
        document.getElementById('tabQrSelect').classList.add('active');
    } else {
        document.getElementById('qrModeCreate').classList.remove('hidden');
        document.getElementById('tabQrCreate').classList.add('active');
        if (!document.getElementById('qrNewPath').value) generateQRPath();
    }
    updateQRPreview();
}

/**
 * COMPONENTE REUTILIZÁVEL DE CRIAÇÃO/EDIÇÃO DE QR CODES DINÂMICOS
 * ---------------------------------------------------------------
 * Todos os inputs da UI são identificados via (prefix)+chave.
 * - prefix === ''            → usado pelo formulário de criação (markup estático em index.html).
 * - prefix === `ed${id}_`    → usado pelo painel de edição (HTML gerado dinamicamente por qrBuilderMarkup).
 *
 * Com isso temos UMA só lógica para criar e editar QR Codes, e a mesma
 * estrutura de abas funciona nos dois contextos.
 */

const QR_SOCIAL_PLATFORMS = ['whatsapp', 'instagram', 'facebook', 'linkedin', 'tiktok'];

function qrBuilderSocialLabel(platform) {
    if (platform === 'whatsapp') {
        return { label: 'Número do WhatsApp (com DDD)', placeholder: 'Ex: 5511999999999' };
    }
    return {
        label: `Usuário/Link do ${platform.charAt(0).toUpperCase() + platform.slice(1)}`,
        placeholder: 'Ex: seu_usuario'
    };
}

function qrBuilderSwitchType(type, prefix = '') {
    const typeEl = document.getElementById(`${prefix}qrNewType`);
    if (!typeEl) return;
    typeEl.value = type;

    const root = document.querySelector(`[data-qr-prefix="${prefix}"]`) || document;
    root.querySelectorAll('.qr-type-tabs button').forEach(b => b.classList.remove('active'));
    const btn = document.getElementById(`${prefix}btnType${type.charAt(0).toUpperCase() + type.slice(1)}`);
    if (btn) btn.classList.add('active');

    root.querySelectorAll('.qr-type-content').forEach(c => c.classList.add('hidden'));
    const content = document.getElementById(`${prefix}qrTypeContent_${type}`);
    if (content) content.classList.remove('hidden');

    const extraConfig = document.getElementById(`${prefix}qrExtraConfig`);
    if (extraConfig) {
        if (type === 'link') {
            extraConfig.classList.remove('hidden');
        } else {
            extraConfig.classList.add('hidden');
            const subSelect = document.getElementById(`${prefix}qrSubdomainSelect`);
            if (subSelect) subSelect.value = 'ROOT';
            const pathEl = document.getElementById(`${prefix}qrNewPath`);
            if (pathEl && !pathEl.value) generateQRPath(prefix);
        }
    }

    if (prefix === '') updateQRPreview();
}

function qrBuilderSelectSocial(platform, el, prefix = '') {
    const root = document.querySelector(`[data-qr-prefix="${prefix}"]`) || document;
    const hidden = document.getElementById(`${prefix}qrSocialPlatform`);
    if (hidden) hidden.value = platform;

    root.querySelectorAll('.social-item').forEach(item => item.classList.remove('active'));
    if (el) el.classList.add('active');

    const lbl = document.getElementById(`${prefix}lblSocialInput`);
    const input = document.getElementById(`${prefix}qrSocialInput`);
    if (lbl && input) {
        const cfg = qrBuilderSocialLabel(platform);
        lbl.innerText = cfg.label;
        input.placeholder = cfg.placeholder;
    }

    if (prefix === '') updateQRPreview();
}

/**
 * Lê o estado atual do builder e devolve o destino estruturado.
 * @returns {{type:string, metadata:object, data:string}}
 */
function qrBuilderReadState(prefix = '') {
    const typeEl = document.getElementById(`${prefix}qrNewType`);
    if (!typeEl) return { type: 'link', metadata: {}, data: '' };

    const type = typeEl.value || 'link';
    const metadata = {};
    let data = '';

    const val = (id) => {
        const el = document.getElementById(`${prefix}${id}`);
        return el ? (el.value || '').trim() : '';
    };

    switch (type) {
        case 'social': {
            const platform = val('qrSocialPlatform') || 'whatsapp';
            const v = val('qrSocialInput');
            metadata.platform = platform;
            metadata.value = v;
            if (v) {
                if (platform === 'whatsapp') data = `https://wa.me/${v.replace(/\D/g, '')}`;
                else if (platform === 'instagram') data = `https://instagram.com/${v.replace('@', '')}`;
                else if (platform === 'facebook') data = `https://facebook.com/${v}`;
                else if (platform === 'linkedin') data = `https://linkedin.com/in/${v}`;
                else if (platform === 'tiktok') data = `https://tiktok.com/@${v.replace('@', '')}`;
            }
            break;
        }
        case 'vcard': {
            const name = val('qrVcardName');
            const phone = val('qrVcardPhone');
            const email = val('qrVcardEmail');
            const org = val('qrVcardOrg');
            metadata.name = name;
            metadata.phone = phone;
            metadata.email = email;
            metadata.org = org;
            if (name || phone) {
                data = `BEGIN:VCARD\nVERSION:3.0\nFN:${name}\nTEL:${phone}\nEMAIL:${email}\nORG:${org}\nEND:VCARD`;
            }
            break;
        }
        case 'phone': {
            const p = val('qrPhoneInput');
            metadata.phone = p;
            if (p) data = `tel:${p.replace(/\s/g, '')}`;
            break;
        }
        case 'email': {
            const to = val('qrEmailTo');
            const sub = val('qrEmailSubject');
            const body = val('qrEmailBody');
            metadata.to = to;
            metadata.subject = sub;
            metadata.body = body;
            if (to) data = `mailto:${to}?subject=${encodeURIComponent(sub)}&body=${encodeURIComponent(body)}`;
            break;
        }
        default: {
            const raw = val('qrNewTarget');
            metadata.url = raw;
            if (raw) {
                data = raw;
                if (!/^[a-z][a-z0-9+\-.]*:\/\//i.test(data)) data = 'https://' + data;
            }
        }
    }
    return { type, metadata, data };
}

/**
 * Gera o HTML do builder de QR com IDs prefixados. Reutilizado no modal de edição.
 * @param {string} prefix  prefixo único por instância (ex: "ed123_").
 * @param {object} initial dados iniciais: { type, metadata }.
 */
function qrBuilderMarkup(prefix, initial) {
    const esc = (s) => String(s == null ? '' : s)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#39;');

    const data = (initial && initial.metadata) || {};
    const type = (initial && initial.type) || 'link';
    const platform = QR_SOCIAL_PLATFORMS.includes(data.platform) ? data.platform : 'whatsapp';
    const socialCfg = qrBuilderSocialLabel(platform);
    const isActive = (t) => (t === type ? 'active' : '');
    const isHidden = (t) => (t === type ? '' : 'hidden');

    return `
    <div class="qr-builder-root" data-qr-prefix="${prefix}">
        <div class="qr-type-tabs">
            <button type="button" onclick="qrBuilderSwitchType('link', '${prefix}')" id="${prefix}btnTypeLink" class="${isActive('link')}"><i class="fas fa-link"></i> Link</button>
            <button type="button" onclick="qrBuilderSwitchType('social', '${prefix}')" id="${prefix}btnTypeSocial" class="${isActive('social')}"><i class="fas fa-share-alt"></i> Redes Sociais</button>
            <button type="button" onclick="qrBuilderSwitchType('vcard', '${prefix}')" id="${prefix}btnTypeVcard" class="${isActive('vcard')}"><i class="fas fa-address-card"></i> VCard</button>
            <button type="button" onclick="qrBuilderSwitchType('phone', '${prefix}')" id="${prefix}btnTypePhone" class="${isActive('phone')}"><i class="fas fa-phone"></i> Telefone</button>
            <button type="button" onclick="qrBuilderSwitchType('email', '${prefix}')" id="${prefix}btnTypeEmail" class="${isActive('email')}"><i class="fas fa-envelope"></i> E-mail</button>
        </div>
        <input type="hidden" id="${prefix}qrNewType" value="${esc(type)}">

        <div id="${prefix}qrTypeContent_link" class="qr-type-content ${isHidden('link')}">
            <div class="form-group">
                <label>URL de Destino</label>
                <input type="url" id="${prefix}qrNewTarget" placeholder="https://exemplo.com/seu-produto" value="${esc(data.url || '')}">
            </div>
        </div>

        <div id="${prefix}qrTypeContent_social" class="qr-type-content ${isHidden('social')}">
            <div class="social-grid">
                ${QR_SOCIAL_PLATFORMS.map(p => `
                    <div class="social-item ${p === platform ? 'active' : ''}" onclick="qrBuilderSelectSocial('${p}', this, '${prefix}')">
                        <i class="fab fa-${p}"></i> ${p.charAt(0).toUpperCase() + p.slice(1)}
                    </div>
                `).join('')}
            </div>
            <input type="hidden" id="${prefix}qrSocialPlatform" value="${esc(platform)}">
            <div class="form-group">
                <label id="${prefix}lblSocialInput">${esc(socialCfg.label)}</label>
                <input type="text" id="${prefix}qrSocialInput" placeholder="${esc(socialCfg.placeholder)}" value="${esc(data.value || '')}">
            </div>
        </div>

        <div id="${prefix}qrTypeContent_vcard" class="qr-type-content ${isHidden('vcard')}">
            <div class="vcard-grid">
                <div class="form-group"><label>Nome</label><input type="text" id="${prefix}qrVcardName" value="${esc(data.name || '')}" placeholder="João Silva"></div>
                <div class="form-group"><label>Telefone</label><input type="text" id="${prefix}qrVcardPhone" value="${esc(data.phone || '')}" placeholder="+5511999999999"></div>
                <div class="form-group"><label>E-mail</label><input type="email" id="${prefix}qrVcardEmail" value="${esc(data.email || '')}" placeholder="joao@exemplo.com"></div>
                <div class="form-group"><label>Empresa</label><input type="text" id="${prefix}qrVcardOrg" value="${esc(data.org || '')}" placeholder="Minha Empresa"></div>
            </div>
        </div>

        <div id="${prefix}qrTypeContent_phone" class="qr-type-content ${isHidden('phone')}">
            <div class="form-group">
                <label>Número de Telefone</label>
                <input type="tel" id="${prefix}qrPhoneInput" value="${esc(data.phone || '')}" placeholder="+5511999999999">
            </div>
        </div>

        <div id="${prefix}qrTypeContent_email" class="qr-type-content ${isHidden('email')}">
            <div class="form-group"><label>E-mail de Destino</label><input type="email" id="${prefix}qrEmailTo" value="${esc(data.to || '')}" placeholder="exemplo@mail.com"></div>
            <div class="form-group"><label>Assunto</label><input type="text" id="${prefix}qrEmailSubject" value="${esc(data.subject || '')}" placeholder="Contato via QR Code"></div>
            <div class="form-group"><label>Mensagem</label><textarea id="${prefix}qrEmailBody" style="width:100%; height:80px; background:var(--page-surface-soft); border:1px solid var(--border); border-radius:10px; padding:15px; color:var(--text); outline:none; font-family:inherit; resize:none;">${esc(data.body || '')}</textarea></div>
        </div>
    </div>
    `;
}

// --- Wrappers compatíveis com o markup estático do index.html (sem prefix) ---
function switchQRType(type) { qrBuilderSwitchType(type, ''); }
function selectSocial(platform, el) { qrBuilderSelectSocial(platform, el, ''); }
function getQRDataByType() { return qrBuilderReadState('').data; }

function generateQRPath(prefix = '') {
    const chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
    let res = 'qr-';
    for (let i = 0; i < 5; i++) res += chars.charAt(Math.floor(Math.random() * chars.length));
    const el = document.getElementById(`${prefix}qrNewPath`);
    if (el) el.value = res;
}

async function saveQRLink() {
    if (!isLoggedIn) return openModal('modalLogin');
    if (!guardTeamEditPermission()) return;

    const state = qrBuilderReadState('');
    const target = state.data;
    const path = document.getElementById('qrNewPath').value.trim();
    const btn = document.getElementById('btnSaveQR');

    if (!target || !path) {
        showStatus('qr', 'Preencha as informações necessárias do QR Code.', 'error');
        return;
    }


    btn.disabled = true;
    btn.innerHTML = 'Salvando... <i class="fas fa-spinner fa-spin"></i>';

    const subSelect = document.getElementById('qrSubdomainSelect');
    const sub = subSelect ? subSelect.value : 'ROOT';

    const fd = new FormData();
    fd.append('url', target);
    fd.append('subdomain', sub);
    fd.append('path', path);
    fd.append('type', 'qr');
    fd.append('destination_type', state.type);
    fd.append('destination_metadata', JSON.stringify(state.metadata));

    try {
        const res = await fetch('api.php', { method: 'POST', body: fd, credentials: 'include' });
        const data = await res.json();
        if (data.success) {
            await loadQRLinks();
            clearQRCreationFields();
            showStatus('qr', 'QR Code salvo!', 'success');
            loadUsageSummary(); // Atualiza painel de limites
            // Seleciona o novo link
            const hasSub = sub && sub !== 'ROOT';
            const subPrefix = hasSub ? sub + '.' : '';
            const fullUrl = `${protocol}//${subPrefix}${domain}/${path}`;
            document.getElementById('qrLinkSelect').value = fullUrl + '?src=qr';
            updateQRPreview();
            openCreatedQRModal(fullUrl);
        } else {
            console.error(data.message);
            showStatus('qr', data.message, 'error');
            if (data.error === 'quota_exceeded' || data.error === 'feature_locked') {
                openPlanRestrictionModal({
                    feature: 'custom_domain',
                    title: data.error === 'quota_exceeded' ? 'Limite do plano atingido' : 'Recurso bloqueado no seu plano',
                    message: data.message || 'Seu plano atual não permite continuar esta ação.',
                    ctaText: 'Ver planos'
                });
            }
        }
    } catch (e) {
        console.error("Erro ao salvar QR.");
        showStatus('qr', 'Erro de conexão.', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = 'Salvar QR Code <i class="fas fa-save"></i>';
    }
}

function clearQRCreationFields() {
    const qrFieldIds = [
        'qrNewTarget',
        'qrSocialInput',
        'qrVcardName',
        'qrVcardPhone',
        'qrVcardEmail',
        'qrVcardOrg',
        'qrPhoneInput',
        'qrEmailTo',
        'qrEmailSubject',
        'qrEmailBody'
    ];

    qrFieldIds.forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = '';
    });

    const qrSubdomainSelect = document.getElementById('qrSubdomainSelect');
    if (qrSubdomainSelect) qrSubdomainSelect.value = 'ROOT';

    const logoUrl = document.getElementById('qrLogoUrl');
    if (logoUrl) logoUrl.value = '';
    const logoFile = document.getElementById('qrLogoFile');
    if (logoFile) logoFile.value = '';
    const logoFileName = document.getElementById('logoFileName');
    if (logoFileName) logoFileName.innerText = 'Nenhum arquivo...';

    generateQRPath();
    updateQRPreview();
}

function getCurrentQRStyleOptions(url) {
    const dotsColorEl = document.getElementById('qrColor');
    const bgColorEl = document.getElementById('qrBgColor');
    const moduleTypeEl = document.getElementById('qrModuleStyle');
    const cornerTypeEl = document.getElementById('qrCornerStyle');
    const logoAreaEl = document.getElementById('qrLogoUrl');
    const normalizedLogoUrl = normalizeQRLogoUrl(logoAreaEl ? (logoAreaEl.value || '') : '');
    return {
        width: 230,
        height: 230,
        type: "svg",
        data: url,
        image: normalizedLogoUrl,
        dotsOptions: { color: dotsColorEl ? dotsColorEl.value : '#000000', type: moduleTypeEl ? moduleTypeEl.value : 'square' },
        backgroundOptions: { color: bgColorEl ? bgColorEl.value : '#ffffff' },
        cornersSquareOptions: { type: cornerTypeEl ? cornerTypeEl.value : 'square', color: dotsColorEl ? dotsColorEl.value : '#000000' },
        cornersDotOptions: { type: cornerTypeEl ? cornerTypeEl.value : 'square', color: dotsColorEl ? dotsColorEl.value : '#000000' },
        imageOptions: { crossOrigin: "anonymous", margin: 5, saveAsBlob: true }
    };
}

function openCreatedQRModal(url) {
    lastCreatedQRUrl = url || '';
    const urlEl = document.getElementById('createdQrUrl');
    const canvasEl = document.getElementById('qrCreatedCanvas');
    if (!urlEl || !canvasEl || !lastCreatedQRUrl) return;

    urlEl.textContent = lastCreatedQRUrl;
    canvasEl.innerHTML = '';

    const options = getCurrentQRStyleOptions(lastCreatedQRUrl);
    qrCreatedModalCode = new QRCodeStyling(options);
    qrCreatedModalCode.append(canvasEl);
    renderCreatedQRPreviewImage(canvasEl);

    openModal('modalQrCreated');
}

async function renderCreatedQRPreviewImage(containerEl) {
    if (!qrCreatedModalCode || !containerEl) return;
    try {
        const rawData = await qrCreatedModalCode.getRawData("png");
        if (!rawData) return;
        if (createdQRPreviewObjectUrl) {
            URL.revokeObjectURL(createdQRPreviewObjectUrl);
        }
        createdQRPreviewObjectUrl = URL.createObjectURL(rawData);
        containerEl.innerHTML = `<img src="${createdQRPreviewObjectUrl}" alt="QR Code gerado" class="qr-created-preview-img">`;
    } catch (e) {
        // Fallback: mantém o canvas/svg já anexado pelo QRCodeStyling
    }
}

function shareCreatedQR() {
    if (!lastCreatedQRUrl) return;
    if (navigator.share) {
        navigator.share({ title: 'LinkNeo.pro - QR Code', url: lastCreatedQRUrl }).catch(() => { });
        return;
    }
    navigator.clipboard.writeText(lastCreatedQRUrl).then(() => {
        showStatus('qr', 'Link do QR copiado para a área de transferência.', 'success');
    }).catch(() => { });
}

function downloadCreatedQR(ext) {
    if (!qrCreatedModalCode) return;
    qrCreatedModalCode.download({ name: "qrcode-linkneo", extension: ext });
}

function goToMyQRsFromCreatedModal() {
    closeModals();
    showSection('section-myqrs');
}


async function loadQRLinks() {
    if (!isLoggedIn) return;
    try {
        const res = await fetch('manage_links.php?action=list&type=all', { credentials: 'include' });
        const data = await res.json();
        if (!data.success) return;

        const select = document.getElementById('qrLinkSelect');
        const container = document.getElementById('myQRsCards');

        if (select) select.innerHTML = '<option value="">--- Selecione um Link ---</option>';
        if (container) container.innerHTML = '';
        const allItems = data.links || [];
        myLinksData = allItems;

        if (allItems.length === 0) {
            if (select) select.innerHTML = '<option value="">Crie um link primeiro!</option>';
            if (container) container.innerHTML = '<div style="grid-column: 1/-1; text-align:center; padding:60px; color:#94a3b8; background:var(--card); border-radius:20px;">Nenhum QR Code gerado ainda.</div>';
            return;
        }

        allItems.forEach((link, idx) => {
            const qrUrl = getFullShortUrl(link) + '?src=qr';
            if (select) {
                const option = document.createElement('option');
                option.value = `${qrUrl}|${link.id}`;
                option.text = `[/${link.path}] ${link.title || 'Sem título'}`;
                select.appendChild(option);
            }
            if (idx === 0) setTimeout(updateQRPreview, 100);
        });

        if (container) {
            const qrItems = allItems.filter(item => item.type === 'qr');
            const tagCounter = new Map();
            qrItems.forEach(item => {
                (item.tags || []).forEach(tag => {
                    const key = String(tag.id);
                    if (!tagCounter.has(key)) {
                        tagCounter.set(key, { id: tag.id, name: tag.name, usage_count: 0 });
                    }
                    tagCounter.get(key).usage_count += 1;
                });
            });
            linksAvailableTagsByType.qr = Array.from(tagCounter.values()).sort((a, b) => String(a.name).localeCompare(String(b.name)));
            populateTagFilters('qr', linksAvailableTagsByType.qr);
            linksFoldersByType.qr = Array.isArray(data.folders) ? data.folders.filter(folder => folder.type === 'qr') : [];

            const filters = readListFiltersFromUI('qr');
            const filteredQrItems = qrItems.filter(item => itemMatchesFilters(item, filters));
            if (filteredQrItems.length === 0) {
                container.innerHTML = '<div style="grid-column: 1/-1; text-align:center; padding:60px; color:#94a3b8; background:var(--card); border-radius:20px;">Nenhum QR Code encontrado com os filtros atuais.</div>';
            } else {
                renderFolderedItems(container, 'qr', filteredQrItems, linksFoldersByType.qr, renderQrCardItem);
            }
        }

        applyFeatureLocks();
    } catch (e) { console.error("Erro no loadQRLinks", e); }
}

function focusQR(url) {
    showSection('section-home');
    switchHomeTab('qrcodes');
    toggleQRMode('select');

    // Adiciona o parâmetro se não tiver
    if (!url.includes('?src=qr') && !url.includes('&src=qr')) {
        url += (url.includes('?') ? '&' : '?') + 'src=qr';
    }

    // Pequeno atraso para garantir que a aba carregou os links no seletor
    setTimeout(() => {
        const select = document.getElementById('qrLinkSelect');
        if (select) {
            select.value = url;
            updateQRPreview();
        }
    }, 500);

    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function goToAnalytics(id) {
    document.getElementById('analyticsFilter').value = id;
    showSection('section-analytics');
    loadAnalytics();
}

function updateQRPreview() {
    let url = "";

    if (currentQRMode === 'select') {
        const select = document.getElementById('qrLinkSelect');
        if (select && select.value && select.value !== "Carregando meus links...") {
            url = select.value.split('|')[0]; // Pega apenas a URL
        }
    } else {
        const subSelect = document.getElementById('qrSubdomainSelect');
        const sub = subSelect ? subSelect.value : 'ROOT';
        const path = document.getElementById('qrNewPath')?.value.trim() || "";

        url = getQRDataByType();

        // Se após tentar pegar o dado específico ele ainda for nulo, tenta o fallback do path
        if (!url && path) {
            const hasSub = sub && sub !== 'ROOT';
            const subPrefix = hasSub ? sub + '.' : '';
            url = `${protocol}//${subPrefix}${domain}/${path}`;
        }
    }

    // Se ainda estiver vazio (sem link selecionado ou sem slug digitado), usa o genérico para preencher a tela
    if (!url) url = `${protocol}//${domain}/?utm_source=qr_preview`;

    const dotsColor = document.getElementById('qrColor').value;
    const bgColor = document.getElementById('qrBgColor').value;
    const moduleType = document.getElementById('qrModuleStyle').value;
    const cornerType = document.getElementById('qrCornerStyle').value;
    const rawLogoArea = document.getElementById('qrLogoUrl') ? document.getElementById('qrLogoUrl').value.trim() : "";
    const logoArea = normalizeQRLogoUrl(rawLogoArea);

    const options = {
        width: 300, height: 300, type: "svg", data: url, image: logoArea || "",
        dotsOptions: { color: dotsColor, type: moduleType },
        backgroundOptions: { color: bgColor },
        cornersSquareOptions: { type: cornerType, color: dotsColor },
        cornersDotOptions: { type: cornerType, color: dotsColor },
        imageOptions: { crossOrigin: "anonymous", margin: 5, saveAsBlob: true }
    };

    if (!qrCode) {
        qrCode = new QRCodeStyling(options);
        const previewCanvas = document.getElementById("qrPreviewCanvas");
        if (previewCanvas) qrCode.append(previewCanvas);
    } else {
        qrCode.update(options);
    }
}

function normalizeQRLogoUrl(value) {
    const raw = (value || '').trim();
    if (!raw) return '';
    if (/^(data:|blob:|https?:\/\/)/i.test(raw)) return raw;
    try {
        return new URL(raw.replace(/^\/+/, ''), `${window.location.origin}/`).toString();
    } catch (_) {
        return raw;
    }
}

function downloadQR(ext) {
    if (!qrCode) return;
    qrCode.download({ name: "qrcode-linkneo", extension: ext });
}

function downloadQRFromList(url, slug) {
    const tempQr = new QRCodeStyling({
        width: 1000,
        height: 1000,
        data: url,
        dotsOptions: { color: "#000", type: "square" },
        backgroundOptions: { color: "#fff" },
        imageOptions: { crossOrigin: "anonymous", margin: 20 }
    });
    tempQr.download({ name: `qr-${slug}`, extension: "png" });
}

async function handleLogoUpload(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        if (file.size > 512 * 1024) { console.error("Máximo 500KB."); return; }
        document.getElementById('logoFileName').innerText = "Enviando...";
        const fd = new FormData();
        fd.append('logo', file);
        try {
            const res = await fetch('upload_logo.php', { method: 'POST', body: fd, credentials: 'include' });
            const data = await res.json();
            if (data.success) {
                document.getElementById('qrLogoUrl').value = normalizeQRLogoUrl(data.path || '');
                document.getElementById('logoFileName').innerText = file.name;
                updateQRPreview();
            } else {
                document.getElementById('logoFileName').innerText = 'Falha no upload';
            }
        } catch (e) { console.error("Erro no upload."); }
    }
}

async function saveExistingAsQR() {
    const select = document.getElementById('qrLinkSelect');
    if (!select || !select.value || select.value.includes('---')) {
        return showStatus('qr', 'Selecione um link válido.', 'error');
    }

    const parts = select.value.split('|');
    const linkId = parts[1];
    const btn = document.getElementById('btnSaveExisting');

    if (!linkId) return;

    btn.disabled = true;
    btn.innerHTML = 'Salvando... <i class="fas fa-spinner fa-spin"></i>';

    const fd = new FormData();
    fd.append('action', 'update_type');
    fd.append('id', linkId);
    fd.append('type', 'qr');

    try {
        const res = await fetch('manage_links.php', { method: 'POST', body: fd, credentials: 'include' });
        const data = await res.json();

        if (data.success) {
            if (typeof loadMyLinks === 'function') await loadMyLinks();
            showStatus('qr', 'Link agora é um QR Code Dinâmico!', 'success');
            await loadQRLinks();
        } else {
            showStatus('qr', data.message, 'error');
        }
    } catch (e) {
        showStatus('qr', 'Erro ao salvar alteração.', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = 'Salvar como QR Code <i class="fas fa-save"></i>';
    }
}

// --- MÓDULO DE CAMPANHAS ---
function getCampaignNameFromUrl(url) {
    try {
        const parsed = new URL(url);
        return parsed.searchParams.get('utm_campaign') || '';
    } catch (err) {
        return '';
    }
}

function extractUtmParamsFromUrl(url) {
    try {
        const parsed = new URL(url);
        return {
            utm_source: parsed.searchParams.get('utm_source') || '',
            utm_medium: parsed.searchParams.get('utm_medium') || '',
            utm_campaign: parsed.searchParams.get('utm_campaign') || '',
            utm_term: parsed.searchParams.get('utm_term') || '',
            utm_content: parsed.searchParams.get('utm_content') || ''
        };
    } catch (err) {
        return { utm_source: '', utm_medium: '', utm_campaign: '', utm_term: '', utm_content: '' };
    }
}

function normalizeCampaignKey(name) {
    return (name || '').trim().toLowerCase();
}

function escapeCampaignHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function updateCampaignSelectionHighlight(campaignName) {
    const panel = document.getElementById('campaignSelectionHighlight');
    const title = document.getElementById('campaignSelectionHighlightName');
    if (!panel || !title) return;
    const name = (campaignName || '').trim();
    panel.classList.toggle('hidden', !name);
    title.innerHTML = name
        ? `<i class="fas fa-bullhorn"></i>${name}`
        : '<i class="fas fa-bullhorn"></i>Nenhuma campanha selecionada';
}

function ensureCreateCampaignModal() {
    if (document.getElementById('modalCreateCampaign')) return;
    const overlay = document.getElementById('modalOverlay');
    if (!overlay) return;
    const div = document.createElement('div');
    div.id = 'modalCreateCampaign';
    div.className = 'modal-content hidden';
    div.innerHTML = `
        <div class="modal-header">
            <h2>Nova campanha</h2>
            <button type="button" onclick="closeModals()" class="close-btn"><i class="fas fa-times"></i></button>
        </div>
        <form onsubmit="submitCreateCampaignModal(event)">
            <div class="form-group" style="margin-bottom: 16px;">
                <label for="createCampaignModalName">Nome da campanha</label>
                <input id="createCampaignModalName" type="text" placeholder="Ex: Black Friday 2026" required
                    style="width: 100%; background: var(--page-surface-soft); color: var(--text); border: 1px solid var(--border); padding: 12px; border-radius: 10px; outline: none;">
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="btn-text" onclick="closeModals()">Cancelar</button>
                <button type="submit" class="btn-primary"><i class="fas fa-check"></i> Criar campanha</button>
            </div>
        </form>
    `;
    overlay.appendChild(div);
}

function openCreateCampaignModal() {
    ensureCreateCampaignModal();
    openModal('modalCreateCampaign');
    setTimeout(() => {
        const input = document.getElementById('createCampaignModalName');
        if (input) {
            input.value = '';
            input.focus();
        }
    }, 0);
}

async function submitCreateCampaignModal(event) {
    if (event) event.preventDefault();
    const input = document.getElementById('createCampaignModalName');
    const name = (input?.value || '').trim();
    if (!name) {
        showStatus('campaign', 'Informe o nome da campanha para criar.', 'error');
        return;
    }
    const created = await createNewCampaign(name);
    if (created) {
        closeModals();
    }
}

async function loadCampaigns() {
    if (!isLoggedIn) return;
    if (isFeatureLocked('bulk_utm')) {
        renderCampaignsDemoData();
        return;
    }

    try {
        const resCamp = await fetch('analytics.php?action=get_campaigns', { credentials: 'include' });
        const dataCamp = await resCamp.json();

        // 1. Popula o Dropdown
        const campSelect = document.getElementById('campaignSelect');
        if (campSelect) {
            const currentVal = campSelect.value;
            campSelect.innerHTML = '<option value="">Selecione uma Campanha</option>';
            if (dataCamp.success && dataCamp.campaigns) {
                dataCamp.campaigns.forEach(c => {
                    const opt = document.createElement('option');
                    opt.value = c.name; opt.innerText = c.name;
                    campSelect.appendChild(opt);
                });
            }
            if (currentVal && Array.from(campSelect.options).some(opt => opt.value === currentVal)) {
                campSelect.value = currentVal;
            } else {
                campSelect.value = '';
            }
        }

        // 2. Renderiza a Lista de Cards
        renderCampaignList(dataCamp.campaigns || []);

        // 3. Carrega lista de links para o formulário e painel de tracking da campanha
        const resLinks = await fetch('manage_links.php?action=list&type=all', { credentials: 'include' });
        const dataLinks = await resLinks.json();
        const linkSelect = document.getElementById('linkToCampaignSelect');
        const trackingSelect = document.getElementById('campaignTrackingLinkSelect');
        if (linkSelect) {
            linkSelect.innerHTML = '<option value="">Selecione o Link</option>';
            if (dataLinks.success && dataLinks.links) {
                dataLinks.links.forEach(l => {
                    const opt = document.createElement('option');
                    opt.value = l.id;
                    opt.innerText = `/${l.path} (${l.subdomain})`;
                    linkSelect.appendChild(opt);
                });
            }
        }
        campaignTrackingLinksCache = (dataLinks.success && dataLinks.links ? dataLinks.links : []).map(l => ({
            id: String(l.id),
            label: `/${l.path} (${l.subdomain})`,
            campaignName: (l.campaign_name || getCampaignNameFromUrl(l.original_url || '') || '').trim(),
            trackingEnabled: parseInt(l.tracking_enabled || 0, 10) === 1 ? 1 : 0
        }));
        campaignLinksDetailsCache = (dataLinks.success && dataLinks.links ? dataLinks.links : []).map(l => {
            const utm = extractUtmParamsFromUrl(l.original_url || '');
            return {
                id: Number(l.id),
                path: l.path || '',
                subdomain: l.subdomain || '',
                original_url: l.original_url || '',
                tracking_enabled: parseInt(l.tracking_enabled || 0, 10) === 1 ? 1 : 0,
                campaign_name: (l.campaign_name || utm.utm_campaign || '').trim(),
                utm_source: utm.utm_source,
                utm_medium: utm.utm_medium,
                utm_campaign: utm.utm_campaign,
                utm_term: utm.utm_term,
                utm_content: utm.utm_content
            };
        });
        if (trackingSelect) {
            trackingSelect.onchange = updateCampaignTrackingPanelState;
        }
        const selectedCampaign = campSelect ? campSelect.value : '';
        updateCampaignSelectionHighlight(selectedCampaign);
        filterCampaignTrackingLinks(selectedCampaign);
        updateCampaignTrackingPanelState();
        loadCampaignLinksForSelected(selectedCampaign);
    } catch (e) { console.error("Erro ao carregar campanhas", e); }
}

function renderCampaignList(campaigns) {
    const container = document.getElementById('campaignsCardList');
    if (!container) return;
    container.innerHTML = '';

    if (campaigns.length === 0) {
        container.innerHTML = `<div class="campaign-empty-state">Nenhuma campanha ativa encontrada. Crie uma acima.</div>`;
        return;
    }

    const selectedCampaign = (document.getElementById('campaignSelect')?.value || '').trim();
    campaigns.forEach(camp => {
        const card = document.createElement('div');
        card.className = `chart-container campaign-list-card${selectedCampaign === camp.name ? ' is-active' : ''}`;
        card.onclick = () => {
            document.getElementById('campaignSelect').value = camp.name;
            loadCampaignAnalytics(camp.name);
            window.scrollTo({ top: document.getElementById('campaignAnalyticsArea').offsetTop - 100, behavior: 'smooth' });
        };

        card.innerHTML = `
            <div class="campaign-list-card-head">
                <div class="campaign-list-card-icon">
                    <i class="fas fa-bullhorn"></i>
                </div>
                <span class="campaign-list-card-badge">
                    ${camp.link_count} link(s)
                </span>
            </div>
            <h3 class="campaign-list-card-name">${camp.name}</h3>
            <p class="campaign-list-card-desc">Campanha de rastreamento UTM</p>
            <button class="btn-text campaign-list-card-action">
                <i class="fas fa-chart-line"></i> Ver Estatísticas <i class="fas fa-chevron-right" style="font-size: 0.7rem; margin-left: 5px;"></i>
            </button>
        `;
        container.appendChild(card);
    });
}

async function createNewCampaign(nameFromInput = null) {
    const name = (nameFromInput || '').trim();
    if (!name) return;
    if (isFeatureLocked('bulk_utm')) {
        openPlanRestrictionModal({
            feature: 'bulk_utm',
            title: 'Campanhas bloqueadas no seu plano',
            message: 'Seu plano atual não permite criar ou gerenciar campanhas UTM.',
            ctaText: 'Ver planos'
        });
        return false;
    }

    const campSelect = document.getElementById('campaignSelect');
    if (!campSelect) return;

    try {
        const fd = new FormData();
        fd.append('action', 'create_campaign');
        fd.append('name', name);
        const res = await fetch('manage_links.php', { method: 'POST', body: fd, credentials: 'include' });
        const data = await res.json();
        if (!data.success && !data.message?.includes('Estrutura de campanhas')) {
            if (handlePlanRestrictionResponse(data, { feature: 'bulk_utm', title: 'Campanhas bloqueadas no seu plano' })) {
                return false;
            }
            showStatus('campaign', data.message || 'Não foi possível criar a campanha.', 'error');
            return false;
        }
    } catch (err) {
        console.error('Erro ao persistir campanha', err);
        return false;
    }

    let exists = false;
    for (let opt of campSelect.options) {
        if (opt.value === name) { exists = true; break; }
    }
    if (!exists) {
        const opt = document.createElement('option');
        opt.value = name; opt.innerText = name;
        campSelect.appendChild(opt);
    }
    campSelect.value = name;
    const hiddenInput = document.getElementById('camp_utm_campaign');
    if (hiddenInput) hiddenInput.value = name;
    filterCampaignTrackingLinks(name);
    updateCampaignTrackingPanelState();
    loadCampaignAnalytics(name);
    return true;
}

function createNewCampaignFromInput() {
    openCreateCampaignModal();
}

async function applyUtmToLink(e) {
    e.preventDefault();
    if (isFeatureLocked('bulk_utm')) {
        openPlanRestrictionModal({
            feature: 'bulk_utm',
            title: 'Gestão de UTM bloqueada',
            message: 'Seu plano atual não permite aplicar UTMs e campanhas em massa.',
            ctaText: 'Ver planos'
        });
        return;
    }
    const campaign = document.getElementById('camp_utm_campaign').value;
    if (!campaign) {
        showStatus('campaign', 'Selecione ou crie uma campanha primeiro.', 'error');
        return;
    }

    const fd = new FormData();
    fd.append('action', 'apply_utm');
    fd.append('link_id', document.getElementById('linkToCampaignSelect').value);
    fd.append('utm_source', document.getElementById('camp_utm_source').value.trim());
    fd.append('utm_medium', document.getElementById('camp_utm_medium').value.trim());
    fd.append('utm_campaign', campaign);
    fd.append('utm_term', document.getElementById('camp_utm_term').value.trim());
    fd.append('utm_content', document.getElementById('camp_utm_content').value.trim());

    try {
        const res = await fetch('manage_links.php', { method: 'POST', body: fd, credentials: 'include' });
        const data = await res.json();
        if (data.success) {
            showStatus('campaign', 'Tags UTM aplicadas com sucesso!', 'success');
            loadCampaigns(); // Atualiza a lista de cards e o dropdown
            loadCampaignAnalytics(campaign);
            filterCampaignTrackingLinks(campaign);
            loadCampaignLinksForSelected(campaign);
        } else {
            if (handlePlanRestrictionResponse(data, { feature: 'bulk_utm', title: 'Gestão de UTM bloqueada' })) {
                return;
            }
            showStatus('campaign', data.message, 'error');
        }
    } catch (e) { console.error("Erro ao aplicar UTM", e); }
}

function renderCampaignLinksList(campaignName, links) {
    const panel = document.getElementById('campaignLinksPanel');
    const subtitle = document.getElementById('campaignLinksPanelSubtitle');
    const list = document.getElementById('campaignLinksList');
    if (!panel || !subtitle || !list) return;

    if (!campaignName) {
        panel.classList.add('hidden');
        subtitle.innerText = 'Selecione uma campanha para visualizar os links vinculados.';
        list.innerHTML = '';
        return;
    }

    panel.classList.remove('hidden');
    subtitle.innerText = `Gerencie os links vinculados à campanha "${campaignName}".`;
    panel.classList.toggle('campaign-links-panel-compact', campaignLinksCompactMode);

    const normalizedFilter = normalizeCampaignKey(campaignLinksFilterText);
    const sourceLinks = Array.isArray(links) ? links : [];
    const filteredByText = !normalizedFilter
        ? sourceLinks
        : sourceLinks.filter(link => {
            const searchable = [
                link.path,
                link.subdomain,
                link.original_url,
                link.utm_source,
                link.utm_medium,
                link.utm_campaign,
                link.utm_term,
                link.utm_content
            ].map(v => normalizeCampaignKey(v || '')).join(' ');
            return searchable.includes(normalizedFilter);
        });

    if (filteredByText.length === 0) {
        list.innerHTML = '<div class="campaign-empty-state">Nenhum link vinculado a esta campanha.</div>';
        return;
    }

    list.innerHTML = filteredByText.map(link => {
        const id = Number(link.id);
        const trackingEnabled = parseInt(link.tracking_enabled || 0, 10) === 1;
        return `
            <article class="campaign-link-item">
                <div class="campaign-link-top">
                    <div>
                        <h4 class="campaign-link-title">/${escapeCampaignHtml(link.path)} (${escapeCampaignHtml(link.subdomain)})</h4>
                        <p class="campaign-link-url">${escapeCampaignHtml(link.original_url || '')}</p>
                    </div>
                    <span class="campaign-link-status ${trackingEnabled ? 'is-active' : 'is-off'}">
                        ${parseInt(link.tracking_enabled || 0, 10) === 1 ? 'Tracking ativo' : 'Tracking inativo'}
                    </span>
                </div>
                <div class="campaign-utm-grid">
                    <div><label class="campaign-field-label">utm_source</label><input id="campaignLinkUtmSource-${id}" class="campaign-field campaign-utm-input" value="${escapeCampaignHtml(link.utm_source || '')}"></div>
                    <div><label class="campaign-field-label">utm_medium</label><input id="campaignLinkUtmMedium-${id}" class="campaign-field campaign-utm-input" value="${escapeCampaignHtml(link.utm_medium || '')}"></div>
                    <div><label class="campaign-field-label">utm_campaign</label><input id="campaignLinkUtmCampaign-${id}" class="campaign-field campaign-utm-input campaign-utm-readonly" value="${escapeCampaignHtml(link.utm_campaign || campaignName || '')}" readonly></div>
                    <div><label class="campaign-field-label">utm_term</label><input id="campaignLinkUtmTerm-${id}" class="campaign-field campaign-utm-input" value="${escapeCampaignHtml(link.utm_term || '')}"></div>
                    <div><label class="campaign-field-label">utm_content</label><input id="campaignLinkUtmContent-${id}" class="campaign-field campaign-utm-input" value="${escapeCampaignHtml(link.utm_content || '')}"></div>
                </div>
                <div class="campaign-link-actions">
                    <button type="button" class="btn-text campaign-action-btn" onclick="toggleCampaignLinkActions(${id})">
                        <i class="fas fa-bullseye"></i> Configurar tracking interno
                    </button>
                    <button type="button" class="btn-text campaign-action-btn danger" onclick="removeLinkFromCampaign(${id})">
                        <i class="fas fa-unlink"></i> Remover da campanha
                    </button>
                    <button type="button" class="btn-primary campaign-save-btn" onclick="saveCampaignLinkUtm(${id})">
                        <i class="fas fa-save"></i> Salvar UTM
                    </button>
                </div>
                ${trackingEnabled ? `
                    <div style="margin-top:8px; background:rgba(15,23,42,0.45); border:1px solid rgba(148,163,184,0.18); border-radius:10px; padding:10px;">
                        <div style="display:flex; justify-content:space-between; align-items:center; gap:8px; margin-bottom:8px;">
                            <strong style="font-size:0.8rem; color:#c7d2fe;"><i class="fas fa-chart-column"></i> Resultado do tracking interno (este link)</strong>
                            <span id="campaignPixelSummary-${id}" style="font-size:0.72rem; color:var(--text-dim);">Carregando...</span>
                        </div>
                        <div style="height:200px;">
                            <canvas id="campaignPixelChart-${id}"></canvas>
                        </div>
                    </div>
                ` : `
                    <div style="margin-top:8px; font-size:0.75rem; color:var(--text-dim); background:rgba(100,116,139,0.08); border:1px dashed rgba(148,163,184,0.2); border-radius:10px; padding:10px;">
                        Ative o tracking para exibir o gráfico de conversões internas diretamente nesta lista.
                    </div>
                `}
                <div id="campaignLinkActionsWrap-${id}" class="hidden campaign-pixels-panel">
                    <p class="campaign-pixels-steps">
                        1) Ative o tracking interno deste link. 2) Cadastre eventos. 3) Copie os snippets para a página de destino.
                    </p>
                    <div style="background:rgba(59,130,246,0.08); border:1px solid rgba(96,165,250,0.28); border-radius:10px; padding:10px; font-size:0.78rem; color:#dbeafe; margin-bottom:10px; line-height:1.45;">
                        <i class="fas fa-circle-info"></i> Dica importante: as conversões só aparecem quando o visitante chega pela URL encurtada (com <code>ep_cid</code>) e depois executa o evento na página final.
                    </div>
                    <div class="campaign-pixels-actions">
                        <button type="button" class="btn-primary campaign-pixels-toggle-btn" onclick="toggleCampaignTrackingForLink(${id})" id="campaignLinkTrackingToggleBtn-${id}">
                            ${trackingEnabled ? '<i class="fas fa-toggle-on"></i> Desativar rastreamento' : '<i class="fas fa-power-off"></i> Ativar rastreamento'}
                        </button>
                        <button type="button" class="btn-text campaign-action-btn" onclick="openTrackingActionModal(${id})">
                            <i class="fas fa-plus"></i> Novo evento
                        </button>
                    </div>
                    ${trackingEnabled ? `
                        <div class="campaign-pixels-grid">
                            <div class="campaign-pixels-block">
                                <h5 class="campaign-pixels-block-title"><i class="fas fa-boxes"></i> Eventos cadastrados</h5>
                                <div id="trackingActionsList-${id}" class="campaign-tracking-actions-list">
                                    <div class="campaign-loading">Carregando eventos... <i class="fas fa-spinner fa-spin"></i></div>
                                </div>
                            </div>
                            <div class="campaign-pixels-block">
                                <h5 class="campaign-pixels-block-title script"><i class="fas fa-code"></i> Script de instalação</h5>
                                <p class="campaign-pixels-note">Cole no <code>&lt;head&gt;</code> da página de destino:</p>
                                <textarea readonly class="campaign-script-box"><script src="${new URL('tracker.js', window.location.href).toString()}" defer></script></textarea>
                                <p class="campaign-pixels-note">Use o botão <i class="fas fa-code"></i> nos eventos para copiar o disparo.</p>
                            </div>
                        </div>
                    ` : `
                        <div class="campaign-pixels-warning">
                            O rastreamento está desligado para este link. Ative para liberar eventos e snippets.
                        </div>
                    `}
                </div>
            </article>
        `;
    }).join('');

    filteredByText
        .filter(link => parseInt(link.tracking_enabled || 0, 10) === 1)
        .forEach(link => {
            loadCampaignInlinePixelChart(Number(link.id));
        });

    if (campaignOpenPixelLinkId !== null) {
        const wrap = document.getElementById(`campaignLinkActionsWrap-${campaignOpenPixelLinkId}`);
        if (wrap) {
            wrap.classList.remove('hidden');
            const target = campaignLinksDetailsCache.find(link => Number(link.id) === Number(campaignOpenPixelLinkId));
            if (target && parseInt(target.tracking_enabled || 0, 10) === 1) {
                loadTrackingActions(Number(campaignOpenPixelLinkId));
            }
        } else {
            campaignOpenPixelLinkId = null;
        }
    }
}

async function loadCampaignInlinePixelChart(linkId) {
    const chartId = `campaignPixelChart-${linkId}`;
    const summaryEl = document.getElementById(`campaignPixelSummary-${linkId}`);
    const canvas = document.getElementById(chartId);
    if (!canvas) return;

    try {
        const res = await fetch(`manage_links.php?action=get_actions&link_id=${linkId}`, { credentials: 'include' });
        const data = await res.json();
        if (!data.success || !Array.isArray(data.actions) || data.actions.length === 0) {
            if (summaryEl) summaryEl.innerText = 'Sem dados de conversão';
            renderChart(chartId, 'bar', ['Sem dados'], [0], {
                backgroundColor: '#64748b',
                borderColor: '#64748b'
            });
            return;
        }

        const labels = data.actions.map(action => action.name || 'Evento');
        const values = data.actions.map(action => Number(action.total_conversions || 0));
        const total = values.reduce((acc, cur) => acc + cur, 0);
        if (summaryEl) {
            summaryEl.innerText = `${total.toLocaleString('pt-BR')} convers${total === 1 ? 'ão' : 'ões'}`;
        }

        renderChart(chartId, 'bar', labels, values, {
            backgroundColor: '#1A8CFF',
            borderColor: getBrandTokens().primary
        });
    } catch (err) {
        if (summaryEl) summaryEl.innerText = 'Falha ao carregar';
        renderChart(chartId, 'bar', ['Erro'], [0], {
            backgroundColor: '#ef4444',
            borderColor: '#ef4444'
        });
    }
}

async function loadCampaignLinksForSelected(campaignName) {
    const normalized = (campaignName || '').trim();
    if (!normalized) {
        renderCampaignLinksList('', []);
        return;
    }
    const normalizedKey = normalizeCampaignKey(normalized);
    const links = campaignLinksDetailsCache.filter(link =>
        normalizeCampaignKey(link.campaign_name || link.utm_campaign || '') === normalizedKey
    );
    renderCampaignLinksList(normalized, links);
}

async function saveCampaignLinkUtm(linkId) {
    if (isFeatureLocked('bulk_utm')) {
        openPlanRestrictionModal({
            feature: 'bulk_utm',
            title: 'Gestão de UTM bloqueada',
            message: 'Seu plano atual não permite editar UTMs desta campanha.',
            ctaText: 'Ver planos'
        });
        return;
    }
    const campaignName = (document.getElementById('campaignSelect')?.value || '').trim();
    if (!campaignName) {
        showStatus('campaign', 'Selecione uma campanha para editar os links.', 'error');
        return;
    }
    const source = document.getElementById(`campaignLinkUtmSource-${linkId}`)?.value?.trim() || '';
    const medium = document.getElementById(`campaignLinkUtmMedium-${linkId}`)?.value?.trim() || '';
    const term = document.getElementById(`campaignLinkUtmTerm-${linkId}`)?.value?.trim() || '';
    const content = document.getElementById(`campaignLinkUtmContent-${linkId}`)?.value?.trim() || '';

    const fd = new FormData();
    fd.append('action', 'update_campaign_link_utm');
    fd.append('link_id', String(linkId));
    fd.append('campaign_name', campaignName);
    fd.append('utm_source', source);
    fd.append('utm_medium', medium);
    fd.append('utm_campaign', campaignName);
    fd.append('utm_term', term);
    fd.append('utm_content', content);

    try {
        const res = await fetch('manage_links.php', { method: 'POST', body: fd, credentials: 'include' });
        const data = await res.json();
        if (!data.success) {
            if (handlePlanRestrictionResponse(data, { feature: 'bulk_utm', title: 'Gestão de UTM bloqueada' })) {
                return;
            }
            showStatus('campaign', data.message || 'Não foi possível salvar as UTM.', 'error');
            return;
        }
        showStatus('campaign', 'Link da campanha atualizado com sucesso.', 'success');
        await loadCampaigns();
        await loadCampaignLinksForSelected(campaignName);
        filterCampaignTrackingLinks(campaignName);
        updateCampaignTrackingPanelState();
    } catch (err) {
        console.error('Erro ao salvar UTM da campanha', err);
        showStatus('campaign', 'Erro ao salvar UTM do link.', 'error');
    }
}

async function removeLinkFromCampaign(linkId) {
    if (isFeatureLocked('bulk_utm')) {
        openPlanRestrictionModal({
            feature: 'bulk_utm',
            title: 'Gestão de campanhas bloqueada',
            message: 'Seu plano atual não permite editar vínculos de campanha.',
            ctaText: 'Ver planos'
        });
        return;
    }
    const campaignName = (document.getElementById('campaignSelect')?.value || '').trim();
    if (!confirm('Deseja remover este link da campanha?')) return;
    const fd = new FormData();
    fd.append('action', 'remove_link_from_campaign');
    fd.append('link_id', String(linkId));

    try {
        const res = await fetch('manage_links.php', { method: 'POST', body: fd, credentials: 'include' });
        const data = await res.json();
        if (!data.success) {
            if (handlePlanRestrictionResponse(data, { feature: 'bulk_utm', title: 'Gestão de campanhas bloqueada' })) {
                return;
            }
            showStatus('campaign', data.message || 'Não foi possível remover o link da campanha.', 'error');
            return;
        }
        showStatus('campaign', 'Link removido da campanha com sucesso.', 'success');
        if (Number(campaignOpenPixelLinkId) === Number(linkId)) {
            campaignOpenPixelLinkId = null;
        }
        await loadCampaigns();
        await loadCampaignLinksForSelected(campaignName);
        filterCampaignTrackingLinks(campaignName);
        updateCampaignTrackingPanelState();
        loadCampaignAnalytics(campaignName);
    } catch (err) {
        console.error('Erro ao remover link da campanha', err);
        showStatus('campaign', 'Erro ao remover o link da campanha.', 'error');
    }
}

function toggleCampaignLinkActions(linkId) {
    const wrap = document.getElementById(`campaignLinkActionsWrap-${linkId}`);
    if (!wrap) return;
    const willOpen = wrap.classList.contains('hidden');
    document.querySelectorAll('[id^="campaignLinkActionsWrap-"]').forEach(el => el.classList.add('hidden'));
    if (willOpen) {
        wrap.classList.remove('hidden');
        campaignOpenPixelLinkId = Number(linkId);
        const targetList = document.getElementById(`trackingActionsList-${linkId}`);
        if (targetList) {
            loadTrackingActions(linkId);
        }
    } else {
        campaignOpenPixelLinkId = null;
    }
}

async function toggleCampaignTrackingForLink(linkId) {
    if (isFeatureLocked('tracking')) {
        openPlanRestrictionModal({
            feature: 'tracking',
            title: 'Tracking bloqueado no plano',
            message: 'Seu plano atual não permite ativar/desativar tracking em campanhas.',
            ctaText: 'Ver planos'
        });
        return;
    }
    const campaignName = (document.getElementById('campaignSelect')?.value || '').trim();
    const target = campaignLinksDetailsCache.find(link => Number(link.id) === Number(linkId));
    if (!target) {
        showStatus('campaign', 'Não foi possível localizar o link para atualizar rastreamento.', 'error');
        return;
    }

    const newStatus = parseInt(target.tracking_enabled || 0, 10) === 1 ? 0 : 1;
    const fd = new FormData();
    fd.append('action', 'toggle_tracking');
    fd.append('id', String(linkId));
    fd.append('status', String(newStatus));

    try {
        const res = await fetch('manage_links.php', { method: 'POST', body: fd, credentials: 'include' });
        const data = await res.json();
        if (!data.success) {
            if (handlePlanRestrictionResponse(data, { feature: 'tracking', title: 'Tracking bloqueado no plano' })) {
                return;
            }
            showStatus('campaign', data.message || 'Não foi possível atualizar o rastreamento.', 'error');
            return;
        }
        campaignLinksDetailsCache = campaignLinksDetailsCache.map(link =>
            Number(link.id) === Number(linkId) ? { ...link, tracking_enabled: newStatus } : link
        );
        campaignTrackingLinksCache = campaignTrackingLinksCache.map(link =>
            Number(link.id) === Number(linkId) ? { ...link, trackingEnabled: newStatus } : link
        );
        showStatus('campaign', newStatus === 1 ? 'Rastreamento ativado para o link.' : 'Rastreamento desativado para o link.', 'success');
        renderCampaignLinksList(campaignName, campaignLinksDetailsCache.filter(link =>
            normalizeCampaignKey(link.campaign_name || link.utm_campaign || '') === normalizeCampaignKey(campaignName)
        ));
        const reopened = document.getElementById(`campaignLinkActionsWrap-${linkId}`);
        if (reopened) {
            reopened.classList.remove('hidden');
            campaignOpenPixelLinkId = Number(linkId);
            if (newStatus === 1) loadTrackingActions(linkId);
        }
    } catch (err) {
        console.error('Erro ao alternar rastreamento por link', err);
        showStatus('campaign', 'Erro ao atualizar rastreamento do link.', 'error');
    }
}

function handleCampaignLinksFilterChange() {
    const input = document.getElementById('campaignLinksSearchInput');
    campaignLinksFilterText = (input?.value || '').trim();
    const campaignName = (document.getElementById('campaignSelect')?.value || '').trim();
    loadCampaignLinksForSelected(campaignName);
}

function toggleCampaignLinksCompactMode() {
    campaignLinksCompactMode = !campaignLinksCompactMode;
    const btn = document.getElementById('campaignCompactToggleBtn');
    if (btn) {
        btn.innerHTML = campaignLinksCompactMode
            ? '<i class="fas fa-expand-alt"></i> Modo confortável'
            : '<i class="fas fa-compress-alt"></i> Modo compacto';
    }
    const campaignName = (document.getElementById('campaignSelect')?.value || '').trim();
    loadCampaignLinksForSelected(campaignName);
}

async function loadCampaignAnalytics(campaignName) {
    updateCampaignSelectionHighlight(campaignName);
    if (!campaignName) {
        document.getElementById('campaignAnalyticsArea').classList.add('hidden');
        document.getElementById('camp_utm_campaign').value = "";
        filterCampaignTrackingLinks('');
        updateCampaignTrackingPanelState();
        renderCampaignLinksList('', []);
        return;
    }

    document.getElementById('campaignAnalyticsArea').classList.remove('hidden');
    document.getElementById('camp_utm_campaign').value = campaignName;
    filterCampaignTrackingLinks(campaignName);
    updateCampaignTrackingPanelState();
    loadCampaignLinksForSelected(campaignName);

    if (isFeatureLocked('bulk_utm')) {
        const normalized = normalizeCampaignKey(campaignName);
        const links = campaignLinksDetailsCache.filter(link =>
            normalizeCampaignKey(link.campaign_name || link.utm_campaign || '') === normalized
        );
        const sourceMap = new Map();
        const mediumMap = new Map();
        links.forEach(link => {
            const source = (link.utm_source || 'direto').toLowerCase();
            const medium = (link.utm_medium || 'direct').toLowerCase();
            const sourceBoost = 80 + Math.floor(Math.random() * 160);
            const mediumBoost = 80 + Math.floor(Math.random() * 160);
            sourceMap.set(source, (sourceMap.get(source) || 0) + sourceBoost);
            mediumMap.set(medium, (mediumMap.get(medium) || 0) + mediumBoost);
        });
        if (sourceMap.size === 0) {
            sourceMap.set('instagram', 220);
            sourceMap.set('google', 160);
        }
        if (mediumMap.size === 0) {
            mediumMap.set('cpc', 240);
            mediumMap.set('stories', 140);
        }
        renderChart('chartCampSource', 'doughnut', Array.from(sourceMap.keys()), Array.from(sourceMap.values()));
        renderChart('chartCampMedium', 'pie', Array.from(mediumMap.keys()), Array.from(mediumMap.values()));
        showStatus('campaign', 'Campanha em modo demonstração com dados fictícios para análise completa.', 'success');
        return;
    }

    try {
        const res = await fetch(`analytics.php?campaign=${encodeURIComponent(campaignName)}`, { credentials: 'include' });
        const data = await res.json();
        if (!data.success) return;

        renderChart('chartCampSource', 'doughnut', data.utms.sources.map(i => i.label), data.utms.sources.map(i => i.value));
        renderChart('chartCampMedium', 'pie', data.utms.mediums.map(i => i.label), data.utms.mediums.map(i => i.value));

    } catch (e) { console.error("Erro ao carregar analytics de campanha", e); }
}

function filterCampaignTrackingLinks(campaignName) {
    const trackingSelect = document.getElementById('campaignTrackingLinkSelect');
    if (!trackingSelect) return;
    const campaign = normalizeCampaignKey(campaignName);
    const selectedValue = trackingSelect.value;

    if (!campaign) {
        trackingSelect.disabled = true;
        trackingSelect.innerHTML = '<option value="">Selecione uma campanha antes de continuar</option>';
        trackingSelect.value = '';
        return;
    }

    trackingSelect.disabled = false;
    trackingSelect.innerHTML = '<option value="">Selecione um link da campanha</option>';
    const filteredLinks = campaignTrackingLinksCache.filter(
        link => normalizeCampaignKey(link.campaignName) === campaign
    );

    filteredLinks.forEach(link => {
        const opt = document.createElement('option');
        opt.value = link.id;
        opt.dataset.campaignName = link.campaignName;
        opt.dataset.trackingEnabled = String(link.trackingEnabled);
        opt.innerText = link.label;
        trackingSelect.appendChild(opt);
    });

    if (selectedValue && filteredLinks.some(link => link.id === selectedValue)) {
        trackingSelect.value = selectedValue;
    } else {
        trackingSelect.value = '';
    }
}

function updateCampaignTrackingPanelState() {
    const trackingSelect = document.getElementById('campaignTrackingLinkSelect');
    const toggleBtn = document.getElementById('campaignTrackingToggleBtn');
    const summary = document.getElementById('campaignTrackingSummary');
    const host = document.getElementById('campaignTrackingActionsHost');
    if (!trackingSelect || !toggleBtn || !summary || !host) return;
    if (trackingSelect.disabled) {
        summary.innerText = 'Selecione uma campanha antes de continuar.';
        toggleBtn.innerHTML = '<i class="fas fa-power-off"></i> Ativar rastreamento';
        host.innerHTML = '';
        return;
    }
    const opt = trackingSelect.selectedOptions[0];
    if (!opt || !opt.value) {
        summary.innerText = 'Selecione um link para configurar eventos de conversão.';
        toggleBtn.innerHTML = '<i class="fas fa-power-off"></i> Ativar rastreamento';
        host.innerHTML = '';
        return;
    }
    const enabled = parseInt(opt.dataset.trackingEnabled || '0', 10) === 1;
    toggleBtn.innerHTML = enabled
        ? '<i class="fas fa-toggle-on"></i> Desativar rastreamento'
        : '<i class="fas fa-power-off"></i> Ativar rastreamento';
    summary.innerText = enabled
        ? 'Rastreamento ativo. Cadastre eventos e copie os snippets para a página de destino.'
        : 'Rastreamento desligado. Ative para começar a registrar eventos da campanha.';

    host.innerHTML = enabled ? `
        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-top: 10px;">
            <div style="background:var(--card); padding:20px; border-radius:10px; border:1px solid rgba(255,255,255,0.05);">
                <h4 style="margin-bottom:15px; color:#8b5cf6;"><i class="fas fa-boxes"></i> Eventos Cadastrados</h4>
                <div id="trackingActionsList-${opt.value}" style="display:flex; flex-direction:column; gap:10px; margin-bottom:15px; max-height: 250px; overflow-y:auto; padding-right:5px;">
                    <div style="text-align:center; padding:10px; color:var(--text-dim);">Carregando eventos... <i class="fas fa-spinner fa-spin"></i></div>
                </div>
                <button onclick="openTrackingActionModal(${opt.value})" class="btn-text" style="width:100%; border:1px dashed rgba(255,255,255,0.2); padding:10px; border-radius:6px;"><i class="fas fa-plus"></i> Novo Evento</button>
            </div>
            <div style="background:var(--card); padding:20px; border-radius:10px; border:1px solid rgba(255,255,255,0.05);">
                <h4 style="margin-bottom:15px; color:#3b82f6;"><i class="fas fa-code"></i> Script de Instalação</h4>
                <p style="font-size:0.8rem; color:var(--text-dim);">1. Cole o script base no <code>&lt;head&gt;</code> da página de destino.</p>
                <textarea readonly style="width:100%; height:62px; font-family:monospace; font-size:0.75rem; background:var(--interactive-soft); border:1px solid var(--border); border-radius:6px; padding:10px; color:var(--text-soft);"><script src="https://${window.location.hostname}/tracker.js" async></script></textarea>
                <p style="font-size:0.8rem; color:var(--text-dim); margin-top:12px;">2. Crie um evento e use o botão <i class="fas fa-code"></i> para copiar o disparo.</p>
            </div>
        </div>
    ` : '';

    if (enabled) {
        loadTrackingActions(parseInt(opt.value, 10));
    }
}

async function toggleCampaignTrackingFromPanel() {
    const trackingSelect = document.getElementById('campaignTrackingLinkSelect');
    if (!trackingSelect || !trackingSelect.value) {
        showStatus('campaign', 'Selecione um link da campanha para ativar o rastreamento.', 'error');
        return;
    }
    if (isFeatureLocked('tracking')) {
        openPlanRestrictionModal({
            feature: 'tracking',
            title: 'Tracking bloqueado no plano',
            message: 'Seu plano atual não permite rastreamento de eventos.',
            ctaText: 'Ver planos'
        });
        return;
    }
    const opt = trackingSelect.selectedOptions[0];
    const currentlyEnabled = parseInt(opt.dataset.trackingEnabled || '0', 10) === 1;
    const newStatus = currentlyEnabled ? 0 : 1;
    const fd = new FormData();
    fd.append('action', 'toggle_tracking');
    fd.append('id', trackingSelect.value);
    fd.append('status', String(newStatus));
    const res = await fetch('manage_links.php', { method: 'POST', body: fd, credentials: 'include' });
    const data = await res.json();
    if (data.success) {
        opt.dataset.trackingEnabled = String(newStatus);
        campaignTrackingLinksCache = campaignTrackingLinksCache.map(link => (
            link.id === String(trackingSelect.value)
                ? { ...link, trackingEnabled: newStatus }
                : link
        ));
        updateCampaignTrackingPanelState();
    } else {
        if (handlePlanRestrictionResponse(data, { feature: 'tracking', title: 'Tracking bloqueado no plano' })) {
            return;
        }
        showStatus('campaign', data.message || 'Não foi possível atualizar o rastreamento.', 'error');
    }
}

// --- BIO PAGES (MICROSITES) ---
function switchBioTab(tab) {
    document.querySelectorAll('.bio-tab-content').forEach(c => c.classList.add('hidden'));
    document.querySelectorAll('.bio-tab-btn').forEach(b => b.classList.remove('active'));

    document.getElementById(`bio-tab-${tab}`).classList.remove('hidden');

    // Verificação robusta do evento
    const e = window.event || (arguments.length > 0 ? arguments[0] : null);
    if (e && e.currentTarget && e.currentTarget.classList) {
        e.currentTarget.classList.add('active');
    } else {
        const btn = document.querySelector(`.bio-tab-btn[onclick*='${tab}']`);
        if (btn) btn.classList.add('active');
    }
}

window.uploadBioPhoto = async function (input) {
    if (!input.files || !input.files[0]) return;

    const file = input.files[0];
    const formData = new FormData();
    formData.append('bio_photo', file);

    const btn = document.querySelector('.upload-btn');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>...';
    btn.disabled = true;

    try {
        const res = await fetch('upload_bio.php', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();

        if (data.success) {
            document.getElementById('bio-avatar').value = data.path;
            updateBioPreview();
            showNotification('Foto enviada com sucesso!', 'success');
        } else {
            showNotification(data.message || 'Erro no upload', 'danger');
        }
    } catch (e) {
        console.error(e);
        showNotification('Erro de conexão no servidor.', 'danger');
    } finally {
        btn.innerHTML = originalText;
        btn.disabled = false;
        input.value = ''; // Reset input
    }
};

window.uploadBioCoverPhoto = async function (input) {
    if (!input.files || !input.files[0]) return;

    const file = input.files[0];
    const formData = new FormData();
    formData.append('bio_cover', file);

    const btn = document.querySelector('.bio-cover-upload-btn');
    if (!btn) return;
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>...';
    btn.disabled = true;

    try {
        const res = await fetch('upload_bio.php', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();

        if (data.success) {
            const coverInput = document.getElementById('bio-hero-cover');
            if (coverInput) coverInput.value = data.path;
            if (typeof window.updateBioPreview === 'function') {
                window.updateBioPreview();
            }
            showNotification('Imagem de capa enviada com sucesso!', 'success');
        } else {
            showNotification(data.message || 'Erro no upload', 'danger');
        }
    } catch (e) {
        console.error(e);
        showNotification('Erro de conexão no servidor.', 'danger');
    } finally {
        btn.innerHTML = originalText;
        btn.disabled = false;
        input.value = '';
    }
};

// --- BIO TEMPLATES (MODULAR) ---
// --- BIO GOAL TEMPLATES & SOCIAL NETWORKS ---
const SOCIAL_NETWORKS = {
    instagram: { name: 'Instagram', icon: 'fab fa-instagram', placeholder: '@usuario ou link', color: '#E1306C', url: 'https://instagram.com/' },
    facebook: { name: 'Facebook', icon: 'fab fa-facebook', placeholder: 'usuario ou link', color: '#1877F2', url: 'https://facebook.com/' },
    tiktok: { name: 'TikTok', icon: 'fab fa-tiktok', placeholder: '@usuario ou link', color: '#000000', url: 'https://www.tiktok.com/@' },
    youtube: { name: 'YouTube', icon: 'fab fa-youtube', placeholder: 'canal ou link', color: '#FF0000', url: 'https://youtube.com/@' },
    twitter: { name: 'X / Twitter', icon: 'fab fa-x-twitter', placeholder: '@usuario ou link', color: '#000000', url: 'https://x.com/' },
    linkedin: { name: 'LinkedIn', icon: 'fab fa-linkedin', placeholder: 'perfil ou link', color: '#0077B5', url: 'https://linkedin.com/in/' },
    whatsapp: { name: 'WhatsApp', icon: 'fab fa-whatsapp', placeholder: 'DDD + Numero', color: '#25D366', url: 'https://wa.me/' },
    telegram: { name: 'Telegram', icon: 'fab fa-telegram', placeholder: 'usuario', color: '#26A5E4', url: 'https://t.me/' },
    threads: { name: 'Threads', icon: 'fab fa-threads', placeholder: '@usuario', color: '#000000', url: 'https://threads.net/@' },
    spotify: { name: 'Spotify', icon: 'fab fa-spotify', placeholder: 'link da playlist/perfil', color: '#1DB954', url: '' },
    pinterest: { name: 'Pinterest', icon: 'fab fa-pinterest', placeholder: 'usuario', color: '#E60023', url: 'https://pinterest.com/' },
    github: { name: 'GitHub', icon: 'fab fa-github', placeholder: 'usuario', color: '#333', url: 'https://github.com/' },
    behance: { name: 'Behance', icon: 'fab fa-behance', placeholder: 'usuario', color: '#1769ff', url: 'https://www.behance.net/' },
    dribbble: { name: 'Dribbble', icon: 'fab fa-dribbble', placeholder: 'usuario', color: '#ea4c89', url: 'https://dribbble.com/' },
    kwai: { name: 'Kwai', icon: 'fas fa-play-circle', placeholder: 'usuario', color: '#ff5000', url: 'https://kwai.com/' },
    snapchat: { name: 'Snapchat', icon: 'fab fa-snapchat', placeholder: 'usuario', color: '#fffc00', url: 'https://www.snapchat.com/add/' }
};

const GOAL_TEMPLATES = {
    afiliado: {
        id: 'goal-afiliado',
        name: 'Modelo: Afiliado',
        type: 'goal',
        desc: 'Foco em conversão e destaque para ofertas irresistíveis.',
        theme: { bg: '#0f172a', btn: '#1A8CFF', text: '#ffffff', style: 'solid', template_id: 1 },
        links: [
            { title: '🔥 Oferta Especial (Tempo Limitado)', url: 'https://...', link_type: 'link', is_featured: 1 },
            { title: 'Lista de Descontos VIP', url: 'https://...', link_type: 'link' },
            { title: 'Siga no Instagram', url: '', link_type: 'social', social_network: 'instagram', social_value: '@seu_link' },
            { title: 'Chame no WhatsApp', url: '', link_type: 'social', social_network: 'whatsapp', social_value: '11999999999' }
        ]
    },
    loja: {
        id: 'goal-loja',
        name: 'Modelo: Loja',
        type: 'goal',
        desc: 'Para e-commerce e vitrines de produtos físicos.',
        theme: { bg: '#ffffff', btn: '#000000', text: '#000000', style: 'rounded', template_id: 2 },
        links: [
            { title: '🛍️ Ver Catálogo Completo', url: 'https://...', link_type: 'link' },
            { title: 'Novidades da Semana', url: 'https://...', link_type: 'link' },
            { title: 'Nossa Localização', url: 'https://...', link_type: 'link' },
            { title: 'Atendimento via WhatsApp', url: '', link_type: 'social', social_network: 'whatsapp', social_value: '11999999999' }
        ]
    },
    expert: {
        id: 'goal-expert',
        name: 'Modelo: Expert',
        type: 'goal',
        desc: 'Autoridade e links para mentorias e cursos.',
        theme: { bg: '#111827', btn: '#3b82f6', text: '#ffffff', style: 'pill', template_id: 3 },
        links: [
            { title: '🚀 Inscrições Abertas: Mentoria', url: 'https://...', link_type: 'link', is_featured: 1 },
            { title: 'Download E-book Grátis', url: 'https://...', link_type: 'link' },
            { title: 'Conteúdo no YouTube', url: '', link_type: 'social', social_network: 'youtube', social_value: 'seu_canal' },
            { title: 'Acompanhe no Instagram', url: '', link_type: 'social', social_network: 'instagram', social_value: '@seu_insta' }
        ]
    },
    portfolio: {
        id: 'goal-portfolio',
        name: 'Modelo: Portfólio',
        type: 'goal',
        desc: 'Mostre seus melhores projetos e trabalhos.',
        theme: { bg: '#000000', btn: '#ffffff', text: '#ffffff', style: 'outline', template_id: 4 },
        links: [
            { title: '🎨 Veja meu Behance', url: '', link_type: 'social', social_network: 'behance', social_value: 'seu_perfil' },
            { title: '💻 Repositórios no GitHub', url: '', link_type: 'social', social_network: 'github', social_value: 'seu_perfil' },
            { title: 'Projeto Destaque 2024', url: 'https://...', link_type: 'link' },
            { title: 'Entre em contato', url: '', link_type: 'social', social_network: 'linkedin', social_value: 'seu_perfil' }
        ]
    },
    lancamento: {
        id: 'goal-lancamento',
        name: 'Modelo: Lançamento',
        type: 'goal',
        desc: 'Página focada em capturar leads e gerar hype.',
        theme: { bg: '#050505', btn: '#f59e0b', text: '#ffffff', style: 'solid', template_id: 5 },
        links: [
            { title: '🔔 QUERO ME INSCREVER AGORA', url: 'https://...', link_type: 'link', is_featured: 1 },
            { title: 'Grupo Exclusivo (WhatsApp)', url: '', link_type: 'social', social_network: 'whatsapp', social_value: '11999999999' },
            { title: 'Assista ao Vídeo de Introdução', url: 'https://...', link_type: 'link' }
        ]
    },
    whatsapp: {
        id: 'goal-whatsapp',
        name: 'Modelo: Consultoria',
        type: 'goal',
        desc: 'Foco total em levar o cliente para o atendente.',
        theme: { bg: '#064e3b', btn: '#25D366', text: '#ffffff', style: 'pill', template_id: 1 },
        links: [
            { title: '💬 Falar com Atendente agora', url: '', link_type: 'social', social_network: 'whatsapp', social_value: '11999999999' },
            { title: 'Ver Horários de Funcionamento', url: 'https://...', link_type: 'link' },
            { title: 'Nossos Serviços', url: 'https://...', link_type: 'link' }
        ]
    },
    evento: {
        id: 'goal-evento',
        name: 'Modelo: Evento',
        type: 'goal',
        desc: 'Datas, ingressos e informações úteis.',
        theme: { bg: '#1e1b4b', btn: '#8b5cf6', text: '#ffffff', style: 'rounded', template_id: 7 },
        links: [
            { title: '🎟️ GARANTIR MEU INGRESSO', url: 'https://...', link_type: 'link', is_featured: 1 },
            { title: '📍 Localização do Evento', url: 'https://...', link_type: 'link' },
            { title: 'Programação Completa', url: 'https://...', link_type: 'link' }
        ]
    },
    restaurante: {
        id: 'goal-restaurante',
        name: 'Modelo: Restaurante',
        type: 'goal',
        desc: 'Cardápio, pedidos e localização rápida.',
        theme: { bg: '#450a0a', btn: '#ef4444', text: '#ffffff', style: 'rounded', template_id: 6 },
        links: [
            { title: '🍕 VER CARDÁPIO ONLINE', url: 'https://...', link_type: 'link', is_featured: 1 },
            { title: '🛵 Faça seu pedido no WhatsApp', url: '', link_type: 'social', social_network: 'whatsapp', social_value: '11999999999' },
            { title: '📍 Como Chegar', url: 'https://...', link_type: 'link' }
        ]
    }
};


let bioTemplatesData = [];

function isBioTemplateLockedByPlan(templateId) {
    const tpl = bioTemplatesData.find(t => Number(t.id) === Number(templateId));
    return Boolean(tpl && tpl.locked_by_plan === true);
}

async function loadBioTemplates() {
    const list = document.getElementById('bio-templates-list');
    if (!list) return;

    try {
        const res = await fetch('api_bio_templates.php');
        const data = await res.json();

        if (data.success) {
            bioTemplatesData = data.templates;
            list.innerHTML = '';

            // 1. Injeta os MODELOS POR OBJETIVO primeiro (como solicitado pelo usuário implicitamente ao integrá-los)
            Object.keys(GOAL_TEMPLATES).forEach(key => {
                const t = GOAL_TEMPLATES[key];
                const isActive = t.id === currentBioPage.selected_goal_id; // Precisaremos persistir isso ou apenas checar template_id

                const div = document.createElement('div');
                div.className = `template-option goal-item ${isActive ? 'active' : ''}`;
                div.dataset.id = t.id;
                div.onclick = () => switchBioTemplate(t.id);

                div.innerHTML = `
                    <div class="tpl-thumb tpl-preview-goal">
                        <i class="fas fa-magic"></i>
                    </div>
                    <span>${t.name}</span>
                `;
                list.appendChild(div);
            });

            // 2. Injeta os TEMPLATES VISUAIS normais
            data.templates.forEach(tpl => {
                const isActive = tpl.id === currentBioPage.template_id && !currentBioPage.selected_goal_id;
                const isLocked = tpl.locked_by_plan === true;
                const div = document.createElement('div');
                div.className = `template-option ${isActive ? 'active' : ''} ${isLocked ? 'template-option-locked' : ''}`;
                div.dataset.id = tpl.id;
                div.onclick = () => {
                    if (isLocked) {
                        openPlanRestrictionModal({
                            feature: 'premium_bio_templates',
                            title: 'Template premium bloqueado',
                            message: 'Seu plano atual não inclui templates premium de Bio. Faça upgrade para liberar.',
                            ctaText: 'Ver planos'
                        });
                        return;
                    }
                    switchBioTemplate(tpl.id);
                };

                div.innerHTML = `
                    ${tpl.thumb_html}
                    <span>${tpl.name}</span>
                    ${isLocked ? '<small style="display:block; color:#ef4444; margin-top:4px;">Premium</small>' : ''}
                `;
                list.appendChild(div);
            });
        }
    } catch (e) {
        console.error("Erro ao carregar templates", e);
        list.innerHTML = '<div style="color:#ef4444; padding:20px;">Falha ao carregar modelos.</div>';
    }
}

function switchBioTemplate(id) {
    // Verifica se é um Modelo por Objetivo (ID começa com 'goal-')
    if (typeof id === 'string' && id.startsWith('goal-')) {
        const goalKey = id.replace('goal-', '');
        applyGoalTemplate(goalKey);
        return;
    }

    if (isBioTemplateLockedByPlan(id)) {
        openPlanRestrictionModal({
            feature: 'premium_bio_templates',
            title: 'Template premium bloqueado',
            message: 'Seu plano atual não inclui templates premium de Bio. Faça upgrade para liberar.',
            ctaText: 'Ver planos'
        });
        return;
    }

    currentBioPage.template_id = parseInt(id);
    currentBioPage.selected_goal_id = null; // Limpa se escolher um visual puro

    // Atualiza UI de seleção na galeria
    document.querySelectorAll('.template-option').forEach(opt => {
        opt.classList.toggle('active', opt.dataset.id == id);
    });

    // Injeta CSS do template dinamicamente para o Mockup
    const tpl = bioTemplatesData.find(t => t.id == id);
    if (tpl) {
        let tplLink = document.getElementById('bio-tpl-css-preview');
        if (!tplLink) {
            tplLink = document.createElement('link');
            tplLink.id = 'bio-tpl-css-preview';
            tplLink.rel = 'stylesheet';
            document.head.appendChild(tplLink);
        }
        // Use folder for the path
        tplLink.href = `templates/bio/${tpl.folder}/style.css?v=${Date.now()}`;
    }

    updateBioPreview();
}



function applyGoalTemplate(key) {
    if (!guardTeamEditPermission()) return;
    const t = GOAL_TEMPLATES[key];
    if (!t) return;

    if (isBioTemplateLockedByPlan(t.theme.template_id)) {
        openPlanRestrictionModal({
            feature: 'premium_bio_templates',
            title: 'Template premium bloqueado',
            message: 'Este modelo utiliza um template premium de Bio. Faça upgrade para liberar.',
            ctaText: 'Ver planos'
        });
        return;
    }

    // if (currentBioPage.links.length > 0 && !confirm("Este modelo irá preencher sua página com links de exemplo e configurar o design. Deseja continuar?")) {
    //     return;
    // }

    // Marca como objetivo selecionado para UI
    currentBioPage.selected_goal_id = t.id;
    currentBioPage.template_id = t.theme.template_id;

    // Aplica o Tema nos inputs
    document.getElementById('bio-theme-bg').value = t.theme.bg;
    document.getElementById('bio-theme-btn').value = t.theme.btn;
    document.getElementById('bio-theme-text').value = t.theme.text;
    document.getElementById('bio-theme-style').value = t.theme.style;

    // Injeta CSS do template base para o preview
    const tpl = bioTemplatesData.find(tplItem => tplItem.id == t.theme.template_id);
    if (tpl) {
        let tplLink = document.getElementById('bio-tpl-css-preview');
        if (tplLink) tplLink.href = `templates/bio/${tpl.folder}/style.css?v=${Date.now()}`;
    }

    // Aplica os Links/Blocos
    currentBioPage.links = t.links.map(l => ({
        ...l,
        id: Date.now() + Math.random()
    }));

    // Atualiza UI
    document.querySelectorAll('.template-option').forEach(opt => {
        opt.classList.toggle('active', opt.dataset.id === t.id);
    });

    renderBioLinksEditor();
    updateBioPreview();
    showNotification(`Modelo "${t.name}" aplicado!`, 'success');
}

function addBioLink() {
    if (!guardTeamEditPermission()) return;
    const linkId = Date.now();
    const linkObj = { id: linkId, title: '', url: '', icon_class: 'fas fa-link', link_type: 'link' };
    currentBioPage.links.push(linkObj);
    renderBioLinksEditor();
    updateBioPreview();
}

function addSpecialBlock(type) {
    if (!guardTeamEditPermission()) return;
    const linkId = Date.now();
    if (type === 'whatsapp') {
        const num = prompt("Digite o número do WhatsApp (com DDD):");
        if (num) {
            currentBioPage.links.push({ id: linkId, title: 'WhatsApp', url: '', link_type: 'social', social_network: 'whatsapp', social_value: num.replace(/\D/g, ''), icon_class: 'fab fa-whatsapp' });
        }
    } else if (type === 'pix') {
        const key = prompt("Digite sua Chave Pix:");
        if (key) {
            currentBioPage.links.push({ id: linkId, title: 'Copiar Chave Pix', url: '', link_type: 'pix', social_value: key, icon_class: 'fas fa-qrcode' });
        }
    } else if (type === 'social') {
        openSocialSelector();
        return;
    }
    renderBioLinksEditor();
    updateBioPreview();
}

function openSocialSelector() {
    if (!document.getElementById('modalSocialSelector')) {
        const div = document.createElement('div');
        div.id = 'modalSocialSelector';
        div.className = 'modal-content hidden';
        div.style.maxWidth = '500px';
        div.onclick = (e) => e.stopPropagation();

        let socialHtml = '';
        Object.keys(SOCIAL_NETWORKS).forEach(key => {
            const s = SOCIAL_NETWORKS[key];
            socialHtml += `
            <div class="social-select-item" onclick="addSocialLink('${key}')">
                <i class="${s.icon}" style="color: ${s.color};"></i>
                <span>${s.name}</span>
            </div>
            `;
        });

        div.innerHTML = `
            <div class="modal-header">
                <h3>Adicionar Rede Social</h3>
                <button type="button" onclick="closeModals()" class="close-btn"><i class="fas fa-times"></i></button>
            </div>
            <div class="social-selector-grid">
                ${socialHtml}
            </div>
        `;
        document.getElementById('modalOverlay').appendChild(div);
    }
    openModal('modalSocialSelector');
}

function addSocialLink(network) {
    const s = SOCIAL_NETWORKS[network];
    const linkId = Date.now();
    currentBioPage.links.push({
        id: linkId,
        title: s.name,
        url: '',
        link_type: 'social',
        social_network: network,
        social_value: '',
        icon_class: s.icon
    });
    closeModals();
    renderBioLinksEditor();
    updateBioPreview();
}

function renderBioLinksEditor() {
    const list = document.getElementById('bio-links-list');
    list.innerHTML = '';

    currentBioPage.links.forEach((link, index) => {
        const div = document.createElement('div');
        div.className = 'bio-link-item';

        let contentHtml = '';

        if (link.link_type === 'link') {
            contentHtml = `
                <div class="form-group" style="margin-bottom: 10px;">
                    <label style="font-size: 0.7rem;">Título do Link</label>
                    <input type="text" value="${link.title}" placeholder="Ex: Meu Instagram" oninput="updateLinkData(${link.id}, 'title', this.value)" class="bio-input">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 0.7rem;">URL de Destino</label>
                    <input type="url" value="${link.url}" placeholder="https://..." oninput="updateLinkData(${link.id}, 'url', this.value)" class="bio-input">
                </div>
            `;
        } else if (link.link_type === 'social') {
            const net = SOCIAL_NETWORKS[link.social_network];
            contentHtml = `
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px; background: rgba(255,255,255,0.05); padding: 5px 10px; border-radius: 6px;">
                    <i class="${net.icon}" style="color: ${net.color}; font-size: 1.2rem;"></i>
                    <span style="font-size: 0.8rem; font-weight: bold;">${net.name}</span>
                </div>
                <div class="form-group" style="margin-bottom: 10px;">
                    <label style="font-size: 0.7rem;">Texto do Botão</label>
                    <input type="text" value="${link.title}" oninput="updateLinkData(${link.id}, 'title', this.value)" class="bio-input">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 0.7rem;">Usuário ou Link</label>
                    <input type="text" value="${link.social_value || ''}" placeholder="${net.placeholder}" oninput="updateLinkData(${link.id}, 'social_value', this.value)" class="bio-input">
                </div>
            `;
        } else if (link.link_type === 'pix') {
            contentHtml = `
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px; background: rgba(50, 188, 173, 0.1); padding: 5px 10px; border-radius: 6px;">
                    <i class="fas fa-qrcode" style="color: #32BCAD; font-size: 1.2rem;"></i>
                    <span style="font-size: 0.8rem; font-weight: bold;">Chave PIX</span>
                </div>
                <div class="form-group" style="margin-bottom: 10px;">
                    <label style="font-size: 0.7rem;">Texto do Botão</label>
                    <input type="text" value="${link.title}" oninput="updateLinkData(${link.id}, 'title', this.value)" class="bio-input">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 0.7rem;">Chave Pix</label>
                    <input type="text" value="${link.social_value || ''}" oninput="updateLinkData(${link.id}, 'social_value', this.value)" class="bio-input">
                </div>
            `;
        }

        div.innerHTML = `
            <span class="remove-link" onclick="removeBioLink(${link.id})"><i class="fas fa-times"></i></span>
            <div class="drag-handle"><i class="fas fa-grip-vertical"></i></div>
            ${contentHtml}
        `;
        list.appendChild(div);
    });
}

function updateLinkData(id, field, value) {
    const link = currentBioPage.links.find(l => l.id == id);
    if (link) {
        link[field] = value;
        updateBioPreview();
    }
}

function removeBioLink(id) {
    if (!guardTeamEditPermission()) return;
    if (!confirm("Tem certeza que deseja remover este link?")) return;
    currentBioPage.links = currentBioPage.links.filter(l => l.id != id);
    renderBioLinksEditor();
    updateBioPreview();
}


function updateBioPreview() {
    if (typeof window.updateBioPreviewEnhanced === 'function' && document.getElementById('bio-avatar-zoom')) {
        window.updateBioPreviewEnhanced();
        return;
    }
    const preview = document.getElementById('bio-live-preview');
    if (!preview) return;

    const slug = document.getElementById('bio-slug').value;
    const title = document.getElementById('bio-title').value || 'Seu Nome';
    const description = document.getElementById('bio-description').value || 'Sua descrição aparecerá aqui.';
    const avatar = document.getElementById('bio-avatar').value;
    const cacheBuster = `?v=${Date.now()}`;

    const bgColor = document.getElementById('bio-theme-bg').value;
    const btnColor = document.getElementById('bio-theme-btn').value;
    const textColor = document.getElementById('bio-theme-text').value;
    const btnStyle = document.getElementById('bio-theme-style').value;

    const frame = document.querySelector('.mobile-frame');
    frame.style.backgroundColor = bgColor;
    frame.style.color = textColor;

    // Limpa classes de template anteriores e aplica a nova dinamicamente
    const mock = document.querySelector('.mobile-mockup');
    mock.className = 'mobile-mockup'; // Reset to base classes

    const tpl = bioTemplatesData.find(t => t.id == currentBioPage.template_id);
    if (tpl) {
        mock.classList.add(`tpl-${tpl.slug}`);
    } else {
        mock.classList.add('tpl-classic');
    }

    const avatarDiv = preview.querySelector('.bio-avatar');
    const header = preview.querySelector('.bio-header');

    if (avatar) {
        const fullPath = avatar.startsWith('http') ? avatar : avatar + cacheBuster;
        avatarDiv.style.backgroundImage = `url('${fullPath}')`;
        if (currentBioPage.template_id == 3 && header) {
            header.style.backgroundImage = `url('${fullPath}')`;
        }
    } else {
        avatarDiv.style.backgroundImage = "url('https://cdn-icons-png.flaticon.com/512/1243/1243933.png')";
        if (header) header.style.backgroundImage = 'none';
    }

    // Reset background no header se não for template 3
    if (currentBioPage.template_id != 3 && header) {
        header.style.backgroundImage = 'none';
    }

    avatarDiv.style.border = `3px solid ${btnColor}`;

    preview.querySelector('.preview-name').innerText = title;
    preview.querySelector('.preview-name').style.color = textColor;
    preview.querySelector('.preview-bio').innerText = description;
    preview.querySelector('.preview-bio').style.color = textColor;

    const linksDiv = preview.querySelector('.preview-links');
    linksDiv.innerHTML = '';

    currentBioPage.links.forEach(link => {
        if (!link.title && link.link_type === 'link') return;

        const a = document.createElement('a');
        a.href = '#';
        a.className = `bio-btn-preview style-${btnStyle}`;

        if (btnStyle === 'outline') {
            a.style.backgroundColor = 'transparent';
            a.style.border = `2px solid ${btnColor}`;
            a.style.color = btnColor;
        } else {
            a.style.backgroundColor = btnColor;
            a.style.color = bgColor;
        }

        let iconHtml = '';
        if (link.link_type === 'social') {
            const net = SOCIAL_NETWORKS[link.social_network];
            if (net) iconHtml = `<i class="${net.icon}" style="margin-right: 10px;"></i>`;
        } else if (link.link_type === 'pix') {
            iconHtml = `<i class="fas fa-qrcode" style="margin-right: 10px;"></i>`;
        }

        a.innerHTML = `${iconHtml}<span>${link.title || ''}</span>`;
        linksDiv.appendChild(a);
    });
}

async function saveBioPage() {
    if (!isLoggedIn) return openModal('modalLogin');
    if (!guardTeamEditPermission()) return;

    const slug = document.getElementById('bio-slug').value.trim();
    if (!slug) return alert("Por favor, defina um slug para sua página.");

    const btn = document.getElementById('btnSaveBio');
    const originalHTML = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando...';

    const fd = new FormData();
    if (currentBioPage.id) fd.append('id', currentBioPage.id);

    fd.append('action', 'save');
    fd.append('slug', slug);
    fd.append('title', document.getElementById('bio-title').value);
    fd.append('description', document.getElementById('bio-description').value);
    fd.append('avatar_url', document.getElementById('bio-avatar').value);
    fd.append('theme_bg_color', document.getElementById('bio-theme-bg').value);
    fd.append('theme_btn_color', document.getElementById('bio-theme-btn').value);
    fd.append('theme_text_color', document.getElementById('bio-theme-text').value);
    fd.append('theme_btn_style', document.getElementById('bio-theme-style').value);
    fd.append('template_id', currentBioPage.template_id);

    fd.append('links', JSON.stringify(currentBioPage.links));

    try {
        const res = await fetch('manage_bio.php', { method: 'POST', body: fd, credentials: 'include' });
        const data = await res.json();
        if (data.success) {
            showStatus('bio', 'Página de Bio salva com sucesso!', 'success');
            currentBioPage.id = data.id;
            loadBioPages();
        } else {
            showStatus('bio', data.message, 'error');
            if (data.error === 'quota_exceeded' || data.error === 'feature_locked') {
                openPlanRestrictionModal({
                    feature: 'bio_pages',
                    title: data.error === 'quota_exceeded' ? 'Limite do plano atingido' : 'Recurso bloqueado no seu plano',
                    message: data.message || 'Seu plano atual não permite continuar esta ação.',
                    ctaText: 'Ver planos'
                });
            }
        }
    } catch (e) {
        console.error("Erro ao salvar Bio Page", e);
        showStatus('bio', 'Erro de conexão.', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalHTML;
    }
}

async function loadBioPages() {
    if (!isLoggedIn) {
        const container = document.getElementById('bioPagesList');
        if (container) {
            container.innerHTML = `<div style="grid-column: 1/-1; text-align:center; padding:40px; color:#94a3b8; background:var(--card); border-radius:20px;">Faça login para salvar, editar e listar suas Bio Pages publicadas.</div>`;
        }
        return;
    }
    const canEdit = canCurrentUserEditContent();
    if (isFeatureLocked('bio_pages')) {
        renderBioPagesDemoData();
        return;
    }
    try {
        const res = await fetch('manage_bio.php?action=list', { credentials: 'include' });
        const data = await res.json();
        const container = document.getElementById('bioPagesList');
        if (!container) return;
        container.innerHTML = '';

        if (!data.success || !data.pages || data.pages.length === 0) {
            container.innerHTML = `<div style="grid-column: 1/-1; text-align:center; padding:40px; color:#94a3b8; background:var(--card); border-radius:20px;">Você ainda não criou nenhuma página de bio.</div>`;
            return;
        }

        data.pages.forEach(page => {
            const fullUrl = `${protocol}//${domain}/bio.php?slug=${page.slug}`;
            container.innerHTML += `
                <div class="link-card">
                    <div class="link-card-header">
                        <div class="link-card-icon" style="background: ${page.theme_bg_color}; border-radius: 50%; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; color: ${page.theme_btn_color};">
                            <i class="fas fa-mobile-alt"></i>
                        </div>
                        <div class="link-card-title-group">
                            <span class="link-card-title">${page.title || 'Sem título'}</span>
                            <span class="link-card-date">/${page.slug}</span>
                        </div>
                    </div>
                    <div class="link-card-body">
                        <a href="${fullUrl}" target="_blank" class="link-card-short">${domain}/bio/${page.slug}</a>
                    </div>
                    <div class="link-card-footer">
                        <div class="link-card-actions" style="width:100%; justify-content: flex-end; gap: 10px;">
                            ${canEdit ? `<button onclick="editBioPage(${page.id})" class="btn-action btn-edit" title="Editar"><i class="fas fa-pen"></i></button>` : ''}
                            ${canEdit ? `<button onclick="deleteBioPage(${page.id})" class="btn-action btn-delete" title="Excluir"><i class="fas fa-trash"></i></button>` : ''}
                        </div>
                    </div>
                </div>
            `;
        });
    } catch (e) { console.error("Erro ao carregar bio pages", e); }
}

async function editBioPage(id) {
    try {
        const res = await fetch(`manage_bio.php?action=get&id=${id}`, { credentials: 'include' });
        const data = await res.json();
        if (data.success) {
            const page = data.page;
            currentBioPage = { id: page.id, links: page.links };

            document.getElementById('bio-slug').value = page.slug;
            document.getElementById('bio-title').value = page.title;
            document.getElementById('bio-description').value = page.description;
            document.getElementById('bio-avatar').value = page.avatar_url;
            document.getElementById('bio-theme-bg').value = page.theme_bg_color;
            document.getElementById('bio-theme-btn').value = page.theme_btn_color;
            document.getElementById('bio-theme-text').value = page.theme_text_color;
            document.getElementById('bio-theme-style').value = page.theme_btn_style;

            // Templates
            currentBioPage.template_id = page.template_id || 1;
            document.querySelectorAll('.template-option').forEach(opt => {
                opt.classList.toggle('active', opt.dataset.id == currentBioPage.template_id);
            });

            renderBioLinksEditor();
            updateBioPreview();
            window.scrollTo({ top: 0, behavior: 'smooth' });
            switchBioTab('profile');
        }
    } catch (e) { console.error("Erro ao buscar bio page", e); }
}

async function deleteBioPage(id) {
    if (!guardTeamEditPermission()) return;
    if (!confirm("Excluir esta página de bio permanentemente?")) return;
    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('id', id);
    await fetch('manage_bio.php', { method: 'POST', body: fd, credentials: 'include' });
    loadBioPages();
}

// --- BILLING / ASSINATURAS ---
let isAnnualBilling = true;

async function loadUpgradePlans() {
    try {
        const res = await fetch(`billing/api.php?action=plans&_=${Date.now()}`, { cache: 'no-store' });
        const data = await res.json();
        const container = document.getElementById('upgradePlansContainer');
        if (!container) return;

        container.innerHTML = `
            <div style="width: 100%; display: flex; justify-content: center; align-items: center; gap: 15px; margin-bottom: 40px; margin-top: 10px;">
                <span style="color: ${isAnnualBilling ? 'var(--text-dim)' : 'var(--primary)'}; font-weight: bold; font-size: 1.1rem; transition: 0.3s; cursor:pointer;" onclick="if(isAnnualBilling) toggleBillingCycle()">Mensal</span>
                <label style="position: relative; display: inline-block; width: 64px; height: 34px;">
                    <input type="checkbox" id="billingCycleToggle" ${isAnnualBilling ? 'checked' : ''} onchange="toggleBillingCycle()" style="opacity: 0; width: 0; height: 0;">
                    <span style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: var(--interactive-soft); border: 2px solid ${isAnnualBilling ? 'var(--primary)' : 'var(--text-dim)'}; transition: .4s; border-radius: 34px;">
                        <span style="position: absolute; content: ''; height: 22px; width: 22px; left: ${isAnnualBilling ? '34px' : '4px'}; bottom: 4px; background-color: ${isAnnualBilling ? 'var(--primary)' : 'var(--text-dim)'}; transition: .4s; border-radius: 50%;"></span>
                    </span>
                </label>
                <div style="display:flex; flex-direction:column; align-items:flex-start; cursor:pointer;" onclick="if(!isAnnualBilling) toggleBillingCycle()">
                    <span style="color: ${isAnnualBilling ? 'var(--primary)' : 'var(--text-dim)'}; font-weight: bold; font-size: 1.1rem; transition: 0.3s;">Anual</span>
                    <span style="background:var(--primary-dim); color:var(--primary); padding:2px 8px; border-radius:8px; font-size:0.75rem; margin-top:3px; font-weight:bold;">Ganhe 2 meses</span>
                </div>
            </div>
            
            <!-- BLOCO 1: CARDS COMPACTOS -->
            <div id="plansCardsContainer" class="pricing-cards-modern"></div>
            
            <!-- BLOCO 2: TABELA COMPARATIVA -->
            <div id="plansTableContainer" class="pricing-table-container">
                <div class="pricing-table-header">
                    <h2>Compare os Planos</h2>
                    <p>Encontre a solução perfeita para a sua necessidade e escale seus links.</p>
                </div>
                <div class="pricing-table-wrapper">
                    <table class="pricing-table" id="compareTable">
                        <thead>
                            <tr id="tableHeadRow">
                                <!-- Colunas injetadas via JS -->
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            <!-- Linhas injetadas via JS -->
                        </tbody>
                    </table>
                </div>
            </div>
        `;

        if (data.plans) {
            const pricingPlans = data.plans.map(normalizePlanForPricing);
            const cardsContainer = document.getElementById('plansCardsContainer');
            const tableHead = document.getElementById('tableHeadRow');
            const tableBody = document.getElementById('tableBody');

            // --- DEFAULTS DE PREÇO (FALLBACK) ---
            const planPrices = {
                1: { m: 0, a: 0 },
                2: { m: 29.90, a: 287.00 },
                3: { m: 99.90, a: 959.00 },
                4: { m: 0, a: 1999.00 }, // Compatibilidade legado
                5: { m: 149.90, a: 1439.00 }
            };

            // Estruturação das Funcionalidades da Tabela Comparatória
            const featuresMap = [
                { id: 'max_links', label: 'Links Curtos/mês', icon: 'fas fa-link', type: 'number', infinity_trigger: 999999, tooltip: 'Quantidade máxima de links curtos criados por ciclo mensal do plano.' },
                { id: 'max_qrs', label: 'QR Codes/mês', icon: 'fas fa-qrcode', type: 'number', infinity_trigger: 999999, tooltip: 'Quantidade máxima de QR Codes dinâmicos criados por ciclo mensal do plano.' },
                { id: 'max_link_destination_edits', label: 'Edições de destino/mês (Links)', icon: 'fas fa-pen', type: 'number', infinity_trigger: 999999, tooltip: 'Quantidade máxima de edições de destino de links por ciclo mensal.' },
                { id: 'max_qr_destination_edits', label: 'Edições de destino/mês (QR)', icon: 'fas fa-edit', type: 'number', infinity_trigger: 999999, tooltip: 'Quantidade máxima de edições de destino de QR Codes por ciclo mensal.' },
                { id: 'max_bio_pages', label: 'Bio Pages/mês', icon: 'fas fa-mobile-alt', type: 'number', infinity_trigger: 999999, tooltip: 'Quantidade máxima de Bio Pages criadas por ciclo mensal.' },
                { id: 'max_campaigns', label: 'Campanhas/mês', icon: 'fas fa-bullhorn', type: 'number', infinity_trigger: 999999, tooltip: 'Quantidade máxima de campanhas criadas por ciclo mensal.' },
                { id: 'max_subdomains', label: 'Subdomínios cadastrados', icon: 'fas fa-globe', type: 'number', tooltip: 'Quantidade máxima de subdomínios que o usuário pode registrar na conta.' },
                { id: 'max_team_members', label: 'Membros/mês (equipe)', icon: 'fas fa-users', type: 'number', infinity_trigger: 999999, tooltip: 'Quantidade máxima de membros adicionados à equipe por ciclo mensal.' },
                { id: 'max_link_dwell_monitors', label: 'Monitores de permanência (Links)', icon: 'fas fa-hourglass-half', type: 'number', infinity_trigger: 999999, tooltip: 'Quantidade máxima de links com monitoramento de permanência ativo ao mesmo tempo.' },
                { id: 'max_qr_dwell_monitors', label: 'Monitores de permanência (QR)', icon: 'fas fa-stopwatch', type: 'number', infinity_trigger: 999999, tooltip: 'Quantidade máxima de QR Codes com monitoramento de permanência ativo ao mesmo tempo.' },
                { id: 'analytics_days', label: 'Analytics detalhado', icon: 'fas fa-chart-line', type: 'days', tooltip: 'Período de histórico analítico disponível para visualizar o desempenho dos seus links.' },
                { id: 'max_clicks_monthly', label: 'Cliques por mês', icon: 'fas fa-mouse-pointer', type: 'number', infinity_trigger: 999999999, tooltip: 'Quantidade de cliques rastreados por mês. Use 999999999 para ilimitado.' },
                { id: 'custom_path', label: 'Terminação personalizada', icon: 'fas fa-keyboard', type: 'boolean_json', tooltip: 'Permite escolher livremente o slug/terminação dos links.' },
                { id: 'tracking', label: 'Tracking + Retargeting', icon: 'fas fa-bullseye', type: 'boolean_json', tooltip: 'Permite rastrear conversões internas e configurar pixels externos de retargeting por link.' },
                { id: 'dwell_monitor', label: 'Monitoramento de Permanência', icon: 'fas fa-hourglass-half', type: 'boolean_json', tooltip: 'Permite ativar medição de tempo de permanência por link/QR.' },
                { id: 'redirect', label: 'Redirecionamento de links', icon: 'fas fa-random', type: 'boolean_json', tooltip: 'Permite alterar o destino de um link ou QR Code já criado sem recriar o item.' },
                { id: 'deep_linking', label: 'Deep Linking (Apps)', icon: 'fas fa-external-link-alt', type: 'boolean_json', tooltip: 'Abre automaticamente aplicativos instalados (Instagram, WhatsApp, etc).' },
                { id: 'bulk_utm', label: 'UTM Builder em Massa', icon: 'fas fa-layer-group', type: 'boolean_json', tooltip: 'Permite criar e gerenciar campanhas e parâmetros UTM em massa.' },
                { id: 'ab_testing', label: 'Testes A/B', icon: 'fas fa-flask', type: 'boolean_json', tooltip: 'Permite criar variantes para testes de performance por link.' },
                { id: 'rules', label: 'Regras Avançadas', icon: 'fas fa-magic', type: 'boolean_json', tooltip: 'Automatiza comportamento de links por regras.' },
                { id: 'team', label: 'Colaboração em Equipe', icon: 'fas fa-user-friends', type: 'boolean_json', tooltip: 'Permite convidar membros para trabalhar na mesma conta/equipe.' },
                { id: 'folders_tags', label: 'Pastas e Tags', icon: 'fas fa-folder-tree', type: 'boolean_json', tooltip: 'Permite organizar links e QR Codes em pastas e tags.' },
                { id: 'social_preview', label: 'Preview Social', icon: 'fas fa-share-nodes', type: 'boolean_json', tooltip: 'Permite personalizar título, descrição, imagem e favicon do preview social.' },
                { id: 'premium_bio_templates', label: 'Templates Premium da Bio', icon: 'fas fa-palette', type: 'boolean_json', tooltip: 'Permite usar templates premium no construtor de Bio Pages.' },
                { id: 'bio_form_exports', label: 'Exportação de Formulários', icon: 'fas fa-file-export', type: 'boolean_json', tooltip: 'Permite exportar respostas de formulários da Bio em CSV/XLSX.' },
                { id: 'ai_tutor', label: 'Tutor IA', icon: 'fas fa-robot', type: 'boolean_json', tooltip: 'Acesso ao assistente Tutor IA dentro do produto.' },
                { id: 'priority_support', label: 'Suporte Prioritário', icon: 'fas fa-headset', type: 'boolean_json', tooltip: 'Atendimento preferencial por nossa equipe técnica.' }
            ];

            tableHead.innerHTML = '<th class="feature-col">RECURSOS & LIMITES</th>';

            pricingPlans.forEach(p => {
                const priceM = parseFloat(p.monthly_price) > 0 ? parseFloat(p.monthly_price) : (planPrices[p.id]?.m || 0);
                const priceA = parseFloat(p.annual_price) > 0 ? parseFloat(p.annual_price) : (planPrices[p.id]?.a || 0);

                let activePrice = isAnnualBilling ? (priceA / 12) : priceM;
                let displayPrice = '';
                let cyclesText = '';
                const normalizedPlanName = String(p.name || '').toLowerCase();
                let isFounder = normalizedPlanName.includes('founder');
                let isPopular = p.id == 3;

                // Montagem PREÇO
                if (p.id == 1) {
                    displayPrice = 'Grátis';
                    cyclesText = '&nbsp;';
                } else {
                    displayPrice = `<span class="pricing-card-currency">R$</span> ${activePrice.toFixed(2).replace('.', ',')}`;
                    if (isFounder) {
                        cyclesText = isAnnualBilling
                            ? `Preço congelado · R$ ${priceA.toFixed(2).replace('.', ',')} cobrado anualmente`
                            : 'Preço congelado — sem reajustes futuros · cobrado mensalmente';
                    } else {
                        cyclesText = isAnnualBilling ? `R$ ${priceA.toFixed(2).replace('.', ',')} cobrado anualmente` : 'Cobrado mensalmente';
                    }
                }

                // Montagem BOTÃO CTA
                let btnStr = `<button class="btn-primary" onclick="initCheckout(${p.id}, '${isAnnualBilling ? 'annual' : 'monthly'}')" style="margin-top:auto; padding: 15px; border-radius: 12px; width:100%;">Assinar ${p.name.split(' ')[0]}</button>`;
                if (p.id == 1) btnStr = '<button class="btn-outline" disabled style="margin-top:auto; width:100%; border-color:var(--text-dim); color:var(--text-dim);">Plano Atual</button>';

                if (isFounder) {
                    if (data.founder_slots && data.founder_slots.available <= 0) {
                        btnStr = '<button class="btn-outline" disabled style="margin-top:auto; width:100%; border-color:#ef4444; color:#ef4444;">Vagas Esgotadas</button>';
                    } else {
                        btnStr = `<button class="btn-primary" onclick="initCheckout(${p.id}, '${isAnnualBilling ? 'annual' : 'monthly'}')" style="background:#fcd34d; color:#000; margin-top:auto; padding: 15px; border-radius: 12px; width:100%; box-shadow: 0 10px 25px rgba(252, 211, 77, 0.4);">Garantir minha vaga (${data.founder_slots?.available || 0} restantes)</button>`;
                    }
                }

                // RENDERING DO CARTÃO COMPACTO
                let cardClass = "pricing-card-compact";
                if (isPopular) cardClass += " pricing-card-popular";
                if (isFounder) cardClass += " pricing-card-founder";

                let badgeHtml = '';
                if (isPopular) badgeHtml = '<div class="pricing-card-badge">MAIS POPULAR</div>';
                if (isFounder) badgeHtml = '<div class="pricing-card-badge pricing-card-badge-founder"><i class="fas fa-lock"></i> PREÇO TRAVADO</div>';

                const planFeatureFlags = safeParsePlanFeatures(p.features_json);
                const analyticsDays = getPlanAnalyticsDays(p);
                cardsContainer.innerHTML += `
                    <div class="${cardClass}">
                        ${badgeHtml}
                        <h3 class="pricing-card-title" style="color: ${isFounder ? '#fcd34d' : 'var(--primary)'};">${p.name}</h3>
                        
                        <div class="pricing-card-price-area">
                            <div class="pricing-card-price">${displayPrice} ${p.id !== 1 ? '<span class="pricing-card-period">/mês</span>' : ''}</div>
                            <div class="pricing-card-subtext" style="color: ${(isAnnualBilling || isFounder) && p.id !== 1 ? 'var(--primary)' : 'var(--text-dim)'}; font-weight: ${(isAnnualBilling || isFounder) ? '600' : '400'};">${cyclesText}</div>
                        </div>
                        
                        <ul class="pricing-card-features-mini">
                            ${getPlanLimitListItem(p.max_links, {
                    infinityTrigger: 999999,
                    labelUnavailable: 'Sem Links',
                    labelUnitSingular: 'Link',
                    labelUnitPlural: 'Links',
                    unlimitedPrefix: 'Links'
                })}
                            ${getPlanLimitListItem(p.max_qrs, {
                    infinityTrigger: 999999,
                    labelUnavailable: 'Sem QR Codes',
                    labelUnitSingular: 'QR Code',
                    labelUnitPlural: 'QR Codes',
                    unlimitedPrefix: 'QR Codes'
                })}
                            ${getPlanLimitListItem(p.max_link_destination_edits, {
                    infinityTrigger: 999999,
                    labelUnavailable: 'Sem edição de destino (Links)',
                    labelUnitSingular: 'edição de destino (Links)',
                    labelUnitPlural: 'edições de destino (Links)',
                    unlimitedPrefix: 'Edições de destino (Links)'
                })}
                            ${getPlanLimitListItem(p.max_qr_destination_edits, {
                    infinityTrigger: 999999,
                    labelUnavailable: 'Sem edição de destino (QR)',
                    labelUnitSingular: 'edição de destino (QR)',
                    labelUnitPlural: 'edições de destino (QR)',
                    unlimitedPrefix: 'Edições de destino (QR)'
                })}
                            ${analyticsDays > 0
                        ? `<li><i class="fas fa-check"></i> <b>${formatAnalyticsDays(analyticsDays)} de Analytics</b></li>`
                        : '<li><i class="fas fa-times" style="opacity:0.3"></i> Sem Analytics</li>'}
                            ${getPlanLimitListItem(parseInt(p.max_subdomains, 10), {
                            labelUnavailable: 'Sem Subdomínio',
                            labelUnitSingular: 'Subdomínio',
                            labelUnitPlural: 'Subdomínios'
                        })}
                            ${getPlanLimitListItem(parseInt(p.max_team_members, 10), {
                            labelUnavailable: 'Sem equipe',
                            labelUnitSingular: 'Membro de equipe',
                            labelUnitPlural: 'Membros de equipe'
                        })}
                            <li><i class="${planFeatureFlags.tracking === true ? 'fas fa-check' : 'fas fa-times'}" style="${planFeatureFlags.tracking === true ? '' : 'opacity:0.3'}"></i> Tracking/Pixels</li>
                            <li><i class="${planFeatureFlags.bulk_utm === true ? 'fas fa-check' : 'fas fa-times'}" style="${planFeatureFlags.bulk_utm === true ? '' : 'opacity:0.3'}"></i> UTM em Massa</li>
                            <li><i class="${planFeatureFlags.ab_testing === true ? 'fas fa-check' : 'fas fa-times'}" style="${planFeatureFlags.ab_testing === true ? '' : 'opacity:0.3'}"></i> Testes A/B</li>
                            <li><i class="${planFeatureFlags.rules === true ? 'fas fa-check' : 'fas fa-times'}" style="${planFeatureFlags.rules === true ? '' : 'opacity:0.3'}"></i> Regras Avançadas</li>
                        </ul>
                        
                        ${btnStr}
                    </div>
                `;

                // ADICIONAR COLUNA NO CABEÇALHO DA TABELA
                tableHead.innerHTML += `<th style="color: ${isFounder ? '#fcd34d' : (isPopular ? 'var(--primary)' : 'var(--text-soft)')}">${p.name}</th>`;
            });

            featuresMap.forEach(feat => {
                let row = document.createElement('tr');
                let th = document.createElement('td');
                th.className = 'feature-name';
                let labelHtml = feat.icon ? `<i class="${feat.icon}" style="opacity:0.5; margin-right:8px; width:20px; text-align:center;"></i> ${feat.label}` : feat.label;
                if (feat.tooltip) {
                    labelHtml += ` <span class="feature-tooltip-trigger" title="${feat.tooltip}"><i class="far fa-question-circle" style="font-size:0.8em; opacity:0.6;"></i></span>`;
                }
                th.innerHTML = labelHtml;
                th.setAttribute('data-label', 'Recurso');
                row.appendChild(th);

                pricingPlans.forEach((p, pIdx) => {
                    let td = document.createElement('td');
                    td.setAttribute('data-label', p.name);
                    let cellVal = '';

                    // Tratar os dados baseados no tipo
                    if (feat.type === 'number') {
                        let val = p[feat.id];
                        if (feat.id === 'max_clicks_monthly' && getPlanAnalyticsDays(p) <= 0) {
                            cellVal = '<span style="opacity:0.3">-</span>';
                        } else
                            if (val == feat.infinity_trigger || val >= 999999) {
                                cellVal = '<span class="unlimited-badge">Ilimitado</span>';
                            } else {
                                if (!isPlanLimitAvailable(val, feat.infinity_trigger)) {
                                    cellVal = '<i class="fas fa-times cross-icon"></i>';
                                } else if (feat.id === 'max_clicks_monthly' && val >= 1000) {
                                    cellVal = (val / 1000).toLocaleString('pt-BR') + 'k';
                                } else {
                                    cellVal = val.toLocaleString('pt-BR');
                                }
                            }
                    }
                    else if (feat.type === 'unlimited') {
                        cellVal = '<span class="unlimited-badge">Ilimitado</span>';
                    }
                    else if (feat.type === 'days') {
                        let days = getPlanAnalyticsDays(p);
                        if (days <= 0) cellVal = '<span style="opacity:0.3">-</span>';
                        else cellVal = formatAnalyticsDays(days);
                    }
                    else if (feat.type === 'boolean_json') {
                        let parsedJson = {};
                        try { parsedJson = JSON.parse(p.features_json) || {}; } catch (e) { }
                        const hasFeature = parsedJson[feat.id] === true;

                        if (hasFeature) {
                            cellVal = '<i class="fas fa-check check-icon"></i>';
                        } else {
                            cellVal = '<i class="fas fa-times cross-icon"></i>';
                        }
                    }

                    td.innerHTML = cellVal;
                    row.appendChild(td);
                });

                tableBody.appendChild(row);
            });
        }
    } catch (e) {
        console.error("Erro ao carregar os planos:", e);
    }
}

function toggleBillingCycle() {
    isAnnualBilling = !isAnnualBilling;
    loadUpgradePlans();
}

let currentCheckoutData = { planId: null, cycle: 'monthly' };

function goToPostSubscriptionWelcome() {
    window.location.href = new URL('boasvindas', window.location.href).href;
}

async function initiateCheckout(planId, cycle = 'monthly') {
    currentCheckoutData = { planId, cycle };

    // Tenta carregar dados salvos do usuário (se logado)
    try {
        const res = await fetch('checkout.php?action=get_customer_info');
        const data = await res.json();
        if (data.success && data.info) {
            if (data.info.billing_name) document.getElementById('checkoutBillingName').value = data.info.billing_name;
            if (data.info.cpf_cnpj) document.getElementById('checkoutCpfCnpj').value = data.info.cpf_cnpj;
            if (data.info.postal_code) document.getElementById('checkoutCEP').value = data.info.postal_code;
            if (data.info.address) document.getElementById('checkoutAddr').value = data.info.address;
            if (data.info.address_number) document.getElementById('checkoutAddrNum').value = data.info.address_number;
            if (data.info.complement) document.getElementById('checkoutAddrComp').value = data.info.complement;
            if (data.info.province) document.getElementById('checkoutBairro').value = data.info.province;
            if (data.info.city) document.getElementById('checkoutCidade').value = data.info.city;
            if (data.info.state) document.getElementById('checkoutUF').value = data.info.state;
        }
    } catch (e) { }

    // Reseta o modal para o passo 1
    goToCheckoutStep(1);
    document.getElementById('checkout-proration-info').classList.add('hidden');
    document.getElementById('checkout-success-area').classList.add('hidden');
    document.getElementById('pixQrArea').classList.add('hidden');
    document.getElementById('pixInitArea').classList.remove('hidden');
    document.getElementById('cardCheckoutForm').reset();

    openModal('modalCheckout');
    applyCheckoutMasks();
}

function goToCheckoutStep(step) {
    document.querySelectorAll('.checkout-step').forEach(s => s.classList.add('hidden'));
    document.getElementById(`checkout-step-${step}`).classList.remove('hidden');

    // Atualiza indicadores
    for (let i = 1; i <= 3; i++) {
        const ind = document.getElementById(`step${i}-indicator`);
        if (i <= step) {
            ind.style.opacity = "1";
            if (i === step) ind.querySelector('span').style.background = "var(--primary)";
        } else {
            ind.style.opacity = "0.3";
            ind.querySelector('span').style.background = "#475569";
        }
    }

    if (step === 3) {
        updateUpgradeDetails();
    }
}

async function updateUpgradeDetails() {
    const infoBlock = document.getElementById('checkout-proration-info');
    if (!infoBlock) return;
    infoBlock.classList.add('hidden'); // Esconde por padrão

    try {
        const query = `action=get_upgrade_details&plan_id=${currentCheckoutData.planId}&cycle=${currentCheckoutData.cycle}`;
        const res = await fetch(`checkout.php?${query}`, { credentials: 'include' });
        const data = await res.json();

        if (data.success && data.details.is_upgrade) {
            document.getElementById('proration-original-price').innerText = `R$ ${data.details.original_price.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}`;
            document.getElementById('proration-credit-amount').innerText = `- R$ ${data.details.credit_amount.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}`;
            document.getElementById('proration-final-amount').innerText = `R$ ${data.details.final_amount.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}`;
            infoBlock.classList.remove('hidden');
        }
    } catch (e) {
        console.error("Erro ao carregar detalhes de upgrade", e);
    }
}

async function lookupCepAsaas() {
    const cep = document.getElementById('checkoutCEP').value.replace(/\D/g, '');
    if (cep.length !== 8) return;

    const btn = document.getElementById('checkoutCEP');
    btn.style.borderColor = "var(--primary)";

    try {
        const res = await fetch(`https://viacep.com.br/ws/${cep}/json/`);
        const data = await res.json();
        if (!data.erro) {
            document.getElementById('checkoutAddr').value = data.logradouro;
            document.getElementById('checkoutBairro').value = data.bairro;
            document.getElementById('checkoutCidade').value = data.localidade;
            document.getElementById('checkoutUF').value = data.uf;
            document.getElementById('checkoutAddrNum').focus();
        }
    } catch (e) {
        console.error("Erro ao buscar CEP");
    }
}

function applyCheckoutMasks() {
    // CPF/CNPJ
    const cpfInput = document.getElementById('checkoutCpfCnpj');
    cpfInput.addEventListener('input', (e) => {
        let v = e.target.value.replace(/\D/g, '');
        if (v.length <= 11) {
            v = v.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, "$1.$2.$3-$4");
        } else {
            v = v.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, "$1.$2.$3/$4-$5");
        }
        e.target.value = v;
    });

    // CEP
    const cepInput = document.getElementById('checkoutCEP');
    cepInput.addEventListener('input', (e) => {
        let v = e.target.value.replace(/\D/g, '');
        v = v.replace(/(\d{5})(\d{3})/, "$1-$2");
        e.target.value = v;
    });

    // Iniciais: Se ja tem CEP, busca
    if (cepInput.value.length >= 8) lookupCepAsaas();
}

// Fallback para manter compatibilidade com botões que chamam initCheckout
async function initCheckout(planId, cycle = 'monthly') {
    return initiateCheckout(planId, cycle);
}

function switchCheckoutTab(tab) {
    document.querySelectorAll('.checkout-tab-content').forEach(c => c.classList.add('hidden'));
    document.querySelectorAll('.checkout-tabs button').forEach(b => b.classList.remove('active'));

    document.getElementById(`checkout-${tab}`).classList.remove('hidden');
    document.getElementById(`tabCheckout${tab.charAt(0).toUpperCase() + tab.slice(1)}`).classList.add('active');
}

async function generateAsaasPayment(method) {
    const billingName = document.getElementById('checkoutBillingName').value;
    const cpfCnpj = document.getElementById('checkoutCpfCnpj').value.replace(/\D/g, '');
    const cep = document.getElementById('checkoutCEP').value.replace(/\D/g, '');
    const address = document.getElementById('checkoutAddr').value;
    const number = document.getElementById('checkoutAddrNum').value;
    const complement = document.getElementById('checkoutAddrComp').value;
    const province = document.getElementById('checkoutBairro').value;
    const city = document.getElementById('checkoutCidade').value;
    const state = document.getElementById('checkoutUF').value;

    if (!billingName || !cpfCnpj || !cep || !address || !number || !province || !city || !state) {
        Swal.fire('Atenção', 'Preencha todos os campos obrigatórios nos passos anteriores.', 'warning');
        return;
    }

    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Gerando...';
    btn.disabled = true;

    try {
        const payload = {
            plan_id: currentCheckoutData.planId,
            cycle: currentCheckoutData.cycle,
            method: method,
            billingName: billingName,
            cpfCnpj: cpfCnpj,
            postalCode: cep,
            address: address,
            addressNumber: number,
            complement: complement,
            province: province,
            city: city,
            state: state
        };

        const res = await fetch('checkout.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
            credentials: 'include'
        });
        const data = await res.json();

        if (data.success) {
            if (method === 'PIX') {
                document.getElementById('pixQrCodeImg').src = `data:image/png;base64,${data.pix.encodedImage}`;
                document.getElementById('pixCopyPaste').value = data.pix.payload;
                document.getElementById('pixInitArea').classList.add('hidden');
                document.getElementById('pixQrArea').classList.remove('hidden');

                // Inicia o polling para verificar pagamento
                startPaymentPolling(data.paymentId);
            }
        } else {
            Swal.fire('Erro', data.message || 'Erro ao gerar pagamento.', 'error');
        }
    } catch (e) {
        Swal.fire('Erro', 'Conexão falhou ao gerar pagamento.', 'error');
    } finally {
        btn.innerHTML = originalText;
        btn.disabled = false;
    }
}

async function handleCardPayment(e) {
    e.preventDefault();

    const billingName = document.getElementById('checkoutBillingName').value;
    const cpfCnpj = document.getElementById('checkoutCpfCnpj').value.replace(/\D/g, '');
    const cep = document.getElementById('checkoutCEP').value.replace(/\D/g, '');
    const address = document.getElementById('checkoutAddr').value;
    const number = document.getElementById('checkoutAddrNum').value;
    const complement = document.getElementById('checkoutAddrComp').value;
    const province = document.getElementById('checkoutBairro').value;
    const city = document.getElementById('checkoutCidade').value;
    const state = document.getElementById('checkoutUF').value;

    if (!billingName || !cpfCnpj || !cep || !address || !number || !province || !city || !state) {
        Swal.fire('Atenção', 'Certifique-se de preencher todos os dados nos passos anteriores.', 'warning');
        return;
    }

    const btn = document.getElementById('btnPayCard');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processando...';
    btn.disabled = true;

    const cardNum = document.getElementById('cardNumber').value.replace(/\s/g, '');
    const cardExp = document.getElementById('cardExpiry').value.trim();
    const cardCvc = document.getElementById('cardCvc').value.trim();

    if (!cardNum || !cardExp || !cardCvc) {
        Swal.fire('Atenção', 'Por favor, preencha todos os dados do cartão de crédito.', 'warning');
        btn.innerHTML = originalText;
        btn.disabled = false;
        return;
    }

    try {
        const payload = {
            plan_id: currentCheckoutData.planId,
            cycle: currentCheckoutData.cycle,
            method: 'CREDIT_CARD',
            holderName: billingName,
            number: cardNum,
            expiry: cardExp,
            cvc: cardCvc,
            billingName: billingName,
            cpfCnpj: cpfCnpj,
            postalCode: cep,
            address: address,
            addressNumber: number,
            complement: complement,
            province: province,
            city: city,
            state: state
        };

        const res = await fetch('checkout.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
            credentials: 'include'
        });
        const data = await res.json();

        if (data.success) {
            goToPostSubscriptionWelcome();
            return;
        } else {
            Swal.fire('Erro no Cartão', data.message || 'Cartão recusado ou erro no processamento.', 'error');
        }
    } catch (e) {
        Swal.fire('Erro', 'Falha na comunicação com o gateway.', 'error');
    } finally {
        btn.innerHTML = originalText;
        btn.disabled = false;
    }
}

function copyPixCode() {
    const input = document.getElementById('pixCopyPaste');
    input.select();
    document.execCommand('copy');
    Swal.fire({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        icon: 'success',
        title: 'Código Pix copiado!'
    });
}

function startPaymentPolling(paymentId) {
    const interval = setInterval(async () => {
        try {
            const res = await fetch(`checkout.php?action=check&id=${paymentId}`);
            const data = await res.json();
            if (data.confirmed) {
                clearInterval(interval);
                goToPostSubscriptionWelcome();
            }
        } catch (e) {
            console.error("Erro no polling", e);
        }
    }, 5000);

    // Limpa o polling se o modal for fechado
    const originalClose = closeModals;
    window.closeModals = function () {
        clearInterval(interval);
        originalClose();
        window.closeModals = originalClose; // Restaura
    };
}

async function loadSubscriptionTab() {
    if (!isLoggedIn) {
        const nameEl = document.getElementById('subPlanName');
        const descEl = document.getElementById('subPlanDesc');
        if (nameEl) nameEl.innerText = 'Visitante';
        if (descEl) descEl.innerHTML = 'Faça login para gerenciar sua assinatura e ver seus limites.';
        return;
    }
    try {
        const res = await fetch('billing/api.php?action=status', { credentials: 'include' });
        const data = await res.json();
        if (data.success && data.data) {
            document.getElementById('subPlanName').innerText = data.data.plan_name || 'Start (Free)';

            let statusBadge = '';
            if (data.data.billing_status === 'active') {
                statusBadge = '<span style="background:var(--primary-surface-strong); color:var(--primary); padding:5px 10px; border-radius:10px; font-weight:bold;">ATIVA</span>';
            } else {
                statusBadge = '<span style="background:rgba(148,163,184,0.1); color:#94a3b8; padding:5px 10px; border-radius:10px; font-weight:bold;">FREEMIUM</span>';
            }
            document.getElementById('subPlanDesc').innerHTML = `Status da Assinatura: ${statusBadge}`;
        }
    } catch (e) { }
}

function formatCurrencyBRL(value) {
    return Number(value || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

function createAffiliateChart(canvasId, key, config) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    if (charts[key]) charts[key].destroy();
    charts[key] = new Chart(ctx, config);
}

function copyAffiliateLink() {
    const input = document.getElementById('affiliateLinkInput');
    if (!input || !input.value) {
        alert('Link de afiliado indisponível no momento.');
        return;
    }
    navigator.clipboard.writeText(input.value)
        .then(() => {
            announceStatus('Link de afiliado copiado.');
        })
        .catch(() => {
            input.select();
            document.execCommand('copy');
            announceStatus('Link de afiliado copiado.');
        });
}

async function loadAffiliateDashboard() {
    if (!isLoggedIn || !currentUserIsAffiliate) {
        const body = document.getElementById('affiliateCommissionsTable');
        if (body) {
            body.innerHTML = '<tr><td colspan="7" class="dashboard-empty">Acesso permitido apenas para afiliados.</td></tr>';
        }
        return;
    }

    try {
        const res = await fetch('affiliate_dashboard.php?t=' + Date.now(), { credentials: 'include' });
        const data = await res.json();
        if (!data.success) {
            alert(data.message || 'Não foi possível carregar o painel de afiliado.');
            return;
        }

        const affiliate = data.affiliate || {};
        const totals = data.totals || {};
        const plans = Array.isArray(data.active_users_by_plan) ? data.active_users_by_plan : [];
        const countries = Array.isArray(data.geo_countries) ? data.geo_countries : [];
        const cities = Array.isArray(data.geo_cities) ? data.geo_cities : [];
        const forecast = Array.isArray(data.estimated_next_months) ? data.estimated_next_months : [];
        const commissions = Array.isArray(data.recent_commissions) ? data.recent_commissions : [];

        currentAffiliateLink = affiliate.link || currentAffiliateLink || '';
        const affiliateInput = document.getElementById('affiliateLinkInput');
        if (affiliateInput) affiliateInput.value = currentAffiliateLink;

        const setText = (id, value) => {
            const el = document.getElementById(id);
            if (el) el.textContent = value;
        };
        setText('affTotalSignups', Number(totals.total_signups || 0).toLocaleString('pt-BR'));
        setText('affActiveUsers', Number(totals.total_active_paid_users || 0).toLocaleString('pt-BR'));
        setText('affPendingCommission', formatCurrencyBRL(totals.pending_commission || 0));
        setText('affReleasedCommission', formatCurrencyBRL(totals.released_commission || 0));

        createAffiliateChart('affChartPlans', 'affPlans', {
            type: 'bar',
            data: {
                labels: plans.map((row) => row.plan_name || 'Plano'),
                datasets: [{
                    label: 'Usuários ativos',
                    data: plans.map((row) => Number(row.total || 0)),
                    backgroundColor: hexToRgba(getBrandTokens().accent, 0.85),
                    borderRadius: 8
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
        });

        createAffiliateChart('affChartCountries', 'affCountries', {
            type: 'doughnut',
            data: {
                labels: countries.map((row) => row.label || 'N/D'),
                datasets: [{
                    data: countries.map((row) => Number(row.total || 0)),
                    backgroundColor: chartVisualTheme.colors.slice(0, 6),
                    borderWidth: 0
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } }, cutout: '70%' }
        });

        createAffiliateChart('affChartCities', 'affCities', {
            type: 'bar',
            data: {
                labels: cities.map((row) => row.label || 'N/D'),
                datasets: [{
                    label: 'Indicados',
                    data: cities.map((row) => Number(row.total || 0)),
                    backgroundColor: '#f6b64f',
                    borderRadius: 8
                }]
            },
            options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
        });

        createAffiliateChart('affChartForecast', 'affForecast', {
            type: 'line',
            data: {
                labels: forecast.map((row) => {
                    const d = new Date(String(row.month_key) + 'T00:00:00');
                    return d.toLocaleDateString('pt-BR', { month: 'short', year: 'numeric' });
                }),
                datasets: [{
                    label: 'Comissão estimada (R$)',
                    data: forecast.map((row) => Number(row.total || 0)),
                    borderColor: getBrandTokens().primary,
                    backgroundColor: getBrandTokens().chartFillPrimary,
                    fill: true,
                    tension: 0.35
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
        });

        const tableBody = document.getElementById('affiliateCommissionsTable');
        if (tableBody) {
            if (commissions.length === 0) {
                tableBody.innerHTML = '<tr><td colspan="7" class="dashboard-empty">Ainda não há comissões registradas.</td></tr>';
            } else {
                tableBody.innerHTML = commissions.map((row) => {
                    const releaseDate = row.expected_release_at
                        ? new Date(String(row.expected_release_at).replace(' ', 'T')).toLocaleDateString('pt-BR')
                        : '-';
                    return `
                        <tr class="dashboard-activity-row">
                            <td>${row.asaas_payment_id || '-'}</td>
                            <td>${row.payment_method || '-'}</td>
                            <td>#${Number(row.installment_number || 1)}</td>
                            <td>${formatCurrencyBRL(row.gross_amount || 0)}</td>
                            <td>${formatCurrencyBRL(row.commission_amount || 0)} (${Number(row.commission_rate || 0)}%)</td>
                            <td>${releaseDate}</td>
                            <td>${row.payout_status || 'pending'}</td>
                        </tr>
                    `;
                }).join('');
            }
        }
    } catch (e) {
        console.error('Erro ao carregar painel afiliado:', e);
        const tableBody = document.getElementById('affiliateCommissionsTable');
        if (tableBody) {
            tableBody.innerHTML = '<tr><td colspan="7" class="dashboard-empty">Erro de conexão ao carregar dados.</td></tr>';
        }
    }
}

// --- ADMIN SYSTEM ---
function switchAdminTab(tab) {
    if (tab === 'global-dashboard' && !currentUserIsMasterAdminDashboard) {
        alert('Acesso negado a este dashboard.');
        return;
    }

    document.querySelectorAll('#section-admin .bio-tab-content').forEach(c => c.classList.add('hidden'));
    document.querySelectorAll('#section-admin .bio-tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById(`admin-tab-${tab}`).classList.remove('hidden');

    // Mapeamento correto do índice do botão para o hover/active
    const btns = document.querySelectorAll('#section-admin .bio-tab-btn');
    btns.forEach(btn => {
        if (btn.getAttribute('onclick').includes(tab)) btn.classList.add('active');
    });

    if (tab === 'plans') loadAdminPlans();
    if (tab === 'matrix') loadAdminMatrix();
    if (tab === 'global') loadAdminConfig();
    if (tab === 'affiliates') loadAdminAffiliatesDashboard();
    if (tab === 'marketing-forms') loadAdminMarketingFormResponses();
    if (tab === 'global-dashboard') loadAdminGlobalDashboard();
}

async function loadAdminMarketingFormResponses() {
    if (!isLoggedIn || !currentUserIsAdmin) return;
    const tbody = document.getElementById('adminMarketingFormsTable');
    if (!tbody) return;

    tbody.innerHTML = '<tr><td colspan="8" class="dashboard-empty">Carregando respostas...</td></tr>';

    try {
        const res = await fetch(`admin_api.php?action=marketing_form_responses&t=${Date.now()}`, { credentials: 'include' });
        const data = await res.json();
        if (!data.success) {
            tbody.innerHTML = `<tr><td colspan="8" class="dashboard-empty">${data.message || 'Erro ao carregar respostas.'}</td></tr>`;
            return;
        }

        const rows = Array.isArray(data.data) ? data.data : [];
        if (rows.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" class="dashboard-empty">Nenhuma resposta recebida até o momento.</td></tr>';
            return;
        }

        tbody.innerHTML = rows.map((row) => {
            const id = Number(row.id || 0);
            const dateText = row.created_at
                ? new Date(String(row.created_at).replace(' ', 'T')).toLocaleDateString('pt-BR', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                })
                : '-';
            const safeRequest = String(row.request_text || '-').replace(/</g, '&lt;').replace(/>/g, '&gt;');
            return `
                <tr class="dashboard-activity-row">
                    <td>${id}</td>
                    <td>${row.name || '-'}</td>
                    <td>${row.email || '-'}</td>
                    <td>${row.whatsapp || '-'}</td>
                    <td style="max-width:340px; white-space:normal;">${safeRequest}</td>
                    <td>${row.source_page || 'marketing.php'}</td>
                    <td>${row.submitter_ip || '-'}</td>
                    <td>${dateText}</td>
                </tr>
            `;
        }).join('');
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="8" class="dashboard-empty">Erro de conexão ao carregar respostas.</td></tr>';
    }
}

function renderAdminGlobalList(elementId, rows, emptyLabel = 'Sem dados.') {
    const el = document.getElementById(elementId);
    if (!el) return;
    const list = Array.isArray(rows) ? rows : [];
    if (list.length === 0) {
        el.innerHTML = `<li>${emptyLabel}</li>`;
        return;
    }
    el.innerHTML = list.map((item) => {
        const label = item.label || item.plan_name || 'Não identificado';
        const total = Number(item.total || 0).toLocaleString('pt-BR');
        return `<li><strong>${label}</strong>: ${total}</li>`;
    }).join('');
}

function createAdminGlobalChart(canvasId, key, config) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    if (charts[key]) charts[key].destroy();
    charts[key] = new Chart(ctx, config);
}

async function loadAdminGlobalDashboard() {
    if (!isLoggedIn || !currentUserIsMasterAdminDashboard) {
        return;
    }
    try {
        const period = document.getElementById('adminGlobalPeriod')?.value || '24h';
        const res = await fetch(`admin_dashboard.php?period=${encodeURIComponent(period)}&t=${Date.now()}`, { credentials: 'include' });
        const data = await res.json();
        if (!data.success) {
            alert(data.message || 'Não foi possível carregar o dashboard global.');
            return;
        }

        const totals = data.totals || {};
        const totalUsers = Number(totals.total_users || 0).toLocaleString('pt-BR');
        const totalClicks = Number(totals.total_clicks || 0).toLocaleString('pt-BR');
        const clicks24h = Number(totals.clicks_24h || 0).toLocaleString('pt-BR');
        const totalLinks = Number(totals.total_links || 0).toLocaleString('pt-BR');
        const totalQrcodes = Number(totals.total_qrcodes || 0).toLocaleString('pt-BR');
        const totalBiopages = Number(totals.total_biopages || 0).toLocaleString('pt-BR');

        const usersEl = document.getElementById('adminGlobalTotalUsers');
        const clicksEl = document.getElementById('adminGlobalTotalClicks');
        const clicks24hEl = document.getElementById('adminGlobalClicks24h');
        const assetsEl = document.getElementById('adminGlobalAssets');

        if (usersEl) usersEl.textContent = totalUsers;
        if (clicksEl) clicksEl.textContent = totalClicks;
        if (clicks24hEl) clicks24hEl.textContent = clicks24h;
        if (assetsEl) assetsEl.textContent = `${totalLinks} / ${totalQrcodes} / ${totalBiopages}`;

        const trendRows = Array.isArray(data.clicks_trend) ? data.clicks_trend : [];
        createAdminGlobalChart('adminGlobalChartTrend', 'adminGlobalTrend', {
            type: 'line',
            data: {
                labels: trendRows.map((row) => {
                    // Use T00:00:00 only if time is missing to force local timezone and avoid 'Invalid Date'
                    const dateStr = row.day.includes(' ') ? row.day.replace(' ', 'T') : `${row.day}T00:00:00`;
                    const date = new Date(dateStr);
                    if (row.day.includes(' ')) {
                        return date.getHours().toString().padStart(2, '0') + ':00';
                    }
                    return date.toLocaleDateString('pt-BR', { day: '2-digit', month: 'short' });
                }),
                datasets: [{
                    label: 'Cliques',
                    data: trendRows.map((row) => Number(row.total || 0)),
                    borderColor: getBrandTokens().primary,
                    backgroundColor: getBrandTokens().chartFillPrimary,
                    fill: true,
                    tension: 0.35
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
        });

        const sourceRows = Array.isArray(data.traffic_source_type) ? data.traffic_source_type : [];
        createAdminGlobalChart('adminGlobalChartSourceType', 'adminGlobalSourceType', {
            type: 'doughnut',
            data: {
                labels: sourceRows.map((row) => String(row.label || '').toUpperCase()),
                datasets: [{
                    data: sourceRows.map((row) => Number(row.total || 0)),
                    backgroundColor: chartVisualTheme.colors.slice(0, 4),
                    borderWidth: 0
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } }, cutout: '72%' }
        });

        const usersByPlanRows = Array.isArray(data.users_by_plan) ? data.users_by_plan : [];
        createAdminGlobalChart('adminGlobalChartUsersByPlan', 'adminGlobalUsersByPlan', {
            type: 'bar',
            data: {
                labels: usersByPlanRows.map((row) => row.plan_name || 'Plano'),
                datasets: [{
                    label: 'Usuários',
                    data: usersByPlanRows.map((row) => Number(row.total || 0)),
                    backgroundColor: hexToRgba(getBrandTokens().accent, 0.85),
                    borderRadius: 8
                }]
            },
            options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
        });

        const activePlanRows = Array.isArray(data.active_users_by_plan) ? data.active_users_by_plan : [];
        createAdminGlobalChart('adminGlobalChartActiveUsersByPlan', 'adminGlobalActiveUsersByPlan', {
            type: 'bar',
            data: {
                labels: activePlanRows.map((row) => row.plan_name || 'Plano'),
                datasets: [{
                    label: 'Ativos',
                    data: activePlanRows.map((row) => Number(row.total || 0)),
                    backgroundColor: hexToRgba(getBrandTokens().primary, 0.85),
                    borderRadius: 8
                }]
            },
            options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
        });

        const countriesRows = Array.isArray(data.top_countries) ? data.top_countries : [];
        createAdminGlobalChart('adminGlobalChartTopCountries', 'adminGlobalTopCountries', {
            type: 'bar',
            data: {
                labels: countriesRows.map((row) => row.label || 'N/D'),
                datasets: [{
                    label: 'Cliques',
                    data: countriesRows.map((row) => Number(row.total || 0)),
                    backgroundColor: '#6366f1',
                    borderRadius: 8
                }]
            },
            options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
        });

        const citiesRows = Array.isArray(data.top_cities) ? data.top_cities : [];
        createAdminGlobalChart('adminGlobalChartTopCities', 'adminGlobalTopCities', {
            type: 'bar',
            data: {
                labels: citiesRows.map((row) => row.label || 'N/D'),
                datasets: [{
                    label: 'Cliques',
                    data: citiesRows.map((row) => Number(row.total || 0)),
                    backgroundColor: '#f6b64f',
                    borderRadius: 8
                }]
            },
            options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
        });

        renderAdminGlobalList('adminGlobalTopReferrers', data.top_referrers, 'Sem dados de referrer.');
        renderAdminGlobalList('adminGlobalSummaryList', [
            { label: 'Total de planos com usuários', total: usersByPlanRows.length },
            { label: 'Planos com usuários ativos', total: activePlanRows.length },
            { label: 'Fontes de tráfego identificadas', total: sourceRows.length },
            { label: 'Países com cliques', total: countriesRows.length }
        ], 'Sem dados de resumo.');

        // Renderização da Tabela de Todos os Usuários
        const allUsers = Array.isArray(data.all_users) ? data.all_users : [];
        adminGlobalUsersCache = allUsers;
        const allUsersTable = document.getElementById('adminGlobalAllUsersTable');
        if (allUsersTable) {
            if (allUsers.length === 0) {
                allUsersTable.innerHTML = '<tr><td colspan="8" class="dashboard-empty">Nenhum usuário cadastrado.</td></tr>';
            } else {
                allUsersTable.innerHTML = allUsers.map(u => {
                    const userId = Number(u.id || 0);
                    const dateStr = u.created_at ? new Date(u.created_at.replace(' ', 'T')).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '-';
                    const isBanned = Number(u.is_banned || 0) === 1;
                    const statusHtml = renderAdminGlobalUserStatusBadge(u);
                    const linkCount = Number(u.link_count || 0).toLocaleString('pt-BR');
                    const banBtn = isBanned
                        ? `<button class="btn-text" style="color:#10b981;" onclick="unbanAdminGlobalUser(${userId})" title="Desbanir"><i class="fas fa-unlock"></i></button>`
                        : `<button class="btn-text" style="color:#ef4444;" onclick="banAdminGlobalUser(${userId})" title="Banir"><i class="fas fa-ban"></i></button>`;
                    return `
                        <tr>
                            <td>${escapeCampaignHtml(u.name || '-')}</td>
                            <td>${escapeCampaignHtml(u.email || '-')}</td>
                            <td>${escapeCampaignHtml(u.phone || '-')}</td>
                            <td><span class="badge-plan">${escapeCampaignHtml(u.plan_name || 'Start')}</span></td>
                            <td>${statusHtml}</td>
                            <td>${linkCount}</td>
                            <td>${dateStr}</td>
                            <td style="white-space:nowrap;">
                                <button class="btn-text" style="color:var(--primary); margin-right:6px;" onclick="openAdminUserLinksModal(${userId})" title="Ver links"><i class="fas fa-link"></i></button>
                                ${banBtn}
                            </td>
                        </tr>
                    `;
                }).join('');
            }
        }

        const periodSelect = document.getElementById('adminGlobalPeriod');
        if (periodSelect && !periodSelect.dataset.bound) {
            periodSelect.dataset.bound = '1';
            periodSelect.addEventListener('change', () => {
                loadAdminGlobalDashboard();
            });
        }
    } catch (e) {
        console.error('Erro ao carregar dashboard global admin:', e);
        alert('Erro de conexão ao carregar dashboard global.');
    }
}

function renderAdminGlobalUserStatusBadge(userRow) {
    if (Number(userRow?.is_banned || 0) !== 1) {
        return '<span class="badge-plan" style="background:rgba(16,185,129,0.15); color:#10b981;">Ativo</span>';
    }
    const expiresAt = userRow?.ban_expires_at;
    if (!expiresAt) {
        return '<span class="badge-plan" style="background:rgba(239,68,68,0.15); color:#ef4444;">Banido (permanente)</span>';
    }
    const dateStr = new Date(String(expiresAt).replace(' ', 'T')).toLocaleString('pt-BR', {
        day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit'
    });
    return `<span class="badge-plan" style="background:rgba(245,158,11,0.15); color:#f59e0b;">Banido até ${dateStr}</span>`;
}

function findAdminGlobalUser(userId) {
    return adminGlobalUsersCache.find(u => Number(u.id) === Number(userId)) || null;
}

function openAdminUserLinksModal(userId) {
    if (!currentUserIsMasterAdminDashboard) return;
    const user = findAdminGlobalUser(userId);
    adminUserLinksModalUserId = Number(userId);
    const subtitle = document.getElementById('adminUserLinksSubtitle');
    if (subtitle) {
        subtitle.textContent = user
            ? `${user.name || 'Usuário'} · ${user.email || ''}`
            : `Usuário #${userId}`;
    }
    const searchInput = document.getElementById('adminUserLinksSearch');
    if (searchInput) searchInput.value = '';
    openModal('modalAdminUserLinks');
    loadAdminUserLinks(userId, '');
    if (searchInput && !searchInput.dataset.bound) {
        searchInput.dataset.bound = '1';
        searchInput.addEventListener('input', () => {
            clearTimeout(adminUserLinksSearchTimer);
            adminUserLinksSearchTimer = setTimeout(() => {
                loadAdminUserLinks(adminUserLinksModalUserId, searchInput.value.trim());
            }, 300);
        });
    }
}

function closeAdminUserLinksModal() {
    adminUserLinksModalUserId = 0;
    closeModals();
}

async function loadAdminUserLinks(userId, term) {
    if (!currentUserIsMasterAdminDashboard || !userId) return;
    const listEl = document.getElementById('adminUserLinksList');
    const countEl = document.getElementById('adminUserLinksCount');
    if (listEl) {
        listEl.innerHTML = '<tr><td colspan="6" class="dashboard-empty">Carregando links...</td></tr>';
    }
    try {
        const params = new URLSearchParams({
            action: 'list_user_links',
            user_id: String(userId),
            term: term || '',
            t: String(Date.now())
        });
        const res = await fetch(`admin_dashboard.php?${params.toString()}`, { credentials: 'include' });
        const data = await res.json();
        if (!data.success) {
            if (listEl) listEl.innerHTML = `<tr><td colspan="6" class="dashboard-empty">${escapeCampaignHtml(data.message || 'Erro ao carregar links.')}</td></tr>`;
            if (countEl) countEl.textContent = '0 links';
            return;
        }
        const links = Array.isArray(data.links) ? data.links : [];
        if (countEl) countEl.textContent = `${links.length} link${links.length === 1 ? '' : 's'}`;
        if (!listEl) return;
        if (links.length === 0) {
            listEl.innerHTML = '<tr><td colspan="6" class="dashboard-empty">Nenhum link encontrado.</td></tr>';
            return;
        }
        listEl.innerHTML = links.map(link => {
            const typeLabel = link.type === 'qr' ? 'QR Code' : 'Link';
            const statusLabel = link.status === 'paused'
                ? (Number(link.admin_ban_paused || 0) === 1 ? 'Pausado (ban)' : 'Pausado')
                : 'Ativo';
            const createdAt = link.created_at
                ? new Date(String(link.created_at).replace(' ', 'T')).toLocaleDateString('pt-BR')
                : '-';
            const shortUrl = link.short_url || getFullShortUrl(link);
            return `
                <tr>
                    <td>${escapeCampaignHtml(typeLabel)}</td>
                    <td><a href="${escapeCampaignHtml(shortUrl)}" target="_blank" rel="noopener noreferrer" style="color:var(--primary);">${escapeCampaignHtml(shortUrl)}</a></td>
                    <td style="max-width:260px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="${escapeCampaignHtml(link.original_url || '')}">${escapeCampaignHtml(link.original_url || '-')}</td>
                    <td>${escapeCampaignHtml(statusLabel)}</td>
                    <td>${Number(link.clicks || 0).toLocaleString('pt-BR')}</td>
                    <td>${createdAt}</td>
                </tr>
            `;
        }).join('');
    } catch (e) {
        console.error('Erro ao carregar links do usuário:', e);
        if (listEl) listEl.innerHTML = '<tr><td colspan="6" class="dashboard-empty">Erro de conexão.</td></tr>';
    }
}

async function banAdminGlobalUser(userId) {
    if (!currentUserIsMasterAdminDashboard) return;
    const user = findAdminGlobalUser(userId);
    const displayName = user?.name || user?.email || `Usuário #${userId}`;

    const result = await Swal.fire({
        title: 'Banir usuário',
        html: `
            <div style="text-align:left;">
                <p style="color:var(--text-dim); margin-bottom:12px;">Banir <strong>${escapeCampaignHtml(displayName)}</strong>. Links ativos serão pausados automaticamente.</p>
                <label style="display:block; margin-bottom:6px; font-size:0.85rem;">Tipo de banimento</label>
                <select id="adminBanType" class="swal2-input" style="width:100%; margin:0 0 12px;">
                    <option value="permanent">Permanente</option>
                    <option value="temporary">Temporário</option>
                </select>
                <div id="adminBanDurationWrap" style="display:none; margin-bottom:12px;">
                    <label style="display:block; margin-bottom:6px; font-size:0.85rem;">Duração</label>
                    <select id="adminBanDuration" class="swal2-input" style="width:100%; margin:0 0 12px;">
                        <option value="1h">1 hora</option>
                        <option value="24h" selected>24 horas</option>
                        <option value="7d">7 dias</option>
                        <option value="30d">30 dias</option>
                        <option value="custom">Data/hora personalizada</option>
                    </select>
                    <input type="datetime-local" id="adminBanUntil" class="swal2-input" style="width:100%; margin:0; display:none;">
                </div>
                <label style="display:block; margin-bottom:6px; font-size:0.85rem;">Motivo (opcional)</label>
                <textarea id="adminBanReason" class="swal2-textarea" placeholder="Motivo do banimento..." style="width:100%; min-height:80px; margin:0;"></textarea>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Confirmar banimento',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#ef4444',
        background: getCurrentTheme() === 'light' ? '#ffffff' : '#0f172a',
        color: getCurrentTheme() === 'light' ? '#0f172a' : '#e2e8f0',
        didOpen: () => {
            const typeEl = document.getElementById('adminBanType');
            const durationWrap = document.getElementById('adminBanDurationWrap');
            const durationEl = document.getElementById('adminBanDuration');
            const untilEl = document.getElementById('adminBanUntil');
            const syncBanForm = () => {
                const isTemp = typeEl?.value === 'temporary';
                if (durationWrap) durationWrap.style.display = isTemp ? 'block' : 'none';
                if (untilEl && durationEl) {
                    untilEl.style.display = isTemp && durationEl.value === 'custom' ? 'block' : 'none';
                }
            };
            typeEl?.addEventListener('change', syncBanForm);
            durationEl?.addEventListener('change', syncBanForm);
            syncBanForm();
        },
        preConfirm: () => {
            const banType = document.getElementById('adminBanType')?.value || 'permanent';
            const durationPreset = document.getElementById('adminBanDuration')?.value || '';
            const banUntil = document.getElementById('adminBanUntil')?.value || '';
            const reason = (document.getElementById('adminBanReason')?.value || '').trim();
            if (banType === 'temporary') {
                if (durationPreset === 'custom' && !banUntil) {
                    Swal.showValidationMessage('Informe a data/hora do ban temporário.');
                    return false;
                }
                if (durationPreset !== 'custom' && !durationPreset) {
                    Swal.showValidationMessage('Selecione a duração do ban temporário.');
                    return false;
                }
            }
            return { banType, durationPreset, banUntil, reason };
        }
    });

    if (!result.isConfirmed || !result.value) return;

    const fd = new FormData();
    fd.append('action', 'ban_user');
    fd.append('user_id', String(userId));
    fd.append('ban_type', result.value.banType);
    if (result.value.banType === 'temporary') {
        if (result.value.durationPreset === 'custom') {
            fd.append('ban_until', result.value.banUntil.replace('T', ' ') + ':00');
        } else {
            fd.append('duration_preset', result.value.durationPreset);
        }
    }
    fd.append('reason', result.value.reason);
    appendCsrfToFormData(fd);

    try {
        const res = await fetch('admin_dashboard.php', { method: 'POST', body: fd, credentials: 'include' });
        const data = await res.json();
        if (data.success) {
            await Swal.fire({ icon: 'success', title: 'Banido', text: data.message || 'Usuário banido com sucesso.', timer: 2000, showConfirmButton: false });
            loadAdminGlobalDashboard();
        } else {
            await Swal.fire({ icon: 'error', title: 'Erro', text: data.message || 'Não foi possível banir o usuário.' });
        }
    } catch (e) {
        await Swal.fire({ icon: 'error', title: 'Erro', text: 'Erro de conexão ao banir usuário.' });
    }
}

async function unbanAdminGlobalUser(userId) {
    if (!currentUserIsMasterAdminDashboard) return;
    const user = findAdminGlobalUser(userId);
    const displayName = user?.name || user?.email || `Usuário #${userId}`;

    const confirm = await Swal.fire({
        title: 'Desbanir usuário',
        text: `Remover banimento de ${displayName}? Links pausados pelo ban serão reativados.`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Desbanir',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#10b981'
    });
    if (!confirm.isConfirmed) return;

    const fd = new FormData();
    fd.append('action', 'unban_user');
    fd.append('user_id', String(userId));
    appendCsrfToFormData(fd);

    try {
        const res = await fetch('admin_dashboard.php', { method: 'POST', body: fd, credentials: 'include' });
        const data = await res.json();
        if (data.success) {
            await Swal.fire({ icon: 'success', title: 'Desbanido', text: data.message || 'Banimento removido.', timer: 2000, showConfirmButton: false });
            loadAdminGlobalDashboard();
        } else {
            await Swal.fire({ icon: 'error', title: 'Erro', text: data.message || 'Não foi possível desbanir.' });
        }
    } catch (e) {
        await Swal.fire({ icon: 'error', title: 'Erro', text: 'Erro de conexão ao desbanir usuário.' });
    }
}

/**
 * Aplica cadeados e lógica de bloqueio em elementos com data-feature
 */
function applyFeatureLocks() {
    document.querySelectorAll('[data-feature]').forEach(el => {
        const feature = el.dataset.feature;
        const isAllowed = userFeatures[feature] === true;

        if (!isAllowed) {
            el.classList.add('locked-feature');
            // Se for um botão ou link, remove o onclick original temporariamente ou intercepta
            el.setAttribute('data-original-click', el.getAttribute('onclick') || '');
            // el.setAttribute('onclick', 'handleLockedFeatureClick(event)'); 
            // Preferível listener para não poluir
        } else {
            el.classList.remove('locked-feature');
        }
    });
}

// Interceptador Global de Cliques para Locked Features
document.addEventListener('click', (e) => {
    const lockedEl = e.target.closest('.locked-feature');
    if (lockedEl) {
        // Se for um botão de expansão da lista, permite o clique para que loadExpandedContent lide com o bloqueio visualmente na área expandida
        if (lockedEl.getAttribute('onclick') && lockedEl.getAttribute('onclick').includes('toggleItemExpansion')) {
            return;
        }

        e.preventDefault();
        e.stopPropagation();
        const onclickCode = lockedEl.getAttribute('onclick') || '';
        if (onclickCode.includes('showSection')) {
            const sectionMatch = onclickCode.match(/showSection\('([^']+)'/);
            if (sectionMatch && sectionMatch[1]) {
                showSection(sectionMatch[1]);
                const mappedFeature = lockedEl.dataset.feature || sectionFeatureMap[sectionMatch[1]];
                if (mappedFeature && isFeatureLocked(mappedFeature)) {
                    renderSectionDemoBanner(sectionMatch[1], mappedFeature);
                }
                return;
            }
        }
        handleLockedFeatureClick(lockedEl.dataset.feature);
    }
}, true);

function handleLockedFeatureClick(featureName) {
    const label = featureDisplayLabels[featureName] || 'Este recurso';
    showStatus('link', `${label}: exibição em modo demonstração. Faça upgrade para liberar dados reais.`, 'success');
    if (featureSectionMap[featureName]) {
        showSection(featureSectionMap[featureName]);
        renderSectionDemoBanner(featureSectionMap[featureName], featureName);
        return;
    }
    showSection('section-subscription');
}

const ADMIN_FEATURE_FIELDS = [
    { id: 'analytics', name: 'Analytics Detalhado' },
    { id: 'custom_domain', name: 'Domínios/Subdomínios' },
    { id: 'deep_linking', name: 'Deep Linking (Nativo)' },
    { id: 'bulk_utm', name: 'UTM Builder em Massa' },
    { id: 'tracking', name: 'Tracking + Retargeting' },
    { id: 'dwell_monitor', name: 'Monitoramento de Permanência' },
    { id: 'ab_testing', name: 'Testes A/B' },
    { id: 'rules', name: 'Regras Avançadas' },
    { id: 'bio_pages', name: 'Bio Pages' },
    { id: 'custom_path', name: 'Terminação Personalizada' },
    { id: 'redirect', name: 'Redirecionamento sem recriar link' },
    { id: 'team', name: 'Colaboração em Equipe' },
    { id: 'folders_tags', name: 'Pastas e Tags' },
    { id: 'social_preview', name: 'Preview Social' },
    { id: 'premium_bio_templates', name: 'Templates Premium da Bio' },
    { id: 'bio_form_exports', name: 'Exportação de Formulários da Bio' },
    { id: 'ai_tutor', name: 'Tutor IA' },
    { id: 'priority_support', name: 'Suporte Prioritário' }
];

const ADMIN_LIMIT_FIELDS = [
    { id: 'max_links', name: 'Limite mensal de Links', type: 'number' },
    { id: 'max_qrs', name: 'Limite mensal de QR Codes', type: 'number' },
    { id: 'max_link_destination_edits', name: 'Edições mensais de destino (Links)', type: 'number' },
    { id: 'max_qr_destination_edits', name: 'Edições mensais de destino (QR Codes)', type: 'number' },
    { id: 'max_bio_pages', name: 'Limite mensal de Bio Pages', type: 'number' },
    { id: 'max_campaigns', name: 'Limite mensal de Campanhas', type: 'number' },
    { id: 'max_subdomains', name: 'Limite mensal de Subdomínios', type: 'number' },
    { id: 'max_team_members', name: 'Limite mensal de Membros da Equipe', type: 'number' },
    { id: 'max_link_dwell_monitors', name: 'Monitores de permanência ativos (Links)', type: 'number' },
    { id: 'max_qr_dwell_monitors', name: 'Monitores de permanência ativos (QR)', type: 'number' },
    { id: 'analytics_days', name: 'Retenção de Analytics (dias)', type: 'number' },
    { id: 'max_clicks_monthly', name: 'Limite mensal de Cliques', type: 'number' }
];

function safeParsePlanFeatures(featuresJson) {
    try {
        const parsed = JSON.parse(featuresJson || '{}');
        return (parsed && typeof parsed === 'object') ? parsed : {};
    } catch (e) {
        return {};
    }
}

function collectAllFeatureKeys(plans) {
    const keys = new Set(ADMIN_FEATURE_FIELDS.map(f => f.id));
    plans.forEach(plan => {
        const parsed = safeParsePlanFeatures(plan.features_json);
        Object.keys(parsed).forEach(k => {
            if (typeof parsed[k] === 'boolean') {
                keys.add(k);
            }
        });
    });
    return Array.from(keys);
}

function buildAdminFeatureEditor(plan, featureKeys) {
    const labels = ADMIN_FEATURE_FIELDS.reduce((acc, item) => {
        acc[item.id] = item.name;
        return acc;
    }, {});
    const current = safeParsePlanFeatures(plan.features_json);

    return featureKeys.map((featureId) => {
        const checked = current[featureId] === true ? 'checked' : '';
        const label = labels[featureId] || featureId;
        return `
            <label style="display:flex; align-items:center; gap:8px; padding:8px 10px; background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.06); border-radius:8px; font-size:12px;">
                <input type="checkbox" class="admin-plan-feature-check" data-plan="${plan.id}" data-feature="${featureId}" ${checked} style="width:16px; height:16px;">
                <span>${label}</span>
            </label>
        `;
    }).join('');
}

function getPlanFeaturePayload(planId) {
    const checks = document.querySelectorAll(`.admin-plan-feature-check[data-plan="${planId}"]`);
    const features = {};
    checks.forEach(ch => {
        features[ch.dataset.feature] = ch.checked;
    });
    return features;
}

function refreshPricingViewsAfterPlanChange() {
    if (typeof loadUpgradePlans === 'function') {
        loadUpgradePlans();
    }
}

function getPlanAnalyticsDays(plan) {
    const dbDays = parseInt(plan?.analytics_days || 0, 10);
    if (dbDays > 0) return dbDays;

    const features = safeParsePlanFeatures(plan?.features_json);
    const jsonDays = parseInt(features.analytics_days ?? features.analytics_retention_days ?? 0, 10);
    return Number.isFinite(jsonDays) && jsonDays > 0 ? jsonDays : 0;
}

function formatAnalyticsDays(days) {
    if (days <= 0) return 'Sem Analytics';
    if (days === 30) return '30 dias';
    if (days === 365) return '1 ano';
    if (days === 730) return '2 anos';
    return `${days} dias`;
}

function getPlanNumericValue(plan, fieldName, fallback = 0) {
    const direct = Number(plan?.[fieldName]);
    if (Number.isFinite(direct)) return direct;

    const features = safeParsePlanFeatures(plan?.features_json);
    const jsonVal = Number(features?.[fieldName]);
    if (Number.isFinite(jsonVal)) return jsonVal;

    return fallback;
}

function normalizePlanForPricing(plan) {
    const normalized = { ...plan };
    normalized.max_links = getPlanNumericValue(plan, 'max_links', 0);
    normalized.max_qrs = getPlanNumericValue(plan, 'max_qrs', 0);
    normalized.max_link_destination_edits = getPlanNumericValue(plan, 'max_link_destination_edits', 999999);
    normalized.max_qr_destination_edits = getPlanNumericValue(plan, 'max_qr_destination_edits', 999999);
    normalized.max_bio_pages = getPlanNumericValue(plan, 'max_bio_pages', 0);
    normalized.max_campaigns = getPlanNumericValue(plan, 'max_campaigns', 0);
    normalized.max_clicks_monthly = getPlanNumericValue(plan, 'max_clicks_monthly', 0);
    normalized.max_subdomains = getPlanNumericValue(plan, 'max_subdomains', 0);
    normalized.max_team_members = getPlanNumericValue(plan, 'max_team_members', 1);
    normalized.max_link_dwell_monitors = getPlanNumericValue(plan, 'max_link_dwell_monitors', 0);
    normalized.max_qr_dwell_monitors = getPlanNumericValue(plan, 'max_qr_dwell_monitors', 0);
    normalized.analytics_days = getPlanAnalyticsDays(plan);
    normalized.monthly_price = Number(plan?.monthly_price || 0);
    normalized.annual_price = Number(plan?.annual_price || 0);
    return normalized;
}

function isPlanLimitAvailable(value, infinityTrigger) {
    const num = Number(value);
    if (!Number.isFinite(num)) return false;
    if (num >= 999999) return true;
    if (Number.isFinite(Number(infinityTrigger)) && num >= Number(infinityTrigger)) return true;
    return num > 0;
}

function getPlanLimitListItem(value, options) {
    const {
        infinityTrigger = 999999,
        labelUnavailable = 'Não incluso',
        labelUnitSingular = '',
        labelUnitPlural = '',
        labelUnlimited = 'Ilimitado',
        unlimitedPrefix = ''
    } = options || {};

    const numericValue = Number(value);
    const isUnlimited = Number.isFinite(numericValue) && (
        numericValue >= 999999 ||
        (Number.isFinite(Number(infinityTrigger)) && numericValue >= Number(infinityTrigger))
    );
    const isAvailable = isPlanLimitAvailable(numericValue, infinityTrigger);

    if (isUnlimited) {
        const textUnlimited = unlimitedPrefix ? `${unlimitedPrefix} ${labelUnlimited}` : labelUnlimited;
        return `<li><i class="fas fa-check"></i> <b>${textUnlimited}</b></li>`;
    }

    if (!isAvailable) {
        return `<li><i class="fas fa-times" style="opacity:0.3"></i> ${labelUnavailable}</li>`;
    }

    const unit = numericValue === 1 ? labelUnitSingular : labelUnitPlural;
    return `<li><i class="fas fa-check"></i> <b>${numericValue.toLocaleString('pt-BR')} ${unit}</b></li>`;
}

function fillAdminAffiliateForm(userId, affiliateCode = '', isActive = 1) {
    const userInput = document.getElementById('adminAffiliateUserId');
    const codeInput = document.getElementById('adminAffiliateCode');
    const statusInput = document.getElementById('adminAffiliateIsActive');
    if (userInput) userInput.value = userId || '';
    if (codeInput) codeInput.value = affiliateCode || '';
    if (statusInput) statusInput.value = Number(isActive || 0) === 1 ? '1' : '0';
}

async function searchAffiliateCandidates() {
    const term = (document.getElementById('adminAffiliateSearch')?.value || '').trim();
    const tbody = document.getElementById('adminAffiliateCandidatesTable');
    if (!tbody) return;
    if (!term) {
        tbody.innerHTML = '<tr><td colspan="6" class="dashboard-empty">Digite um termo para buscar usuários.</td></tr>';
        return;
    }
    try {
        const res = await fetch(`admin_api.php?action=search_users_for_affiliate&term=${encodeURIComponent(term)}`, { credentials: 'include' });
        const data = await res.json();
        if (!data.success) {
            tbody.innerHTML = `<tr><td colspan="6" class="dashboard-empty">${data.message || 'Falha na busca de usuários.'}</td></tr>`;
            return;
        }
        const rows = Array.isArray(data.data) ? data.data : [];
        if (rows.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="dashboard-empty">Nenhum usuário encontrado.</td></tr>';
            return;
        }
        tbody.innerHTML = rows.map((u) => `
            <tr class="dashboard-activity-row">
                <td>${u.id}</td>
                <td>${u.name || '-'}</td>
                <td>${u.email || '-'}</td>
                <td>${u.affiliate_code || '-'}</td>
                <td>${Number(u.is_active || 0) === 1 ? 'Ativo' : 'Inativo'}</td>
                <td>
                    <button class="btn-primary" style="width:auto; padding:6px 12px;" onclick="fillAdminAffiliateForm(${Number(u.id)}, '${String(u.affiliate_code || '').replace(/'/g, "\\'")}', ${Number(u.is_active || 0)})">
                        Selecionar
                    </button>
                </td>
            </tr>
        `).join('');
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="6" class="dashboard-empty">Erro de conexão na busca.</td></tr>';
    }
}

async function saveAdminAffiliate(e) {
    if (e) e.preventDefault();
    const userId = Number(document.getElementById('adminAffiliateUserId')?.value || 0);
    const code = (document.getElementById('adminAffiliateCode')?.value || '').trim();
    const isActive = Number(document.getElementById('adminAffiliateIsActive')?.value || 1);
    if (userId <= 0 || code.length < 4) {
        alert('Preencha ID do usuário e um código válido (mínimo 4 caracteres).');
        return;
    }
    const fd = new FormData();
    fd.append('action', 'save_affiliate');
    fd.append('user_id', String(userId));
    fd.append('affiliate_code', code);
    fd.append('is_active', String(isActive === 1 ? 1 : 0));
    try {
        const res = await fetch('admin_api.php', { method: 'POST', body: fd, credentials: 'include' });
        const data = await res.json();
        if (!data.success) {
            alert(data.message || 'Não foi possível salvar afiliado.');
            return;
        }
        alert('Afiliado salvo com sucesso.');
        await loadAdminAffiliatesDashboard(userId, true);
    } catch (e2) {
        alert('Erro de conexão ao salvar afiliado.');
    }
}

async function removeAdminAffiliate(affiliateId) {
    const id = Number(affiliateId || 0);
    if (id <= 0) return;
    if (!confirm('Deseja remover este afiliado? Esta ação remove também os dados relacionados por cascade.')) return;
    const fd = new FormData();
    fd.append('action', 'remove_affiliate');
    fd.append('affiliate_id', String(id));
    try {
        const res = await fetch('admin_api.php', { method: 'POST', body: fd, credentials: 'include' });
        const data = await res.json();
        if (!data.success) {
            alert(data.message || 'Não foi possível remover afiliado.');
            return;
        }
        alert('Afiliado removido com sucesso.');
        currentAdminAffiliateId = 0;
        await loadAdminAffiliatesDashboard();
    } catch (e) {
        alert('Erro de conexão ao remover afiliado.');
    }
}

async function loadAdminAffiliatesDashboard(selectedAffiliateId = 0, preserveSelection = false) {
    if (!isLoggedIn || !currentUserIsAdmin) return;
    const targetAffiliateId = Number(selectedAffiliateId || 0);
    const resolvedId = targetAffiliateId > 0 ? targetAffiliateId : (preserveSelection ? currentAdminAffiliateId : 0);
    const query = resolvedId > 0
        ? `admin_api.php?action=affiliate_dashboard_overview&affiliate_id=${encodeURIComponent(resolvedId)}`
        : 'admin_api.php?action=affiliate_dashboard_overview';
    try {
        const res = await fetch(`${query}&t=${Date.now()}`, { credentials: 'include' });
        const data = await res.json();
        if (!data.success) {
            alert(data.message || 'Não foi possível carregar dashboard de afiliados.');
            return;
        }

        const kpis = data.kpis || {};
        const affiliates = Array.isArray(data.affiliates) ? data.affiliates : [];
        const refsByAff = Array.isArray(data.referrals_by_affiliate) ? data.referrals_by_affiliate : [];
        const commByAff = Array.isArray(data.commissions_by_affiliate) ? data.commissions_by_affiliate : [];
        const detail = data.detail || {};
        currentAdminAffiliateId = Number(detail.affiliate_id || 0);

        const setText = (id, value) => {
            const el = document.getElementById(id);
            if (el) el.textContent = value;
        };
        setText('adminAffTotal', Number(kpis.total_affiliates || 0).toLocaleString('pt-BR'));
        setText('adminAffActive', Number(kpis.active_affiliates || 0).toLocaleString('pt-BR'));
        setText('adminAffReferrals', Number(kpis.total_referrals || 0).toLocaleString('pt-BR'));
        setText('adminAffPending', formatCurrencyBRL(kpis.pending_commissions || 0));
        setText('adminAffReleased', formatCurrencyBRL(kpis.released_commissions || 0));

        createAdminGlobalChart('adminAffChartReferrals', 'adminAffChartReferrals', {
            type: 'bar',
            data: {
                labels: refsByAff.map((r) => r.label || 'Afiliado'),
                datasets: [{
                    label: 'Cadastros',
                    data: refsByAff.map((r) => Number(r.total || 0)),
                    backgroundColor: hexToRgba(getBrandTokens().accent, 0.85),
                    borderRadius: 8
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
        });

        createAdminGlobalChart('adminAffChartCommissions', 'adminAffChartCommissions', {
            type: 'bar',
            data: {
                labels: commByAff.map((r) => r.label || 'Afiliado'),
                datasets: [{
                    label: 'Comissões (R$)',
                    data: commByAff.map((r) => Number(r.total || 0)),
                    backgroundColor: hexToRgba(getBrandTokens().primary, 0.85),
                    borderRadius: 8
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
        });

        const plansDist = Array.isArray(detail.plans_distribution) ? detail.plans_distribution : [];
        const geoCountries = Array.isArray(detail.geo_countries) ? detail.geo_countries : [];
        const geoCities = Array.isArray(detail.geo_cities) ? detail.geo_cities : [];
        createAdminGlobalChart('adminAffChartPlans', 'adminAffChartPlans', {
            type: 'doughnut',
            data: {
                labels: plansDist.map((r) => r.plan_name || 'Plano'),
                datasets: [{
                    data: plansDist.map((r) => Number(r.total || 0)),
                    backgroundColor: chartVisualTheme.colors.slice(0, 6),
                    borderWidth: 0
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } }, cutout: '68%' }
        });

        createAdminGlobalChart('adminAffChartCountries', 'adminAffChartCountries', {
            type: 'bar',
            data: {
                labels: geoCountries.map((r) => r.label || 'N/D'),
                datasets: [{
                    label: 'Cadastros',
                    data: geoCountries.map((r) => Number(r.total || 0)),
                    backgroundColor: '#6366f1',
                    borderRadius: 8
                }]
            },
            options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
        });

        createAdminGlobalChart('adminAffChartCities', 'adminAffChartCities', {
            type: 'bar',
            data: {
                labels: geoCities.map((r) => r.label || 'N/D'),
                datasets: [{
                    label: 'Cadastros',
                    data: geoCities.map((r) => Number(r.total || 0)),
                    backgroundColor: '#f6b64f',
                    borderRadius: 8
                }]
            },
            options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
        });

        const forecast = Array.isArray(detail.forecast_months) ? detail.forecast_months : [];
        createAdminGlobalChart('adminAffChartForecast', 'adminAffChartForecast', {
            type: 'line',
            data: {
                labels: forecast.map((row) => {
                    const d = new Date(`${row.month_key}T00:00:00`);
                    return d.toLocaleDateString('pt-BR', { month: 'short', year: 'numeric' });
                }),
                datasets: [{
                    label: 'Comissão prevista',
                    data: forecast.map((row) => Number(row.total || 0)),
                    borderColor: getBrandTokens().primary,
                    backgroundColor: getBrandTokens().chartFillPrimary,
                    fill: true,
                    tension: 0.35
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
        });

        const table = document.getElementById('adminAffiliatesTable');
        if (table) {
            if (affiliates.length === 0) {
                table.innerHTML = '<tr><td colspan="9" class="dashboard-empty">Nenhum afiliado cadastrado.</td></tr>';
            } else {
                table.innerHTML = affiliates.map((a) => {
                    const isSelected = Number(a.id) === currentAdminAffiliateId;
                    return `
                        <tr class="dashboard-activity-row ${isSelected ? 'active-admin-affiliate' : ''}">
                            <td>${a.id}</td>
                            <td>${a.user_name || '-'}<br><small style="color:var(--text-dim);">${a.user_email || ''}</small></td>
                            <td>${a.affiliate_code || '-'}</td>
                            <td>${Number(a.is_active || 0) === 1 ? 'Ativo' : 'Inativo'}</td>
                            <td>${Number(a.total_signups || 0).toLocaleString('pt-BR')}</td>
                            <td>${Number(a.active_paid_users || 0).toLocaleString('pt-BR')}</td>
                            <td>${formatCurrencyBRL(a.pending_commission || 0)}</td>
                            <td>${formatCurrencyBRL(a.released_commission || 0)}</td>
                            <td>
                                <button class="btn-primary" style="width:auto; padding:6px 10px; margin-right:6px;" onclick="fillAdminAffiliateForm(${Number(a.user_id)}, '${String(a.affiliate_code || '').replace(/'/g, "\\'")}', ${Number(a.is_active || 0)}); loadAdminAffiliatesDashboard(${Number(a.id)})">Ver</button>
                                <button class="btn-text" style="color:#ef4444;" onclick="removeAdminAffiliate(${Number(a.id)})">Remover</button>
                            </td>
                        </tr>
                    `;
                }).join('');
            }
        }
    } catch (e) {
        console.error('Erro ao carregar dashboard de afiliados:', e);
        alert('Erro de conexão ao carregar dashboard de afiliados.');
    }
}

async function loadAdminMatrix() {
    try {
        const res = await fetch('admin_api.php?action=get_plans', { credentials: 'include' });
        const data = await res.json();
        if (!data.success) return;

        const plans = data.data;
        const featureKeys = collectAllFeatureKeys(plans);
        const featureLabels = ADMIN_FEATURE_FIELDS.reduce((acc, item) => {
            acc[item.id] = item.name;
            return acc;
        }, {});
        const features = featureKeys.map((featureId) => ({
            id: featureId,
            name: featureLabels[featureId] || featureId
        }));
        const limitFields = ADMIN_LIMIT_FIELDS;

        // Header
        let headHtml = '<th style="text-align:left; padding:15px;">Funcionalidade</th>';
        plans.forEach(p => {
            headHtml += `<th style="text-align:center;">${p.name}</th>`;
        });
        document.getElementById('matrixHeadRow').innerHTML = headHtml;

        // Body
        let bodyHtml = '';
        bodyHtml += `<tr style="background:rgba(255,255,255,0.03);">
            <td style="padding:15px; font-weight:700;" data-label="Grupo">Limites Numéricos</td>
            ${plans.map(p => `<td style="text-align:center; padding:10px; font-size:11px; color:var(--text-dim);" data-label="${p.name}">Editável</td>`).join('')}
        </tr>`;

        limitFields.forEach(field => {
            bodyHtml += `<tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                <td style="padding:15px; font-weight:500;" data-label="Funcionalidade">${field.name} <br><small style="color:var(--text-dim); font-size:10px;">${field.id}</small></td>`;

            plans.forEach(p => {
                const value = field.id === 'analytics_days'
                    ? getPlanAnalyticsDays(p)
                    : getPlanNumericValue(p, field.id, 0);
                bodyHtml += `<td style="text-align:center; padding:10px;" data-label="${p.name}">
                    <input type="number" class="matrix-limit-input" data-plan="${p.id}" data-limit="${field.id}" value="${value}" style="max-width:110px; text-align:center;">
                </td>`;
            });
            bodyHtml += '</tr>';
        });

        bodyHtml += `<tr style="background:rgba(255,255,255,0.03);">
            <td style="padding:15px; font-weight:700;" data-label="Grupo">Permissões Booleanas</td>
            ${plans.map(p => `<td style="text-align:center; padding:10px; font-size:11px; color:var(--text-dim);" data-label="${p.name}">Ativo/Inativo</td>`).join('')}
        </tr>`;

        features.forEach(f => {
            bodyHtml += `<tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                <td style="padding:15px; font-weight:500;" data-label="Funcionalidade">${f.name} <br><small style="color:var(--text-dim); font-size:10px;">${f.id}</small></td>`;

            plans.forEach(p => {
                let currentFeats = safeParsePlanFeatures(p.features_json);
                const checked = currentFeats[f.id] === true ? 'checked' : '';

                bodyHtml += `<td style="text-align:center;" data-label="${p.name}">
                    <input type="checkbox" class="matrix-check" data-plan="${p.id}" data-feature="${f.id}" ${checked} style="width:20px; height:20px; cursor:pointer;">
                </td>`;
            });
            bodyHtml += '</tr>';
        });
        document.getElementById('matrixBody').innerHTML = bodyHtml;

    } catch (e) { console.error("Erro ao carregar matriz", e); }
}

async function saveAdminMatrix() {
    const btn = document.querySelector('button[onclick="saveAdminMatrix()"]');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando...';

    const checks = document.querySelectorAll('.matrix-check');
    const limitInputs = document.querySelectorAll('.matrix-limit-input');
    const planUpdates = {}; // { planId: { feature: bool } }
    const planLimitUpdates = {}; // { planId: { limit: value } }

    checks.forEach(c => {
        const pid = c.dataset.plan;
        const fid = c.dataset.feature;
        if (!planUpdates[pid]) planUpdates[pid] = {};
        planUpdates[pid][fid] = c.checked;
    });

    limitInputs.forEach(input => {
        const pid = input.dataset.plan;
        const limitName = input.dataset.limit;
        if (!planLimitUpdates[pid]) planLimitUpdates[pid] = {};
        const numericValue = Number(input.value);
        planLimitUpdates[pid][limitName] = Number.isFinite(numericValue) ? numericValue : 0;
    });

    try {
        // Para cada plano, envia um update (ou poderiamos otimizar a API para receber lote)
        // Por simplicidade e segurança, usaremos o update_plan existente puxando o JSON e mesclando
        const resPlans = await fetch('admin_api.php?action=get_plans', { credentials: 'include' });
        const dataPlans = await resPlans.json();

        for (const p of dataPlans.data) {
            const newFeats = planUpdates[p.id] || {};
            const newLimits = planLimitUpdates[p.id] || {};
            // Mantém outras features que não estão na matriz se houverem
            let currentJson = {};
            try { currentJson = JSON.parse(p.features_json) || {}; } catch (e) { }

            const finalJson = { ...currentJson, ...newFeats };

            const fd = new FormData();
            fd.append('action', 'update_plan');
            fd.append('id', p.id);
            fd.append('name', p.name);
            fd.append('monthly_price', p.monthly_price);
            fd.append('annual_price', p.annual_price);
            fd.append('max_links', newLimits.max_links ?? p.max_links);
            fd.append('max_qrs', newLimits.max_qrs ?? p.max_qrs);
            fd.append('max_bio_pages', newLimits.max_bio_pages ?? p.max_bio_pages);
            fd.append('max_campaigns', newLimits.max_campaigns ?? p.max_campaigns);
            fd.append('max_clicks_monthly', newLimits.max_clicks_monthly ?? p.max_clicks_monthly ?? 0);
            fd.append('analytics_days', newLimits.analytics_days ?? p.analytics_days ?? 0);
            fd.append('max_subdomains', newLimits.max_subdomains ?? p.max_subdomains ?? 0);
            fd.append('max_team_members', newLimits.max_team_members ?? p.max_team_members ?? 1);
            fd.append('max_link_dwell_monitors', newLimits.max_link_dwell_monitors ?? p.max_link_dwell_monitors ?? 0);
            fd.append('max_qr_dwell_monitors', newLimits.max_qr_dwell_monitors ?? p.max_qr_dwell_monitors ?? 0);
            fd.append('max_link_destination_edits', newLimits.max_link_destination_edits ?? p.max_link_destination_edits ?? 999999);
            fd.append('max_qr_destination_edits', newLimits.max_qr_destination_edits ?? p.max_qr_destination_edits ?? 999999);
            fd.append('tier_level', p.tier_level ?? 0);
            fd.append('features_json', JSON.stringify(finalJson));

            await fetch('admin_api.php', { method: 'POST', body: fd, credentials: 'include' });
        }

        alert("✅ Matriz de funcionalidades atualizada com sucesso!");
        loadAdminMatrix();
        refreshPricingViewsAfterPlanChange();
    } catch (e) {
        alert("Erro ao salvar matriz.");
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> Salvar Matriz';
    }
}

async function loadAdminPlans() {
    try {
        const res = await fetch('admin_api.php?action=get_plans', { credentials: 'include' });
        const data = await res.json();
        if (!data.success) { alert(data.message); return; }

        const plans = data.data;
        const allFeatureKeys = collectAllFeatureKeys(plans);
        let html = '';
        plans.forEach(p => {
            html += `
            <div style="border: 1px solid var(--border); padding: 15px; border-radius: 10px; background: var(--interactive-soft);">
                <form onsubmit="saveAdminPlan(event, ${p.id})">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 15px;">
                        <h4 style="color:var(--primary); font-size:1.2rem;">${p.name}</h4>
                        <button type="submit" class="btn-primary" style="padding: 8px 15px; width:auto; font-size:0.8rem;">Salvar Plano</button>
                    </div>
                    <div class="control-group-grid" style="grid-template-columns: 1fr 1fr 1fr; gap:10px;">
                        <div class="form-group"><label style="font-size:11px;">Nome do Plano</label><input type="text" id="admin_plan_name_${p.id}" value="${p.name}" required></div>
                        <div class="form-group"><label style="font-size:11px;">Nível do Plano (Tier)</label><input type="number" id="admin_plan_tier_${p.id}" value="${p.tier_level ?? 0}"></div>
                        <div class="form-group"><label style="font-size:11px;">Preço/Mês</label><input type="number" step="0.01" id="admin_plan_m_${p.id}" value="${p.monthly_price}" required></div>
                        <div class="form-group"><label style="font-size:11px;">Preço/Ano</label><input type="number" step="0.01" id="admin_plan_a_${p.id}" value="${p.annual_price}" required></div>
                        <div class="form-group"><label style="font-size:11px;">Limite mensal Links</label><input type="number" id="admin_plan_links_${p.id}" value="${p.max_links}"></div>
                        <div class="form-group"><label style="font-size:11px;">Limite mensal QR Codes</label><input type="number" id="admin_plan_qrs_${p.id}" value="${p.max_qrs}"></div>
                        <div class="form-group"><label style="font-size:11px;">Limite mensal Bio Pgs</label><input type="number" id="admin_plan_bio_${p.id}" value="${p.max_bio_pages}"></div>
                        <div class="form-group"><label style="font-size:11px;">Limite mensal Campanhas</label><input type="number" id="admin_plan_camp_${p.id}" value="${p.max_campaigns}"></div>
                        <div class="form-group"><label style="font-size:11px;">Limite Cliques/Mês</label><input type="number" id="admin_plan_clicks_${p.id}" value="${p.max_clicks_monthly ?? 0}"></div>
                        <div class="form-group"><label style="font-size:11px;">Retenção Analytics (dias)</label><input type="number" id="admin_plan_analytics_${p.id}" value="${p.analytics_days ?? 0}"></div>
                        <div class="form-group"><label style="font-size:11px;">Limite mensal Subdomínios</label><input type="number" id="admin_plan_subdomains_${p.id}" value="${p.max_subdomains ?? 0}"></div>
                        <div class="form-group"><label style="font-size:11px;">Limite mensal Membros Equipe</label><input type="number" id="admin_plan_team_members_${p.id}" value="${p.max_team_members ?? 1}"></div>
                        <div class="form-group"><label style="font-size:11px;">Monit. permanência ativos (Links)</label><input type="number" id="admin_plan_link_dwell_${p.id}" value="${p.max_link_dwell_monitors ?? 0}"></div>
                        <div class="form-group"><label style="font-size:11px;">Monit. permanência ativos (QR)</label><input type="number" id="admin_plan_qr_dwell_${p.id}" value="${p.max_qr_dwell_monitors ?? 0}"></div>
                        <div class="form-group"><label style="font-size:11px;">Limite mensal Edição Destino (Links)</label><input type="number" id="admin_plan_link_edits_${p.id}" value="${p.max_link_destination_edits ?? 999999}"></div>
                        <div class="form-group"><label style="font-size:11px;">Limite mensal Edição Destino (QR)</label><input type="number" id="admin_plan_qr_edits_${p.id}" value="${p.max_qr_destination_edits ?? 999999}"></div>
                    </div>
                    <div style="margin-top:12px;">
                        <label style="font-size:11px; display:block; margin-bottom:8px;">Funcionalidades do plano</label>
                        <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(210px,1fr)); gap:8px;">
                            ${buildAdminFeatureEditor(p, allFeatureKeys)}
                        </div>
                    </div>
                </form>
            </div>
            `;
        });
        document.getElementById('adminPlansContainer').innerHTML = html;
    } catch (e) { }
}

async function saveAdminPlan(e, id) {
    e.preventDefault();
    const fd = new FormData();
    fd.append('action', 'update_plan');
    fd.append('id', id);
    fd.append('name', document.getElementById(`admin_plan_name_${id}`).value);
    fd.append('monthly_price', document.getElementById(`admin_plan_m_${id}`).value);
    fd.append('annual_price', document.getElementById(`admin_plan_a_${id}`).value);
    fd.append('max_links', document.getElementById(`admin_plan_links_${id}`).value);
    fd.append('max_qrs', document.getElementById(`admin_plan_qrs_${id}`).value);
    fd.append('max_bio_pages', document.getElementById(`admin_plan_bio_${id}`).value);
    fd.append('max_campaigns', document.getElementById(`admin_plan_camp_${id}`).value);
    fd.append('max_clicks_monthly', document.getElementById(`admin_plan_clicks_${id}`).value);
    fd.append('analytics_days', document.getElementById(`admin_plan_analytics_${id}`).value);
    fd.append('max_subdomains', document.getElementById(`admin_plan_subdomains_${id}`).value);
    fd.append('max_team_members', document.getElementById(`admin_plan_team_members_${id}`).value);
    fd.append('max_link_dwell_monitors', document.getElementById(`admin_plan_link_dwell_${id}`).value);
    fd.append('max_qr_dwell_monitors', document.getElementById(`admin_plan_qr_dwell_${id}`).value);
    fd.append('max_link_destination_edits', document.getElementById(`admin_plan_link_edits_${id}`).value);
    fd.append('max_qr_destination_edits', document.getElementById(`admin_plan_qr_edits_${id}`).value);
    fd.append('tier_level', document.getElementById(`admin_plan_tier_${id}`).value);
    fd.append('features_json', JSON.stringify(getPlanFeaturePayload(id)));

    try {
        const res = await fetch('admin_api.php', { method: 'POST', body: fd, credentials: 'include' });
        const data = await res.json();
        if (data.success) {
            alert("Plano salvo com sucesso!");
            refreshPricingViewsAfterPlanChange();
            loadAdminPlans();
            loadAdminMatrix();
        }
        else alert(data.message);
    } catch (e) { }
}

async function loadAdminConfig() {
    try {
        const res = await fetch('admin_api.php?action=get_config', { credentials: 'include' });
        const data = await res.json();
        if (data.success && data.data) {
            if (document.getElementById('config_founder_edition_active'))
                document.getElementById('config_founder_edition_active').value = data.data.founder_edition_active || '0';
            if (document.getElementById('config_founder_max_seats'))
                document.getElementById('config_founder_max_seats').value = data.data.founder_max_seats || '0';
            if (document.getElementById('config_grace_period_days'))
                document.getElementById('config_grace_period_days').value = data.data.grace_period_days || '0';
        }
    } catch (e) { }
}

async function saveAdminConfig(e) {
    e.preventDefault();
    const fd = new FormData();
    fd.append('action', 'update_config');
    fd.append('configs[founder_edition_active]', document.getElementById('config_founder_edition_active').value);
    fd.append('configs[founder_max_seats]', document.getElementById('config_founder_max_seats').value);
    fd.append('configs[grace_period_days]', document.getElementById('config_grace_period_days').value);

    try {
        const res = await fetch('admin_api.php', { method: 'POST', body: fd, credentials: 'include' });
        const data = await res.json();
        if (data.success) alert("Configurações Globais Salvas!");
        else alert(data.message);
    } catch (e) { }
}

async function searchAdminUsers() {
    const term = document.getElementById('adminUserSearch').value;
    if (!term) return;
    try {
        const res = await fetch(`admin_api.php?action=search_users&term=${encodeURIComponent(term)}`, { credentials: 'include' });
        const data = await res.json();
        if (!data.success) { alert(data.message); return; }

        let html = '';
        data.data.forEach(u => {
            html += `
                <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                    <td style="padding:10px;">${u.id}</td>
                    <td>${u.name}</td>
                    <td>${u.email}</td>
                    <td><span style="background:var(--primary); color:#000; padding:2px 8px; border-radius:10px; font-size:10px;">${u.special_account_type || 'N/A'}</span></td>
                    <td>${u.billing_bypass == 1 ? '<i class="fas fa-check" style="color:var(--primary);"></i>' : '<i class="fas fa-times" style="color:#ef4444;"></i>'}</td>
                    <td>
                        <button onclick="editAdminUser(${u.id}, '${u.name}', '${u.special_account_type || 'interna'}', ${u.billing_bypass || 0}, ${u.custom_plan_id || ''})" class="btn-primary" style="padding:5px 10px; width:auto;"><i class="fas fa-edit"></i> Editar</button>
                    </td>
                </tr>
            `;
        });
        document.getElementById('adminUserTableBody').innerHTML = html;
    } catch (e) { }
}

function editAdminUser(id, name, type, bypass, customPlan) {
    document.getElementById('adminUserEditBox').classList.remove('hidden');
    document.getElementById('lblEditUser').innerText = name + ' (ID: ' + id + ')';
    document.getElementById('editUserId').value = id;
    document.getElementById('editUserType').value = type || 'interna';
    document.getElementById('editUserBypass').value = bypass;
    document.getElementById('editUserCustomPlan').value = customPlan || '';
}

async function saveUserOverride(e) {
    e.preventDefault();
    const fd = new FormData();
    fd.append('action', 'save_user_override');
    fd.append('user_id', document.getElementById('editUserId').value);
    fd.append('special_account_type', document.getElementById('editUserType').value);
    fd.append('billing_bypass', document.getElementById('editUserBypass').value);
    fd.append('custom_plan_id', document.getElementById('editUserCustomPlan').value);

    try {
        const res = await fetch('admin_api.php', { method: 'POST', body: fd, credentials: 'include' });
        const data = await res.json();
        if (data.success) {
            alert("Exceção salva!");
            document.getElementById('adminUserEditBox').classList.add('hidden');
            searchAdminUsers();
        } else { alert(data.message); }
    } catch (e) { }
}

async function removeUserOverride() {
    const id = document.getElementById('editUserId').value;
    if (!id) return;
    if (!confirm("Atenção: Todo bypass e override deste usuário será removido. Deseja continuar?")) return;

    const fd = new FormData();
    fd.append('action', 'remove_user_override');
    fd.append('user_id', id);
    try {
        const res = await fetch('admin_api.php', { method: 'POST', body: fd, credentials: 'include' });
        const data = await res.json();
        if (data.success) {
            alert("Exceção removida com sucesso. Usuário pagará normalmente!");
            document.getElementById('adminUserEditBox').classList.add('hidden');
            searchAdminUsers();
        } else alert(data.message);
    } catch (e) { }
}

// --- SUBDOMAIN MANAGEMENT LOGIC ---

async function loadUserSubdomains() {
    try {
        const res = await fetch('manage_links.php?action=list_subdomains');
        const data = await res.json();
        if (data.success) {
            const selects = ['subdomainSelect', 'qrSubdomainSelect'];
            selects.forEach(id => {
                const el = document.getElementById(id);
                if (!el) return;

                // Limpa e mantém o ROOT
                el.innerHTML = '<option value="ROOT">Nenhum (padrão)</option>';

                data.subdomains.forEach(sub => {
                    const opt = document.createElement('option');
                    opt.value = sub;
                    opt.innerText = sub + '.' + domain;
                    el.appendChild(opt);
                });
            });
        }
    } catch (e) {
        console.error("Erro ao carregar subdomínios:", e);
    }
}

function openAddSubdomainModal() {
    if (!isLoggedIn) return openModal('modalLogin');

    // Verifica permissão antes de abrir (UX preview)
    const usage = userUsageData;

    // Admins não possuem limite de subdomínio
    const isAdmin = usage?.is_admin || false;

    if (!isAdmin && usage && usage.subdomains && usage.subdomains.used >= usage.subdomains.total) {
        openPlanRestrictionModal({
            feature: 'custom_domain',
            title: 'Limite de subdomínios atingido',
            message: 'Você atingiu o limite de subdomínios do seu plano. Faça upgrade para cadastrar novos subdomínios.',
            ctaText: 'Fazer upgrade agora'
        });
        return;
    }

    openModal('modalAddSubdomain');
}

async function handleAddSubdomain(e) {
    if (e) e.preventDefault();
    if (!guardTeamEditPermission()) return;
    const sub = document.getElementById('newSubdomain').value.toLowerCase().trim();
    if (!sub) return;

    const fd = new FormData();
    fd.append('action', 'add_subdomain');
    fd.append('subdomain', sub);

    try {
        const res = await fetch('manage_links.php', { method: 'POST', body: fd, credentials: 'include' });
        const data = await res.json();
        if (data.success) {
            Swal.fire('Sucesso!', 'Subdomínio cadastrado com sucesso!', 'success');
            closeModals();
            document.getElementById('newSubdomain').value = '';
            await loadUserSubdomains();
            loadUsageSummary();
        } else {
            if (data.error === 'quota_exceeded' || data.error === 'feature_locked') {
                openPlanRestrictionModal({
                    feature: 'custom_domain',
                    title: data.error === 'quota_exceeded' ? 'Limite de subdomínios atingido' : 'Subdomínio indisponível no plano',
                    message: data.message || 'Seu plano atual não permite cadastrar subdomínio.',
                    ctaText: 'Ver planos'
                });
                return;
            }
            Swal.fire('Atenção', data.message || 'Não foi possível cadastrar subdomínio.', 'warning');
        }
    } catch (e) {
        console.error("Erro ao adicionar subdomínio:", e);
    }
}

function updateShortPreview() {
    // Placeholder para futuras atualizações de preview em tempo real no dashboard home
}

// --- GESTÃO DE EQUIPES ---
function getTeamProfileLabel(profile) {
    const p = String(profile || '').toLowerCase();
    if (p === 'owner') return 'Proprietário';
    if (p === 'administrator') return 'Administrador';
    if (p === 'viewer') return 'Visualizador';
    return 'Editor';
}

function buildTeamProfileOptions(currentProfile) {
    const current = String(currentProfile || 'editor').toLowerCase();
    const options = [
        { value: 'administrator', label: 'Administrador' },
        { value: 'editor', label: 'Editor' },
        { value: 'viewer', label: 'Visualizador' }
    ];
    return options.map(opt => `<option value="${opt.value}" ${opt.value === current ? 'selected' : ''}>${opt.label}</option>`).join('');
}

async function loadTeamMembers() {
    try {
        const res = await fetch('manage_teams.php?action=list');
        const data = await res.json();

        if (data.success) {
            const list = document.getElementById('teamMembersList');
            const inviteInput = document.getElementById('inviteEmail');
            const inviteButton = document.getElementById('btnInviteMember');
            if (!list) return;

            list.innerHTML = '';

            const canManageMembers = data.can_manage_members === true;
            if (data.members.length === 0) {
                list.innerHTML = '<tr><td colspan="3" style="text-align:center; padding: 20px; color: #94a3b8;">Nenhum membro na equipe.</td></tr>';
            } else {
                data.members.forEach(m => {
                    const isOwner = String(m.profile || '').toLowerCase() === 'owner' || m.role === 'proprietario';
                    const canRemove = canManageMembers && !isOwner;
                    const canEditProfile = canManageMembers && !isOwner;
                    const profileValue = String(m.profile || 'editor').toLowerCase();
                    const profileLabel = m.profile_label || getTeamProfileLabel(profileValue);

                    const row = document.createElement('tr');
                    row.style.borderBottom = '1px solid rgba(255,255,255,0.05)';
                    row.innerHTML = `
                        <td style="padding: 12px;">
                            <div style="font-weight: 500;">${m.name}</div>
                            <div style="font-size: 0.8rem; color: #94a3b8;">${m.email}</div>
                        </td>
                        <td style="padding: 12px;">
                            ${canEditProfile
                            ? `<select onchange="updateTeamMemberProfile(${Number(m.id)}, this.value)" style="background:rgba(255,255,255,0.05); color:var(--text); border:1px solid rgba(255,255,255,0.1); border-radius:8px; padding:6px 8px; font-size:12px;">
                                        ${buildTeamProfileOptions(profileValue)}
                                   </select>`
                            : `<span class="badge" style="background: ${isOwner ? 'var(--primary-surface-strong)' : 'var(--interactive-soft)'}; color: ${isOwner ? 'var(--primary)' : 'var(--text-dim)'}; padding: 4px 8px; border-radius: 6px; font-size: 0.75rem; text-transform: uppercase;">
                                        ${profileLabel}
                                   </span>`
                        }
                        </td>
                        <td style="padding: 12px; text-align: right;">
                            ${canRemove ? `<button class="btn-icon" onclick="removeTeamMember(${m.id})" title="Remover" style="color: #ef4444; background: none; border: none; cursor: pointer;"><i class="fas fa-user-minus"></i></button>` : ''}
                        </td>
                    `;
                    list.appendChild(row);
                });
            }

            // Atualiza matriz de uso
            const total = data.max_members || 1;
            const used = data.members.length;
            const percent = Math.min(100, (used / total) * 100);

            const usageText = document.getElementById('teamUsageText');
            const usageBar = document.getElementById('teamUsageBar');

            if (usageText) usageText.innerText = `${used} de ${total} membros utilizados no seu plano.`;
            if (usageBar) {
                usageBar.style.width = percent + '%';
                usageBar.style.background = percent >= 100 ? '#ef4444' : 'var(--primary)';
            }

            if (inviteInput) {
                inviteInput.disabled = data.team_feature_enabled === false || data.can_invite === false;
            }
            if (inviteButton) {
                inviteButton.disabled = data.team_feature_enabled === false || data.can_invite === false;
            }
        }
    } catch (e) {
        console.error("Erro ao carregar equipe:", e);
    }
}

async function updateTeamMemberProfile(userId, profile) {
    try {
        const fd = new FormData();
        fd.append('action', 'update_member_role');
        fd.append('user_id', String(userId));
        fd.append('profile', String(profile || 'editor'));

        const res = await fetch('manage_teams.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (!data.success) {
            Swal.fire('Erro', data.message || 'Não foi possível atualizar o perfil.', 'error');
            await loadTeamMembers();
            return;
        }
        Swal.fire('Perfil atualizado', data.message || 'Permissão do membro atualizada com sucesso.', 'success');
        await loadTeamMembers();
    } catch (e) {
        Swal.fire('Erro', 'Falha de comunicação ao atualizar perfil.', 'error');
        await loadTeamMembers();
    }
}

async function handleInviteMember(e) {
    if (e) e.preventDefault();
    if (!canCurrentUserManageMembers()) {
        showViewerPermissionMessage('Seu perfil na equipe não permite convidar membros.');
        return;
    }
    const emailInput = document.getElementById('inviteEmail');
    if (!emailInput) return;
    const email = emailInput.value;

    try {
        const formData = new FormData();
        formData.append('action', 'invite');
        formData.append('email', email);

        const res = await fetch('manage_teams.php', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();

        if (data.success) {
            Swal.fire('Sucesso!', data.message, 'success');
            emailInput.value = '';
            loadTeamMembers();
        } else {
            if (data.error === 'quota_exceeded' || data.error === 'feature_locked') {
                openPlanRestrictionModal({
                    feature: 'team',
                    title: data.error === 'quota_exceeded' ? 'Limite de equipe atingido' : 'Colaboração bloqueada no plano',
                    message: data.message || 'Seu plano atual não permite convidar membros.',
                    ctaText: 'Ver planos'
                });
                return;
            }
            Swal.fire('Erro', data.message, 'error');
        }
    } catch (e) {
        Swal.fire('Erro', 'Falha na comunicação com o servidor.', 'error');
    }
}

async function removeTeamMember(userId) {
    const result = await Swal.fire({
        title: 'Remover Membro?',
        text: "Este usuário perderá o acesso compartilhado aos links da equipe.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'Sim, remover!',
        cancelButtonText: 'Cancelar'
    });

    if (result.isConfirmed) {
        try {
            const formData = new FormData();
            formData.append('action', 'remove_member');
            formData.append('user_id', userId);

            const res = await fetch('manage_teams.php', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();

            if (data.success) {
                Swal.fire('Removido!', data.message, 'success');
                loadTeamMembers();
            } else {
                Swal.fire('Erro', data.message, 'error');
            }
        } catch (e) {
            Swal.fire('Erro', 'Falha ao remover membro.', 'error');
        }
    }
}

function setDashboardLoading(isLoading) {
    document.querySelectorAll('.dashboard-skeleton').forEach(el => {
        el.classList.toggle('is-loading', isLoading);
    });
}

function animateCounter(el, targetValue, formatter = (v) => v.toString()) {
    if (!el) return;
    const end = Number(targetValue || 0);
    const start = Number((el.dataset.rawValue || '0').replace(/[^\d.-]/g, '')) || 0;
    const startTime = performance.now();
    const duration = 450;

    const frame = (now) => {
        const progress = Math.min(1, (now - startTime) / duration);
        const eased = 1 - Math.pow(1 - progress, 3);
        const value = start + ((end - start) * eased);
        el.textContent = formatter(value);
        if (progress < 1) {
            requestAnimationFrame(frame);
            return;
        }
        el.dataset.rawValue = String(end);
    };

    requestAnimationFrame(frame);
}

function renderInsightList(elementId, items, emptyText) {
    const el = document.getElementById(elementId);
    if (!el) return;
    if (!Array.isArray(items) || items.length === 0) {
        el.innerHTML = `<li>${emptyText}</li>`;
        return;
    }
    el.innerHTML = items.map(item => `<li>${item}</li>`).join('');
}

function isBusinessPlanName(planName) {
    const normalized = String(planName || '').toLowerCase();
    return normalized.includes('business') || normalized.includes('empresarial');
}

function userHasDashboardAIAutoAccess(planName = '') {
    if (currentUserIsAdmin) return true;
    if (isBusinessPlanName(planName)) return true;
    if (userUsageData?.is_admin) return true;
    if (isBusinessPlanName(userUsageData?.plan_name || '')) return true;
    return false;
}

function setDashboardAIButtonState(planName = '') {
    const button = document.getElementById('btnGenerateAIInsights');
    if (!button) return;
    const autoAccess = userHasDashboardAIAutoAccess(planName);
    if (autoAccess) {
        button.classList.add('hidden');
        return;
    }
    button.classList.remove('hidden');
    button.disabled = false;
    button.innerHTML = '<i class="fas fa-robot"></i> Gerar Insights com IA';
}

function typeTextEffect(element, text, speed = 16) {
    if (!element) return;
    const finalText = String(text || '').trim();
    if (finalText === '') {
        element.textContent = 'Sem insight de IA disponível para este período.';
        return;
    }
    element.textContent = '';
    let idx = 0;
    const timer = setInterval(() => {
        element.textContent += finalText.charAt(idx);
        idx += 1;
        if (idx >= finalText.length) clearInterval(timer);
    }, speed);
}

function renderDashboardAI(insight, planName = '') {
    const box = document.getElementById('dashboardAIBox');
    const textEl = document.getElementById('dashboardAIText');
    if (!box || !textEl) return;

    const aiAllowed = Boolean(insight?.ai_allowed);
    const aiSummary = String(insight?.ai_summary || '').trim();
    const canAutoCall = userHasDashboardAIAutoAccess(planName);

    if (!aiAllowed && !canAutoCall && !dashboardAIRequested) {
        box.classList.remove('hidden');
        textEl.textContent = 'Clique em "Gerar Insights com IA" para receber uma leitura inteligente do seu relatório.';
        return;
    }

    box.classList.remove('hidden');
    if (!aiSummary) {
        textEl.textContent = 'Não foi possível gerar insights de IA agora. Tente novamente em instantes.';
        return;
    }
    typeTextEffect(textEl, aiSummary, 12);
}

function updateDashboardTimestamp() {
    const tag = document.getElementById('dashboardLastUpdated');
    if (!tag) return;
    const now = new Date();
    tag.innerHTML = `<i class="fas fa-circle"></i> atualizado às ${now.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })}`;
}

function renderDashboardTable(recentActivity) {
    const tableBody = document.getElementById('dashboardRecentActivity');
    if (!tableBody) return;
    const rows = Array.isArray(recentActivity) ? recentActivity : [];

    if (rows.length === 0) {
        tableBody.innerHTML = '<tr><td colspan="3" class="dashboard-empty">Nenhum clique detectado ainda.</td></tr>';
        return;
    }

    tableBody.innerHTML = rows.map(act => {
        const dt = new Date(act.clicked_at);
        const dateStr = dt.toLocaleDateString('pt-BR');
        const timeStr = dt.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
        const fullPath = (act.subdomain && act.subdomain !== 'ROOT' ? act.subdomain + '.' : '') + domain + '/' + act.path;
        const loc = `${act.visitor_city && act.visitor_city !== 'Desconhecido' ? act.visitor_city + ', ' : ''}${act.visitor_region || 'Desconhecido'}`;
        return `
            <tr class="dashboard-activity-row">
                <td>
                    <div class="dashboard-location-cell">
                        <img src="https://flagcdn.com/24x18/${act.visitor_country ? act.visitor_country.toLowerCase() : 'unknown'}.png" onerror="this.src='https://cdn-icons-png.flaticon.com/512/814/814513.png'" class="dashboard-flag" title="${act.visitor_country}">
                        <span>${loc}</span>
                    </div>
                </td>
                <td><span class="dashboard-link-path">${fullPath}</span></td>
                <td class="dashboard-time-cell">
                    <span class="dashboard-time-main">${timeStr}</span>
                    <span class="dashboard-time-date">${dateStr}</span>
                </td>
            </tr>
        `;
    }).join('');
}

function setupDashboardControls() {
    const periodSelect = document.getElementById('dashboardPeriod');
    if (periodSelect && !periodSelect.dataset.bound) {
        periodSelect.dataset.bound = '1';
        periodSelect.addEventListener('change', () => {
            loadDashboardData();
        });
    }

    const aiButton = document.getElementById('btnGenerateAIInsights');
    if (aiButton && !aiButton.dataset.bound) {
        aiButton.dataset.bound = '1';
        aiButton.addEventListener('click', async () => {
            dashboardAIRequested = true;
            aiButton.disabled = true;
            aiButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Gerando...';
            await loadDashboardData();
            aiButton.disabled = false;
            aiButton.innerHTML = '<i class="fas fa-robot"></i> Gerar Insights com IA';
        });
    }
}

function createDashboardChart(canvasId, key, config) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    if (charts[key]) charts[key].destroy();
    charts[key] = new Chart(ctx, config);
}

function trackDashboardUsage(eventName, payload = {}) {
    if (!navigator.sendBeacon) return;
    const body = JSON.stringify({
        event: eventName,
        payload,
        timestamp: new Date().toISOString()
    });
    navigator.sendBeacon('track_dashboard_usage.php', new Blob([body], { type: 'application/json' }));
}

/**
 * Carrega dados consolidados para o Painel de Controle
 */
async function loadDashboardData() {
    if (!isLoggedIn) return;
    setupDashboardControls();
    setDashboardLoading(true);

    try {
        const selectedPeriod = document.getElementById('dashboardPeriod')?.value || '14';
        const shouldCallAI = dashboardAIRequested || userHasDashboardAIAutoAccess();
        const res = await fetch(`dashboard.php?version=2&period=${encodeURIComponent(selectedPeriod)}&ai=${shouldCallAI ? '1' : '0'}&t=${new Date().getTime()}`, { credentials: 'include' });
        const data = await res.json();
        if (!data.success) return;

        const k = data.kpis || {};
        animateCounter(document.getElementById('kpi-activity'), Number(k.activity || 0), (v) => Math.round(v).toString());
        animateCounter(document.getElementById('kpi-reach'), Number(k.reach || 0), (v) => Math.round(v).toLocaleString('pt-BR'));
        animateCounter(document.getElementById('kpi-engagement-24h'), Number(k.engagement_24h || 0), (v) => Math.round(v).toString());
        animateCounter(document.getElementById('kpi-engagement-1h'), Number(k.engagement_1h || 0), (v) => Math.round(v).toString());
        const teamEl = document.getElementById('kpi-team');
        if (teamEl) teamEl.innerText = `${k.team_count || 0}/${k.team_limit || 0}`;

        const banner = document.getElementById('dashboardUpgradeBanner');
        if ((k.plan_name || '').toLowerCase() === 'free') banner?.classList.remove('hidden');
        else banner?.classList.add('hidden');
        setDashboardAIButtonState(k.plan_name || '');

        const insight = data.insights || {};
        document.getElementById('funnelCtr').innerText = `${Number(insight.funnel_health?.ctr || 0).toFixed(1)}%`;
        document.getElementById('funnelConversionRate').innerText = `${Number(insight.funnel_health?.conversion_rate || 0).toFixed(1)}%`;
        document.getElementById('funnelAvgTime').innerText = `${Number(insight.funnel_health?.avg_time_to_convert_minutes || 0).toFixed(0)} min`;
        document.getElementById('funnelGrowth').innerText = `${Number(insight.funnel_health?.period_growth || 0).toFixed(1)}%`;

        renderInsightList('dashboardAlerts', insight.alerts, 'Nenhum alerta no momento.');
        renderInsightList('dashboardRecommendations', insight.recommendations, 'Sem recomendações no momento.');
        renderDashboardAI(insight, k.plan_name || '');

        createDashboardChart('chartTrend', 'dashboardTrend', {
            type: 'line',
            data: {
                labels: (data.charts?.trend || []).map(d => new Date(d.day + 'T00:00:00').toLocaleDateString('pt-BR', { day: '2-digit', month: 'short' })),
                datasets: [{
                    label: 'Cliques',
                    data: (data.charts?.trend || []).map(d => d.clicks),
                    borderColor: getBrandTokens().primary,
                    backgroundColor: getBrandTokens().chartFillPrimary,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 3,
                    borderWidth: 2
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
        });

        createDashboardChart('chartTopLinks', 'dashboardTop', {
            type: 'bar',
            data: {
                labels: (data.charts?.top_links || []).map(l => l.title || l.path),
                datasets: [{
                    label: 'Cliques',
                    data: (data.charts?.top_links || []).map(l => l.clicks),
                    backgroundColor: hexToRgba(getBrandTokens().accent, 0.8),
                    borderRadius: 6
                }]
            },
            options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
        });

        createDashboardChart('chartMixOrigin', 'dashboardMix', {
            type: 'doughnut',
            data: {
                labels: (data.charts?.mix_origin || []).map(m => m.source === 'qr' ? 'QR Code' : 'Digital'),
                datasets: [{
                    data: (data.charts?.mix_origin || []).map(m => m.total),
                    backgroundColor: chartVisualTheme.colors.slice(0, 3),
                    borderWidth: 0
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } }, cutout: '70%' }
        });

        createDashboardChart('chartConversionFunnel', 'dashboardFunnel', {
            type: 'bar',
            data: {
                labels: (data.charts?.conversion_funnel || []).map(f => f.label),
                datasets: [{ data: (data.charts?.conversion_funnel || []).map(f => f.value), backgroundColor: chartVisualTheme.colors.slice(0, 3) }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
        });

        createDashboardChart('chartConversionsDaily', 'dashboardConversionsDaily', {
            type: 'line',
            data: {
                labels: (data.charts?.conversions_daily || []).map(d => new Date(d.day + 'T00:00:00').toLocaleDateString('pt-BR', { day: '2-digit', month: 'short' })),
                datasets: [{ label: 'Conversões', data: (data.charts?.conversions_daily || []).map(d => d.total), borderColor: getBrandTokens().accent, tension: 0.35 }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
        });

        createDashboardChart('chartHourlyHeatmap', 'dashboardHourlyHeatmap', {
            type: 'bar',
            data: {
                labels: (data.charts?.hourly_heatmap || []).map(h => `${h.hour}h`),
                datasets: [{ label: 'Cliques', data: (data.charts?.hourly_heatmap || []).map(h => h.clicks), backgroundColor: '#3b82f6' }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
        });

        createDashboardChart('chartCampaignEfficiency', 'dashboardCampaignEfficiency', {
            type: 'bar',
            data: {
                labels: (data.charts?.campaign_efficiency || []).map(c => c.campaign),
                datasets: [{ label: 'Eficiência %', data: (data.charts?.campaign_efficiency || []).map(c => c.efficiency), backgroundColor: '#f6b64f', borderRadius: 6 }]
            },
            options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
        });

        renderDashboardTable(data.recent_activity || []);
        updateDashboardTimestamp();
        trackDashboardUsage('dashboard_view', { period: selectedPeriod });
    } catch (e) {
        console.error("Erro ao carregar dashboard", e);
    } finally {
        setDashboardLoading(false);
    }
}

// --- CONTROLE DE ATUALIZAÇÃO AUTOMÁTICA (TEMPO REAL) ---
function setupAutoRefresh(type) {
    stopAutoRefresh(); // Limpa qualquer timer anterior
    console.log(`Iniciando atualização em tempo real para: ${type}`);

    analyticsRefreshTimer = setInterval(() => {
        if (!isLoggedIn) {
            stopAutoRefresh();
            return;
        }

        const activeSection = localStorage.getItem('activeSection');
        if (type === 'dashboard' && activeSection === 'section-dashboard') {
            loadDashboardData();
        } else if (type === 'analytics' && activeSection === 'section-analytics') {
            loadAnalytics();
        } else {
            stopAutoRefresh();
        }
    }, 30000); // 30 segundos
}

function stopAutoRefresh() {
    if (analyticsRefreshTimer) {
        console.log("Parando atualizações automáticas.");
        clearInterval(analyticsRefreshTimer);
        analyticsRefreshTimer = null;
    }
}

/* =========================================
   LEGAL MODAL & COMPLIANCE LOGIC
   ========================================= */

const legalContents = {
    terms: {
        title: "Termos de Uso",
        body: `<h3>1. Aceitação e vigência</h3><p>Ao utilizar o linkneo.pro (o “Site” ou “Plataforma”), o usuário declara ter lido e compreendido estes Termos de Uso e a Política de Privacidade associada. O acesso e/ou cadastro significam concordância integral. Caso não concorde, o usuário deve cessar o uso imediatamente. A linkneo.pro reserva‑se o direito de alterar estes termos a qualquer momento; novas versões serão publicadas no site e entrarão em vigor na data indicada. O uso continuado após a publicação significa aceitação.</p><h3>2. Definições</h3><p><b>Usuário:</b> pessoa natural ou jurídica que cria conta ou utiliza serviços de encurtamento de links, geração de QR codes e criação de páginas “bio” disponibilizados pela linkneo.pro.</p><p><b>Serviços:</b> conjunto de funcionalidades oferecidas pela linkneo.pro, incluindo encurtamento de URL, geração de QR Codes dinâmicos, criação de páginas de destino personalizadas (“bio pages”), monitoramento e análise de tráfego, gestão de campanhas e equipes.</p><p><b>Plano Free ou Assinatura:</b> modelo de oferta de serviços gratuitos com limitações e planos pagos (mensais ou anuais) com recursos ampliados.</p><h3>3. Descrição dos Serviços</h3><p>A plataforma permite transformar links longos em URLs curtas personalizáveis, criar QR codes estáticos ou dinâmicos, associar vários links a uma “bio page” e acompanhar métricas de cliques e conversões. Recursos adicionais incluem teste A/B entre destinos, gestão de equipes, campanhas de marketing e relatórios analíticos. A linkneo.pro não hospeda o conteúdo das páginas externas; apenas redireciona os usuários para os endereços informados.</p><h3>4. Cadastro e responsabilidade do usuário</h3><p>Para utilizar determinadas funcionalidades, o usuário deve criar conta informando dados verdadeiros e completos (nome, e‑mail, telefone e dados de faturamento). Ele é responsável por manter confidenciais suas credenciais de acesso. É proibido criar contas em nome de terceiros sem autorização ou repassar a senha. Quaisquer ações realizadas com o login do usuário serão consideradas de sua exclusiva responsabilidade.</p><p>O usuário declara ser civilmente capaz. Caso represente pessoa jurídica, declara ter poderes para aceitar estes termos. A plataforma não é destinada a menores de 18 anos; caso haja uso por menores, deverá ocorrer com autorização e supervisão dos responsáveis legais.</p><h3>5. Uso aceitável e proibições</h3><p>A linkneo.pro destina‑se a fins lícitos. É proibido:</p><ul><li>Utilizar links para atividades ilegais, phishing, fraude, engenharia social ou coleta indevida de dados.</li><li>Distribuir malware, vírus ou quaisquer códigos maliciosos.</li><li>Enviar mensagens em massa não solicitadas (spam).</li><li>Publicar ou divulgar conteúdo que viole direitos autorais, marcas ou outros direitos de propriedade intelectual.</li><li>Utilizar o serviço para armazenar ou processar dados sensíveis sem base legal apropriada.</li></ul><p>A violação acarretará suspensão ou cancelamento imediato da conta, sem ressarcimento, além de eventuais responsabilidades civis e criminais.</p><h3>6. Planos, assinaturas, pagamentos e cancelamento</h3><p>A linkneo.pro oferece planos gratuitos e planos pagos com renovação automática conforme o ciclo escolhido (mensal ou anual). O processamento dos pagamentos é realizado por parceiros como a plataforma Asaas. Os valores e benefícios de cada plano são divulgados na página de assinaturas e podem sofrer alterações mediante aviso prévio.</p><p>O usuário poderá cancelar a renovação automática a qualquer momento no painel de controle. Em caso de desistência de uma assinatura recém‑contratada, o reembolso seguirá as normas do Código de Defesa do Consumidor (Direito de Arrependimento de 7 dias). Após esse prazo, as cobranças já realizadas não serão reembolsadas. A conta permanecerá ativa até o fim do ciclo já pago.</p><h3>7. Propriedade intelectual</h3><p>Todos os elementos da plataforma (marcas, logotipos, softwares, interface e conteúdos) são de titularidade da linkneo.pro ou licenciados. O usuário tem licença não exclusiva e intransferível para utilizar os serviços conforme estes termos. É proibida a reprodução, distribuição, engenharia reversa ou modificação sem autorização prévia por escrito.</p><h3>8. Conteúdo gerado pelo usuário</h3><p>Ao encurtar um link, criar QR Codes ou páginas “bio”, o usuário declara ter autorização para utilizar o conteúdo de destino e não violar direitos de terceiros. A linkneo.pro não se responsabiliza pela veracidade ou legalidade dos destinos informados, mas poderá remover ou desativar links a qualquer momento em caso de denúncia ou violação destes termos.</p><p>O usuário concede à linkneo.pro licença mundial, gratuita e não exclusiva para hospedar, reproduzir, modificar e exibir o conteúdo estritamente necessário à prestação dos serviços (ex.: geração e hospedagem de páginas “bio” e QR codes), respeitando os limites definidos na Política de Privacidade.</p><h3>9. Limitações de responsabilidade</h3><p>A linkneo.pro envida esforços para manter a plataforma disponível, segura e sem erros, mas não garante que o serviço será ininterrupto ou livre de falhas. A responsabilidade da linkneo.pro limita‑se ao valor efetivamente pago pelo usuário nos últimos 12 meses ou, em caso de plano gratuito, fica restrita a R$0,00. A linkneo.pro não se responsabiliza por danos indiretos, lucros cessantes ou prejuízos decorrentes de:</p><ul><li>Falhas de acesso à internet ou de terceiros.</li><li>Uso inadequado pelo usuário.</li><li>Conteúdos de sites de terceiros acessados via links encurtados.</li></ul><p>A linkneo.pro não garante resultados específicos, sendo o uso dos serviços feito por conta e risco do usuário.</p><h3>10. Suporte e contato</h3><p>Dúvidas, solicitações ou notificações poderão ser encaminhadas pelo canal de suporte indicado na plataforma ou via e‑mail de contato. A linkneo.pro poderá solicitar dados adicionais para validar identidades e atender às solicitações.</p><h3>11. Modificações nos termos</h3><p>Estes termos poderão ser modificados a qualquer momento para refletir mudanças nos serviços, na legislação ou em práticas internas. Alterações materiais serão comunicadas por meio do site ou por e‑mail. Caso o usuário não concorde com as alterações, poderá cancelar a conta; o uso contínuo após a publicação representará aceitação.</p><h3>12. Lei aplicável e foro</h3><p>Estes Termos são regidos pelas leis da República Federativa do Brasil, especialmente a Lei Geral de Proteção de Dados Pessoais – LGPD (Lei nº 13.709/2018) e o Código de Defesa do Consumidor. Fica eleito o foro da Comarca de Brasília/DF, com renúncia a qualquer outro, por mais privilegiado que seja, para dirimir quaisquer controvérsias oriundas destes Termos.</p>`
    },
    privacy: {
        title: "Política de Privacidade",
        body: `<h3>1. Introdução</h3><p>A linkneo.pro valoriza a privacidade de seus usuários e dos visitantes dos links encurtados. Esta Política explica como coletamos, utilizamos, armazenamos e compartilhamos dados pessoais, bem como os direitos dos titulares de acordo com a Lei Geral de Proteção de Dados Pessoais (LGPD). A LGPD protege a liberdade, a privacidade e a personalidade de cada indivíduo. Esta política integra os Termos de Uso e deve ser lida em conjunto.</p><h3>2. Definições</h3><p><b>Dados pessoais:</b> informações relacionadas a pessoa natural identificada ou identificável, como nome, endereço, CPF, e‑mail, IP ou identificadores eletrônicos.</p><p><b>Dados sensíveis:</b> dados que revelam origem racial ou étnica, convicção religiosa, opinião política, saúde ou vida sexual, dados genéticos ou biométricos. A linkneo.pro não solicita dados sensíveis para cadastro.</p><p><b>Controlador:</b> a linkneo.pro, que determina as finalidades e meios de tratamento.</p><p><b>Operador:</b> terceiros que realizam o tratamento de dados em nome da linkneo.pro.</p><h3>3. Dados coletados e como são coletados</h3><p><b>3.1. Dados fornecidos pelo usuário:</b> nome, e‑mail, telefone, senha de acesso e dados de faturamento (CPF ou CNPJ, endereço) para criação da conta e emissão de notas fiscais. Informações inseridas em páginas “bio”, como foto, descrição e links externos.</p><p><b>3.2. Dados coletados automaticamente:</b> cookies para autenticação e manutenção de sessão. Rastreamento de cliques: endereço IP (geolocalização aproximada), tipo de dispositivo, navegador, página de referência e data/hora.</p><p><b>3.3. Dados de terceiros:</b> informações de pagamento tratadas diretamente pelos parceiros (ex.: Asaas). Poderemos receber dados de login social (Google).</p><h3>4. Finalidades e bases legais</h3><p>A linkneo.pro trata dados pessoais para: execução de contrato (prestação dos serviços, cobrança e suporte); cumprimento de obrigações legais (fiscal e regulatória); legítimo interesse (melhoria da plataforma, prevenção de fraudes e marketing interno); e consentimento (newsletters).</p><h3>5. Compartilhamento de dados</h3><p>A linkneo.pro não vende dados pessoais. O compartilhamento ocorre com: prestadores de serviço (pagamentos, hospedagem, marketing); autoridades (quando exigido por lei); e em casos de transferência internacional de dados, garantindo proteção adequada conforme a LGPD.</p><h3>6. Retenção e segurança</h3><p>Implementamos medidas técnicas e administrativas como criptografia TLS/SSL. Os dados são armazenados enquanto houver necessidade para cumprir as finalidades ou obrigações legais. Após o encerramento da conta, manteremos dados pelo prazo necessário para defesa judicial ou cumprimento de obrigações fiscais.</p><h3>7. Direitos dos titulares</h3><p>Nos termos da LGPD, os titulares possuem direitos como: acesso, correção, anonimização, portabilidade, revogação do consentimento e oposição ao tratamento baseado no legítimo interesse. Pedidos podem ser feitos via suporte.</p><h3>8. Cookies e tecnologias de rastreamento</h3><p>Utilizamos cookies para manter sessões, prevenir fraudes, registrar preferências e realizar análises agregadas. O usuário pode configurar seu navegador para recusar cookies.</p><h3>9. Links externos</h3><p>Links encurtados podem direcionar para sites de terceiros com políticas próprias. A linkneo.pro não se responsabiliza pelas práticas de privacidade desses sites.</p><h3>10. Alterações desta política</h3><p>Esta Política pode ser atualizada para refletir mudanças legislativas ou nos serviços. Mudanças substanciais serão informadas aos usuários.</p><h3>11. Contato</h3><p>Dúvidas ou exercício de direitos podem ser encaminhados ao encarregado de dados via e‑mail informado no site.</p><h3>12. Foro aplicável</h3><p>Esta Política é regida pelas leis brasileiras. Fica eleito o foro da Comarca de Brasília/DF.</p>`
    },
    cancellation: {
        title: "Política de Cancelamento",
        body: "<h3>Como cancelar?</h3><p>Você pode cancelar sua assinatura na aba 'Assinatura' do seu painel de controle.</p><h3>Regras de Estorno</h3><ul><li><b>Direito de Arrependimento:</b> Até 7 dias após a primeira compra, você pode solicitar o estorno integral.</li><li><b>Renovações:</b> O cancelamento interrompe cobranças futuras, mas não gera estorno de períodos já iniciados.</li></ul>"
    }
};

function openLegalModal(type, event) {
    if (event) event.preventDefault();
    const content = legalContents[type];
    if (!content) return;

    document.getElementById("legalModalTitle").innerText = content.title;
    document.getElementById("legalModalBody").innerHTML = content.body;

    const modal = document.getElementById("modalLegal");
    modal.classList.remove("hidden");
    // Garantir que o conteúdo interno não esteja oculto por outras funções
    modal.querySelectorAll('.modal-content').forEach(m => m.classList.remove('hidden'));

    document.body.classList.add("modal-open");
}

// Interceptamos o Signup para validar o Checkbox de Termos
const originalHandleAuth = handleAuth;
handleAuth = async function (e, action) {
    if (action === "signup") {
        const termsChecked = document.getElementById("signTerms").checked;
        if (!termsChecked) {
            alert("Você deve aceitar os Termos de Uso e a Política de Privacidade para continuar.");
            return;
        }
    }
    return originalHandleAuth(e, action);
};

// Interceptamos o Checkout Step para validar o Checkbox
const originalGoToCheckoutStep = typeof goToCheckoutStep === "function" ? goToCheckoutStep : null;
if (originalGoToCheckoutStep) {
    window.goToCheckoutStep = function (step) {
        if (step === 2) { // Indo do Passo 1 para o 2
            const checkoutTerms = document.getElementById("checkoutTerms").checked;
            if (!checkoutTerms) {
                alert("Você deve concordar com os Termos e a Política de Cancelamento para prosseguir.");
                return;
            }
        }
        return originalGoToCheckoutStep(step);
    };
}
