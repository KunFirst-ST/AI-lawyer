<?php

require_once __DIR__ . '/../services/ActivityService.php';
require_once __DIR__ . '/../services/BookingWorkflowService.php';

(new ActivityService())->ensureSchema();
(new BookingWorkflowService())->ensureSchema();

echo "advanced-workflow-ready\n";
