<?php
require_once __DIR__ . '/../config/auth_guard.php';
requireRole('admin');

header('Location: ../admin.php?page=schedules');
exit;
