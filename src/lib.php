<?php

const CHATS_PER_GAME = 10;
const TIME_LIMIT = 45; // seconds per chat

function db(): PDO
{
    static $pdo;
    if ($pdo) {
        return $pdo;
    }

    $dir = __DIR__ . '/../data';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $pdo = new PDO('sqlite:' . $dir . '/game.sqlite');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('CREATE TABLE IF NOT EXISTS sessions (
        token       TEXT PRIMARY KEY,
        chat_ids    TEXT NOT NULL,
        current     INTEGER NOT NULL DEFAULT 0,
        served_at   REAL,
        score       INTEGER NOT NULL DEFAULT 0,
        name        TEXT,
        created_at  INTEGER NOT NULL,
        finished_at INTEGER
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS answers (
        token   TEXT NOT NULL,
        idx     INTEGER NOT NULL,
        chat_id TEXT NOT NULL,
        verdict TEXT NOT NULL,
        correct INTEGER NOT NULL,
        points  INTEGER NOT NULL,
        seconds REAL NOT NULL,
        PRIMARY KEY (token, idx)
    )');

    return $pdo;
}

function chats(): array
{
    static $chats;
    return $chats ??= require __DIR__ . '/chats.php';
}

function find_session(string $token): ?array
{
    $st = db()->prepare('SELECT * FROM sessions WHERE token = ?');
    $st->execute([$token]);
    return $st->fetch() ?: null;
}

function results(array $session): array
{
    $st = db()->prepare('SELECT * FROM answers WHERE token = ? ORDER BY idx');
    $st->execute([$session['token']]);

    $chats = chats();
    $reports = [];
    $tally = ['correct' => 0, 'wrongful' => 0, 'missed' => 0, 'timeout' => 0];

    foreach ($st as $a) {
        $chat = $chats[$a['chat_id']];
        $outcome = match (true) {
            $a['verdict'] === 'timeout' => 'timeout',
            (bool) $a['correct'] => 'correct',
            $a['verdict'] === 'flag' => 'wrongful',
            default => 'missed',
        };
        $tally[$outcome]++;
        $reports[] = [
            'id' => $a['chat_id'],
            'people' => $chat['people'],
            'criminal' => $chat['criminal'],
            'outcome' => $outcome,
            'report' => $chat['report'],
            'points' => (int) $a['points'],
        ];
    }

    $better = db()->prepare('SELECT COUNT(*) FROM sessions WHERE finished_at IS NOT NULL AND score > ?');
    $better->execute([$session['score']]);
    $total = db()->query('SELECT COUNT(*) FROM sessions WHERE finished_at IS NOT NULL')->fetchColumn();

    [$grade, $text] = evaluation($tally, count($reports));

    return [
        'score' => (int) $session['score'],
        'name' => $session['name'],
        'rank' => (int) $better->fetchColumn() + 1,
        'players' => (int) $total,
        'tally' => $tally,
        'grade' => $grade,
        'evaluation' => $text,
        'reports' => $reports,
    ];
}

function evaluation(array $t, int $n): array
{
    if ($t['correct'] === $n) {
        return ['EXEMPLARY', 'A flawless shift. The Directorate has noted your name for promotion. Your family will receive an additional ration of Wi-Fi.'];
    }
    if ($t['timeout'] >= 3) {
        return ['ABSENT', 'You let the clock run out again and again. The algorithm is beginning to wonder why it needs you at all.'];
    }
    if ($t['wrongful'] >= 3 && $t['wrongful'] > $t['missed']) {
        return ['OVERZEALOUS', 'Your vigilance is appreciated, but the legal department is drowning in complaints from innocent citizens. Several doors will need to be replaced.'];
    }
    if ($t['missed'] >= 3 && $t['missed'] > $t['wrongful']) {
        return ['NEGLIGENT', 'Multiple criminal networks walked past your desk unchallenged. Internal Affairs would like a word about your sympathies.'];
    }
    if ($t['correct'] >= $n * 0.7) {
        return ['SATISFACTORY', 'Adequate work, officer. As a reward, your next shift has been extended by one hour.'];
    }
    return ['UNSATISFACTORY', 'Your performance has been forwarded to your supervisor. As a precaution, your own chats are now being monitored.'];
}

function e(?string $s): string
{
    return htmlspecialchars($s ?? '', ENT_QUOTES);
}

function emblem(): string
{
    $stars = '';
    for ($i = 0; $i < 12; $i++) {
        $a = deg2rad($i * 30);
        $x = round(50 + 30 * sin($a), 2);
        $y = round(50 - 30 * cos($a), 2);
        $stars .= "<use href=\"#star\" x=\"$x\" y=\"$y\"/>";
    }
    return '<svg class="emblem" viewBox="0 0 100 100" aria-hidden="true">'
        . '<defs><polygon id="star" points="0,-6 1.4,-1.9 5.7,-1.9 2.3,0.7 3.5,4.9 0,2.4 -3.5,4.9 -2.3,0.7 -5.7,-1.9 -1.4,-1.9"/></defs>'
        . '<rect width="100" height="100" fill="#1d3473"/>'
        . '<g fill="#f2c94c">' . $stars . '</g>'
        . '</svg>';
}
