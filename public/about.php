<?php
$pageTitle = 'เกี่ยวกับระบบ';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="section-band">
    <div class="container">
        <div class="row g-4 align-items-start">
            <div class="col-lg-7">
                <span class="legal-badge mb-3"><i class="bi bi-shield-check"></i> LegalTech Platform</span>
                <h1 class="fw-bold">เกี่ยวกับทนายคู่ดี</h1>
                <p class="lead text-muted">ระบบนี้ออกแบบให้ผู้ใช้เริ่มจากการทำความเข้าใจปัญหากฎหมายเบื้องต้นด้วย AI แล้วเลือกเองว่าจะให้ระบบช่วยจับคู่ทนายหรือไม่ โดยแยกบทบาทผู้ใช้ ทนาย และแอดมินอย่างชัดเจน</p>
                <div class="app-card p-4">
                    <h2 class="h5 fw-bold">หลักการสำคัญ</h2>
                    <ul class="mb-0">
                        <li>AI วิเคราะห์เบื้องต้น ไม่ใช่ทนาย และไม่ใช่คำแนะนำขั้นสุดท้าย</li>
                        <li>ระบบไม่ Match ทนายจนกว่าผู้ใช้กดยินยอม</li>
                        <li>ทนายต้องผ่านแอดมินอนุมัติก่อนแสดงในหน้าค้นหา</li>
                        <li>รองรับเคสหลายหมวดกฎหมาย เอกสารประกอบ Booking Payment Review Commission และแชตในระบบ</li>
                    </ul>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="app-card p-4 mb-3">
                    <h2 class="h5 fw-bold">โมดูลที่พร้อมใช้งาน</h2>
                    <div class="d-flex flex-column gap-2">
                        <span><i class="bi bi-check-circle text-success me-2"></i>AI Chat และสรุปข้อมูลเคส</span>
                        <span><i class="bi bi-check-circle text-success me-2"></i>ค้นหาและ Match ทนายตามความเชี่ยวชาญ</span>
                        <span><i class="bi bi-check-circle text-success me-2"></i>Booking, Slip Payment, Admin Approval</span>
                        <span><i class="bi bi-check-circle text-success me-2"></i>แชต ไฟล์แนบ แจ้งเตือน และรีวิวหลังปิดงาน</span>
                        <span><i class="bi bi-check-circle text-success me-2"></i>หน้าติดต่อ FAQ Privacy Terms และ Health Check</span>
                    </div>
                </div>
                <div class="app-card p-4">
                    <h2 class="h5 fw-bold">ก่อนเปิด production</h2>
                    <p class="text-muted mb-0">ตั้งค่า HTTPS, API key, backup database, สิทธิ์โฟลเดอร์ uploads/storage, เปลี่ยนรหัส demo account และตรวจ disclaimer ให้ตรงกับนโยบายธุรกิจจริง</p>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
