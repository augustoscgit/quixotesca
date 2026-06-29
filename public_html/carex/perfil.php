<?php

declare(strict_types=1);

$config = require dirname(__DIR__, 2) . '/carex' . '/src/bootstrap.php';

\Carex\Http\Auth::requireLogin();

header('Location: ../acesso/index.php');
exit;
