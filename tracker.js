/**
 * Encurtou.pro - Tracker Oficial de Conversão
 * Insira este script no <head> ou próximo ao </body> do seu site.
 */
(function() {
    window.Encurtou = window.Encurtou || {};

    // URL BASE do Encurtou API (deve ser a origem onde está o track.php)
    // Se o seu Encurtou.pro estiver instalado em subdomínio ou outro domínio final, defina ele aqui:
    var API_URL = 'https://encurtou.pro/track.php'; 
    var DWELL_URL = 'https://encurtou.pro/track_dwell.php';
    // OBS pro Desenvolvedor: para local/testes via script dinâmico, considere usar caminhos absolutos baseados no próprio script.
    
    // Auto-detecta a URL do script para chamar como API base
    try {
        var scriptUrl = document.currentScript ? document.currentScript.src : '';
        if (scriptUrl) {
            var splitted = scriptUrl.split('/');
            splitted.pop(); // remove tracker.js
            API_URL = splitted.join('/') + '/track.php';
            DWELL_URL = splitted.join('/') + '/track_dwell.php';
        } else {
            // fallback genérico local
            API_URL = window.location.origin + '/track.php'; // se rodando nativamente
            // IMPORTANTE: Isso no PROD deve ser a URL fixa da sua API caso o script seja colado diretamente.
            // Para fim didático deixaremos um placeholder fixo que pode ser sobreposto
        }
    } catch(e) {}

    // Lê os Query Strings da URL Atual
    function getQueryParam(name) {
        var params = new URLSearchParams(window.location.search);
        return params.get(name);
    }

    // Inicialização principal
    function isDwellMonitoringEnabled() {
        var direct = getQueryParam('ep_dm');
        if (direct === '1') return true;
        return window.localStorage.getItem('ep_dm_encurtou') === '1';
    }

    function setupDwellMonitor() {
        if (!isDwellMonitoringEnabled()) return;
        var cid = window.localStorage.getItem('ep_cid_encurtou') || getQueryParam('ep_cid');
        if (!cid) return;

        var startedAt = Date.now();
        var sent = false;
        var flush = function() {
            if (sent) return;
            sent = true;
            var duration = Math.max(0, Date.now() - startedAt);
            var payload = JSON.stringify({
                ep_cid: cid,
                duration_ms: duration
            });
            try {
                if (navigator.sendBeacon) {
                    navigator.sendBeacon(DWELL_URL, new Blob([payload], { type: 'application/json' }));
                    return;
                }
            } catch (e) {}
            try {
                fetch(DWELL_URL, {
                    method: 'POST',
                    credentials: 'omit',
                    keepalive: true,
                    headers: { 'Content-Type': 'application/json' },
                    body: payload
                });
            } catch (e) {}
        };

        window.addEventListener('pagehide', flush, { once: true });
        window.addEventListener('beforeunload', flush, { once: true });
        document.addEventListener('visibilitychange', function() {
            if (document.visibilityState === 'hidden') flush();
        }, { once: true });
    }

    function init() {
        var cid = getQueryParam('ep_cid');
        if (cid) {
            window.localStorage.setItem('ep_cid_encurtou', cid);
        }
        if (getQueryParam('ep_dm') === '1') {
            window.localStorage.setItem('ep_dm_encurtou', '1');
        }
        setupDwellMonitor();
    }

    /**
     * Envia o evento de rastreamento de ação via API
     * @param {string} actionName O slug da Ação de Conversão configurada (ex: 'purchase', 'lead')
     * @param {object} metadata Informações opcionais extras (ex: { value: 100, currency: 'BRL' })
     */
    Encurtou.track = function(actionName, metadata) {
        var cid = window.localStorage.getItem('ep_cid_encurtou') || getQueryParam('ep_cid');
        if (!cid) {
            console.warn("[Encurtou Tracker] ep_cid não encontrado no acesso local. Conversão ignorada.");
            return;
        }

        var payload = {
            ep_cid: cid,
            action: actionName,
            metadata: metadata || {}
        };

        // Faz o request blind de forma segura (nāo trava a page)
        fetch(API_URL, {
            method: 'POST',
            credentials: 'omit', // Sem cookies para evitar bloqueio ITP rigoroso
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        }).then(function(res) {
            return res.json();
        }).then(function(data) {
            if (data.status === 'recorded') {
                console.log("[Encurtou Tracker] Conversão ("+actionName+") capturada sucesso!");
            }
        }).catch(function(err) {});
    };

    // Auto Init
    init();

})();
