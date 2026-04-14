// --- VARIÁVEIS GLOBAIS ---
const domain = window.location.hostname;
const protocol = window.location.protocol; // Detecta http ou https
let isLoggedIn = false;
let charts = {};
let myLinksData = []; // Armazena os links para acesso fácil via ID

// --- INICIALIZAÇÃO ---
window.onload = async () => {
    // Preenche os textos de ".encurtou.pro" na interface
    document.querySelectorAll('.host-display').forEach(el => el.innerText = "." + domain);
    generateRandomSlug();
    
    // Aguarda autenticação antes de restaurar a seção
    await checkAuth();

    // Restaura Seção Ativa
    const savedSection = localStorage.getItem('activeSection');
    if (savedSection) {
        // Verifica permissão (seções privadas exigem login)
        const privateSections = ['section-mylinks', 'section-myqrs', 'section-analytics'];
        if (privateSections.includes(savedSection) && !isLoggedIn) {
            showSection('section-home');
        } else {
            showSection(savedSection);
        }
    }

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

function showSection(id, event) {
    if (event) event.preventDefault();
    
    const target = document.getElementById(id);
    if (!target) return;

    document.querySelectorAll('.view-section').forEach(s => s.classList.add('hidden'));
    target.classList.remove('hidden');
    
    document.querySelectorAll('.sidebar-nav a').forEach(a => a.classList.remove('active'));
    
    // Ativa o link na sidebar (via evento ou busca manual por id da seção)
    if (event) {
        event.currentTarget.classList.add('active');
    } else {
        const sidebarLink = document.querySelector(`.sidebar-nav a[onclick*='${id}']`);
        if (sidebarLink) sidebarLink.classList.add('active');
    }

    // Fechar sidebar no mobile após clique
    if (window.innerWidth <= 900) {
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.querySelector('.sidebar-overlay');
        if (sidebar) sidebar.classList.remove('open');
        if (overlay) overlay.classList.remove('active');
    }

    if (id === 'section-analytics') loadAnalytics();
    if (id === 'section-mylinks') {
        loadMyLinks();
        applyStoredViewMode('myLinksCards');
    }
    if (id === 'section-qrcodes') {
        resetAllInputs(); 
        loadQRLinks();
    }
    if (id === 'section-myqrs') {
        loadQRLinks();
        applyStoredViewMode('myQRsCards');
    }

    localStorage.setItem('activeSection', id);
}

function resetAllInputs() {
    // Links
    const originalUrl = document.getElementById('originalUrl');
    const pathSlug = document.getElementById('pathSlug');
    const resultArea = document.getElementById('resultArea');
    const subElement = document.getElementById('subdomain');
    if (originalUrl) originalUrl.value = '';
    if (subElement) subElement.value = '';
    if (pathSlug) generateRandomSlug();
    if (resultArea) resultArea.classList.add('hidden');

    // QR
    const qrNewTarget = document.getElementById('qrNewTarget');
    const qrNewPath = document.getElementById('qrNewPath');
    const qrSubdomain = document.getElementById('qrSubdomain');
    if (qrNewTarget) qrNewTarget.value = '';
    if (qrSubdomain) qrSubdomain.value = '';
    if (qrNewPath) generateQRPath();
    
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
    const storedMode = localStorage.getItem(`viewMode_${containerId}`);
    if (storedMode) {
        toggleViewMode(containerId, storedMode);
    }
}

// --- CONTROLE DOS MODAIS ---
function openModal(id) {
    document.getElementById('modalOverlay').classList.remove('hidden');
    document.querySelectorAll('.modal-content').forEach(m => m.classList.add('hidden'));
    document.getElementById(id).classList.remove('hidden');
}

function closeModals() {
    document.getElementById('modalOverlay').classList.add('hidden');
}

// --- AUTENTICAÇÃO E INTERFACE ---
function updateUIMenu(name) {
    const menu = document.getElementById('authMenu');
    menu.innerHTML = `
        <span style="color:var(--primary); font-weight:bold; font-size:0.95rem; text-align:center;">Olá, ${name}</span>
        <button onclick="logout()" class="btn-outline" style="width:100%; margin-top:5px;">Sair</button>
    `;
}

function updateUILoggedOut() {
    const menu = document.getElementById('authMenu');
    menu.innerHTML = `
        <button onclick="openModal('modalLogin')" class="btn-text" style="width:100%; text-align:left; padding:8px 0;">Entrar</button>
        <button onclick="openModal('modalSignup')" class="btn-outline" style="width:100%;">Criar Conta</button>
    `;
}

async function checkAuth() {
    try {
        const res = await fetch('auth.php?action=status&t=' + new Date().getTime(), { credentials: 'include' });
        const data = await res.json();
        isLoggedIn = data.logged_in;
        if (data.logged_in) {
            localStorage.setItem('nick', data.name);
            updateUIMenu(data.name);
        } else {
            localStorage.removeItem('nick');
            updateUILoggedOut();
        }
    } catch (e) {
        const sn = localStorage.getItem('nick');
        if (sn) { isLoggedIn = true; updateUIMenu(sn); }
        else { updateUILoggedOut(); }
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
    } else {
        fd.append('email', document.getElementById('loginEmail').value);
        fd.append('password', document.getElementById('loginPass').value);
    }

    try {
        const res = await fetch('auth.php', { method: 'POST', body: fd, credentials: 'include' });
        const data = await res.json();
        if (data.success) {
            isLoggedIn = true;
            localStorage.setItem('nick', data.name);
            closeModals();
            updateUIMenu(data.name);
            if (action === 'signup') console.log("Conta criada com sucesso!");
        } else {
            console.error(data.message);
        }
    } catch (err) { console.error("Erro de conexão com o servidor."); }
}

async function handleForgot(e) {
    e.preventDefault();
    const btn = document.getElementById('btnForgot');
    const email = document.getElementById('forgotEmail').value;
    btn.innerHTML = 'Enviando...'; btn.disabled = true;

    const fd = new FormData(); fd.append('action', 'forgot'); fd.append('email', email);
    await fetch('auth.php', { method: 'POST', body: fd, credentials: 'include' });

    btn.innerHTML = 'Enviar Código'; btn.disabled = false;
    window.resetEmail = email;
    openModal('modalReset');
}

async function handleReset(e) {
    e.preventDefault();
    const fd = new FormData();
    fd.append('action', 'reset'); fd.append('email', window.resetEmail);
    fd.append('code', document.getElementById('resetCode').value);
    fd.append('new_password', document.getElementById('resetPass').value);

    const res = await fetch('auth.php', { method: 'POST', body: fd, credentials: 'include' });
    const data = await res.json();
    if (data.success) {
        openModal('modalLogin');
    } else { console.error(data.message); }
}

async function logout() {
    await fetch('auth.php?action=logout', { credentials: 'include' });
    isLoggedIn = false;
    localStorage.removeItem('nick');
    updateUILoggedOut();
    showSection('section-home');
}

// --- ENCURTADOR DE LINKS ---
async function processLink() {
    if (!isLoggedIn) return openModal('modalLogin');

    let long = document.getElementById('longUrl').value.trim();
    const subElement = document.getElementById('subdomain');
    const sub = subElement ? subElement.value.toLowerCase().trim() : '';
    const path = document.getElementById('pathSlug').value.toLowerCase().trim();
    const btn = document.getElementById('btnMain');

    if (!long || !path) return; // Silent return as requested

    // Auto Adiciona https:// se não houver protocolo
    if (!long.startsWith('http://') && !long.startsWith('https://')) {
        long = 'https://' + long;
        document.getElementById('longUrl').value = long;
    }

    btn.disabled = true; btn.innerHTML = 'Gerando... <i class="fas fa-spinner fa-spin"></i>';

    const fd = new FormData(); 
    fd.append('url', long); 
    fd.append('subdomain', sub); 
    fd.append('path', path);
    fd.append('type', 'link');

    try {
        const res = await fetch('api.php', { method: 'POST', body: fd, credentials: 'include' });
        const data = await res.json();
        if (data.success) {
            document.getElementById('resultArea').classList.remove('hidden');
            const hasSub = sub && sub !== 'ROOT';
            const subPrefix = hasSub ? sub + '.' : '';
            document.getElementById('shortUrl').value = `${protocol}//${subPrefix}${domain}/${path}`;
            generateRandomSlug(); // Renova para o próximo clique
            showStatus('link', 'Link gerado com sucesso!', 'success');
        } else { 
            console.error(data.message); 
            showStatus('link', data.message, 'error');
        }
    } catch (e) { 
        console.error("Erro ao salvar o link.", e); 
        showStatus('link', 'Erro de conexão com o servidor.', 'error');
    }
    finally { btn.disabled = false; btn.innerHTML = 'Gerar Link Agora <i class="fas fa-magic"></i>'; }
}

function showStatus(type, msg, status) {
    const btnId = type === 'link' ? 'btnMain' : 'btnSaveQR';
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
        statusEl.style.background = 'rgba(43, 246, 209, 0.1)';
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

// --- GERENCIAMENTO: MEUS LINKS ---
async function loadMyLinks() {
    if (!isLoggedIn) return;
    try {
        const res = await fetch('manage_links.php?action=list&type=link', { credentials: 'include' });
        const data = await res.json();
        const container = document.getElementById('myLinksCards');
        if (!container) return;
        container.innerHTML = '';
        myLinksData = data.links || [];

        if (!data.success || !data.links || data.links.length === 0) {
            container.innerHTML = `<div style="grid-column: 1/-1; text-align:center; padding:60px; color:#94a3b8; background:var(--card); border-radius:20px;">Nenhum link criado ainda.</div>`;
            return;
        }

        data.links.forEach(link => {
            const hasSub = link.subdomain !== 'ROOT' && link.subdomain !== '';
            const fullUrl = `${protocol}//${hasSub ? link.subdomain + '.' : ''}${domain}/${link.path}`;
            const hostname = new URL(link.original_url).hostname;
            const favicon = `https://www.google.com/s2/favicons?domain=${hostname}&sz=64`;

            container.innerHTML += `
                <div class="link-card" id="link-${link.id}">
                    <div class="link-card-header" style="display:flex; align-items:center; gap:15px; width:100%;">
                        <div class="link-card-icon" style="width:48px; height:48px; flex-shrink:0; display:flex; align-items:center; justify-content:center; background:rgba(255,255,255,0.05); border-radius:12px;">
                            <img src="${favicon}" alt="icon" style="width:28px; height:28px; object-fit:contain;" onerror="this.src='https://cdn-icons-png.flaticon.com/512/1243/1243933.png'">
                        </div>
                        <div class="link-card-title-group" style="flex-grow:1; overflow:hidden;">
                            <span class="link-card-title">${link.title || 'Página sem título'}</span>
                            <span class="link-card-date"><i class="far fa-calendar-alt"></i> ${link.data_criacao}</span>
                        </div>
                        <div class="badge-clicks" title="Cliques Digitais">${link.clicks_digital} <i class="fas fa-mouse-pointer" style="font-size:0.7rem;"></i></div>
                    </div>

                    <div class="link-card-body">
                        <a href="${fullUrl}" target="_blank" class="link-card-short">${fullUrl.replace('https://', '').replace('http://', '')}</a>
                        <span class="link-card-dest" title="${link.original_url}">${link.original_url}</span>
                    </div>

                    <div class="link-card-footer">
                        <div class="link-card-actions">
                            <button onclick="copyTableLink('${fullUrl}', this)" class="btn-action btn-copy" title="Copiar Link">
                                <i class="far fa-copy"></i>
                            </button>
                            <button onclick="focusQR('${fullUrl}')" class="btn-action" style="background:rgba(43, 246, 209, 0.2); color:var(--primary);" title="Gerar QR Code">
                                <i class="fas fa-qrcode"></i>
                            </button>
                            <button onclick="shareLink('${fullUrl}')" class="btn-action" style="background:rgba(255,255,255,0.05); color:var(--text);" title="Compartilhar">
                                <i class="fas fa-share-alt"></i>
                            </button>
                            <button onclick="openEdit(${link.id}, '${link.original_url}', '${link.url_ios || ''}', '${link.url_android || ''}', '${link.url_windows || ''}')" class="btn-action btn-edit" title="Editar">
                                <i class="fas fa-pen"></i>
                            </button>
                            <button onclick="goToAnalytics(${link.id})" class="btn-action btn-play" style="background:rgba(59, 130, 246, 0.2); color:#3b82f6;" title="Estatísticas">
                                <i class="fas fa-chart-line"></i>
                            </button>
                            <button onclick="showLinkDetails(${link.id})" class="btn-action" style="background:rgba(255,255,255,0.05); color:var(--text-dim);" title="Mais Detalhes">
                                <i class="fas fa-info-circle"></i>
                            </button>
                        </div>
                        <button onclick="deleteLink(${link.id})" class="btn-action btn-delete" title="Excluir">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            `;
        });
    } catch (e) { console.error("Erro ao carregar links", e); }
}

function shareLink(url) {
    if (navigator.share) {
        navigator.share({ title: 'Encurtou.pro', url: url }).catch(() => {});
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
        <hr style="border:0; border-top:1px solid rgba(255,255,255,0.1); margin:15px 0;">
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
    const newStatus = currentStatus === 'paused' ? 'active' : 'paused';
    const fd = new FormData(); fd.append('action', 'toggle_status'); fd.append('id', id); fd.append('status', newStatus);
    await fetch('manage_links.php', { method: 'POST', body: fd, credentials: 'include' });
    loadMyLinks();
}

async function deleteLink(id) {
    if (!confirm("Tem certeza que deseja excluir este link definitivamente?")) return;
    const fd = new FormData(); fd.append('action', 'delete'); fd.append('id', id);
    await fetch('manage_links.php', { method: 'POST', body: fd, credentials: 'include' });
    loadMyLinks();
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
async function loadAnalytics() {
    if (!isLoggedIn) { showSection('section-home'); openModal('modalLogin'); return; }

    const filterValue = document.getElementById('analyticsFilter').value;

    try {
        const res = await fetch(`analytics.php?link_id=${filterValue}`, { credentials: 'include' });
        const data = await res.json();

        if (!data.success) return;

        // Preenche o Dropdown de filtro (apenas na primeira vez)
        const filterSelect = document.getElementById('analyticsFilter');
        if (filterSelect.options.length === 1 && data.links) {
            data.links.forEach(l => {
                const option = document.createElement('option');
                option.value = l.id;
                option.text = `/${l.path} (${l.subdomain})`;
                filterSelect.appendChild(option);
            });
        }

        renderChart('chartCountries', 'bar', data.countries.map(i => i.label), data.countries.map(i => i.value));
        renderChart('chartReferrers', 'bar', data.referrers.map(i => i.label), data.referrers.map(i => i.value));
        renderChart('chartDevices', 'doughnut', Object.keys(data.devices), Object.values(data.devices));
        renderChart('chartBrowsers', 'pie', Object.keys(data.browsers), Object.values(data.browsers));
        
        // Novo gráfico de Origem (Digital vs QR)
        const sourceLabels = data.sources.map(s => s.label === 'qr' ? 'QR Code' : 'Link Digital');
        renderChart('chartSources', 'pie', sourceLabels, data.sources.map(i => i.value));

    } catch (e) { console.error("Erro ao carregar Analytics", e); }
}

function renderChart(id, type, labels, data) {
    if (charts[id]) charts[id].destroy();
    const ctx = document.getElementById(id).getContext('2d');
    charts[id] = new Chart(ctx, {
        type: type,
        data: {
            labels: labels,
            datasets: [{ data: data, backgroundColor: ['#2BF6D1', '#3b82f6', '#475569', '#1e293b', '#64748b'], borderWidth: 0 }]
        },
        options: {
            responsive: true,
            plugins: { 
                legend: { 
                    display: (type !== 'bar'), 
                    labels: { color: '#94a3b8' } 
                } 
            },
            scales: type === 'bar' ? { y: { ticks: { color: '#94a3b8', stepSize: 1 } }, x: { ticks: { color: '#94a3b8' } } } : {}
        }
    });
}

// --- LOGICA DE QR CODES ---
let qrCode = null;
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

function generateQRPath() {
    const chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
    let res = 'qr-';
    for (let i = 0; i < 5; i++) res += chars.charAt(Math.floor(Math.random() * chars.length));
    document.getElementById('qrNewPath').value = res;
}

async function saveQRLink() {
    if (!isLoggedIn) return openModal('modalLogin');

    let target = document.getElementById('qrNewTarget').value.trim();
    const path = document.getElementById('qrNewPath').value.trim();
    const btn = document.getElementById('btnSaveQR');

    if (!target || !path) return alert("URL e Terminação são necessários.");

    // Auto Adiciona https:// se não houver protocolo
    if (!target.startsWith('http://') && !target.startsWith('https://')) {
        target = 'https://' + target;
        document.getElementById('qrNewTarget').value = target;
        updateQRPreview();
    }

    btn.disabled = true; 
    btn.innerHTML = 'Salvando... <i class="fas fa-spinner fa-spin"></i>';

    const sub = document.getElementById('qrSubdomain').value.toLowerCase().trim();

    const fd = new FormData();
    fd.append('url', target);
    fd.append('subdomain', sub); 
    fd.append('path', path);
    fd.append('type', 'qr');

    try {
        const res = await fetch('api.php', { method: 'POST', body: fd, credentials: 'include' });
        const data = await res.json();
        if (data.success) {
            await loadQRLinks();
            generateQRPath(); // Renova para o próximo QR
            showStatus('qr', 'QR Code salvo!', 'success');
            toggleQRMode('select');
            // Seleciona o novo link
            const hasSub = sub && sub !== 'ROOT';
            const subPrefix = hasSub ? sub + '.' : '';
            const fullUrl = `${protocol}//${subPrefix}${domain}/${path}`;
            document.getElementById('qrLinkSelect').value = fullUrl + '?src=qr';
            updateQRPreview();
        } else {
            console.error(data.message);
            showStatus('qr', data.message, 'error');
        }
    } catch (e) { 
        console.error("Erro ao salvar QR.");
        showStatus('qr', 'Erro de conexão.', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = 'Salvar QR Code <i class="fas fa-save"></i>';
    }
}


async function loadQRLinks() {
    if (!isLoggedIn) return;
    try {
        // Busca TODOS para o seletor, mas vamos filtrar para os cards no frontend ou o backend mantém o filtro?
        // Na verdade, vou buscar type=all para preencher o select e o frontend filtra os cards.
        const res = await fetch('manage_links.php?action=list&type=all', { credentials: 'include' });
        const data = await res.json();
        
        if (!data.success) return;

        const select = document.getElementById('qrLinkSelect');
        const container = document.getElementById('myQRsCards');
        
        if (select) select.innerHTML = '';
        if (container) container.innerHTML = '';
        myLinksData = data.links;

        if (select) select.innerHTML = '<option value="">--- Selecione um Link ---</option>';
        if (container) container.innerHTML = '';
        myLinksData = data.links || [];
        
        if (!data.success || !data.links || data.links.length === 0) {
            if (select) select.innerHTML = '<option value="">Crie um link primeiro!</option>';
            if (container) container.innerHTML = '<div style="grid-column: 1/-1; text-align:center; padding:60px; color:#94a3b8; background:var(--card); border-radius:20px;">Nenhum QR Code gerado ainda.</div>';
            return;
        }
        
        data.links.forEach((link, idx) => {
            const hasSub = link.subdomain !== 'ROOT' && link.subdomain !== '';
            const fullUrl = `${protocol}//${hasSub ? link.subdomain + '.' : ''}${domain}/${link.path}`;
            const qrUrl = fullUrl + '?src=qr';
            
            // Preenche Seletor (TODOS os links aparecem aqui)
            if (select) {
                const option = document.createElement('option');
                option.value = `${qrUrl}|${link.id}`; // Armazena URL e ID
                option.text = `[/${link.path}] ${link.title || 'Sem título'}`; 
                select.appendChild(option);
            }

            // Preenche Cards (APENAS links criados como QR aparecem aqui)
            if (container && link.type === 'qr') {
                const hostname = new URL(link.original_url).hostname;
                const favicon = `https://www.google.com/s2/favicons?domain=${hostname}&sz=64`;
                
                // Preenche o template do card

                container.innerHTML += `
                    <div class="link-card" id="qr-${link.id}">
                        <div class="qr-card-layout" style="display:flex; gap:20px; width:100%;">
                            <div class="qr-card-preview" style="width:130px; height:130px; flex-shrink:0; background:white; padding:10px; border-radius:16px;">
                                <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=${encodeURIComponent(qrUrl)}" alt="QR Code" style="width:100%; height:100%; object-fit:contain;">
                            </div>
                            <div class="qr-card-info" style="flex-grow:1; display:flex; flex-direction:column; gap:8px; overflow:hidden;">
                                <div class="link-card-header" style="display:flex; align-items:center; gap:15px; width:100%; padding-bottom:12px; border-bottom:1px solid rgba(255,255,255,0.05);">
                                    <div class="link-card-title-group" style="flex-grow:1; overflow:hidden;">
                                        <span class="link-card-title">${link.title || 'QR Code Dinâmico'}</span>
                                        <span class="link-card-date"><i class="far fa-calendar-alt"></i> ${link.data_criacao}</span>
                                    </div>
                                    <div class="badge-clicks" title="Scans de QR Code" style="background:rgba(43, 246, 209, 0.1); color:var(--primary); font-weight:800;">
                                        ${link.clicks_qr} <i class="fas fa-qrcode" style="font-size:0.7rem;"></i>
                                    </div>
                                </div>
                                <div class="link-card-body" style="padding:0; margin-top:5px; display:flex; flex-direction:column; gap:5px;">
                                    <a href="${fullUrl}" target="_blank" class="link-card-short" style="font-size:1.05rem;">${fullUrl.replace('https://', '').replace('http://', '')}</a>
                                    <span class="link-card-dest" style="font-size:0.85rem;" title="${link.original_url}">${link.original_url}</span>
                                </div>
                                <div class="link-card-actions" style="margin-top:auto; padding-top:10px; border-top:1px solid rgba(255,255,255,0.05); display:flex; gap:8px;">
                                    <button onclick="focusQR('${qrUrl}')" class="btn-action btn-copy" style="background:rgba(43, 246, 209, 0.2);" title="Personalizar QR">
                                        <i class="fas fa-paint-brush"></i>
                                    </button>
                                    <button onclick="openEdit(${link.id}, '${link.original_url}', '${link.url_ios || ''}', '${link.url_android || ''}', '${link.url_windows || ''}')" class="btn-action btn-edit" title="Editar">
                                        <i class="fas fa-pen"></i>
                                    </button>
                                    <button onclick="shareLink('${fullUrl}')" class="btn-action" style="background:rgba(255,255,255,0.05); color:var(--text);" title="Compartilhar">
                                        <i class="fas fa-share-alt"></i>
                                    </button>
                                    <button onclick="goToAnalytics(${link.id})" class="btn-action btn-play" style="background:rgba(59, 130, 246, 0.2); color:#3b82f6;" title="Estatísticas">
                                        <i class="fas fa-chart-line"></i>
                                    </button>
                                    <button onclick="deleteLink(${link.id})" class="btn-action btn-delete" title="Excluir">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            }

            if (idx === 0) setTimeout(updateQRPreview, 100);
        });
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
        const sub = document.getElementById('qrSubdomain').value.toLowerCase().trim();
        
        if (target) {
            const hasProtocol = target.startsWith('http://') || target.startsWith('https://');
            url = hasProtocol ? target : 'https://' + target;
        } else if (path) {
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
    const logoArea = document.getElementById('qrLogoUrl') ? document.getElementById('qrLogoUrl').value.trim() : "";

    const options = {
        width: 300, height: 300, type: "svg", data: url, image: logoArea || "",
        dotsOptions: { color: dotsColor, type: moduleType },
        backgroundOptions: { color: bgColor },
        cornersSquareOptions: { type: cornerType, color: dotsColor },
        cornersDotOptions: { type: cornerType, color: dotsColor },
        imageOptions: { crossOrigin: "anonymous", margin: 5 }
    };

    if (!qrCode) {
        qrCode = new QRCodeStyling(options);
        const previewCanvas = document.getElementById("qrPreviewCanvas");
        if (previewCanvas) qrCode.append(previewCanvas);
    } else {
        qrCode.update(options);
    }
}

function downloadQR(ext) {
    if (!qrCode) return;
    qrCode.download({ name: "qrcode-encurtou", extension: ext });
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
                document.getElementById('qrLogoUrl').value = data.path;
                document.getElementById('logoFileName').innerText = file.name;
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
            showStatus('qr', 'Link agora é um QR Code Dinâmico!', 'success');
            await loadQRLinks();
            if (typeof loadMyLinks === 'function') await loadMyLinks();
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