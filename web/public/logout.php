<?php
require_once __DIR__ . '/../bootstrap.php';

// Simple logout
session_destroy();
header('Location: login.php');
exit;
?>