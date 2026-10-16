-- DevGigs schema (3NF) matching the ERD (roles, users, gigs, proposals, reviews), plus the `phone` column the signup form uses
-- and the indexes that keep the marketplace queries fast.

-- Lookup table: one row per role. users.role_id points here.
CREATE TABLE IF NOT EXISTS roles (
    id   TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(20) NOT NULL UNIQUE            -- admin | client | freelancer
) ENGINE=InnoDB;

INSERT IGNORE INTO roles (name) VALUES ('admin'), ('client'), ('freelancer');

CREATE TABLE IF NOT EXISTS users (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100) NOT NULL,
    email      VARCHAR(100) NOT NULL UNIQUE,
    phone      VARCHAR(20)  NULL,
    password   VARCHAR(255) NOT NULL,
    role_id    TINYINT UNSIGNED NOT NULL,        -- which role this account has (one role per account)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_deleted TINYINT(1) NOT NULL DEFAULT 0,
    CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id),
    INDEX idx_users_role (role_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS gigs (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    client_id   INT NOT NULL,
    title       VARCHAR(150) NOT NULL,
    description TEXT NOT NULL,
    budget      DECIMAL(10,2) NOT NULL,
    status      ENUM('open','in_progress','completed','cancelled') NOT NULL DEFAULT 'open',
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_deleted  TINYINT(1) NOT NULL DEFAULT 0,
    CONSTRAINT fk_gigs_client FOREIGN KEY (client_id) REFERENCES users(id),
    INDEX idx_gigs_board (is_deleted, status, created_at),
    INDEX idx_gigs_client (client_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS proposals (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    gig_id        INT NOT NULL,
    freelancer_id INT NOT NULL,
    cover_letter  TEXT NOT NULL,
    bid_amount    DECIMAL(10,2) NOT NULL,
    status        ENUM('pending','accepted','rejected') NOT NULL DEFAULT 'pending',
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_deleted    TINYINT(1) NOT NULL DEFAULT 0,
    CONSTRAINT fk_proposals_gig        FOREIGN KEY (gig_id)        REFERENCES gigs(id),
    CONSTRAINT fk_proposals_freelancer FOREIGN KEY (freelancer_id) REFERENCES users(id),
    UNIQUE KEY uq_one_bid_per_gig (gig_id, freelancer_id),   -- a freelancer bids once per gig
    INDEX idx_proposals_freelancer (freelancer_id)
) ENGINE=InnoDB;

-- One review per completed gig: the client rates the freelancer who did the work.
-- freelancer_id is stored (not just derived from the accepted proposal) so a freelancer's
-- rating can be read with a single indexed lookup.
CREATE TABLE IF NOT EXISTS reviews (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    gig_id        INT NOT NULL,
    reviewer_id   INT NOT NULL,          -- the client who posted the gig
    freelancer_id INT NOT NULL,          -- the freelancer being reviewed
    rating        TINYINT UNSIGNED NOT NULL,
    comment       TEXT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_deleted    TINYINT(1) NOT NULL DEFAULT 0,
    CONSTRAINT fk_reviews_gig        FOREIGN KEY (gig_id)        REFERENCES gigs(id),
    CONSTRAINT fk_reviews_reviewer   FOREIGN KEY (reviewer_id)   REFERENCES users(id),
    CONSTRAINT fk_reviews_freelancer FOREIGN KEY (freelancer_id) REFERENCES users(id),
    CONSTRAINT chk_reviews_rating    CHECK (rating BETWEEN 1 AND 5),
    UNIQUE KEY uq_one_review_per_gig (gig_id),
    INDEX idx_reviews_freelancer (freelancer_id, is_deleted)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------------
-- EXISTING DATABASE: the `reviews` table above is a plain CREATE TABLE IF NOT EXISTS, so it is
-- safe to run on an existing database (it will not touch your other tables or data).
-- EXISTING DATABASE: run these once instead of the CREATE statements above.
-- Skip any line that errors with "Duplicate" (it means it already exists).
-- ---------------------------------------------------------------------------
-- Moving users.role (ENUM) to the roles table? Run database/migrate_roles.sql once.
-- ALTER TABLE users     ADD COLUMN phone VARCHAR(20) NULL AFTER email;
-- ALTER TABLE gigs      ADD INDEX idx_gigs_board (is_deleted, status, created_at);
-- ALTER TABLE gigs      ADD INDEX idx_gigs_client (client_id);
-- ALTER TABLE proposals ADD UNIQUE KEY uq_one_bid_per_gig (gig_id, freelancer_id);
-- ALTER TABLE proposals ADD INDEX idx_proposals_freelancer (freelancer_id);

-- Make yourself an admin (admins cannot be created from the signup form):
-- UPDATE users SET role_id = (SELECT id FROM roles WHERE name = 'admin') WHERE email = 'you@example.com';
