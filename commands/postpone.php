<?php

include_once "../functions.php";

function command_postpone(array $args): string {
    global $db, $now;
    $qty = $args["qty"];
    $unit = $args["unit"];

    if ($qty == 0) {
        return "E: Quantity must not be zero.";
    }

    $datetime = new DateTime(timezone: new DateTimeZone(get_record("timezone")));
    $datetime->setTimestamp(get_next_event());
    if ($qty > 0) $qty = "+" . $qty;
    $datetime->modify($qty . " " . $unit);
    $next = $datetime->getTimestamp();
    set_record("next", date("r", $next));

    $response = "";

    if ($next < $now) {
        $response .= "W: Attempt to set next event to a date/time in the past.  \n";
    }

    $response .= "Next event now at " . date("D M j 'y g:i a", get_next_event());

    update_discord_event();

    return $response;
}
