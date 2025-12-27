<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

$repository = new NotesRepository(db());
$errors = [];
$flash = null;
$formMode = 'create';
$formTitle = '';
$formBody = '';
$editId = null;

function sanitize(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function redirect_with_status(string $status): void
{
    header('Location: index.php?status=' . urlencode($status));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';
    $title = trim($_POST['title'] ?? '');
    $body = trim($_POST['body'] ?? '');
    $noteId = isset($_POST['note_id']) ? (int) $_POST['note_id'] : null;

    if ($action === 'delete') {
        if ($noteId === null) {
            $errors[] = 'Missing note identifier.';
        } else {
            $repository->delete($noteId);
            redirect_with_status('deleted');
        }
    }

    if ($title === '') {
        $errors[] = 'Title is required.';
    } elseif (mb_strlen($title) > 120) {
        $errors[] = 'Title must be 120 characters or less.';
    }

    if ($body === '') {
        $errors[] = 'Content cannot be empty.';
    }

    if ($action === 'update' && $noteId === null) {
        $errors[] = 'Missing note identifier.';
    }

    if (empty($errors)) {
        if ($action === 'update' && $noteId !== null) {
            $repository->update($noteId, $title, $body);
            redirect_with_status('updated');
        }

        $repository->create($title, $body);
        redirect_with_status('created');
    }

    $formMode = $action === 'update' ? 'update' : 'create';
    $formTitle = $title;
    $formBody = $body;
    $editId = $noteId;
}

if (isset($_GET['status'])) {
    $messages = [
        'created' => 'Note saved successfully.',
        'updated' => 'Note updated successfully.',
        'deleted' => 'Note removed successfully.',
    ];
    $flash = $messages[$_GET['status']] ?? null;
}

if (isset($_GET['edit']) && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $editCandidate = (int) $_GET['edit'];
    if ($editCandidate > 0) {
        $note = $repository->find($editCandidate);
        if ($note) {
            $formMode = 'update';
            $formTitle = $note['title'];
            $formBody = $note['body'];
            $editId = $note['id'];
        } else {
            $errors[] = 'Note not found or already removed.';
        }
    }
}

$notes = $repository->all();
$noteCount = count($notes);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Skyward Notes</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="page">
        <header class="hero">
            <div>
                <p class="eyebrow">Skyward Notes</p>
                <h1>Capture ideas, ship faster.</h1>
                <p class="lead">A lightweight PHP + MySQL notebook you can deploy anywhere.</p>
            </div>
            <div class="hero-count">
                <span><?= sanitize((string) $noteCount); ?></span>
                <p>notes saved</p>
            </div>
        </header>

        <?php if ($flash): ?>
            <div class="notice success"><?= sanitize($flash); ?></div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="notice error">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= sanitize($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <section class="card" id="note-form">
            <div class="card-head">
                <h2><?= $formMode === 'create' ? 'Add a fresh note' : 'Edit note'; ?></h2>
                <?php if ($formMode === 'update'): ?>
                    <a class="link" href="index.php">cancel edit</a>
                <?php endif; ?>
            </div>
            <form method="post" class="note-form">
                <input type="hidden" name="action" value="<?= $formMode === 'create' ? 'create' : 'update'; ?>">
                <?php if ($formMode === 'update' && $editId !== null): ?>
                    <input type="hidden" name="note_id" value="<?= sanitize((string) $editId); ?>">
                <?php endif; ?>
                <label>
                    Title
                    <input type="text" name="title" maxlength="120" placeholder="Meeting recap" value="<?= sanitize($formTitle); ?>" required>
                </label>
                <label>
                    Details
                    <textarea name="body" rows="5" placeholder="Decisions, action items, snippets..." required><?= sanitize($formBody); ?></textarea>
                </label>
                <button type="submit" class="btn primary">
                    <?= $formMode === 'create' ? 'Save note' : 'Save changes'; ?>
                </button>
            </form>
        </section>

        <section class="notes-section">
            <div class="section-head">
                <h2>Your notes</h2>
            </div>
            <?php if (empty($notes)): ?>
                <div class="empty">
                    <p>No notes yet. Start by jotting down something above.</p>
                </div>
            <?php else: ?>
                <div class="notes-grid">
                    <?php foreach ($notes as $note): ?>
                        <article class="note-card">
                            <div class="note-meta">Updated <?= sanitize(date('M j, Y \a\t H:i', strtotime($note['updated_at']))); ?></div>
                            <h3><?= sanitize($note['title']); ?></h3>
                            <p><?= nl2br(sanitize($note['body'])); ?></p>
                            <div class="note-actions">
                                <a class="btn ghost" href="?edit=<?= sanitize((string) $note['id']); ?>#note-form">Edit</a>
                                <form method="post">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="note_id" value="<?= sanitize((string) $note['id']); ?>">
                                    <button type="submit" class="btn danger">Delete</button>
                                </form>
                            </div>
                            <p class="note-warning" role="note">Deleting removes this note permanently.</p>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
</body>
</html>
