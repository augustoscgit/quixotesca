<?php

declare(strict_types=1);

namespace Carex\Database;

use PDO;
use RuntimeException;

final class UserRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Inserts a new Google user or updates name & profile_picture on login.
     * Enforces the hardcoded admin email rule.
     * Enforces status validation ('desligado').
     *
     * @param array{google_id: string, email: string, name: string, profile_picture: ?string} $profile
     * @return array<string, mixed>
     * @throws RuntimeException If user status is 'desligado'.
     */
    public function upsertGoogleUser(array $profile): array
    {
        $googleId = trim($profile['google_id']);
        $email = trim(strtolower($profile['email']));
        $name = trim($profile['name']);
        $picture = $profile['profile_picture'] !== null ? trim($profile['profile_picture']) : null;

        if ($googleId === '' || $email === '') {
            throw new RuntimeException('Google ID e e-mail são obrigatórios para autenticação.');
        }

        // Check if user already exists by google_id or by email from a previous local/mock login.
        $stmt = $this->pdo->prepare("
            SELECT id, google_id, name, email, profile_picture, role, status, remember_token
              FROM users 
             WHERE google_id = :google_id
                OR lower(email) = lower(:email)
             ORDER BY CASE WHEN google_id = :google_id THEN 0 ELSE 1 END
             LIMIT 1
        ");
        $stmt->execute([
            'google_id' => $googleId,
            'email' => $email,
        ]);
        $user = $stmt->fetch();

        $isAdminEmail = ($email === 'augustosc@gmail.com');

        if (!$user) {
            // First login - Insert new user
            $role = $isAdminEmail ? 'admin' : 'usuario';
            
            $insertStmt = $this->pdo->prepare("
                INSERT INTO users (google_id, name, email, profile_picture, role, status, updated_at)
                VALUES (:google_id, :name, :email, :profile_picture, :role, 'ativo', CURRENT_TIMESTAMP)
                RETURNING id, google_id, name, email, profile_picture, role, status, remember_token
            ");
            $insertStmt->execute([
                'google_id' => $googleId,
                'name' => $name,
                'email' => $email,
                'profile_picture' => $picture,
                'role' => $role
            ]);
            $user = $insertStmt->fetch();
        } else {
            // Subsequente login - Update name and profile picture
            $role = $user['role'];
            if ($isAdminEmail && $role !== 'admin') {
                $role = 'admin'; // Force admin role
            }

            // Interrupt flow if user is suspended/fired
            if ($user['status'] === 'desligado') {
                throw new RuntimeException('Aviso de desligamento. Por favor, entre em contato com o administrador.');
            }

            $updateStmt = $this->pdo->prepare("
                UPDATE users
                   SET google_id = :google_id,
                       name = :name,
                       profile_picture = :profile_picture,
                       role = :role,
                       updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id
                RETURNING id, google_id, name, email, profile_picture, role, status, remember_token
            ");
            $updateStmt->execute([
                'google_id' => $googleId,
                'name' => $name,
                'profile_picture' => $picture,
                'role' => $role,
                'id' => (int) $user['id']
            ]);
            $user = $updateStmt->fetch();
        }

        if (!$user) {
            throw new RuntimeException('Falha ao autenticar ou gravar dados do usuário.');
        }

        return $user;
    }

    /**
     * Updates the remember me token for a user.
     */
    public function updateRememberToken(int $userId, ?string $token): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE users 
               SET remember_token = :token,
                   updated_at = CURRENT_TIMESTAMP
             WHERE id = :id
        ");
        $stmt->execute([
            'token' => $token,
            'id' => $userId
        ]);
    }

    /**
     * Finds a user by their remember token.
     * Enforces active status checks.
     *
     * @param string $token
     * @return array<string, mixed>|null
     * @throws RuntimeException If user status is 'desligado'.
     */
    public function getUserByRememberToken(string $token): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, google_id, name, email, profile_picture, role, status, remember_token
              FROM users 
             WHERE remember_token = :token
        ");
        $stmt->execute(['token' => $token]);
        $user = $stmt->fetch();

        if ($user && $user['status'] === 'desligado') {
            throw new RuntimeException('Aviso de desligamento. Por favor, entre em contato com o administrador.');
        }

        return $user ?: null;
    }

    /**
     * Retrieves user by id.
     */
    public function getUserById(int $userId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, google_id, name, email, profile_picture, role, status, remember_token
              FROM users 
             WHERE id = :id
        ");
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    /**
     * Retrieves user by email.
     */
    public function getUserByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, google_id, name, email, profile_picture, role, status, remember_token
              FROM users 
             WHERE lower(email) = lower(:email)
        ");
        $stmt->execute(['email' => trim($email)]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    /**
     * Updates user details in the database.
     * Enforces the hardcoded admin rules for 'augustosc@gmail.com'.
     *
     * @param int $userId
     * @param array{name: string, email: string, profile_picture: ?string, role: string, status: string} $data
     * @return array<string, mixed>
     */
    public function updateUser(int $userId, array $data): array
    {
        $name = trim($data['name']);
        $email = trim(strtolower($data['email']));
        $picture = $data['profile_picture'] !== null && trim($data['profile_picture']) !== '' ? trim($data['profile_picture']) : null;
        $role = trim(strtolower($data['role']));
        $status = trim(strtolower($data['status']));

        if ($name === '' || $email === '') {
            throw new \InvalidArgumentException('Nome e e-mail são obrigatórios.');
        }

        if (!in_array($role, ['admin', 'especialista', 'usuario'], true)) {
            throw new \InvalidArgumentException('Perfil de usuário inválido.');
        }

        if (!in_array($status, ['ativo', 'desligado'], true)) {
            throw new \InvalidArgumentException('Status de usuário inválido.');
        }

        // Retrieve existing record to check admin restriction
        $stmtExisting = $this->pdo->prepare('SELECT email FROM users WHERE id = :id');
        $stmtExisting->execute(['id' => $userId]);
        $existing = $stmtExisting->fetch();

        if (!$existing) {
            throw new \InvalidArgumentException('Usuário não encontrado.');
        }

        // Primary admin constraints
        if (strtolower((string) $existing['email']) === 'augustosc@gmail.com') {
            if ($email !== 'augustosc@gmail.com') {
                throw new \InvalidArgumentException('Não é permitido alterar o e-mail da conta administradora principal.');
            }
            if ($role !== 'admin' || $status !== 'ativo') {
                throw new \InvalidArgumentException('A conta administradora principal deve permanecer ativa e com perfil admin.');
            }
        }

        $stmt = $this->pdo->prepare("
            UPDATE users
               SET name = :name,
                   email = :email,
                   profile_picture = :profile_picture,
                   role = :role,
                   status = :status,
                   remember_token = CASE WHEN :status_for_token = 'desligado' THEN NULL ELSE remember_token END,
                   updated_at = CURRENT_TIMESTAMP
             WHERE id = :id
         RETURNING id, google_id, name, email, profile_picture, role, status, created_at, updated_at
        ");
        $stmt->execute([
            'name' => $name,
            'email' => $email,
            'profile_picture' => $picture,
            'role' => $role,
            'status' => $status,
            'status_for_token' => $status,
            'id' => $userId
        ]);

        $updated = $stmt->fetch();
        if (!$updated) {
            throw new \RuntimeException('Falha ao atualizar os dados do usuário.');
        }

        return [
            'id' => (int) $updated['id'],
            'google_id' => (string) $updated['google_id'],
            'name' => (string) $updated['name'],
            'email' => (string) $updated['email'],
            'profile_picture' => $updated['profile_picture'] === null ? '' : (string) $updated['profile_picture'],
            'role' => (string) $updated['role'],
            'status' => (string) $updated['status'],
            'created_at' => (string) $updated['created_at'],
            'updated_at' => (string) $updated['updated_at'],
        ];
    }
}
