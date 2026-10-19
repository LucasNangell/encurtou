(function () {
    'use strict';

    const sections = Array.from(document.querySelectorAll('.marketing-section'));
    const railList = document.getElementById('marketingRailList');
    const progressBar = document.getElementById('marketingProgressBar');
    const prevBtn = document.getElementById('marketingPrevBtn');
    const nextBtn = document.getElementById('marketingNextBtn');
    const prevMobile = document.getElementById('marketingPrevMobile');
    const nextMobile = document.getElementById('marketingNextMobile');
    const mobileLabel = document.getElementById('marketingMobileLabel');
    const backToTopBtn = document.getElementById('marketingBackToTop');

    if (!sections.length) {
        return;
    }

    const reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    let activeIndex = 0;
    let railButtons = [];

    function clamp(index) {
        return Math.max(0, Math.min(index, sections.length - 1));
    }

    function isTypingTarget(target) {
        if (!target) {
            return false;
        }
        const tag = (target.tagName || '').toLowerCase();
        return tag === 'input' || tag === 'textarea' || tag === 'select' || target.isContentEditable;
    }

    function scrollToSection(index) {
        const sec = sections[clamp(index)];
        if (!sec) {
            return;
        }
        sec.scrollIntoView({
            behavior: reduceMotion ? 'auto' : 'smooth',
            block: 'start'
        });
    }

    function setActive(index) {
        activeIndex = clamp(index);
        sections.forEach((section, idx) => {
            const isActive = idx === activeIndex;
            section.classList.toggle('marketing-is-active', isActive);
            section.setAttribute('aria-current', isActive ? 'true' : 'false');
        });

        railButtons.forEach((button, idx) => {
            const isActive = idx === activeIndex;
            button.classList.toggle('is-active', isActive);
            button.setAttribute('aria-current', isActive ? 'true' : 'false');
        });

        if (mobileLabel) {
            mobileLabel.textContent = sections[activeIndex].dataset.nav || ('Seção ' + (activeIndex + 1));
        }

        if (progressBar) {
            const progress = ((activeIndex + 1) / sections.length) * 100;
            progressBar.style.width = progress.toFixed(2) + '%';
        }
    }

    function buildRail() {
        if (!railList) {
            return;
        }

        sections.forEach((section, idx) => {
            const li = document.createElement('li');
            const button = document.createElement('button');
            const number = document.createElement('b');
            const label = document.createElement('span');

            number.textContent = String(idx + 1).padStart(2, '0');
            label.textContent = section.dataset.nav || ('Seção ' + (idx + 1));

            button.type = 'button';
            button.className = 'marketing-rail-link';
            button.setAttribute('aria-label', 'Ir para ' + label.textContent);
            button.appendChild(number);
            button.appendChild(label);
            button.addEventListener('click', function () {
                scrollToSection(idx);
            });

            li.appendChild(button);
            railList.appendChild(li);
        });

        railButtons = Array.from(railList.querySelectorAll('.marketing-rail-link'));
    }

    function bindNavigation() {
        const goPrev = function () { scrollToSection(activeIndex - 1); };
        const goNext = function () { scrollToSection(activeIndex + 1); };

        if (prevBtn) prevBtn.addEventListener('click', goPrev);
        if (nextBtn) nextBtn.addEventListener('click', goNext);
        if (prevMobile) prevMobile.addEventListener('click', goPrev);
        if (nextMobile) nextMobile.addEventListener('click', goNext);

        if (backToTopBtn) {
            backToTopBtn.addEventListener('click', function () {
                scrollToSection(0);
            });
        }

        document.addEventListener('keydown', function (event) {
            if (isTypingTarget(event.target)) return;

            const key = event.key;
            if (key === 'ArrowDown' || key === 'PageDown') {
                event.preventDefault(); goNext();
            } else if (key === 'ArrowUp' || key === 'PageUp') {
                event.preventDefault(); goPrev();
            } else if (key === 'Home') {
                event.preventDefault(); scrollToSection(0);
            } else if (key === 'End') {
                event.preventDefault(); scrollToSection(sections.length - 1);
            }
        });
    }

    function observeSections() {
        const observer = new IntersectionObserver(function (entries) {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    const idx = sections.indexOf(entry.target);
                    if (idx >= 0) setActive(idx);
                }
            });
        }, {
            threshold: 0.35,
            rootMargin: '-10% 0px -45% 0px'
        });

        sections.forEach((section) => observer.observe(section));
    }

    function initReveal() {
        const revealItems = Array.from(document.querySelectorAll('.marketing-reveal'));
        if (!revealItems.length) return;

        if (reduceMotion) {
            revealItems.forEach((item) => item.classList.add('is-visible'));
            return;
        }

        const observer = new IntersectionObserver(function (entries) {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.15 });

        revealItems.forEach((item) => observer.observe(item));
    }

    function formatPrice(value) {
        const n = Number(value);
        if (!Number.isFinite(n) || n <= 0) return 'Sob consulta';
        return n.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL', minimumFractionDigits: 2 });
    }

    function loadPlans() {
        const grid = document.getElementById('marketingPlansGrid');
        if (!grid) return;

        // Fallbacks atualizados com a copy persuasiva
        const fallbackPlans = [
            { id: 1, name: 'Free', monthly_price: 0, annual_price: 0 },
            { id: 2, name: 'Campanha Base', monthly_price: 49.9, annual_price: 479 },
            { id: 3, name: 'War Room (Pro)', monthly_price: 149.9, annual_price: 1439 },
            { id: 5, name: 'Founder Edition', monthly_price: 149.9, annual_price: 1439 }
        ];

        function render(plans) {
            grid.innerHTML = plans.map((plan) => {
                const name = String(plan.name || ('Plano ' + plan.id));
                const normalizedName = name.toLowerCase();
                const isFounder = normalizedName.includes('founder') || Number(plan.id) === 5;
                const isFree = Number(plan.id) === 1;
                const monthly = formatPrice(plan.monthly_price);
                const annual = formatPrice(plan.annual_price);
                const planClass = isFounder ? 'marketing-plan marketing-plan-founder' : 'marketing-plan';

                let description = '';
                if (isFounder) {
                    description = '<span style="color:#fde68a; font-weight:bold;">Preço congelado — sem reajustes futuros.</span><br>Assinatura mensal: <strong>' + monthly + '</strong><br>Anual (Economia): <strong>' + annual + '</strong><br><em>Apenas para os primeiros 100 gabinetes.</em>';
                } else if (isFree) {
                    description = 'Teste a tecnologia básica em ações locais menores sem custo.';
                } else {
                    description = 'Assinatura mensal: <strong>' + monthly + '</strong><br>Anual (Economia): <strong>' + annual + '</strong>';
                }

                return '<article class="' + planClass + '">'
                    + '<h3>' + name + '</h3>'
                    + '<p>' + description + '</p>'
                    + '</article>';
            }).join('');
        }

        fetch('/billing/api.php?action=plans', { cache: 'no-store' })
            .then((res) => {
                if (!res.ok) throw new Error('plans_fetch_failed');
                return res.json();
            })
            .then((data) => {
                if (data && Array.isArray(data.plans) && data.plans.length) {
                    render(data.plans);
                    return;
                }
                render(fallbackPlans);
            })
            .catch(() => render(fallbackPlans));
    }

    function bindPlanosConversionTracking() {
        document.querySelectorAll('a.marketing-cta-planos').forEach((el) => {
            el.addEventListener('click', () => {
                if (window.Encurtou && typeof window.Encurtou.track === 'function') {
                    window.Encurtou.track('planos');
                }
            });
        });
    }

    function bindContactForm() {
        const form = document.getElementById('marketingContactForm');
        if (!form) {
            return;
        }

        const nameInput = document.getElementById('marketingContactName');
        const emailInput = document.getElementById('marketingContactEmail');
        const whatsappInput = document.getElementById('marketingContactWhatsapp');
        const messageInput = document.getElementById('marketingContactMessage');
        const submitBtn = document.getElementById('marketingContactSubmit');
        const whatsappBtn = document.getElementById('marketingContactWhatsappBtn');
        const statusEl = document.getElementById('marketingContactStatus');
        const whatsappNumber = '5561984712888';
        const successMessage = 'Obrigado! A equipe da Encurtou.pro já recebeu sua mensagem. Em breve você receberá nosso contato.';

        function setStatus(message, kind) {
            if (!statusEl) return;
            statusEl.textContent = message || '';
            statusEl.classList.remove('is-error', 'is-success');
            if (kind === 'error') statusEl.classList.add('is-error');
            if (kind === 'success') statusEl.classList.add('is-success');
        }

        function buildWhatsappText() {
            const name = (nameInput && nameInput.value ? nameInput.value : '').trim();
            const message = (messageInput && messageInput.value ? messageInput.value : '').trim();
            const safeName = name || 'não informado';
            const safeMessage = message || 'Olá! Quero entender melhor como funciona a Encurtou.pro.';
            return `Olá, equipe Encurtou.pro! Meu nome é ${safeName}. Minha solicitação: ${safeMessage}`;
        }

        if (whatsappBtn) {
            whatsappBtn.addEventListener('click', function () {
                const text = buildWhatsappText();
                const url = 'https://wa.me/' + whatsappNumber + '?text=' + encodeURIComponent(text);
                window.open(url, '_blank', 'noopener,noreferrer');
            });
        }

        form.addEventListener('submit', async function (event) {
            event.preventDefault();
            const name = (nameInput && nameInput.value ? nameInput.value : '').trim();
            const email = (emailInput && emailInput.value ? emailInput.value : '').trim();
            const whatsapp = (whatsappInput && whatsappInput.value ? whatsappInput.value : '').trim();
            const message = (messageInput && messageInput.value ? messageInput.value : '').trim();

            if (!name || !email || !message) {
                setStatus('Preencha nome, e-mail e texto da solicitação.', 'error');
                return;
            }

            if (submitBtn) submitBtn.disabled = true;
            setStatus('Enviando sua mensagem...', '');

            try {
                const res = await fetch('/marketing_form_submit.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ name, email, whatsapp, message })
                });
                const data = await res.json();
                if (!res.ok || !data.success) {
                    setStatus((data && data.message) ? data.message : 'Não foi possível enviar agora. Tente novamente.', 'error');
                    return;
                }
                form.reset();
                setStatus(successMessage, 'success');
            } catch (err) {
                setStatus('Erro de conexão ao enviar formulário. Tente novamente em instantes.', 'error');
            } finally {
                if (submitBtn) submitBtn.disabled = false;
            }
        });
    }

    buildRail();
    bindNavigation();
    observeSections();
    initReveal();
    loadPlans();
    bindPlanosConversionTracking();
    bindContactForm();
    setActive(0);
})();