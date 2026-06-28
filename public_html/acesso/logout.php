<?php

declare(strict_types=1);

require __DIR__ . '/../../acesso/src/bootstrap.php';

logout_user();
flash('notice', 'Voce saiu com seguranca.');
header('Location: login.php');
exit;
