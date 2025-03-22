<?php

include_once "vendor/autoload.php";

// https://discord.com/developers/docs/interactions/receiving-and-responding#interaction-object-interaction-type
const PING = 1;
const APPLICATION_COMMAND = 2;
const APPLICATION_COMMAND_AUTOCOMPLETE = 4;

// https://discord.com/developers/docs/interactions/receiving-and-responding#interaction-response-object-interaction-callback-type
const CHANNEL_MESSAGE_WITH_SOURCE = 4;

$db = new SQLite3(__DIR__ . "/database.sqlite", SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
$db->busyTimeout(5000); // 5 seconds
$db->exec("CREATE TABLE IF NOT EXISTS key_value ( key TEXT PRIMARY KEY NOT NULL UNIQUE, value TEXT NOT NULL ) WITHOUT ROWID");

date_default_timezone_set(get_record("timezone", "America/Winnipeg"));
$now = time();

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

function get_record(string $key, mixed $default = null): mixed {
    global $db;
    $stmt = $db->prepare("SELECT value FROM key_value WHERE key = ?");
    $stmt->bindValue(1, $key);
    $result = $stmt->execute();
    $value = $result->fetchArray(SQLITE3_ASSOC);
    if ($value === false) {
        set_record($key, $default);
        return $default;
    }
    $value = $value["value"];
    $value = json_decode($value);
    return $value;
}

function set_record(string $key, mixed $value): bool {
    global $db;
    $value = json_encode($value);
    $stmt = $db->prepare("INSERT OR REPLACE INTO key_value (key, value) VALUES (?, ?)");
    $stmt->bindValue(1, $key);
    $stmt->bindValue(2, $value);
    $success = $stmt->execute();
    return $success != false;
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

function respond_to_slash_command(object $request, string $content): never {
    $interaction_id = $request->id;
    $interaction_token = $request->token;
    api_post("/interactions/" . $interaction_id . "/" . $interaction_token . "/callback", [
        "type" => CHANNEL_MESSAGE_WITH_SOURCE,
        "data" => [
            "content" => $content,
        ],
    ]);
    exit;
}

function get_caldav_url(): string {
    $base_url = getenv("BASE_URL");
    $base_url = trim($base_url, "/");
    $caldav_secret = getenv("CALDAV_SECRET");
    $url = $base_url . '/caldav/' . $caldav_secret;
    return $url;
}

function api_post(string $path, array $data): array|object {
    return api("POST", $path, $data);
}

function api_put(string $path, array $data): array|object {
    return api("PUT", $path, $data);
}

function api_patch(string $path, array $data): array|object {
    return api("PATCH", $path, $data);
}

function api_get(string $path): array|object {
    return api("GET", $path, []);
}

function api(string $method, string $path, array $data): array|object {
    $bot_token = getenv("DISCORD_TOKEN") ?? "";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://discord.com/api/v10" . $path);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bot " . $bot_token,
        "Content-Type: application/json",
        "User-Agent: Schedule Bot (http://github.com/jkoop/schedule-bot, 0.0.0-dev)", // @todo: make this dynamic
    ]);
    if ($method != "GET") {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response_body = curl_exec($ch);
    curl_close($ch);
    $response = json_decode($response_body);

    if (is_object($response) && isset($response->retry_after)) {
        sleep($response->retry_after);
        return api($method, $path, $data);
    }

    return $response;
}

function get_next_event(): int {
    global $now;

    $next = strtotime(get_record("next", "2020-01-01"));
    if ($next < $now) {
        $next = calc_next_event();
        set_record("next", date("r", $next));
        advance_snack_rotation();
    }

    return $next;
}

function calc_next_event(): int {
    global $now;

    $str = get_record("repeat_dow") . " " . get_record("repeat_time");
    $next = strtotime($str);
    if ($next < $now) {
        $str = "Next " . $str;
        $next = strtotime($str);
    }

    return $next;
}

function get_snack_name(): string {
    $snack_id = get_record("snack_next");
    return "<@" . $snack_id . ">";
}

function advance_snack_rotation(): void {
    $snack_list = get_record("snack_list");
    $snack_id = get_record("snack_next");
    $index = array_search($snack_id, $snack_list);
    $index += 1;
    $index %= count($snack_list);
    $snack_id = $snack_list[$index];
    set_record("snack_next", $snack_id);
}

function update_discord_event(): void {
    $my_event = get_my_discord_event();
    $app_id = getenv("DISCORD_APP_ID");
    $server_id = getenv("DISCORD_SERVER");
    $snack_name = get_snack_name();
    $start = get_next_event();
    $end = new DateTime();
    $end->setTimestamp($start);
    $end->modify("+1 hour");
    $end = $end->getTimestamp();
    $end = date("c", $end);
    $start = date("c", $start);
    $location = "Ryan's House";
    $description = $snack_name . " handles snack.  \nUse commands to edit this event.";
    $name = "Hang out";

    if ($my_event == null) {
        api_post("/guilds/" . $server_id . "/scheduled-events", [
            "entity_metadata" => [
                "location" => $location,
            ],
            "name" => $name,
            "description" => $description,
            "creator_id" => $app_id,
            "privacy_level" => 2,
            "scheduled_start_time" => $start,
            "scheduled_end_time" => $end,
            "entity_type" => 3,
        ]);
    } else {
        $parameters = [];

        if ($my_event->entity_metadata->location != $location) $parameters["entity_metadata"] = [ "location" => $location ];
        if ($my_event->name != $name) $parameters["name"] = $name;
        if ($my_event->description != $description) $parameters["description"] = $description;
        if ($my_event->scheduled_start_time != $start) $parameters["scheduled_start_time"] = $start;
        if ($my_event->scheduled_end_time != $end) $parameters["scheduled_end_time"] = $end;

        if ($parameters != []) {
            api_patch("/guilds/" . $server_id . "/scheduled-events/" . $my_event->id, $parameters);
        }
    }
}

function get_my_discord_event(): object|null {
    $app_id = getenv("DISCORD_APP_ID");
    $server_id = getenv("DISCORD_SERVER");

    $events = api_get("/guilds/" . $server_id . "/scheduled-events");
    $events = array_filter($events, fn ($event) => $event->creator_id == $app_id);
    $events = array_values($events);
    return $events[0] ?? null;
}
