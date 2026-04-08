// --- VARIÁVEIS GLOBAIS ---
const domain = window.location.hostname;
let isLoggedIn = false;
let charts = {};

// --- INICIALIZAÇÃO ---
window.onload = () => {
    document.querySelectorAll('.host-display').forEach(el => el.innerText = "." + domain);
    generateRandomSlug();
    checkAuth();
    updateThemeIcon(); // Ajusta o ícone sol/lua com base no tema carregado
};

// --- CONTROLE DE TEMA (CLARO / ESCURO) ---
function toggleTheme() {
    const currentTheme = document.documentElement.getAttribute('data-theme') || 'dark';
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    
    document.documentElement.setAttribute('data-theme', newTheme);
    localStorage.setItem('theme', newTheme);
    updateThemeIcon();
    
    // Atualiza os gráficos se a aba analytics estiver aberta
    if (!document.getElementById('section-analytics').classList.contains('hidden')) {
        loadAnalytics();
    }
}

function updateThemeIcon() {
    const isLight = document.documentElement.getAttribute('data-theme') === 'light';
    const icon = document.getElementById('themeIcon');
    if (icon) {
        icon.className = isLight ? 'fas fa-sun' : 'fas fa-moon';
    }
}

function generateRandomSlug() {
    const chars = 'abcdefghijklmnopqrstuvwxyz0123456789'; let result = '';
    for (let i = 0; i < 6; i++) { result += chars.charAt(Math.floor(Math.random() * chars.length)); }
    document.getElementById('pathSlug').value = result;
}

// --- NAVEGAÇÃO DA SIDEBAR ---
function showSection(id, event) {
    if(event) event.preventDefault();
    document.querySelectorAll('.view-section').forEach(s => s.classList.add('hidden'));
    document.getElementById(id).classList.remove('hidden');
    document.querySelectorAll('.sidebar-nav a').forEach(a => a.classList.remove('active'));
    if(event) event.currentTarget.classList.add('active');
    
    if(id === 'section-analytics') loadAnalytics();
    if(id === 'section-mylinks') loadMyLinks();
}

function openModal(id) {
    document.getElementById('modalOverlay').classList.remove('hidden');
    document.querySelectorAll('.modal-content').forEach(m => m.classList.add('hidden'));
    document.getElementById(id).classList.remove('hidden');
}

function closeModals() { document.getElementById('modalOverlay').classList.add('hidden'); }

// --- AUTENTICAÇÃO E INTERFACE NO HEADER ---
function updateUIMenu(name) {
    const menu = document.getElementById('authMenu');
    menu.innerHTML = `
        <span style="color:var(--teal); font-weight:bold; font-size:0.95rem;">Olá, ${name}</span>
        <button onclick="logout()" class="btn-outline">Sair</button>
    `;
}

function updateUILoggedOut() {
    const menu = document.getElementById('authMenu');
    menu.innerHTML = `
        <button onclick="openModal('modalLogin')" class="btn-text">Entrar</button>
        <button onclick="openModal('modalSignup')" class="btn-outline">Criar Conta</button>
    `;
}

async function checkAuth() {
    try {
        const res = await fetch('auth.php?action=status&t=' + new Date().getTime(), { credentials: 'include' });
        const data = await res.json();
        isLoggedIn = data.logged_in;
        if (data.logged_in) { localStorage.setItem('nick', data.name); updateUIMenu(data.name); } 
        else { localStorage.removeItem('nick'); updateUILoggedOut(); }
    } catch(e) { const sn = localStorage.getItem('nick'); if(sn) { isLoggedIn = true; updateUMenu(sn); } else { updateUILoggedOut(); } }
}

async function handleAuth(e, action) {
    e.preventDefault(); const fd = new FormData(); fd.append('action', action);
    if (action === 'signup') {
        fd.append('name', document.getElementById('signName').value); fd.append('nickname', document.getElementById('signNickname').value); fd.append('email', document.getElementById('signEmail').value); fd.append('phone', document.getElementById('signPhone').value); fd.append('password', document.getElementById('signPass').value);
    } else { fd.append('email', document.getElementById('loginEmail').value); fd.append('password', document.getElementById('loginPass').value); }
    try {
        const res = await fetch('auth.php', { method: 'POST', body: fd, credentials: 'include' }); const data = await res.json();
        if (data.success) { isLoggedIn = true; localStorage.setItem('nick', data.name); closeModals(); updateUIMenu(data.name); if(action === 'signup') alert("Conta criada com sucesso!"); } else { alert(data.message); }
    } catch (err) { alert("Erro de conexão com o servidor."); }
}

