-- Separate database for the automated tests (phpunit.xml uses arj_test).
CREATE DATABASE IF NOT EXISTS arj_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON arj_test.* TO 'arj'@'%';
FLUSH PRIVILEGES;
