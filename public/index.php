<?php
// QR code target: every visit starts a fresh shift.
require __DIR__ . '/../src/lib.php';

$ids = array_map('strval', array_keys(chats()));
shuffle($ids);
$token = bin2hex(random_bytes(12));

db()->prepare('INSERT INTO sessions (token, chat_ids, created_at) VALUES (?, ?, ?)')
    ->execute([$token, json_encode(array_slice($ids, 0, CHATS_PER_GAME)), time()]);

header('Location: play.php?s=' . $token);
