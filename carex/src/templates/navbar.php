<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../includes/navbar.php';

$activePage = $activePage ?? 'matrizes';

render_platform_navbar('carex', $activePage);
