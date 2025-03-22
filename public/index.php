<?php

include_once "../functions.php";

use Discord\Interaction;
use Discord\InteractionResponseType;
use Symfony\Component\Yaml\Yaml;

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
    if ($request->data->type != 1) {
        respond_to_slash_command($request, "E: unknown interaction type: " . $request->data->type);
    }

    try {
        $args = [];
        foreach ($request->data->options as $option) {
            $args[$option->name] = $option->value;
        }

        include_once "../commands/router.php";
        respond_to_slash_command($request, command_router($request->data->name, $args));
    } catch (Throwable $e) {
        $class_name = get_class($e);
        $message = $e->getMessage();
        $stack_trace = $e->getTraceAsString();
        respond_to_slash_command(
            $request,
            "Uncaught " . $class_name . ": **" . $message . "**  \n" .
            "```plain\n" .
            "thrown in " . $e->getFile() . ":" . $e->getLine() . "\n" .
            $stack_trace . "\n" .
            "```");
    }
}
