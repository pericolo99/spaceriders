-- Un secondo database accanto a quello dell'app: ci si carica il dump del
-- vecchio pannello (space_riders_service) e da lì `php artisan kaukau:importa-storico`
-- copia i dati nelle tabelle nuove. L'app non ci scrive mai.
CREATE DATABASE IF NOT EXISTS `storico` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON `storico`.* TO 'kaukau'@'%';
FLUSH PRIVILEGES;
