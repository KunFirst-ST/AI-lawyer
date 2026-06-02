SET NAMES utf8mb4;
SET time_zone = '+07:00';

CREATE DATABASE IF NOT EXISTS ai_lawyer_platform
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE ai_lawyer_platform;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    phone VARCHAR(50),
    password VARCHAR(255) NOT NULL,
    role ENUM('user','lawyer','admin') DEFAULT 'user',
    status ENUM('active','inactive','banned') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_users_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS legal_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lawyers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    license_number VARCHAR(100),
    province VARCHAR(100),
    bio TEXT,
    experience_years INT DEFAULT 0,
    consultation_fee DECIMAL(10,2) DEFAULT 0,
    complex_case_experience TINYINT(1) DEFAULT 0,
    is_available TINYINT(1) DEFAULT 1,
    verified TINYINT(1) DEFAULT 0,
    status ENUM('pending','approved','rejected','suspended') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    UNIQUE KEY lawyers_user_unique (user_id),
    KEY idx_lawyers_status_available (status, is_available)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lawyer_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lawyer_id INT NOT NULL,
    category_id INT NOT NULL,
    FOREIGN KEY (lawyer_id) REFERENCES lawyers(id),
    FOREIGN KEY (category_id) REFERENCES legal_categories(id),
    UNIQUE KEY lawyer_category_unique (lawyer_id, category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255),
    description TEXT,
    primary_category_id INT NULL,
    complexity_level ENUM('low','medium','high') DEFAULT 'low',
    urgency ENUM('low','medium','high','critical') DEFAULT 'medium',
    province VARCHAR(100),
    consultation_type ENUM('chat','phone','video','onsite','any') DEFAULT 'any',
    budget_min DECIMAL(10,2) NULL,
    budget_max DECIMAL(10,2) NULL,
    ai_summary TEXT,
    user_wants_lawyer TINYINT(1) DEFAULT NULL,
    match_status ENUM(
        'not_asked',
        'asked',
        'declined_by_user',
        'requested_by_user',
        'matched'
    ) DEFAULT 'not_asked',
    lawyer_review_required TINYINT(1) DEFAULT 0,
    status ENUM('ai_consulting','waiting_match','matched','booked','in_progress','closed','cancelled') DEFAULT 'ai_consulting',
    matched_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (primary_category_id) REFERENCES legal_categories(id),
    KEY idx_cases_user_status (user_id, status),
    KEY idx_cases_match_status (match_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS case_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT NOT NULL,
    category_id INT NOT NULL,
    type ENUM('primary','related') DEFAULT 'related',
    confidence_score DECIMAL(5,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES cases(id),
    FOREIGN KEY (category_id) REFERENCES legal_categories(id),
    UNIQUE KEY case_category_unique (case_id, category_id, type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS case_legal_issues (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT NOT NULL,
    category_id INT NULL,
    issue_title VARCHAR(255),
    issue_summary TEXT,
    risk_level ENUM('low','medium','high','critical') DEFAULT 'medium',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES cases(id),
    FOREIGN KEY (category_id) REFERENCES legal_categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_chats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    case_id INT NULL,
    user_message TEXT NOT NULL,
    ai_response TEXT NOT NULL,
    ai_json JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (case_id) REFERENCES cases(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS case_matches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT NOT NULL,
    lawyer_id INT NOT NULL,
    match_score DECIMAL(10,2) DEFAULT 0,
    match_reason TEXT,
    status ENUM('suggested','viewed','selected','rejected') DEFAULT 'suggested',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES cases(id),
    FOREIGN KEY (lawyer_id) REFERENCES lawyers(id),
    UNIQUE KEY case_lawyer_unique (case_id, lawyer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT NOT NULL,
    user_id INT NOT NULL,
    lawyer_id INT NOT NULL,
    booking_date DATE,
    booking_time TIME,
    consultation_type ENUM('chat','phone','video','onsite'),
    price DECIMAL(10,2),
    status ENUM('pending','confirmed','completed','cancelled') DEFAULT 'pending',
    lawyer_response_status VARCHAR(20) DEFAULT 'pending',
    lawyer_note TEXT NULL,
    responded_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES cases(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (lawyer_id) REFERENCES lawyers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS case_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT NOT NULL,
    actor_user_id INT NULL,
    event_type VARCHAR(60) NOT NULL,
    title VARCHAR(255) NOT NULL,
    details TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES cases(id),
    FOREIGN KEY (actor_user_id) REFERENCES users(id),
    KEY idx_case_events_case_created (case_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    actor_user_id INT NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(60) NOT NULL,
    entity_id INT NULL,
    details TEXT,
    ip_address VARCHAR(64),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (actor_user_id) REFERENCES users(id),
    KEY idx_audit_logs_created (created_at),
    KEY idx_audit_logs_action_created (action, created_at),
    KEY idx_audit_logs_actor_created (actor_user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('bank_transfer','promptpay','gateway') DEFAULT 'bank_transfer',
    slip_image VARCHAR(255),
    status ENUM('pending','approved','rejected','refunded') DEFAULT 'pending',
    admin_note TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id),
    KEY idx_payments_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    user_id INT NOT NULL,
    lawyer_id INT NOT NULL,
    rating INT NOT NULL,
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (lawyer_id) REFERENCES lawyers(id),
    UNIQUE KEY reviews_booking_unique (booking_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT NULL,
    booking_id INT NULL,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    message TEXT,
    file_path VARCHAR(255),
    message_type VARCHAR(20) DEFAULT 'text',
    call_type VARCHAR(20) NULL,
    call_url VARCHAR(255) NULL,
    call_room VARCHAR(80) NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES cases(id),
    FOREIGN KEY (booking_id) REFERENCES bookings(id),
    FOREIGN KEY (sender_id) REFERENCES users(id),
    FOREIGN KEY (receiver_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    case_id INT NULL,
    lawyer_id INT NULL,
    document_type ENUM('case_document','lawyer_license','id_card','payment_slip','other') DEFAULT 'other',
    file_path VARCHAR(255) NOT NULL,
    original_name VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (case_id) REFERENCES cases(id),
    FOREIGN KEY (lawyer_id) REFERENCES lawyers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS call_signals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room VARCHAR(80) NOT NULL,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    signal_type VARCHAR(20) NOT NULL,
    payload LONGTEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id),
    FOREIGN KEY (receiver_id) REFERENCES users(id),
    KEY idx_call_signals_room_receiver_id (room, receiver_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS commissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    lawyer_id INT NOT NULL,
    gross_amount DECIMAL(10,2),
    commission_percent DECIMAL(5,2),
    commission_amount DECIMAL(10,2),
    lawyer_amount DECIMAL(10,2),
    status ENUM('pending','paid') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id),
    FOREIGN KEY (lawyer_id) REFERENCES lawyers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255),
    message TEXT,
    type VARCHAR(100),
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    notification_id INT NULL,
    user_id INT NULL,
    recipient_email VARCHAR(255) NOT NULL,
    recipient_name VARCHAR(255),
    subject VARCHAR(255) NOT NULL,
    html_body LONGTEXT NOT NULL,
    text_body LONGTEXT NOT NULL,
    notification_type VARCHAR(100) DEFAULT 'system',
    status VARCHAR(20) DEFAULT 'queued',
    attempts INT DEFAULT 0,
    last_error TEXT,
    sent_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (notification_id) REFERENCES notifications(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    KEY idx_email_notifications_status_created (status, created_at),
    KEY idx_email_notifications_user_type_created (user_id, notification_type, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS social_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    provider VARCHAR(30) NOT NULL,
    provider_user_id VARCHAR(255) NOT NULL,
    provider_email VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY social_provider_account_unique (provider, provider_user_id),
    UNIQUE KEY social_user_provider_unique (user_id, provider)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS app_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contact_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(50),
    subject VARCHAR(255),
    message TEXT NOT NULL,
    status ENUM('new','in_progress','closed') DEFAULT 'new',
    admin_note TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_contact_messages_status (status),
    KEY idx_contact_messages_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO legal_categories (name, slug, description) VALUES
('กฎหมายอาญา', 'criminal', 'คดีอาญา ฉ้อโกง ทำร้ายร่างกาย ยักยอก หมายเรียก'),
('กฎหมายแพ่ง', 'civil', 'หนี้ สัญญา เรียกค่าเสียหาย ฟ้องร้องทั่วไป'),
('กฎหมายครอบครัว', 'family', 'หย่า บุตร ค่าเลี้ยงดู สินสมรส'),
('กฎหมายแรงงาน', 'labor', 'เลิกจ้าง ค่าชดเชย เงินเดือน สัญญาจ้าง'),
('กฎหมายธุรกิจ', 'business', 'บริษัท หุ้นส่วน กรรมการ ธุรกิจ'),
('กฎหมายที่ดิน', 'land', 'โฉนด ที่ดิน บุกรุก ครอบครองปรปักษ์'),
('กฎหมายมรดก', 'inheritance', 'พินัยกรรม ทายาท แบ่งมรดก'),
('กฎหมายภาษี', 'tax', 'ภาษีย้อนหลัง สรรพากร ภาษีธุรกิจ'),
('กฎหมายผู้บริโภค', 'consumer', 'ซื้อขายออนไลน์ สินค้า บริการ ผู้บริโภค'),
('ทรัพย์สินทางปัญญา', 'intellectual_property', 'ลิขสิทธิ์ เครื่องหมายการค้า สิทธิบัตร'),
('ตรวจคนเข้าเมือง', 'immigration', 'วีซ่า ใบอนุญาตทำงาน ต่างชาติ'),
('ล้มละลาย', 'bankruptcy', 'ล้มละลาย ฟื้นฟูกิจการ หนี้สิน'),
('สัญญา', 'contract', 'ตรวจสัญญา ร่างสัญญา ผิดสัญญา');

INSERT IGNORE INTO users (name, email, phone, password, role, status) VALUES
('ผู้ดูแลระบบ', 'admin@example.com', '020000000', '$2y$10$aXlIs6fs6YOuRxNex7Jsu.RxXs4djVWQ/VzklH.euD9UP7.nBea6e', 'admin', 'active'),
('ผู้ใช้ตัวอย่าง', 'user@example.com', '0811111111', '$2y$10$6badcuvC.P1MAFAcl6VJ6uahr53ME2TBZNHR4cKMWmJvzJoQZN5iu', 'user', 'active'),
('ทนายอาญา ตัวอย่าง', 'criminal.lawyer@example.com', '0822222222', '$2y$10$wHKm4Ts6nDJD3GArPRjwt.aVjLAjpKtqluVFSJ0qKpfrX0zVvX.ki', 'lawyer', 'active'),
('ทนายธุรกิจ ตัวอย่าง', 'business.lawyer@example.com', '0833333333', '$2y$10$wHKm4Ts6nDJD3GArPRjwt.aVjLAjpKtqluVFSJ0qKpfrX0zVvX.ki', 'lawyer', 'active'),
('ทนายครอบครัว ตัวอย่าง', 'family.lawyer@example.com', '0844444444', '$2y$10$wHKm4Ts6nDJD3GArPRjwt.aVjLAjpKtqluVFSJ0qKpfrX0zVvX.ki', 'lawyer', 'active');

INSERT IGNORE INTO lawyers (user_id, license_number, province, bio, experience_years, consultation_fee, complex_case_experience, is_available, verified, status) VALUES
(3, 'LAW-CR-1001', 'กรุงเทพมหานคร', 'ดูแลคดีอาญา ฉ้อโกง และคดีผู้บริโภคออนไลน์ เน้นอธิบายขั้นตอนให้เข้าใจง่าย', 8, 1200, 1, 1, 1, 'approved'),
(4, 'LAW-BZ-1002', 'กรุงเทพมหานคร', 'ให้คำปรึกษาคดีบริษัท สัญญา ภาษี และข้อพิพาททางธุรกิจสำหรับ SME', 11, 1800, 1, 1, 1, 'approved'),
(5, 'LAW-FM-1003', 'เชียงใหม่', 'เชี่ยวชาญคดีครอบครัว มรดก และการเจรจาข้อตกลงที่ละเอียดอ่อน', 6, 900, 0, 1, 1, 'approved');

INSERT IGNORE INTO lawyer_categories (lawyer_id, category_id) VALUES
(1, 1), (1, 2), (1, 9),
(2, 5), (2, 8), (2, 13), (2, 2),
(3, 3), (3, 7), (3, 2);

INSERT INTO reviews (booking_id, user_id, lawyer_id, rating, comment)
SELECT 1, 2, 1, 5, 'ให้คำแนะนำชัดเจน' FROM DUAL WHERE FALSE;

INSERT IGNORE INTO app_settings (setting_key, setting_value) VALUES
('legal_disclaimer', 'AI ช่วยวิเคราะห์ปัญหากฎหมายเบื้องต้นเท่านั้น ไม่ใช่คำปรึกษาทางกฎหมายจากทนายโดยตรง'),
('auto_match_after_consent', '1'),
('commission_percent', '20'),
('bank_account_name', 'ทนายคู่ดี'),
('bank_account_number', '000-0-00000-0'),
('promptpay_id', '0999999999');
