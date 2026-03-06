-- Create databases needed by Namingo (MariaDB entrypoint runs this)
CREATE DATABASE IF NOT EXISTS `registry`;
CREATE DATABASE IF NOT EXISTS `registryTransaction`;
CREATE DATABASE IF NOT EXISTS `registryAudit`;

-- Grant access to application user
GRANT ALL PRIVILEGES ON `registry`.* TO 'namingo'@'%';
GRANT ALL PRIVILEGES ON `registryTransaction`.* TO 'namingo'@'%';
GRANT ALL PRIVILEGES ON `registryAudit`.* TO 'namingo'@'%';
FLUSH PRIVILEGES;
