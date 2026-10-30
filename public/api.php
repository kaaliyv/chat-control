<?php
require __DIR__ . '/../src/lib.php';

header('Content-Type: application/json');

function out(array $data, int $code = 200): never
{
    http_response_code($code);
    echo json_encode($data);
    exit;
}

$session = find_session($_GET['s'] ?? '');
if (!$session) {
    out(['error' => 'Unknown session'], 404);
}

$action = $_GET['action'] ?? '';
$ids = json_decode($session['chat_ids'], true);
$idx = (int) $session['current'];
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';

if ($action === 'state') {
    if ($idx >= count($ids)) {
        out(['finished' => true] + results($session));
    }

    // The clock starts the first time a chat is served and survives reloads.
    if ($session['served_at'] === null) {
        $session['served_at'] = microtime(true);
        db()->prepare('UPDATE sessions SET served_at = ? WHERE token = ?')
            ->execute([$session['served_at'], $session['token']]);
    }

    $chat = chats()[$ids[$idx]];
    out([
        'finished' => false,
        'index' => $idx,
        'total' => count($ids),
        'remaining' => max(0, TIME_LIMIT - (microtime(true) - (float) $session['served_at'])),
        'chat' => [
            'id' => $ids[$idx],
            'people' => $chat['people'],
            'messages' => $chat['messages'],
        ],
    ]);
}

if ($action === 'decide' && $isPost) {
    $verdict = $_POST['verdict'] ?? '';
    if (!in_array($verdict, ['flag', 'clear', 'timeout'], true)) {
        out(['error' => 'Bad verdict'], 400);
    }
    if ((int) ($_POST['idx'] ?? -1) !== $idx || $idx >= count($ids) || $session['served_at'] === null) {
        out(['error' => 'Out of order'], 409);
    }

    $elapsed = microtime(true) - (float) $session['served_at'];
    if ($elapsed > TIME_LIMIT + 2) {
        $verdict = 'timeout';
    }

    $chat = chats()[$ids[$idx]];
    $correct = $verdict === ($chat['criminal'] ? 'flag' : 'clear');
    $points = $correct ? 100 + (int) round(max(0, TIME_LIMIT - $elapsed) * 5) : 0;
    $finished = $idx + 1 >= count($ids);

    $pdo = db();
    $pdo->beginTransaction();
    $st = $pdo->prepare('UPDATE sessions SET current = current + 1, served_at = NULL, score = score + ?, finished_at = ?
                         WHERE token = ? AND current = ?');
    $st->execute([$points, $finished ? time() : null, $session['token'], $idx]);
    if ($st->rowCount() === 0) {
        $pdo->rollBack();
        out(['error' => 'Already decided'], 409);
    }
    $pdo->prepare('INSERT INTO answers (token, idx, chat_id, verdict, correct, points, seconds) VALUES (?, ?, ?, ?, ?, ?, ?)')
        ->execute([$session['token'], $idx, $ids[$idx], $verdict, (int) $correct, $points, $elapsed]);
    $pdo->commit();

    out(['ok' => true, 'finished' => $finished]);
}

if ($action === 'name' && $isPost) {
    $name = trim($_POST['name'] ?? '');
    if ($session['finished_at'] === null) {
        out(['error' => 'Shift not finished'], 409);
    }
    if (!preg_match('/^[\p{L}\p{N} _.\-]{1,16}$/u', $name)) {
        out(['error' => 'Use 1-16 letters, digits, spaces, _ . -'], 400);
    }
    db()->prepare('UPDATE sessions SET name = ? WHERE token = ? AND name IS NULL')
        ->execute([$name, $session['token']]);
    out(['ok' => true]);
}

out(['error' => 'Unknown action'], 400);
