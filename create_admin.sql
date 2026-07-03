USE traffic_system;

INSERT INTO users (email, password, role)
VALUES ('admin@traffic.com', 'admin', 'admin')
ON DUPLICATE KEY UPDATE
password = VALUES(password),
role = VALUES(role);
