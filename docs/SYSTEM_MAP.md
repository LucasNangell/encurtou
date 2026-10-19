# Encurtou.pro - System Map

Este documento serve como o índice de navegação principal da arquitetura do **Encurtou.pro** (Link Management, QR Intelligence e Digital Identity Platform). Seu propósito é ajudar futuras IAs a localizarem rapidamente os arquivos e pastas corretos para qualquer tarefa de manutenção ou melhoria, reduzindo o consumo desnecessário de tokens.

---

## 🚀 Stack Principal do Projeto
- **Backend:** Vanilla PHP (PHP 7.4/8.x nativo) estruturado com PDO para acesso a dados e sessões seguras.
- **Frontend:** Vanilla HTML5, CSS3 e JavaScript (sem frameworks, interface SPA-like com carregamento dinâmico via abas).
- **Banco de Dados:** MySQL (InnoDB, UTF-8 MB4) com histórico de migrações estruturadas em arquivos `.sql`.
- **Infraestrutura/Hospedagem:** Apache (servidor compartilhado Hostinger, roteamento via `.htaccess`) e Cloudflare Worker opcional na borda para tratamento de subdomínios.

---

## 📂 Estrutura de Pastas e Componentes

```
E:\encurtou
├── billing/             # Serviços e lógica de Billing (Planos, Assinaturas, Webhook)
├── docs/                # Mapas de navegação técnica e documentações (Este diretório)
├── templates/
│   └── bio/             # Temas e folhas de estilo para as Bio Pages públicas
├── uploads/             # Destino de escrita para imagens enviadas por usuários
├── *.php                # Endpoints e arquivos de controle principais do backend
├── *.js                 # Lógica de interface frontend e manipuladores do construtor visual
├── *.css                # Estilos visuais e temas do painel administrativo
├── *.sql                # Arquivos de migração e criação de tabelas de banco de dados
└── .htaccess            # Configurações de rotas Apache, variáveis de ambiente e redirecionamento
```

---

## 🗺️ Tabela de Localização de Áreas

Use esta tabela para responder à pergunta: **"Qual arquivo ou pasta devo abrir para alterar isso?"**

