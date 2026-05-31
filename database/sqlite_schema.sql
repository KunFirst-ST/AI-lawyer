PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT UNIQUE NOT NULL,
    phone TEXT,
    password TEXT NOT NULL,
    role TEXT DEFAULT 'user',
    status TEXT DEFAULT 'active',
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_users_role ON users(role);

CREATE TABLE IF NOT EXISTS legal_categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    slug TEXT UNIQUE NOT NULL,
    description TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS lawyers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    license_number TEXT,
    province TEXT,
    bio TEXT,
    experience_years INTEGER DEFAULT 0,
    consultation_fee NUMERIC DEFAULT 0,
    complex_case_experience INTEGER DEFAULT 0,
    is_available INTEGER DEFAULT 1,
    verified INTEGER DEFAULT 0,
    status TEXT DEFAULT 'pending',
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    UNIQUE (user_id)
);
CREATE INDEX IF NOT EXISTS idx_lawyers_status_available ON lawyers(status, is_available);

CREATE TABLE IF NOT EXISTS lawyer_categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    lawyer_id INTEGER NOT NULL,
    category_id INTEGER NOT NULL,
    FOREIGN KEY (lawyer_id) REFERENCES lawyers(id),
    FOREIGN KEY (category_id) REFERENCES legal_categories(id),
    UNIQUE (lawyer_id, category_id)
);

CREATE TABLE IF NOT EXISTS cases (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    title TEXT,
    description TEXT,
    primary_category_id INTEGER NULL,
    complexity_level TEXT DEFAULT 'low',
    urgency TEXT DEFAULT 'medium',
    province TEXT,
    consultation_type TEXT DEFAULT 'any',
    budget_min NUMERIC NULL,
    budget_max NUMERIC NULL,
    ai_summary TEXT,
    user_wants_lawyer INTEGER DEFAULT NULL,
    match_status TEXT DEFAULT 'not_asked',
    lawyer_review_required INTEGER DEFAULT 0,
    status TEXT DEFAULT 'ai_consulting',
    matched_at TEXT NULL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (primary_category_id) REFERENCES legal_categories(id)
);
CREATE INDEX IF NOT EXISTS idx_cases_user_status ON cases(user_id, status);
CREATE INDEX IF NOT EXISTS idx_cases_match_status ON cases(match_status);

CREATE TABLE IF NOT EXISTS case_categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    case_id INTEGER NOT NULL,
    category_id INTEGER NOT NULL,
    type TEXT DEFAULT 'related',
    confidence_score NUMERIC DEFAULT 0,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES cases(id),
    FOREIGN KEY (category_id) REFERENCES legal_categories(id),
    UNIQUE (case_id, category_id, type)
);

CREATE TABLE IF NOT EXISTS case_legal_issues (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    case_id INTEGER NOT NULL,
    category_id INTEGER NULL,
    issue_title TEXT,
    issue_summary TEXT,
    risk_level TEXT DEFAULT 'medium',
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES cases(id),
    FOREIGN KEY (category_id) REFERENCES legal_categories(id)
);

CREATE TABLE IF NOT EXISTS ai_chats (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    case_id INTEGER NULL,
    user_message TEXT NOT NULL,
    ai_response TEXT NOT NULL,
    ai_json TEXT NULL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (case_id) REFERENCES cases(id)
);

CREATE TABLE IF NOT EXISTS case_matches (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    case_id INTEGER NOT NULL,
    lawyer_id INTEGER NOT NULL,
    match_score NUMERIC DEFAULT 0,
    match_reason TEXT,
    status TEXT DEFAULT 'suggested',
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES cases(id),
    FOREIGN KEY (lawyer_id) REFERENCES lawyers(id),
    UNIQUE (case_id, lawyer_id)
);

CREATE TABLE IF NOT EXISTS bookings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    case_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    lawyer_id INTEGER NOT NULL,
    booking_date TEXT,
    booking_time TEXT,
    consultation_type TEXT,
    price NUMERIC,
    status TEXT DEFAULT 'pending',
    lawyer_response_status TEXT DEFAULT 'pending',
    lawyer_note TEXT NULL,
    responded_at TEXT NULL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES cases(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (lawyer_id) REFERENCES lawyers(id)
);

