<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../services/SystemHealthService.php';

$summary = (new SystemHealthService())->summary();
jsonResponse((bool) $summary['ok'], $summary['ok'] ? 'ระบบพร้อมใช้งาน' : 'ระบบยังมีบางส่วนที่ต้องตั้งค่า', $summary, [], $summary['ok'] ? 200 : 503);
