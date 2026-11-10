-- Separate database used only by the automated test suite.
CREATE DATABASE IF NOT EXISTS sentra360_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON sentra360_test.* TO 'sentra'@'%';
FLUSH PRIVILEGES;