async function handleForgot(e) {
    e.preventDefault(); const btn = document.getElementById('btnForgot'); const email = document.getElementById('forgotEmail').value;
    btn.innerHTML = 'Enviando...'; btn.disabled = true;
    const fd = new FormData(); fd.append('action', 'forgot'); fd.append('email', email);
    await fetch('auth.php', { method: 'POST', body: fd, credentials: 'include' });
    btn.innerHTML = 'Enviar Código'; btn.disabled = false; window.resetEmail = email; openModal('modalReset');
}

async function handleReset(e) {
    e.preventDefault(); const fd = new FormData(); fd.append('action', 'reset'); fd.append('email', window.resetEmail); fd.append('code', document.getElementById('resetCode').value); fd.append('new_password', document.getElementById('resetPass').value);
    const res = await fetch('auth.php', { method: 'POST', body: fd, credentials: 'include' }); const data = await res.json();
    if (data.success) { alert("Senha alterada com sucesso! Faça login."); openModal('modalLogin'); } else { alert(data.message); }
}

async function logout() { 
    await fetch('auth.php?action=logout', { credentials: 'include' }); isLoggedIn = false; localStorage.removeItem('nick'); updateUILoggedOut(); showSection('section-home'); 
}

// --- ENCURTADOR DE LINKS ---
async function processLink() {
    if (!isLoggedIn) return openModal('modalLogin');
    const long = document.getElementById('longUrl').value; const sub = document.getElementById('subdomain').value.toLowerCase().trim(); const path = document.getElementById('pathSlug').value.toLowerCase().trim(); const btn = document.getElementById('btnMain');
    if(!long || !path) return alert("URL e Terminação são obrigatórios!");
    btn.disabled = true; btn.innerHTML = 'Gerando... <i class="fas fa-spinner fa-spin"></i>';
    const fd = new FormData(); fd.append('url', long); fd.append('subdomain', sub); fd.append('path', path);
    try {
        const res = await fetch('api.php', { method: 'POST', body: fd, credentials: 'include' }); const data = await res.json();
        if(data.success) { document.getElementById('resultArea').classList.remove('hidden'); document.getElementById('shortUrl').value = `https://${sub ? sub + '.' : ''}${domain}/${path}`; } else { alert(data.message); }
    } catch (e) { alert("Erro ao salvar o link."); } finally { btn.disabled = false; btn.innerHTML = 'Gerar Link Agora <i class="fas fa-magic"></i>'; }
}

function copyLink() {
    const input = document.getElementById("shortUrl"); input.select(); navigator.clipboard.writeText(input.value);
    const btn = document.getElementById('btnCopy'); btn.innerText = "Copiado!"; setTimeout(() => btn.innerText = "Copiar", 2000);
}

// --- GERENCIAMENTO: MEUS LINKS ---
async function loadMyLinks() {
    if(!isLoggedIn) return;
    const res = await fetch('manage_links.php?action=list', { credentials: 'include' }); const data = await res.json();
    const tbody = document.getElementById('myLinksBody'); tbody.innerHTML = '';
    if(data.links.length === 0) { tbody.innerHTML = `<tr><td colspan="4" style="text-align:center; padding:40px; color:var(--text-dim);">Nenhum link criado ainda.</td></tr>`; return; }

    data.links.forEach(link => {
        const fullUrl = `https://${link.subdomain === 'ROOT' ? '' : link.subdomain + '.'}${domain}/${link.path}`;
        const isPaused = link.status === 'paused';
        const statusBtnClass = isPaused ? 'btn-play' : 'btn-pause'; const statusIcon = isPaused ? 'fa-play' : 'fa-pause'; const statusTitle = isPaused ? 'Ativar Link' : 'Pausar Link';
        
        tbody.innerHTML += `
            <tr>
                <td>
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <a href="${fullUrl}" target="_blank" class="link-primary">${fullUrl}</a>
                        <button onclick="copyTableLink('${fullUrl}', this)" class="btn-action btn-copy" title="Copiar"><i class="far fa-copy"></i></button>
                    </div>
                    <span class="link-subtext">Terminação: /${link.path}</span>
                </td>
                <td><span class="link-url-truncate" title="${link.original_url}">${link.original_url}</span></td>
                <td><span class="badge-clicks">${link.clicks}</span></td>
                <td>
                    <div class="actions-flex">
                        <button onclick="toggleStatus(${link.id}, '${link.status}')" class="btn-action ${statusBtnClass}" title="${statusTitle}"><i class="fas ${statusIcon}"></i></button>
                        <button onclick="openEdit(${link.id}, '${link.original_url}')" class="btn-action btn-edit" title="Editar Destino"><i class="fas fa-pen"></i></button>
                        <button onclick="deleteLink(${link.id})" class="btn-action btn-delete" title="Excluir"><i class="fas fa-trash"></i></button>
                    </div>
                </td>
            </tr>
        `;
    });
}

