<?php

declare(strict_types=1);

final class NotesRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function all(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM notes ORDER BY updated_at DESC');
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM notes WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $note = $stmt->fetch();
        return $note ?: null;
    }

    public function create(string $title, string $body): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO notes (title, body) VALUES (:title, :body)');
        $stmt->execute([
            'title' => $title,
            'body' => $body,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, string $title, string $body): void
    {
        $stmt = $this->pdo->prepare('UPDATE notes SET title = :title, body = :body WHERE id = :id');
        $stmt->execute([
            'title' => $title,
            'body' => $body,
            'id' => $id,
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM notes WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
