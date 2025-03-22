<?php

include_once "../functions.php";

use Symfony\Component\Yaml\Yaml;

function command_dump(array $args): string {
    global $db;
    $result = $db->query('SELECT "key", "value" FROM "key_value" ORDER BY "key"');
    $data = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $data[$row["key"]] = json_decode($row["value"]);
    }
    return "```yaml\n" . Yaml::dump($data) . "\n```";
}