function copyTableLink(text, btnElement) {
    navigator.clipboard.writeText(text).then(() => {
        const originalHTML = btnElement.innerHTML;
        btnElement.innerHTML = '<i class="fas fa-check"></i>'; btnElement.style.background = 'var(--teal)'; btnElement.style.color = '#04449E';
        setTimeout(() => { btnElement.innerHTML = originalHTML; btnElement.style.background = ''; btnElement.style.color = ''; }, 2000);
    });
}

async function toggleStatus(id, currentStatus) {
    const newStatus = currentStatus === 'paused' ? 'active' : 'paused';
    const fd = new FormData(); fd.append('action', 'toggle_status'); fd.append('id', id); fd.append('status', newStatus);
    await fetch('manage_links.php', { method: 'POST', body: fd, credentials: 'include' }); loadMyLinks();
}

async function deleteLink(id) {
    if(!confirm("Tem certeza que deseja excluir este link?")) return;
    const fd = new FormData(); fd.append('action', 'delete'); fd.append('id', id);
    await fetch('manage_links.php', { method: 'POST', body: fd, credentials: 'include' }); loadMyLinks();
}

function openEdit(id, currentUrl) { document.getElementById('editLinkId').value = id; document.getElementById('editLinkUrl').value = currentUrl; openModal('modalEditLink'); }

async function saveEditLink(e) {
    e.preventDefault(); const id = document.getElementById('editLinkId').value; const url = document.getElementById('editLinkUrl').value;
    const fd = new FormData(); fd.append('action', 'edit_url'); fd.append('id', id); fd.append('url', url);
    const res = await fetch('manage_links.php', { method: 'POST', body: fd, credentials: 'include' }); const data = await res.json();
    if(data.success) { closeModals(); loadMyLinks(); alert("Destino alterado com sucesso!"); } else { alert(data.message); }
}

// --- ANALYTICS (CHART.JS) ---
async function loadAnalytics() {
    if(!isLoggedIn) { alert("Você precisa estar logado."); showSection('section-home'); openModal('modalLogin'); return; }
    const filterValue = document.getElementById('analyticsFilter').value;
    try {
        const res = await fetch(`analytics.php?link_id=${filterValue}`, { credentials: 'include' }); const data = await res.json();
        if(!data.success) return;
        const filterSelect = document.getElementById('analyticsFilter');
        if(filterSelect.options.length === 1 && data.links) { data.links.forEach(l => { const option = document.createElement('option'); option.value = l.id; option.text = `/${l.path} (${l.subdomain})`; filterSelect.appendChild(option); }); }

        // Cores baseadas no tema atual
        const isLight = document.documentElement.getAttribute('data-theme') === 'light';
        const textColor = isLight ? '#64748b' : '#94a3b8';

        renderChart('chartCountries', 'bar', data.countries.map(i => i.label), data.countries.map(i => i.value), textColor);
        renderChart('chartReferrers', 'bar', data.referrers.map(i => i.label), data.referrers.map(i => i.value), textColor);
        renderChart('chartDevices', 'doughnut', Object.keys(data.devices), Object.values(data.devices), textColor);
        renderChart('chartBrowsers', 'pie', Object.keys(data.browsers), Object.values(data.browsers), textColor);
    } catch(e) {}
}

function renderChart(id, type, labels, data, textColor) {
    if(charts[id]) charts[id].destroy();
    const ctx = document.getElementById(id).getContext('2d');
    charts[id] = new Chart(ctx, {
        type: type,
        data: { labels: labels, datasets: [{ data: data, backgroundColor: ['#2BF6D1', '#04449E', '#3b82f6', '#1e293b', '#64748b'], borderWidth: 0 }] },
        options: { responsive: true, plugins: { legend: { display: (type !== 'bar'), labels: { color: textColor } } }, scales: type === 'bar' ? { y: { ticks: { color: textColor, stepSize: 1 } }, x: { ticks: { color: textColor } } } : {} }
    });
}