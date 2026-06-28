<?php

declare(strict_types=1);

require __DIR__ . '/../../acesso/src/bootstrap.php';
require_login();

$user = current_user();
$permissions = user_permissions((int) $user['id']);

$counts = [
    'users' => (int) db()->query('SELECT COUNT(*) FROM ' . table_name('users'))->fetchColumn(),
    'roles' => (int) db()->query('SELECT COUNT(*) FROM ' . table_name('roles'))->fetchColumn(),
    'permissions' => (int) db()->query('SELECT COUNT(*) FROM ' . table_name('permissions'))->fetchColumn(),
];

render_header('Painel', 'index');
?>
<div class="row g-4">
    <div class="col-lg-8">
        <section class="panel p-4 h-100">
            <div class="section-title mb-2">Modulo comum</div>
            <h1 class="h3 mb-3">Acesso RENAST</h1>
            <p class="muted mb-4">Este modulo foi criado em paralelo aos sistemas atuais. Nesta fase ele ainda nao altera CAREX, Fichario ou LDRT.</p>

            <?php if (!empty($user['must_change_password'])): ?>
                <div class="alert alert-warning">A senha inicial esta em uso. Recomenda-se troca-la em <a href="usuarios.php?edit=<?= h((string) $user['id']) ?>" class="alert-link">Usuarios</a>.</div>
            <?php endif; ?>

            <div class="row g-3">
                <div class="col-sm-4">
                    <div class="panel p-3">
                        <div class="muted small">Usuarios</div>
                        <div class="display-6"><?= h((string) $counts['users']) ?></div>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="panel p-3">
                        <div class="muted small">Papeis</div>
                        <div class="display-6"><?= h((string) $counts['roles']) ?></div>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="panel p-3">
                        <div class="muted small">Permissoes</div>
                        <div class="display-6"><?= h((string) $counts['permissions']) ?></div>
                    </div>
                </div>
            </div>
        </section>
    </div>
    <div class="col-lg-4">
        <section class="panel p-4 h-100">
            <div class="section-title mb-2">Sessao atual</div>
            <h2 class="h5"><?= h($user['name'] ?? '') ?></h2>
            <p class="muted mb-3"><?= h($user['email'] ?? '') ?></p>
            <div class="d-flex flex-wrap gap-2">
                <?php foreach ($permissions as $permission): ?>
                    <span class="badge badge-soft"><?= h($permission) ?></span>
                <?php endforeach; ?>
            </div>
        </section>
    </div>
</div>
<?php render_footer(); ?>
