<?php

use Carbon\Carbon;
use Symfony\Component\Dotenv\Dotenv;
use TelegramBot\Api\BotApi;

require_once 'vendor/autoload.php';
require_once 'database.php';

if (file_exists('.env')) {
    $env = new Dotenv();
    $env->loadEnv('.env');;
}

date_default_timezone_set('Asia/Kolkata');

$data = json_decode(file_get_contents('php://input'));
$message = substr($data->message->text, 1);
$from = $data->message->from->id;
$name = $data->message->from->first_name;
$username = $data->message->from->username ?? "NA";

$bot = new BotApi($_ENV["BOT_TOKEN"]);
$database = new database();
$conn = $database->getConnection();

if ($data->my_chat_member->new_chat_member->status === "kicked") {
    $block_user = $conn->prepare(
        "UPDATE subscriptions SET blocked = 1 WHERE telegram_id = ?"
    );
    $block_user->execute([$data->my_chat_member->chat->id]);
    die("Blocked");
}


function starts_with($string, $startString): bool
{
    $len = strlen($startString);
    return (substr($string, 0, $len) === $startString);
}

try {
    if (starts_with($message, "start")) {
        $res = json_decode(
            file_get_contents(__DIR__ . '/data/states.json')
        );
        $states = $res->states;
        $message = "";
        foreach ($states as $idx => $state) {
            $message .= "{$state->state_name} - /state_{$state->state_id}\n\n";
        }
        $message .= "Click the link besides your state";
        $bot->sendMessage($from, $message);
    } elseif (starts_with($message, "state_")) {
        $state_id = explode("_", $message)[1];
        $local_file_path = __DIR__ . "/data/$state_id-districts.json";
        if (file_exists($local_file_path)) {
            $res = json_decode(
                file_get_contents($local_file_path)
            );
        } else {
            $res = json_decode(
                file_get_contents(
                    "https://cdn-api.co-vin.in/api/v2/admin/location/districts/$state_id",
                )
            );
            if(!empty($res)) {
                file_put_contents($local_file_path, json_encode($res));
            }
        }
        $districts = $res->districts;
        $message = "";
        foreach ($districts as $idx => $district) {
            $message .= "{$district->district_name} - /district_{$district->district_id}\n\n";
        }
        $message .= "Click the link besides your district";
        $bot->sendMessage($from, $message);
    } elseif (starts_with($message, "district_")) {
        $district_id = explode("_", $message)[1];
        $set_data = $conn->prepare(
            "INSERT INTO subscriptions (district_id, telegram_id, first_name, username)
                VALUES (?,?,?,?) ON DUPLICATE KEY
                UPDATE district_id = VALUES(district_id),
                       first_name = VALUES(first_name),
                       username = VALUES(username),
                       blocked = 0"
        );
        if ($set_data->execute([$district_id, $from, $name, $username])) {
            $bot->sendMessage($from, "You have successfully subscribed to alerts for your district. " .
                "\n\nIf you would like to STOP getting alerts, send /unsubscribe.");
        } else {
            $bot->sendMessage($from, "An unexpected error occurred 😔");
        }
    } elseif (starts_with($message, "unsubscribe")) {
        $delete_data = $conn->prepare("DELETE FROM subscriptions WHERE telegram_id = ?");
        $delete_data->execute([$from]);
        $bot->sendMessage($from, "You have successfully unsubscribed.");
    } else {
        $bot->sendMessage($from, "Sorry, I don't understand what you mean.");
    }
} catch (Exception $exception) {
    file_put_contents(
        "error.log",
        Carbon::now() . " => " . $exception->getMessage() . PHP_EOL,
        FILE_APPEND
    );
}
