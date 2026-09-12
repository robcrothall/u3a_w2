-- 002_create_users_roles_payments.sql
-- Fresh users/roles/payments schema for w2. Not a migration of the old
-- w1 `users` table - that data gets imported separately once the
-- membership spreadsheet cleanup is done, with fresh temporary passwords
-- (old crypt() hashes can't be converted to bcrypt, only replaced).

CREATE TABLE IF NOT EXISTS users (
    id                    INT AUTO_INCREMENT PRIMARY KEY,
    email                 VARCHAR(255) NOT NULL,
    password_hash         VARCHAR(255) NOT NULL,
    first_name            VARCHAR(100) NOT NULL,
    surname               VARCHAR(100) NOT NULL,
    given_name            VARCHAR(100) NULL,
    must_change_password  TINYINT(1) NOT NULL DEFAULT 0,
    last_logon            TIMESTAMP NULL DEFAULT NULL,
    created               TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    changed               TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS roles (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    role_name  VARCHAR(50) NOT NULL,
    UNIQUE KEY uq_roles_name (role_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO roles (role_name) VALUES ('registered'), ('paid_up'), ('admin');

CREATE TABLE IF NOT EXISTS user_roles (
    id       INT AUTO_INCREMENT PRIMARY KEY,
    user_id  INT NOT NULL,
    role_id  INT NOT NULL,
    expiry   DATE NULL DEFAULT NULL,  -- e.g. paid_up expires end of membership year; NULL = doesn't expire
    created  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_user_roles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_user_roles_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    UNIQUE KEY uq_user_role (user_id, role_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS membership_payments (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    user_id       INT NOT NULL,
    year          INT NOT NULL,
    amount        DECIMAL(8,2) NULL,
    paid_date     DATE NOT NULL,
    recorded_by   INT NULL,  -- admin user id who captured this payment
    created       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_payments_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_payments_recorded_by FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY uq_payment_user_year (user_id, year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
