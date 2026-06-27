<?php

declare(strict_types=1);

use Carex\Http\Auth;

require dirname(__DIR__) . '/src/bootstrap.php';

Auth::logout();

header('Location: login.php');
exit;
