-- Hardening de subdomínios de usuários (user_subdomains)
-- Execute APÓS plans_update_v3.sql.
--
-- Pré-checagem (listar duplicatas globais antes de aplicar UNIQUE):
-- SELECT subdomain, COUNT(*) AS total, GROUP_CONCAT(user_id) AS users
-- FROM user_subdomains
-- GROUP BY subdomain
-- HAVING total > 1;
--
-- Se houver duplicatas, resolva manualmente antes de continuar.

-- Alinhar tamanho com links.subdomain (VARCHAR 50)
ALTER TABLE `user_subdomains`
  MODIFY `subdomain` VARCHAR(50) NOT NULL;

-- Unicidade global (protege contra race condition no cadastro)
ALTER TABLE `user_subdomains`
  ADD UNIQUE KEY `unique_subdomain_global` (`subdomain`);
