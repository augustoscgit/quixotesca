<?php

declare(strict_types=1);

require __DIR__ . '/../../acesso/src/bootstrap.php';

$sent = false;
$login = trim((string) ($_POST['login'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $user = $login !== '' ? find_user_by_login($login) : null;
    if ($user && ($user['status'] ?? '') === 'active') {
        $token = bin2hex(random_bytes(32));
        $ttl = max(15, env_int('PASSWORD_RESET_TTL_MINUTES', 120));
        $expiresAt = gmdate('Y-m-d H:i:s', time() + ($ttl * 60));

        $stmt = db()->prepare('
            INSERT INTO ' . table_name('password_resets') . ' (user_id, token_hash, expires_at)
            VALUES (:user_id, :token_hash, :expires_at)
        ');
        $stmt->execute([
            'user_id' => $user['id'],
            'token_hash' => hash('sha256', $token),
            'expires_at' => $expiresAt,
        ]);

        send_password_reset_email($user, $token);
    }

    $sent = true;
}

render_header('Recuperar senha');
?>
<section class="auth-shell panel p-4">
    <div class="section-title mb-2">Recuperacao</div>
    <h1 class="h3 mb-3">Esqueci minha senha</h1>

    <?php if ($sent): ?>
        <div class="alert alert-success">Se o usuario existir, enviaremos um link de recuperacao para o e-mail cadastrado.</div>
        <a class="btn btn-outline-primary" href="login.php">Voltar ao login</a>
    <?php else: ?>
        <p class="muted">Informe seu e-mail ou nome de usuario. O link de recuperacao expira automaticamente.</p>
        <form method="post" class="d-grid gap-3">
            <?= csrf_field() ?>
            <div>
                <label class="form-label" for="login">E-mail ou usuario</label>
                <input class="form-control" id="login" name="login" required value="<?= h($login) ?>">
            </div>
            <button class="btn btn-primary" type="submit">Enviar link</button>
        </form>
    <?php endif; ?>
</section>
<?php render_footer(); ?>
