-- Database tes terpisah untuk lokal.
CREATE DATABASE IF NOT EXISTS sikg_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON sikg_test.* TO 'sikg'@'%';
FLUSH PRIVILEGES;
