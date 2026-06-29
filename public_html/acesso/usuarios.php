<?php

declare(strict_types=1);

$query = $_SERVER['QUERY_STRING'] ?? '';
$redirectUrl = '../admin/usuarios.php' . ($query !== '' ? '?' . $query : '');

header('Location: ' . $redirectUrl);
exit;
