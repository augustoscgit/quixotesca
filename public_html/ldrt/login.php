<?php
/**
 * Redirect local login requests to global Acesso authentication
 */
$next = (string) ($_GET['next'] ?? '');
header('Location: ../acesso/login.php?next=' . rawurlencode($next));
exit;
