<?php

declare(strict_types=1);

// Redirect to Acesso login page as the central authentication gateway
$next = (string) ($_GET['next'] ?? 'matrizes.php');

if (str_starts_with($next, '/')) {
    $redirectUrl = $next;
} else {
    $redirectUrl = '../carex/' . $next;
}

header('Location: ../acesso/login.php?next=' . rawurlencode($redirectUrl));
exit;