| Área do Sistema | Arquivo / Diretório Principal | Função / Responsabilidade | Quando alterar |
| :--- | :--- | :--- | :--- |
| **Configurações Globais** | [`db.php`](file:///E:/encurtou/db.php) | Conexão PDO, sessão segura, validação e emissão de tokens CSRF. | Alterar configurações de cookie, segurança de sessão ou conexão local/produção. |
| **Variáveis e Servidor** | [`.htaccess`](file:///E:/encurtou/.htaccess) | Configurações do Apache, HTTPS forçado, chaves de API de produção e fallback de rota. | Atualizar credenciais de gateway, alterar nível de validação CSRF ou regras de rewrite. |
| **Redirecionamento** | [`redirect.php`](file:///E:/encurtou/redirect.php) | Motor que resolve slugs e subdomínios, verifica regras de expiração, A/B e SO, e redireciona. | Corrigir comportamento de redirecionamento, logs de cliques ou lógica de pixels e deep-linking. |
| **Páginas de Bio** | [`bio.php`](file:///E:/encurtou/bio.php) | Renderizador público das páginas de Link-in-Bio microsites e blocos de conteúdo. | Atualizar ou adicionar blocos de conteúdo na visualização pública das Bio Pages. |
| **Lógica da Bio** | [`bio_helpers.php`](file:///E:/encurtou/bio_helpers.php) | Utilitários de sanitização, normalização e resolução de schemas JSON de Bio Pages. | Adicionar novas propriedades a blocos de formulário, cards ou FAQ do construtor de Bios. |
| **Estrutura do Painel** | [`index.html`](file:///E:/encurtou/index.html) | Esqueleto HTML da SPA do painel do usuário e do painel administrativo global. | Inserir novos botões de navegação, formulários de criação ou seções inteiras do painel. |
| **Ações do Painel** | [`script.js`](file:///E:/encurtou/script.js) | Lógica e controladores de UI do painel do usuário, gráficos (Chart.js) e requisições HTTP. | Alterar o comportamento visual de troca de abas, interações de modais ou chamadas de API do painel. |
| **Construtor da Bio** | [`bio_builder_layer.js`](file:///E:/encurtou/bio_builder_layer.js) | Lógica interativa do construtor visual de Bio Pages (No-Code editor + live preview). | Atualizar o comportamento do live preview do builder, ordenação de blocos ou envio de dados. |
| **Autenticação** | [`auth.php`](file:///E:/encurtou/auth.php) | API de Login, Cadastro, Recuperação de Acesso e Google Identity Login. | Alterar validações de senha, tempo de expiração de token de reset ou regras de login com Google. |
| **Criação de Links** | [`api.php`](file:///E:/encurtou/api.php) | API de inserção de links, QR codes, variantes de teste A/B e checagem de cotas/limites. | Alterar validação de URL de destino, processamento de slug ou limites de criação. |
| **Billing & Planos** | [`billing/`](file:///E:/encurtou/billing) | EntitlementService, SubscriptionService, PlanService e webhook de cobranças. | Alterar limites de cliques mensais, recursos restritos de planos ou processamento de assinaturas. |
| **Webhooks** | [`webhook_asaas.php`](file:///E:/encurtou/webhook_asaas.php) | Ponto de entrada de webhooks do Asaas (gateway de pagamentos). | Ajustar validação de IP de origem ou autenticação de payloads de cobrança. |
| **Gestão de Links** | [`manage_links.php`](file:///E:/encurtou/manage_links.php) | API para listar, atualizar agendamentos, excluir ou configurar pixels de links. | Modificar edição de URLs encurtadas existentes ou remoção de links. |
| **Gestão de Times** | [`manage_teams.php`](file:///E:/encurtou/manage_teams.php) | API para criar e atualizar membros de equipes de colaboração. | Alterar limites de convite ou regras de visibilidade compartilhada de links entre membros. |
| **Upload de Mídia** | [`upload_bio.php`](file:///E:/encurtou/upload_bio.php) | API de upload e validação de imagens de perfil/avatar para as Bio Pages. | Reforçar restrições de extensões de arquivo ou mudar limites de tamanho de upload. |
| **Banco de Dados** | [`schema.sql`](file:///E:/encurtou/schema.sql) | Estrutura base original do banco de dados MySQL e tabelas core. | Entender relacionamentos de tabelas ou criar scripts delta para produção. |

---

## 🗺️ Mapas Técnicos Complementares

Consulte os mapas de detalhe de cada área para guiar alterações profundas:
1. 🎨 **[Frontend Map](file:///E:/encurtou/docs/FRONTEND_MAP.md):** Contém detalhes da SPA, arquivos CSS/JS, interações do Bio Builder e bibliotecas no cliente.
2. ⚙️ **[Backend Map](file:///E:/encurtou/docs/BACKEND_MAP.md):** Detalha os fluxos de rotas PHP, o mecanismo de redirecionamento, APIs de gestão e o ecossistema de billing.
3. 💾 **[Database Map](file:///E:/encurtou/docs/DATABASE_MAP.md):** Mapeamento físico de tabelas MySQL, relacionamentos, migrações SQL e boas práticas.
4. 🚢 **[Deploy Map](file:///E:/encurtou/docs/DEPLOY_MAP.md):** Variáveis de ambiente configuradas no Apache, HTTPS forçado, estrutura FTP/Hostinger e scripts.

---

## 🔒 Áreas Sensíveis e Cuidados Especiais
1. **Credenciais no Repositório:** O arquivo [`.htaccess`](file:///E:/encurtou/.htaccess) contém chaves de API e strings de conexões reais do banco de dados de sandbox. Valores de produção reais devem ser configurados no painel de administração da Hostinger ou injetados externamente.
2. **Redirecionamento Crítico:** [`redirect.php`](file:///E:/encurtou/redirect.php) é executado em cada clique. Qualquer erro de sintaxe PHP ou lentidão de conexão quebrará todos os links ativos dos clientes em produção.
3. **Idempotência de Webhooks:** [`webhook_asaas.php`](file:///E:/encurtou/webhook_asaas.php) e [`billing/webhook.php`](file:///E:/encurtou/billing/webhook.php) processam transações financeiras. Devem ser tolerantes a falhas e idempotentes para evitar duplicidades de cobrança.

---

## 🤖 Regras para Futuras IAs
> Antes de usar busca global, @codebase ou varrer o projeto inteiro, consulte primeiro este arquivo e o mapa específico da área afetada. Abra apenas os arquivos indicados nos mapas. Se precisar consultar arquivo fora do mapa, explique o motivo antes. Após alterar estrutura, rotas, endpoints, banco, configuração, comandos, deploy ou responsabilidade de arquivos, atualize o mapa correspondente.

---

## 📅 Histórico de Criação do Mapa
- **2026-06-24:** Estruturação inicial do mapa do sistema e mapeamento completo das dependências de billing, banco de dados, frontend e deploy de produção na Hostinger.
