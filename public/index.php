<?php
$pageTitle = 'ทนายคู่ดี | เข้าใจกฎหมาย เข้าถึงทนาย';
require_once __DIR__ . '/../includes/header.php';

$categories = [];
$featuredLawyers = [];
try {
    $categories = db()->query('SELECT name, slug, description FROM legal_categories ORDER BY id LIMIT 8')->fetchAll();
    $featuredLawyers = db()->query(
        'SELECT l.id, l.province, l.consultation_fee, l.verified, u.name, u.profile_image,
                (SELECT COALESCE(AVG(r.rating), 0) FROM reviews r WHERE r.lawyer_id = l.id) AS avg_rating
         FROM lawyers l
         JOIN users u ON u.id = l.user_id
         WHERE l.status = "approved" AND l.is_available = 1
         ORDER BY l.verified DESC, avg_rating DESC
         LIMIT 3'
    )->fetchAll();
} catch (Throwable) {
    $categories = [
        ['name' => 'กฎหมายอาญา', 'slug' => 'criminal', 'description' => 'ฉ้อโกง หมายเรียก แจ้งความ'],
        ['name' => 'กฎหมายแพ่ง', 'slug' => 'civil', 'description' => 'หนี้ สัญญา ค่าเสียหาย'],
        ['name' => 'กฎหมายแรงงาน', 'slug' => 'labor', 'description' => 'เลิกจ้าง ค่าชดเชย'],
        ['name' => 'กฎหมายธุรกิจ', 'slug' => 'business', 'description' => 'บริษัท หุ้นส่วน สัญญา'],
    ];
}

$categoryIcons = [
    'criminal' => 'shield-exclamation',
    'civil' => 'file-earmark-text',
    'family' => 'people',
    'labor' => 'briefcase',
    'business' => 'buildings',
    'land' => 'geo-alt',
    'inheritance' => 'diagram-3',
    'tax' => 'calculator',
    'consumer' => 'bag-check',
    'contract' => 'pen',
];
?>
<section class="brand-hero">
    <img class="brand-hero-image" src="<?= e(url('/assets/images/thanai-khu-dee-hero.png')) ?>" alt="ทนายกำลังให้คำปรึกษาลูกความ">
    <div class="brand-hero-shade"></div>
    <div class="container brand-hero-content">
        <div class="brand-hero-copy">
            <span class="brand-kicker"><i class="bi bi-patch-check-fill"></i> พื้นที่กฎหมายที่เข้าใจคุณ</span>
            <h1>ทนายคู่ดี</h1>
            <p class="brand-hero-lead">เริ่มจากคำถามที่คุณกังวล ให้ AI ช่วยจัดประเด็น แล้วเลือกทนายที่เหมาะกับเรื่องของคุณอย่างมั่นใจ</p>
            <div class="brand-hero-actions">
                <a class="btn btn-primary btn-lg" href="<?= e($user ? url('/user/ai-chat.php') : url('/user/login.php')) ?>"><i class="bi bi-chat-heart me-2"></i>เริ่มปรึกษาเบื้องต้น</a>
                <a class="btn btn-light btn-lg" href="<?= e(url('/public/lawyers.php')) ?>"><i class="bi bi-search me-2"></i>ค้นหาทนาย</a>
            </div>
            <div class="brand-hero-note"><i class="bi bi-shield-check"></i> คุณเป็นผู้ตัดสินใจทุกขั้นตอน ระบบจะไม่ส่งต่อเคสให้ทนายจนกว่าจะได้รับความยินยอม</div>
        </div>
    </div>
</section>

<section class="trust-strip">
    <div class="container">
        <div class="trust-grid">
            <div><i class="bi bi-person-check"></i><span><strong>ทนายผ่านการตรวจสอบ</strong><small>โปรไฟล์พร้อมข้อมูลสำคัญ</small></span></div>
            <div><i class="bi bi-stars"></i><span><strong>AI ช่วยจัดประเด็น</strong><small>เริ่มต้นได้แม้ยังไม่รู้หมวดกฎหมาย</small></span></div>
            <div><i class="bi bi-chat-square-text"></i><span><strong>คุยต่อเนื่องในระบบ</strong><small>แชต เสียง ไฟล์ และวิดีโอคอล</small></span></div>
            <div><i class="bi bi-shield-lock"></i><span><strong>ควบคุมการส่งต่อข้อมูล</strong><small>ยินยอมก่อน Match ทนายเสมอ</small></span></div>
        </div>
    </div>
</section>

