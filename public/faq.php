<?php
$pageTitle = 'คำถามที่พบบ่อย';
require_once __DIR__ . '/../includes/header.php';

$faqs = [
    ['AI เป็นทนายหรือให้คำปรึกษาทางกฎหมายแทนทนายได้ไหม?', 'ไม่ใช่ AI ช่วยวิเคราะห์เบื้องต้นเพื่อจัดหมวดประเด็น ความเร่งด่วน เอกสารที่ควรเตรียม และคำถามต่อยอดเท่านั้น หากต้องการความเห็นทางกฎหมายเฉพาะคดีควรปรึกษาทนายที่มีใบอนุญาต'],
    ['ระบบจะส่งข้อมูลของฉันให้ทนายทันทีหรือไม่?', 'ไม่ ระบบจะถามความยินยอมก่อนเสมอ หลังจากผู้ใช้กดต้องการหาทนาย ระบบจึงนำข้อมูลเคสไป Match กับทนายที่เหมาะสม'],
    ['ชำระเงินอย่างไร?', 'หลังสร้าง Booking ระบบจะแสดงข้อมูลบัญชีและ PromptPay ให้ผู้ใช้อัปโหลดสลิป แอดมินต้องตรวจสอบก่อน Booking จึงเปลี่ยนเป็นยืนยันแล้ว'],
    ['ทนายสมัครแล้วแสดงในระบบทันทีไหม?', 'ไม่ โปรไฟล์ทนายจะอยู่ในสถานะรอตรวจสอบ แอดมินต้องอนุมัติและยืนยันก่อนจึงแสดงในหน้าค้นหาทนาย'],
    ['ข้อมูลและเอกสารที่อัปโหลดปลอดภัยแค่ไหน?', 'ระบบจำกัดชนิดไฟล์ สุ่มชื่อไฟล์ และเปิดเอกสารผ่าน endpoint ที่ตรวจสิทธิ์ ผู้ดูแลควรตั้งค่า HTTPS และสิทธิ์โฟลเดอร์ให้เหมาะสมเมื่อขึ้น production'],
    ['ถ้าไม่มี OpenAI API key ยังใช้งานได้ไหม?', 'ใช้งานได้ในโหมด fallback แบบ rule-based สำหรับ demo/local แต่ production ควรตั้งค่า API key และตรวจ prompt/นโยบายก่อนเปิดใช้งานจริง'],
];
?>
<section class="section-band">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-4">
                <span class="legal-badge mb-3"><i class="bi bi-question-circle"></i> FAQ</span>
                <h1 class="fw-bold">คำถามที่พบบ่อย</h1>
                <p class="text-muted">รวมคำตอบสำคัญก่อนเริ่มถาม AI จองทนาย หรืออัปโหลดเอกสารในระบบ</p>
                <a class="btn btn-primary" href="<?= e(url('/public/contact.php')) ?>">ติดต่อทีมงาน</a>
            </div>
            <div class="col-lg-8">
                <div class="accordion" id="faqList">
                    <?php foreach ($faqs as $index => $faq): ?>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button <?= $index === 0 ? '' : 'collapsed' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#faq<?= $index ?>">
                                    <?= e($faq[0]) ?>
                                </button>
                            </h2>
                            <div id="faq<?= $index ?>" class="accordion-collapse collapse <?= $index === 0 ? 'show' : '' ?>" data-bs-parent="#faqList">
                                <div class="accordion-body"><?= e($faq[1]) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