CREATE TABLE IF NOT EXISTS case_events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    case_id INTEGER NOT NULL,
    actor_user_id INTEGER NULL,
    event_type TEXT NOT NULL,
    title TEXT NOT NULL,
    details TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES cases(id),
    FOREIGN KEY (actor_user_id) REFERENCES users(id)
);
CREATE INDEX IF NOT EXISTS idx_case_events_case_created ON case_events (case_id, created_at);

CREATE TABLE IF NOT EXISTS audit_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    actor_user_id INTEGER NULL,
    action TEXT NOT NULL,
    entity_type TEXT NOT NULL,
    entity_id INTEGER NULL,
    details TEXT,
    ip_address TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (actor_user_id) REFERENCES users(id)
);
CREATE INDEX IF NOT EXISTS idx_audit_logs_created ON audit_logs (created_at);

CREATE TABLE IF NOT EXISTS payments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    booking_id INTEGER NOT NULL,
    amount NUMERIC NOT NULL,
    payment_method TEXT DEFAULT 'bank_transfer',
    slip_image TEXT,
    status TEXT DEFAULT 'pending',
    admin_note TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id)
);
CREATE INDEX IF NOT EXISTS idx_payments_status ON payments(status);

CREATE TABLE IF NOT EXISTS reviews (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    booking_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    lawyer_id INTEGER NOT NULL,
    rating INTEGER NOT NULL,
    comment TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (lawyer_id) REFERENCES lawyers(id),
    UNIQUE (booking_id)
);

CREATE TABLE IF NOT EXISTS messages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    case_id INTEGER NULL,
    booking_id INTEGER NULL,
    sender_id INTEGER NOT NULL,
    receiver_id INTEGER NOT NULL,
    message TEXT,
    file_path TEXT,
    message_type TEXT DEFAULT 'text',
    call_type TEXT NULL,
    call_url TEXT NULL,
    call_room TEXT NULL,
    is_read INTEGER DEFAULT 0,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES cases(id),
    FOREIGN KEY (booking_id) REFERENCES bookings(id),
    FOREIGN KEY (sender_id) REFERENCES users(id),
    FOREIGN KEY (receiver_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS documents (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    case_id INTEGER NULL,
    lawyer_id INTEGER NULL,
    document_type TEXT DEFAULT 'other',
    file_path TEXT NOT NULL,
    original_name TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (case_id) REFERENCES cases(id),
    FOREIGN KEY (lawyer_id) REFERENCES lawyers(id)
);

CREATE TABLE IF NOT EXISTS call_signals (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    room TEXT NOT NULL,
    sender_id INTEGER NOT NULL,
    receiver_id INTEGER NOT NULL,
    signal_type TEXT NOT NULL,
    payload TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id),
    FOREIGN KEY (receiver_id) REFERENCES users(id)
);
CREATE INDEX IF NOT EXISTS idx_call_signals_room_receiver_id ON call_signals (room, receiver_id, id);

CREATE TABLE IF NOT EXISTS commissions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    booking_id INTEGER NOT NULL,
    lawyer_id INTEGER NOT NULL,
    gross_amount NUMERIC,
    commission_percent NUMERIC,
    commission_amount NUMERIC,
    lawyer_amount NUMERIC,
    status TEXT DEFAULT 'pending',
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id),
    FOREIGN KEY (lawyer_id) REFERENCES lawyers(id)
);

CREATE TABLE IF NOT EXISTS notifications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    title TEXT,
    message TEXT,
    type TEXT,
    is_read INTEGER DEFAULT 0,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS social_accounts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    provider TEXT NOT NULL,
    provider_user_id TEXT NOT NULL,
    provider_email TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE (provider, provider_user_id),
    UNIQUE (user_id, provider)
);

CREATE TABLE IF NOT EXISTS app_settings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    setting_key TEXT UNIQUE NOT NULL,
    setting_value TEXT,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS contact_messages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT NOT NULL,
    phone TEXT,
    subject TEXT,
    message TEXT NOT NULL,
    status TEXT DEFAULT 'new',
    admin_note TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_contact_messages_status ON contact_messages(status);
