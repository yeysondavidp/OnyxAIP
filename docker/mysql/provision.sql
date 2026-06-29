-- ONYX AIP — MySQL provisioning (run once on the shared MySQL container)
-- Grants are scoped to the onyx_aip schema only (least-privilege, ADR-002).
--
-- Usage:
--   docker exec -i <shared_mysql_container> mysql -uroot -p < docker/mysql/provision.sql
--
-- Replace 'CHANGE_ME' with a strong password and store it in .env as DB_PASSWORD.

CREATE DATABASE IF NOT EXISTS onyx_aip
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS 'onyx_aip'@'%' IDENTIFIED BY 'CHANGE_ME';

-- Least-privilege: data manipulation + schema changes (migrations), no SUPER.
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP, REFERENCES
    ON onyx_aip.* TO 'onyx_aip'@'%';

FLUSH PRIVILEGES;
