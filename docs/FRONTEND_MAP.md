# Encurtou.pro - Frontend Map

Este documento detalha o funcionamento da camada cliente (frontend) do **Encurtou.pro**. Ele serve como um mapa prático para que futuras IAs saibam quais arquivos abrir e modificar ao trabalhar em tarefas de UI/UX, layouts, animações e fluxos visuais.

---

## 🎨 Visão Geral da Stack do Frontend
- **Estrutura:** HTML5 semântico com navegação em formato SPA (Single Page Application) controlada por exibição de elementos `<section>` na mesma página.
- **Estilização:** CSS3 puro e responsivo com uso extensivo de variáveis CSS para temas, fontes personalizadas (Manrope e Sora) e ícones (FontAwesome 6.0).
- **Lógica e Dinâmica:** Vanilla Javascript para controle de estado local da UI, gerenciamento de abas, renderização de tabelas, interações de modais e gráficos.
- **Bibliotecas Importantes (via CDN):**
  - **Chart.js:** Utilizado na geração dos relatórios estatísticos da aba de Analytics.
  - **SweetAlert2:** Utilizado na exibição de modais interativos, diálogos de confirmação e alertas de erro/sucesso.
  - **QR Code Styling:** Utilizado para desenhar e customizar QR Codes em tempo real no cliente antes de exportar em PNG ou SVG.
  - **Google Identity Services Client:** Utilizado para renderizar e gerenciar o fluxo nativo de login com Google.

---

## 📂 Organização dos Arquivos de Frontend

```
E:\encurtou
├── index.html               # Estrutura HTML principal do Dashboard SPA
├── style.css                # Folha de estilo global principal (Temas e utilitários)
├── styled.css               # Estilos adicionais para telas menores/ajustes finos
├── script.js                # Lógica JavaScript global do painel (abas, auth, links)
├── bio_builder_shared.css   # Estilos CSS comuns aos blocos das Bio Pages (painel e preview)
├── bio_builder_layer.js     # Lógica do construtor de Bio Pages e preview interativo
├── tracker.js               # Script auxiliar para log de cliques no cliente
└── templates/bio/           # Diretório com temas de Bio Pages públicas
```

---

## 🧭 Mapeamento de Telas (Seções) e Arquivos de Controle

O painel é estruturado em seções de abas dentro do arquivo [`index.html`](file:///E:/encurtou/index.html). A alteração visual e o controle lógico de cada aba ocorrem nos seguintes arquivos:

| Aba / Funcionalidade | ID da Seção no HTML | Arquivo Javascript Controlador | Função Visual |
| :--- | :--- | :--- | :--- |
| **Início (Home)** | `section-home` | [`script.js`](file:///E:/encurtou/script.js) | Entrada de dados para encurtamento rápido de links, customização de QR Code e importador UTM. |
| **Painel de Controle** | `section-dashboard` | [`script.js`](file:///E:/encurtou/script.js) | Exibe links rápidos para recursos criados e resumo de métricas. |
| **Meus Links** | `section-mylinks` | [`script.js`](file:///E:/encurtou/script.js) | Tabela e cards contendo todos os links cadastrados com filtros e opções de edição/exclusão. |
| **Meus QR Codes** | `section-myqrs` | [`script.js`](file:///E:/encurtou/script.js) | Lista de QR Codes customizados criados e ações de download. |
| **Analytics** | `section-analytics` | [`script.js`](file:///E:/encurtou/script.js) | Exibição de gráficos de cliques no tempo, origens (referrers) e geolocalização. |
| **Campanhas** | `section-campaigns` | [`script.js`](file:///E:/encurtou/script.js) | Agrupamento de links em campanhas de marketing e UTM injection. |
| **Bio Pages** | `section-biopages` | [`script.js`](file:///E:/encurtou/script.js) | Grid de Bio Pages ativas e tela de inicialização do construtor. |
| **Equipes** | `section-team` | [`script.js`](file:///E:/encurtou/script.js) | Gestão de membros do time e convites de acesso. |
| **Upgrade / Assinatura**| `section-subscription` | [`script.js`](file:///E:/encurtou/script.js) | Exibição da tabela de planos, preços, limites e checkout de pagamento. |
| **Administração** | `section-admin` | [`script.js`](file:///E:/encurtou/script.js) | Visão administrativa de usuários, cotas e overrides. |

---

## 🛠️ O Construtor Visual de Bio (Bio Pages Builder)

Quando o usuário edita uma Bio Page, uma interface de edição com preview é aberta:
- **Painel de Configurações (HTML):** Elementos dentro de `section-biopages` em [`index.html`](file:///E:/encurtou/index.html).
- **Estilos do Editor:** Definidos em [`bio_builder_shared.css`](file:///E:/encurtou/bio_builder_shared.css).
- **Lógica e Preview:** O arquivo [`bio_builder_layer.js`](file:///E:/encurtou/bio_builder_layer.js) gerencia a edição baseada em blocos, manipulando um JSON Schema (`builder_schema_json`) que é sincronizado com o banco de dados. Ele gerencia as interações de adicionar, ordenar e remover blocos de conteúdo no preview lateral.

---

## 🔄 Fluxos de Interações no Frontend

### Fluxo 1: Autenticação e Token CSRF
1. A página inicial carrega e envia um GET para `auth.php?action=status`.
2. Se logado, o backend retorna `csrf_token` e as permissões de plano.
3. O JavaScript atualiza `window.csrfToken`, preenche o painel de uso `userUsagePanel` e exibe o menu correspondente.
4. Toda requisição POST subsequente envia o cabeçalho `X-CSRF-Token` com esse valor.

### Fluxo 2: Geração de Link e QR Code Customizado
1. O usuário digita a URL e a terminação (slug) em `section-home`.
2. O JavaScript dispara `api.php?action=check_slug` enquanto o usuário digita para validar se a terminação está livre.
3. Ao clicar em Criar, o formulário faz um POST para `api.php`. Se for tipo `qr`, processa o desenho final no Canvas através da biblioteca `qr-code-styling` utilizando as propriedades selecionadas de cores e logo.

---

## 📝 Guia de Alterações: Onde Alterar?

- **Para alterar fontes, cores do painel ou estilos de botões:**
  - Edite as variáveis CSS em [`style.css`](file:///E:/encurtou/style.css).
- **Para adicionar um novo campo no formulário de criação de links:**
  - Insira a marcação HTML em [`index.html`](file:///E:/encurtou/index.html) dentro de `section-home` e gerencie o envio de dados via `FormData` em [`script.js`](file:///E:/encurtou/script.js).
- **Para alterar animações e efeitos de transição visual do construtor de Bios:**
  - Edite [`bio_builder_shared.css`](file:///E:/encurtou/bio_builder_shared.css) ou [`styled.css`](file:///E:/encurtou/styled.css).
- **Para adicionar novos temas visuais selecionáveis para as Bio Pages:**
  - Crie um novo subdiretório dentro de [`templates/bio/`](file:///E:/encurtou/templates/bio/) contendo `style.css` e `config.json` definindo as propriedades e IDs do tema.
