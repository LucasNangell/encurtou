<?php
declare(strict_types=1);

$ctaPlanosUrl = '/planos';
$ctaWhatsappUrl = 'https://wa.me/5547984712888';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <script>
        (function () {
            var storageKey = 'enc_theme_preference';
            var root = document.documentElement;
            var stored = null;
            try { stored = localStorage.getItem(storageKey); } catch (e) { stored = null; }
            var theme = (stored === 'light' || stored === 'dark') ? stored : 'light';
            root.setAttribute('data-theme', theme);
        })();
    </script>
    <meta name="description" content="encurtou.pro: Inteligência de Dados e Segurança Técnica para Campanhas e Mandatos. Evite desperdício de verba com QR Codes dinâmicos e rastreáveis.">
    <title>encurtou.pro | Inteligência e Segurança para Gabinetes</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Sora:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/marketing.css?v=3.0">
</head>
<body class="marketing-page">
    <a class="marketing-skip-link" href="#marketing-main">Pular para o conteúdo principal</a>

    <div class="marketing-progress" aria-hidden="true">
        <span id="marketingProgressBar"></span>
    </div>

    <header class="marketing-header" role="banner">
        <div class="marketing-header-inner">
            <a href="/" class="marketing-logo-link" aria-label="Ir para a página inicial">
                <div class="marketing-site-logo" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" xml:space="preserve" width="200px" height="50px" viewBox="0 0 14287 3882">
                        <g id="Camada_x0020_1">
                            <g id="_2877010636624">
                                <text x="2934" y="2972" class="brand-logo-text brand-logo-text-main">encurtou</text>
                                <text x="11077" y="2972" class="brand-logo-text brand-logo-text-suffix">.pro</text>
                                <line class="brand-logo-mark" x1="572" y1="3729" x2="2112" y2="2189"></line>
                                <line class="brand-logo-mark" x1="153" y1="2645" x2="974" y2="1824"></line>
                                <circle class="brand-logo-mark brand-logo-mark-fill" cx="975" cy="313" r="302"></circle>
                            </g>
                        </g>
                    </svg>
                </div>
            </a>
        </div>
    </header>

    <nav class="marketing-rail" aria-label="Navegação de seções da apresentação">
        <button type="button" class="marketing-rail-btn" id="marketingPrevBtn" aria-label="Seção anterior">
            <i class="fas fa-arrow-up" aria-hidden="true"></i>
        </button>
        <ol class="marketing-rail-list" id="marketingRailList"></ol>
        <button type="button" class="marketing-rail-btn" id="marketingNextBtn" aria-label="Próxima seção">
            <i class="fas fa-arrow-down" aria-hidden="true"></i>
        </button>
    </nav>

    <main id="marketing-main" class="marketing-main">
        <section class="marketing-section marketing-section-hero marketing-is-active" id="sec-hero" data-nav="Abertura" aria-labelledby="hero-title">
            <div class="marketing-glow marketing-glow-a" aria-hidden="true"></div>
            <div class="marketing-shell">
                <p class="marketing-kicker marketing-reveal">Blindagem técnica para o período eleitoral</p>
                <h1 id="hero-title" class="marketing-h1 marketing-reveal">
                    Inteligência de dados e segurança para a campanha do seu mandato.
                </h1>
                <p class="marketing-lead marketing-reveal">
                    A encurtou.pro transforma santinhos, banners e cartões em sensores de engajamento. Nunca mais perca materiais por causa de um QR Code gratuito que expirou ou link que mudou.
                </p>
                <div class="marketing-founder-hero marketing-reveal">
                    <strong>VAGAS LIMITADAS: Founder Edition</strong> · somente para os <strong>100 primeiros gabinetes</strong> · <strong>preço congelado para sempre</strong> · assinatura com zero reajustes enquanto for membro.
                </div>
                <div class="marketing-cta-row marketing-reveal">
                    <a class="marketing-btn marketing-btn-primary marketing-btn-lg marketing-cta-planos" href="<?php echo htmlspecialchars($ctaPlanosUrl, ENT_QUOTES, 'UTF-8'); ?>">Garantir minha vaga Founder</a>
                    <a class="marketing-btn marketing-btn-outline marketing-btn-lg" href="<?php echo htmlspecialchars($ctaWhatsappUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">Falar com Estrategista (WhatsApp)</a>
                </div>
                <ul class="marketing-pill-list marketing-reveal" aria-label="Principais ganhos para o gabinete">
                    <li>Segurança de Verba</li>
                    <li>Analytics Territorial</li>
                    <li>Lead Gen (WhatsApp)</li>
                    <li>QR Codes Dinâmicos</li>
                </ul>
            </div>
        </section>

        <section class="marketing-section" id="sec-dor" data-nav="O Risco" aria-labelledby="dor-title">
            <div class="marketing-shell">
                <p class="marketing-kicker marketing-reveal">1. O Risco Silencioso</p>
                <h2 id="dor-title" class="marketing-h2 marketing-reveal">O perigo invisível na sua verba de comunicação de rua.</h2>
                
                <div class="marketing-alert-box marketing-reveal">
                    <h3><i class="fas fa-handshake"></i> Complemento perfeito para sua Agência</h3>
                    <p>Nós não substituímos o trabalho da sua agência de marketing ou comunicação. Pelo contrário: entregamos a eles a infraestrutura tecnológica de links e as métricas do mundo físico que a maioria das agências não possui internamente.</p>
                </div>

                <div class="marketing-grid marketing-grid-3">
                    <article class="marketing-card marketing-reveal">
                        <h3><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> QR Codes Expirados</h3>
                        <p>Geradores gratuitos expiram do nada ou limitam acessos. Se o link cair em plena campanha, todo o material impresso vira lixo instantaneamente.</p>
                    </article>
                    <article class="marketing-card marketing-reveal">
                        <h3><i class="fa-solid fa-eye-slash" aria-hidden="true"></i> O Papel é "Cego"</h3>
                        <p>Foram distribuídos 100 mil panfletos, mas em quais cidades eles realmente geraram interesse? Sem nossa tecnologia, o gabinete atua no escuro.</p>
                    </article>
                    <article class="marketing-card marketing-reveal">
                        <h3><i class="fa-solid fa-users-slash" aria-hidden="true"></i> Perda de Base (Leads)</h3>
                        <p>Levar o eleitor para uma página genérica não ganha voto. É preciso captar o contato dele de forma estruturada no momento do scan.</p>
                    </article>
                </div>
            </div>
        </section>

        <section class="marketing-section" id="sec-solucao" data-nav="Inteligência" aria-labelledby="solucao-title">
            <div class="marketing-shell">
                <p class="marketing-kicker marketing-reveal">2. A Solução Estratégica</p>
                <h2 id="solucao-title" class="marketing-h2 marketing-reveal">Uma verdadeira "War Room" digital para o mandato.</h2>
                <div class="marketing-grid marketing-grid-3">
                    <article class="marketing-card marketing-reveal">
                        <h3><i class="fa-solid fa-qrcode" aria-hidden="true"></i> QR Codes 100% Dinâmicos</h3>
                        <p>Imprima uma vez e mude o destino do link sempre que precisar. Corrija erros na rota da campanha sem gastar 1 real a mais em gráfica.</p>
                    </article>
                    <article class="marketing-card marketing-reveal">
                        <h3><i class="fa-brands fa-whatsapp" aria-hidden="true"></i> Conversão Direta via WhatsApp</h3>
                        <p>O eleitor escaneia e o celular já abre o WhatsApp com uma mensagem pré-preenchida (ex: "Quero apoiar o Deputado"). Captação imediata da base.</p>
                    </article>
                    <article class="marketing-card marketing-reveal">
                        <h3><i class="fa-solid fa-bullseye" aria-hidden="true"></i> Atribuição de Performance</h3>
                        <p>Crie links diferentes para o "Santinho A" e "Banner B" e descubra através do nosso painel qual material está trazendo mais engajamento real.</p>
                    </article>
                </div>
            </div>
        </section>

        <section class="marketing-section" id="sec-cidades" data-nav="Mapeamento" aria-labelledby="cidades-title">
            <div class="marketing-shell">
                <p class="marketing-kicker marketing-reveal">3. Inteligência Territorial</p>
                <h2 id="cidades-title" class="marketing-h2 marketing-reveal">Descubra exatamente onde estão os seus votos.</h2>
                <p class="marketing-lead marketing-reveal">
                    A análise de acessos por cidade transforma QR Codes em radares. Em vez de decidir a agenda do candidato com base em "achismos", o coordenador passa a priorizar o território com base no interesse real da população.
                </p>
                <div class="marketing-grid marketing-grid-2">
                    <article class="marketing-panel marketing-reveal">
                        <h3><i class="fa-solid fa-map-location-dot" aria-hidden="true"></i> Ajuste de Rota Eleitoral</h3>
                        <p>Identifique quais municípios e regiões administrativas estão acessando mais os links e envie o candidato para onde já existe tração orgânica.</p>
                    </article>
                    <article class="marketing-panel marketing-reveal">
                        <h3><i class="fa-solid fa-chart-pie" aria-hidden="true"></i> Otimização de Distribuição</h3>
                        <p>Pare de enviar material impresso para regiões que não engajam. Redirecione equipes de rua focando na eficiência dos dados.</p>
                    </article>
                </div>
            </div>
        </section>

        <section class="marketing-section" id="sec-form" data-nav="Páginas e Forms" aria-labelledby="form-title">
            <div class="marketing-shell">
                <p class="marketing-kicker marketing-reveal">4. Captação Estruturada</p>
                <h2 id="form-title" class="marketing-h2 marketing-reveal">Hub Digital e coleta de demandas de forma automatizada.</h2>
                <div class="marketing-form-layout">
                    <article class="marketing-panel marketing-reveal">
                        <h3>O seu Escritório de Bolso</h3>
                        <ul class="marketing-checklist">
                            <li><strong>Bio-pages ultraleves:</strong> Carregam rápido mesmo no 4G oscilante da rua.</li>
                            <li><strong>Centralização:</strong> Um único link com redes, leis aprovadas e WhatsApp.</li>
                            <li><strong>Formulários CRM:</strong> Organize demandas de bairros e lideranças direto na plataforma, gerando um banco de dados poderoso.</li>
                        </ul>
                    </article>
                    <article class="marketing-form-example marketing-reveal" aria-label="Exemplo de formulário real">
                        <h3>Exemplo de Captação na Rua</h3>
                        <form action="#" method="post" onsubmit="return false;">
                            <label for="mkNome">Nome da Liderança / Eleitor</label>
                            <input id="mkNome" type="text" placeholder="Nome completo">
                            <label for="mkTelefone">WhatsApp de Contato</label>
                            <input id="mkTelefone" type="tel" placeholder="(61) 90000-0000">
                            <label for="mkBairro">Região / Bairro</label>
                            <input id="mkBairro" type="text" placeholder="Onde você atua?">
                            <button type="button" class="marketing-mini-btn">Cadastrar Apoio</button>
                        </form>
                    </article>
                </div>
            </div>
        </section>

        <section class="marketing-section" id="sec-telas" data-nav="Visão do Sistema" aria-labelledby="telas-title">
            <div class="marketing-shell">
                <p class="marketing-kicker marketing-reveal">5. Controle Total</p>
                <h2 id="telas-title" class="marketing-h2 marketing-reveal">Painel técnico simples, feito para a correria de Brasília.</h2>
                <div class="marketing-ui-preview marketing-reveal">
                    <article class="marketing-ui-card">
                        <header>
                            <h3>Santinho Região Norte</h3>
                            <span class="status-live">● Rastreando</span>
                        </header>
                        <div class="marketing-ui-row">
                            <label>URL de destino (Editável)</label>
                            <p>wa.me/5561900000000?text=ApoioNorte</p>
                        </div>
                        <div class="marketing-ui-row">
                            <label>Analytics Ativo</label>
                            <p>Detectando Cidades e Dispositivos</p>
                        </div>
                        <div class="marketing-ui-row">
                            <label>Gatilho de Urgência</label>
                            <p>Redirecionar para página de doação após o 1º turno.</p>
                        </div>
                    </article>

                    <article class="marketing-ui-card">
                        <header>
                            <h3>QR Code Banner Central</h3>
                            <span class="status-live">Dinâmico</span>
                        </header>
                        <div class="marketing-qr-preview" aria-hidden="true">
                            <span></span><span></span><span></span><span></span><span></span>
                            <span></span><span></span><span></span><span></span><span></span>
                            <span></span><span></span><span></span><span></span><span></span>
                            <span></span><span></span><span></span><span></span><span></span>
                            <span></span><span></span><span></span><span></span><span></span>
                        </div>
                        <div class="marketing-ui-row">
                            <label>Blindagem Anti-Falha</label>
                            <p>Scans ilimitados, servidor com alta disponibilidade e CDN focada no Brasil.</p>
                        </div>
                    </article>
                </div>
            </div>
        </section>

        <section class="marketing-section" id="sec-contato" data-nav="Fale Conosco" aria-labelledby="contato-title">
            <div class="marketing-shell">
                <p class="marketing-kicker marketing-reveal">6. Atendimento Especializado</p>
                <h2 id="contato-title" class="marketing-h2 marketing-reveal">Ficou com alguma dúvida ou quer entender melhor como funciona?</h2>
                <p class="marketing-lead marketing-reveal">Envie uma mensagem com sua dúvida, sugestão ou proposta que um de nossos analistas irá te atender assim que possível.</p>
                <article class="marketing-contact-card marketing-reveal" aria-label="Formulário de contato">
                    <form id="marketingContactForm" class="marketing-contact-form" method="post" novalidate>
                        <div class="marketing-contact-grid">
                            <div class="marketing-contact-field">
                                <label for="marketingContactName">Nome</label>
                                <input id="marketingContactName" name="name" type="text" autocomplete="name" required>
                            </div>
                            <div class="marketing-contact-field">
                                <label for="marketingContactEmail">E-mail</label>
                                <input id="marketingContactEmail" name="email" type="email" autocomplete="email" required>
                            </div>
                        </div>
                        <div class="marketing-contact-field">
                            <label for="marketingContactWhatsapp">WhatsApp (opcional)</label>
                            <input id="marketingContactWhatsapp" name="whatsapp" type="tel" autocomplete="tel">
                        </div>
                        <div class="marketing-contact-field">
                            <label for="marketingContactMessage">Texto de solicitação</label>
                            <textarea id="marketingContactMessage" name="message" rows="5" required></textarea>
                        </div>
                        <div class="marketing-contact-actions">
                            <button type="submit" id="marketingContactSubmit" class="marketing-btn marketing-btn-primary marketing-btn-lg">Quero entender melhor</button>
                            <button type="button" id="marketingContactWhatsappBtn" class="marketing-btn marketing-btn-outline marketing-btn-lg">Chamar no whatsapp</button>
                        </div>
                        <p id="marketingContactStatus" class="marketing-contact-status" role="status" aria-live="polite"></p>
                    </form>
                </article>
            </div>
        </section>

        <section class="marketing-section" id="sec-planos" data-nav="Founder Edition" aria-labelledby="planos-title">
            <div class="marketing-shell">
                <p class="marketing-kicker marketing-reveal">7. Oportunidade Exclusiva</p>
                <h2 id="planos-title" class="marketing-h2 marketing-reveal">Invista agora, use durante toda a campanha e no próximo mandato.</h2>
                <article class="marketing-founder-callout marketing-reveal" aria-label="Condição especial Founder">
                    <h3>🔥 FOUNDER EDITION: Preço Congelado (Apenas 100 vagas)</h3>
                    <p style="color: #fde68a; margin-bottom: 15px;">Desenvolvida com a realidade de Brasília em mente, abrimos uma janela única de contratação:</p>
                    <ul>
                        <li><strong>Preço Congelado:</strong> Trave o valor de hoje. Mesmo que reajustemos Creator e Pro, seu preço não muda enquanto mantiver a assinatura ativa.</li>
                        <li><strong>Voz Ativa no Desenvolvimento:</strong> Membros Founder podem solicitar a criação de funcionalidades específicas. Nós adaptamos a ferramenta para a sua estratégia.</li>
                        <li><strong>Suporte Prioritário:</strong> Durante a campanha, tempo é voto. Você terá canal direto com nossos especialistas.</li>
                    </ul>
                </article>
                <p class="marketing-lead marketing-reveal" style="font-size: 0.95rem;">
                    Nossos planos cobrem desde mandatos locais até grandes campanhas federais.
                </p>
                <div id="marketingPlansGrid" class="marketing-grid marketing-grid-4 marketing-reveal" aria-live="polite"></div>
            </div>
        </section>

        <section class="marketing-section" id="sec-faq" data-nav="FAQ" aria-labelledby="faq-title">
            <div class="marketing-shell">
                <p class="marketing-kicker marketing-reveal">8. FAQ Técnico</p>
                <h2 id="faq-title" class="marketing-h2 marketing-reveal">Dúvidas comuns da coordenação de campanha.</h2>
                <div class="marketing-faq marketing-reveal">
                    <details>
                        <summary>O que acontece se eu usar um QR Code gratuito do Google/Sites gringos?</summary>
                        <p>A maioria possui limites de escaneamento. Quando bate o limite (que na campanha é rápido), eles bloqueiam o link e exigem pagamento em Dólar/Euro, ou pior: deletam o código. A encurtou.pro não possui limite de scans.</p>
                    </details>
                    <details>
                        <summary>A ferramenta substitui minha agência de comunicação?</summary>
                        <p>Não. A agência cria a arte, o conceito e a estratégia. Nós fornecemos o motor técnico (links rastreáveis, proteção contra quedas e captação de dados de conversão) para que a agência prove o valor do trabalho deles.</p>
                    </details>
                    <details>
                        <summary>O que significa preço congelado?</summary>
                        <p>Ao entrar como membro Founder, o valor da sua assinatura mensal ou anual fica garantido enquanto você mantiver o plano ativo. Reajustes futuros nos demais planos não se aplicam a você.</p>
                    </details>
                    <details>
                        <summary>Como funciona a "Voz Ativa" da Founder Edition?</summary>
                        <p>Se o seu gabinete precisar de uma métrica específica, integração com um sistema próprio da câmara, ou um tipo de formulário novo, nossa equipe de desenvolvimento priorizará a sua demanda na criação de atualizações do sistema.</p>
                    </details>
                    <details>
                        <summary>Meus dados estarão seguros?</summary>
                        <p>Sim. Contamos com criptografia ponta a ponta para formulários, proteção contra ataques CSRF/XSS e backups diários, adequados às normativas de proteção de dados.</p>
                    </details>
                </div>
            </div>
        </section>

        <section class="marketing-section marketing-section-final" id="sec-cta" data-nav="Fechamento" aria-labelledby="cta-title">
            <div class="marketing-glow marketing-glow-b" aria-hidden="true"></div>
            <div class="marketing-shell">
                <p class="marketing-kicker marketing-reveal">9. Decisão Estratégica</p>
                <h2 id="cta-title" class="marketing-h2 marketing-reveal">Não deixe sua campanha refém de tecnologias amadoras.</h2>
                <p class="marketing-lead marketing-reveal">
                    A Founder Edition está limitada a 100 gabinetes. Garanta a infraestrutura digital do seu mandato hoje.
                </p>
                <div class="marketing-cta-row marketing-reveal">
                    <a class="marketing-btn marketing-btn-primary marketing-btn-lg marketing-cta-planos" href="<?php echo htmlspecialchars($ctaPlanosUrl, ENT_QUOTES, 'UTF-8'); ?>">Ver Planos e Assinar</a>
                    <a class="marketing-btn marketing-btn-outline marketing-btn-lg" href="<?php echo htmlspecialchars($ctaWhatsappUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">Tirar dúvidas no WhatsApp</a>
                </div>
                <button type="button" id="marketingBackToTop" class="marketing-back-top marketing-reveal">Voltar ao topo</button>
            </div>
        </section>
    </main>

    <nav class="marketing-mobile-nav" aria-label="Navegação móvel da apresentação">
        <button type="button" id="marketingPrevMobile" aria-label="Seção anterior">Anterior</button>
        <span id="marketingMobileLabel">Abertura</span>
        <button type="button" id="marketingNextMobile" aria-label="Próxima seção">Próximo</button>
    </nav>

    <footer class="marketing-footer" role="contentinfo">
        <div class="marketing-footer-inner">
            <p>encurtou.pro © <?php echo date('Y'); ?>. Tecnologia especializada para mandatos.</p>
            <ul>
                <li><a href="/termos_de_uso.txt" target="_blank" rel="noopener noreferrer">Termos de uso</a></li>
                <li><a href="/politicas_de_privacidade.txt" target="_blank" rel="noopener noreferrer">Política de privacidade</a></li>
                <li><a href="mailto:suporte@encurtou.pro">suporte@encurtou.pro</a></li>
            </ul>
        </div>
    </footer>

    <script src="/tracker.js" defer></script>
    <script src="/marketing.js?v=2.3" defer></script>
</body>
</html>