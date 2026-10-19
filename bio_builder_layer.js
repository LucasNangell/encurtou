const BIO_PAGE_TYPE_LABELS = {
    bio: 'Bio',
    landing: 'Landing',
    blog: 'Blog',
    campaign: 'Campanha'
};

const BIO_STATUS_LABELS = {
    published: 'Publicado',
    draft: 'Rascunho'
};

const BIO_TEMPLATE_ENRICHMENTS = {
    1: {
        category: 'Creator',
        description: 'Estrutura central com foco em presença pessoal e links de conversão.',
        ideal_for: 'Criadores, experts, consultores',
        page_types: ['bio', 'landing']
    },
    2: {
        category: 'Premium',
        description: 'Visual refinado com cara de landing premium e alto contraste.',
        ideal_for: 'Captura, lançamentos, vitrines premium',
        page_types: ['bio', 'landing', 'campaign'],
        premium: true
    },
    3: {
        category: 'Editorial',
        description: 'Hero forte e narrativa visual para experts e páginas com conteúdo.',
        ideal_for: 'Experts, mini-sites, campanhas',
        page_types: ['landing', 'campaign', 'blog'],
        premium: true
    },
    4: {
        category: 'Commerce',
        description: 'Blocos em grade para catálogos, links rápidos e navegação visual.',
        ideal_for: 'Lojas, catálogos, vitrines',
        page_types: ['bio', 'landing', 'campaign']
    },
    5: {
        category: 'Institucional',
        description: 'Aparência corporativa para negócio local, serviços e páginas institucionais.',
        ideal_for: 'Empresas, restaurantes, atendimento local',
        page_types: ['landing', 'campaign']
    },
    6: {
        category: 'Impacto',
        description: 'Layout com profundidade e destaque em CTAs principais.',
        ideal_for: 'Ofertas, campanhas, lançamento',
        page_types: ['landing', 'campaign'],
        premium: true
    },
    7: {
        category: 'Bold',
        description: 'Linguagem intensa para campanhas, creators e páginas ousadas.',
        ideal_for: 'Creators, evento, campanha',
        page_types: ['bio', 'campaign']
    },
    8: {
        category: 'Soft UI',
        description: 'Estética clean e suave para marca pessoal e serviços criativos.',
        ideal_for: 'Portfólio, design, branding',
        page_types: ['bio', 'landing']
    },
    9: {
        category: 'Noir',
        description: 'Minimalismo escuro para posicionamento autoral e conteúdo.',
        ideal_for: 'Portfólio, blog, autoridade',
        page_types: ['bio', 'blog'],
        premium: true
    },
    10: {
        category: 'Conversão',
        description: 'Layout focado em coleta de respostas com formulário integrado.',
        ideal_for: 'Pesquisas, leads, cadastros e check-ins',
        page_types: ['bio', 'landing', 'campaign'],
        premium: false
    },
    11: {
        category: 'Executivo',
        description: 'Visual premium para apresentação institucional, serviços e autoridade.',
        ideal_for: 'Consultorias, escritórios e empresas B2B',
        page_types: ['bio', 'landing', 'campaign'],
        premium: true
    },
    12: {
        category: 'Portfólio',
        description: 'Template sofisticado para cases, projetos e provas visuais.',
        ideal_for: 'Designers, criadores, arquitetos e estúdios',
        page_types: ['bio', 'landing', 'blog'],
        premium: false
    },
    13: {
        category: 'Produto',
        description: 'Estrutura orientada a conversão com foco em oferta principal.',
        ideal_for: 'Infoprodutos, lançamentos e vendas diretas',
        page_types: ['landing', 'campaign'],
        premium: true
    },
    14: {
        category: 'Minimal',
        description: 'Visual clean com capa suave e área de perfil. Foto recomendada: capa 16:9 e perfil 1:1.',
        ideal_for: 'Designers, criadores e marca pessoal',
        page_types: ['bio', 'landing'],
        premium: true
    },
    15: {
        category: 'Jurídico',
        description: 'Template elegante para presença profissional. Foto recomendada: perfil 1:1.',
        ideal_for: 'Advocacia, consultoria e serviços premium',
        page_types: ['bio', 'landing', 'campaign'],
        premium: true
    },
    16: {
        category: 'Impacto',
        description: 'Modelo de alto contraste para campanhas fortes. Foto recomendada: perfil 1:1.',
        ideal_for: 'Campanhas, lançamentos e creators',
        page_types: ['bio', 'campaign', 'landing'],
        premium: true
    },
    17: {
        category: 'Editorial',
        description: 'Estética sofisticada com foco em imagem e serviços. Foto recomendada: capa 4:5.',
        ideal_for: 'Beleza, moda, estética e branding',
        page_types: ['bio', 'landing'],
        premium: true
    },
    18: {
        category: 'Creator',
        description: 'Template vibrante com identidade digital. Foto recomendada: capa 16:9 e perfil 1:1.',
        ideal_for: 'Marketing, social media e creators',
        page_types: ['bio', 'landing', 'campaign'],
        premium: true
    }
};

const BIO_BLOCK_LIBRARY = [
    { type: 'heading', title: 'Título', description: 'Abre uma nova seção com título e subtítulo.' },
    { type: 'text', title: 'Texto', description: 'Conteúdo editorial, manifesto ou descrição longa.' },
    { type: 'highlight', title: 'Destaque', description: 'Bloco premium para proposta de valor ou oferta.' },
    { type: 'cta', title: 'CTA', description: 'Chamada principal com botão dedicado.' },
    { type: 'links', title: 'Grupo de botões', description: 'Seção de botões gerenciada pelo painel de links.' },
    { type: 'social', title: 'Redes sociais', description: 'Grade de redes integrada ao painel de ações.' },
    { type: 'cards', title: 'Cards', description: 'Serviços, produtos, etapas ou recursos.' },
    { type: 'faq', title: 'FAQ', description: 'Perguntas frequentes e objeções.' },
    { type: 'gallery', title: 'Galeria', description: 'Imagens, banners e provas visuais.' },
    { type: 'posts', title: 'Posts', description: 'Lista de conteúdos, artigos ou novidades.' },
    { type: 'contact', title: 'Contato', description: 'Telefone, e-mail, endereço ou canais de suporte.' },
    { type: 'schedule', title: 'Agenda', description: 'Horários, etapas de evento ou cronograma.' },
    { type: 'video', title: 'Vídeo / Embed', description: 'Vídeo principal incorporado.' },
    { type: 'divider', title: 'Divisor', description: 'Separador para organizar a leitura.' },
    { type: 'form', title: 'Formulário', description: 'Coleta respostas com vários tipos de campo (uma seção por página).' }
];

const BIO_FORM_FIELD_TYPES = [
    { value: 'short_text', label: 'Texto curto' },
    { value: 'long_text', label: 'Texto longo' },
    { value: 'email', label: 'E-mail' },
    { value: 'phone', label: 'Telefone' },
    { value: 'number', label: 'Número' },
    { value: 'date', label: 'Data' },
    { value: 'time', label: 'Hora' },
    { value: 'url', label: 'URL' },
    { value: 'select', label: 'Lista (select)' },
    { value: 'radio', label: 'Opção única (radio)' },
    { value: 'checkbox', label: 'Caixa única (sim/não)' },
    { value: 'multiselect', label: 'Múltipla escolha' }
];

const EMBED_PROVIDER_CONFIG = {
    youtube: {
        label: 'YouTube',
        placeholder: 'https://www.youtube.com/watch?v=... ou https://youtu.be/...',
        allow: 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share',
        referrerPolicy: 'strict-origin-when-cross-origin',
        sandbox: 'allow-scripts allow-same-origin allow-presentation allow-popups'
    },
    google_maps: {
        label: 'Google Maps',
        placeholder: 'https://www.google.com/maps/... ou link compartilhado do Maps',
        allow: 'geolocation; fullscreen',
        referrerPolicy: 'no-referrer-when-downgrade',
        sandbox: 'allow-scripts allow-same-origin allow-popups'
    },
    spotify: {
        label: 'Spotify',
        placeholder: 'https://open.spotify.com/playlist/... (ou track/album/artist/show/episode)',
        allow: 'autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture',
        referrerPolicy: 'strict-origin-when-cross-origin',
        sandbox: 'allow-scripts allow-same-origin allow-popups'
    },
    vimeo: {
        label: 'Vimeo',
        placeholder: 'https://vimeo.com/...',
        allow: 'autoplay; fullscreen; picture-in-picture',
        referrerPolicy: 'strict-origin-when-cross-origin',
        sandbox: 'allow-scripts allow-same-origin allow-presentation'
    }
};

const EXTRA_GOAL_TEMPLATES = {
    institucional: {
        id: 'goal-institucional',
        name: 'Modelo: Institucional',
        desc: 'Apresente empresa, serviços e canais de contato de forma clara.',
        page_type: 'landing',
        theme: { bg: '#0b1220', btn: '#60a5fa', text: '#f8fafc', style: 'rounded', template_id: 5 },
        links: [
            { title: 'Conheça nossos serviços', url: 'https://...', link_type: 'link', is_featured: 1 },
            { title: 'Solicitar proposta', url: 'https://...', link_type: 'link' },
            { title: 'Falar no WhatsApp', url: '', link_type: 'social', social_network: 'whatsapp', social_value: '11999999999' }
        ]
    },
    leads: {
        id: 'goal-leads',
        name: 'Modelo: Captura de Leads',
        desc: 'Oferta, benefício, prova e CTA para captar contatos.',
        page_type: 'landing',
        theme: { bg: '#111827', btn: '#f59e0b', text: '#f8fafc', style: 'pill', template_id: 6 },
        links: [
            { title: 'Quero receber o material', url: 'https://...', link_type: 'link', is_featured: 1 },
            { title: 'Entrar no grupo VIP', url: '', link_type: 'social', social_network: 'whatsapp', social_value: '11999999999' }
        ]
    },
    blog: {
        id: 'goal-blog',
        name: 'Modelo: Blog / Conteúdo',
        desc: 'Estruture uma página editorial com artigos, destaques e assinatura.',
        page_type: 'blog',
        theme: { bg: '#09090b', btn: '#f8fafc', text: '#f8fafc', style: 'outline', template_id: 9 },
        links: [
            { title: 'Artigo em destaque', url: 'https://...', link_type: 'link', is_featured: 1 },
            { title: 'Newsletter semanal', url: 'https://...', link_type: 'link' },
            { title: 'Instagram', url: '', link_type: 'social', social_network: 'instagram', social_value: '@perfil' }
        ]
    },
    servicos: {
        id: 'goal-servicos',
        name: 'Modelo: Produtos / Serviços',
        desc: 'Apresente serviços, diferenciais e CTA comercial.',
        page_type: 'landing',
        theme: { bg: '#0f172a', btn: '#14b8a6', text: '#ecfeff', style: 'rounded', template_id: 5 },
        links: [
            { title: 'Ver soluções', url: 'https://...', link_type: 'link', is_featured: 1 },
            { title: 'Agendar diagnóstico', url: 'https://...', link_type: 'link' },
            { title: 'Falar com especialista', url: '', link_type: 'social', social_network: 'whatsapp', social_value: '11999999999' }
        ]
    },
    campanha: {
        id: 'goal-campanha',
        name: 'Modelo: Central de Campanha',
        desc: 'Reúna anúncios, materiais, páginas e provas em um hub de campanha.',
        page_type: 'campaign',
        theme: { bg: '#111111', btn: '#ff6b00', text: '#ffffff', style: 'solid', template_id: 7 },
        links: [
            { title: 'Oferta principal', url: 'https://...', link_type: 'link', is_featured: 1 },
            { title: 'Ver prova social', url: 'https://...', link_type: 'link' },
            { title: 'Instagram da campanha', url: '', link_type: 'social', social_network: 'instagram', social_value: '@campanha' }
        ]
    },
    portifolio: {
        id: 'goal-portifolio',
        name: 'Modelo: Portfólio Editorial',
        desc: 'Mostre cases, galeria e contato em uma página com presença autoral.',
        page_type: 'bio',
        theme: { bg: '#111827', btn: '#f43f5e', text: '#ffffff', style: 'outline', template_id: 8 },
        links: [
            { title: 'Ver portfólio completo', url: 'https://...', link_type: 'link', is_featured: 1 },
            { title: 'Behance', url: '', link_type: 'social', social_network: 'behance', social_value: 'seuperfil' },
            { title: 'Agendar conversa', url: '', link_type: 'social', social_network: 'whatsapp', social_value: '11999999999' }
        ]
    }
};

const goalTemplatesRef = (typeof globalThis.GOAL_TEMPLATES === 'object' && globalThis.GOAL_TEMPLATES)
    ? globalThis.GOAL_TEMPLATES
    : (globalThis.GOAL_TEMPLATES = {});
Object.assign(goalTemplatesRef, EXTRA_GOAL_TEMPLATES);

let bioPreviewMode = 'desktop';
let bioPagesCache = [];
let bioTemplateFilter = 'all';
let enhancedBioBuilderInitialized = false;
let currentBioTab = 'profile';

function createEmptyBioPageState() {
    return {
        id: null,
        template_id: 1,
        selected_goal_key: '',
        page_type: 'bio',
        page_status: 'draft',
        links: [],
        blocks: [],
        theme: {}
    };
}

function cloneDeep(value) {
    return JSON.parse(JSON.stringify(value));
}

