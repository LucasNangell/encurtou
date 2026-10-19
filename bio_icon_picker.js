(function (global) {
    'use strict';

    const ICON_RAW = `fas fa-house|house
fas fa-home|home
fas fa-user|user
fas fa-users|users
fas fa-user-group|user group
fas fa-user-plus|user plus
fas fa-user-check|user check
fas fa-user-xmark|user xmark
fas fa-user-minus|user minus
fas fa-user-gear|user gear
fas fa-person|person
fas fa-person-dress|person dress
fas fa-person-walking|person walking
fas fa-people-group|people group
fas fa-people-roof|people roof
fas fa-child|child
fas fa-baby|baby
fas fa-wheelchair|wheelchair
fas fa-heart|heart
fas fa-star|star
fas fa-certificate|certificate
fas fa-award|award
fas fa-trophy|trophy
fas fa-medal|medal
fas fa-ribbon|ribbon
fas fa-bookmark|bookmark
fas fa-flag|flag
fas fa-banner|banner
fas fa-book|book
fas fa-books|books
fas fa-newspaper|newspaper
fas fa-file|file
fas fa-file-lines|file lines
fas fa-file-pdf|file pdf
fas fa-file-word|file word
fas fa-file-excel|file excel
fas fa-file-image|file image
fas fa-file-video|file video
fas fa-file-audio|file audio
fas fa-file-code|file code
fas fa-file-zipper|file zipper
fas fa-folder|folder
fas fa-folder-open|folder open
fas fa-box|box
fas fa-archive|archive
fas fa-inbox|inbox
fas fa-envelope|envelope
fas fa-envelope-open|envelope open
fas fa-paper-plane|paper plane
fas fa-comment|comment
fas fa-comments|comments
fas fa-message|message
fas fa-sms|sms
fas fa-phone|phone
fas fa-phone-volume|phone volume
fas fa-mobile-screen|mobile screen
fas fa-mobile-screen-button|mobile screen button
fas fa-headset|headset
fas fa-microphone|microphone
fas fa-video|video
fas fa-camera|camera
fas fa-camera-retro|camera retro
fas fa-image|image
fas fa-images|images
fas fa-photo-film|photo film
fas fa-clapperboard|clapperboard
fas fa-play|play
fas fa-pause|pause
fas fa-stop|stop
fas fa-forward|forward
fas fa-backward|backward
fas fa-volume-high|volume high
fas fa-volume-low|volume low
fas fa-volume-off|volume off
fas fa-volume-xmark|volume xmark
fas fa-music|music
fas fa-headphones|headphones
fas fa-radio|radio
fas fa-film|film
fas fa-compact-disc|compact disc
fas fa-bell|bell
fas fa-bell-slash|bell slash
fas fa-megaphone|megaphone
fas fa-bullhorn|bullhorn
fas fa-rss|rss
fas fa-wifi|wifi
fas fa-signal|signal
fas fa-tower-broadcast|tower broadcast
fas fa-satellite-dish|satellite dish
fas fa-link|link
fas fa-chain|chain
fas fa-paperclip|paperclip
fas fa-clipboard|clipboard
fas fa-clipboard-list|clipboard list
fas fa-clipboard-check|clipboard check
fas fa-note-sticky|note sticky
fas fa-pen|pen
fas fa-pencil|pencil
fas fa-pen-to-square|pen to square
fas fa-eraser|eraser
fas fa-print|print
fas fa-scissors|scissors
fas fa-paintbrush|paintbrush
fas fa-palette|palette
fas fa-droplet|droplet
fas fa-eye|eye
fas fa-eye-slash|eye slash
fas fa-ear|ear
fas fa-glasses|glasses
fas fa-mask-theater|mask theater
fas fa-icons|icons
fas fa-wand-magic-sparkles|wand magic sparkles
fas fa-sparkles|sparkles
fas fa-fire|fire
fas fa-flame|flame
fas fa-bolt|bolt
fas fa-lightbulb|lightbulb
fas fa-sun|sun
fas fa-moon|moon
fas fa-cloud|cloud
fas fa-cloud-sun|cloud sun
fas fa-cloud-moon|cloud moon
fas fa-cloud-rain|cloud rain
fas fa-cloud-bolt|cloud bolt
fas fa-snowflake|snowflake
fas fa-wind|wind
fas fa-tornado|tornado
fas fa-umbrella|umbrella
fas fa-thermometer|thermometer
fas fa-bottle-water|bottle water
fas fa-mug-saucer|mug saucer
fas fa-wine-glass|wine glass
fas fa-utensils|utensils
fas fa-fork-knife|fork knife
fas fa-pizza-slice|pizza slice
fas fa-fish|fish
fas fa-ice-cream|ice cream
fas fa-carrot|carrot
fas fa-apple-whole|apple whole
fas fa-pepper-hot|pepper hot
fas fa-bread-slice|bread slice
fas fa-cookie|cookie
fas fa-cake-candles|cake candles
fas fa-candy-cane|candy cane
fas fa-gift|gift
fas fa-shopping-cart|shopping cart
fas fa-shopping-bag|shopping bag
fas fa-basket-shopping|basket shopping
fas fa-store|store
fas fa-receipt|receipt
fas fa-credit-card|credit card
fas fa-money-bill|money bill
fas fa-money-bill-wave|money bill wave
fas fa-coins|coins
fas fa-piggy-bank|piggy bank
fas fa-wallet|wallet
fas fa-landmark|landmark
fas fa-building|building
fas fa-building-columns|building columns
fas fa-bank|bank
fas fa-industry|industry
fas fa-warehouse|warehouse
fas fa-truck|truck
fas fa-van|van
fas fa-car|car
fas fa-bus|bus
fas fa-taxi|taxi
fas fa-motorcycle|motorcycle
fas fa-bicycle|bicycle
fas fa-ship|ship
fas fa-anchor|anchor
fas fa-plane|plane
fas fa-helicopter|helicopter
fas fa-rocket|rocket
fas fa-satellite|satellite
fas fa-map|map
fas fa-map-pin|map pin
fas fa-location-dot|location dot
fas fa-location-crosshairs|location crosshairs
fas fa-compass|compass
fas fa-directions|directions
fas fa-globe|globe
fas fa-earth-americas|earth americas
fas fa-language|language
fas fa-passport|passport
fas fa-suitcase|suitcase
fas fa-hotel|hotel
fas fa-bed|bed
fas fa-house-chimney|house chimney
fas fa-door-open|door open
fas fa-key|key
fas fa-lock|lock
fas fa-unlock|unlock
fas fa-shield|shield
fas fa-bug|bug
fas fa-skull|skull
fas fa-ghost|ghost
fas fa-robot|robot
fas fa-paw-clipboard|paw clipboard
fas fa-fish-fins|fish fins
fas fa-frog|frog
fas fa-leaf|leaf
fas fa-tree|tree
fas fa-seedling|seedling
fas fa-clover|clover
fas fa-wheat-awn|wheat awn
fas fa-tractor|tractor
fas fa-barn|barn
fas fa-mountain|mountain
fas fa-water|water
fas fa-ladder|ladder
fas fa-fire-extinguisher|fire extinguisher
fas fa-kit-medical|kit medical
fas fa-syringe|syringe
fas fa-pills|pills
fas fa-bandage|bandage
fas fa-stethoscope|stethoscope
fas fa-heart-pulse|heart pulse
fas fa-hospital|hospital
fas fa-briefcase|briefcase
fas fa-handshake|handshake
fas fa-scale-balanced|scale balanced
fas fa-gavel|gavel
fas fa-chart-line|chart line
fas fa-chart-bar|chart bar
fas fa-chart-pie|chart pie
fas fa-diagram-project|diagram project
fas fa-network-wired|network wired
fas fa-sitemap|sitemap
fas fa-layer-group|layer group
fas fa-cubes|cubes
fas fa-box-open|box open
fas fa-pallet|pallet
fas fa-tasks|tasks
fas fa-list-check|list check
fas fa-circle-question|circle question
fas fa-circle-info|circle info
fas fa-circle-exclamation|circle exclamation
fas fa-triangle-exclamation|triangle exclamation
fas fa-octagon-xmark|octagon xmark
fas fa-ban|ban
fas fa-xmark|xmark
fas fa-check|check
fas fa-plus|plus
fas fa-minus|minus
fas fa-percent|percent
fas fa-hashtag|hashtag
fas fa-at|at
fas fa-dollar-sign|dollar sign
fas fa-euro-sign|euro sign
fas fa-calculator|calculator
fas fa-filter|filter
fas fa-arrows-rotate|arrows rotate
fas fa-share|share
fas fa-retweet|retweet
fas fa-magnifying-glass|magnifying glass
fas fa-magnifying-glass-plus|magnifying glass plus
fas fa-flask|flask
fas fa-vial|vial
fas fa-dna|dna
fas fa-hands-praying|hands praying
fas fa-thumbs-up|thumbs up
fas fa-thumbs-down|thumbs down
fas fa-face-smile|face smile
fas fa-face-frown|face frown
fas fa-bomb|bomb
fas fa-biohazard|biohazard
fas fa-person-running|person running
fas fa-person-biking|person biking
fas fa-football|football
fas fa-basketball|basketball
fas fa-gamepad|gamepad
fas fa-puzzle-piece|puzzle piece
fas fa-scroll|scroll
fas fa-peace|peace
fas fa-truck-plane|truck plane
fas fa-ferry-sailboat|ferry sailboat
fas fa-sailboat|sailboat
fas fa-bridge-water|bridge water
fab fa-instagram|instagram brand marca
fab fa-facebook|facebook brand marca
fab fa-tiktok|tiktok brand marca
fab fa-youtube|youtube brand marca
fab fa-linkedin|linkedin brand marca
fab fa-whatsapp|whatsapp brand marca
fab fa-telegram|telegram brand marca
fab fa-x-twitter|x twitter brand marca
fab fa-threads|threads brand marca
fab fa-pinterest|pinterest brand marca
fab fa-snapchat|snapchat brand marca
fab fa-reddit|reddit brand marca
fab fa-discord|discord brand marca
fab fa-slack|slack brand marca
fab fa-github|github brand marca
fab fa-gitlab|gitlab brand marca
fab fa-bitbucket|bitbucket brand marca
fab fa-codepen|codepen brand marca
fab fa-stack-overflow|stack overflow brand marca
fab fa-npm|npm brand marca
fab fa-node-js|node js brand marca
fab fa-react|react brand marca
fab fa-vuejs|vuejs brand marca
fab fa-php|php brand marca
fab fa-laravel|laravel brand marca
fab fa-wordpress|wordpress brand marca
fab fa-shopify|shopify brand marca
fab fa-stripe|stripe brand marca
fab fa-paypal|paypal brand marca
fab fa-amazon|amazon brand marca
fab fa-google|google brand marca
fab fa-apple|apple brand marca
fab fa-microsoft|microsoft brand marca
fab fa-windows|windows brand marca
fab fa-android|android brand marca
fab fa-linux|linux brand marca
fab fa-docker|docker brand marca
fab fa-aws|aws brand marca
fab fa-cloudflare|cloudflare brand marca
fab fa-firefox|firefox brand marca
fab fa-chrome|chrome brand marca
fab fa-spotify|spotify brand marca
fab fa-soundcloud|soundcloud brand marca
fab fa-vimeo|vimeo brand marca
fab fa-twitch|twitch brand marca
fab fa-dribbble|dribbble brand marca
fab fa-behance|behance brand marca
fab fa-figma|figma brand marca
fab fa-medium|medium brand marca
fab fa-dev|dev brand marca
fab fa-yelp|yelp brand marca
fab fa-airbnb|airbnb brand marca
fab fa-uber|uber brand marca
fab fa-lyft|lyft brand marca
fab fa-mastodon|mastodon brand marca
fab fa-bluesky|bluesky brand marca
fab fa-cc-visa|cc visa brand marca
fab fa-cc-paypal|cc paypal brand marca
fab fa-cc-amex|cc amex brand marca
`;

    const PT_SLUG = {
        house: 'casa inicio', home: 'casa', heart: 'coracao amor curtir', star: 'estrela favorito', user: 'usuario perfil pessoa',
        users: 'pessoas equipe grupo', envelope: 'email correio mensagem', 'paper-plane': 'enviar', phone: 'telefone ligar',
        'mobile-screen-button': 'celular smartphone', comment: 'comentario chat', link: 'ligacao url', 'location-dot': 'mapa pin local',
        briefcase: 'trabalho portfolio negocio', building: 'empresa predio', bank: 'banco dinheiro', 'credit-card': 'cartao pagamento',
        'money-bill': 'dinheiro pagamento', gift: 'presente', truck: 'entrega frete', plane: 'viagem aviao', car: 'carro veiculo',
        bus: 'onibus transporte', bicycle: 'bike', ship: 'barco navio', anchor: 'ancora', camera: 'foto', image: 'imagem foto',
        video: 'video filmagem', music: 'musica som', play: 'reproduzir', pause: 'pausar', hospital: 'saude medico',
        pills: 'remedio farmacia', stethoscope: 'consultorio', football: 'futebol americano', basketball: 'basquete',
        gamepad: 'jogo games', 'puzzle-piece': 'quebra cabeca', leaf: 'natureza ecologia', tree: 'arvore', mountain: 'montanha',
        fish: 'peixe', 'pizza-slice': 'pizza comida', utensils: 'restaurante comida', 'wine-glass': 'vinho bebida',
        shield: 'seguranca', lock: 'cadeado seguranca', key: 'chave', fire: 'fogo quente', bolt: 'raio energia',
        lightbulb: 'ideia lampada', check: 'ok confirmar', xmark: 'fechar cancelar', plus: 'adicionar', minus: 'remover',
        'magnifying-glass': 'busca pesquisa lupa', 'user-gear': 'configuracao engrenagem', pen: 'editar escrever',
        share: 'compartilhar', eye: 'ver visualizar', instagram: 'foto rede social', facebook: 'meta', tiktok: 'video curto',
        youtube: 'video canal', linkedin: 'profissional carreira', whatsapp: 'zap conversa', telegram: 'mensagem',
        'x-twitter': 'x rede twitter', github: 'codigo repositorio', spotify: 'musica streaming', paypal: 'pagamento',
        amazon: 'loja ecommerce', google: 'busca', apple: 'marca', docker: 'container dev', react: 'frontend',
        wordpress: 'blog site', shopify: 'loja virtual', 'cc-visa': 'cartao bandeira', 'cc-paypal': 'pagamento'
    };

    function stripAccents(s) {
        return String(s || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
    }

    function parseIcons(raw) {
        const icons = [];
        raw.trim().split('\n').forEach(function (line) {
            const p = line.indexOf('|');
            if (p === -1) return;
            const c = line.slice(0, p).trim();
            const base = line.slice(p + 1).trim();
            const m = /fa-([a-z0-9-]+)$/i.exec(c);
            const slug = m ? m[1] : '';
            const pt = PT_SLUG[slug] || '';
            icons.push({ c: c, labels: stripAccents(base + ' ' + pt + ' ' + c) });
        });
        return icons;
    }

    const ICONS = parseIcons(ICON_RAW);
    let targetInput = null;

    function esc(s) {
        return String(s || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
    }

    function ensureModal() {
        if (document.getElementById('modalBioIconPicker')) return;
        var ov = document.getElementById('modalOverlay');
        if (!ov) return;
        var div = document.createElement('div');
        div.id = 'modalBioIconPicker';
        div.className = 'modal-content bio-icon-picker-modal hidden';
        div.onclick = function (e) { e.stopPropagation(); };
        div.innerHTML = '<div class="modal-header">' +
            '<h3>Escolher ícone</h3>' +
            '<button type="button" onclick="closeModals()" class="close-btn" aria-label="Fechar"><i class="fas fa-times"></i></button>' +
            '</div>' +
            '<div class="bio-icon-picker-toolbar">' +
            '<input type="search" id="bio-icon-picker-search" class="bio-icon-picker-search" placeholder="Pesquisar (ex: coração, whatsapp, mapa, seta)..." autocomplete="off">' +
            '<span id="bio-icon-picker-count" class="bio-icon-picker-count"></span>' +
            '</div>' +
            '<div id="bio-icon-picker-grid" class="bio-icon-picker-grid" role="listbox" aria-label="Ícones"></div>' +
            '<p class="bio-icon-picker-hint">Font Awesome 6 (CDN). Toque no ícone para preencher o campo com a classe CSS.</p>';
        ov.appendChild(div);
        document.getElementById('bio-icon-picker-search').addEventListener('input', renderGrid);
    }

    var MAX_SHOW = 450;

    function renderGrid() {
        var inp = document.getElementById('bio-icon-picker-search');
        var grid = document.getElementById('bio-icon-picker-grid');
        var stat = document.getElementById('bio-icon-picker-count');
        if (!grid) return;
        var q = stripAccents((inp && inp.value) || '').trim();
        var list = ICONS;
        if (q) {
            list = ICONS.filter(function (x) { return x.labels.indexOf(q) !== -1; });
        }
        var total = list.length;
        if (list.length > MAX_SHOW) list = list.slice(0, MAX_SHOW);
        grid.innerHTML = list.map(function (x) {
            return '<button type="button" class="bio-icon-picker-cell" role="option" title="' + esc(x.c) + '" data-icon="' + esc(x.c) + '"><i class="' + esc(x.c) + '" aria-hidden="true"></i></button>';
        }).join('');
        grid.querySelectorAll('.bio-icon-picker-cell').forEach(function (btn) {
            btn.addEventListener('click', function () {
                pickIcon(btn.getAttribute('data-icon'));
            });
        });
        if (stat) {
            stat.textContent = total + (q ? ' encontrado(s)' : ' ícones');
            if (total > MAX_SHOW) stat.textContent += ' (mostrando ' + MAX_SHOW + ')';
        }
    }

    function pickIcon(cls) {
        if (targetInput) {
            targetInput.value = cls;
            targetInput.dispatchEvent(new Event('input', { bubbles: true }));
            targetInput.dispatchEvent(new Event('change', { bubbles: true }));
        }
        targetInput = null;
        if (typeof closeModals === 'function') closeModals();
    }

    global.openBioIconPicker = function (triggerBtn) {
        var wrap = triggerBtn && triggerBtn.closest && triggerBtn.closest('.bio-icon-field-wrap');
        var input = wrap && wrap.querySelector('.bio-icon-class-input');
        if (!input) return;
        targetInput = input;
        ensureModal();
        var search = document.getElementById('bio-icon-picker-search');
        if (search) search.value = '';
        renderGrid();
        if (typeof openModal === 'function') openModal('modalBioIconPicker');
        setTimeout(function () { if (search) { search.focus(); search.select(); } }, 80);
    };
})(typeof window !== 'undefined' ? window : this);