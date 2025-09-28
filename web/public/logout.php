<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../classes/AuthService.php';

$authService = new AuthService($pdo, $config);
$authService->logout();

header('Location: login_new.php');
exit;
?>