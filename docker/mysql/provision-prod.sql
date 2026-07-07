-- ONYX AIP — MySQL provisioning for PRODUCTION (run once on the shared MySQL container)
-- Separate database + user from dev (onyx_aip) and test (onyx_aip_test) — never share
-- credentials across environments, so a misconfigured env var fails to connect rather
-- than silently operating against the wrong data.
--
-- Usage:
--   docker exec -i <shared_mysql_container> mysql -uroot -p < docker/mysql/provision-prod.sql
--
-- Replace 'CHANGE_ME' with a strong password and store it in the prod .env as DB_PASSWORD.

CREATE DATABASE IF NOT EXISTS onyx_aip_prod
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS 'onyx_aip_prod'@'%' IDENTIFIED BY 'CHANGE_ME';

-- Least-privilege: data manipulation + schema changes (migrations), no SUPER.
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP, REFERENCES
    ON onyx_aip_prod.* TO 'onyx_aip_prod'@'%';

FLUSH PRIVILEGES;
