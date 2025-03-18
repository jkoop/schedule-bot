<?php

include_once "../functions.php";

use Discord\Interaction;
use Discord\InteractionResponseType;

if ($_SERVER["REQUEST_METHOD"] != "POST") abort(AbortReason::MethodNotAllowed);

$public_key = hex2bin(getenv("DISCORD_PUBLIC_KEY") ?? "");
$signature = hex2bin($_SERVER['HTTP_X_SIGNATURE_ED25519'] ?? "");
$timestamp = $_SERVER['HTTP_X_SIGNATURE_TIMESTAMP'] ?? "";
$post_body = file_get_contents('php://input');

if (
    strlen($signature) != SODIUM_CRYPTO_SIGN_BYTES ||
    sodium_crypto_sign_verify_detached($signature, $timestamp . $post_body, $public_key) != true
) abort(AbortReason::NotVerified);

$request = json_decode($post_body);
j_log(json_encode($request));

if (request_is_ping($request)) {
    respond([ "type" => ResponseType::Pong ]);
} else if (request_is_command($request)) {
    $command = $request->data->name;
    $args = $request->data->options;
    // https://discord.com/developers/docs/interactions/application-commands#application-command-object-application-command-types
    $type = $request->data->type;

    if ($command == "blep") {
        respond_to_interaction($request, [
            "type" => CHANNEL_MESSAGE_WITH_SOURCE,
            "data" => [
                "content" => "OK",
            ],
        ]);
    }
}