CREATE INDEX IF NOT EXISTS idx_contact_messages_created_at ON contact_messages(created_at);

INSERT OR IGNORE INTO legal_categories (id, name, slug, description) VALUES
(1, 'Criminal Law', 'criminal', 'Fraud, assault, threats, police reports, and criminal cases'),
(2, 'Civil Law', 'civil', 'Debt, damages, lawsuits, and general disputes'),
(3, 'Family Law', 'family', 'Divorce, child support, custody, and family property'),
(4, 'Labor Law', 'labor', 'Dismissal, compensation, wages, and employment contracts'),
(5, 'Business Law', 'business', 'Company, partnership, contracts, and commercial disputes'),
(6, 'Land Law', 'land', 'Title deeds, possession, trespass, and land disputes'),
(7, 'Inheritance Law', 'inheritance', 'Wills, heirs, estate administration, and inheritance'),
(8, 'Tax Law', 'tax', 'Tax disputes and business taxation'),
(9, 'Consumer Law', 'consumer', 'Online purchases, products, services, and consumer rights'),
(10, 'Intellectual Property', 'intellectual_property', 'Copyright, trademarks, patents, and IP disputes'),
(11, 'Immigration', 'immigration', 'Visa, work permit, and immigration matters'),
(12, 'Bankruptcy', 'bankruptcy', 'Bankruptcy, debt restructuring, and rehabilitation'),
(13, 'Contract Law', 'contract', 'Contract review, drafting, and breach of contract');

INSERT OR IGNORE INTO users (id, name, email, phone, password, role, status) VALUES
(1, 'System Admin', 'admin@example.com', '020000000', '$2y$10$aXlIs6fs6YOuRxNex7Jsu.RxXs4djVWQ/VzklH.euD9UP7.nBea6e', 'admin', 'active'),
(2, 'Demo User', 'user@example.com', '0811111111', '$2y$10$6badcuvC.P1MAFAcl6VJ6uahr53ME2TBZNHR4cKMWmJvzJoQZN5iu', 'user', 'active'),
(3, 'Criminal Lawyer Demo', 'criminal.lawyer@example.com', '0822222222', '$2y$10$wHKm4Ts6nDJD3GArPRjwt.aVjLAjpKtqluVFSJ0qKpfrX0zVvX.ki', 'lawyer', 'active'),
(4, 'Business Lawyer Demo', 'business.lawyer@example.com', '0833333333', '$2y$10$wHKm4Ts6nDJD3GArPRjwt.aVjLAjpKtqluVFSJ0qKpfrX0zVvX.ki', 'lawyer', 'active'),
(5, 'Family Lawyer Demo', 'family.lawyer@example.com', '0844444444', '$2y$10$wHKm4Ts6nDJD3GArPRjwt.aVjLAjpKtqluVFSJ0qKpfrX0zVvX.ki', 'lawyer', 'active');

INSERT OR IGNORE INTO lawyers (id, user_id, license_number, province, bio, experience_years, consultation_fee, complex_case_experience, is_available, verified, status) VALUES
(1, 3, 'LAW-CR-1001', 'Bangkok', 'Criminal law, fraud, assault, and online consumer disputes.', 8, 1200, 1, 1, 1, 'approved'),
(2, 4, 'LAW-BZ-1002', 'Bangkok', 'Business, contracts, tax, and SME disputes.', 11, 1800, 1, 1, 1, 'approved'),
(3, 5, 'LAW-FM-1003', 'Chiang Mai', 'Family, inheritance, and sensitive negotiation cases.', 6, 900, 0, 1, 1, 'approved');

INSERT OR IGNORE INTO lawyer_categories (lawyer_id, category_id) VALUES
(1, 1), (1, 2), (1, 9),
(2, 5), (2, 8), (2, 13), (2, 2),
(3, 3), (3, 7), (3, 2);

INSERT OR IGNORE INTO app_settings (setting_key, setting_value) VALUES
('legal_disclaimer', 'AI provides preliminary legal issue spotting only and is not direct legal advice from a lawyer.'),
('auto_match_after_consent', '1'),
('commission_percent', '20'),
('bank_account_name', 'ทนายคู่ดี'),
('bank_account_number', '000-0-00000-0'),
('promptpay_id', '0999999999');