function escapeHtml(value) {
    return String(value || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function escapeAttribute(value) {
    return escapeHtml(value).replace(/`/g, '&#96;');
}

function bioIconClassInputHtml(value, oninputCode) {
    return (
        '<div class="bio-icon-field-wrap">' +
        '<input type="text" class="bio-icon-class-input" value="' + escapeAttribute(value || '') + '" oninput="' + escapeAttribute(oninputCode) + '" placeholder="ex: fas fa-link">' +
        '<button type="button" class="bio-icon-picker-btn" title="Buscar ícone" aria-label="Abrir galeria de ícones" onclick="openBioIconPicker(this)">' +
        '<i class="fas fa-magnifying-glass" aria-hidden="true"></i></button></div>'
    );
}

function isCurrentBioTemplatePremium() {
    const m = getBioTemplateMetaById(currentBioPage?.template_id || 1);
    return Boolean(m?.premium);
}

/** Paletas disponíveis no builder (Google Fonts já carregadas no index quando aplicável). */
const BIO_EDITOR_FONT_OPTIONS = [
    { value: '', label: 'Padrão do modelo' },
    { value: 'Manrope, sans-serif', label: 'Manrope' },
    { value: 'Sora, sans-serif', label: 'Sora' },
    { value: 'Inter, system-ui, sans-serif', label: 'Inter' },
    { value: 'Georgia, serif', label: 'Georgia' },
    { value: '"Times New Roman", Times, serif', label: 'Times New Roman' },
    { value: 'system-ui, sans-serif', label: 'Sistema (UI)' },
    { value: '"Segoe UI", Roboto, Helvetica, Arial, sans-serif', label: 'Segoe / Roboto' },
    { value: '"Playfair Display", Georgia, serif', label: 'Playfair Display' },
    { value: '"DM Sans", system-ui, sans-serif', label: 'DM Sans' },
];

function bioNormalizeHexForInput(colorStr) {
    const s = String(colorStr || '').trim();
    if (/^#([0-9a-f]{6})$/i.test(s)) return s.toLowerCase();
    if (/^#([0-9a-f]{3})$/i.test(s)) {
        const h = s.slice(1);
        return (`#${h[0]}${h[0]}${h[1]}${h[1]}${h[2]}${h[2]}`).toLowerCase();
    }
    const m = s.match(/^rgba?\(\s*([0-9]+)\s*,\s*([0-9]+)\s*,\s*([0-9]+)/i);
    if (m) {
        const r = Math.min(255, parseInt(m[1], 10));
        const g = Math.min(255, parseInt(m[2], 10));
        const b = Math.min(255, parseInt(m[3], 10));
        const hx = (n) => n.toString(16).padStart(2, '0');
        return `#${hx(r)}${hx(g)}${hx(b)}`;
    }
    return '#ffffff';
}

function bioSpinSafeId(parts) {
    const raw = parts.map((p) => String(p ?? '')).join('_');
    const safe = raw.replace(/[^a-zA-Z0-9_-]/g, '_');
    return `bio-spin_${safe}`.slice(0, 160);
}

function bioTypographySpinApply(prevRaw, key, direction) {
    const dir = direction >= 0 ? 1 : -1;
    const prev = String(prevRaw || '').trim();
    if (key === 'fontSize') {
        let num;
        let unit = 'rem';
        const m = prev.match(/^(-?[0-9.]+)\s*(rem|em|px)?$/i);
        if (m) {
            num = parseFloat(m[1]);
            if (m[2]) unit = m[2].toLowerCase();
        } else if (!prev) {
            num = 1;
        } else {
            num = 1;
        }
        num += dir * (unit === 'px' ? 1 : 0.05);
        num = Math.round(num * (unit === 'px' ? 1 : 100)) / (unit === 'px' ? 1 : 100);
        const min = unit === 'px' ? 10 : 0.65;
        const max = unit === 'px' ? 48 : 3.25;
        num = Math.min(max, Math.max(min, num));
        return `${num}${unit}`;
    }
    if (key === 'lineHeight') {
        let num;
        if (!prev || prev === 'normal') num = 1.45;
        else if (/^[0-9.]+$/.test(prev)) num = parseFloat(prev);
        else num = 1.45;
        num += dir * 0.05;
        num = Math.round(num * 100) / 100;
        num = Math.min(2.85, Math.max(1, num));
        return String(num);
    }
    if (key === 'letterSpacing') {
        let num = 0;
        const m = prev.match(/^(-?[0-9.]+)\s*em$/i);
        if (m) num = parseFloat(m[1]);
        else if (prev === 'normal' || !prev) num = 0;
        num += dir * 0.01;
        num = Math.round(num * 100) / 100;
        num = Math.min(0.35, Math.max(-0.08, num));
        return `${num}em`;
    }
    return prev;
}

function bioTypographySpinLabel(val, key) {
    const v = String(val || '').trim();
    if (!v) return '—';
    if (key === 'fontSize') return v;
    if (key === 'lineHeight') return v;
    if (key === 'letterSpacing') return v;
    return v;
}

function bioEditorFontSelectHtml(selectedFamily, onchangeJs) {
    const sel = String(selectedFamily || '').trim();
    let opts = BIO_EDITOR_FONT_OPTIONS.map((o) => {
        const isSel = sel === o.value;
        return `<option value="${escapeAttribute(o.value)}" ${isSel ? 'selected' : ''}>${escapeHtml(o.label)}</option>`;
    }).join('');
    return `<select class="bio-font-select" onchange="${escapeAttribute(onchangeJs)}">${opts}</select>`;
}

function bioEditorColorInputHtml(hexRaw, oninputJs) {
    const hx = bioNormalizeHexForInput(hexRaw);
    return `<div class="bio-color-field"><input type="color" value="${escapeAttribute(hx)}" oninput="${escapeAttribute(oninputJs)}" /></div>`;
}

function bioEditorSpinRowHtml(label, spinId, displayText, minusJs, plusJs) {
    return `
        <div class="form-group bio-spin-group">
            <label>${escapeHtml(label)}</label>
            <div class="bio-spin-row">
                <button type="button" class="bio-spin-btn" onclick="${escapeAttribute(minusJs)}" aria-label="Diminuir">−</button>
                <span class="bio-spin-value" id="${escapeAttribute(spinId)}">${escapeHtml(displayText)}</span>
                <button type="button" class="bio-spin-btn" onclick="${escapeAttribute(plusJs)}" aria-label="Aumentar">+</button>
            </div>
        </div>`;
}

window.bioSpinTypoBlock = function (blockId, slot, key, direction) {
    const idx = getBlockIndexById(blockId);
    if (idx === -1) return;
    const st = currentBioPage.blocks[idx].element_styles?.[slot] || {};
    const prev = String(st[key] || '').trim();
    const next = bioTypographySpinApply(prev, key, direction);
    bioUpdateBlockElementStyle(blockId, slot, key, next);
    const sid = bioSpinSafeId(['b', blockId, slot, key]);
    const el = document.getElementById(sid);
    if (el) el.textContent = bioTypographySpinLabel(next, key);
};

window.bioSpinTypoItem = function (blockId, itemIndex, slot, key, direction) {
    const idx = getBlockIndexById(blockId);
    if (idx === -1) return;
    const block = currentBioPage.blocks[idx];
    if (!Array.isArray(block.items) || !block.items[itemIndex]) return;
    const st = block.items[itemIndex].element_styles?.[slot] || {};
    const prev = String(st[key] || '').trim();
    const next = bioTypographySpinApply(prev, key, direction);
    bioUpdateBlockItemElementStyle(blockId, itemIndex, slot, key, next);
    const sid = bioSpinSafeId(['i', blockId, itemIndex, slot, key]);
    const el = document.getElementById(sid);
    if (el) el.textContent = bioTypographySpinLabel(next, key);
};

window.bioSpinTypoLink = function (linkId, key, direction) {
    const link = currentBioPage.links.find((l) => String(l.id) === String(linkId));
    if (!link) return;
    const st = link.element_styles?.button || {};
    const prev = String(st[key] || '').trim();
    const next = bioTypographySpinApply(prev, key, direction);
    bioUpdateLinkElementStyle(linkId, 'button', key, next);
    const sid = bioSpinSafeId(['l', linkId, key]);
    const el = document.getElementById(sid);
    if (el) el.textContent = bioTypographySpinLabel(next, key);
};

window.bioTypographyColorPickBlock = function (blockId, slot, hex) {
    bioUpdateBlockElementStyle(blockId, slot, 'color', bioNormalizeHexForInput(hex));
};

window.bioTypographyColorPickItem = function (blockId, itemIndex, slot, hex) {
    bioUpdateBlockItemElementStyle(blockId, itemIndex, slot, 'color', bioNormalizeHexForInput(hex));
};

window.bioTypographyColorPickLink = function (linkId, hex) {
    bioUpdateLinkElementStyle(linkId, 'button', 'color', bioNormalizeHexForInput(hex));
};

function bioTypographyToCss(slot) {
    if (!slot || typeof slot !== 'object') return '';
    const parts = [];
    const fam = String(slot.fontFamily || '').trim();
    if (fam && !/[<>{;]|url\s*\(/i.test(fam)) parts.push(`font-family:${fam.slice(0, 120)}`);
    const fs = String(slot.fontSize || '').trim();
    if (fs && /^[0-9a-z.%()\s,+×x\-]+$/i.test(fs)) parts.push(`font-size:${fs.slice(0, 48)}`);
    const fw = String(slot.fontWeight || '').trim();
    if (/^(normal|bold|bolder|lighter|[1-9]00)$/.test(fw)) parts.push(`font-weight:${fw}`);
    const lh = String(slot.lineHeight || '').trim();
    if (lh === 'normal' || /^[0-9.]+$/.test(lh)) parts.push(`line-height:${lh}`);
    const ls = String(slot.letterSpacing || '').trim();
    if (ls && (/^-?[0-9.]+\s*em$/i.test(ls) || /^normal$/i.test(ls) || /^[0-9a-z.\-%]+$/i.test(ls))) parts.push(`letter-spacing:${ls}`);
    const col = String(slot.color || '').trim();
    if (col && (/^#([0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/i.test(col) || /^rgba?\(\s*[0-9.,\s%]+\)$/i.test(col))) {
        parts.push(`color:${col}`);
    }
    const tt = String(slot.textTransform || '').trim().toLowerCase();
    if (['none', 'uppercase', 'lowercase', 'capitalize'].includes(tt)) parts.push(`text-transform:${tt}`);
    return parts.join(';');
}

function bioTypoAttr(block, slot) {
    const raw = block?.element_styles?.[slot];
    const css = bioTypographyToCss(raw);
    return css ? ` style="${escapeAttribute(css)}"` : '';
}

function bioItemTypoAttr(item, slot) {
    const raw = item?.element_styles?.[slot];
    const css = bioTypographyToCss(raw);
    return css ? ` style="${escapeAttribute(css)}"` : '';
}

function bioJsStr(val) {
    return JSON.stringify(val == null ? '' : String(val));
}

function bioPremiumTypoControlsBlock(blockId, slot, st) {
    const s = st && typeof st === 'object' ? st : {};
    const js = bioJsStr;
    const fsRaw = String(s.fontSize || '').trim();
    const lhRaw = String(s.lineHeight || '').trim();
    const lsRaw = String(s.letterSpacing || '').trim();
    const fsDisp = fsRaw ? bioTypographySpinLabel(fsRaw, 'fontSize') : '—';
    const lhDisp = lhRaw ? bioTypographySpinLabel(lhRaw, 'lineHeight') : '—';
    const lsDisp = lsRaw ? bioTypographySpinLabel(lsRaw, 'letterSpacing') : '—';
    const idFs = bioSpinSafeId(['b', blockId, slot, 'fontSize']);
    const idLh = bioSpinSafeId(['b', blockId, slot, 'lineHeight']);
    const idLs = bioSpinSafeId(['b', blockId, slot, 'letterSpacing']);
    return `
        <div class="control-group-grid">
            <div class="form-group"><label>Fonte</label>${bioEditorFontSelectHtml(s.fontFamily || '', `bioUpdateBlockElementStyle(${js(blockId)}, ${js(slot)}, 'fontFamily', this.value)`)}</div>
            ${bioEditorSpinRowHtml('Tamanho', idFs, fsDisp, `bioSpinTypoBlock(${js(blockId)}, ${js(slot)}, 'fontSize', -1)`, `bioSpinTypoBlock(${js(blockId)}, ${js(slot)}, 'fontSize', 1)`)}
        </div>
        <div class="control-group-grid">
            <div class="form-group"><label>Cor</label>${bioEditorColorInputHtml(s.color || '', `bioTypographyColorPickBlock(${js(blockId)}, ${js(slot)}, this.value)`)}</div>
            <div class="form-group"><label>Peso</label>
                <select class="bio-font-select" onchange="${escapeAttribute(`bioUpdateBlockElementStyle(${js(blockId)}, ${js(slot)}, 'fontWeight', this.value)`)}">
                    <option value="" ${!s.fontWeight ? 'selected' : ''}>Padrão</option>
                    <option value="400" ${String(s.fontWeight) === '400' ? 'selected' : ''}>400</option>
                    <option value="600" ${String(s.fontWeight) === '600' ? 'selected' : ''}>600</option>
                    <option value="700" ${String(s.fontWeight) === '700' ? 'selected' : ''}>700</option>
                    <option value="800" ${String(s.fontWeight) === '800' ? 'selected' : ''}>800</option>
                    <option value="900" ${String(s.fontWeight) === '900' ? 'selected' : ''}>900</option>
                    <option value="bold" ${s.fontWeight === 'bold' ? 'selected' : ''}>bold</option>
                </select>
            </div>
        </div>
        <div class="control-group-grid">
            ${bioEditorSpinRowHtml('Altura de linha', idLh, lhDisp, `bioSpinTypoBlock(${js(blockId)}, ${js(slot)}, 'lineHeight', -1)`, `bioSpinTypoBlock(${js(blockId)}, ${js(slot)}, 'lineHeight', 1)`)}
            ${bioEditorSpinRowHtml('Espaç. letras', idLs, lsDisp, `bioSpinTypoBlock(${js(blockId)}, ${js(slot)}, 'letterSpacing', -1)`, `bioSpinTypoBlock(${js(blockId)}, ${js(slot)}, 'letterSpacing', 1)`)}
        </div>
        <div class="form-group"><label>Caixa do texto</label>
            <select class="bio-font-select" onchange="${escapeAttribute(`bioUpdateBlockElementStyle(${js(blockId)}, ${js(slot)}, 'textTransform', this.value)`)}">
                <option value="" ${!s.textTransform ? 'selected' : ''}>Padrão</option>
                <option value="none" ${s.textTransform === 'none' ? 'selected' : ''}>Nenhuma</option>
                <option value="uppercase" ${s.textTransform === 'uppercase' ? 'selected' : ''}>MAIÚSCULAS</option>
                <option value="lowercase" ${s.textTransform === 'lowercase' ? 'selected' : ''}>minúsculas</option>
                <option value="capitalize" ${s.textTransform === 'capitalize' ? 'selected' : ''}>Capitalizar</option>
            </select>
        </div>
        <button type="button" class="btn-outline" style="margin-top:8px;width:100%;" onclick="${escapeAttribute(`bioClearElementStyleSlot('block', ${js(blockId)}, ${js(slot)})`)}">Limpar este trecho</button>`;
}

function bioPremiumTypoControlsItem(blockId, itemIndex, slot, st) {
    const s = st && typeof st === 'object' ? st : {};
    const js = bioJsStr;
    const ii = Number(itemIndex);
    const fsRaw = String(s.fontSize || '').trim();
    const lhRaw = String(s.lineHeight || '').trim();
    const lsRaw = String(s.letterSpacing || '').trim();
    const fsDisp = fsRaw ? bioTypographySpinLabel(fsRaw, 'fontSize') : '—';
    const lhDisp = lhRaw ? bioTypographySpinLabel(lhRaw, 'lineHeight') : '—';
    const lsDisp = lsRaw ? bioTypographySpinLabel(lsRaw, 'letterSpacing') : '—';
    const idFs = bioSpinSafeId(['i', blockId, itemIndex, slot, 'fontSize']);
    const idLh = bioSpinSafeId(['i', blockId, itemIndex, slot, 'lineHeight']);
    const idLs = bioSpinSafeId(['i', blockId, itemIndex, slot, 'letterSpacing']);
    return `
        <div class="control-group-grid">
            <div class="form-group"><label>Fonte</label>${bioEditorFontSelectHtml(s.fontFamily || '', `bioUpdateBlockItemElementStyle(${js(blockId)}, ${ii}, ${js(slot)}, 'fontFamily', this.value)`)}</div>
            ${bioEditorSpinRowHtml('Tamanho', idFs, fsDisp, `bioSpinTypoItem(${js(blockId)}, ${ii}, ${js(slot)}, 'fontSize', -1)`, `bioSpinTypoItem(${js(blockId)}, ${ii}, ${js(slot)}, 'fontSize', 1)`)}
        </div>
        <div class="control-group-grid">
            <div class="form-group"><label>Cor</label>${bioEditorColorInputHtml(s.color || '', `bioTypographyColorPickItem(${js(blockId)}, ${ii}, ${js(slot)}, this.value)`)}</div>
            <div class="form-group"><label>Peso</label>
                <select class="bio-font-select" onchange="${escapeAttribute(`bioUpdateBlockItemElementStyle(${js(blockId)}, ${ii}, ${js(slot)}, 'fontWeight', this.value)`)}">
                    <option value="" ${!s.fontWeight ? 'selected' : ''}>Padrão</option>
                    <option value="400" ${String(s.fontWeight) === '400' ? 'selected' : ''}>400</option>
                    <option value="600" ${String(s.fontWeight) === '600' ? 'selected' : ''}>600</option>
                    <option value="700" ${String(s.fontWeight) === '700' ? 'selected' : ''}>700</option>
                    <option value="800" ${String(s.fontWeight) === '800' ? 'selected' : ''}>800</option>
                    <option value="bold" ${s.fontWeight === 'bold' ? 'selected' : ''}>bold</option>
                </select>
            </div>
        </div>
        <div class="control-group-grid">
            ${bioEditorSpinRowHtml('Altura de linha', idLh, lhDisp, `bioSpinTypoItem(${js(blockId)}, ${ii}, ${js(slot)}, 'lineHeight', -1)`, `bioSpinTypoItem(${js(blockId)}, ${ii}, ${js(slot)}, 'lineHeight', 1)`)}
            ${bioEditorSpinRowHtml('Espaç. letras', idLs, lsDisp, `bioSpinTypoItem(${js(blockId)}, ${ii}, ${js(slot)}, 'letterSpacing', -1)`, `bioSpinTypoItem(${js(blockId)}, ${ii}, ${js(slot)}, 'letterSpacing', 1)`)}
        </div>
        <div class="form-group"><label>Caixa do texto</label>
            <select class="bio-font-select" onchange="${escapeAttribute(`bioUpdateBlockItemElementStyle(${js(blockId)}, ${ii}, ${js(slot)}, 'textTransform', this.value)`)}">
                <option value="" ${!s.textTransform ? 'selected' : ''}>Padrão</option>
                <option value="none" ${s.textTransform === 'none' ? 'selected' : ''}>Nenhuma</option>
                <option value="uppercase" ${s.textTransform === 'uppercase' ? 'selected' : ''}>MAIÚSCULAS</option>
                <option value="lowercase" ${s.textTransform === 'lowercase' ? 'selected' : ''}>minúsculas</option>
                <option value="capitalize" ${s.textTransform === 'capitalize' ? 'selected' : ''}>Capitalizar</option>
            </select>
        </div>
        <button type="button" class="btn-outline" style="margin-top:8px;width:100%;" onclick="${escapeAttribute(`bioClearElementStyleSlot('item', ${js(blockId)}, ${js(slot)}, ${ii})`)}">Limpar este trecho</button>`;
}

function bioPremiumTypoControlsLink(linkId, st) {
    const s = st && typeof st === 'object' ? st : {};
    const js = bioJsStr;
    const fsRaw = String(s.fontSize || '').trim();
    const lhRaw = String(s.lineHeight || '').trim();
    const lsRaw = String(s.letterSpacing || '').trim();
    const fsDisp = fsRaw ? bioTypographySpinLabel(fsRaw, 'fontSize') : '—';
    const lhDisp = lhRaw ? bioTypographySpinLabel(lhRaw, 'lineHeight') : '—';
    const lsDisp = lsRaw ? bioTypographySpinLabel(lsRaw, 'letterSpacing') : '—';
    const idFs = bioSpinSafeId(['l', linkId, 'fontSize']);
    const idLh = bioSpinSafeId(['l', linkId, 'lineHeight']);
    const idLs = bioSpinSafeId(['l', linkId, 'letterSpacing']);
    return `
        <div class="control-group-grid">
            <div class="form-group"><label>Fonte</label>${bioEditorFontSelectHtml(s.fontFamily || '', `bioUpdateLinkElementStyle(${js(linkId)}, 'button', 'fontFamily', this.value)`)}</div>
            ${bioEditorSpinRowHtml('Tamanho', idFs, fsDisp, `bioSpinTypoLink(${js(linkId)}, 'fontSize', -1)`, `bioSpinTypoLink(${js(linkId)}, 'fontSize', 1)`)}
        </div>
        <div class="control-group-grid">
            <div class="form-group"><label>Cor do texto</label>${bioEditorColorInputHtml(s.color || '', `bioTypographyColorPickLink(${js(linkId)}, this.value)`)}</div>
            <div class="form-group"><label>Peso</label>
                <select class="bio-font-select" onchange="${escapeAttribute(`bioUpdateLinkElementStyle(${js(linkId)}, 'button', 'fontWeight', this.value)`)}">
                    <option value="" ${!s.fontWeight ? 'selected' : ''}>Padrão</option>
                    <option value="700" ${String(s.fontWeight) === '700' ? 'selected' : ''}>700</option>
                    <option value="800" ${String(s.fontWeight) === '800' ? 'selected' : ''}>800</option>
                    <option value="900" ${String(s.fontWeight) === '900' ? 'selected' : ''}>900</option>
                    <option value="bold" ${s.fontWeight === 'bold' ? 'selected' : ''}>bold</option>
                </select>
            </div>
        </div>
        <div class="control-group-grid">
            ${bioEditorSpinRowHtml('Altura de linha', idLh, lhDisp, `bioSpinTypoLink(${js(linkId)}, 'lineHeight', -1)`, `bioSpinTypoLink(${js(linkId)}, 'lineHeight', 1)`)}
            ${bioEditorSpinRowHtml('Espaç. letras', idLs, lsDisp, `bioSpinTypoLink(${js(linkId)}, 'letterSpacing', -1)`, `bioSpinTypoLink(${js(linkId)}, 'letterSpacing', 1)`)}
        </div>
        <div class="form-group"><label>Caixa do texto</label>
            <select class="bio-font-select" onchange="${escapeAttribute(`bioUpdateLinkElementStyle(${js(linkId)}, 'button', 'textTransform', this.value)`)}">
                <option value="" ${!s.textTransform ? 'selected' : ''}>Padrão</option>
                <option value="none" ${s.textTransform === 'none' ? 'selected' : ''}>Nenhuma</option>
                <option value="uppercase" ${s.textTransform === 'uppercase' ? 'selected' : ''}>MAIÚSCULAS</option>
            </select>
        </div>
        <button type="button" class="btn-outline" style="margin-top:8px;width:100%;" onclick="${escapeAttribute(`bioClearElementStyleSlot('link', ${js(linkId)}, 'button')`)}">Limpar tipografia do botão</button>`;
}

function bioPremiumTypoSectionBlock(block, slot, label) {
    if (!isCurrentBioTemplatePremium()) return '';
    const st = block.element_styles?.[slot];
    return `
        <details class="bio-premium-typo" style="margin-top:12px;padding-top:12px;border-top:1px solid rgba(255,255,255,0.08);">
            <summary style="cursor:pointer;font-weight:700;">Tipografia premium · ${escapeHtml(label)}</summary>
            <div style="margin-top:10px;">${bioPremiumTypoControlsBlock(block.id, slot, st)}</div>
        </details>`;
}

function bioPremiumTypoSectionItem(block, itemIndex, slot, label) {
    if (!isCurrentBioTemplatePremium()) return '';
    const st = block.items?.[itemIndex]?.element_styles?.[slot];
    return `
        <details class="bio-premium-typo" style="margin-top:10px;">
            <summary style="cursor:pointer;font-size:0.9rem;">Tipografia · ${escapeHtml(label)}</summary>
            <div style="margin-top:8px;">${bioPremiumTypoControlsItem(block.id, itemIndex, slot, st)}</div>
        </details>`;
}

function bioPremiumTypoSectionLink(linkId, label) {
    if (!isCurrentBioTemplatePremium()) return '';
    const link = currentBioPage.links.find(l => String(l.id) === String(linkId));
    const st = link?.element_styles?.button;
    return `
        <details class="bio-premium-typo" style="margin-top:10px;">
            <summary style="cursor:pointer;font-size:0.9rem;">Tipografia do botão · ${escapeHtml(label)}</summary>
            <div style="margin-top:8px;">${bioPremiumTypoControlsLink(linkId, st)}</div>
        </details>`;
}

function nl2brSafe(value) {
    return escapeHtml(value).replace(/\n/g, '<br>');
}

function bioToast(message, type = 'success') {
    if (typeof showNotification === 'function') {
        showNotification(message, type === 'error' ? 'danger' : type);
        return;
    }
    if (typeof showStatus === 'function') {
        showStatus('bio', message, type === 'error' ? 'error' : 'success');
        return;
    }
    alert(message);
}

function getThemeConfig() {
    return {
        bg: document.getElementById('bio-theme-bg')?.value || '#0f172a',
        btn: document.getElementById('bio-theme-btn')?.value || '#1A8CFF',
        text: document.getElementById('bio-theme-text')?.value || '#ffffff',
        style: document.getElementById('bio-theme-style')?.value || 'solid'
    };
}

function sanitizeBioSlug(value) {
    return String(value || '')
        .trim()
        .toLowerCase()
        .replace(/[^a-z0-9-]/g, '');
}

function generateBioRandomSlug(size = 6) {
    const chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
    let out = '';
    for (let i = 0; i < size; i++) {
        out += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    return out;
}

function getBioPageMeta() {
    return {
        slug: sanitizeBioSlug(document.getElementById('bio-slug')?.value || ''),
        title: (document.getElementById('bio-title')?.value || '').trim(),
        description: (document.getElementById('bio-description')?.value || '').trim(),
        avatar: (document.getElementById('bio-avatar')?.value || '').trim(),
        cover_image: (document.getElementById('bio-hero-cover')?.value || '').trim(),
        avatar_shape: normalizeAvatarShape(document.getElementById('bio-avatar-shape')?.value || 'rounded'),
        page_type: 'bio',
        page_status: document.getElementById('bio-page-status')?.value || currentBioPage.page_status || 'draft'
    };
}

function normalizeAvatarShape(value) {
    const allowed = ['rounded', 'circle', 'square', 'banner', 'soft'];
    return allowed.includes(value) ? value : 'rounded';
}

function syncBioStatusSwitchUI(status) {
    const value = status === 'draft' ? 'draft' : 'published';
    const statusInput = document.getElementById('bio-page-status');
    const switchElement = document.getElementById('bio-page-status-switch');
    const labelElement = document.getElementById('bio-page-status-label');
    if (statusInput) statusInput.value = value;
    if (switchElement) {
        switchElement.classList.toggle('draft', value === 'draft');
        switchElement.classList.toggle('published', value === 'published');
        switchElement.setAttribute('aria-checked', value === 'published' ? 'true' : 'false');
    }
    if (labelElement) {
        labelElement.textContent = value === 'published' ? 'Publicado' : 'Rascunho';
    }
}

window.toggleBioStatusSwitch = function () {
    const nextStatus = (document.getElementById('bio-page-status')?.value || 'published') === 'published' ? 'draft' : 'published';
    syncBioStatusSwitchUI(nextStatus);
    updateBioPreview();
};

window.toggleBioThemePanel = function () {
    const panel = document.getElementById('bio-theme-panel');
    if (!panel) return;
    panel.classList.toggle('hidden');
};

function getBioTemplateMetaById(id) {
    const template = (bioTemplatesData || []).find(item => String(item.id) === String(id));
    if (!template) {
        return null;
    }
    return {
        ...BIO_TEMPLATE_ENRICHMENTS[id],
        ...template,
        page_types: template.page_types || BIO_TEMPLATE_ENRICHMENTS[id]?.page_types || ['bio'],
        description: template.description || BIO_TEMPLATE_ENRICHMENTS[id]?.description || 'Template visual para Bio Pages.',
        ideal_for: template.ideal_for || BIO_TEMPLATE_ENRICHMENTS[id]?.ideal_for || 'Uso geral',
        category: template.category || BIO_TEMPLATE_ENRICHMENTS[id]?.category || 'Visual',
        premium: Boolean(template.premium || BIO_TEMPLATE_ENRICHMENTS[id]?.premium)
    };
}

function bioCreateBlock(type, overrides = {}) {
    const block = {
        id: `${type}-${Math.random().toString(36).slice(2, 10)}`,
        type,
        title: '',
        subtitle: '',
        content: '',
        style: 'default',
        layout: 'stack',
        badge: '',
        label: '',
        url: '',
        icon_class: '',
        media_url: '',
        cover_image_url: '',
        avatar_shape: 'rounded',
        embed_provider: 'youtube',
        embed_url: '',
        items: [],
        element_styles: {}
    };

    switch (type) {
        case 'hero':
            block.title = 'Seu nome ou marca';
            // block.badge = 'Bio';
            // block.subtitle = 'Página pronta para publicar';
            block.content = 'Descreva sua proposta principal em uma frase forte.';
            block.style = 'profile';
            break;
        case 'heading':
            block.title = 'Novo bloco de título';
            block.subtitle = 'Subtítulo ou contexto';
            break;
        case 'text':
            block.title = 'Seção de conteúdo';
            block.content = 'Adicione aqui um texto mais longo, editorial ou institucional.';
            break;
        case 'highlight':
            block.badge = 'Destaque';
            block.title = 'Oferta, proposta de valor ou anúncio principal';
            block.content = 'Use esse bloco para gerar impacto logo após o hero.';
            break;
        case 'cta':
            block.title = 'Chamada principal';
            block.content = 'Convide a audiência para a próxima ação.';
            block.label = 'Acessar agora';
            block.url = 'https://';
            block.icon_class = 'fas fa-arrow-up-right-from-square';
            block.style = 'primary';
            break;
        case 'links':
            block.title = 'Links principais';
            block.layout = 'stack';
            block.source = 'legacy';
            break;
        case 'social':
            block.title = 'Redes e contatos';
            block.layout = 'grid';
            block.source = 'legacy';
            break;
        case 'cards':
            block.title = 'Cards de produto ou serviço';
            block.items = [
                { id: `card-${Date.now()}`, title: 'Serviço premium', text: 'Descrição breve do serviço.', label: 'Saiba mais', url: 'https://', badge: 'Destaque', icon_class: 'fas fa-star' }
            ];
            break;
        case 'faq':
            block.title = 'Perguntas frequentes';
            block.items = [
                { id: `faq-${Date.now()}`, title: 'Como funciona?', text: 'Explique aqui a resposta da pergunta.' }
            ];
            break;
        case 'gallery':
            block.title = 'Galeria visual';
            block.items = [
                { id: `gallery-${Date.now()}`, image_url: '', caption: 'Legenda da imagem', url: '' }
            ];
            break;
        case 'posts':
            block.title = 'Conteúdo recente';
            block.items = [
                { id: `post-${Date.now()}`, title: 'Post ou artigo principal', text: 'Resumo do conteúdo.', url: 'https://', meta: 'Publicado hoje' }
            ];
            break;
        case 'contact':
            block.title = 'Contato e suporte';
            block.items = [
                { id: `contact-${Date.now()}`, title: 'WhatsApp', text: '(11) 99999-9999', icon_class: 'fab fa-whatsapp' },
                { id: `contact-${Date.now() + 1}`, title: 'E-mail', text: 'contato@seudominio.com', icon_class: 'fas fa-envelope' }
            ];
            break;
        case 'schedule':
            block.title = 'Agenda / horário';
            block.items = [
                { id: `slot-${Date.now()}`, title: 'Segunda a sexta', text: '09h às 18h' }
            ];
            break;
        case 'video':
            block.title = 'Embed principal';
            block.content = 'Use um embed para apresentar vídeo, localização ou música.';
            block.embed_provider = 'youtube';
            block.embed_url = '';
            break;
        case 'divider':
            block.title = 'Próxima seção';
            break;
        case 'form':
            block.title = 'Responder formulário';
            block.subtitle = 'Preencha os campos abaixo';
            block.content = 'Suas respostas são enviadas com segurança ao criador desta página.';
            block.items = [
                {
                    id: `fld-${Date.now()}`,
                    field_type: 'short_text',
                    label: 'Nome completo',
                    required: true,
                    placeholder: '',
                    help_text: '',
                    max_length: 120,
                    mask_pattern: '',
                    default_value: '',
                    options: [],
                    show_if: null
                },
                {
                    id: `fld-${Date.now() + 1}`,
                    field_type: 'email',
                    label: 'E-mail',
                    required: true,
                    placeholder: 'voce@exemplo.com',
                    help_text: '',
                    max_length: 0,
                    mask_pattern: '',
                    default_value: '',
                    options: [],
                    show_if: null
                }
            ];
            break;
        default:
            break;
    }

    return { ...block, ...overrides };
}

function getBlockMeta(type) {
    return BIO_BLOCK_LIBRARY.find(item => item.type === type) || { title: type, description: '' };
}

function ensureGoalTemplates() {
    Object.entries(GOAL_TEMPLATES).forEach(([key, goal]) => {
        goal.key = key;
        goal.page_type = goal.page_type || (key === 'blog' ? 'blog' : (key === 'campanha' ? 'campaign' : 'bio'));
        goal.category = goal.category || (goal.page_type === 'blog' ? 'Conteúdo' : 'Objetivo');
        goal.recommended_template_id = goal.theme?.template_id || goal.template_id || 1;
        goal.blocks = createGoalBlocks(key, goal);
    });
}

function createGoalBlocks(key, goal) {
    const baseTitle = goal.page_type === 'blog' ? 'Seu Blog' : 'Sua Página';
    const hero = bioCreateBlock('hero', {
        title: goal.hero_title || baseTitle,
        subtitle: goal.name,
        content: goal.desc || 'Personalize este preset com sua proposta.',
        badge: BIO_PAGE_TYPE_LABELS[goal.page_type] || 'Bio',
        style: 'profile'
    });

    const blocks = [hero];

    if (key === 'blog') {
        blocks.push(
            bioCreateBlock('highlight', {
                badge: 'Editorial',
                title: 'Publique artigos, destaques e convide para assinar sua base.',
                content: 'Esse preset já organiza uma bio page com cara de hub de conteúdo.'
            }),
            bioCreateBlock('posts', {
                title: 'Artigos em destaque',
                items: [
                    { id: 'blog-post-1', title: 'Como transformar seguidores em audiência recorrente', text: 'Mostre um resumo convincente do post principal.', url: 'https://...', meta: 'Artigo principal' },
                    { id: 'blog-post-2', title: 'Ferramentas para creators e negócios locais', text: 'Use esse bloco para escalar conteúdo e percepção de valor.', url: 'https://...', meta: 'Leitura recomendada' }
                ]
            }),
            bioCreateBlock('cta', {
                title: 'Receba novas publicações',
                content: 'Leve sua audiência para newsletter, grupo VIP ou lista de espera.',
                label: 'Assinar agora',
                url: 'https://...'
            }),
            bioCreateBlock('social', { title: 'Acompanhe em outras plataformas' })
        );
        return blocks;
    }

    if (key === 'institucional' || key === 'servicos') {
        blocks.push(
            bioCreateBlock('highlight', {
                badge: 'Credibilidade',
                title: 'Estruture sua apresentação com foco em clareza e confiança.',
                content: 'Combine apresentação, diferenciais, serviços e canais de atendimento.'
            }),
            bioCreateBlock('cards', {
                title: 'Soluções em destaque',
                items: [
                    { id: 'service-1', title: 'Serviço principal', text: 'Explique o principal serviço ou produto.', label: 'Solicitar proposta', url: 'https://...', badge: 'Mais pedido' },
                    { id: 'service-2', title: 'Atendimento consultivo', text: 'Adicione benefícios e diferenciais.', label: 'Falar com a equipe', url: 'https://...' }
                ]
            }),
            bioCreateBlock('contact', {
                title: 'Canais de contato',
                items: [
                    { id: 'contact-whats', title: 'WhatsApp', text: '(11) 99999-9999', icon_class: 'fab fa-whatsapp' },
                    { id: 'contact-mail', title: 'E-mail', text: 'contato@empresa.com', icon_class: 'fas fa-envelope' },
                    { id: 'contact-address', title: 'Endereço', text: 'Av. Principal, 1000 - Centro', icon_class: 'fas fa-location-dot' }
                ]
            })
        );
        return blocks;
    }

    if (key === 'campanha' || key === 'lancamento' || key === 'leads') {
        blocks.push(
            bioCreateBlock('highlight', {
                badge: 'Conversão',
                title: 'Organize sua campanha com um CTA dominante e prova social.',
                content: 'Use essa estrutura para mídia paga, captação ou lançamentos.'
            }),
            bioCreateBlock('cta', {
                title: 'Oferta principal',
                content: 'Explique o benefício principal da campanha.',
                label: 'Quero participar',
                url: 'https://...',
                icon_class: 'fas fa-bolt'
            }),
            bioCreateBlock('faq', {
                title: 'Dúvidas antes da ação',
                items: [
                    { id: 'faq-camp-1', title: 'Quem pode participar?', text: 'Explique rapidamente o público ideal.' },
                    { id: 'faq-camp-2', title: 'Até quando a oferta fica disponível?', text: 'Mostre prazo e urgência com clareza.' }
                ]
            }),
            bioCreateBlock('social', { title: 'Redes oficiais da campanha' })
        );
        return blocks;
    }

    if (key === 'portfolio' || key === 'portifolio') {
        blocks.push(
            bioCreateBlock('gallery', {
                title: 'Projetos e destaques',
                items: [
                    { id: 'portfolio-shot-1', image_url: '', caption: 'Projeto destaque 01', url: 'https://...' },
                    { id: 'portfolio-shot-2', image_url: '', caption: 'Projeto destaque 02', url: 'https://...' }
                ]
            }),
            bioCreateBlock('posts', {
                title: 'Cases selecionados',
                items: [
                    { id: 'case-1', title: 'Case com resultado forte', text: 'Mostre processo, estratégia ou impacto.', url: 'https://...', meta: '+180% em conversão' },
                    { id: 'case-2', title: 'Projeto com branding autoral', text: 'Use esse bloco para expandir percepção de valor.', url: 'https://...', meta: 'Branding / UX' }
                ]
            }),
            bioCreateBlock('contact', {
                title: 'Vamos conversar?',
                items: [
                    { id: 'contact-portfolio-1', title: 'WhatsApp', text: '(11) 99999-9999', icon_class: 'fab fa-whatsapp' },
                    { id: 'contact-portfolio-2', title: 'Behance', text: 'behance.net/seuperfil', icon_class: 'fab fa-behance' }
                ]
            })
        );
        return blocks;
    }

    if (key === 'evento') {
        blocks.push(
            bioCreateBlock('schedule', {
                title: 'Programação e datas',
                items: [
                    { id: 'event-slot-1', title: 'Abertura das inscrições', text: '08 de maio' },
                    { id: 'event-slot-2', title: 'Evento principal', text: '15 de maio, às 19h' }
                ]
            }),
            bioCreateBlock('cta', {
                title: 'Garanta sua vaga',
                content: 'Destaque o link de compra ou inscrição.',
                label: 'Quero meu ingresso',
                url: 'https://...'
            }),
            bioCreateBlock('faq', {
                title: 'Informações rápidas',
                items: [
                    { id: 'event-faq-1', title: 'Onde será o evento?', text: 'Informe local, formato ou transmissão.' },
                    { id: 'event-faq-2', title: 'Há replay ou gravação?', text: 'Use esse espaço para detalhes importantes.' }
                ]
            })
        );
        return blocks;
    }

    if (key === 'restaurante' || key === 'loja') {
        blocks.push(
            bioCreateBlock('cards', {
                title: 'Produtos ou destaques do dia',
                items: [
                    { id: 'menu-1', title: 'Destaque da casa', text: 'Apresente seu item principal.', label: 'Pedir agora', url: 'https://...', badge: 'Mais vendido' },
                    { id: 'menu-2', title: 'Combo especial', text: 'Use para elevar ticket médio.', label: 'Ver catálogo', url: 'https://...' }
                ]
            }),
            bioCreateBlock('contact', {
                title: 'Pedido e atendimento',
                items: [
                    { id: 'restaurant-contact-1', title: 'WhatsApp', text: '(11) 99999-9999', icon_class: 'fab fa-whatsapp' },
                    { id: 'restaurant-contact-2', title: 'Endereço', text: 'Rua da cozinha, 50', icon_class: 'fas fa-location-dot' }
                ]
            })
        );
        return blocks;
    }

    blocks.push(
        bioCreateBlock('highlight', {
            badge: 'Estratégia',
            title: 'Use este preset como ponto de partida e refine o conteúdo ao seu objetivo.',
            content: 'A Bio Page já nasce com estrutura, tema e elementos para acelerar a criação.'
        }),
        bioCreateBlock('links', { title: 'Links principais' }),
        bioCreateBlock('social', { title: 'Redes e contatos' })
    );

    return blocks;
}

function createTemplateStarterBlocks(templateId) {
    const profileGuide = 'Imagem de perfil recomendada: proporção 1:1 (mínimo 800x800).';
    const coverGuide = 'Se usar imagem de capa, recomendação: proporção 16:9 (mínimo 1600x900).';

    const hero = bioCreateBlock('hero', {
        title: 'Nome da marca ou profissional',
        subtitle: 'Cargo, nicho ou proposta principal',
        content: `${profileGuide} ${coverGuide}`,
        badge: 'Bio',
        style: 'profile'
    });

    const links = bioCreateBlock('links', {
        title: 'Links principais',
        source: 'template',
        layout: 'stack',
        items: [
            { id: `lnk-${Date.now()}-1`, title: 'Link principal', url: 'https://...', icon_class: 'fas fa-bolt', is_featured: 1, link_type: 'link' },
            { id: `lnk-${Date.now()}-2`, title: 'Portfólio / Serviços', url: 'https://...', icon_class: 'fas fa-briefcase', is_featured: 0, link_type: 'link' },
            { id: `lnk-${Date.now()}-3`, title: 'Agendamento / Contato', url: 'https://...', icon_class: 'fas fa-calendar-check', is_featured: 0, link_type: 'link' }
        ]
    });

    const social = bioCreateBlock('social', {
        title: 'Redes e atendimento',
        source: 'template',
        layout: 'grid',
        items: [
            { id: `soc-${Date.now()}-1`, title: 'Instagram', url: '', icon_class: 'fab fa-instagram', link_type: 'social', social_network: 'instagram', social_value: '@seuperfil' },
            { id: `soc-${Date.now()}-2`, title: 'WhatsApp', url: '', icon_class: 'fab fa-whatsapp', link_type: 'social', social_network: 'whatsapp', social_value: '11999999999' }
        ]
    });

    const form = bioCreateBlock('form', {
        title: 'Formulário de contato',
        subtitle: 'Receba solicitações com campos personalizados',
        content: 'Esses campos são totalmente editáveis e podem ser removidos.'
    });

    const starters = {
        1: [
            hero,
            bioCreateBlock('highlight', { badge: 'Apresentação', title: 'Resumo profissional', content: 'Explique de forma clara quem você ajuda e qual resultado entrega.' }),
            links,
            social,
            form
        ],
        2: [
            hero,
            bioCreateBlock('highlight', { badge: 'Premium', title: 'Oferta principal', content: 'Use este bloco para comunicar sua proposta de alto valor.' }),
            bioCreateBlock('cards', { title: 'Serviços premium' }),
            links,
            form
        ],
        3: [
            hero,
            bioCreateBlock('text', { title: 'Manifesto / posicionamento', content: 'Use este espaço para construir narrativa e autoridade.' }),
            bioCreateBlock('posts', { title: 'Conteúdos em destaque' }),
            links,
            form
        ],
        4: [
            hero,
            bioCreateBlock('cards', { title: 'Produtos e categorias' }),
            bioCreateBlock('gallery', { title: 'Galeria visual' }),
            links,
            form
        ],
        5: [
            hero,
            bioCreateBlock('highlight', { badge: 'Institucional', title: 'Proposta de valor institucional', content: 'Apresente empresa, diferenciais e prova de confiança.' }),
            bioCreateBlock('contact', { title: 'Canais de atendimento' }),
            links,
            form
        ],
        6: [
            hero,
            bioCreateBlock('cta', { title: 'Ação imediata', content: 'Direcione para conversão principal.', label: 'Quero avançar', url: 'https://...' }),
            bioCreateBlock('faq', { title: 'Perguntas frequentes' }),
            links,
            form
        ],
        7: [
            hero,
            bioCreateBlock('highlight', { badge: 'Destaque', title: 'Mensagem de impacto', content: 'Chame atenção para campanha, produto ou posicionamento.' }),
            links,
            social,
            form
        ],
        8: [
            hero,
            bioCreateBlock('cards', { title: 'Projetos e serviços' }),
            bioCreateBlock('contact', { title: 'Contato profissional' }),
            links,
            form
        ],
        9: [
            hero,
            bioCreateBlock('posts', { title: 'Conteúdos recentes' }),
            bioCreateBlock('text', { title: 'Sobre', content: 'Descreva visão, método e foco editorial.' }),
            links,
            form
        ],
        10: [
            hero,
            bioCreateBlock('highlight', { badge: 'Captação', title: 'Coleta de respostas', content: 'Este modelo prioriza formulário com campos customizáveis.' }),
            form,
            links
        ],
        11: [
            hero,
            bioCreateBlock('cards', { title: 'Áreas de atuação' }),
            bioCreateBlock('contact', { title: 'Contato corporativo' }),
            links,
            form
        ],
        12: [
            hero,
            bioCreateBlock('gallery', { title: 'Projetos selecionados' }),
            bioCreateBlock('posts', { title: 'Cases e bastidores' }),
            links,
            form
        ],
        13: [
            hero,
            bioCreateBlock('highlight', { badge: 'Oferta', title: 'Oferta principal com urgência', content: 'Explique benefício e motivo para agir agora.' }),
            bioCreateBlock('cta', { title: 'Conversão principal', label: 'Comprar agora', url: 'https://...' }),
            bioCreateBlock('faq', { title: 'Objeções comuns' }),
            form
        ],
        14: [
            hero,
            bioCreateBlock('text', { title: 'Sobre o trabalho', content: 'Descreva estilo, proposta e diferenciais do atendimento.' }),
            links,
            social,
            form
        ],
        15: [
            hero,
            bioCreateBlock('highlight', { badge: 'Credibilidade', title: 'Área de atuação', content: 'Descreva especialidade jurídica/consultiva e forma de atendimento.' }),
            bioCreateBlock('contact', { title: 'Canais oficiais' }),
            links,
            form
        ],
        16: [
            hero,
            bioCreateBlock('cta', { title: 'Ação central', content: 'Comunique objetivo de campanha com mensagem direta.', label: 'Entrar agora', url: 'https://...' }),
            links,
            social,
            form
        ],
        17: [
            hero,
            bioCreateBlock('gallery', { title: 'Portfólio visual' }),
            links,
            form
        ],
        18: [
            hero,
            bioCreateBlock('highlight', { badge: 'Creator', title: 'Posicionamento digital', content: 'Mostre proposta, serviços e pontos de contato.' }),
            links,
            social,
            form
        ]
    };

    return cloneDeep(starters[templateId] || starters[1]);
}

function shouldApplyTemplateStarterToCurrentPage() {
    const blocks = Array.isArray(currentBioPage.blocks) ? currentBioPage.blocks : [];
    const links = Array.isArray(currentBioPage.links) ? currentBioPage.links : [];
    if (!blocks.length) return true;

    if (blocks.length === 1 && blocks[0]?.type === 'hero' && links.length === 0) {
        return true;
    }

    if (blocks.length === 2 && blocks[0]?.type === 'hero' && blocks[1]?.type === 'links') {
        const isLegacyBlock = blocks[1]?.source === 'legacy';
        const hasItems = Array.isArray(blocks[1]?.items) && blocks[1].items.length > 0;
        if (isLegacyBlock && !hasItems && links.length === 0) {
            return true;
        }
    }

    return false;
}

function applyTemplateStarterToCurrentPage(templateId, force = false) {
    if (!force && !shouldApplyTemplateStarterToCurrentPage()) {
        return false;
    }
    currentBioPage.blocks = createTemplateStarterBlocks(templateId);
    consolidateQuickActionBlocksInCurrentPage(true);
    return true;
}

function normalizeCurrentBioPage() {
    const base = createEmptyBioPageState();
    currentBioPage = { ...base, ...(currentBioPage || {}) };
    currentBioPage.links = Array.isArray(currentBioPage.links) ? currentBioPage.links : [];
    currentBioPage.blocks = Array.isArray(currentBioPage.blocks) ? currentBioPage.blocks : [];
    currentBioPage.page_type = 'bio';
    currentBioPage.page_status = document.getElementById('bio-page-status')?.value || currentBioPage.page_status || 'draft';
    currentBioPage.selected_goal_key = currentBioPage.selected_goal_key || '';
    if (document.getElementById('bio-page-type')) document.getElementById('bio-page-type').value = 'bio';
    syncBioStatusSwitchUI(currentBioPage.page_status);

    if (!currentBioPage.blocks.length) {
        applyTemplateStarterToCurrentPage(parseInt(currentBioPage.template_id, 10) || 1, true);
    }
    const heroBlock = getOrCreateHeroBlock();
    const avatarShapeInput = document.getElementById('bio-avatar-shape');
    if (avatarShapeInput) {
        heroBlock.avatar_shape = normalizeAvatarShape(avatarShapeInput.value || heroBlock.avatar_shape || 'rounded');
    } else {
        heroBlock.avatar_shape = normalizeAvatarShape(heroBlock.avatar_shape || 'rounded');
    }

    if (needsQuickActionConsolidation()) {
        consolidateQuickActionBlocksInCurrentPage();
    } else if (!currentBioPage.links.length && currentBioPage.blocks.length) {
        const flat = flattenActionLinksFromBlocksJs(currentBioPage.blocks);
        if (flat.length) {
            currentBioPage.links = flat.map(normalizeActionLinkRow);
        }
    }
}

function normalizeActionLinkRow(link) {
    return {
        id: link.id || `link-${Math.random().toString(36).slice(2, 9)}`,
        title: link.title || '',
        url: link.url || '',
        icon_class: link.icon_class || '',
        is_featured: link.is_featured ? 1 : 0,
        link_type: link.link_type || 'link',
        social_network: link.social_network || '',
        social_value: link.social_value || '',
        element_styles: link.element_styles && typeof link.element_styles === 'object' ? cloneDeep(link.element_styles) : {}
    };
}

function needsQuickActionConsolidation() {
    if (!Array.isArray(currentBioPage.blocks)) return false;
    const nSocial = currentBioPage.blocks.filter(b => b.type === 'social').length;
    const nLinks = currentBioPage.blocks.filter(b => b.type === 'links').length;
    return nSocial > 0 || nLinks > 1;
}

function applyUnifiedQuickActionsToBlocks(allLinksNormalized) {
    const heroIndex = currentBioPage.blocks.findIndex(block => block.type === 'hero');

    for (let i = currentBioPage.blocks.length - 1; i >= 0; i--) {
        if (currentBioPage.blocks[i].type === 'social') {
            currentBioPage.blocks.splice(i, 1);
        }
    }

    let firstLinksIdx = currentBioPage.blocks.findIndex(b => b.type === 'links');

    if (!allLinksNormalized.length) {
        currentBioPage.blocks.forEach(b => {
            if (b.type === 'links') b.items = [];
        });
        for (let i = currentBioPage.blocks.length - 1; i >= 0; i--) {
            const b = currentBioPage.blocks[i];
            if (b.type === 'links' && b.source === 'legacy' && (!b.items || !b.items.length)) {
                currentBioPage.blocks.splice(i, 1);
            }
        }
        return;
    }

    if (firstLinksIdx === -1) {
        const insertAt = heroIndex >= 0 ? heroIndex + 1 : 0;
        currentBioPage.blocks.splice(insertAt, 0, bioCreateBlock('links', { source: 'legacy', items: cloneDeep(allLinksNormalized) }));
    } else {
        currentBioPage.blocks[firstLinksIdx].items = cloneDeep(allLinksNormalized);
        currentBioPage.blocks[firstLinksIdx].layout = currentBioPage.blocks[firstLinksIdx].layout || 'stack';
    }

    const keep = currentBioPage.blocks.findIndex(b => b.type === 'links');
    for (let i = currentBioPage.blocks.length - 1; i >= 0; i--) {
        if (currentBioPage.blocks[i].type === 'links' && i !== keep) {
            currentBioPage.blocks.splice(i, 1);
        }
    }
}

/** @param {boolean} fromBlocksOnly - true ao aplicar template novo (ignora links antigos na memória) */
function consolidateQuickActionBlocksInCurrentPage(fromBlocksOnly = false) {
    let allLinks;
    if (!fromBlocksOnly && Array.isArray(currentBioPage.links) && currentBioPage.links.length) {
        allLinks = currentBioPage.links.map(normalizeActionLinkRow);
    } else {
        allLinks = flattenActionLinksFromBlocksJs(currentBioPage.blocks).map(normalizeActionLinkRow);
        currentBioPage.links = allLinks;
    }
    applyUnifiedQuickActionsToBlocks(allLinks);
}

function getBlockIndexById(blockId) {
    const want = String(blockId ?? '');
    return currentBioPage.blocks.findIndex(block => String(block.id ?? '') === want);
}

function getOrCreateHeroBlock() {
    let heroIndex = currentBioPage.blocks.findIndex(block => block.type === 'hero');
    if (heroIndex === -1) {
        currentBioPage.blocks.unshift(bioCreateBlock('hero'));
        heroIndex = 0;
    }
    return currentBioPage.blocks[heroIndex];
}

function syncHeroBlockWithProfile() {
    const meta = getBioPageMeta();
    currentBioPage.page_type = meta.page_type;
    currentBioPage.page_status = meta.page_status;

    const heroBlock = getOrCreateHeroBlock();
    heroBlock.title = meta.title || heroBlock.title || 'Sua página';
    heroBlock.content = meta.description || heroBlock.content || 'Apresente sua proposta principal.';
    heroBlock.media_url = meta.avatar || heroBlock.media_url || '';
    heroBlock.cover_image_url = meta.cover_image;
    heroBlock.avatar_shape = normalizeAvatarShape(meta.avatar_shape || heroBlock.avatar_shape || 'rounded');
    if (heroBlock.badge === undefined || heroBlock.badge === null) {
        heroBlock.badge = BIO_PAGE_TYPE_LABELS[currentBioPage.page_type] || 'Bio';
    }
    if (heroBlock.subtitle === undefined || heroBlock.subtitle === null) {
        heroBlock.subtitle = currentBioPage.selected_goal_key ? GOAL_TEMPLATES[currentBioPage.selected_goal_key]?.name || '' : (meta.slug ? `linkneo.pro/bio/${meta.slug}` : 'Página pronta para publicar');
    }
}

function syncLegacyBlocksFromLinks() {
    const allLinks = currentBioPage.links.map(normalizeActionLinkRow);
    applyUnifiedQuickActionsToBlocks(allLinks);
}

function flattenActionLinksFromBlocksJs(blocks) {
    const links = [];
    (blocks || []).forEach(block => {
        if (!block || typeof block !== 'object') return;
        if (block.type === 'cta') {
            links.push({
                id: block.id,
                title: block.label || block.title,
                url: block.url || '',
                icon_class: block.icon_class || '',
                is_featured: 1,
                link_type: 'link'
            });
            return;
        }
        if (['links', 'social'].includes(block.type) && Array.isArray(block.items)) {
            block.items.forEach(item => {
                links.push({
                    id: item.id || `link-${Math.random().toString(36).slice(2, 9)}`,
                    title: item.title || item.label || '',
                    url: item.url || '',
                    icon_class: item.icon_class || '',
                    is_featured: item.is_featured ? 1 : 0,
                    link_type: block.type === 'social' ? 'social' : (item.link_type || 'link'),
                    social_network: item.social_network || '',
                    social_value: item.social_value || '',
                    element_styles: item.element_styles && typeof item.element_styles === 'object' ? cloneDeep(item.element_styles) : {}
                });
            });
        }
    });
    return links;
}

function buildBioRuntimeSchema() {
    normalizeCurrentBioPage();
    syncHeroBlockWithProfile();
    syncLegacyBlocksFromLinks();
    return {
        version: 2,
        page_type: currentBioPage.page_type,
        page_status: currentBioPage.page_status,
        selected_goal_key: currentBioPage.selected_goal_key || '',
        blocks: cloneDeep(currentBioPage.blocks)
    };
}

function ensureBioPreviewTemplateCss(templateMeta) {
    if (!templateMeta || !templateMeta.folder) {
        return;
    }
    let tplLink = document.getElementById('bio-tpl-css-preview');
    if (!tplLink) {
        tplLink = document.createElement('link');
        tplLink.id = 'bio-tpl-css-preview';
        tplLink.rel = 'stylesheet';
        document.head.appendChild(tplLink);
    }
    tplLink.href = `templates/bio/${templateMeta.folder}/style.css?v=${Date.now()}`;
}

function getButtonInlineStyle(item) {
    const theme = getThemeConfig();
    if (item.link_type === 'social' && item.social_network === 'whatsapp') {
        return 'background:#25D366;color:#ffffff;border:none;';
    }
    if (item.link_type === 'pix') {
        return 'background:#32BCAD;color:#ffffff;border:none;';
    }
    if (theme.style === 'outline') {
        return `background:transparent;border:2px solid ${theme.btn};color:${theme.btn};`;
    }
    return `background:${theme.btn};color:${theme.bg};border:none;`;
}

function buildSocialWebUrl(item) {
    const value = (item.social_value || '').trim();
    if (!value) return '#';
    if (value.startsWith('http://') || value.startsWith('https://')) {
        return value;
    }
    switch (item.social_network) {
        case 'instagram': return `https://instagram.com/${value.replace(/^@/, '')}`;
        case 'facebook': return `https://facebook.com/${value}`;
        case 'tiktok': return `https://www.tiktok.com/@${value.replace(/^@/, '')}`;
        case 'youtube': return `https://youtube.com/@${value.replace(/^@/, '')}`;
        case 'twitter': return `https://x.com/${value.replace(/^@/, '')}`;
        case 'linkedin': return `https://linkedin.com/in/${value}`;
        case 'telegram': return `https://t.me/${value.replace(/^@/, '')}`;
        case 'whatsapp': return `https://wa.me/${value.replace(/\D/g, '')}`;
        case 'spotify': return value;
        case 'behance': return `https://behance.net/${value}`;
        case 'github': return `https://github.com/${value}`;
        default: return value;
    }
}

function buildEmbedUrl(url) {
    return buildEmbedConfig('youtube', url).embedUrl;
}

function normalizeEmbedProvider(provider) {
    const value = String(provider || '').trim().toLowerCase();
    if (Object.prototype.hasOwnProperty.call(EMBED_PROVIDER_CONFIG, value)) {
        return value;
    }
    return 'youtube';
}

function extractYouTubeEmbedUrl(parsed) {
    const host = (parsed.hostname || '').toLowerCase();
    let videoId = '';
    if (host.includes('youtube.com')) {
        videoId = parsed.searchParams.get('v') || '';
        if (!videoId) {
            const parts = parsed.pathname.split('/').filter(Boolean);
            if (parts[0] === 'shorts' && parts[1]) {
                videoId = parts[1];
            } else if (parts[0] === 'embed' && parts[1]) {
                videoId = parts[1];
            }
        }
    } else if (host === 'youtu.be') {
        videoId = parsed.pathname.split('/').filter(Boolean)[0] || '';
    }
    const safeId = (videoId || '').replace(/[^a-zA-Z0-9_-]/g, '');
    return safeId ? `https://www.youtube.com/embed/${safeId}` : '';
}

function extractVimeoEmbedUrl(parsed) {
    const host = (parsed.hostname || '').toLowerCase();
    if (!host.includes('vimeo.com')) return '';
    const videoId = (parsed.pathname.split('/').filter(Boolean)[0] || '').replace(/[^\d]/g, '');
    return videoId ? `https://player.vimeo.com/video/${videoId}` : '';
}

function extractGoogleMapsEmbedUrl(parsed, rawUrl) {
    const host = (parsed.hostname || '').toLowerCase();
    if (!host.includes('google.') || !host.includes('maps')) return '';
    if (parsed.pathname.includes('/maps/embed')) {
        return `https://www.google.com${parsed.pathname}${parsed.search || ''}`;
    }
    return `https://www.google.com/maps?q=${encodeURIComponent(rawUrl)}&output=embed`;
}

function extractSpotifyEmbedUrl(parsed) {
    const host = (parsed.hostname || '').toLowerCase();
    if (host !== 'open.spotify.com') return '';
    const parts = parsed.pathname.split('/').filter(Boolean);
    if (!parts.length) return '';
    let type = parts[0];
    let id = parts[1] || '';
    if (type === 'embed') {
        type = parts[1] || '';
        id = parts[2] || '';
    }
    const allowedTypes = ['track', 'album', 'playlist', 'artist', 'show', 'episode'];
    if (!allowedTypes.includes(type)) return '';
    const safeId = id.replace(/[^a-zA-Z0-9]/g, '');
    if (!safeId) return '';
    return `https://open.spotify.com/embed/${type}/${safeId}`;
}

function buildEmbedConfig(providerHint, url) {
    const rawUrl = String(url || '').trim();
    const provider = normalizeEmbedProvider(providerHint);
    const defaults = EMBED_PROVIDER_CONFIG[provider] || EMBED_PROVIDER_CONFIG.youtube;
    const base = {
        provider,
        embedUrl: '',
        allow: defaults.allow,
        referrerPolicy: defaults.referrerPolicy,
        sandbox: defaults.sandbox,
        errorMessage: 'Cole um link válido para incorporar este conteúdo.'
    };
    if (!rawUrl) {
        return {
            ...base,
            errorMessage: `Cole um link válido de ${defaults.label}.`
        };
    }

    let parsed;
    try {
        parsed = new URL(rawUrl);
    } catch (e) {
        return {
            ...base,
            errorMessage: 'Link inválido. Use uma URL completa com https://.'
        };
    }

    const autoCandidates = {
        youtube: extractYouTubeEmbedUrl(parsed),
        google_maps: extractGoogleMapsEmbedUrl(parsed, rawUrl),
        spotify: extractSpotifyEmbedUrl(parsed),
        vimeo: extractVimeoEmbedUrl(parsed)
    };
    const embedUrl = autoCandidates[provider] || '';
    if (!embedUrl) {
        return {
            ...base,
            errorMessage: `Não foi possível gerar o embed para ${defaults.label}. Verifique o link informado.`
        };
    }
    return { ...base, embedUrl, errorMessage: '' };
}

function renderActionButton(item, previewModeOnly = true) {
    const href = previewModeOnly ? '#' : (item.link_type === 'social' ? buildSocialWebUrl(item) : (item.url || '#'));
    const icon = item.icon_class ? `<i class="${escapeAttribute(item.icon_class)}"></i>` : '';
    const btnTypo = bioTypographyToCss(item.element_styles?.button);
    const spanAttr = btnTypo ? ` style="${escapeAttribute(btnTypo)}"` : '';
    return `
        <a href="${escapeAttribute(href)}" ${previewModeOnly ? 'onclick="return false;"' : 'target="_blank"'}
            class="bio-btn-preview btn-bio style-${escapeAttribute(getThemeConfig().style)}"
            style="${getButtonInlineStyle(item)}">
            ${icon}
            <span${spanAttr}>${escapeHtml(item.title || item.label || 'Acessar')}</span>
        </a>
    `;
}

function renderBlockHtml(block) {
    const title = block.title ? `<div class="bio-block-title"${bioTypoAttr(block, 'title')}>${escapeHtml(block.title)}</div>` : '';
    const subtitle = block.subtitle ? `<div class="bio-block-subtitle"${bioTypoAttr(block, 'subtitle')}>${escapeHtml(block.subtitle)}</div>` : '';
    const bodyOpen = `<div class="bio-copy"${bioTypoAttr(block, 'body')}>`;

    switch (block.type) {
        case 'heading':
            return `<section class="bio-block bio-block--heading">${block.badge ? `<div class="bio-badge"${bioTypoAttr(block, 'badge')}>${escapeHtml(block.badge)}</div>` : ''}${title}${subtitle}</section>`;
        case 'text':
            return `<section class="bio-block bio-block--text">${title}${subtitle}${bodyOpen}${nl2brSafe(block.content)}</div></section>`;
        case 'highlight':
            return `<section class="bio-block bio-highlight">${block.badge ? `<div class="bio-badge"${bioTypoAttr(block, 'badge')}>${escapeHtml(block.badge)}</div>` : ''}${title}${bodyOpen}${nl2brSafe(block.content)}</div></section>`;
        case 'cta':
            return `<section class="bio-block bio-block--cta">${title}${block.content ? `${bodyOpen}${nl2brSafe(block.content)}</div>` : ''}<div class="bio-action-grid">${renderActionButton({ title: block.label || 'Acessar', url: block.url, icon_class: block.icon_class, link_type: 'link', element_styles: block.element_styles })}</div></section>`;
        case 'links':
            return `<section class="bio-block bio-block--links">${title}${subtitle}<div class="links preview-links ${block.layout === 'grid' ? 'bio-action-grid grid' : 'bio-action-grid'}">${(block.items || []).map(item => renderActionButton(item)).join('')}</div></section>`;
        case 'social':
            return `<section class="bio-block bio-block--social">${title}${subtitle}<div class="bio-social-grid ${block.layout === 'grid' ? 'grid' : ''}">${(block.items || []).map(item => renderActionButton({ ...item, link_type: 'social' })).join('')}</div></section>`;
        case 'cards':
            return `<section class="bio-block bio-block--cards">${title}${subtitle}<div class="bio-card-grid">${(block.items || []).map(item => `
                <article class="bio-card-item">
                    ${item.badge ? `<div class="bio-badge"${bioItemTypoAttr(item, 'badge')}>${escapeHtml(item.badge)}</div>` : ''}
                    <h4${bioItemTypoAttr(item, 'cardTitle')}>${escapeHtml(item.title || 'Card')}</h4>
                    <p${bioItemTypoAttr(item, 'cardText')}>${escapeHtml(item.text || '')}</p>
                    ${item.url ? `<div style="margin-top:12px;">${renderActionButton({ title: item.label || 'Abrir', url: item.url, icon_class: item.icon_class || 'fas fa-arrow-up-right-from-square', link_type: 'link', element_styles: item.element_styles })}</div>` : ''}
                </article>
            `).join('')}</div></section>`;
        case 'faq':
            return `<section class="bio-block bio-block--faq">${title}${subtitle}<div class="bio-stack-list">${(block.items || []).map(item => `
                <article class="bio-faq-item">
                    <div class="bio-faq-question"${bioItemTypoAttr(item, 'question')}>${escapeHtml(item.title || 'Pergunta')}</div>
                    <div class="bio-faq-answer"${bioItemTypoAttr(item, 'answer')}>${nl2brSafe(item.text || '')}</div>
                </article>
            `).join('')}</div></section>`;
        case 'gallery':
            return `<section class="bio-block bio-block--gallery">${title}${subtitle}<div class="bio-gallery-grid">${(block.items || []).map(item => `
                <article class="bio-gallery-item">
                    ${item.image_url ? `<img src="${escapeAttribute(item.image_url)}" alt="${escapeAttribute(item.caption || item.title || 'Imagem')}">` : '<div class="bio-gallery-item" style="min-height:160px; display:flex; align-items:center; justify-content:center;">Adicione uma imagem</div>'}
                    ${item.caption ? `<div class="bio-gallery-caption"${bioItemTypoAttr(item, 'caption')}>${escapeHtml(item.caption)}</div>` : ''}
                </article>
            `).join('')}</div></section>`;
        case 'posts':
            return `<section class="bio-block bio-block--posts">${title}${subtitle}<div class="bio-post-list">${(block.items || []).map(item => `
                <article class="bio-post-item">
                    <h4${bioItemTypoAttr(item, 'postTitle')}>${escapeHtml(item.title || 'Post')}</h4>
                    <p${bioItemTypoAttr(item, 'postBody')}>${escapeHtml(item.text || '')}</p>
                    ${item.meta ? `<div class="bio-post-meta"${bioItemTypoAttr(item, 'meta')}>${escapeHtml(item.meta)}</div>` : ''}
                    ${item.url ? `<div style="margin-top:12px;">${renderActionButton({ title: item.label || 'Ler conteúdo', url: item.url, icon_class: 'fas fa-book-open', link_type: 'link', element_styles: item.element_styles })}</div>` : ''}
                </article>
            `).join('')}</div></section>`;
        case 'contact':
            return `<section class="bio-block bio-block--contact">${title}${subtitle}<div class="bio-contact-list">${(block.items || []).map(item => `
                <div class="bio-info-item bio-contact-row">
                    <i class="${escapeAttribute(item.icon_class || 'fas fa-circle')}"></i>
                    <div>
                        <strong${bioItemTypoAttr(item, 'lineTitle')}>${escapeHtml(item.title || 'Contato')}</strong>
                        <span${bioItemTypoAttr(item, 'lineText')}>${escapeHtml(item.text || '')}</span>
                    </div>
                </div>
            `).join('')}</div></section>`;
        case 'schedule':
            return `<section class="bio-block bio-block--schedule">${title}${subtitle}<div class="bio-info-list">${(block.items || []).map(item => `
                <div class="bio-info-item">
                    <strong${bioItemTypoAttr(item, 'lineTitle')}>${escapeHtml(item.title || 'Item')}</strong>
                    <span${bioItemTypoAttr(item, 'lineText')}>${escapeHtml(item.text || '')}</span>
                </div>
            `).join('')}</div></section>`;
        case 'video': {
            const provider = normalizeEmbedProvider(block.embed_provider || 'youtube');
            const cfg = buildEmbedConfig(provider, block.embed_url);
            const frameHtml = cfg.embedUrl
                ? `<div class="bio-video-frame"><iframe src="${escapeAttribute(cfg.embedUrl)}" allow="${escapeAttribute(cfg.allow)}" referrerpolicy="${escapeAttribute(cfg.referrerPolicy)}" sandbox="${escapeAttribute(cfg.sandbox)}" allowfullscreen loading="lazy"></iframe></div>`
                : `<div class="bio-copy bio-embed-note">${escapeHtml(cfg.errorMessage)}</div>`;
            return `<section class="bio-block bio-block--video">${title}${subtitle}${frameHtml}${block.content ? `<div class="bio-gallery-caption"${bioTypoAttr(block, 'caption')}>${nl2brSafe(block.content)}</div>` : ''}</section>`;
        }
        case 'divider':
            return `<section class="bio-block bio-block--divider"><div class="bio-divider-line"></div>${block.title ? `<div class="bio-divider-label"${bioTypoAttr(block, 'title')}>${escapeHtml(block.title)}</div>` : ''}<div class="bio-divider-line"></div></section>`;
        case 'form': {
            const fields = (block.items || []).map(f => `
                <div class="bio-form-field bio-form-field--preview">
                    <div class="bio-form-label"${bioItemTypoAttr(f, 'label')}>${escapeHtml(f.label || 'Pergunta')}${f.required ? ' *' : ''}</div>
                    <div class="bio-form-control bio-form-control--fake">${escapeHtml(BIO_FORM_FIELD_TYPES.find(t => t.value === f.field_type)?.label || 'Campo')}</div>
                </div>
            `).join('');
            const submitTypo = bioTypographyToCss(block.element_styles?.submit);
            const submitSpan = submitTypo ? ` style="${escapeAttribute(submitTypo)}"` : '';
            return `<section class="bio-block bio-block--form">${title}${subtitle}${block.content ? `<div class="bio-form-intro bio-copy"${bioTypoAttr(block, 'body')}>${nl2brSafe(block.content)}</div>` : ''}<div class="bio-public-form bio-public-form--preview">${fields}<div class="btn-bio ${escapeAttribute(getThemeConfig().style)}" style="${getButtonInlineStyle({ link_type: 'link' })}"><span${submitSpan}>Enviar respostas</span></div><div class="bio-form-hint">Pré-visualização: o envio real funciona na página publicada.</div></div></section>`;
        }
        default:
            return `<section class="bio-block">${title}${subtitle}${block.content ? `${bodyOpen}${nl2brSafe(block.content)}</div>` : ''}</section>`;
    }
}

function renderBioPageShellHtml(schema, isPreview = true, options = {}) {
    const meta = getBioPageMeta();
    const theme = getThemeConfig();
    const templateMeta = getBioTemplateMetaById(currentBioPage?.template_id || 1) || { slug: 'classic' };
    const tplSlug = String(templateMeta.slug || 'classic').replace(/[^a-zA-Z0-9_-]/g, '') || 'classic';
    const blocks = schema.blocks || [];
    const heroBlock = blocks.find(block => block.type === 'hero') || bioCreateBlock('hero');
    const contentBlocks = blocks.filter(block => block.id !== heroBlock.id);
    const slugLabel = meta.slug ? `linkneo.pro/bio/${meta.slug}` : 'Defina um slug para publicar';
    const hasHeroBadge = Object.prototype.hasOwnProperty.call(heroBlock, 'badge');
    const hasHeroSubtitle = Object.prototype.hasOwnProperty.call(heroBlock, 'subtitle');
    const heroBadge = hasHeroBadge ? String(heroBlock.badge ?? '') : String(BIO_PAGE_TYPE_LABELS[schema.page_type] || 'Bio');
    const heroSubtitle = hasHeroSubtitle ? String(heroBlock.subtitle ?? '') : '';
    const heroAvatarShape = normalizeAvatarShape(heroBlock.avatar_shape || meta.avatar_shape || 'rounded');
    const heroAvatarImage = heroBlock.media_url || meta.avatar || 'https://cdn-icons-png.flaticon.com/512/1243/1243933.png';
    const heroCoverRaw = String(heroBlock.cover_image_url || meta.cover_image || '').trim();
    const isAtelierMinimal = tplSlug === 'atelier-minimal';
    const heroCoverLayer = heroCoverRaw && !isAtelierMinimal
        ? `<div class="bio-header-cover-layer" style="background-image:url('${escapeAttribute(heroCoverRaw)}');"></div>`
        : '';
    const atelierCoverAttr = heroCoverRaw && isAtelierMinimal
        ? ` style="--bio-atelier-cover:url('${escapeAttribute(heroCoverRaw)}');"`
        : '';

    const includeBackdrop = options.includeBackdrop !== false;
    const tv = `--bg:${theme.bg};--primary:${theme.btn};--text:${theme.text};`;
    const backdropHtml = includeBackdrop
        ? `<div class="bio-public-bg-fixed" aria-hidden="true" style="${tv}"></div>`
        : '';

    return `
        ${backdropHtml}
        <div class="bio-page-shell tpl-${tplSlug} ${isPreview ? 'is-preview' : 'is-public'} ${bioPreviewMode === 'desktop' ? 'is-desktop' : ''}" style="${tv}">
            <header class="bio-header ${heroCoverRaw ? 'bio-header--has-cover' : ''} ${heroBlock.style === 'left' ? 'bio-hero-align-left' : ''}"${atelierCoverAttr}>
                ${heroCoverLayer}
                <div class="bio-avatar bio-avatar--${escapeAttribute(heroAvatarShape)}" style="background-image:url('${escapeAttribute(heroAvatarImage)}');background-size:contain;background-position:center;background-repeat:no-repeat;"></div>
                <div class="bio-hero-copy">
                    <div class="bio-kicker"${bioTypoAttr(heroBlock, 'kicker')}>${escapeHtml(heroBadge)}</div>
                    <div class="preview-name"${bioTypoAttr(heroBlock, 'title')}>${escapeHtml(meta.title || heroBlock.title || 'Sua página')}</div>
                    ${heroSubtitle ? `<div class="bio-subtitle"${bioTypoAttr(heroBlock, 'subtitle')}>${escapeHtml(heroSubtitle)}</div>` : ''}
                    <div class="preview-bio"${bioTypoAttr(heroBlock, 'body')}>${nl2brSafe(meta.description || heroBlock.content || '')}</div>
                </div>  
            </header>
            <div class="links-wrapper">
                <div class="bio-content">
                    ${contentBlocks.map(renderBlockHtml).join('')}
                </div>
                <div class="bio-footer">Criado com <a href="https://linkneo.pro" target="_blank"> LinkNeo.pro</a></div>
            </div>
        </div>
    `;
}

function renderGoalShortcuts() {
    const container = document.getElementById('bio-goal-shortcuts');
    if (!container) return;

    container.innerHTML = Object.values(GOAL_TEMPLATES).map(goal => `
        <button class="bio-goal-chip ${currentBioPage.selected_goal_key === goal.key ? 'active' : ''}" onclick="applyGoalTemplate('${goal.key}')">
            ${escapeHtml(goal.name.replace('Modelo: ', ''))}
        </button>
    `).join('');
}

function renderBioTemplateFilters() {
    const container = document.getElementById('bio-template-filters');
    if (!container) return;
    const filters = [
        { value: 'all', label: 'Todos' },
        { value: 'bio', label: 'Bio' },
        { value: 'landing', label: 'Landing' },
        { value: 'blog', label: 'Blog' },
        { value: 'campaign', label: 'Campanha' },
        { value: 'premium', label: 'Premium' }
    ];
    container.innerHTML = filters.map(filter => `
        <button class="bio-filter-chip ${bioTemplateFilter === filter.value ? 'active' : ''}" onclick="setBioTemplateFilter('${filter.value}')">${filter.label}</button>
    `).join('');
}

window.setBioTemplateFilter = function (value) {
    bioTemplateFilter = value;
    renderBioTemplateFilters();
    renderBioTemplatesGallery();
};

function renderBioTemplatesGallery() {
    const list = document.getElementById('bio-templates-list');
    if (!list) return;

    const templates = (bioTemplatesData || [])
        .map(item => getBioTemplateMetaById(item.id))
        .filter(template => template && template.folder);
    const filteredTemplates = templates.filter(template => {
        if (bioTemplateFilter === 'all') return true;
        if (bioTemplateFilter === 'premium') return Boolean(template.premium);
        const pageTypes = Array.isArray(template.page_types)
            ? template.page_types
            : (template.page_types ? [template.page_types] : []);
        return pageTypes.includes(bioTemplateFilter);
    });

    if (!filteredTemplates.length) {
        list.innerHTML = '<div class="bio-empty-state">Nenhum template disponivel para esta versao.</div>';
        return;
    }

    list.innerHTML = filteredTemplates.map(template => `
        <div class="template-option ${String(currentBioPage.template_id) === String(template.id) ? 'active' : ''}" onclick="switchBioTemplate('${template.id}')">
            ${template.thumb_html || '<div class="tpl-thumb"></div>'}
            <span>${escapeHtml(template.name)}</span>
        </div>
    `).join('');
}

function renderBioBlockLibrary() {
    const container = document.getElementById('bio-block-library');
    if (!container) return;
    container.innerHTML = BIO_BLOCK_LIBRARY.map(item => `
        <button class="bio-block-chip" onclick="addBioBlock('${item.type}')">
            <strong>${escapeHtml(item.title)}</strong>
            <span>${escapeHtml(item.description)}</span>
        </button>
    `).join('');
}

function bioSocialNetworkSelectHtml(blockId, itemIndex, selectedKey) {
    const keys = typeof SOCIAL_NETWORKS === 'object' && SOCIAL_NETWORKS ? Object.keys(SOCIAL_NETWORKS) : [];
    const sel = selectedKey || 'instagram';
    if (!keys.length) {
        return `<div class="form-group"><label>Rede (código)</label><input type="text" value="${escapeAttribute(sel)}" oninput="bioUpdateBlockItemField('${blockId}', ${itemIndex}, 'social_network', this.value)"></div>`;
    }
    const opts = keys.map(k => {
        const m = SOCIAL_NETWORKS[k];
        return `<option value="${escapeAttribute(k)}" ${sel === k ? 'selected' : ''}>${escapeHtml(m.name || k)}</option>`;
    }).join('');
    return `<div class="form-group"><label>Rede</label><select onchange="bioUpdateBlockItemSocialNetwork('${blockId}', ${itemIndex}, this.value)">${opts}</select></div>`;
}

function renderBlockItemsEditor(block) {
    if (!Array.isArray(block.items) || !block.items.length) {
        return '<div class="bio-empty-state">Nenhum item configurado.</div>';
    }

    return block.items.map((item, itemIndex) => {
        let fields = '';
        switch (block.type) {
            case 'links': {
                const lt = item.link_type || 'link';
                const typeSelect = `
                    <div class="form-group"><label>Tipo de botão</label><select onchange="bioSetBlockLinkItemType('${block.id}', ${itemIndex}, this.value)">
                        <option value="link" ${lt === 'link' ? 'selected' : ''}>Link</option>
                        <option value="pix" ${lt === 'pix' ? 'selected' : ''}>Pix</option>
                        <option value="social" ${lt === 'social' ? 'selected' : ''}>Rede social</option>
                    </select></div>`;
                if (lt === 'social') {
                    const net = item.social_network || 'instagram';
                    const meta = typeof SOCIAL_NETWORKS === 'object' && SOCIAL_NETWORKS && SOCIAL_NETWORKS[net]
                        ? SOCIAL_NETWORKS[net]
                        : { name: 'Rede', placeholder: '@usuario ou link', icon: 'fas fa-share-alt' };
                    fields = `
                        <div class="form-group"><label>Título do botão</label><input type="text" value="${escapeAttribute(item.title || '')}" oninput="bioUpdateBlockItemField('${block.id}', ${itemIndex}, 'title', this.value)"></div>
                        ${typeSelect}
                        ${bioSocialNetworkSelectHtml(block.id, itemIndex, net)}
                        <div class="form-group"><label>${escapeHtml(meta.placeholder || 'Usuário ou link')}</label><input type="text" value="${escapeAttribute(item.social_value || '')}" oninput="bioUpdateBlockItemField('${block.id}', ${itemIndex}, 'social_value', this.value)"></div>
                        <div class="control-group-grid">
                            <div class="form-group"><label>Ícone (classe CSS)</label>${bioIconClassInputHtml(item.icon_class || '', `bioUpdateBlockItemField('${block.id}', ${itemIndex}, 'icon_class', this.value)`)}</div>
                            <div class="form-group"><label>Destaque</label><select onchange="bioUpdateBlockItemField('${block.id}', ${itemIndex}, 'is_featured', this.value === '1' ? 1 : 0)"><option value="0" ${!item.is_featured ? 'selected' : ''}>Normal</option><option value="1" ${item.is_featured ? 'selected' : ''}>Destaque</option></select></div>
                        </div>
                    `;
                    break;
                }
                const isPix = lt === 'pix';
                const valueFieldLabel = isPix ? 'Chave Pix' : 'URL';
                const valueField = isPix ? (item.social_value || '') : (item.url || '');
                const valueFieldKey = isPix ? 'social_value' : 'url';
                const inputType = lt === 'link' ? 'url' : 'text';
                fields = `
                    <div class="form-group"><label>Título do botão</label><input type="text" value="${escapeAttribute(item.title || '')}" oninput="bioUpdateBlockItemField('${block.id}', ${itemIndex}, 'title', this.value)"></div>
                    ${typeSelect}
                    <div class="form-group"><label>${valueFieldLabel}</label><input type="${inputType}" value="${escapeAttribute(valueField)}" oninput="bioUpdateBlockItemField('${block.id}', ${itemIndex}, '${valueFieldKey}', this.value)" placeholder="${escapeAttribute(isPix ? 'Sua chave Pix' : 'https://...')}"></div>
                    <div class="control-group-grid">
                        <div class="form-group"><label>Ícone (classe CSS)</label>${bioIconClassInputHtml(item.icon_class || '', `bioUpdateBlockItemField('${block.id}', ${itemIndex}, 'icon_class', this.value)`)}</div>
                        <div class="form-group"><label>Destaque</label><select onchange="bioUpdateBlockItemField('${block.id}', ${itemIndex}, 'is_featured', this.value === '1' ? 1 : 0)"><option value="0" ${!item.is_featured ? 'selected' : ''}>Normal</option><option value="1" ${item.is_featured ? 'selected' : ''}>Destaque</option></select></div>
                    </div>
                `;
                break;
            }
            case 'social': {
                const net = item.social_network || 'instagram';
                const meta = typeof SOCIAL_NETWORKS === 'object' && SOCIAL_NETWORKS && SOCIAL_NETWORKS[net]
                    ? SOCIAL_NETWORKS[net]
                    : { name: 'Rede', placeholder: '@usuario ou link', icon: 'fas fa-share-alt' };
                fields = `
                    ${bioSocialNetworkSelectHtml(block.id, itemIndex, net)}
                    <div class="form-group"><label>Título do botão</label><input type="text" value="${escapeAttribute(item.title || '')}" oninput="bioUpdateBlockItemField('${block.id}', ${itemIndex}, 'title', this.value)"></div>
                    <div class="form-group"><label>${escapeHtml(meta.placeholder || 'Usuário ou link')}</label><input type="text" value="${escapeAttribute(item.social_value || '')}" oninput="bioUpdateBlockItemField('${block.id}', ${itemIndex}, 'social_value', this.value)"></div>
                    <div class="control-group-grid">
                        <div class="form-group"><label>Ícone (classe CSS)</label>${bioIconClassInputHtml(item.icon_class || '', `bioUpdateBlockItemField('${block.id}', ${itemIndex}, 'icon_class', this.value)`)}</div>
                        <div class="form-group"><label>Destaque</label><select onchange="bioUpdateBlockItemField('${block.id}', ${itemIndex}, 'is_featured', this.value === '1' ? 1 : 0)"><option value="0" ${!item.is_featured ? 'selected' : ''}>Normal</option><option value="1" ${item.is_featured ? 'selected' : ''}>Destaque</option></select></div>
                    </div>
                `;
                break;
            }
            case 'cards':
                fields = `
                    <div class="form-group"><label>Título</label><input type="text" value="${escapeAttribute(item.title || '')}" oninput="bioUpdateBlockItemField('${block.id}', ${itemIndex}, 'title', this.value)"></div>
                    <div class="form-group"><label>Descrição</label><textarea oninput="bioUpdateBlockItemField('${block.id}', ${itemIndex}, 'text', this.value)">${escapeHtml(item.text || '')}</textarea></div>
                    <div class="control-group-grid">
                        <div class="form-group"><label>Botão</label><input type="text" value="${escapeAttribute(item.label || '')}" oninput="bioUpdateBlockItemField('${block.id}', ${itemIndex}, 'label', this.value)"></div>
                        <div class="form-group"><label>Badge</label><input type="text" value="${escapeAttribute(item.badge || '')}" oninput="bioUpdateBlockItemField('${block.id}', ${itemIndex}, 'badge', this.value)"></div>
                    </div>
                    <div class="form-group"><label>URL</label><input type="url" value="${escapeAttribute(item.url || '')}" oninput="bioUpdateBlockItemField('${block.id}', ${itemIndex}, 'url', this.value)"></div>
                `;
                break;
            case 'gallery':
                fields = `
                    <div class="form-group"><label>URL da imagem</label><input type="url" value="${escapeAttribute(item.image_url || '')}" oninput="bioUpdateBlockItemField('${block.id}', ${itemIndex}, 'image_url', this.value)"></div>
                    <div class="control-group-grid">
                        <div class="form-group"><label>Legenda</label><input type="text" value="${escapeAttribute(item.caption || '')}" oninput="bioUpdateBlockItemField('${block.id}', ${itemIndex}, 'caption', this.value)"></div>
                        <div class="form-group"><label>Link opcional</label><input type="url" value="${escapeAttribute(item.url || '')}" oninput="bioUpdateBlockItemField('${block.id}', ${itemIndex}, 'url', this.value)"></div>
                    </div>
                `;
                break;
            case 'posts':
                fields = `
                    <div class="form-group"><label>Título do post</label><input type="text" value="${escapeAttribute(item.title || '')}" oninput="bioUpdateBlockItemField('${block.id}', ${itemIndex}, 'title', this.value)"></div>
                    <div class="form-group"><label>Resumo</label><textarea oninput="bioUpdateBlockItemField('${block.id}', ${itemIndex}, 'text', this.value)">${escapeHtml(item.text || '')}</textarea></div>
                    <div class="control-group-grid">
                        <div class="form-group"><label>Meta</label><input type="text" value="${escapeAttribute(item.meta || '')}" oninput="bioUpdateBlockItemField('${block.id}', ${itemIndex}, 'meta', this.value)"></div>
                        <div class="form-group"><label>URL</label><input type="url" value="${escapeAttribute(item.url || '')}" oninput="bioUpdateBlockItemField('${block.id}', ${itemIndex}, 'url', this.value)"></div>
                    </div>
                `;
                break;
            case 'faq':
            case 'schedule':
                fields = `
                    <div class="form-group"><label>${block.type === 'faq' ? 'Pergunta' : 'Título'}</label><input type="text" value="${escapeAttribute(item.title || '')}" oninput="bioUpdateBlockItemField('${block.id}', ${itemIndex}, 'title', this.value)"></div>
                    <div class="form-group"><label>${block.type === 'faq' ? 'Resposta' : 'Descrição'}</label><textarea oninput="bioUpdateBlockItemField('${block.id}', ${itemIndex}, 'text', this.value)">${escapeHtml(item.text || '')}</textarea></div>
                `;
                break;
            case 'contact':
                fields = `
                    <div class="control-group-grid">
                        <div class="form-group"><label>Título</label><input type="text" value="${escapeAttribute(item.title || '')}" oninput="bioUpdateBlockItemField('${block.id}', ${itemIndex}, 'title', this.value)"></div>
                        <div class="form-group"><label>Ícone</label>${bioIconClassInputHtml(item.icon_class || '', `bioUpdateBlockItemField('${block.id}', ${itemIndex}, 'icon_class', this.value)`)}</div>
                    </div>
                    <div class="form-group"><label>Texto</label><input type="text" value="${escapeAttribute(item.text || '')}" oninput="bioUpdateBlockItemField('${block.id}', ${itemIndex}, 'text', this.value)"></div>
                `;
                break;
            case 'form': {
                const ftOpts = BIO_FORM_FIELD_TYPES.map(t =>
                    `<option value="${t.value}" ${item.field_type === t.value ? 'selected' : ''}>${escapeHtml(t.label)}</option>`
                ).join('');
                const needsOpts = ['select', 'radio', 'multiselect'].includes(item.field_type);
                const sib = (block.items || []).map((it, ii) => (ii === itemIndex ? null : `${block.id}|${it.id}`)).filter(Boolean);
                const sibOpts = ['<option value="">(não usar condição)</option>'].concat(
                    sib.map(k => `<option value="${escapeAttribute(k)}" ${item.show_if && item.show_if.field_key === k ? 'selected' : ''}>${escapeHtml(k)}</option>`)
                ).join('');
                const opts = Array.isArray(item.options) ? item.options : [];
                const optRows = opts.map((op, oi) => `
                    <div class="control-group-grid" style="align-items:flex-end;">
                        <div class="form-group"><label>Valor</label><input type="text" value="${escapeAttribute(op.value || '')}" oninput="bioUpdateFormOption('${block.id}',${itemIndex},${oi},'value',this.value)"></div>
                        <div class="form-group"><label>Rótulo</label><input type="text" value="${escapeAttribute(op.label || '')}" oninput="bioUpdateFormOption('${block.id}',${itemIndex},${oi},'label',this.value)"></div>
                        <button type="button" class="btn-outline" onclick="bioRemoveFormOption('${block.id}',${itemIndex},${oi})" title="Remover"><i class="fas fa-trash"></i></button>
                    </div>
                `).join('');
                fields = `
                    <div class="form-group"><label>Rótulo da pergunta</label><input type="text" value="${escapeAttribute(item.label || '')}" oninput="bioUpdateBlockItemField('${block.id}', ${itemIndex}, 'label', this.value)"></div>
                    <div class="form-group"><label>Tipo de campo</label><select onchange="bioUpdateBlockItemField('${block.id}', ${itemIndex}, 'field_type', this.value)">${ftOpts}</select></div>
                    <div class="control-group-grid">
                        <div class="form-group"><label>Obrigatoriedade</label><select onchange="bioUpdateBlockItemField('${block.id}', ${itemIndex}, 'required', this.value === '1' ? 1 : 0)"><option value="0" ${!item.required ? 'selected' : ''}>Opcional</option><option value="1" ${item.required ? 'selected' : ''}>Obrigatório</option></select></div>
                        <div class="form-group"><label>Máx. caracteres (0 = sem limite)</label><input type="number" min="0" value="${escapeAttribute(String(item.max_length ?? 0))}" oninput="bioUpdateBlockItemField('${block.id}', ${itemIndex}, 'max_length', parseInt(this.value || '0', 10) || 0)"></div>
                    </div>
                    <div class="form-group"><label>Placeholder</label><input type="text" value="${escapeAttribute(item.placeholder || '')}" oninput="bioUpdateBlockItemField('${block.id}', ${itemIndex}, 'placeholder', this.value)"></div>
                    <div class="form-group"><label>Texto de ajuda</label><input type="text" value="${escapeAttribute(item.help_text || '')}" oninput="bioUpdateBlockItemField('${block.id}', ${itemIndex}, 'help_text', this.value)"></div>
                    <div class="form-group"><label>Valor padrão</label><input type="text" value="${escapeAttribute(item.default_value || '')}" oninput="bioUpdateBlockItemField('${block.id}', ${itemIndex}, 'default_value', this.value)"></div>
                    <div class="form-group"><label>Validação extra (regex HTML5, opcional)</label><input type="text" value="${escapeAttribute(item.mask_pattern || '')}" oninput="bioUpdateBlockItemField('${block.id}', ${itemIndex}, 'mask_pattern', this.value)" placeholder="ex: ^[0-9]+$"></div>
                    ${needsOpts ? `<div style="margin-top:10px;padding-top:10px;border-top:1px solid rgba(255,255,255,0.08);"><strong>Opções</strong>${optRows}<button type="button" class="btn-outline" style="margin-top:8px;width:100%;" onclick="bioAddFormOption('${block.id}',${itemIndex})"><i class="fas fa-plus"></i> Adicionar opção</button></div>` : ''}
                    <div style="margin-top:10px;padding-top:10px;border-top:1px solid rgba(255,255,255,0.08);"><strong>Condição simples</strong>
                        <div class="form-group"><label>Mostrar se o campo (chave)</label><select onchange="bioUpdateFormShowIfKey('${block.id}',${itemIndex},this.value)">${sibOpts}</select></div>
                        <div class="control-group-grid">
                            <div class="form-group"><label>Operador</label><select onchange="bioUpdateFormShowIfOp('${block.id}',${itemIndex},this.value)"><option value="eq" ${(item.show_if && item.show_if.operator === 'neq') ? '' : 'selected'}>igual a</option><option value="neq" ${item.show_if && item.show_if.operator === 'neq' ? 'selected' : ''}>diferente de</option></select></div>
                            <div class="form-group"><label>Valor esperado</label><input type="text" value="${escapeAttribute(item.show_if && item.show_if.value != null ? item.show_if.value : '')}" oninput="bioUpdateFormShowIfVal('${block.id}',${itemIndex},this.value)"></div>
                        </div>
                    </div>
                `;
                break;
            }
            default:
                fields = `
                    <div class="form-group"><label>Título</label><input type="text" value="${escapeAttribute(item.title || '')}" oninput="bioUpdateBlockItemField('${block.id}', ${itemIndex}, 'title', this.value)"></div>
                    <div class="form-group"><label>Texto</label><textarea oninput="bioUpdateBlockItemField('${block.id}', ${itemIndex}, 'text', this.value)">${escapeHtml(item.text || '')}</textarea></div>
                `;
                break;
        }

        let premiumItemExtras = '';
        if (isCurrentBioTemplatePremium()) {
            if (block.type === 'cards') {
                premiumItemExtras = bioPremiumTypoSectionItem(block, itemIndex, 'badge', 'Badge do card')
                    + bioPremiumTypoSectionItem(block, itemIndex, 'cardTitle', 'Título do card')
                    + bioPremiumTypoSectionItem(block, itemIndex, 'cardText', 'Texto do card')
                    + bioPremiumTypoSectionItem(block, itemIndex, 'button', 'Texto do botão');
            } else if (block.type === 'faq') {
                premiumItemExtras = bioPremiumTypoSectionItem(block, itemIndex, 'question', 'Pergunta')
                    + bioPremiumTypoSectionItem(block, itemIndex, 'answer', 'Resposta');
            } else if (block.type === 'gallery') {
                premiumItemExtras = bioPremiumTypoSectionItem(block, itemIndex, 'caption', 'Legenda');
            } else if (block.type === 'posts') {
                premiumItemExtras = bioPremiumTypoSectionItem(block, itemIndex, 'postTitle', 'Título do post')
                    + bioPremiumTypoSectionItem(block, itemIndex, 'postBody', 'Resumo')
                    + bioPremiumTypoSectionItem(block, itemIndex, 'meta', 'Meta / data')
                    + bioPremiumTypoSectionItem(block, itemIndex, 'button', 'Botão');
            } else if (block.type === 'contact' || block.type === 'schedule') {
                premiumItemExtras = bioPremiumTypoSectionItem(block, itemIndex, 'lineTitle', 'Título da linha')
                    + bioPremiumTypoSectionItem(block, itemIndex, 'lineText', 'Texto da linha');
            } else if (block.type === 'form') {
                premiumItemExtras = bioPremiumTypoSectionItem(block, itemIndex, 'label', 'Rótulo da pergunta');
            } else if (block.type === 'links' || block.type === 'social') {
                premiumItemExtras = bioPremiumTypoSectionItem(block, itemIndex, 'button', 'Botão');
            }
        }

        const itemReorder = ['links', 'social'].includes(block.type) ? `
                        <button type="button" onclick="bioMoveBlockItem('${block.id}', ${itemIndex}, -1)" ${itemIndex === 0 ? 'disabled' : ''} title="Mover para cima"><i class="fas fa-arrow-up"></i></button>
                        <button type="button" onclick="bioMoveBlockItem('${block.id}', ${itemIndex}, 1)" ${itemIndex === block.items.length - 1 ? 'disabled' : ''} title="Mover para baixo"><i class="fas fa-arrow-down"></i></button>
        ` : '';

        return `
            <div class="bio-editor-card" style="padding:14px; margin-top:10px;">
                <div class="bio-editor-card-header">
                    <div>
                        <h4>Item ${itemIndex + 1}</h4>
                    </div>
                    <div class="bio-editor-card-actions">
                        ${itemReorder}
                        <button type="button" onclick="bioRemoveBlockItem('${block.id}', ${itemIndex})"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
                ${fields}
                ${premiumItemExtras}
            </div>
        `;
    }).join('');
}

function renderBioBlockEditorFields(block) {
    switch (block.type) {
        case 'hero':
            return `
                <div class="control-group-grid">
                    <div class="form-group"><label>Badge</label><input type="text" value="${escapeAttribute(block.badge || '')}" oninput="bioUpdateBlockField('${block.id}', 'badge', this.value)"></div>
                    <div class="form-group"><label>Subtítulo</label><input type="text" value="${escapeAttribute(block.subtitle || '')}" oninput="bioUpdateBlockField('${block.id}', 'subtitle', this.value)"></div>
                </div>
                <div class="bio-block-note">O título, a descrição, a foto de perfil e a <strong>imagem de capa</strong> do topo são editados na aba Perfil.</div>
                ${bioPremiumTypoSectionBlock(block, 'kicker', 'Badge (kicker)')}
                ${bioPremiumTypoSectionBlock(block, 'title', 'Nome / título principal')}
                ${bioPremiumTypoSectionBlock(block, 'subtitle', 'Subtítulo do topo')}
                ${bioPremiumTypoSectionBlock(block, 'body', 'Descrição / bio')}
            `;
        case 'heading':
            return `
                <div class="form-group"><label>Badge</label><input type="text" value="${escapeAttribute(block.badge || '')}" oninput="bioUpdateBlockField('${block.id}', 'badge', this.value)"></div>
                <div class="form-group"><label>Título</label><input type="text" value="${escapeAttribute(block.title || '')}" oninput="bioUpdateBlockField('${block.id}', 'title', this.value)"></div>
                <div class="form-group"><label>Subtítulo</label><input type="text" value="${escapeAttribute(block.subtitle || '')}" oninput="bioUpdateBlockField('${block.id}', 'subtitle', this.value)"></div>
                ${bioPremiumTypoSectionBlock(block, 'badge', 'Badge')}
                ${bioPremiumTypoSectionBlock(block, 'title', 'Título')}
                ${bioPremiumTypoSectionBlock(block, 'subtitle', 'Subtítulo')}
            `;
        case 'text':
            return `
                <div class="form-group"><label>Título</label><input type="text" value="${escapeAttribute(block.title || '')}" oninput="bioUpdateBlockField('${block.id}', 'title', this.value)"></div>
                <div class="form-group"><label>Conteúdo</label><textarea oninput="bioUpdateBlockField('${block.id}', 'content', this.value)">${escapeHtml(block.content || '')}</textarea></div>
                ${bioPremiumTypoSectionBlock(block, 'title', 'Título')}
                ${bioPremiumTypoSectionBlock(block, 'body', 'Texto')}
            `;
        case 'highlight':
            return `
                <div class="control-group-grid">
                    <div class="form-group"><label>Badge</label><input type="text" value="${escapeAttribute(block.badge || '')}" oninput="bioUpdateBlockField('${block.id}', 'badge', this.value)"></div>
                    <div class="form-group"><label>Título</label><input type="text" value="${escapeAttribute(block.title || '')}" oninput="bioUpdateBlockField('${block.id}', 'title', this.value)"></div>
                </div>
                <div class="form-group"><label>Conteúdo</label><textarea oninput="bioUpdateBlockField('${block.id}', 'content', this.value)">${escapeHtml(block.content || '')}</textarea></div>
                ${bioPremiumTypoSectionBlock(block, 'badge', 'Badge')}
                ${bioPremiumTypoSectionBlock(block, 'title', 'Título')}
                ${bioPremiumTypoSectionBlock(block, 'body', 'Texto')}
            `;
        case 'cta':
            return `
                <div class="form-group"><label>Título</label><input type="text" value="${escapeAttribute(block.title || '')}" oninput="bioUpdateBlockField('${block.id}', 'title', this.value)"></div>
                <div class="form-group"><label>Descrição</label><textarea oninput="bioUpdateBlockField('${block.id}', 'content', this.value)">${escapeHtml(block.content || '')}</textarea></div>
                <div class="control-group-grid">
                    <div class="form-group"><label>Texto do botão</label><input type="text" value="${escapeAttribute(block.label || '')}" oninput="bioUpdateBlockField('${block.id}', 'label', this.value)"></div>
                    <div class="form-group"><label>Ícone</label>${bioIconClassInputHtml(block.icon_class || '', `bioUpdateBlockField('${block.id}', 'icon_class', this.value)`)}</div>
                </div>
                <div class="form-group"><label>URL</label><input type="url" value="${escapeAttribute(block.url || '')}" oninput="bioUpdateBlockField('${block.id}', 'url', this.value)"></div>
                ${bioPremiumTypoSectionBlock(block, 'title', 'Título')}
                ${bioPremiumTypoSectionBlock(block, 'body', 'Descrição')}
                ${bioPremiumTypoSectionBlock(block, 'button', 'Botão')}
            `;
        case 'links':
        case 'social':
            return `
                <div class="control-group-grid">
                    <div class="form-group"><label>Título do grupo</label><input type="text" value="${escapeAttribute(block.title || '')}" oninput="bioUpdateBlockField('${block.id}', 'title', this.value)"></div>
                    <div class="form-group"><label>Layout</label>
                        <select onchange="bioUpdateBlockField('${block.id}', 'layout', this.value)">
                            <option value="stack" ${block.layout === 'stack' ? 'selected' : ''}>Empilhado</option>
                            <option value="grid" ${block.layout === 'grid' ? 'selected' : ''}>Grade</option>
                        </select>
                    </div>
                </div>
                <div class="bio-block-note">Um único grupo na ordem exata da página. Reordene pelas setas na lista “Ações rápidas e redes” ou nos itens abaixo.</div>
                ${bioPremiumTypoSectionBlock(block, 'title', 'Título do bloco')}
                ${bioPremiumTypoSectionBlock(block, 'subtitle', 'Subtítulo do bloco')}
                ${renderBlockItemsEditor(block)}
                <button type="button" class="btn-outline" style="width:100%; margin-top:10px;" onclick="bioAddBlockItem('${block.id}')"><i class="fas fa-plus"></i> ${block.type === 'social' ? 'Adicionar rede' : 'Adicionar botão'}</button>
            `;
        case 'cards':
        case 'faq':
        case 'gallery':
        case 'posts':
        case 'contact':
        case 'schedule':
            return `
                <div class="form-group"><label>Título do bloco</label><input type="text" value="${escapeAttribute(block.title || '')}" oninput="bioUpdateBlockField('${block.id}', 'title', this.value)"></div>
                <div class="form-group"><label>Subtítulo (opcional)</label><input type="text" value="${escapeAttribute(block.subtitle || '')}" oninput="bioUpdateBlockField('${block.id}', 'subtitle', this.value)"></div>
                ${bioPremiumTypoSectionBlock(block, 'title', 'Título do bloco')}
                ${bioPremiumTypoSectionBlock(block, 'subtitle', 'Subtítulo do bloco')}
                ${renderBlockItemsEditor(block)}
                <button type="button" class="btn-outline" style="width:100%; margin-top:10px;" onclick="bioAddBlockItem('${block.id}')"><i class="fas fa-plus"></i> Adicionar item</button>
            `;
        case 'video':
            const currentProvider = normalizeEmbedProvider(block.embed_provider || 'youtube');
            const providerCfg = EMBED_PROVIDER_CONFIG[currentProvider] || EMBED_PROVIDER_CONFIG.youtube;
            return `
                <div class="form-group"><label>Título</label><input type="text" value="${escapeAttribute(block.title || '')}" oninput="bioUpdateBlockField('${block.id}', 'title', this.value)"></div>
                <div class="form-group"><label>Provedor</label>
                    <select onchange="bioUpdateBlockField('${block.id}', 'embed_provider', this.value); renderBioBlocksEditor(); updateBioPreview();">
                        <option value="youtube" ${currentProvider === 'youtube' ? 'selected' : ''}>YouTube</option>
                        <option value="google_maps" ${currentProvider === 'google_maps' ? 'selected' : ''}>Google Maps</option>
                        <option value="spotify" ${currentProvider === 'spotify' ? 'selected' : ''}>Spotify</option>
                        <option value="vimeo" ${currentProvider === 'vimeo' ? 'selected' : ''}>Vimeo (compatibilidade)</option>
                    </select>
                </div>
                <div class="form-group"><label>Link para embed</label><input type="url" value="${escapeAttribute(block.embed_url || '')}" placeholder="${escapeAttribute(providerCfg.placeholder)}" oninput="bioUpdateBlockField('${block.id}', 'embed_url', this.value)"></div>
                <div class="bio-block-note">Suporta links de YouTube, Google Maps e Spotify. O sistema converte para formato embed automaticamente.</div>
                <div class="form-group"><label>Legenda</label><textarea oninput="bioUpdateBlockField('${block.id}', 'content', this.value)">${escapeHtml(block.content || '')}</textarea></div>
                ${bioPremiumTypoSectionBlock(block, 'title', 'Título')}
                ${bioPremiumTypoSectionBlock(block, 'caption', 'Legenda')}
            `;
        case 'divider':
            return `<div class="form-group"><label>Rótulo opcional</label><input type="text" value="${escapeAttribute(block.title || '')}" oninput="bioUpdateBlockField('${block.id}', 'title', this.value)"></div>
                ${bioPremiumTypoSectionBlock(block, 'title', 'Rótulo')}`;
        case 'form':
            return `
                <div class="form-group"><label>Título do formulário</label><input type="text" value="${escapeAttribute(block.title || '')}" oninput="bioUpdateBlockField('${block.id}', 'title', this.value)"></div>
                <div class="form-group"><label>Subtítulo</label><input type="text" value="${escapeAttribute(block.subtitle || '')}" oninput="bioUpdateBlockField('${block.id}', 'subtitle', this.value)"></div>
                <div class="form-group"><label>Texto introdutório</label><textarea oninput="bioUpdateBlockField('${block.id}', 'content', this.value)">${escapeHtml(block.content || '')}</textarea></div>
                ${bioPremiumTypoSectionBlock(block, 'title', 'Título do formulário')}
                ${bioPremiumTypoSectionBlock(block, 'subtitle', 'Subtítulo')}
                ${bioPremiumTypoSectionBlock(block, 'body', 'Texto introdutório')}
                ${bioPremiumTypoSectionBlock(block, 'submit', 'Botão enviar')}
                ${renderBlockItemsEditor(block)}
                <button type="button" class="btn-outline" style="width:100%; margin-top:10px;" onclick="bioAddBlockItem('${block.id}')"><i class="fas fa-plus"></i> Adicionar pergunta</button>
                <div class="bio-block-note" style="margin-top:10px;">Uma página pode ter apenas um bloco de formulário. Links rápidos continuam na seção abaixo.</div>
            `;
        default:
            return '';
    }
}

window.renderBioBlocksEditor = function () {
    const list = document.getElementById('bio-blocks-list');
    if (!list) return;

    normalizeCurrentBioPage();
    if (!currentBioPage.blocks.length) {
        list.innerHTML = '<div class="bio-empty-state">Adicione blocos para montar a página.</div>';
        return;
    }

    list.innerHTML = currentBioPage.blocks.map((block, index) => {
        const meta = getBlockMeta(block.type);
        return `
            <div class="bio-editor-card">
                <div class="bio-editor-card-header">
                    <div>
                        <h4>${escapeHtml(meta.title)}</h4>
                        <p>${escapeHtml(meta.description)}</p>
                    </div>
                    <div class="bio-editor-card-actions">
                        <button type="button" onclick="bioMoveBlock('${block.id}', -1)" ${index === 0 ? 'disabled' : ''}><i class="fas fa-arrow-up"></i></button>
                        <button type="button" onclick="bioMoveBlock('${block.id}', 1)" ${index === currentBioPage.blocks.length - 1 ? 'disabled' : ''}><i class="fas fa-arrow-down"></i></button>
                        <button type="button" onclick="bioRemoveBlock('${block.id}')"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
                ${renderBioBlockEditorFields(block)}
            </div>
        `;
    }).join('');
};

window.renderBioLinksEditor = function () {
    const list = document.getElementById('bio-links-list');
    if (!list) return;

    if (needsQuickActionConsolidation()) {
        consolidateQuickActionBlocksInCurrentPage();
    }

    if (!currentBioPage.links.length) {
        list.innerHTML = '<div class="bio-empty-state">Nenhuma ação rápida adicionada ainda.</div>';
        return;
    }

    const lid = id => JSON.stringify(String(id ?? ''));

    list.innerHTML = currentBioPage.links.map((link, index) => {
        const isSocial = link.link_type === 'social';
        const network = isSocial ? (SOCIAL_NETWORKS[link.social_network] || { name: 'Rede', placeholder: 'usuario', icon: 'fas fa-share-alt' }) : null;
        const valueFieldLabel = link.link_type === 'pix' ? 'Chave Pix' : (isSocial ? 'Usuário ou link' : 'URL');
        const valueField = link.link_type === 'link' ? (link.url || '') : (link.social_value || '');
        const linkKey = lid(link.id);

        return `
            <div class="bio-editor-card">
                <div class="bio-editor-card-header">
                    <div>
                        <h4>${escapeHtml(link.title || `Ação ${index + 1}`)}</h4>
                        <p>${escapeHtml(link.link_type === 'pix' ? 'Pix' : (isSocial ? network.name : 'Link rápido'))}</p>
                    </div>
                    <div class="bio-editor-card-actions">
                        <button type="button" onclick='bioMoveLegacyLinkById(${linkKey}, -1)' ${index === 0 ? 'disabled' : ''}><i class="fas fa-arrow-up"></i></button>
                        <button type="button" onclick='bioMoveLegacyLinkById(${linkKey}, 1)' ${index === currentBioPage.links.length - 1 ? 'disabled' : ''}><i class="fas fa-arrow-down"></i></button>
                        <button type="button" onclick='removeBioLink(${linkKey})'><i class="fas fa-trash"></i></button>
                    </div>
                </div>
                <div class="form-group"><label>Título</label><input type="text" value="${escapeAttribute(link.title || '')}" oninput='updateLinkData(${linkKey}, "title", this.value)'></div>
                <div class="form-group"><label>${valueFieldLabel}</label><input type="${link.link_type === 'link' ? 'url' : 'text'}" value="${escapeAttribute(valueField)}" oninput='updateLinkData(${linkKey}, "${link.link_type === 'link' ? 'url' : 'social_value'}", this.value)' placeholder="${escapeAttribute(isSocial ? network.placeholder : 'https://...')}"></div>
                <div class="control-group-grid">
                    <div class="form-group"><label>Ícone</label>${bioIconClassInputHtml(link.icon_class || '', `updateLinkData(${linkKey}, 'icon_class', this.value)`)}</div>
                    <div class="form-group"><label>Destaque</label><select onchange='updateLinkData(${linkKey}, "is_featured", this.value === "1" ? 1 : 0)'><option value="0" ${!link.is_featured ? 'selected' : ''}>Normal</option><option value="1" ${link.is_featured ? 'selected' : ''}>Destaque</option></select></div>
                </div>
                ${bioPremiumTypoSectionLink(link.id, link.title || 'Link')}
            </div>
        `;
    }).join('');
};

window.bioUpdateBlockField = function (blockId, field, value) {
    const index = getBlockIndexById(blockId);
    if (index === -1) return;
    currentBioPage.blocks[index][field] = value;
    updateBioPreview();
};

window.bioUpdateBlockElementStyle = function (blockId, slot, key, value) {
    const index = getBlockIndexById(blockId);
    if (index === -1) return;
    const block = currentBioPage.blocks[index];
    if (!block.element_styles) block.element_styles = {};
    if (!block.element_styles[slot]) block.element_styles[slot] = {};
    block.element_styles[slot][key] = value;
    updateBioPreview();
};

window.bioUpdateBlockItemElementStyle = function (blockId, itemIndex, slot, key, value) {
    const index = getBlockIndexById(blockId);
    if (index === -1) return;
    const block = currentBioPage.blocks[index];
    if (!Array.isArray(block.items) || !block.items[itemIndex]) return;
    const item = block.items[itemIndex];
    if (!item.element_styles) item.element_styles = {};
    if (!item.element_styles[slot]) item.element_styles[slot] = {};
    item.element_styles[slot][key] = value;
    updateBioPreview();
};

window.bioUpdateLinkElementStyle = function (linkId, slot, key, value) {
    const link = currentBioPage.links.find(l => String(l.id) === String(linkId));
    if (!link) return;
    if (!link.element_styles) link.element_styles = {};
    if (!link.element_styles[slot]) link.element_styles[slot] = {};
    link.element_styles[slot][key] = value;
    updateBioPreview();
};

window.bioClearElementStyleSlot = function (mode, id, slot, itemIndex) {
    if (mode === 'block') {
        const b = currentBioPage.blocks[getBlockIndexById(id)];
        if (b?.element_styles?.[slot]) delete b.element_styles[slot];
    } else if (mode === 'item') {
        const b = currentBioPage.blocks[getBlockIndexById(id)];
        const it = b?.items?.[itemIndex];
        if (it?.element_styles?.[slot]) delete it.element_styles[slot];
    } else if (mode === 'link') {
        const l = currentBioPage.links.find(x => String(x.id) === String(id));
        if (l?.element_styles?.[slot]) delete l.element_styles[slot];
    }
    renderBioBlocksEditor();
    renderBioLinksEditor();
    updateBioPreview();
};

window.bioUpdateBlockItemField = function (blockId, itemIndex, field, value) {
    const index = getBlockIndexById(blockId);
    if (index === -1) return;
    const block = currentBioPage.blocks[index];
    if (!Array.isArray(block.items) || !block.items[itemIndex]) return;
    block.items[itemIndex][field] = value;
    if (['links', 'social'].includes(block.type)) {
        currentBioPage.links = flattenActionLinksFromBlocksJs(currentBioPage.blocks);
    }
    updateBioPreview();
};

window.bioSetBlockLinkItemType = function (blockId, itemIndex, newType) {
    const index = getBlockIndexById(blockId);
    if (index === -1) return;
    const block = currentBioPage.blocks[index];
    if (block.type !== 'links' || !Array.isArray(block.items) || !block.items[itemIndex]) return;
    const item = block.items[itemIndex];
    item.link_type = newType;
    if (newType === 'social') {
        item.social_network = item.social_network || 'instagram';
        if (item.social_value === undefined || item.social_value === null) item.social_value = '';
        const meta = typeof SOCIAL_NETWORKS === 'object' && SOCIAL_NETWORKS && SOCIAL_NETWORKS[item.social_network];
        if (meta && meta.icon) item.icon_class = meta.icon;
    } else if (newType === 'link') {
        if (!item.url) item.url = 'https://';
    } else if (newType === 'pix') {
        if (item.social_value === undefined || item.social_value === null) item.social_value = '';
    }
    currentBioPage.links = flattenActionLinksFromBlocksJs(currentBioPage.blocks);
    renderBioBlocksEditor();
    renderBioLinksEditor();
    updateBioPreview();
};

window.bioUpdateBlockItemSocialNetwork = function (blockId, itemIndex, network) {
    const index = getBlockIndexById(blockId);
    if (index === -1) return;
    const block = currentBioPage.blocks[index];
    if (!Array.isArray(block.items) || !block.items[itemIndex]) return;
    const item = block.items[itemIndex];
    item.social_network = network;
    item.link_type = 'social';
    const meta = typeof SOCIAL_NETWORKS === 'object' && SOCIAL_NETWORKS && SOCIAL_NETWORKS[network];
    if (meta && meta.icon) {
        item.icon_class = meta.icon;
    }
    if (['links', 'social'].includes(block.type)) {
        currentBioPage.links = flattenActionLinksFromBlocksJs(currentBioPage.blocks);
    }
    renderBioBlocksEditor();
    renderBioLinksEditor();
    updateBioPreview();
};

window.bioMoveBlockItem = function (blockId, itemIndex, direction) {
    const index = getBlockIndexById(blockId);
    if (index === -1) return;
    const block = currentBioPage.blocks[index];
    if (!Array.isArray(block.items)) return;
    const next = itemIndex + direction;
    if (next < 0 || next >= block.items.length) return;
    const [row] = block.items.splice(itemIndex, 1);
    block.items.splice(next, 0, row);
    if (['links', 'social'].includes(block.type)) {
        currentBioPage.links = flattenActionLinksFromBlocksJs(currentBioPage.blocks);
    }
    renderBioBlocksEditor();
    renderBioLinksEditor();
    updateBioPreview();
};

window.bioAddBlockItem = function (blockId) {
    const index = getBlockIndexById(blockId);
    if (index === -1) return;
    const block = currentBioPage.blocks[index];
    if (!Array.isArray(block.items)) {
        block.items = [];
    }
    let item = { id: `item-${Math.random().toString(36).slice(2, 9)}`, title: 'Novo item', text: '' };
    if (block.type === 'gallery') item = { ...item, image_url: '', caption: '', url: '' };
    if (block.type === 'cards') item = { ...item, label: 'Saiba mais', url: 'https://', badge: '' };
    if (block.type === 'posts') item = { ...item, url: 'https://', meta: 'Novo conteúdo' };
    if (block.type === 'contact') item = { ...item, icon_class: 'fas fa-envelope' };
    if (block.type === 'form') {
        item = {
            id: `fld-${Date.now()}`,
            field_type: 'short_text',
            label: 'Nova pergunta',
            required: false,
            placeholder: '',
            help_text: '',
            max_length: 0,
            mask_pattern: '',
            default_value: '',
            options: [],
            show_if: null
        };
    }
    if (block.type === 'links') {
        item = {
            id: `lnk-${Date.now()}-${Math.random().toString(36).slice(2, 5)}`,
            title: 'Novo link',
            url: 'https://',
            icon_class: 'fas fa-link',
            is_featured: 0,
            link_type: 'link'
        };
    }
    if (block.type === 'social') {
        item = {
            id: `soc-${Date.now()}-${Math.random().toString(36).slice(2, 5)}`,
            title: 'Instagram',
            url: '',
            icon_class: 'fab fa-instagram',
            is_featured: 0,
            link_type: 'social',
            social_network: 'instagram',
            social_value: ''
        };
    }
    block.items.push(item);
    if (['links', 'social'].includes(block.type)) {
        currentBioPage.links = flattenActionLinksFromBlocksJs(currentBioPage.blocks);
    }
    renderBioBlocksEditor();
    renderBioLinksEditor();
    updateBioPreview();
};

window.bioRemoveBlockItem = function (blockId, itemIndex) {
    const index = getBlockIndexById(blockId);
    if (index === -1) return;
    const block = currentBioPage.blocks[index];
    if (!Array.isArray(block.items)) return;
    block.items.splice(itemIndex, 1);
    if (['links', 'social'].includes(block.type)) {
        currentBioPage.links = flattenActionLinksFromBlocksJs(currentBioPage.blocks);
    }
    renderBioBlocksEditor();
    renderBioLinksEditor();
    updateBioPreview();
};

window.bioAddFormOption = function (blockId, fieldIndex) {
    const index = getBlockIndexById(blockId);
    if (index === -1) return;
    const field = currentBioPage.blocks[index].items[fieldIndex];
    if (!field) return;
    if (!Array.isArray(field.options)) field.options = [];
    field.options.push({ value: `opt_${field.options.length + 1}`, label: 'Nova opção' });
    renderBioBlocksEditor();
    updateBioPreview();
};

window.bioUpdateFormOption = function (blockId, fieldIndex, optIndex, part, value) {
    const index = getBlockIndexById(blockId);
    if (index === -1) return;
    const field = currentBioPage.blocks[index].items[fieldIndex];
    if (!field || !Array.isArray(field.options) || !field.options[optIndex]) return;
    field.options[optIndex][part] = value;
    updateBioPreview();
};

window.bioRemoveFormOption = function (blockId, fieldIndex, optIndex) {
    const index = getBlockIndexById(blockId);
    if (index === -1) return;
    const field = currentBioPage.blocks[index].items[fieldIndex];
    if (!field || !Array.isArray(field.options)) return;
    field.options.splice(optIndex, 1);
    renderBioBlocksEditor();
    updateBioPreview();
};

window.bioUpdateFormShowIfKey = function (blockId, fieldIndex, key) {
    const index = getBlockIndexById(blockId);
    if (index === -1) return;
    const field = currentBioPage.blocks[index].items[fieldIndex];
    if (!field) return;
    if (!key) {
        field.show_if = null;
    } else {
        field.show_if = field.show_if || { operator: 'eq', value: '' };
        field.show_if.field_key = key;
        if (!field.show_if.operator) field.show_if.operator = 'eq';
    }
    renderBioBlocksEditor();
    updateBioPreview();
};

window.bioUpdateFormShowIfOp = function (blockId, fieldIndex, op) {
    const index = getBlockIndexById(blockId);
    if (index === -1) return;
    const field = currentBioPage.blocks[index].items[fieldIndex];
    if (!field || !field.show_if || !field.show_if.field_key) return;
    field.show_if.operator = op === 'neq' ? 'neq' : 'eq';
    updateBioPreview();
};

window.bioUpdateFormShowIfVal = function (blockId, fieldIndex, val) {
    const index = getBlockIndexById(blockId);
    if (index === -1) return;
    const field = currentBioPage.blocks[index].items[fieldIndex];
    if (!field || !field.show_if || !field.show_if.field_key) return;
    field.show_if.value = val;
    updateBioPreview();
};

window.bioMoveBlock = function (blockId, direction) {
    const index = getBlockIndexById(blockId);
    const nextIndex = index + direction;
    if (index === -1 || nextIndex < 0 || nextIndex >= currentBioPage.blocks.length) return;
    const [item] = currentBioPage.blocks.splice(index, 1);
    currentBioPage.blocks.splice(nextIndex, 0, item);
    if (currentBioPage.blocks.some(b => b.type === 'links' || b.type === 'social')) {
        currentBioPage.links = flattenActionLinksFromBlocksJs(currentBioPage.blocks);
    }
    renderBioBlocksEditor();
    renderBioLinksEditor();
    updateBioPreview();
};

window.bioRemoveBlock = function (blockId) {
    const want = String(blockId ?? '');
    currentBioPage.blocks = currentBioPage.blocks.filter(block => String(block.id ?? '') !== want);
    if (!currentBioPage.blocks.some(block => block.type === 'hero')) {
        currentBioPage.blocks.unshift(bioCreateBlock('hero'));
    }
    currentBioPage.links = flattenActionLinksFromBlocksJs(currentBioPage.blocks);
    renderBioBlocksEditor();
    renderBioLinksEditor();
    updateBioPreview();
};

window.bioMoveLegacyLink = function (index, direction) {
    const next = index + direction;
    if (next < 0 || next >= currentBioPage.links.length) return;
    const [item] = currentBioPage.links.splice(index, 1);
    currentBioPage.links.splice(next, 0, item);
    renderBioLinksEditor();
    updateBioPreview();
    renderBioBlocksEditor();
};

window.bioMoveLegacyLinkById = function (linkId, direction) {
    const index = currentBioPage.links.findIndex(l => String(l.id) === String(linkId));
    if (index === -1) return;
    window.bioMoveLegacyLink(index, direction);
};

window.addBioBlock = function (type) {
    normalizeCurrentBioPage();
    const block = bioCreateBlock(type);
    if (['links', 'social'].includes(type)) {
        const alreadyExists = currentBioPage.blocks.some(item => item.type === type);
        if (alreadyExists) {
            bioToast('Esse bloco já existe. Edite a seção atual ou use os links rápidos.', 'warning');
            return;
        }
    }
    if (type === 'form') {
        const hasForm = currentBioPage.blocks.some(item => item.type === 'form');
        if (hasForm) {
            bioToast('Já existe um formulário nesta página. Edite o bloco atual.', 'warning');
            return;
        }
    }
    currentBioPage.blocks.push(block);
    renderBioBlocksEditor();
    updateBioPreview();
    switchBioTab('builder');
};

window.addBioItemFromSelector = function (selectedType) {
    const sourceSelector = document.getElementById('bio-central-block-select');
    const type = selectedType || sourceSelector?.value || 'link';
    if (type === 'link') {
        addBioLink();
        return;
    }
    addSpecialBlock(type);
};

window.addBioLink = function () {
    normalizeCurrentBioPage();
    currentBioPage.links.push({
        id: `link-${Date.now()}-${Math.random().toString(36).slice(2, 7)}`,
        title: 'Novo link',
        url: 'https://',
        icon_class: 'fas fa-link',
        is_featured: 0,
        link_type: 'link'
    });
    renderBioLinksEditor();
    updateBioPreview();
    renderBioBlocksEditor();
    switchBioTab('builder');
};

window.addSpecialBlock = function (type) {
    normalizeCurrentBioPage();
    if (type === 'social') {
        openSocialSelector();
        return;
    }
    if (type === 'whatsapp') {
        currentBioPage.links.push({
            id: `link-${Date.now()}-wa`,
            title: 'WhatsApp',
            url: '',
            icon_class: 'fab fa-whatsapp',
            is_featured: 1,
            link_type: 'social',
            social_network: 'whatsapp',
            social_value: ''
        });
    }
    if (type === 'pix') {
        currentBioPage.links.push({
            id: `link-${Date.now()}-pix`,
            title: 'Copiar chave Pix',
            url: '',
            icon_class: 'fas fa-qrcode',
            is_featured: 0,
            link_type: 'pix',
            social_value: ''
        });
    }
    renderBioLinksEditor();
    updateBioPreview();
    renderBioBlocksEditor();
    switchBioTab('builder');
};

window.addSocialLink = function (network) {
    const meta = SOCIAL_NETWORKS[network];
    if (!meta) return;
    currentBioPage.links.push({
        id: `link-${Date.now()}-${network}`,
        title: meta.name,
        url: '',
        icon_class: meta.icon,
        is_featured: 0,
        link_type: 'social',
        social_network: network,
        social_value: ''
    });
    closeModals();
    renderBioLinksEditor();
    updateBioPreview();
    renderBioBlocksEditor();
};

window.updateLinkData = function (id, field, value) {
    const link = currentBioPage.links.find(item => String(item.id) === String(id));
    if (!link) return;
    link[field] = value;
    updateBioPreview();
};

window.removeBioLink = function (id) {
    currentBioPage.links = currentBioPage.links.filter(item => String(item.id) !== String(id));
    renderBioLinksEditor();
    updateBioPreview();
    renderBioBlocksEditor();
};

window.switchBioTab = function (tab) {
    currentBioTab = tab;
    document.querySelectorAll('#section-biopages .bio-tab-content').forEach(content => content.classList.add('hidden'));
    const content = document.getElementById(`bio-tab-${tab}`);
    if (content) content.classList.remove('hidden');
    document.querySelectorAll('#section-biopages [data-bio-tab]').forEach(button => {
        button.classList.toggle('active', button.dataset.bioTab === tab);
    });
    document.querySelectorAll('#section-biopages [data-bio-flow]').forEach(item => {
        item.classList.toggle('active', item.dataset.bioFlow === tab);
    });
    if (tab === 'formresponses' && typeof window.loadBioFormAnalytics === 'function') {
        window.loadBioFormAnalytics();
    }
};

window.switchBioTemplate = function (id) {
    if (typeof id === 'string' && id.startsWith('goal-')) {
        applyGoalTemplate(id.replace('goal-', ''));
        return;
    }

    const previousTemplateId = currentBioPage.template_id;
    currentBioPage.template_id = parseInt(id, 10) || 1;
    currentBioPage.selected_goal_key = '';
    applyTemplateStarterToCurrentPage(currentBioPage.template_id, false);
    if (previousTemplateId === 10 && currentBioPage.template_id !== 10) {
        currentBioPage.blocks = currentBioPage.blocks.filter(b => b.type !== 'form');
    }
    if (currentBioPage.template_id === 10 && !currentBioPage.blocks.some(b => b.type === 'form')) {
        currentBioPage.blocks.push(bioCreateBlock('form'));
    }
    renderGoalShortcuts();
    renderBioTemplatesGallery();
    const templateMeta = getBioTemplateMetaById(currentBioPage.template_id);
    ensureBioPreviewTemplateCss(templateMeta);
    renderBioBlocksEditor();
    renderBioLinksEditor();
    updateBioPreview();
};

window.applyGoalTemplate = function (key) {
    const goal = GOAL_TEMPLATES[key];
    if (!goal) return;
    const previousStatus = currentBioPage.page_status;

    currentBioPage.selected_goal_key = key;
    currentBioPage.page_type = 'bio';
    currentBioPage.page_status = previousStatus === 'published' ? 'published' : 'draft';
    currentBioPage.template_id = goal.recommended_template_id || goal.theme?.template_id || 1;
    currentBioPage.blocks = cloneDeep(goal.blocks || []);
    currentBioPage.links = cloneDeep(goal.links || flattenActionLinksFromBlocksJs(currentBioPage.blocks));

    const pageType = document.getElementById('bio-page-type');
    const pageStatus = document.getElementById('bio-page-status');
    if (pageType) pageType.value = currentBioPage.page_type;
    if (pageStatus) pageStatus.value = currentBioPage.page_status;
    syncBioStatusSwitchUI(currentBioPage.page_status);

    if (goal.theme) {
        if (document.getElementById('bio-theme-bg')) document.getElementById('bio-theme-bg').value = goal.theme.bg;
        if (document.getElementById('bio-theme-btn')) document.getElementById('bio-theme-btn').value = goal.theme.btn;
        if (document.getElementById('bio-theme-text')) document.getElementById('bio-theme-text').value = goal.theme.text;
        if (document.getElementById('bio-theme-style')) document.getElementById('bio-theme-style').value = goal.theme.style;
    }

    renderGoalShortcuts();
    renderBioTemplatesGallery();
    renderBioBlocksEditor();
    renderBioLinksEditor();
    const templateMeta = getBioTemplateMetaById(currentBioPage.template_id);
    ensureBioPreviewTemplateCss(templateMeta);
    updateBioPreview();
    bioToast(`Preset ${goal.name.replace('Modelo: ', '')} aplicado.`, 'success');
};

window.setBioPreviewMode = function (mode) {
    bioPreviewMode = mode;
    const mobileBtn = document.getElementById('bioPreviewMobileBtn');
    const desktopBtn = document.getElementById('bioPreviewDesktopBtn');
    mobileBtn?.classList.toggle('active', mode === 'mobile');
    desktopBtn?.classList.toggle('active', mode === 'desktop');
    const device = document.getElementById('bioPreviewDevice');
    if (device) {
        device.classList.toggle('preview-desktop', mode === 'desktop');
    }
    updateBioPreview();
};

window.openCurrentBioPublicPage = function () {
    normalizeCurrentBioPage();
    const baseOrigin = (window.location.origin || '').replace(/\/$/, '');
    const currentBasePath = (window.location.pathname || '/').replace(/\/[^/]*$/, '/');
    const schema = buildBioRuntimeSchema();
    const previewWindow = window.open('', '_blank');
    if (!previewWindow) {
        bioToast('Não foi possível abrir o preview em nova aba.', 'error');
        return;
    }

    const pageMeta = getBioPageMeta();
    const templateMeta = getBioTemplateMetaById(currentBioPage.template_id) || { folder: 'classic', slug: 'classic' };
    const tplBodySlug = String(templateMeta.slug || 'classic').replace(/[^a-zA-Z0-9_-]/g, '') || 'classic';
    const shellInner = renderBioPageShellHtml(schema, true, { includeBackdrop: false });
    const previewTitle = escapeHtml(pageMeta.title || 'Preview da Bio Page');
    const statusLabel = BIO_STATUS_LABELS[currentBioPage.page_status] || 'Rascunho';
    const slugLabel = pageMeta.slug ? `${domain}/bio/${pageMeta.slug}` : `${domain}/bio/seu-slug`;
    const defaultCssUrl = `${baseOrigin}${currentBasePath}bio_builder_shared.css?v=${Date.now()}`;
    const templateCssUrl = `${baseOrigin}${currentBasePath}templates/bio/${encodeURIComponent(templateMeta.folder || 'classic')}/style.css?v=${Date.now()}`;

    const themeCfg = getThemeConfig();
    const tvAttrs = `--bg:${themeCfg.bg};--primary:${themeCfg.btn};--text:${themeCfg.text};`;

    previewWindow.document.open();
    previewWindow.document.write(`<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>${previewTitle}</title>
    <link rel="stylesheet" href="${defaultCssUrl}">
    <link rel="stylesheet" href="${templateCssUrl}">
    <style>
        body { margin: 0; min-height: 100vh; background: #0a0f18; color: #e2e8f0; font-family: Inter, Arial, sans-serif; }
        .bio-preview-standalone-header { display: flex; justify-content: space-between; gap: 12px; align-items: center; padding: 14px 18px; border-bottom: 1px solid rgba(255, 255, 255, 0.1); background: rgba(2, 6, 23, 0.9); position: sticky; top: 0; z-index: 2; }
        .bio-preview-standalone-meta { display: flex; gap: 8px; align-items: center; color: rgba(255, 255, 255, 0.8); font-size: 12px; }
        .bio-preview-standalone-status { padding: 4px 10px; border-radius: 999px; border: 1px solid rgba(255, 255, 255, 0.25); font-weight: 700; text-transform: uppercase; font-size: 10px; letter-spacing: 0.06em; }
        .bio-preview-standalone-body { max-width: 980px; margin: 0 auto; padding: 28px 16px 40px; box-sizing: border-box; position: relative; z-index: 1; }
    </style>
</head>
<body class="tpl-${tplBodySlug}" style="${tvAttrs}">
    <div class="bio-public-bg-fixed" aria-hidden="true"></div>
    <div class="bio-preview-standalone-header">
        <strong>Preview do editor (estado atual)</strong>
        <div class="bio-preview-standalone-meta">
            <span>${escapeHtml(slugLabel)}</span>
            <span class="bio-preview-standalone-status">${escapeHtml(statusLabel)}</span>
        </div>
    </div>
    <div class="bio-preview-standalone-body">${shellInner}</div>
</body>
</html>`);
    previewWindow.document.close();
};

window.updateBioPreview = function () {
    normalizeCurrentBioPage();
    syncBioStatusSwitchUI(currentBioPage.page_status);
    const schema = buildBioRuntimeSchema();
    const preview = document.getElementById('bio-live-preview');
    const statusBadge = document.getElementById('bio-preview-status-badge');
    const urlLabel = document.getElementById('bio-preview-url');
    const device = document.getElementById('bioPreviewDevice');

    const templateMeta = getBioTemplateMetaById(currentBioPage.template_id) || { slug: 'classic' };
    if (device) {
        device.className = `mobile-mockup tpl-${templateMeta.slug || 'classic'} ${bioPreviewMode === 'desktop' ? 'preview-desktop' : ''}`.trim();
    }
    ensureBioPreviewTemplateCss(templateMeta);

    if (preview) {
        preview.innerHTML = renderBioPageShellHtml(schema, true);
    }

    const slug = (document.getElementById('bio-slug')?.value || '').trim();
    if (urlLabel) {
        urlLabel.textContent = slug ? `${domain}/bio/${slug}` : `${domain}/bio/seu-slug`;
    }
    if (statusBadge) {
        statusBadge.textContent = BIO_STATUS_LABELS[currentBioPage.page_status] || 'Rascunho';
    }

    renderGoalShortcuts();
};
window.updateBioPreviewEnhanced = window.updateBioPreview;

window.saveBioPage = async function () {
    if (!isLoggedIn) return openModal('modalLogin');

    normalizeCurrentBioPage();
    const slugInput = document.getElementById('bio-slug');
    let slug = sanitizeBioSlug(slugInput?.value || '');
    if (!slug) {
        slug = generateBioRandomSlug(6);
        if (slugInput) slugInput.value = slug;
        bioToast('Slug não informado. Geramos um slug automático para esta Bio.', 'warning');
    } else if (slugInput && slugInput.value !== slug) {
        slugInput.value = slug;
    }

    const button = document.getElementById('btnSaveBio');
    const dockButton = document.querySelector('.bio-save-dock-btn');
    const original = button?.innerHTML || '';
    const dockOriginal = dockButton?.innerHTML || '';
    if (button) {
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando...';
    }
    if (dockButton) {
        dockButton.disabled = true;
        dockButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando...';
    }

    const schema = buildBioRuntimeSchema();
    const fd = new FormData();
    if (currentBioPage.id) fd.append('id', currentBioPage.id);
    fd.append('action', 'save');
    fd.append('slug', slug);
    fd.append('title', document.getElementById('bio-title')?.value || '');
    fd.append('description', document.getElementById('bio-description')?.value || '');
    fd.append('avatar_url', document.getElementById('bio-avatar')?.value || '');
    fd.append('theme_bg_color', document.getElementById('bio-theme-bg')?.value || '#0f172a');
    fd.append('theme_btn_color', document.getElementById('bio-theme-btn')?.value || '#1A8CFF');
    fd.append('theme_text_color', document.getElementById('bio-theme-text')?.value || '#ffffff');
    fd.append('theme_btn_style', document.getElementById('bio-theme-style')?.value || 'solid');
    fd.append('template_id', currentBioPage.template_id);
    fd.append('page_type', currentBioPage.page_type);
    fd.append('page_status', currentBioPage.page_status);
    fd.append('selected_goal_key', currentBioPage.selected_goal_key || '');
    fd.append('links', JSON.stringify(currentBioPage.links));
    fd.append('builder_schema_json', JSON.stringify(schema));

    try {
        const response = await fetch('manage_bio.php', { method: 'POST', body: fd, credentials: 'include' });
        const data = await response.json();
        if (!data.success) {
            bioToast(data.message || 'Erro ao salvar.', 'error');
            return;
        }
        currentBioPage.id = data.id;
        if (data.slug && slugInput) {
            slugInput.value = data.slug;
        }
        document.getElementById('btnDuplicateBio')?.classList.remove('hidden');
        await loadBioPages();
        updateBioPreview();
        bioToast('Página salva com sucesso.', 'success');
    } catch (e) {
        console.error('Erro ao salvar bio page', e);
        bioToast('Erro de conexão ao salvar a página.', 'error');
    } finally {
        if (button) {
            button.disabled = false;
            button.innerHTML = original;
        }
        if (dockButton) {
            dockButton.disabled = false;
            dockButton.innerHTML = dockOriginal;
        }
    }
};

window.loadBioTemplates = async function () {
    try {
        const response = await fetch('api_bio_templates.php');
        const data = await response.json();
        if (!data.success) {
            throw new Error('Falha ao carregar templates.');
        }
        bioTemplatesData = (data.templates || []).map(template => ({
            ...template,
            ...(BIO_TEMPLATE_ENRICHMENTS[template.id] || {}),
            description: template.description || BIO_TEMPLATE_ENRICHMENTS[template.id]?.description || 'Template visual do builder.',
            ideal_for: template.ideal_for || BIO_TEMPLATE_ENRICHMENTS[template.id]?.ideal_for || 'Uso geral',
            page_types: template.page_types || BIO_TEMPLATE_ENRICHMENTS[template.id]?.page_types || ['bio']
        }));
        renderBioTemplateFilters();
        renderBioTemplatesGallery();
        renderGoalShortcuts();
        const templateMeta = getBioTemplateMetaById(currentBioPage.template_id);
        ensureBioPreviewTemplateCss(templateMeta);
    } catch (e) {
        console.error('Erro ao carregar templates', e);
        const list = document.getElementById('bio-templates-list');
        if (list) list.innerHTML = '<div class="bio-empty-state">Falha ao carregar os templates.</div>';
    }
};

function renderBioPageCards(pages) {
    const container = document.getElementById('bioPagesList');
    if (!container) return;

    if (!pages.length) {
        container.innerHTML = '<div class="bio-empty-state">Você ainda não criou nenhuma página nessa área.</div>';
        return;
    }

    container.innerHTML = pages.map(page => {
        const templateMeta = getBioTemplateMetaById(page.template_id) || { name: 'Template', category: 'Visual' };
        const pageType = page.page_type || 'bio';
        const status = page.page_status || 'published';
        const publicUrl = `${protocol}//${domain}/bio.php?slug=${page.slug}`;
        const hasForm = !!page.has_form;
        return `
            <div class="link-card bio-page-card" data-page-id="${page.id}">
                <div class="link-card-header">
                    <div class="link-card-icon" style="background:${escapeAttribute(page.theme_bg_color || '#0f172a')}; color:${escapeAttribute(page.theme_btn_color || '#1A8CFF')}; border-radius:16px; width:46px; height:46px; display:flex; align-items:center; justify-content:center;">
                        <i class="fas fa-layer-group"></i>
                    </div>
                    <div class="link-card-title-group">
                        <span class="link-card-title">${escapeHtml(page.title || 'Sem título')}</span>
                        <span class="link-card-date">/${escapeHtml(page.slug || '')}</span>
                    </div>
                </div>
                <div class="link-card-body" style="display:grid; gap:12px;">
                    <a href="${escapeAttribute(publicUrl)}" target="_blank" class="link-card-short">${domain}/bio/${escapeHtml(page.slug || '')}</a>
                    <div style="display:flex; flex-wrap:wrap; gap:8px;">
                        <span class="bio-meta-pill">${escapeHtml(BIO_PAGE_TYPE_LABELS[pageType] || 'Bio')}</span>
                        <span class="bio-meta-pill">${escapeHtml(BIO_STATUS_LABELS[status] || 'Publicado')}</span>
                        <span class="bio-meta-pill">${escapeHtml(templateMeta.name || 'Template')}</span>
                    </div>
                </div>
                <div class="link-card-footer">
                    <div class="link-card-actions" style="width:100%; justify-content:space-between; gap:8px;">
                        <button type="button" onclick="toggleBioPageListAnalytics(${page.id}, ${hasForm})" class="btn-action" title="Analytics da Bio Page"><i class="fas fa-chart-pie"></i></button>
                        <button onclick="window.open('${escapeAttribute(publicUrl)}', '_blank')" class="btn-action" title="Visualizar"><i class="fas fa-eye"></i></button>
                        <button onclick="editBioPage(${page.id})" class="btn-action btn-edit" title="Editar"><i class="fas fa-pen"></i></button>
                        <button onclick="duplicateBioPage(${page.id})" class="btn-action" title="Duplicar"><i class="fas fa-copy"></i></button>
                        <button onclick="deleteBioPage(${page.id})" class="btn-action btn-delete" title="Excluir"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
                <div class="bio-page-card-analytics" id="bio-page-analytics-${page.id}" aria-live="polite"></div>
            </div>
        `;
    }).join('');
};

window.filterBioPagesList = function () {
    const search = (document.getElementById('bio-pages-search')?.value || '').trim().toLowerCase();
    const filter = document.getElementById('bio-pages-filter')?.value || 'all';
    const filtered = bioPagesCache.filter(page => {
        const haystack = [page.title, page.slug, page.page_type, page.page_status].join(' ').toLowerCase();
        const matchesSearch = !search || haystack.includes(search);
        const matchesFilter = filter === 'all' || (page.page_type || 'bio') === filter;
        return matchesSearch && matchesFilter;
    });
    renderBioPageCards(filtered);
};

window.loadBioPages = async function () {
    if (!isLoggedIn) return;
    try {
        const response = await fetch('manage_bio.php?action=list', { credentials: 'include' });
        const data = await response.json();
        bioPagesCache = data.success ? (data.pages || []) : [];
        filterBioPagesList();
    } catch (e) {
        console.error('Erro ao carregar páginas de bio', e);
    }
};

window.editBioPage = async function (id) {
    try {
        const response = await fetch(`manage_bio.php?action=get&id=${id}`, { credentials: 'include' });
        const data = await response.json();
        if (!data.success) {
            bioToast(data.message || 'Não foi possível carregar a página.', 'error');
            return;
        }
        const page = data.page;
        currentBioPage = createEmptyBioPageState();
        currentBioPage.id = page.id;
        currentBioPage.template_id = page.template_id || 1;
        currentBioPage.page_type = 'bio';
        currentBioPage.page_status = page.page_status || 'published';
        currentBioPage.selected_goal_key = page.selected_goal_key || (page.resolved_schema?.selected_goal_key || '');
        currentBioPage.links = Array.isArray(page.links) ? page.links.map(item => ({ ...item })) : [];
        currentBioPage.blocks = Array.isArray(page.resolved_schema?.blocks) ? cloneDeep(page.resolved_schema.blocks) : [];

        if (document.getElementById('bio-slug')) document.getElementById('bio-slug').value = page.slug || '';
        if (document.getElementById('bio-title')) document.getElementById('bio-title').value = page.title || '';
        if (document.getElementById('bio-description')) document.getElementById('bio-description').value = page.description || '';
        if (document.getElementById('bio-avatar')) document.getElementById('bio-avatar').value = page.avatar_url || '';
        const heroBlock = Array.isArray(currentBioPage.blocks) ? currentBioPage.blocks.find(block => block.type === 'hero') : null;
        if (document.getElementById('bio-hero-cover')) document.getElementById('bio-hero-cover').value = heroBlock?.cover_image_url || '';
        if (document.getElementById('bio-avatar-shape')) document.getElementById('bio-avatar-shape').value = normalizeAvatarShape(heroBlock?.avatar_shape || 'rounded');
        if (document.getElementById('bio-theme-bg')) document.getElementById('bio-theme-bg').value = page.theme_bg_color || '#0f172a';
        if (document.getElementById('bio-theme-btn')) document.getElementById('bio-theme-btn').value = page.theme_btn_color || '#1A8CFF';
        if (document.getElementById('bio-theme-text')) document.getElementById('bio-theme-text').value = page.theme_text_color || '#ffffff';
        if (document.getElementById('bio-theme-style')) document.getElementById('bio-theme-style').value = page.theme_btn_style || 'solid';
        if (document.getElementById('bio-page-type')) document.getElementById('bio-page-type').value = 'bio';
        if (document.getElementById('bio-page-status')) document.getElementById('bio-page-status').value = currentBioPage.page_status;
        syncBioStatusSwitchUI(currentBioPage.page_status);

        document.getElementById('btnDuplicateBio')?.classList.remove('hidden');
        renderGoalShortcuts();
        renderBioTemplatesGallery();
        renderBioBlocksEditor();
        renderBioLinksEditor();
        updateBioPreview();
        switchBioTab('profile');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    } catch (e) {
        console.error('Erro ao buscar bio page', e);
        bioToast('Erro ao carregar página para edição.', 'error');
    }
};

window.duplicateCurrentBioPage = function () {
    if (!currentBioPage.id) {
        bioToast('Salve a página antes de duplicar.', 'warning');
        return;
    }
    duplicateBioPage(currentBioPage.id);
};

window.duplicateBioPage = async function (id) {
    const fd = new FormData();
    fd.append('action', 'duplicate');
    fd.append('id', id);
    try {
        const response = await fetch('manage_bio.php', { method: 'POST', body: fd, credentials: 'include' });
        const data = await response.json();
        if (!data.success) {
            bioToast(data.message || 'Não foi possível duplicar.', 'error');
            return;
        }
        await loadBioPages();
        bioToast('Página duplicada com sucesso.', 'success');
    } catch (e) {
        console.error('Erro ao duplicar bio page', e);
        bioToast('Erro ao duplicar página.', 'error');
    }
};

window.deleteBioPage = async function (id) {
    if (!confirm('Excluir esta página permanentemente?')) return;
    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('id', id);
    try {
        const response = await fetch('manage_bio.php', { method: 'POST', body: fd, credentials: 'include' });
        const data = await response.json();
        if (!data.success) {
            bioToast(data.message || 'Não foi possível excluir.', 'error');
            return;
        }
        if (String(currentBioPage.id) === String(id)) {
            currentBioPage = createEmptyBioPageState();
            document.getElementById('btnDuplicateBio')?.classList.add('hidden');
        }
        await loadBioPages();
        updateBioPreview();
        bioToast('Página excluída com sucesso.', 'success');
    } catch (e) {
        console.error('Erro ao excluir bio page', e);
        bioToast('Erro ao excluir página.', 'error');
    }
};

let bioFormChartHandles = {};

function destroyBioFormChartsInMount(mount) {
    if (!mount) return;
    mount.querySelectorAll('canvas[id^="bio-form-chart-"]').forEach(canvas => {
        const id = canvas.id;
        const ch = bioFormChartHandles[id];
        if (ch) {
            try {
                ch.destroy();
            } catch (e) { /* ignore */ }
            delete bioFormChartHandles[id];
        }
    });
}

function destroyBioFormCharts() {
    Object.keys(bioFormChartHandles).forEach(id => {
        const ch = bioFormChartHandles[id];
        if (ch) {
            try {
                ch.destroy();
            } catch (e) { /* ignore */ }
        }
        delete bioFormChartHandles[id];
    });
}

function bioAnalyticsPalette(i) {
    const p = ['#1A8CFF', '#9932CC', '#f472b6', '#fbbf24', '#B366FF', '#34d399', '#fb7185', '#94a3b8'];
    return p[i % p.length];
}

function bioAnalyticsFormatLocalYmd(d) {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
}

function bioAnalyticsExportUrl(pageId, exportAction, state) {
    const p = new URLSearchParams({ action: exportAction, page_id: String(pageId) });
    if (state.dateFrom) p.append('date_from', state.dateFrom);
    if (state.dateTo) p.append('date_to', state.dateTo);
    return `manage_bio_form.php?${p.toString()}`;
}

async function bioAnalyticsReloadMount(mount, pageId, patch) {
    if (!mount || !pageId) return;
    mount.bioAnalyticsPageId = pageId;
    const prev = mount.bioAnalyticsState || {};
    const state = { ...prev, ...patch };
    state.recentPerPage = Math.min(50, Math.max(5, parseInt(String(state.recentPerPage || 10), 10) || 10));
    state.recentPage = Math.max(1, parseInt(String(state.recentPage || 1), 10) || 1);
    mount.bioAnalyticsState = state;
    mount.innerHTML = '<div class="bio-empty-state">Carregando análise…</div>';
    destroyBioFormChartsInMount(mount);
    try {
        const qs = new URLSearchParams({ action: 'analytics', page_id: String(pageId) });
        if (state.dateFrom) qs.append('date_from', state.dateFrom);
        if (state.dateTo) qs.append('date_to', state.dateTo);
        if (state.recentPage > 1) qs.append('recent_page', String(state.recentPage));
        qs.append('recent_per_page', String(state.recentPerPage));
        const res = await fetch(`manage_bio_form.php?${qs.toString()}`, { credentials: 'include' });
        const data = await res.json();
        if (!data.success) {
            mount.innerHTML = `<div class="bio-empty-state">${escapeHtml(data.message || 'Não foi possível carregar.')}</div>`;
            return;
        }
        mount.bioAnalyticsState = {
            dateFrom: data.date_from || '',
            dateTo: data.date_to || ''
        };
        if (data.has_form) {
            mount.bioAnalyticsState.recentPage = data.recent_page || 1;
            mount.bioAnalyticsState.recentPerPage = data.recent_per_page || 10;
        } else {
            const basic = data.basic_page_analytics || {};
            mount.bioAnalyticsState.recentPage = basic.recent_page || 1;
            mount.bioAnalyticsState.recentPerPage = basic.recent_per_page || 10;
        }
        const { html, jobs } = buildBioAnalyticsHtml(data, pageId, mount.bioAnalyticsState);
        mount.innerHTML = html;
        runBioAnalyticsChartJobs(jobs);
        bindBioAnalyticsMount(mount);
        hydrateBioAnalyticsTextBlocks(mount);
    } catch (e) {
        console.error(e);
        mount.innerHTML = '<div class="bio-empty-state">Erro ao carregar análise.</div>';
    }
}

function bindBioAnalyticsMount(mount) {
    if (!mount || mount._bioAnalyticsEventsBound) return;
    mount._bioAnalyticsEventsBound = true;
    mount.addEventListener('click', ev => {
        const btn = ev.target.closest('[data-bio-analytics-action]');
        if (!btn || !mount.contains(btn)) return;
        const act = btn.getAttribute('data-bio-analytics-action');
        const st = mount.bioAnalyticsState || {};
        const pid = mount.bioAnalyticsPageId;
        if (!pid) return;
        const perNow = readPerFromMount(mount);

        if (act === 'filter-apply') {
            const df = mount.querySelector('.bio-analytics-in-date-from')?.value?.trim() || '';
            const dt = mount.querySelector('.bio-analytics-in-date-to')?.value?.trim() || '';
            bioAnalyticsReloadMount(mount, pid, { dateFrom: df, dateTo: dt, recentPage: 1, recentPerPage: perNow });
            return;
        }
        if (act === 'filter-clear') {
            mount.querySelectorAll('.bio-analytics-in-date-from, .bio-analytics-in-date-to').forEach(el => { el.value = ''; });
            bioAnalyticsReloadMount(mount, pid, { dateFrom: '', dateTo: '', recentPage: 1, recentPerPage: perNow });
            return;
        }
        if (act === 'preset') {
            const days = parseInt(btn.getAttribute('data-preset-days') || '30', 10) || 30;
            const to = new Date();
            const from = new Date();
            from.setDate(from.getDate() - days);
            const df = bioAnalyticsFormatLocalYmd(from);
            const dte = bioAnalyticsFormatLocalYmd(to);
            const iFrom = mount.querySelector('.bio-analytics-in-date-from');
            const iTo = mount.querySelector('.bio-analytics-in-date-to');
            if (iFrom) iFrom.value = df;
            if (iTo) iTo.value = dte;
            bioAnalyticsReloadMount(mount, pid, { dateFrom: df, dateTo: dte, recentPage: 1, recentPerPage: perNow });
            return;
        }
        if (act === 'recent-prev') {
            const p = Math.max(1, (st.recentPage || 1) - 1);
            bioAnalyticsReloadMount(mount, pid, { recentPage: p, recentPerPage: perNow });
            return;
        }
        if (act === 'recent-next') {
            bioAnalyticsReloadMount(mount, pid, { recentPage: (st.recentPage || 1) + 1, recentPerPage: perNow });
            return;
        }
        if (act === 'text-prev' || act === 'text-next') {
            const block = btn.closest('.bio-analytics-text-block');
            if (!block) return;
            const cur = block.bioTextPage || 1;
            const nextP = act === 'text-next' ? cur + 1 : cur - 1;
            renderBioTextSamplesForBlock(block, pid, mount.bioAnalyticsState || {}, Math.max(1, nextP));
        }
    });
    mount.addEventListener('change', ev => {
        const sel = ev.target.closest('.bio-analytics-recent-per-page');
        if (!sel || !mount.contains(sel)) return;
        const pid = mount.bioAnalyticsPageId;
        if (!pid) return;
        bioAnalyticsReloadMount(mount, pid, { recentPage: 1, recentPerPage: readPerFromMount(mount) });
    });
}

function readPerFromMount(mount) {
    const v = mount.querySelector('.bio-analytics-recent-per-page')?.value;
    return Math.min(50, Math.max(5, parseInt(v || '10', 10) || 10));
}

async function renderBioTextSamplesForBlock(block, pageId, state, pageNum) {
    const fk = block.getAttribute('data-field-key');
    if (!fk) return;
    const tbody = block.querySelector('.bio-analytics-text-tbody');
    const meta = block.querySelector('.bio-analytics-text-meta');
    const pager = block.querySelector('.bio-analytics-text-pager');
    const loading = block.querySelector('.bio-analytics-text-load-msg');
    if (tbody) tbody.innerHTML = '';
    if (loading) loading.style.display = 'block';
    block.bioTextPage = Math.max(1, pageNum);
    try {
        const p = new URLSearchParams({
            action: 'text_samples',
            page_id: String(pageId),
            field_key: fk,
            page: String(block.bioTextPage),
            per_page: '20'
        });
        if (state.dateFrom) p.append('date_from', state.dateFrom);
        if (state.dateTo) p.append('date_to', state.dateTo);
        const res = await fetch(`manage_bio_form.php?${p.toString()}`, { credentials: 'include' });
        const data = await res.json();
        if (loading) loading.style.display = 'none';
        if (!data.success || !tbody) {
            if (meta) meta.textContent = data.message || 'Não foi possível carregar.';
            return;
        }
        const samples = Array.isArray(data.samples) ? data.samples : [];
        const total = data.total ?? 0;
        const per = data.per_page ?? 20;
        const pg = data.page ?? 1;
        const totalPages = Math.max(1, Math.ceil(total / per));
        block.bioTextPage = pg;
        if (meta) meta.textContent = `Página ${pg} de ${totalPages} — ${total} resposta(s)`;
        samples.forEach(t => {
            tbody.innerHTML += `<tr><td>${escapeHtml(String(t))}</td></tr>`;
        });
        if (pager) {
            pager.innerHTML = `
                <button type="button" class="btn-outline" data-bio-analytics-action="text-prev" ${pg <= 1 ? ' disabled' : ''}>Anterior</button>
                <button type="button" class="btn-outline" data-bio-analytics-action="text-next" ${pg >= totalPages ? ' disabled' : ''}>Próxima</button>
            `;
        }
    } catch (e) {
        console.error(e);
        if (loading) loading.style.display = 'none';
        if (meta) meta.textContent = 'Erro ao carregar respostas.';
    }
}

function hydrateBioAnalyticsTextBlocks(mount) {
    const pid = mount.bioAnalyticsPageId;
    if (!pid) return;
    mount.querySelectorAll('.bio-analytics-text-block').forEach(block => {
        block.bioTextPage = 1;
        renderBioTextSamplesForBlock(block, pid, mount.bioAnalyticsState || {}, 1);
    });
}

function buildBioPageViewsAnalyticsHtml(data, pageId, state) {
    const jobs = [];
    const basic = data.basic_page_analytics || {};
    const st = state || {};
    const df = st.dateFrom || '';
    const dt = st.dateTo || '';
    const totalViews = basic.total_views ?? 0;
    const timeline = Array.isArray(basic.timeline) ? basic.timeline : [];
    const recent = Array.isArray(basic.recent_views) ? basic.recent_views : [];
    const rp = basic.recent_per_page || st.recentPerPage || 10;
    const rpage = basic.recent_page || st.recentPage || 1;
    const rtotal = basic.recent_total ?? 0;
    const rTotalPages = Math.max(1, Math.ceil(rtotal / rp));
    let html = '';

    html += '<div class="bio-analytics-toolbar card bio-panel-card">';
    html += '<div class="bio-analytics-toolbar-row">';
    html += `<label class="bio-analytics-date-label">De <input type="date" class="bio-analytics-in-date-from" value="${escapeAttribute(df)}"></label>`;
    html += `<label class="bio-analytics-date-label">Até <input type="date" class="bio-analytics-in-date-to" value="${escapeAttribute(dt)}"></label>`;
    html += '<button type="button" class="btn-outline" data-bio-analytics-action="filter-apply">Aplicar filtro</button>';
    html += '<button type="button" class="btn-outline" data-bio-analytics-action="filter-clear">Limpar datas</button>';
    html += '</div>';
    html += '<div class="bio-analytics-toolbar-row bio-analytics-presets">';
    html += '<span class="bio-analytics-presets-label">Atalhos:</span>';
    [7, 30, 90].forEach(d => {
        html += `<button type="button" class="btn-outline bio-analytics-preset-btn" data-bio-analytics-action="preset" data-preset-days="${d}">${d} dias</button>`;
    });
    html += '</div>';
    html += '<p class="bio-analytics-filter-hint">Visão de acessos da Bio Page. Use os filtros para analisar períodos específicos.</p>';
    html += '</div>';

    html += '<div class="bio-form-analytics-summary card bio-panel-card bio-analytics-panel">';
    html += '<div class="bio-analytics-kpi-row">';
    const kpiSub = df || dt ? 'No período filtrado' : 'Total geral';
    html += `<div class="bio-analytics-kpi"><span class="bio-analytics-kpi-value">${totalViews}</span><span class="bio-analytics-kpi-label">Acessos <small class="bio-analytics-kpi-sub">${escapeHtml(kpiSub)}</small></span></div>`;
    html += `<div class="bio-analytics-kpi"><span class="bio-analytics-kpi-value">${timeline.length}</span><span class="bio-analytics-kpi-label">Dias com tráfego</span></div>`;
    html += '</div>';
    if (timeline.length) {
        const cid = `bio-form-chart-${pageId}-views-timeline`;
        html += '<div class="bio-analytics-chart-wrap bio-analytics-chart-wrap--timeline"><canvas id="' + cid + '"></canvas></div>';
        jobs.push({
            id: cid,
            kind: 'line',
            labels: timeline.map(t => t.date),
            values: timeline.map(t => t.count),
            datasetLabel: 'Acessos'
        });
    } else {
        html += '<div class="bio-empty-state">Sem acessos no período selecionado.</div>';
    }
    if (basic.message) {
        html += `<p class="bio-analytics-hint">${escapeHtml(String(basic.message))}</p>`;
    }
    html += '</div>';

    html += '<div class="bio-analytics-recent card bio-panel-card">';
    html += '<div class="bio-analytics-recent-head">';
    html += '<h4 class="bio-analytics-section-title">Últimos acessos</h4>';
    html += '<label class="bio-analytics-per-label">Por página <select class="bio-analytics-recent-per-page">';
    [5, 10, 25, 50].forEach(n => {
        html += `<option value="${n}"${n === rp ? ' selected' : ''}>${n}</option>`;
    });
    html += '</select></label></div>';
    if (recent.length) {
        html += '<div class="table-wrap bio-analytics-table-wrap"><table class="bio-analytics-table"><thead><tr><th>ID</th><th>Data</th></tr></thead><tbody>';
        recent.forEach(r => {
            html += `<tr><td>#${r.id}</td><td>${escapeHtml(String(r.viewed_at || ''))}</td></tr>`;
        });
        html += '</tbody></table></div>';
    } else {
        html += '<div class="bio-empty-state">Nenhum acesso neste período.</div>';
    }
    html += '<div class="bio-analytics-recent-pager">';
    html += `<button type="button" class="btn-outline" data-bio-analytics-action="recent-prev"${rpage <= 1 ? ' disabled' : ''}>Anterior</button>`;
    html += `<span class="bio-analytics-pager-info">Página <strong>${rpage}</strong> de <strong>${rTotalPages}</strong> <span class="bio-analytics-pager-sub">(${rtotal} acesso(s))</span></span>`;
    html += `<button type="button" class="btn-outline" data-bio-analytics-action="recent-next"${rpage >= rTotalPages ? ' disabled' : ''}>Próxima</button>`;
    html += '</div></div>';

    return { html, jobs };
}

function buildBioAnalyticsHtml(data, pageId, state) {
    if (!data.has_form) {
        return buildBioPageViewsAnalyticsHtml(data, pageId, state);
    }
    const jobs = [];
    const total = data.total_submissions ?? 0;
    const fields = data.fields || [];
    const st = state || {};
    const df = st.dateFrom || '';
    const dt = st.dateTo || '';
    let html = '';

    html += '<div class="bio-analytics-toolbar card bio-panel-card">';
    html += '<div class="bio-analytics-toolbar-row">';
    html += `<label class="bio-analytics-date-label">De <input type="date" class="bio-analytics-in-date-from" value="${escapeAttribute(df)}"></label>`;
    html += `<label class="bio-analytics-date-label">Até <input type="date" class="bio-analytics-in-date-to" value="${escapeAttribute(dt)}"></label>`;
    html += '<button type="button" class="btn-outline" data-bio-analytics-action="filter-apply">Aplicar filtro</button>';
    html += '<button type="button" class="btn-outline" data-bio-analytics-action="filter-clear">Limpar datas</button>';
    html += '</div>';
    html += '<div class="bio-analytics-toolbar-row bio-analytics-presets">';
    html += '<span class="bio-analytics-presets-label">Atalhos:</span>';
    [7, 30, 90].forEach(d => {
        html += `<button type="button" class="btn-outline bio-analytics-preset-btn" data-bio-analytics-action="preset" data-preset-days="${d}">${d} dias</button>`;
    });
    html += '</div>';
    html += `<p class="bio-analytics-filter-hint">Sem datas = todos os envios no total e na tabela de envios; o gráfico de linha sem filtro mostra os últimos 90 dias.</p>`;
    html += '</div>';

    html += '<div class="bio-form-analytics-summary card bio-panel-card bio-analytics-panel">';
    html += '<div class="bio-analytics-kpi-row">';
    const kpiSub = df || dt ? 'No período filtrado' : 'Total geral';
    html += `<div class="bio-analytics-kpi"><span class="bio-analytics-kpi-value">${total}</span><span class="bio-analytics-kpi-label">Respostas <small class="bio-analytics-kpi-sub">${escapeHtml(kpiSub)}</small></span></div>`;
    html += `<div class="bio-analytics-kpi"><span class="bio-analytics-kpi-value">${fields.length}</span><span class="bio-analytics-kpi-label">Perguntas</span></div>`;
    html += '</div>';

    if (data.timeline && data.timeline.length) {
        const cid = `bio-form-chart-${pageId}-timeline`;
        html += '<div class="bio-analytics-chart-wrap bio-analytics-chart-wrap--timeline"><canvas id="' + cid + '"></canvas></div>';
        jobs.push({
            id: cid,
            kind: 'line',
            labels: data.timeline.map(t => t.date),
            values: data.timeline.map(t => t.count)
        });
    }

    const csvHref = bioAnalyticsExportUrl(pageId, 'export_csv', st);
    const xlsxHref = bioAnalyticsExportUrl(pageId, 'export_xlsx', st);
    html += '<div class="bio-analytics-export-row">';
    html += `<a class="btn-outline" href="${escapeAttribute(csvHref)}"><i class="fas fa-file-csv"></i> Exportar CSV</a>`;
    html += `<a class="btn-outline" href="${escapeAttribute(xlsxHref)}"><i class="fas fa-file-excel"></i> Exportar XLSX</a>`;
    html += '</div></div>';

    const recent = data.recent_submissions || [];
    const rp = data.recent_per_page || 10;
    const rpage = data.recent_page || 1;
    const rtotal = data.recent_total ?? 0;
    const rTotalPages = Math.max(1, Math.ceil(rtotal / rp));
    html += '<div class="bio-analytics-recent card bio-panel-card">';
    html += '<div class="bio-analytics-recent-head">';
    html += '<h4 class="bio-analytics-section-title">Envios</h4>';
    html += '<label class="bio-analytics-per-label">Por página <select class="bio-analytics-recent-per-page">';
    [5, 10, 25, 50].forEach(n => {
        html += `<option value="${n}"${n === rp ? ' selected' : ''}>${n}</option>`;
    });
    html += '</select></label></div>';
    if (recent.length) {
        html += '<div class="table-wrap bio-analytics-table-wrap"><table class="bio-analytics-table"><thead><tr><th>ID</th><th>Data</th></tr></thead><tbody>';
        recent.forEach(r => {
            html += `<tr><td>#${r.id}</td><td>${escapeHtml(String(r.created_at))}</td></tr>`;
        });
        html += '</tbody></table></div>';
    } else {
        html += '<div class="bio-empty-state">Nenhum envio neste período.</div>';
    }
    html += '<div class="bio-analytics-recent-pager">';
    html += `<button type="button" class="btn-outline" data-bio-analytics-action="recent-prev"${rpage <= 1 ? ' disabled' : ''}>Anterior</button>`;
    html += `<span class="bio-analytics-pager-info">Página <strong>${rpage}</strong> de <strong>${rTotalPages}</strong> <span class="bio-analytics-pager-sub">(${rtotal} envio(s))</span></span>`;
    html += `<button type="button" class="btn-outline" data-bio-analytics-action="recent-next"${rpage >= rTotalPages ? ' disabled' : ''}>Próxima</button>`;
    html += '</div></div>';

    fields.forEach((f, idx) => {
        const ft = f.field_type;
        const rc = f.response_count ?? 0;
        const fill = total > 0 ? Math.round((rc / total) * 100) : 0;

        html += '<div class="bio-form-analytics-field card bio-panel-card bio-analytics-field-block">';
        html += '<div class="bio-analytics-field-head">';
        html += `<h4>${escapeHtml(f.label)}</h4><span class="bio-analytics-type-badge">${escapeHtml(ft)}</span>`;
        html += '</div>';
        html += `<p class="bio-analytics-mini-kpi">Preenchimento: <strong>${fill}%</strong> <span class="bio-analytics-mini-sub">(${rc} de ${total} envios)</span></p>`;

        if (ft === 'number' && f.numeric_summary) {
            const ns = f.numeric_summary;
            const keys = ['count', 'min', 'max', 'avg', 'median'];
            const labels = { count: 'N', min: 'Mín.', max: 'Máx.', avg: 'Média', median: 'Mediana' };
            html += '<div class="bio-analytics-kpi-row bio-analytics-kpi-row--compact">';
            keys.forEach(k => {
                if (ns[k] === undefined || ns[k] === null) return;
                const v = typeof ns[k] === 'number' ? ns[k] : ns[k];
                html += `<div class="bio-analytics-kpi bio-analytics-kpi--sm"><span class="bio-analytics-kpi-value">${escapeHtml(String(v))}</span><span class="bio-analytics-kpi-label">${labels[k]}</span></div>`;
            });
            html += '</div>';
            if (f.numeric_histogram && f.numeric_histogram.length) {
                const cid = `bio-form-chart-${pageId}-num-${idx}`;
                html += '<p class="bio-analytics-hint">Distribuição por faixas</p>';
                html += `<div class="bio-analytics-chart-wrap"><canvas id="${cid}"></canvas></div>`;
                jobs.push({
                    id: cid,
                    kind: 'bar',
                    labels: f.numeric_histogram.map(h => h.label),
                    values: f.numeric_histogram.map(h => h.count)
                });
            }
        } else if ((ft === 'date' || ft === 'time') && f.distribution && Object.keys(f.distribution).length) {
            const cid = `bio-form-chart-${pageId}-dt-${idx}`;
            const labels = Object.keys(f.distribution);
            const values = labels.map(k => f.distribution[k]);
            html += `<div class="bio-analytics-chart-wrap bio-analytics-chart-wrap--tall"><canvas id="${cid}"></canvas></div>`;
            jobs.push({
                id: cid,
                kind: 'bar',
                labels,
                values,
                indexAxis: 'y'
            });
        } else if (f.chartable && f.distribution && Object.keys(f.distribution).length) {
            const cid = `bio-form-chart-${pageId}-dist-${idx}`;
            const labels = Object.keys(f.distribution);
            const values = labels.map(k => f.distribution[k]);
            html += `<div class="bio-analytics-chart-wrap"><canvas id="${cid}"></canvas></div>`;
            jobs.push({
                id: cid,
                kind: labels.length <= 6 ? 'doughnut' : 'bar',
                labels,
                values
            });
        } else if (['short_text', 'long_text', 'email', 'phone', 'url'].includes(ft)) {
            const ttot = f.text_total ?? 0;
            if (ttot === 0) {
                html += '<div class="bio-empty-state">Sem respostas para esta pergunta neste período.</div>';
            } else {
                html += `<div class="bio-analytics-text-block" data-field-key="${escapeAttribute(f.key)}">`;
                html += '<p class="bio-analytics-hint">Respostas em texto <span class="bio-analytics-text-meta"></span> — exporte o CSV para o arquivo completo.</p>';
                html += '<div class="bio-analytics-text-pager"></div>';
                html += '<div class="table-wrap bio-analytics-table-wrap"><table class="bio-analytics-table"><thead><tr><th>Resposta</th></tr></thead><tbody class="bio-analytics-text-tbody"></tbody></table></div>';
                html += '<div class="bio-analytics-text-load-msg bio-empty-state">Carregando respostas…</div>';
                html += '</div>';
            }
        } else {
            html += '<div class="bio-empty-state">Sem dados para esta pergunta ainda.</div>';
        }

        html += '</div>';
    });

    return { html, jobs };
}

function runBioAnalyticsChartJobs(jobs) {
    if (!window.Chart || !jobs || !jobs.length) return;
    jobs.forEach(job => {
        const ctx = document.getElementById(job.id);
        if (!ctx) return;
        const labels = job.labels || [];
        const values = job.values || [];
        const colors = labels.map((_, i) => bioAnalyticsPalette(i));

        if (job.kind === 'line') {
            bioFormChartHandles[job.id] = new Chart(ctx, {
                type: 'line',
                data: {
                    labels,
                    datasets: [{
                        label: job.datasetLabel || 'Envios',
                        data: values,
                        borderColor: '#1A8CFF',
                        backgroundColor: 'rgba(43,246,209,0.12)',
                        fill: true,
                        tension: 0.25
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, ticks: { precision: 0 } },
                        x: { ticks: { maxRotation: 45, minRotation: 0 } }
                    }
                }
            });
            return;
        }

        if (job.kind === 'doughnut') {
            bioFormChartHandles[job.id] = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels,
                    datasets: [{
                        data: values,
                        backgroundColor: colors,
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom' } }
                }
            });
            return;
        }

        const isHorizontal = job.indexAxis === 'y';
        const chartType = job.kind === 'doughnut' ? 'doughnut' : 'bar';
        const dataset = {
            label: 'Respostas',
            data: values,
            backgroundColor: chartType === 'bar' ? colors : colors,
            borderWidth: 1
        };

        bioFormChartHandles[job.id] = new Chart(ctx, {
            type: chartType,
            data: {
                labels,
                datasets: [dataset]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: isHorizontal ? 'y' : 'x',
                plugins: { legend: { display: chartType === 'bar' && !isHorizontal, position: 'top' } },
                scales: isHorizontal
                    ? { x: { beginAtZero: true, ticks: { precision: 0 } } }
                    : { y: { beginAtZero: true, ticks: { precision: 0 } } }
            }
        });
    });
}

window.loadBioFormAnalytics = async function () {
    const mount = document.getElementById('bio-form-analytics-mount');
    if (!mount) return;
    const pageId = currentBioPage && currentBioPage.id;
    if (!pageId) {
        mount.innerHTML = '<div class="bio-empty-state">Salve a página com um bloco de formulário para ver respostas e exportações.</div>';
        return;
    }
    await bioAnalyticsReloadMount(mount, pageId, {
        dateFrom: '',
        dateTo: '',
        recentPage: 1,
        recentPerPage: 10
    });
};

window.toggleBioPageListAnalytics = async function (pageId, hasForm) {
    const panel = document.getElementById(`bio-page-analytics-${pageId}`);
    if (!panel) return;

    const isOpen = panel.classList.contains('open');
    if (isOpen) {
        panel.classList.remove('open');
        destroyBioFormChartsInMount(panel);
        panel.innerHTML = '';
        return;
    }

    document.querySelectorAll('.bio-page-card-analytics.open').forEach(p => {
        if (p.id !== panel.id) {
            p.classList.remove('open');
            destroyBioFormChartsInMount(p);
            p.innerHTML = '';
        }
    });

    panel.classList.add('open');

    await bioAnalyticsReloadMount(panel, pageId, {
        dateFrom: '',
        dateTo: '',
        recentPage: 1,
        recentPerPage: 10
    });
};

function hydrateInitialBioState() {
    if (typeof currentBioPage !== 'object' || !currentBioPage) {
        currentBioPage = createEmptyBioPageState();
    } else {
        currentBioPage = { ...createEmptyBioPageState(), ...currentBioPage };
    }
    normalizeCurrentBioPage();
}

function initEnhancedBioBuilder() {
    if (enhancedBioBuilderInitialized) return;
    enhancedBioBuilderInitialized = true;
    ensureGoalTemplates();
    hydrateInitialBioState();
    syncBioStatusSwitchUI(currentBioPage.page_status || 'published');
    renderGoalShortcuts();
    renderBioBlockLibrary();
    renderBioBlocksEditor();
    renderBioLinksEditor();
    renderBioTemplateFilters();
    switchBioTab(currentBioTab);
    updateBioPreview();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initEnhancedBioBuilder);
} else {
    initEnhancedBioBuilder();
}
