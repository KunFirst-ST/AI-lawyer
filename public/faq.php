<?php
$pageTitle = 'คำถามที่พบบ่อย';
require_once __DIR__ . '/../includes/header.php';

$faqs = [
    ['AI เป็นทนายหรือให้คำปรึกษาทางกฎหมายแทนทนายได้ไหม?', 'ไม่ใช่ AI ช่วยวิเคราะห์เบื้องต้นเพื่อจัดหมวดประเด็น ความเร่งด่วน เอกสารที่ควรเตรียม และคำถามต่อยอดเท่านั้น หากต้องการความเห็นทางกฎหมายเฉพาะคดีควรปรึกษาทนายที่มีใบอนุญาต'],
    ['ระบบจะส่งข้อมูลของฉันให้ทนายทันทีหรือไม่?', 'ไม่ ระบบจะถามความยินยอมก่อนเสมอ หลังจากผู้ใช้กดต้องการหาทนาย ระบบจึงนำข้อมูลเคสไปจับคู่กับทนายที่เหมาะสม'],
    ['ชำระเงินอย่างไร?', 'หลังส่งคำขอนัดหมาย ให้รอทนายตอบรับก่อน เมื่อทนายรับงานแล้วระบบจะแสดงข้อมูลบัญชีและ PromptPay เพื่อให้โอนเงินและอัปโหลดสลิป แอดมินจะตรวจสอบสลิปก่อนยืนยันนัดหมาย'],
    ['ทนายสมัครแล้วแสดงในระบบทันทีไหม?', 'ไม่ โปรไฟล์ทนายจะอยู่ในสถานะรอตรวจสอบ แอดมินต้องอนุมัติและยืนยันก่อนจึงแสดงในหน้าค้นหาทนาย'],
    ['ข้อมูลและเอกสารที่อัปโหลดปลอดภัยแค่ไหน?', 'ระบบจำกัดชนิดไฟล์ สุ่มชื่อไฟล์ และเปิดเอกสารผ่านหน้าที่ตรวจสิทธิ์ ผู้ใช้จะเห็นเฉพาะไฟล์ของตนเองหรือไฟล์ที่เกี่ยวข้องกับเคสเท่านั้น'],
    ['ถ้าต้องการความช่วยเหลือเพิ่มเติมต้องทำอย่างไร?', 'สามารถติดต่อทีมงานผ่านหน้าติดต่อ หรือแชตกับทนายหลังจากมีการจับคู่และจองนัดหมายแล้ว'],
];
?>
<section class="section-band">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-4">
                <span class="legal-badge mb-3"><i class="bi bi-question-circle"></i> คำถามที่พบบ่อย</span>
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
