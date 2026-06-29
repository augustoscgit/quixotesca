<?php

declare(strict_types=1);

require __DIR__ . '/../../acesso/src/bootstrap.php';
require_platform_admin();

$errors = [];
$canCreate = has_permission('acesso.users.create');
$canUpdate = has_permission('acesso.users.update');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'create' && $canCreate) {
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')), 'UTF-8');
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($name === '') {
            $errors[] = 'Informe o nome.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Informe um e-mail valido.';
        }
        $errors = array_merge($errors, validate_password_policy($password));

        if ($errors === []) {
            try {
                $stmt = db()->prepare('
                    INSERT INTO ' . table_name('users') . " (name, email, username, password_hash, status, email_verified_at)
                    VALUES (:name, :email, NULLIF(:username, ''), :password_hash, 'active', (now() at time zone 'utc'))
                    RETURNING id
                ");
                $stmt->execute([
                    'name' => $name,
                    'email' => $email,
                    'username' => $username,
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                ]);
                $newUserId = (int) $stmt->fetchColumn();
                $roleStmt = db()->prepare('SELECT id FROM ' . table_name('roles') . " WHERE slug = 'user' LIMIT 1");
                $roleStmt->execute();
                $userRoleId = (int) $roleStmt->fetchColumn();
                if ($newUserId > 0 && $userRoleId > 0) {
                    db()->prepare('INSERT INTO ' . table_name('user_roles') . ' (user_id, role_id) VALUES (:user_id, :role_id) ON CONFLICT DO NOTHING')
                        ->execute(['user_id' => $newUserId, 'role_id' => $userRoleId]);
                }
                flash('notice', 'Usuario criado.');
                header('Location: usuarios.php');
                exit;
            } catch (Throwable $e) {
                $errors[] = 'Nao foi possivel criar. Verifique se e-mail ou usuario ja existem.';
            }
        }
    }

    if ($action === 'update' && $canUpdate) {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')), 'UTF-8');
        $username = trim((string) ($_POST['username'] ?? ''));
        $status = (string) ($_POST['status'] ?? 'active');
        $password = (string) ($_POST['password'] ?? '');
        $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');

        if ($id <= 0 || $name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !in_array($status, ['active', 'inactive'], true)) {
            $errors[] = 'Dados invalidos para atualizacao.';
        }

        if ($password !== '' && $password !== $passwordConfirm) {
            $errors[] = 'A confirmacao da nova senha nao confere.';
        }

        if ($errors === []) {
            try {
                if ($password !== '') {
                    $stmt = db()->prepare('
                        UPDATE ' . table_name('users') . '
                           SET name = :name,
                               email = :email,
                               username = NULLIF(:username, \'\'),
                               status = :status,
                               password_hash = :password_hash,
                               must_change_password = false,
                               updated_at = (now() at time zone \'utc\')
                          WHERE id = :id
                    ');
                    $stmt->execute([
                        'name' => $name,
                        'email' => $email,
                        'username' => $username,
                        'status' => $status,
                        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                        'id' => $id,
                    ]);
                } else {
                    $stmt = db()->prepare('
                        UPDATE ' . table_name('users') . '
                           SET name = :name,
                               email = :email,
                               username = NULLIF(:username, \'\'),
                               status = :status,
                               updated_at = (now() at time zone \'utc\')
                          WHERE id = :id
                    ');
                    $stmt->execute([
                        'name' => $name,
                        'email' => $email,
                        'username' => $username,
                        'status' => $status,
                        'id' => $id,
                    ]);
                }

                flash('notice', 'Usuario atualizado.');
                header('Location: usuarios.php');
                exit;
            } catch (Throwable $e) {
                $errors[] = 'Nao foi possivel atualizar. Verifique se e-mail ou usuario ja existem.';
            }
        }
    }
}

$users = db()->query('SELECT * FROM ' . table_name('users') . ' ORDER BY name')->fetchAll();

render_header('Usuarios', 'usuarios');
?>
<div class="row g-4">
    <div class="col-lg-7">
        <section class="panel p-4">
            <div class="section-title mb-2">Gestao</div>
            <h1 class="h3 mb-4">Usuarios</h1>

            <?php if ($errors !== []): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $error): ?>
                        <div><?= h($error) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>E-mail</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($users as $item): ?>
                        <tr>
                            <td>
                                <strong><?= h($item['name']) ?></strong>
                                <?php if (!empty($item['username'])): ?>
                                    <div class="small muted">@<?= h($item['username']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?= h($item['email']) ?></td>
                            <td><span class="badge <?= $item['status'] === 'active' ? 'bg-success' : 'bg-warning' ?>"><?= h($item['status']) ?></span></td>
                            <td class="text-end"><a class="btn btn-sm btn-outline-light" href="usuarios.php?edit=<?= h((string) $item['id']) ?>">Editar</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
    <div class="col-lg-5">
        <?php
        $editId = (int) ($_GET['edit'] ?? 0);
        $editUser = null;
        if ($editId > 0) {
            foreach ($users as $item) {
                if ((int) $item['id'] === $editId) {
                    $editUser = $item;
                    break;
                }
            }
        }
        ?>
        <section class="panel p-4">
            <div class="section-title mb-2"><?= $editUser ? 'Editar' : 'Novo' ?></div>
            <h2 class="h4 mb-4"><?= $editUser ? 'Editar usuario' : 'Criar usuario' ?></h2>

            <?php if (($editUser && $canUpdate) || (!$editUser && $canCreate)): ?>
                <form method="post" class="d-grid gap-3">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="<?= $editUser ? 'update' : 'create' ?>">
                    <?php if ($editUser): ?>
                        <input type="hidden" name="id" value="<?= h((string) $editUser['id']) ?>">
                    <?php endif; ?>
                    <div>
                        <label class="form-label" for="name">Nome</label>
                        <input class="form-control" id="name" name="name" required value="<?= h($editUser['name'] ?? '') ?>">
                    </div>
                    <div>
                        <label class="form-label" for="email">E-mail</label>
                        <input class="form-control" id="email" name="email" type="email" required value="<?= h($editUser['email'] ?? '') ?>">
                    </div>
                    <div>
                        <label class="form-label" for="username">Usuario</label>
                        <input class="form-control" id="username" name="username" value="<?= h($editUser['username'] ?? '') ?>">
                    </div>
                    <?php if ($editUser): ?>
                        <div>
                            <label class="form-label" for="status">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="active" <?= ($editUser['status'] ?? '') === 'active' ? 'selected' : '' ?>>Ativo</option>
                                <option value="inactive" <?= ($editUser['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inativo</option>
                            </select>
                        </div>
                    <?php endif; ?>
                    <div>
                        <label class="form-label" for="password"><?= $editUser ? 'Nova senha opcional' : 'Senha inicial' ?></label>
                        <input class="form-control" id="password" name="password" type="password" <?= $editUser ? '' : 'required' ?>>
                    </div>
                    <?php if ($editUser): ?>
                        <div>
                            <label class="form-label" for="password_confirm">Confirmar nova senha</label>
                            <input class="form-control" id="password_confirm" name="password_confirm" type="password" autocomplete="new-password">
                        </div>
                    <?php endif; ?>
                    <button class="btn btn-light" type="submit">Salvar</button>
                </form>
            <?php else: ?>
                <p class="muted mb-0">Seu usuario nao possui permissao para esta operacao.</p>
            <?php endif; ?>
        </section>
    </div>
</div>
<?php render_footer(); ?>