<section class="section-band brand-intro">
    <div class="container">
        <div class="section-heading">
            <span class="brand-kicker">เริ่มต้นได้ง่าย</span>
            <h2>จากเรื่องที่กังวล สู่คำปรึกษาที่ตรงจุด</h2>
            <p>ทนายคู่ดีช่วยลดความสับสนในช่วงแรก และทำให้การเข้าถึงทนายเป็นขั้นตอนที่ชัดเจนขึ้น</p>
        </div>
        <div class="brand-steps">
            <?php foreach ([
                ['chat-dots', 'เล่าเรื่องที่เกิดขึ้น', 'เริ่มจากภาษาธรรมดา ไม่จำเป็นต้องรู้ศัพท์กฎหมาย'],
                ['stars', 'รับสรุปเบื้องต้นจาก AI', 'ดูหมวด ประเด็น ความเร่งด่วน และเอกสารที่ควรเตรียม'],
                ['person-check', 'เลือกทนายที่เหมาะกับคุณ', 'เปรียบเทียบข้อมูล นัดหมาย และติดตามสถานะได้ในระบบ'],
            ] as $index => $step): ?>
                <article>
                    <span class="brand-step-number">0<?= $index + 1 ?></span>
                    <i class="bi bi-<?= e($step[0]) ?>"></i>
                    <h3><?= e($step[1]) ?></h3>
                    <p><?= e($step[2]) ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section-band brand-category-section">
    <div class="container">
        <div class="section-heading section-heading-row">
            <div>
                <span class="brand-kicker">หมวดกฎหมาย</span>
                <h2>ค้นหาความช่วยเหลือที่ตรงเรื่อง</h2>
                <p>เลือกหมวดเพื่อดูทนาย หรือเริ่มจาก AI หากยังไม่แน่ใจว่าปัญหาอยู่ในกลุ่มไหน</p>
            </div>
            <a class="btn btn-outline-primary" href="<?= e(url('/public/lawyers.php')) ?>">ดูทนายทั้งหมด <i class="bi bi-arrow-right ms-2"></i></a>
        </div>
        <div class="category-grid">
            <?php foreach ($categories as $category): ?>
                <a class="category-tile" href="<?= e(url('/public/lawyers.php?category=' . urlencode($category['slug']))) ?>">
                    <i class="bi bi-<?= e($categoryIcons[$category['slug']] ?? 'folder2-open') ?>"></i>
                    <span><strong><?= e($category['name']) ?></strong><small><?= e($category['description'] ?? '') ?></small></span>
                    <i class="bi bi-arrow-up-right"></i>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section-band lawyer-showcase">
    <div class="container">
        <div class="section-heading section-heading-row">
            <div>
                <span class="brand-kicker">ทนายแนะนำ</span>
                <h2>เริ่มรู้จักทนายก่อนตัดสินใจ</h2>
                <p>ดูพื้นที่ให้บริการ ค่าปรึกษา และข้อมูลยืนยันก่อนเลือกนัดหมาย</p>
            </div>
            <a class="btn btn-outline-primary" href="<?= e(url('/public/lawyers.php')) ?>">ค้นหาทนาย <i class="bi bi-search ms-2"></i></a>
        </div>
        <div class="lawyer-showcase-grid">
            <?php foreach ($featuredLawyers as $lawyer): ?>
                <article class="lawyer-showcase-card">
                    <div class="lawyer-showcase-head">
                        <?= avatarHtml($lawyer['profile_image'] ?? null, 'person-badge') ?>
                        <span class="verified-pill"><i class="bi bi-patch-check-fill"></i> ยืนยันแล้ว</span>
                    </div>
                    <h3><?= e($lawyer['name']) ?></h3>
                    <p><i class="bi bi-geo-alt"></i> <?= e($lawyer['province']) ?></p>
                    <div class="lawyer-showcase-meta">
                        <span><small>ค่าปรึกษา</small><strong><?= e(formatMoney($lawyer['consultation_fee'])) ?></strong></span>
                        <span><small>คะแนนรีวิว</small><strong><?= e(number_format((float) $lawyer['avg_rating'], 1)) ?></strong></span>
                    </div>
                    <a class="btn btn-outline-primary w-100" href="<?= e(url('/public/lawyer-detail.php?id=' . $lawyer['id'])) ?>">ดูโปรไฟล์ทนาย</a>
                </article>
            <?php endforeach; ?>
            <?php if (!$featuredLawyers): ?>
                <div class="alert alert-info mb-0">ยังไม่มีทนายที่เปิดรับงานในขณะนี้</div>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="brand-care-band">
    <div class="container">
        <div>
            <span class="brand-kicker">พร้อมเริ่มเมื่อคุณพร้อม</span>
            <h2>เรื่องกฎหมายไม่จำเป็นต้องเริ่มจากความสับสน</h2>
            <p>เล่าเรื่องของคุณให้ AI ช่วยจัดประเด็นเบื้องต้น หรือค้นหาทนายที่ผ่านการตรวจสอบได้ทันที</p>
        </div>
        <div class="brand-care-actions">
            <a class="btn btn-primary btn-lg" href="<?= e($user ? url('/user/ai-chat.php') : url('/public/register.php')) ?>">เริ่มใช้งานทนายคู่ดี</a>
            <a class="btn btn-outline-primary btn-lg" href="<?= e(url('/public/faq.php')) ?>">อ่านคำถามที่พบบ่อย</a>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
