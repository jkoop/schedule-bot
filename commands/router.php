<?php

include_once "../functions.php";

function command_router(string $command, array $args): string {
    if ($command == "dump") {
        include_once "../commands/dump.php";
        return command_dump($args);
    } else if ($command == "caldav") {
        include_once "../commands/caldav.php";
        return command_caldav($args);
    } else if ($command == "postpone") {
        include_once "../commands/postpone.php";
        return command_postpone($args);
    } else {
        throw new Exception("Unknown command: " . $command);
    }
}
