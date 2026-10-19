# Encurtou.pro - Deploy Map

Este documento descreve o fluxo de build, execução e deploy do **Encurtou.pro**. Ele serve como um mapa prático para que futuras IAs saibam como rodar o ambiente local de desenvolvimento, como preparar a aplicação para produção na Hostinger e quais variáveis de ambiente e arquivos configurar.

---

## 💻 Ambiente de Desenvolvimento (Local)

### Requisitos Mínimos
- **Servidor Web:** Apache (instalado localmente, por exemplo, através de XAMPP, WampServer ou Laragon).
- **PHP:** Versão 7.4 ou 8.x nativa.
- **Banco de Dados:** MySQL 5.7 ou superior.

### Execução Local
1. Copie o projeto para o diretório root do seu Apache (ex: `htdocs/encurtou`).
2. Certifique-se de que o módulo `mod_rewrite` do Apache está ativo para que o arquivo [`.htaccess`](file:///E:/encurtou/.htaccess) funcione corretamente.
3. Importe os arquivos SQL necessários para o seu MySQL local (começando por [`schema.sql`](file:///E:/encurtou/schema.sql)).
4. Configure as variáveis de ambiente locais no [`.htaccess`](file:///E:/encurtou/.htaccess) usando as variáveis terminadas em `_LOCAL` para evitar interferência na produção.

---

## 🚢 Ambiente de Produção (Hostinger)

A hospedagem do Encurtou.pro em produção é mantida sob um servidor web Apache compartilhado na Hostinger. 

### Roteamento e ModRewrite
O arquivo [`.htaccess`](file:///E:/encurtou/.htaccess) redireciona de forma transparente qualquer slug inserida na URL para o script principal do roteador backend:
```apache
# Encaminhar tudo para o redirecionador
RewriteRule ^(.*)$ redirect.php?slug=$1 [L,QSA]
```

### Proxy de Borda: Cloudflare Worker
Para suportar subdomínios de usuários em `*.linkneo.pro` (Hostinger compartilhada não roteia wildcards nativamente), o projeto utiliza o script [`cloudflare_worker.js`](file:///E:/encurtou/cloudflare_worker.js) na borda Cloudflare.

O Worker intercepta requisições em subdomínios da plataforma e repassa ao domínio principal com parâmetros explícitos:

- URL de entrada: `https://ofertas.linkneo.pro/promo`
- Worker traduz para: `https://linkneo.pro/redirect.php?subdomain=ofertas&slug=promo`

**Checklist de produção (subdomínios):**
1. Migração [`plans_update_v3.sql`](file:///E:/encurtou/plans_update_v3.sql) aplicada (`user_subdomains`, `plans.max_subdomains`).
2. Migração [`subdomain_hardening_migration.sql`](file:///E:/encurtou/subdomain_hardening_migration.sql) aplicada (UNIQUE global).
3. DNS wildcard `*.linkneo.pro` apontando para Cloudflare.
4. Worker deployado com rota `*.linkneo.pro/*` (ver instruções no final de `cloudflare_worker.js`).
5. `PUBLIC_BASE_URL` em `config_secrets.php` alinhado ao domínio de produção (`https://linkneo.pro`).

> **Nota:** Domínios externos do cliente (ex.: `link.meudominio.com`) **não** são suportados pelo Worker atual — apenas subdomínios sob `linkneo.pro`.

---

## 🔑 Variáveis de Ambiente e Configurações

As variáveis de ambiente são definidas por diretivas `SetEnv` no Apache em [`.htaccess`](file:///E:/encurtou/.htaccess) ou injetadas diretamente nas configurações do painel Hostinger.

### Banco de Dados
- `DB_HOST`: Host de conexão do MySQL de produção (normalmente `localhost` ou IP interno).
- `DB_NAME`: Nome do banco de dados de produção.
- `DB_USER`: Usuário de banco de dados em produção.
- `DB_PASS`: Senha do banco de dados de produção.
- `DB_USER_LOCAL` / `DB_PASS_LOCAL`: Credenciais de fallback utilizadas quando a requisição vem de um endereço local (`127.0.0.1` ou `localhost`).

### Pagamento (Asaas Gateway)
- `ASAAS_ENV`: Identificador de ambiente (`sandbox` ou `production`).
- `ASAAS_API_KEY`: Token de autenticação da API Asaas.
- `ASAAS_WEBHOOK_TOKEN`: Token de segurança configurado no painel Asaas para validação do payload enviado ao webhook [`webhook_asaas.php`](file:///E:/encurtou/webhook_asaas.php).

### Configurações de Segurança
- `CSRF_ENFORCE`: Flag binária (`0` = apenas gera token; `1` = bloqueia POSTs sem o token CSRF correspondente na sessão).

---

## 📂 Pastas de Gravação e Escrita (Uploads)
O servidor requer que a seguinte pasta tenha permissões de gravação explícitas:
- `/uploads`: Direitório de escrita onde avatares de Bio Pages e imagens de logos personalizados de QR Codes são salvos via PHP.
- **Configuração de Permissão Recomendada:** `755` ou `775` dependendo do proprietário do processo do servidor web (normalmente `www-data` ou o usuário Hostinger).

---

## ⚠️ Cuidados Antes do Deploy em Produção
> [!WARNING]
> - **Testes de Conexão:** Sempre certifique-se de que o arquivo [`db.php`](file:///E:/encurtou/db.php) não possui debugs ativos que possam vazar informações de erro em produção.
> - **Validação do .htaccess:** Qualquer erro de sintaxe no arquivo [`.htaccess`](file:///E:/encurtou/.htaccess) causará um erro generalizado de servidor HTTP 500 (Internal Server Error) em toda a aplicação. Teste regras locais de rewrite antes de enviá-las.
