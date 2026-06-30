<?php

declare(strict_types=1);

require __DIR__ . '/../../acesso/src/bootstrap.php';

$token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
$tokenHash = hash('sha256', $token);
$errors = [];
$validReset = null;

if ($token !== '') {
    $stmt = db()->prepare('
        SELECT pr.*, u.email, u.name
          FROM ' . table_name('password_resets') . ' pr
          JOIN ' . table_name('users') . ' u ON u.id = pr.user_id
         WHERE pr.token_hash = :token_hash
           AND pr.used_at IS NULL
           AND pr.expires_at > (now() at time zone \'utc\')
         ORDER BY pr.created_at DESC
         LIMIT 1
    ');
    $stmt->execute(['token_hash' => $tokenHash]);
    $validReset = $stmt->fetch() ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    if (!$validReset) {
        $errors[] = 'Link invalido ou expirado.';
    }

    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');
    $errors = array_merge($errors, validate_password_policy($password));
    if ($password !== $passwordConfirm) {
        $errors[] = 'A confirmacao de senha nao confere.';
    }

    if ($errors === []) {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE ' . table_name('users') . ' SET password_hash = :password_hash, must_change_password = false, updated_at = (now() at time zone \'utc\') WHERE id = :id')
                ->execute([
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'id' => $validReset['user_id'],
                ]);
            $pdo->prepare('UPDATE ' . table_name('password_resets') . ' SET used_at = (now() at time zone \'utc\') WHERE id = :id')
                ->execute(['id' => $validReset['id']]);
            $pdo->commit();

            flash('notice', 'Senha atualizada. Entre com a nova senha.');
            header('Location: login.php');
            exit;
        } catch (Throwable $e) {
            $pdo->rollBack();
            $errors[] = 'Nao foi possivel atualizar a senha.';
        }
    }
}

render_header('Redefinir senha');
?>
<section class="auth-shell panel p-4">
    <div class="section-title mb-2">Recuperacao</div>
    <h1 class="h3 mb-3">Redefinir senha</h1>

    <?php if ($errors !== []): ?>
        <div class="alert alert-danger">
            <?php foreach ($errors as $error): ?>
                <div><?= h($error) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (!$validReset): ?>
        <p class="muted">Este link nao existe, ja foi usado ou expirou.</p>
        <a class="btn btn-outline-primary" href="forgot_password.php">Solicitar novo link</a>
    <?php else: ?>
        <p class="muted">Defina uma nova senha para <?= h($validReset['email']) ?>.</p>
        <form method="post" class="d-grid gap-3">
            <?= csrf_field() ?>
            <input type="hidden" name="token" value="<?= h($token) ?>">
            <div>
                <label class="form-label" for="password">Nova senha</label>
                <input class="form-control" id="password" name="password" type="password" autocomplete="new-password" required>
            </div>
            <div>
                <label class="form-label" for="password_confirm">Confirmar nova senha</label>
                <input class="form-control" id="password_confirm" name="password_confirm" type="password" autocomplete="new-password" required>
            </div>
            <button class="btn btn-primary" type="submit">Atualizar senha</button>
        </form>
    <?php endif; ?>
</section>
<?php render_footer(); ?>
