<?php

declare(strict_types=1);

require __DIR__ . '/../../acesso/src/bootstrap.php';

if (is_logged_in()) {
    $next = (string) ($_GET['next'] ?? $_POST['next'] ?? 'index.php');
    header('Location: ' . safe_redirect_target($next));
    exit;
}

$errors = [];
$login = trim((string) ($_POST['login'] ?? ''));
$next = (string) ($_GET['next'] ?? $_POST['next'] ?? 'index.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $password = (string) ($_POST['password'] ?? '');
    $user = $login !== '' ? find_user_by_login($login) : null;
    $ok = $user && ($user['status'] ?? '') === 'active' && password_verify($password, (string) $user['password_hash']);

    record_login_attempt($login, (bool) $ok);

    if ($ok) {
        try {
            db()->prepare('UPDATE ' . table_name('users') . " SET last_login_at = (now() at time zone 'utc') WHERE id = :id")->execute(['id' => $user['id']]);
        } catch (Throwable $e) {
            // last_login_at is informational and must not block authentication.
        }
        login_user($user);
        header('Location: ' . safe_redirect_target($next));
        exit;
    }

    $errors[] = 'Usuario/e-mail inexistente ou senha invalida.';
}

render_header('Entrar');
?>
<section class="auth-shell panel p-4">
    <div class="text-center mb-4">
        <div class="section-title mb-2">Login comum</div>
        <h1 class="h3 mb-2">Entrar no Acesso</h1>
        <p class="muted mb-0">Use e-mail ou nome de usuario e senha.</p>
    </div>

    <?php if ($errors !== []): ?>
        <div class="alert alert-danger">
            <?php foreach ($errors as $error): ?>
                <div><?= h($error) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="post" class="d-grid gap-3">
        <?= csrf_field() ?>
        <input type="hidden" name="next" value="<?= h($next) ?>">
        <div>
            <label class="form-label" for="login">E-mail ou usuario</label>
            <input class="form-control" id="login" name="login" autocomplete="username" required value="<?= h($login) ?>">
        </div>
        <div>
            <label class="form-label" for="password">Senha</label>
            <input class="form-control" id="password" name="password" type="password" autocomplete="current-password" required>
        </div>
        <button class="btn btn-primary" type="submit" data-loading-text="Entrando...">Entrar</button>
    </form>

    <div class="d-flex justify-content-between mt-4 small">
        <a href="forgot_password.php">Esqueci minha senha</a>
        <a href="cadastro.php">Solicitar cadastro</a>
    </div>
</section>
<?php render_footer(); ?>
