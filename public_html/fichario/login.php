<?php
declare(strict_types=1);

require __DIR__ . '/../../fichario/bootstrap.php';

$defaultNext = preg_replace('~/login\.php$~', '/index.php', (string) ($_SERVER['SCRIPT_NAME'] ?? '/fichario/index.php')) ?: '/fichario/index.php';
$next = (string) ($_GET['next'] ?? $defaultNext);
redirect_to_access('login.php?next=' . rawurlencode($next));
