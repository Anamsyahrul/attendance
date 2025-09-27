<?php
require_once __DIR__ . '/../bootstrap.php';
logout_user();
header('Location: login.php?logged_out=1');
exit;

