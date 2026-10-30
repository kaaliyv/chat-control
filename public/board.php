<?php
require __DIR__ . '/../src/lib.php';

$me = $_GET['s'] ?? '';
$rows = db()->query('SELECT token, name, score FROM sessions
                     WHERE name IS NOT NULL ORDER BY score DESC, finished_at ASC LIMIT 50')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Chat Control - Score Table</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Silkscreen&family=VT323&display=swap">
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="app">
    <header class="topbar">
        <?= emblem() ?>
        <div class="agency">Directorate for<br>Chat Oversight</div>
    </header>
    <section class="results">
        <h2>Officers of the month</h2>
        <div class="paper">
            <?php if (!$rows): ?>
                <p>No officers on record yet.</p>
            <?php else: ?>
                <table class="board">
                    <?php foreach ($rows as $i => $row): ?>
                        <tr class="<?= $row['token'] === $me ? 'me' : '' ?>">
                            <td><?= $i + 1 ?>.</td>
                            <td><?= e($row['name']) ?></td>
                            <td class="num"><?= (int) $row['score'] ?></td>
                        </tr>
                    <?php endforeach ?>
                </table>
            <?php endif ?>
        </div>
        <a class="btn btn-stamp" href="index.php">Start new shift</a>
    </section>
</div>
</body>
</html>
