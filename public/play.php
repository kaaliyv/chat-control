<?php
require __DIR__ . '/../src/lib.php';

$session = find_session($_GET['s'] ?? '');
if (!$session) {
    header('Location: index.php');
    exit;
}
$showRules = (int) $session['current'] === 0 && $session['served_at'] === null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Chat Control</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Silkscreen&family=VT323&display=swap">
    <link rel="stylesheet" href="style.css">
</head>
<body data-token="<?= e($session['token']) ?>" data-rules="<?= $showRules ? '1' : '0' ?>">
<div class="app">
    <header class="topbar">
        <?= emblem() ?>
        <div class="agency">Directorate for<br>Chat Oversight</div>
        <div class="timer" id="timer">--</div>
    </header>

    <section class="game" id="game">
        <div class="case">
            <span id="case-no">CASE --/--</span>
            <span id="case-id"></span>
        </div>
        <div class="chat" id="chat"></div>
        <div class="actions">
            <button class="btn btn-clear" data-verdict="clear" disabled>Clear</button>
            <button class="btn btn-flag" data-verdict="flag" disabled>Flag</button>
        </div>
    </section>

    <section class="results" id="results" hidden></section>
</div>

<div class="overlay" id="rules" hidden>
    <div class="plaque">
        <?= emblem() ?>
        <h2>Officer, your shift begins</h2>
        <p>The algorithm has intercepted private conversations. A human must decide.</p>
        <ul>
            <li><b class="red">FLAG</b> the chat if the people are criminals.</li>
            <li><b class="green">CLEAR</b> it if it is harmless talk.</li>
            <li>You have <b><?= TIME_LIMIT ?> seconds</b> per chat. Messages keep arriving, but you may decide early. Faster correct decisions earn more points.</li>
            <li>Wrongful flags ruin innocent lives. Missed criminals ruin yours.</li>
            <li>There are <?= CHATS_PER_GAME ?> chats in your shift. The police will report on your work at the end.</li>
        </ul>
        <button class="btn btn-stamp" id="start">Begin shift</button>
    </div>
</div>

<div class="overlay" id="stamp" hidden><div class="stamp"></div></div>

<script src="game.js"></script>
</body>
</html>
