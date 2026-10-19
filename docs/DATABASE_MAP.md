# Encurtou.pro - Database Map

Este documento descreve o modelo físico de dados (MySQL) do **Encurtou.pro**. Ele serve como um mapa prático para que futuras IAs compreendam as tabelas, chaves primárias, relacionamentos, histórico de migrações e comandos úteis para a administração do banco de dados.

---

## 💾 Visão Geral do Banco de Dados
- **SGBD:** MySQL 5.7+ / 8.x compatível.
- **Engine padrão:** InnoDB (suporte nativo a transações e chaves estrangeiras).
- **Collation:** `utf8mb4_unicode_ci` ou `utf8mb4_general_ci` (suporte a emojis e caracteres especiais).

---

## 📂 Mapeamento de Arquivos SQL e Migrações

Todas as alterações estruturais do banco de dados estão catalogadas em arquivos `.sql` e executadas localmente via scripts PHP equivalentes. Em produção (Hostinger), as migrações são aplicadas executando o SQL correspondente de forma manual no phpMyAdmin.

| Arquivo SQL de Migração | Script PHP Executor | Objetivo / Alteração |
| :--- | :--- | :--- |
| [`schema.sql`](file:///E:/encurtou/schema.sql) | [`run_migration.php`](file:///E:/encurtou/run_migration.php) | Criação da estrutura core original (users, links, clicks_log, bio_pages, etc.). |
| [`billing_schema.sql`](file:///E:/encurtou/billing_schema.sql) | [`run_plans_migration.php`](file:///E:/encurtou/run_plans_migration.php) | Cria tabelas de cobrança (plans, user_subscriptions, invoices, billing_events_log). |
| [`admin_schema.sql`](file:///E:/encurtou/admin_schema.sql) | N/A (Execução direta) | Cria tabelas de controle administrativo (user_account_overrides, system_config). |
| [`team_update_migration.sql`](file:///E:/encurtou/team_update_migration.sql) | [`run_team_migration.php`](file:///E:/encurtou/run_team_migration.php) | Cria tabelas de times (teams, team_members) e índices adicionais. |
| [`ab_test_migration.sql`](file:///E:/encurtou/ab_test_migration.sql) | N/A (Execução direta) | Modifica tabelas de links e cria `link_variants` para o fluxo de Testes A/B. |
| [`bio_form_migration.sql`](file:///E:/encurtou/bio_form_migration.sql) | [`run_bio_form_tables.php`](file:///E:/encurtou/run_bio_form_tables.php) | Cria tabelas de respostas de formulários inseridos nas páginas de Bio. |
| [`conversions_migration.sql`](file:///E:/encurtou/conversions_migration.sql) | N/A (Execução direta) | Cria tabelas para metas de conversão de cliques (`conversion_actions`, `conversions_log`). |
| [`plans_update_v3.sql`](file:///E:/encurtou/plans_update_v3.sql) | N/A (Execução direta) | Cria a tabela de subdomínios cadastrados de usuários (`user_subdomains`). |
| [`subdomain_hardening_migration.sql`](file:///E:/encurtou/subdomain_hardening_migration.sql) | N/A (Execução direta) | UNIQUE global em `user_subdomains.subdomain` e alinhamento VARCHAR(50). |
| [`qr_destination_migration.sql`](file:///E:/encurtou/qr_destination_migration.sql) | [`run_qr_destination_migration.php`](file:///E:/encurtou/run_qr_destination_migration.php) | Atualiza a tabela `links` com colunas de metadados dinâmicos para QR Codes. |
| [`user_ban_migration.sql`](file:///E:/encurtou/user_ban_migration.sql) | [`run_user_ban_migration.php`](file:///E:/encurtou/run_user_ban_migration.php) | Banimento de contas (`users.is_banned`, `ban_expires_at`, etc.) e flag `links.admin_ban_paused`. |
| [`security_abuse_migration.sql`](file:///E:/encurtou/security_abuse_migration.sql) | [`run_security_abuse_migration.php`](file:///E:/encurtou/run_security_abuse_migration.php) | Anti-abuse: verificação de e-mail, strikes, blocklists, cache de scan, denúncias, audit log, flags `links.security_paused`. |
| [`founder_monthly_migration.sql`](file:///E:/encurtou/founder_monthly_migration.sql) | [`run_founder_monthly_migration.php`](file:///E:/encurtou/run_founder_monthly_migration.php) | Plano 5 (Founder Edition mensal) e colunas `locked_monthly_price` / `locked_annual_price` em `founder_allocations`. |

---

## 🗺️ Modelo de Entidades e Relacionamentos (Tabelas)

Abaixo está o mapa das tabelas físicas organizadas logicamente por componentes do sistema:

### 1. Núcleo de Usuários e Segurança
- **`users`**: Cadastro de contas de usuários do sistema.
  - *Chave Primária:* `id` (INT)
  - *Chave Única:* `email` (VARCHAR)
  - *Campos Importantes:* `password` (hash bcrypt), `current_plan_id` (FK), `is_admin` (TINYINT), `is_banned`, `ban_expires_at`, `ban_reason`, `banned_by`, `banned_at`, `email_verified_at`, `session_invalidated_at`, `abuse_strike_count`, `can_moderate`.
- **`user_subdomains`**: Subdomínios da plataforma (`*.linkneo.pro`) registrados por usuários (cadastro instantâneo, sem verificação DNS externa).
  - *Chave Primária:* `id` (INT)
  - *Chaves Estrangeiras:* `user_id` (FK para `users.id`)
  - *Unicidade:* `(user_id, subdomain)` e global `subdomain` (pós `subdomain_hardening_migration.sql`)
- **`teams` & `team_members`**: Organização de equipes multiusuários.
  - *Relações:* `teams.owner_id` -> `users.id`, `team_members.user_id` -> `users.id`.

### 2. Gestão de Redirecionamento e Links
- **`links`**: Registro de slugs encurtadas e configurações de QR Codes.
  - *Chave Primária:* `id` (INT)
  - *Chave Única:* (`subdomain`, `path`) - impede slugs duplicadas sob o mesmo domínio.
  - *Chaves Estrangeiras:* `user_id` (FK para `users.id`)
  - *Campos Importantes:* `is_ab_test` (flag), `is_premium_locked` (flag cota), `clicks` (contador otimizado), `admin_ban_paused` (pausado por ban admin), `security_paused`, `security_verdict`, `security_scanned_at`.
- **`link_variants`**: Variações de destinos alternativos para testes A/B de um link.
  - *Relações:* `link_id` (FK para `links.id`).
- **`clicks_log`**: Histórico detalhado de cliques efetuados nos links.
  - *Relações:* `link_id` (FK para `links.id`), `variant_id` (FK para `link_variants.id`).
  - *Índices Compostos:* `idx_clicks_link_clicked` (`link_id`, `clicked_at`) e `idx_clicks_link_source_clicked` (`link_id`, `source`, `clicked_at`) para agilizar as consultas de relatórios.

### 3. Link-in-Bio Pages (Microsites)
- **`bio_pages`**: Configurações de design e JSON schemas das Bio Pages.
  - *Chaves Estrangeiras:* `user_id` (FK para `users.id`)
  - *Campos Importantes:* `builder_schema_json` (LONGTEXT com os blocos no-code).
- **`bio_links`**: Links avulsos da Bio (Legado/Fallback).
  - *Relações:* `bio_page_id` (FK para `bio_pages.id`).
- **`bio_form_submissions` & `bio_form_answers`**: Registros de capturas e envios nos blocos de formulário integrados às Bio Pages.
  - *Relações:* `bio_page_id` (FK para `bio_pages.id`), `submission_id` (FK para `bio_form_submissions.id`).

### 4. Billing, Faturas e Planos
- **`plans`**: Definições de preços e cotas máximas para os planos de assinatura.
  - *IDs:* 1 Free, 2 Creator, 3 Pro, 4 Business, **5 Founder Edition** (mensal/anual, preço editável pelo admin).
  - *Campos Importantes:* `max_links`, `max_qrs`, `max_bio_pages`, `features_json`, `monthly_price`, `annual_price`.
- **`founder_allocations`**: Vagas exclusivas Founder (máx. configurável via `system_config.founder_max_seats`).
  - *Campos:* `user_id`, `status` (`pending_payment` | `active`), `locked_monthly_price`, `locked_annual_price` (preço congelado na primeira ativação).
- **`user_subscriptions`**: Registro das assinaturas ativas com status atual.
  - *Relações:* `user_id` (FK para `users.id`), `plan_id` (FK para `plans.id`).
- **`invoices`**: Faturas individuais de pagamento.
  - *Relações:* `user_id` (FK para `users.id`).
- **`user_usage_monthly`**: Contador mensal de cliques consumidos por usuário para controle de limite de cota de plano.

---

## ⚠️ Cuidados ao Alterar o Schema de Banco
> [!IMPORTANT]
> - **Não assuma acesso direto automático ao banco de dados de produção.** Toda alteração estrutural no banco de dados deve gerar um script SQL delta idempotente (utilizando `IF NOT EXISTS` ou `ADD COLUMN IF NOT EXISTS`) para ser executado manualmente em produção.
> - **Evite Lock de Tabelas:** Tabelas como `clicks_log` possuem alto volume de escrita em produção. Alterações nessa tabela devem ser feitas em janelas de manutenção e utilizando indexações otimizadas para evitar lentidão no redirecionador.
