<?php
declare(strict_types=1);

require __DIR__ . '/../../fichario/bootstrap.php';

$token = (string) ($_GET['token'] ?? '');
$path = 'reset_password.php' . ($token !== '' ? '?token=' . rawurlencode($token) : '');
redirect_to_access($path);
