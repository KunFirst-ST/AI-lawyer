<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../services/LawyerRegistrationService.php';

if (currentUser()) {
    flash('warning', 'กรุณาออกจากระบบก่อนสมัครบัญชีทนายใหม่');
    redirect(dashboardPathForRole(currentUser()['role']));
}

$categories = db()->query('SELECT id, name FROM legal_categories ORDER BY name')->fetchAll();
$enabled = (bool) app_config('lawyer_registration_enabled', true);
$minPasswordLength = (int) app_config('password_min_length', 8);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $result = (new LawyerRegistrationService())->register($_POST, $_FILES);
        loginUser($result['user']);
        flash('success', 'ส่งใบสมัครทนายแล้ว กรุณารอแอดมินตรวจสอบ');
        redirect(url('/lawyer/dashboard.php'));
    } catch (InvalidArgumentException $exception) {
        flash('danger', $exception->getMessage());
    } catch (Throwable $exception) {
        flash('danger', 'สมัครทนายไม่สำเร็จ: ' . $exception->getMessage());
    }
}

$pageTitle = 'สมัครเป็นทนาย';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="auth-section auth-lawyer">
    <div class="container">
        <div class="auth-shell">
            <div class="row g-0 align-items-stretch">
                <div class="col-xl-5 col-lg-6 order-2 order-lg-1">
                    <div class="auth-side h-100">
                        <span class="legal-badge auth-badge mb-3"><i class="bi bi-person-badge"></i> Lawyer Application</span>
                        <h1 class="auth-title">สมัครบัญชีทนายสำหรับรับเคสผ่านแพลตฟอร์ม</h1>
                        <p class="auth-copy">บัญชีทนายแยกจากผู้ใช้ทั่วไปและต้องผ่านการตรวจสอบก่อนแสดงบนหน้าค้นหาทนาย</p>

                        <div class="auth-stage">
                            <div class="auth-stage-top">
                                <span>Verification</span>
                                <strong>ตรวจสอบเอกสาร</strong>
                            </div>
                            <div class="auth-stage-row is-active">
                                <i class="bi bi-award"></i>
                                <div>
                                    <strong>ข้อมูลวิชาชีพ</strong>
                                    <span>ใบอนุญาต หมวดกฎหมาย และประสบการณ์</span>
                                </div>
                            </div>
                            <div class="auth-stage-row">
                                <i class="bi bi-file-earmark-lock"></i>
                                <div>
                                    <strong>เอกสารยืนยัน</strong>
                                    <span>แนบไฟล์สำหรับให้แอดมินตรวจสอบ</span>
                                </div>
                            </div>
                            <div class="auth-stage-row">
                                <i class="bi bi-patch-check"></i>
                                <div>
                                    <strong>รออนุมัติ</strong>
                                    <span>เปิดโปรไฟล์หลังผ่านการตรวจสอบ</span>
                                </div>
                            </div>
                        </div>

                        <div class="auth-meta-grid">
                            <div>
                                <span>ขั้นตอน</span>
                                <strong>สมัครและรออนุมัติ</strong>
                            </div>
                            <div>
                                <span>ประเภทบัญชี</span>
                                <strong>ทนายผู้ให้บริการ</strong>
                            </div>
                        </div>

                        <div class="auth-requirements">
                            <h2>เอกสารและข้อมูลที่ต้องใช้</h2>
                            <ul class="auth-check-list">
                                <li><i class="bi bi-check2-circle"></i> เลขใบอนุญาตทนายและไฟล์ใบอนุญาต</li>
                                <li><i class="bi bi-check2-circle"></i> เอกสารยืนยันตัวตน เช่น บัตรประชาชน</li>
                                <li><i class="bi bi-check2-circle"></i> จังหวัด ค่าปรึกษา ประสบการณ์ และประวัติย่อ</li>
                                <li><i class="bi bi-check2-circle"></i> เลือกหมวดกฎหมายที่เชี่ยวชาญอย่างน้อย 1 หมวด</li>
                            </ul>
                        </div>

                        <div class="auth-alt-box">
                            <strong>ต้องการใช้บริการทนาย?</strong>
                            <span>สมัครบัญชีผู้ใช้ทั่วไปแทน เพื่อสร้างเคสและจองปรึกษา</span>
                            <a href="<?= e(url('/public/register.php')) ?>">สมัครผู้ใช้</a>
                        </div>
                    </div>
                </div>
                <div class="col-xl-7 col-lg-6 order-1 order-lg-2">
                    <div class="auth-card h-100">
                        <div class="auth-switch mb-4" aria-label="เลือกประเภทสมัคร">
                            <a href="<?= e(url('/public/register.php')) ?>"><i class="bi bi-person-plus"></i> สมัครผู้ใช้</a>
                            <a class="active" href="<?= e(url('/lawyer/register-lawyer.php')) ?>"><i class="bi bi-person-badge"></i> สมัครทนาย</a>
                            <a href="<?= e(url('/admin/login.php')) ?>"><i class="bi bi-shield-lock"></i> แอดมิน</a>
                        </div>

                        <div class="mb-4">
                            <span class="auth-eyebrow">Lawyer Onboarding</span>
                            <h2 class="h3 fw-bold mb-2">สมัครเป็นทนาย</h2>
                            <p class="text-muted mb-0">กรอกข้อมูลวิชาชีพให้ครบเพื่อให้แอดมินตรวจสอบและเปิดใช้งานบัญชีทนาย</p>
                        </div>

                        <?php if (!$enabled): ?>
                            <div class="alert alert-warning mb-0">ระบบปิดรับสมัครทนายชั่วคราว กรุณาติดต่อทีมงาน</div>
                        <?php else: ?>
                            <form method="post" enctype="multipart/form-data" class="auth-form row g-3">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

                                <div class="col-12">
                                    <div class="auth-form-section">
                                        <i class="bi bi-person-lines-fill"></i>
                                        <span>ข้อมูลบัญชี</span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="auth-input-icon">
                                        <i class="bi bi-person"></i>
                                        <div class="form-floating auth-field mb-0">
                                            <input class="form-control" id="lawyerName" name="name" autocomplete="name" placeholder="ชื่อ-นามสกุล" required>
                                            <label for="lawyerName">ชื่อ-นามสกุล</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="auth-input-icon">
                                        <i class="bi bi-telephone"></i>
                                        <div class="form-floating auth-field mb-0">
                                            <input class="form-control" id="lawyerPhone" name="phone" inputmode="tel" autocomplete="tel" placeholder="0812345678">
                                            <label for="lawyerPhone">เบอร์โทร</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="auth-input-icon">
                                        <i class="bi bi-envelope"></i>
                                        <div class="form-floating auth-field mb-0">
                                            <input class="form-control" id="lawyerRegisterEmail" type="email" name="email" autocomplete="email" placeholder="lawyer@example.com" required>
                                            <label for="lawyerRegisterEmail">อีเมลสำหรับเข้าระบบทนาย</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="auth-input-icon auth-password-wrap">
                                        <i class="bi bi-lock"></i>
                                        <div class="form-floating auth-field mb-1">
                                            <input class="form-control" id="lawyerRegisterPassword" type="password" name="password" minlength="<?= e((string) $minPasswordLength) ?>" autocomplete="new-password" placeholder="รหัสผ่าน" data-password-watch data-password-strength required>
                                            <label for="lawyerRegisterPassword">รหัสผ่าน</label>
                                        </div>
                                        <button class="auth-password-toggle" type="button" data-password-toggle="lawyerRegisterPassword" aria-label="แสดงรหัสผ่าน"><i class="bi bi-eye"></i></button>
                                        <div class="auth-caps-warning" data-caps-warning="lawyerRegisterPassword" hidden><i class="bi bi-exclamation-triangle"></i> Caps Lock เปิดอยู่</div>
                                    </div>
                                    <div class="auth-strength" data-strength-for="lawyerRegisterPassword">
                                        <div class="auth-strength-track"><span class="auth-strength-bar"></span></div>
                                        <div class="auth-strength-text">เริ่มพิมพ์เพื่อเช็กความปลอดภัย</div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="auth-input-icon auth-password-wrap">
                                        <i class="bi bi-lock-fill"></i>
                                        <div class="form-floating auth-field mb-1">
                                            <input class="form-control" id="lawyerPasswordConfirm" type="password" name="password_confirm" minlength="<?= e((string) $minPasswordLength) ?>" autocomplete="new-password" placeholder="ยืนยันรหัสผ่าน" data-password-watch data-password-confirm="lawyerRegisterPassword" required>
                                            <label for="lawyerPasswordConfirm">ยืนยันรหัสผ่าน</label>
                                        </div>
                                        <button class="auth-password-toggle" type="button" data-password-toggle="lawyerPasswordConfirm" aria-label="แสดงรหัสผ่าน"><i class="bi bi-eye"></i></button>
                                        <div class="auth-caps-warning" data-caps-warning="lawyerPasswordConfirm" hidden><i class="bi bi-exclamation-triangle"></i> Caps Lock เปิดอยู่</div>
                                    </div>
                                    <div class="form-text" data-match-for="lawyerPasswordConfirm">กรอกให้ตรงกับรหัสผ่าน</div>
                                </div>

                                <div class="col-12">
                                    <div class="auth-form-section">
                                        <i class="bi bi-award"></i>
                                        <span>ข้อมูลวิชาชีพ</span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="auth-input-icon">
                                        <i class="bi bi-award"></i>
                                        <div class="form-floating auth-field mb-0">
                                            <input class="form-control" id="licenseNumber" name="license_number" placeholder="เลขใบอนุญาตทนาย" required>
                                            <label for="licenseNumber">เลขใบอนุญาตทนาย</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="auth-input-icon">
                                        <i class="bi bi-geo-alt"></i>
                                        <div class="form-floating auth-field mb-0">
                                            <input class="form-control" id="lawyerProvince" name="province" placeholder="จังหวัดที่รับงานหลัก" required>
                                            <label for="lawyerProvince">จังหวัดที่รับงานหลัก</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-floating auth-field mb-0">
                                        <input class="form-control" id="experienceYears" type="number" name="experience_years" min="0" value="0" placeholder="0">
                                        <label for="experienceYears">ประสบการณ์กี่ปี</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-floating auth-field mb-0">
                                        <input class="form-control" id="consultationFee" type="number" name="consultation_fee" min="0" step="100" value="0" placeholder="0">
                                        <label for="consultationFee">ค่าปรึกษาเริ่มต้น</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="auth-consent auth-consent-compact h-100">
                                        <input class="form-check-input" type="checkbox" name="complex_case_experience" id="complex">
                                        <span>มีประสบการณ์คดีซับซ้อน</span>
                                    </label>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">ประวัติย่อ</label>
                                    <textarea class="form-control auth-textarea" name="bio" rows="4" placeholder="สรุปความเชี่ยวชาญ วิธีทำงาน และประเภทเคสที่รับ"></textarea>
                                </div>

                                <div class="col-12">
                                    <div class="auth-form-section">
                                        <i class="bi bi-tags"></i>
                                        <span>หมวดกฎหมายที่เชี่ยวชาญ</span>
                                        <em data-checkbox-count=".auth-checkbox-grid input[type='checkbox']">0 หมวด</em>
                                    </div>
                                    <div class="auth-checkbox-grid">
                                        <?php foreach ($categories as $category): ?>
                                            <label class="auth-checkbox-item" for="cat<?= e($category['id']) ?>">
                                                <input class="form-check-input" type="checkbox" name="categories[]" value="<?= e($category['id']) ?>" id="cat<?= e($category['id']) ?>">
                                                <span><?= e($category['name']) ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="auth-form-section">
                                        <i class="bi bi-file-earmark-arrow-up"></i>
                                        <span>เอกสารประกอบ</span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">อัปโหลดใบอนุญาตทนาย</label>
                                    <input class="form-control auth-file-input" id="licenseDocument" type="file" name="license_document" accept=".pdf,.jpg,.jpeg,.png,.webp">
                                    <div class="auth-file-hint" data-file-name="licenseDocument">ยังไม่ได้เลือกไฟล์</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">อัปโหลดเอกสารยืนยันตัวตน</label>
                                    <input class="form-control auth-file-input" id="idCardDocument" type="file" name="id_card" accept=".pdf,.jpg,.jpeg,.png,.webp">
                                    <div class="auth-file-hint" data-file-name="idCardDocument">ยังไม่ได้เลือกไฟล์</div>
                                </div>
                                <div class="col-12">
                                    <label class="auth-consent">
                                        <input class="form-check-input" type="checkbox" name="accepted_terms" value="1" id="acceptedTerms" required>
                                        <span>
                                            ยอมรับ <a href="<?= e(url('/public/terms.php')) ?>" target="_blank">เงื่อนไขการใช้งาน</a> และ <a href="<?= e(url('/public/privacy.php')) ?>" target="_blank">นโยบายความเป็นส่วนตัว</a>
                                        </span>
                                    </label>
                                </div>
                                <div class="col-12">
                                    <button class="btn btn-primary btn-lg w-100 auth-submit" type="submit">
                                        <i class="bi bi-send-check me-2"></i>ส่งสมัครและรอตรวจสอบ
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>

                        <div class="auth-footnote">
                            <span>มีบัญชีทนายแล้ว?</span>
                            <a href="<?= e(url('/lawyer/login.php')) ?>">เข้าสู่ระบบทนาย</a>
                            <a href="<?= e(url('/public/portals.php')) ?>">เลือกพอร์ทัลอื่น</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
