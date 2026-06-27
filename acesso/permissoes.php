<?php

declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';
require_permission('acesso.users.permissions');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $userId = (int) ($_POST['user_id'] ?? 0);
    $roleIds = array_map('intval', $_POST['role_ids'] ?? []);

    if ($userId > 0) {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM ' . table_name('user_roles') . ' WHERE user_id = :user_id')->execute(['user_id' => $userId]);
            $stmt = $pdo->prepare('INSERT INTO ' . table_name('user_roles') . ' (user_id, role_id) VALUES (:user_id, :role_id) ON CONFLICT DO NOTHING');
            foreach ($roleIds as $roleId) {
                if ($roleId > 0) {
                    $stmt->execute(['user_id' => $userId, 'role_id' => $roleId]);
                }
            }
            $pdo->commit();
            flash('notice', 'Papeis atualizados.');
        } catch (Throwable $e) {
            $pdo->rollBack();
            flash('error', 'Nao foi possivel atualizar os papeis.');
        }
    }

    header('Location: permissoes.php?user_id=' . $userId);
    exit;
}

$users = db()->query('SELECT id, name, email FROM ' . table_name('users') . ' ORDER BY name')->fetchAll();
$roles = db()->query('SELECT * FROM ' . table_name('roles') . ' ORDER BY app_slug, slug')->fetchAll();

$selectedUserId = (int) ($_GET['user_id'] ?? ($users[0]['id'] ?? 0));
$assignedStmt = db()->prepare('SELECT role_id FROM ' . table_name('user_roles') . ' WHERE user_id = :user_id');
$assignedStmt->execute(['user_id' => $selectedUserId]);
$assignedRoleIds = array_map('intval', $assignedStmt->fetchAll(PDO::FETCH_COLUMN));

render_header('Papeis', 'permissoes');
?>
<div class="row g-4">
    <div class="col-lg-4">
        <section class="panel p-4">
            <div class="section-title mb-2">Usuario</div>
            <h1 class="h4 mb-3">Selecionar usuario</h1>
            <div class="list-group">
                <?php foreach ($users as $user): ?>
                    <a class="list-group-item list-group-item-action <?= (int) $user['id'] === $selectedUserId ? 'active' : '' ?>" href="permissoes.php?user_id=<?= h((string) $user['id']) ?>">
                        <strong><?= h($user['name']) ?></strong>
                        <div class="small"><?= h($user['email']) ?></div>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    </div>
    <div class="col-lg-8">
        <section class="panel p-4">
            <div class="section-title mb-2">Autorizacao</div>
            <h2 class="h4 mb-3">Atribuir papeis</h2>
            <p class="muted">Os papeis das aplicacoes ficam preparados aqui, mas ainda nao sao aplicados em CAREX, Fichario ou LDRT nesta fase.</p>

            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="user_id" value="<?= h((string) $selectedUserId) ?>">

                <div class="row g-3">
                    <?php foreach ($roles as $role): ?>
                        <div class="col-md-6">
                            <label class="panel p-3 d-block h-100">
                                <input class="form-check-input me-2" type="checkbox" name="role_ids[]" value="<?= h((string) $role['id']) ?>" <?= in_array((int) $role['id'], $assignedRoleIds, true) ? 'checked' : '' ?>>
                                <strong><?= h($role['slug']) ?></strong>
                                <div class="small muted"><?= h($role['name']) ?></div>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>

                <button class="btn btn-light mt-4" type="submit">Salvar papeis</button>
            </form>
        </section>
    </div>
</div>
<?php render_footer(); ?>
