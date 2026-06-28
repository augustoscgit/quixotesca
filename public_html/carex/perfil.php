<?php

declare(strict_types=1);

use Carex\Database\Connection;
use Carex\Database\UserRepository;
use Carex\Http\Auth;
use Carex\Http\Security;

$config = require dirname(__DIR__, 2) . '/carex' . '/src/bootstrap.php';

Auth::requireLogin();
Security::applyHeaders();

$currentUser = Auth::currentUser();
$isAdmin = ($currentUser['role'] ?? '') === 'admin';

$pdo = Connection::make($config['database']);
$userRepository = new UserRepository($pdo);

// Determine which user's profile to display
$targetUserId = (int) ($_GET['id'] ?? 0);
if ($targetUserId <= 0 || !$isAdmin) {
    // Regular users can only view their own profile
    $targetUserId = (int) ($currentUser['id'] ?? 0);
}

$profileUser = $userRepository->getUserById($targetUserId);
if (!$profileUser) {
    // Fallback to own profile if not found
    $targetUserId = (int) ($currentUser['id'] ?? 0);
    $profileUser = $userRepository->getUserById($targetUserId);
}

$error = '';
$success = isset($_GET['saved']) && $_GET['saved'] === 'true';

// Handle profile updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::isValidCsrfToken($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        exit('Token CSRF inválido.');
    }

    if (!$isAdmin) {
        $error = 'Apenas administradores podem atualizar perfis de usuário.';
    } else {
        try {
            $data = [
                'name' => trim((string) ($_POST['name'] ?? '')),
                'email' => trim((string) ($_POST['email'] ?? '')),
                'profile_picture' => trim((string) ($_POST['profile_picture'] ?? '')),
                'role' => trim((string) ($_POST['role'] ?? '')),
                'status' => trim((string) ($_POST['status'] ?? ''))
            ];

            // Enforce database writes
            $writeConfig = $config['database'];
            $writeConfig['allow_writes'] = true;
            $writePdo = Connection::make($writeConfig);
            $writeRepo = new UserRepository($writePdo);

            $updatedUser = $writeRepo->updateUser($targetUserId, $data);

            // Update session if editing self
            if ($targetUserId === (int) ($currentUser['id'] ?? 0)) {
                Auth::updateCurrentUser($updatedUser);
                $currentUser = Auth::currentUser();
            }

            // Redirect to avoid form resubmission
            $redirectParams = ['saved' => 'true'];
            if ($targetUserId !== (int) ($currentUser['id'] ?? 0)) {
                $redirectParams['id'] = $targetUserId;
            }

            header('Location: perfil.php?' . http_build_query($redirectParams));
            exit;

        } catch (InvalidArgumentException | RuntimeException $exception) {
            $error = $exception->getMessage();
        } catch (Throwable $exception) {
            $error = $config['app']['debug'] ? $exception->getMessage() : 'Não foi possível atualizar o perfil.';
        }
    }
}

