<?php
// Script temporaire pour tester api.php en CLI
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET['action'] = 'get_config';
require __DIR__ . '/api.php';
