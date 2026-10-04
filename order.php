<?php
// Приём заявки с сайта и отправка её Марине в Telegram через бота.
// Токен и chat_id лежат в tg-config.php — он создаётся при деплое из
// GitHub Secrets (TG_BOT_TOKEN, TG_CHAT_ID) и в git не хранится.

header('Content-Type: application/json; charset=utf-8');

function reply($ok, $code = 200) {
    http_response_code($code);
    echo json_encode(['ok' => $ok]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') reply(false, 405);

$cfg = __DIR__ . '/tg-config.php';
if (!is_file($cfg)) reply(false, 500);
require $cfg; // задаёт $TG_BOT_TOKEN и $TG_CHAT_ID

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) reply(false, 400);

// Ловушка для спам-ботов: скрытое поле, человек его не заполняет
if (!empty($data['website'])) reply(true);

$f = function ($key, $max = 300) use ($data) {
    $v = isset($data[$key]) ? trim((string)$data[$key]) : '';
    return mb_substr($v, 0, $max);
};

$name = $f('name', 100);
$contact = $f('contact', 150);
if ($name === '' || $contact === '') reply(false, 400);

$text = "🧀 Новая заявка с сайта\n\n"
    . "Имя: $name\n"
    . "Контакт: $contact\n"
    . "Интересует: " . $f('type', 100);
if ($f('date') !== '') $text .= "\nДата: " . $f('date', 30);
if ($f('message') !== '') $text .= "\nКомментарий: " . $f('message', 2000);

$ch = curl_init("https://api.telegram.org/bot{$TG_BOT_TOKEN}/sendMessage");
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => ['chat_id' => $TG_CHAT_ID, 'text' => $text],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
]);
$res = json_decode((string)curl_exec($ch), true);
curl_close($ch);

reply(!empty($res['ok']), !empty($res['ok']) ? 200 : 502);