$bootstrapCss = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css';
$bootstrapJs  = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js';
$csrfToken = Security::csrfToken();
?>
<!doctype html>
<html lang="pt-BR" data-module="carex">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="csrf-token" content="<?= Security::e($csrfToken) ?>">
    <title>CAREX | Perfil do Usuário</title>
    <link href="../assets/favicon.png" rel="icon" type="image/png">
    <link href="<?= Security::e($bootstrapCss) ?>" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="assets/app.css" rel="stylesheet">
    <style>
        .profile-container {
            max-width: 800px;
            margin: 2rem auto 4rem;
        }
        .profile-card {
            background: var(--bs-body-bg);
            border: 1px solid var(--bs-border-color-translucent);
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            overflow: hidden;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .profile-header-gradient {
            background: linear-gradient(135deg, #4f46e5 0%, #06b6d4 100%);
            height: 120px;
            position: relative;
        }
        .profile-avatar-wrapper {
            width: 120px;
            height: 120px;
            margin: -60px auto 0;
            position: relative;
            z-index: 2;
        }
        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 5px solid var(--bs-body-bg);
            object-fit: cover;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .avatar-fallback {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 5px solid var(--bs-body-bg);
            background: linear-gradient(135deg, #10b981 0%, #3b82f6 100%);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            font-weight: 700;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }
        .info-item {
            background: var(--bs-tertiary-bg);
            padding: 1rem;
            border-radius: 10px;
            border: 1px solid var(--bs-border-color-translucent);
        }
        .info-item-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--bs-secondary-color);
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        .info-item-value {
            font-weight: 500;
            color: var(--bs-body-color);
        }
        .badge-role-admin { background-color: rgba(79, 70, 229, 0.1); color: #4f46e5; border: 1px solid rgba(79, 70, 229, 0.2); }
        .badge-role-especialista { background-color: rgba(16, 185, 129, 0.1); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.2); }
        .badge-role-usuario { background-color: rgba(107, 114, 128, 0.1); color: #6b7280; border: 1px solid rgba(107, 114, 128, 0.2); }
        
        .badge-status-ativo { background-color: rgba(16, 185, 129, 0.1); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.2); }
        .badge-status-desligado { background-color: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2); }
    </style>
    <script src="../../assets/js/theme-switcher.js"></script>
    <link href="../../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php
    $activePage = 'trabalho'; // Keeps menu context neutral
    require dirname(__DIR__, 2) . '/carex' . '/src/templates/navbar.php';
    ?>

    <main class="container py-4">
        <div class="profile-container">
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show py-2 mb-3 shadow-sm border-success-subtle" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i> Alterações salvas com sucesso!
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                </div>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger alert-dismissible fade show py-2 mb-3 shadow-sm border-danger-subtle" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= Security::e($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                </div>
            <?php endif; ?>

            <!-- Breadcrumb Navigation for Admins -->
            <?php if ($isAdmin && $targetUserId !== (int) ($currentUser['id'] ?? 0)): ?>
                <nav aria-label="Navegação de contexto" class="mb-3">
                    <ol class="breadcrumb small">
                        <li class="breadcrumb-item"><a href="administrativo.php?tab=usuarios" class="text-decoration-none"><i class="bi bi-arrow-left"></i> Painel Administrativo</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Perfil de <?= Security::e($profileUser['name']) ?></li>
                    </ol>
                </nav>
            <?php endif; ?>

            <article class="profile-card">
                <div class="profile-header-gradient" aria-hidden="true"></div>
                
                <div class="profile-avatar-wrapper">
                    <?php if (!empty($profileUser['profile_picture'])): ?>
                        <img src="<?= Security::e($profileUser['profile_picture']) ?>" alt="Foto de <?= Security::e($profileUser['name']) ?>" class="profile-avatar">
                    <?php else: ?>
                        <div class="avatar-fallback" aria-label="Avatar padrão">
                            <?= Security::e(strtoupper(substr($profileUser['name'], 0, 1))) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="p-4 pt-3 text-center border-bottom">
                    <h1 class="h3 mb-1"><?= Security::e($profileUser['name']) ?></h1>
                    <div class="text-secondary small mb-3"><?= Security::e($profileUser['email']) ?></div>
                    
                    <div class="d-flex justify-content-center gap-2 mb-2">
                        <span class="badge badge-role-<?= Security::e($profileUser['role']) ?> px-3 py-1.5 rounded-pill text-uppercase">
                            Perfil: <?= Security::e($profileUser['role']) ?>
                        </span>
                        <span class="badge badge-status-<?= Security::e($profileUser['status']) ?> px-3 py-1.5 rounded-pill text-uppercase">
                            Status: <?= Security::e($profileUser['status']) ?>
                        </span>
                    </div>
                </div>

                <!-- Profile Data view -->
                <div class="p-4" id="profileViewSection">
                    <h2 class="h5 mb-3 d-flex align-items-center gap-2"><i class="bi bi-info-circle text-primary"></i> Informações do Cadastro</h2>
                    
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-item-label">ID do Google</div>
                            <div class="info-item-value font-monospace text-truncate" title="<?= Security::e($profileUser['google_id']) ?>">
                                <?= Security::e($profileUser['google_id']) ?>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-item-label">Cadastrado em</div>
                            <div class="info-item-value">
                                <?= date('d/m/Y \à\s H:i', strtotime($profileUser['created_at'])) ?>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-item-label">Último Acesso</div>
                            <div class="info-item-value">
                                <?= date('d/m/Y \à\s H:i', strtotime($profileUser['updated_at'])) ?>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-center mt-4">
                        <button type="button" class="btn btn-primary d-flex align-items-center gap-2" id="btnEditToggle">
                            <i class="bi bi-pencil-square"></i> Editar Perfil
                        </button>
                    </div>
                </div>

                <!-- Profile Edit Form (Hidden by default) -->
                <div class="p-4 bg-light border-top d-none" id="profileEditSection">
                    <h2 class="h5 mb-3 d-flex align-items-center gap-2"><i class="bi bi-gear-fill text-primary"></i> Configurações do Perfil</h2>
                    
                    <?php if (!$isAdmin): ?>
                        <div class="alert alert-warning py-2 small mb-3">
                            <i class="bi bi-shield-lock-fill me-1"></i>
                            <strong>Dados Protegidos:</strong> Estes dados são integrados e gerenciados. Apenas administradores do sistema podem editá-los.
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="perfil.php?<?= http_build_query($targetUserId !== (int) ($currentUser['id'] ?? 0) ? ['id' => $targetUserId] : []) ?>" class="needs-validation" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= Security::e($csrfToken) ?>">
                        
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label for="inputName" class="form-label small fw-semibold">Nome Completo</label>
                                <input type="text" class="form-control" id="inputName" name="name" value="<?= Security::e($profileUser['name']) ?>" required <?= $isAdmin ? '' : 'disabled' ?>>
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="inputEmail" class="form-label small fw-semibold">E-mail Corporativo</label>
                                <input type="email" class="form-control" id="inputEmail" name="email" value="<?= Security::e($profileUser['email']) ?>" required <?= $isAdmin ? '' : 'disabled' ?>>
                            </div>
                            <div class="col-12">
                                <label for="inputPicture" class="form-label small fw-semibold">URL da Foto de Perfil (Google)</label>
                                <input type="url" class="form-control font-monospace" id="inputPicture" name="profile_picture" value="<?= Security::e($profileUser['profile_picture']) ?>" <?= $isAdmin ? '' : 'disabled' ?>>
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="selectRole" class="form-label small fw-semibold">Perfil de Acesso</label>
                                <select class="form-select" id="selectRole" name="role" required <?= $isAdmin ? '' : 'disabled' ?>>
                                    <option value="usuario" <?= $profileUser['role'] === 'usuario' ? 'selected' : '' ?>>Usuário Comum</option>
                                    <option value="especialista" <?= $profileUser['role'] === 'especialista' ? 'selected' : '' ?>>Especialista</option>
                                    <option value="admin" <?= $profileUser['role'] === 'admin' ? 'selected' : '' ?>>Administrador</option>
                                </select>
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="selectStatus" class="form-label small fw-semibold">Situação do Cadastro</label>
                                <select class="form-select" id="selectStatus" name="status" required <?= $isAdmin ? '' : 'disabled' ?>>
                                    <option value="ativo" <?= $profileUser['status'] === 'ativo' ? 'selected' : '' ?>>Ativo</option>
                                    <option value="desligado" <?= $profileUser['status'] === 'desligado' ? 'selected' : '' ?>>Desligado</option>
                                </select>
                            </div>
                        </div>

                        <div class="d-flex justify-content-center gap-3 mt-4">
                            <button type="button" class="btn btn-outline-secondary" id="btnEditCancel">
                                Cancelar
                            </button>
                            <?php if ($isAdmin): ?>
                                <button type="submit" class="btn btn-success d-flex align-items-center gap-2">
                                    <i class="bi bi-save-fill"></i> Salvar Alterações
                                </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </article>
        </div>
    </main>

    <script src="<?= Security::e($bootstrapJs) ?>" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const btnToggle = document.getElementById('btnEditToggle');
            const btnCancel = document.getElementById('btnEditCancel');
            const viewSection = document.getElementById('profileViewSection');
            const editSection = document.getElementById('profileEditSection');

            if (btnToggle && btnCancel && viewSection && editSection) {
                btnToggle.addEventListener('click', () => {
                    viewSection.classList.add('d-none');
                    editSection.classList.remove('d-none');
                });

                btnCancel.addEventListener('click', () => {
                    editSection.classList.add('d-none');
                    viewSection.classList.remove('d-none');
                });
            }
        });
    </script>
</body>
</html>
