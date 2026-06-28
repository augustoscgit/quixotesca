<?php
if (!isset($_SESSION)) {
    session_start();
}

$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$scriptDir = rtrim($scriptDir, '/');
$platformRoot = preg_replace('~/(carex|fichario|ldrt|renastonline|includes)(/.*)?$~', '', $scriptDir) ?? '';
$platformRoot = $platformRoot === '.' ? '' : rtrim($platformRoot, '/');

if (!function_exists('platform_url')) {
function platform_url(string $path): string
{
    global $platformRoot;
    $url = ($platformRoot === '' ? '' : $platformRoot) . '/' . ltrim($path, '/');
    return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
}
}
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
  <div class="container-fluid justify-content-between">
    <a class="navbar-brand" href="<?= platform_url('index.html') ?>">
      <img src="<?= platform_url('assets/img/logo-fundo-escuro-horizontal.png') ?>" alt="Logo RENAST" class="platform-logo-img navbar-logo-img">
    </a>
    <div class="d-flex align-items-center gap-3">
      <?php if (isset($_SESSION['perfil']) && $_SESSION['perfil'] === 'administrador'): ?>
      <div class="dropdown">
        <button class="btn btn-outline-light dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
          Administrar
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
          <li><a class="dropdown-item" href="<?= platform_url('carex/administrativo.php') ?>">Administrativo CAREX</a></li>
        </ul>
      </div>
      <?php endif; ?>
      <i class="bi bi-person-circle text-white fs-4"></i>
      <a class="btn btn-outline-light" href="<?= platform_url('carex/logout.php') ?>">Sair</a>
    </div>
  </div>
</nav>
