<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../services/SystemHealthService.php';
require_once __DIR__ . '/../services/ActivityService.php';

requireRole('admin');

$healthService = new SystemHealthService();
$summary = $healthService->summary(true);
$checks = $summary['checks'];
$metrics = $summary['metrics'];
$recentAuditLogs = $healthService->recentAuditLogs(12);

$statusClass = static fn (string $status): string => match ($status) {
    'ok' => 'success',
    'warn' => 'warning',
    default => 'danger',
};

$checksBySeverity = [
    'required' => array_values(array_filter($checks, static fn (array $check): bool => ($check['severity'] ?? '') === 'required')),
    'optional' => array_values(array_filter($checks, static fn (array $check): bool => ($check['severity'] ?? '') === 'optional')),
    'info' => array_values(array_filter($checks, static fn (array $check): bool => ($check['severity'] ?? '') === 'info')),
];

$pageTitle = 'สถานะระบบ';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="dashboard-shell">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-3"><?php require __DIR__ . '/../includes/admin-sidebar.php'; ?></div>
            <div class="col-lg-9">
                <div class="d-flex flex-column flex-md-row justify-content-between gap-3 align-items-md-center mb-3">
                    <div>
                        <h1 class="h3 fw-bold mb-1">สถานะระบบ</h1>
                        <div class="small-muted">ตรวจความพร้อมของบริการ ฐานข้อมูล ไฟล์อัปโหลด และเหตุการณ์ความปลอดภัยล่าสุด</div>
                    </div>
                    <span class="admin-status-badge <?= e($summary['ok'] ? 'success' : 'danger') ?>">
                        <?= e($summary['ok'] ? 'ระบบหลักพร้อมใช้งาน' : 'ต้องตรวจสอบ') ?>
                    </span>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <div class="admin-stat-card tone-<?= e($summary['score'] >= 85 ? 'green' : ($summary['score'] >= 65 ? 'amber' : 'red')) ?>">
                            <div>
                                <span>คะแนนความพร้อม</span>
                                <strong><?= e((string) $summary['score']) ?>%</strong>
                                <small><?= e(appEnvironmentLabel((string) $summary['environment'])) ?> · PHP <?= e((string) $summary['php_version']) ?></small>
                            </div>
                            <i class="bi bi-shield-check"></i>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="admin-stat-card tone-blue">
                            <div>
                                <span>ตรวจความปลอดภัย 60 นาที</span>
                                <strong><?= e((string) $metrics['audit_events_60m']) ?></strong>
                                <small>เข้าสู่ระบบไม่สำเร็จ <?= e((string) $metrics['failed_logins_60m']) ?></small>
                            </div>
                            <i class="bi bi-journal-check"></i>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="admin-stat-card tone-amber">
                            <div>
                                <span>งานรอตรวจ</span>
                                <strong><?= e((string) ((int) $metrics['pending_lawyer_reviews'] + (int) $metrics['pending_payments'])) ?></strong>
                                <small>ทนาย <?= e((string) $metrics['pending_lawyer_reviews']) ?> · สลิป <?= e((string) $metrics['pending_payments']) ?></small>
                            </div>
                            <i class="bi bi-clipboard2-check"></i>
                        </div>
                    </div>
                </div>

                <div class="app-card admin-panel-card mb-3">
                    <div class="admin-card-heading">
                        <div>
                            <h2>รายการตรวจหลัก</h2>
                            <p>รายการนี้ควรเป็นสีเขียวทั้งหมดก่อนใช้งานจริง</p>
                        </div>
                        <i class="bi bi-hdd-network"></i>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead><tr><th>รายการตรวจ</th><th>สถานะ</th><th>รายละเอียด</th></tr></thead>
                            <tbody>
                            <?php foreach ($checksBySeverity['required'] as $check): ?>
                                <tr>
                                    <td class="fw-semibold"><?= e((string) $check['label']) ?></td>
                                    <td><span class="admin-status-badge <?= e($statusClass((string) $check['status'])) ?>"><?= e(systemHealthStatusLabel((string) $check['status'])) ?></span></td>
                                    <td class="small-muted"><?= e((string) $check['message']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-xl-6">
                        <div class="app-card admin-panel-card h-100">
                            <div class="admin-card-heading">
                                <div>
                                    <h2>บริการเสริม</h2>
                                    <p>บริการเสริมที่ทำให้ระบบพร้อมใช้งานจริงมากขึ้น</p>
                                </div>
                                <i class="bi bi-puzzle"></i>
                            </div>
                            <div class="admin-feed-list">
                                <?php foreach ($checksBySeverity['optional'] as $check): ?>
                                    <div class="d-flex align-items-center gap-3 py-2 border-bottom">
                                        <span class="admin-status-badge <?= e($statusClass((string) $check['status'])) ?>"><?= e(systemHealthStatusLabel((string) $check['status'])) ?></span>
                                        <span>
                                            <strong><?= e((string) $check['label']) ?></strong>
                                            <small class="d-block small-muted"><?= e((string) $check['message']) ?></small>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-6">
                        <div class="app-card admin-panel-card h-100">
                            <div class="admin-card-heading">
                                <div>
                                    <h2>ตัวชี้วัดการใช้งาน</h2>
                                    <p>ตัวเลขที่ควรดูเป็นประจำระหว่างใช้งานจริง</p>
                                </div>
                                <i class="bi bi-graph-up"></i>
                            </div>
                            <div class="table-responsive">
                                <table class="table align-middle mb-0">
                                    <tbody>
                                    <?php foreach ($metrics as $key => $value): ?>
                                        <tr>
                                            <td class="small-muted"><?= e(systemMetricLabel((string) $key)) ?></td>
                                            <td class="fw-semibold text-end"><?= e((string) $value) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="app-card admin-panel-card">
                    <div class="admin-card-heading">
                        <div>
                            <h2>เหตุการณ์ความปลอดภัยล่าสุด</h2>
                            <p>บันทึกล่าสุดสำหรับติดตามพฤติกรรมผิดปกติ</p>
                        </div>
                        <a href="<?= e(url('/admin/audit-logs.php')) ?>">ดูบันทึกทั้งหมด</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead><tr><th>เวลา</th><th>ผู้ใช้</th><th>การทำรายการ</th><th>IP</th><th>รายละเอียด</th></tr></thead>
                            <tbody>
                            <?php foreach ($recentAuditLogs as $log): ?>
                                <?php
                                $decoded = json_decode((string) ($log['details'] ?? ''), true);
                                $details = is_array($decoded) && $decoded ? json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '-';
                                ?>
                                <tr>
                                    <td class="text-nowrap"><?= e((string) $log['created_at']) ?></td>
                                    <td>
                                        <div class="fw-semibold"><?= e((string) ($log['actor_name'] ?: 'ระบบ')) ?></div>
                                        <small class="small-muted"><?= e((string) ($log['actor_email'] ?: '-')) ?></small>
                                    </td>
                                    <td><span class="badge text-bg-light text-dark"><?= e(auditActionLabel((string) $log['action'])) ?></span></td>
                                    <td><?= e((string) ($log['ip_address'] ?: '-')) ?></td>
                                    <td><small><?= e($details) ?></small></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$recentAuditLogs): ?><tr><td colspan="5" class="text-muted">ยังไม่มีเหตุการณ์ความปลอดภัยล่าสุด</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
