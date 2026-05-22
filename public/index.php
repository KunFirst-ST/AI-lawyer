<?php
$pageTitle = 'AI Lawyer Matching Platform';
require_once __DIR__ . '/../includes/header.php';

$categories = [];
$featuredLawyers = [];
try {
    $categories = db()->query('SELECT name, slug, description FROM legal_categories ORDER BY id LIMIT 8')->fetchAll();
    $featuredLawyers = db()->query(
        'SELECT l.id, l.province, l.consultation_fee, l.verified, u.name,
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
?>
<section class="hero section-band">
    <div class="container">
        <div class="row align-items-center g-4">
            <div class="col-lg-6">
                <span class="legal-badge mb-3"><i class="bi bi-stars"></i> AI Legal Assistant สำหรับประเทศไทย</span>
                <h1 class="display-5 fw-bold mb-3">ถามปัญหากฎหมายกับ AI แล้วเลือกให้ระบบช่วยหาทนายเมื่อคุณพร้อม</h1>
                <p class="lead text-muted mb-4">AI ช่วยวิเคราะห์ปัญหากฎหมายเบื้องต้นเท่านั้น ไม่ใช่คำปรึกษาทางกฎหมายจากทนายโดยตรง</p>
                <p class="text-muted">หากต้องการ ระบบสามารถช่วยจับคู่ทนายที่เหมาะสมให้คุณได้ โดยจะถามความยินยอมก่อนทุกครั้ง</p>
                <div class="d-flex flex-wrap gap-2 mt-4">
                    <a class="btn btn-primary btn-lg" href="<?= e($user ? url('/user/ai-chat.php') : url('/user/login.php')) ?>"><i class="bi bi-chat-dots me-2"></i>ถาม AI ฟรี</a>
                    <a class="btn btn-outline-primary btn-lg" href="<?= e(url('/public/lawyers.php')) ?>"><i class="bi bi-search me-2"></i>ค้นหาทนาย</a>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="hero-visual app-card p-4">
                    <div class="hero-visual-inner">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div>
                                <div class="small text-white-50">Case Analysis</div>
                                <h3 class="h5 mb-0">AI วิเคราะห์เบื้องต้น</h3>
                            </div>
                            <span class="badge text-bg-success">Consent Required</span>
                        </div>
                        <div class="bg-white text-dark p-3 rounded-2 mb-3">
                            <div class="small text-muted">หมวดหลัก</div>
                            <div class="fw-bold">กฎหมายอาญา</div>
                        </div>
                        <div class="row g-3">
                            <div class="col-6"><div class="bg-white text-dark p-3 rounded-2"><div class="small text-muted">ซับซ้อน</div><div class="fw-bold">ปานกลาง</div></div></div>
                            <div class="col-6"><div class="bg-white text-dark p-3 rounded-2"><div class="small text-muted">เร่งด่วน</div><div class="fw-bold">สูง</div></div></div>
                        </div>
                        <div class="mt-4 p-3 border border-light rounded-2">
                            <div class="fw-semibold mb-2">ต้องการให้ระบบช่วยหาทนายไหม?</div>
                            <div class="d-flex gap-2">
                                <span class="btn btn-light btn-sm disabled">ต้องการหาทนาย</span>
                                <span class="btn btn-outline-light btn-sm disabled">ยังไม่ต้องการ</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section-band bg-white">
    <div class="container">
        <div class="row g-4 align-items-start">
            <div class="col-lg-4">
                <h2 class="h3 fw-bold">AI Legal Assistant ทำอะไรได้บ้าง</h2>
                <p class="text-muted">ระบบช่วยแยกหมวดกฎหมาย วิเคราะห์ความซับซ้อน ความเร่งด่วน และแนะนำเอกสารที่ควรเตรียม โดยไม่จับคู่ทนายทันทีหลังวิเคราะห์</p>
            </div>
            <div class="col-lg-8">
                <div class="row g-3">
                    <?php foreach (['แยกหมวดหลักและหมวดเกี่ยวข้อง', 'ประเมินความซับซ้อนและเร่งด่วน', 'แนะนำเอกสารที่ควรเตรียม', 'ถามความยินยอมก่อน Match เสมอ'] as $item): ?>
                        <div class="col-md-6">
                            <div class="app-card p-3 h-100">
                                <span class="icon-pill mb-3"><i class="bi bi-check2-circle"></i></span>
                                <div class="fw-semibold"><?= e($item) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section-band">
    <div class="container">
        <div class="d-flex justify-content-between align-items-end mb-3">
            <div>
                <h2 class="h3 fw-bold mb-1">หมวดกฎหมายยอดนิยม</h2>
                <p class="text-muted mb-0">รองรับเคสหลายหมวดในคดีเดียว</p>
            </div>
        </div>
        <div class="row g-3">
            <?php foreach ($categories as $category): ?>
                <div class="col-md-3">
                    <a class="app-card p-3 h-100 d-block text-dark" href="<?= e(url('/public/lawyers.php?category=' . urlencode($category['slug']))) ?>">
                        <div class="fw-bold"><?= e($category['name']) ?></div>
                        <div class="small-muted mt-1"><?= e($category['description'] ?? '') ?></div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section-band bg-white">
    <div class="container">
        <div class="d-flex justify-content-between align-items-end mb-3">
            <div>
                <h2 class="h3 fw-bold mb-1">ทนายแนะนำ</h2>
                <p class="text-muted mb-0">แสดงเฉพาะทนายที่ผ่านการอนุมัติจากแอดมิน</p>
            </div>
            <a href="<?= e(url('/public/lawyers.php')) ?>" class="btn btn-outline-primary">ดูทั้งหมด</a>
        </div>
        <div class="row g-3">
            <?php foreach ($featuredLawyers as $lawyer): ?>
                <div class="col-md-4">
                    <div class="app-card p-3 h-100">
                        <div class="d-flex gap-3">
                            <div class="profile-avatar"><i class="bi bi-person-badge"></i></div>
                            <div>
                                <h3 class="h6 fw-bold mb-1"><?= e($lawyer['name']) ?></h3>
                                <div class="small-muted"><?= e($lawyer['province']) ?> · <?= e(formatMoney($lawyer['consultation_fee'])) ?></div>
                                <div class="mt-2">
                                    <span class="badge text-bg-success">Verified</span>
                                    <span class="badge text-bg-light text-dark">รีวิว <?= e(number_format((float) $lawyer['avg_rating'], 1)) ?></span>
                                </div>
                            </div>
                        </div>
                        <a class="btn btn-sm btn-primary mt-3" href="<?= e(url('/public/lawyer-detail.php?id=' . $lawyer['id'])) ?>">ดูโปรไฟล์</a>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (!$featuredLawyers): ?>
                <div class="col-12"><div class="alert alert-info">ยังไม่มีทนายที่แสดงในระบบ กรุณา import database และอนุมัติทนาย</div></div>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="section-band">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-4"><h2 class="h3 fw-bold">ขั้นตอนการใช้งาน</h2></div>
            <div class="col-lg-8">
                <div class="row g-3">
                    <?php foreach (['ถามปัญหากฎหมายกับ AI', 'อ่านผลวิเคราะห์เบื้องต้นและเอกสารที่ควรเตรียม', 'ตัดสินใจว่าจะให้ระบบหาทนายหรือไม่', 'เลือกทนาย จองปรึกษา และอัปโหลดสลิป'] as $index => $step): ?>
                        <div class="col-md-6">
                            <div class="app-card p-3 h-100">
                                <div class="fw-bold text-primary mb-2">0<?= $index + 1 ?></div>
                                <div><?= e($step) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section-band bg-white">
    <div class="container">
        <div class="row g-4">
            <div class="col-md-6">
                <h2 class="h3 fw-bold">รีวิวผู้ใช้</h2>
                <div class="app-card p-4">“เข้าใจประเด็นกฎหมายได้เร็วขึ้น และชอบที่ระบบถามก่อนว่าจะให้หาทนายไหม”</div>
            </div>
            <div class="col-md-6">
                <h2 class="h3 fw-bold">FAQ</h2>
                <div class="accordion" id="faq">
                    <div class="accordion-item">
                        <h3 class="accordion-header"><button class="accordion-button" data-bs-toggle="collapse" data-bs-target="#faq1">AI เป็นทนายไหม?</button></h3>
                        <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faq"><div class="accordion-body">ไม่ใช่ AI ให้ข้อมูลเบื้องต้นเท่านั้น</div></div>
                    </div>
                    <div class="accordion-item">
                        <h3 class="accordion-header"><button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#faq2">ระบบจับคู่ทนายทันทีหรือไม่?</button></h3>
                        <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faq"><div class="accordion-body">ไม่ ระบบต้องถามความยินยอมก่อนทุกครั้ง</div></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
