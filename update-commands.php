<?php

include_once "functions.php";

// add/update commands
$app_id = getenv("DISCORD_APP_ID");
api_post("/applications/" . $app_id . "/commands", [
    "name" => "caldav",
    "type" => 1, // slash command
    "description" => "Returns instructions to import the internal calendar in Google Calendar.",
    "options" => [],
]);
api_post("/applications/" . $app_id . "/commands", [
    "name" => "dump",
    "type" => 1, // slash command
    "description" => "Returns all of the config and data and some calculated data.",
    "options" => [],
]);
api_post("/applications/" . $app_id . "/commands", [
    "name" => "postpone",
    "type" => 1, // slash command
    "description" => "Postpone the next event",
    "options" => [
        [
            "name" => "qty",
            "description" => "Quantity of time to delay the next event. Negative values reverse the delay.",
            "type" => 4, // INTEGER
            "required" => true,
        ],
        [
            "name" => "unit",
            "description" => "Unit of time to delay the next event.",
            "type" => 3, // STRING
            "required" => true,
            "choices" => [
                [ "name" => "Minute", "value" => "minute" ],
                [ "name" => "Hour", "value" => "hour" ],
                [ "name" => "Day", "value" => "day" ],
                [ "name" => "Week", "value" => "week" ],
            ],
        ],
    ],
]);
