<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../services/MemberRegistrationService.php';

if (currentUser()) {
    redirect(dashboardPathForRole(currentUser()['role']));
}

$minPasswordLength = (int) app_config('password_min_length', 8);
$registrationEnabled = (bool) app_config('registration_enabled', true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $user = (new MemberRegistrationService())->register($_POST);
        if (app_config('auto_login_after_register', false)) {
            loginUser($user);
            flash('success', 'สมัครสมาชิกสำเร็จ');
            redirect(dashboardPathForRole($user['role']));
        }
        flash('success', 'สมัครสมาชิกสำเร็จ กรุณาเข้าสู่ระบบ');
        redirect(url('/user/login.php?registered=1'));
    } catch (InvalidArgumentException $exception) {
        flash('danger', $exception->getMessage());
    } catch (Throwable) {
        flash('danger', 'เกิดข้อผิดพลาดในการสมัครสมาชิก กรุณาลองใหม่อีกครั้ง');
    }
}

$pageTitle = 'สมัครสมาชิกผู้ใช้';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="auth-section auth-user">
    <div class="container">
        <div class="auth-shell">
            <div class="row g-0 align-items-stretch">
                <div class="col-xl-5 col-lg-6 order-2 order-lg-1">
                    <div class="auth-side h-100">
                        <span class="legal-badge auth-badge mb-3"><i class="bi bi-person-plus"></i> Member Account</span>
                        <h1 class="auth-title">สมัครบัญชีผู้ใช้สำหรับเริ่มต้นเคสของคุณ</h1>
                        <p class="auth-copy">บัญชีนี้เหมาะกับประชาชนหรือลูกความที่ต้องการถาม AI, บันทึกเคส, หาและจองทนาย รวมถึงติดตามการชำระเงิน</p>

                        <div class="auth-stage">
                            <div class="auth-stage-top">
                                <span>Onboarding</span>
                                <strong>3 ขั้นตอน</strong>
                            </div>
                            <div class="auth-stage-row is-active">
                                <i class="bi bi-person-plus"></i>
                                <div>
                                    <strong>สร้างบัญชี</strong>
                                    <span>ใช้อีเมลและเบอร์โทรสำหรับติดต่อ</span>
                                </div>
                            </div>
                            <div class="auth-stage-row">
                                <i class="bi bi-chat-square-text"></i>
                                <div>
                                    <strong>เล่าเรื่องเคส</strong>
                                    <span>ให้ AI ช่วยจัดประเด็นเบื้องต้น</span>
                                </div>
                            </div>
                            <div class="auth-stage-row">
                                <i class="bi bi-calendar2-check"></i>
                                <div>
                                    <strong>จองปรึกษา</strong>
                                    <span>เลือกทนายและติดตามสถานะงาน</span>
                                </div>
                            </div>
                        </div>

                        <div class="auth-meta-grid">
                            <div>
                                <span>สมัครใช้เวลา</span>
                                <strong>ประมาณ 1 นาที</strong>
                            </div>
                            <div>
                                <span>ประเภทบัญชี</span>
                                <strong>ผู้ใช้ทั่วไป</strong>
                            </div>
                        </div>

                        <div class="auth-requirements">
                            <h2>สิ่งที่ต้องเตรียมสำหรับผู้ใช้</h2>
                            <ul class="auth-check-list">
                                <li><i class="bi bi-check2-circle"></i> ชื่อและอีเมลสำหรับเข้าระบบ</li>
                                <li><i class="bi bi-check2-circle"></i> เบอร์โทรสำหรับการนัดหมายกับทนาย</li>
                                <li><i class="bi bi-check2-circle"></i> รหัสผ่านอย่างน้อย <?= e((string) $minPasswordLength) ?> ตัวอักษร มีตัวอักษรและตัวเลข</li>
                                <li><i class="bi bi-check2-circle"></i> ยอมรับเงื่อนไขการใช้งานและนโยบายความเป็นส่วนตัว</li>
                            </ul>
                        </div>

                        <div class="auth-alt-box">
                            <strong>เป็นทนาย?</strong>
                            <span>ใช้หน้าสมัครทนายแยกต่างหาก เพราะต้องมีใบอนุญาตและเอกสารยืนยันตัวตน</span>
                            <a href="<?= e(url('/lawyer/register-lawyer.php')) ?>">สมัครเป็นทนาย</a>
                        </div>
                    </div>
                </div>
                <div class="col-xl-7 col-lg-6 order-1 order-lg-2">
                    <div class="auth-card h-100">
                        <div class="auth-switch mb-4" aria-label="เลือกประเภทสมัคร">
                            <a class="active" href="<?= e(url('/public/register.php')) ?>"><i class="bi bi-person-plus"></i> สมัครผู้ใช้</a>
                            <a href="<?= e(url('/lawyer/register-lawyer.php')) ?>"><i class="bi bi-person-badge"></i> สมัครทนาย</a>
                            <a href="<?= e(url('/admin/login.php')) ?>"><i class="bi bi-shield-lock"></i> แอดมิน</a>
                        </div>

                        <div class="mb-4">
                            <span class="auth-eyebrow">Create Account</span>
                            <h2 class="h3 fw-bold mb-2">สมัครสมาชิกผู้ใช้</h2>
                            <p class="text-muted mb-0">ฟอร์มนี้สร้างเฉพาะบัญชีผู้ใช้ทั่วไป หากต้องการรับงานในฐานะทนายให้ใช้หน้าสมัครทนาย</p>
                        </div>

                        <?php require __DIR__ . '/../includes/social-auth-buttons.php'; ?>

                        <?php if (!$registrationEnabled): ?>
                            <div class="alert alert-warning mb-0">ระบบปิดรับสมัครสมาชิกชั่วคราว กรุณาติดต่อทีมงาน</div>
                        <?php else: ?>
                            <form id="memberRegisterForm" method="post" class="auth-form row g-3" novalidate>
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <div class="col-12">
                                    <div class="auth-input-icon">
                                        <i class="bi bi-person"></i>
                                        <div class="form-floating auth-field mb-0">
                                            <input class="form-control" id="memberName" name="name" minlength="2" autocomplete="name" placeholder="ชื่อ-นามสกุล" required>
                                            <label for="memberName">ชื่อ-นามสกุล</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="auth-input-icon">
                                        <i class="bi bi-envelope"></i>
                                        <div class="form-floating auth-field mb-1">
                                            <input id="registerEmail" class="form-control" type="email" name="email" autocomplete="email" placeholder="name@example.com" required>
                                            <label for="registerEmail">อีเมลสำหรับเข้าระบบ</label>
                                        </div>
                                    </div>
                                    <div id="emailAvailability" class="form-text"></div>
                                </div>
                                <div class="col-md-6">
                                    <div class="auth-input-icon">
                                        <i class="bi bi-telephone"></i>
                                        <div class="form-floating auth-field mb-0">
                                            <input class="form-control" id="memberPhone" name="phone" inputmode="tel" autocomplete="tel" placeholder="0812345678">
                                            <label for="memberPhone">เบอร์โทร</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="auth-input-icon auth-password-wrap">
                                        <i class="bi bi-lock"></i>
                                        <div class="form-floating auth-field mb-1">
                                            <input class="form-control" id="memberPassword" type="password" name="password" minlength="<?= e((string) $minPasswordLength) ?>" autocomplete="new-password" placeholder="รหัสผ่าน" data-password-watch data-password-strength required>
                                            <label for="memberPassword">รหัสผ่าน</label>
                                        </div>
                                        <button class="auth-password-toggle" type="button" data-password-toggle="memberPassword" aria-label="แสดงรหัสผ่าน"><i class="bi bi-eye"></i></button>
                                        <div class="auth-caps-warning" data-caps-warning="memberPassword" hidden><i class="bi bi-exclamation-triangle"></i> Caps Lock เปิดอยู่</div>
                                    </div>
                                    <div class="auth-strength" data-strength-for="memberPassword">
                                        <div class="auth-strength-track"><span class="auth-strength-bar"></span></div>
                                        <div class="auth-strength-text">เริ่มพิมพ์เพื่อเช็กความปลอดภัย</div>
                                    </div>
                                    <div class="auth-rule-row">
                                        <span data-rule-for="memberPassword" data-rule="length"><i class="bi bi-check"></i> <?= e((string) $minPasswordLength) ?> ตัวอักษร</span>
                                        <span data-rule-for="memberPassword" data-rule="letter"><i class="bi bi-check"></i> ตัวอักษร</span>
                                        <span data-rule-for="memberPassword" data-rule="number"><i class="bi bi-check"></i> ตัวเลข</span>
                                        <span data-rule-for="memberPassword" data-rule="special"><i class="bi bi-check"></i> สัญลักษณ์</span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="auth-input-icon auth-password-wrap">
                                        <i class="bi bi-lock-fill"></i>
                                        <div class="form-floating auth-field mb-1">
                                            <input class="form-control" id="memberPasswordConfirm" type="password" name="password_confirm" minlength="<?= e((string) $minPasswordLength) ?>" autocomplete="new-password" placeholder="ยืนยันรหัสผ่าน" data-password-watch data-password-confirm="memberPassword" required>
                                            <label for="memberPasswordConfirm">ยืนยันรหัสผ่าน</label>
                                        </div>
                                        <button class="auth-password-toggle" type="button" data-password-toggle="memberPasswordConfirm" aria-label="แสดงรหัสผ่าน"><i class="bi bi-eye"></i></button>
                                        <div class="auth-caps-warning" data-caps-warning="memberPasswordConfirm" hidden><i class="bi bi-exclamation-triangle"></i> Caps Lock เปิดอยู่</div>
                                    </div>
                                    <div class="form-text" data-match-for="memberPasswordConfirm">กรอกให้ตรงกับรหัสผ่าน</div>
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
                                    <button id="registerButton" class="btn btn-primary btn-lg w-100 auth-submit" type="submit">
                                        <i class="bi bi-person-plus me-2"></i>สมัครสมาชิกผู้ใช้
                                    </button>
                                </div>
                            </form>
                            <div id="registerResult" class="mt-3"></div>
                        <?php endif; ?>

                        <div class="auth-footnote">
                            <span>มีบัญชีผู้ใช้แล้ว?</span>
                            <a href="<?= e(url('/user/login.php')) ?>">เข้าสู่ระบบผู้ใช้</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<script>
