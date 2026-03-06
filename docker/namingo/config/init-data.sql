-- Seed data for Namingo Docker development environment

-- Insert .test TLD
INSERT IGNORE INTO `domain_tld` (`tld`, `idn_table`, `secure`) VALUES
('.test', '', 0);

-- Insert test registrar (password: testpassword123, hashed with Argon2ID)
INSERT INTO `registrar` (
    `name`, `clid`, `pw`, `prefix`, `email`,
    `whois_server`, `rdap_server`, `url`,
    `abuse_email`, `abuse_phone`,
    `accountBalance`, `creditLimit`, `currency`, `crdate`
) VALUES (
    'Test Registrar',
    'testregistrar',
    '$argon2id$v=19$m=131072,t=6,p=4$bEh3NkZNUWwvOUJhMnNWZg$eczWs6Aq9UyE9/iRLReLChy2gG5gTBxKiQa3Q+Xpm3M',
    'TR',
    'registrar@test.example',
    'whois.test.example',
    'rdap.test.example',
    'https://test.example',
    'abuse@test.example',
    '+1.0000000000',
    10000.00,
    0.00,
    'USD',
    NOW()
);

-- Allow all IPs for the test registrar (0.0.0.0/0 = allow all)
INSERT INTO `registrar_whitelist` (`registrar_id`, `addr`)
SELECT `id`, '0.0.0.0/0' FROM `registrar` WHERE `clid` = 'testregistrar';

-- Domain pricing for .test TLD (create, renew, transfer)
INSERT INTO `domain_price` (`tldid`, `command`, `m0`, `m12`, `m24`, `m36`, `m48`, `m60`)
SELECT `id`, 'create', 0.00, 10.00, 20.00, 30.00, 40.00, 50.00 FROM `domain_tld` WHERE `tld` = '.test';

INSERT INTO `domain_price` (`tldid`, `command`, `m0`, `m12`, `m24`, `m36`, `m48`, `m60`)
SELECT `id`, 'renew', 0.00, 10.00, 20.00, 30.00, 40.00, 50.00 FROM `domain_tld` WHERE `tld` = '.test';

INSERT INTO `domain_price` (`tldid`, `command`, `m0`, `m12`, `m24`, `m36`, `m48`, `m60`)
SELECT `id`, 'transfer', 0.00, 10.00, 20.00, 30.00, 40.00, 50.00 FROM `domain_tld` WHERE `tld` = '.test';

-- Admin user for the control panel (password: admin123)
INSERT INTO `users` (`email`, `password`, `username`, `status`, `verified`, `roles_mask`, `registered`)
VALUES (
    'admin@test.example',
    '$2y$10$sAcWT9ylAmaELkcKZyWVk.1B1mommITZ.luPwq8ZlLhr3A7IyPfca',
    'admin',
    0,
    1,
    1,
    UNIX_TIMESTAMP()
);

-- Link admin user to test registrar
INSERT INTO `registrar_users` (`registrar_id`, `user_id`)
SELECT r.`id`, u.`id` FROM `registrar` r, `users` u
WHERE r.`clid` = 'testregistrar' AND u.`email` = 'admin@test.example';
