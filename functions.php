<?php

// https://discord.com/developers/docs/interactions/receiving-and-responding#interaction-object-interaction-type
const PING = 1;
const APPLICATION_COMMAND = 2;
const APPLICATION_COMMAND_AUTOCOMPLETE = 4;

// https://discord.com/developers/docs/interactions/receiving-and-responding#interaction-response-object-interaction-callback-type
const CHANNEL_MESSAGE_WITH_SOURCE = 4;

$db = new SQLite3(__DIR__ . "/database.sqlite", SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
$db->exec("CREATE TABLE IF NOT EXISTS key_value ( key TEXT PRIMARY KEY NOT NULL UNIQUE, value BLOB NOT NULL ) WITHOUT ROWID");

enum AbortReason: int {
    case NotVerified = 401;
    case MethodNotAllowed = 405;
}

enum ResponseType: int {
    case Pong = 1;
}

function respond(array $data): never {
    http_response_code(200); // OK
    header("Content-Type: application/json");
    echo json_encode($data) . "\n";
    exit;
}

function abort(AbortReason $reason): never {
    http_response_code($reason->value);
    header("Content-Type: text/plain");
    echo $reason->name . "\n";
    exit;
}

function get_record(string $key, mixed $default): mixed {
    global $db;
    $stmt = $db->prepare("SELECT value FROM key_value WHERE key = ?");
    $stmt->bindValue(1, $key);
    $result = $stmt->execute();
    $value = $result->fetchArray(SQLITE3_ASSOC);
    if ($value === false) return $default;
    $value = $value["value"];
    $value = unserialize($value);
    return $value;
}

function set_record(string $key, mixed $value): bool {
    global $db;
    $value = serialize($value);
    $stmt = $db->prepare("INSERT OR REPLACE INTO key_value (key, value) VALUES (?, ?)");
    $stmt->bindValue(1, $key);
    $stmt->bindValue(2, $value);
    $success = $stmt->execute();
    return $success;
}

function j_log(string $line): void {
    global $db;
    $stmt = $db->prepare("INSERT INTO log (date, line) VALUES (?, ?)");
    $stmt->bindValue(1, date("r"));
    $stmt->bindValue(2, $line);
    $stmt->execute();
}

function request_is_ping(object $request): bool {
    return $request->type == PING;
}

function request_is_command(object $request): bool {
    return $request->type == APPLICATION_COMMAND;
}

function request_is_autocomplete(object $request): bool {
    return $request->type == APPLICATION_COMMAND_AUTOCOMPLETE;
}

function respond_to_interaction(object $request, array|object $data): never {
/*
    POST /api/v10/interactions/$interaction_id/$interaction_token/callback
    Content-Type: application/json
    User-Agent: Schedule Bot (http://github.com/jkoop/schedule-bot, 0.0.0-dev)
    Authorization: Bot $bot_token
*/

$interaction_id = $request->id;
$interaction_token = $request->token;
$bot_token = getenv("DISCORD_TOKEN") ?? "";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://discord.com/api/v10/interactions/" . $interaction_id . "/" . $interaction_token . "/callback");
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bot " . $bot_token,
    "Content-Type: application/json",
    "User-Agent: Schedule Bot (http://github.com/jkoop/schedule-bot, 0.0.0-dev)", // @todo: make this dynamic
]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_exec($ch);
curl_close($ch);
exit;
}