const registerForm = document.querySelector('#memberRegisterForm');
const registerResult = document.querySelector('#registerResult');
const registerEmail = document.querySelector('#registerEmail');
const emailAvailability = document.querySelector('#emailAvailability');
const registerButton = document.querySelector('#registerButton');
let emailCheckTimer = null;

function setRegisterResult(type, message) {
    if (!registerResult) return;
    registerResult.innerHTML = `<div class="alert alert-${type} mb-0">${message}</div>`;
}

registerEmail?.addEventListener('input', () => {
    clearTimeout(emailCheckTimer);
    emailAvailability.textContent = '';
    emailAvailability.className = 'form-text';
    const email = registerEmail.value.trim();
    if (!email || !email.includes('@')) return;

    emailCheckTimer = setTimeout(async () => {
        const response = await fetch(`<?= e(url('/api/email-check.php')) ?>?email=${encodeURIComponent(email)}`);
        const json = await response.json();
        emailAvailability.textContent = json.message || '';
        emailAvailability.className = `form-text ${json.data?.available ? 'text-success' : 'text-danger'}`;
    }, 350);
});

registerForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (!registerForm.checkValidity()) {
        registerForm.classList.add('was-validated');
        setRegisterResult('danger', 'กรุณากรอกข้อมูลให้ครบและถูกต้อง');
        return;
    }

    registerButton.disabled = true;
    registerButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>กำลังสมัครสมาชิก...';

    try {
        const response = await fetch('<?= e(url('/api/register.php')) ?>', {
            method: 'POST',
            headers: {'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content},
            body: new FormData(registerForm),
        });
        const json = await response.json();
        if (!json.success) {
            setRegisterResult('danger', json.message || 'สมัครสมาชิกไม่สำเร็จ');
            return;
        }

        setRegisterResult('success', json.message || 'สมัครสมาชิกสำเร็จ');
        window.location.href = json.data.redirect || '<?= e(url('/user/login.php?registered=1')) ?>';
    } catch (error) {
        setRegisterResult('danger', 'ไม่สามารถเชื่อมต่อ API สมัครสมาชิกได้');
    } finally {
        registerButton.disabled = false;
        registerButton.innerHTML = '<i class="bi bi-person-plus me-2"></i>สมัครสมาชิกผู้ใช้';
    }
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
