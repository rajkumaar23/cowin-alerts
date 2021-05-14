<?php

use Carbon\Carbon;
use Symfony\Component\Dotenv\Dotenv;
use TelegramBot\Api\BotApi;

require_once 'vendor/autoload.php';
require_once 'database.php';

if (file_exists('.env')) {
    $env = new Dotenv();
    $env->loadEnv('.env');
}

$bot = new BotApi($_ENV["BOT_TOKEN"]);
$db = new database();
$conn = $db->getConnection();

file_put_contents(
    "output.log",
    Carbon::now() . " => Starting the cron" . PHP_EOL, FILE_APPEND
);
$result = $conn->query("SELECT * FROM subscriptions");
$result = $result->fetchAll();
$unique_districts = array_unique(array_column($result, "district_id"));
foreach ($unique_districts as $district) {
    $appointments = file_get_contents(
        "https://cdn-api.co-vin.in/api/v2/appointment/sessions/calendarByDistrict?district_id=$district&date="
        . date("d-m-Y")
    );
    if (empty($appointments)) {
        continue;
    }
    $centers = json_decode($appointments)->centers;
    $response_body = "";
    foreach ($centers as $center) {
        $center_name_added = false;
        foreach ($center->sessions as $session) {
            if ($session->available_capacity > 0 && $session->min_age_limit <= 25) {
                if (!$center_name_added) {
                    $response_body .= "\n<b>{$center->name}</b> (<code>$center->fee_type</code>)\n";
                    $center_name_added = true;
                }
                $response_body .= "<code>{$session->available_capacity}</code> slots available on ";
                $response_body .= "<code>{$session->date}</code> for <code>{$session->min_age_limit}+</code>\n";
            }
        }
    }
    if (empty($response_body)) {
        continue;
    }
    $users = array_column(
        (array)array_filter($result, static function ($item) use ($district) {
            return $item["district_id"] === $district;
        }),
        "telegram_id"
    );
    foreach ($users as $user) {
        try {
            $bot->sendMessage($user, $response_body, "HTML");
        } catch (Exception $exception) {
            file_put_contents(
                "error.log",
                Carbon::now() . " => " . $exception->getMessage() . PHP_EOL, FILE_APPEND
            );
        }
    }
}
file_put_contents(
    "output.log",
    Carbon::now() . " => Ending the cron" . PHP_EOL, FILE_APPEND
);
