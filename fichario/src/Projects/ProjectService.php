<?php
declare(strict_types=1);

namespace App\Projects;

use PDO;
use RuntimeException;
use Throwable;

class ProjectService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly bool $isAdmin
    ) {
    }

    public function fetchProject(int $projectId, int $userId): ?array
    {
        $sql = 'SELECT * FROM projects WHERE id = :id';
        $params = [':id' => $projectId];

        if (!$this->isAdmin) {
            $sql .= ' AND owner_user_id = :owner_user_id';
            $params[':owner_user_id'] = $userId;
        }

        $stmt = $this->pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        $project = $stmt->fetch();

        return $project ?: null;
    }

    public function fetchSection(int $projectId, int $sectionId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM project_sections WHERE id = :id AND project_id = :project_id LIMIT 1');
        $stmt->execute([':id' => $sectionId, ':project_id' => $projectId]);
        $section = $stmt->fetch();

        return $section ?: null;
    }

    public function assertSection(int $projectId, int $sectionId, string $message = 'Secao nao encontrada.'): array
    {
        $section = $this->fetchSection($projectId, $sectionId);
        if ($section === null) {
            throw new RuntimeException($message);
        }

        return $section;
    }

    public function touchProject(int $projectId): void
    {
        $stmt = $this->pdo->prepare('UPDATE projects SET updated_at = CURRENT_TIMESTAMP WHERE id = :id');
        $stmt->execute([':id' => $projectId]);
    }

    public function nextSectionPosition(int $projectId): int
    {
        $stmt = $this->pdo->prepare('SELECT COALESCE(MAX(position), 0) + 1 FROM project_sections WHERE project_id = :project_id');
        $stmt->execute([':project_id' => $projectId]);

        return (int) $stmt->fetchColumn();
    }

    public function nextMarkingPosition(int $sectionId): int
    {
        $stmt = $this->pdo->prepare('SELECT COALESCE(MAX(position), 0) + 1 FROM project_section_notes WHERE section_id = :section_id');
        $stmt->execute([':section_id' => $sectionId]);

        return (int) $stmt->fetchColumn();
    }

    public function nextNotePosition(int $sectionId): int
    {
        return $this->nextMarkingPosition($sectionId);
    }

    public function moveSection(int $projectId, int $sectionId, string $direction): void
    {
        $current = $this->assertSection($projectId, $sectionId);

        $operator = $direction === 'up' ? '<' : '>';
        $order = $direction === 'up' ? 'DESC' : 'ASC';
        $stmt = $this->pdo->prepare("
            SELECT * FROM project_sections
            WHERE project_id = :project_id AND position $operator :position
            ORDER BY position $order, id $order
            LIMIT 1
        ");
        $stmt->execute([':project_id' => $projectId, ':position' => (int) $current['position']]);
        $target = $stmt->fetch();

        if (!$target) {
            return;
        }

        $swap = $this->pdo->prepare('UPDATE project_sections SET position = :position, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
        $swap->execute([':position' => (int) $target['position'], ':id' => $sectionId]);
        $swap->execute([':position' => (int) $current['position'], ':id' => (int) $target['id']]);
    }

    public function moveMarkingWithinSection(int $sectionId, int $noteId, string $direction): void
    {
        $stmt = $this->pdo->prepare('SELECT * FROM project_section_notes WHERE section_id = :section_id AND note_id = :note_id LIMIT 1');
        $stmt->execute([':section_id' => $sectionId, ':note_id' => $noteId]);
        $current = $stmt->fetch();

        if (!$current) {
            throw new RuntimeException('Marcação nao vinculada a esta secao.');
        }

        $operator = $direction === 'up' ? '<' : '>';
        $order = $direction === 'up' ? 'DESC' : 'ASC';
        $targetStmt = $this->pdo->prepare("
            SELECT * FROM project_section_notes
            WHERE section_id = :section_id AND position $operator :position
            ORDER BY position $order, note_id $order
            LIMIT 1
        ");
        $targetStmt->execute([':section_id' => $sectionId, ':position' => (int) $current['position']]);
        $target = $targetStmt->fetch();

        if (!$target) {
            return;
        }

        $swap = $this->pdo->prepare('UPDATE project_section_notes SET position = :position WHERE section_id = :section_id AND note_id = :note_id');
        $swap->execute([
            ':position' => (int) $target['position'],
            ':section_id' => $sectionId,
            ':note_id' => $noteId,
        ]);
        $swap->execute([
            ':position' => (int) $current['position'],
            ':section_id' => $sectionId,
            ':note_id' => (int) $target['note_id'],
        ]);
    }

    public function moveNoteWithinSection(int $sectionId, int $noteId, string $direction): void
    {
        $this->moveMarkingWithinSection($sectionId, $noteId, $direction);
    }

    public function ensureGeneralSection(int $projectId): int
    {
        $stmt = $this->pdo->prepare("SELECT id FROM project_sections WHERE project_id = :project_id AND lower(title) = 'geral' LIMIT 1");
        $stmt->execute([':project_id' => $projectId]);
        $sectionId = $stmt->fetchColumn();
        if ($sectionId) {
            return (int) $sectionId;
        }

        $insert = $this->pdo->prepare('
            INSERT INTO project_sections (project_id, title, context, position)
            VALUES (:project_id, :title, :context, :position)
        ');
        $insert->execute([
            ':project_id' => $projectId,
            ':title' => 'Geral',
            ':context' => 'Marcações vinculadas diretamente ao projeto.',
            ':position' => $this->nextSectionPosition($projectId),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function assertMarkingExists(int $noteId): void
    {
        $stmt = $this->pdo->prepare('SELECT id FROM article_tag_quotes WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $noteId]);
        if (!$stmt->fetchColumn()) {
            throw new RuntimeException('Marcação nao encontrada.');
        }
    }

    public function assertNoteExists(int $noteId): void
    {
        $this->assertMarkingExists($noteId);
    }

    public function linkMarkingToSection(int $sectionId, int $noteId): void
    {
        $this->assertMarkingExists($noteId);

        $stmt = $this->pdo->prepare('
            INSERT INTO project_section_notes (section_id, note_id, position)
            VALUES (:section_id, :note_id, :position)
            ON CONFLICT (section_id, note_id) DO NOTHING
        ');
        $stmt->execute([
            ':section_id' => $sectionId,
            ':note_id' => $noteId,
            ':position' => $this->nextMarkingPosition($sectionId),
        ]);
    }

    public function linkNoteToSection(int $sectionId, int $noteId): void
    {
        $this->linkMarkingToSection($sectionId, $noteId);
    }

    public function unlinkMarkingFromSection(int $sectionId, int $noteId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM project_section_notes WHERE section_id = :section_id AND note_id = :note_id');
        $stmt->execute([':section_id' => $sectionId, ':note_id' => $noteId]);
    }

    public function unlinkNoteFromSection(int $sectionId, int $noteId): void
    {
        $this->unlinkMarkingFromSection($sectionId, $noteId);
    }

    public function moveMarkingToSection(int $fromSectionId, int $toSectionId, int $noteId): void
    {
        if ($fromSectionId === $toSectionId) {
            throw new RuntimeException('As secoes de origem e destino sao iguais.');
        }

        $checkStmt = $this->pdo->prepare('SELECT 1 FROM project_section_notes WHERE section_id = :section_id AND note_id = :note_id');
        $checkStmt->execute([':section_id' => $fromSectionId, ':note_id' => $noteId]);
        if (!$checkStmt->fetchColumn()) {
            throw new RuntimeException('Marcação nao encontrada na secao de origem.');
        }

        $this->pdo->beginTransaction();
        try {
            $this->unlinkMarkingFromSection($fromSectionId, $noteId);
            $this->linkMarkingToSection($toSectionId, $noteId);
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function moveNoteToSection(int $fromSectionId, int $toSectionId, int $noteId): void
    {
        $this->moveMarkingToSection($fromSectionId, $toSectionId, $noteId);
    }

    public function updateMarking(int $noteId, string $quoteText, string $comment): void
    {
        $this->assertMarkingExists($noteId);

        $update = $this->pdo->prepare('
            UPDATE article_tag_quotes
            SET quote_text = :quote_text, comment = :comment, updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ');
        $update->execute([
            ':quote_text' => $quoteText,
            ':comment' => $comment,
            ':id' => $noteId,
        ]);
    }

    public function updateNote(int $noteId, string $quoteText, string $comment): void
    {
        $this->updateMarking($noteId, $quoteText, $comment);
    }

    public static function markingOptionLabel(array $note): string
    {
        $article = trim((string) ($note['article_title'] ?? 'Artigo sem titulo'));
        $year = trim((string) ($note['year'] ?? ''));
        $teaserSource = trim((string) ($note['comment'] ?? '')) !== ''
            ? (string) $note['comment']
            : (string) ($note['quote_text'] ?? '');
        $teaser = text_teaser($teaserSource, 90);
        $prefix = '#' . (int) $note['id'] . ' - ' . $article . ($year !== '' ? ' (' . $year . ')' : '');

        return $teaser !== '' ? $prefix . ' - ' . $teaser : $prefix;
    }

    public static function noteOptionLabel(array $note): string
    {
        return self::markingOptionLabel($note);
    }
}
