# Auditoria de Controle por Plano (Checklist)

## Objetivo
Garantir controle centralizado, no painel administrativo, sobre funcionalidades e limites por plano, com enforcement no backend e reflexo na interface de planos.

## Cobertura implementada nesta rodada

### 1) Enforcement de limites no backend
- `max_links` / `max_qrs` aplicados em criação e troca de tipo (`link`/`qr`).
- `max_bio_pages` aplicado em criação de Bio Page.
- `max_campaigns` aplicado ao criar campanhas (inclusive em fluxos UTM que criam campanha automaticamente).
- `max_subdomains` aplicado na criação de subdomínio.
- `max_clicks_monthly` aplicado no registro de cliques em `redirect`.
- `max_link_destination_edits` / `max_qr_destination_edits` aplicados em edição de destino.
- `max_team_members` aplicado em gestão de equipe.

### 2) Features com gate no backend
- `analytics`
- `custom_domain`
- `custom_path`
- `deep_linking`
- `bulk_utm`
- `tracking`
- `ab_testing`
- `rules`
- `redirect`
- `bio_pages`
- `team`
- `priority_support`
- `folders_tags` (pastas e tags)
- `social_preview` (preview social/favicon)
- `premium_bio_templates`
- `bio_form_exports`
- `ai_tutor`

### 3) Painel administrativo (controle)
- Matriz/admin de features atualizada para incluir:
  - `team`
  - `folders_tags`
  - `social_preview`
  - `premium_bio_templates`
  - `bio_form_exports`
  - `ai_tutor`
- Matriz/admin de limites atualizada para incluir:
  - `max_team_members`
- `update_plan` com suporte a `max_team_members` (quando coluna existir).

### 4) Reflexo na visualização de planos
- Tabela comparativa e cards atualizados para usar flags/limites reais do plano.
- Novas linhas de comparação adicionadas para features avançadas e limites de equipe.
- Fallback de marketing ajustado para incluir plano Business.

### 5) Consistência adicional
- `auth.php` passou a expor features adicionais no `status` para o front.
- `update_config` administrativo com whitelist de chaves permitidas.
- Templates premium de Bio sinalizados com `locked_by_plan` na API de templates.
- UI de templates bloqueia seleção de template premium sem permissão.

## Itens auditados por área

### Links/QR
- Criação, edição, tracking, campanhas, regras, A/B, deep linking: com gate.
- Pastas/tags: agora com gate (`folders_tags`).
- Preview social/favicon: agora com gate (`social_preview`).

### Bio Pages
- Quota de Bio Pages: com gate.
- Templates premium: agora com gate (`premium_bio_templates`).
- Formulários Bio (export): agora com gate (`bio_form_exports`).

### Equipe
- Acesso à funcionalidade: gate por feature (`team`).
- Limite de membros: limite por plano/override (`max_team_members`).

### Analytics
- Retenção por plano: aplicada.
- Limite de cliques/mês para registro de analytics: aplicado.

### Tutor IA
- Acesso ao endpoint: agora com gate (`ai_tutor`), preservando fallback para ambientes sem schema de billing completo.

## Pendências e decisões de produto (não bloqueantes)
- Definir matriz comercial final (por plano) para novas features adicionadas ao controle:
  - `folders_tags`, `social_preview`, `premium_bio_templates`, `bio_form_exports`, `ai_tutor`.
- Revisar se cards de plano devem exibir todas as features novas ou manter subset comercial (a tabela comparativa já reflete mais detalhes).

## Resultado
O sistema ficou preparado para controle granular de funcionalidades e limites via painel administrativo, com enforcement no backend em fluxos críticos e alinhamento visual de planos.

## Regra de ciclo mensal aplicada
- Todos os limites numéricos passaram a ser avaliados por ciclo mensal do usuário.
- Âncora do ciclo:
  1. `users.plan_started_at` (quando existir e estiver preenchido)
  2. fallback para `users.created_at`
- Janela de consumo: `start <= evento < end`, onde `end` é o próximo aniversário mensal da âncora.
- Assim, o reset acontece automaticamente na virada de cada novo ciclo mensal do usuário.
