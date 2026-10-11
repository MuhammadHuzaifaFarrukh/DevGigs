-- DevGigs schema (3NF) matching the ERD, plus the `phone` column the signup form uses
-- and the indexes that keep the marketplace queries fast.
-- Fresh install: run this whole file.  Existing database: see the ALTER section at the bottom.

CREATE TABLE IF NOT EXISTS users (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100) NOT NULL,
    email      VARCHAR(100) NOT NULL UNIQUE,
    phone      VARCHAR(20)  NULL,
    password   VARCHAR(255) NOT NULL,
    role       ENUM('admin','client','freelancer') NOT NULL DEFAULT 'freelancer',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_deleted TINYINT(1) NOT NULL DEFAULT 0
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

-- ---------------------------------------------------------------------------
-- EXISTING DATABASE: run these once instead of the CREATE statements above.
-- Skip any line that errors with "Duplicate" (it means it already exists).
-- ---------------------------------------------------------------------------
-- ALTER TABLE users     ADD COLUMN phone VARCHAR(20) NULL AFTER email;
-- ALTER TABLE gigs      ADD INDEX idx_gigs_board (is_deleted, status, created_at);
-- ALTER TABLE gigs      ADD INDEX idx_gigs_client (client_id);
-- ALTER TABLE proposals ADD UNIQUE KEY uq_one_bid_per_gig (gig_id, freelancer_id);
-- ALTER TABLE proposals ADD INDEX idx_proposals_freelancer (freelancer_id);

-- Make yourself an admin (admins cannot be created from the signup form):
-- UPDATE users SET role = 'admin' WHERE email = 'you@example.com';
