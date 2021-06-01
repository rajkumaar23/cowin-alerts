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

echo Carbon::now() . " => Starting the cron\n";
$result = $conn->query("SELECT * FROM subscriptions WHERE blocked = 0");
$result = $result->fetchAll();
$unique_districts = array_unique(array_column($result, "district_id"));
echo count($unique_districts) . " unique districts to process\n";
foreach ($unique_districts as $district) {
    echo "Processing $district\n";
    $appointments = file_get_contents(
        "https://cdn-api.co-vin.in/api/v2/appointment/sessions/calendarByDistrict?district_id=$district&date="
        . date("d-m-Y")
    );
    sleep(5);
    if (empty($appointments)) {
        echo "No response from API for $district\n";
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

                if (!empty($session->available_capacity_dose1)) {
                    $response_body .= "- Dose 1 : <code>{$session->available_capacity_dose1}</code> slots\n";
                }
                if (!empty($session->available_capacity_dose2)) {
                    $response_body .= "- Dose 2 : <code>{$session->available_capacity_dose2}</code> slots\n";
                }
                $response_body .= "- Vaccine : <code>{$session->vaccine}</code>\n";
                $response_body .= "- Date : <code>{$session->date}</code> \n";
                $response_body .= "- Age limit : <code>{$session->min_age_limit}+</code>\n";
                $response_body .= "\n";
            }
        }
    }
    if (empty($response_body)) {
        echo "No centers found for $district\n";
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
            echo "Sending alert to $user\n";
            $bot->sendMessage($user, $response_body, "HTML");
        } catch (Exception $exception) {
            if ($exception->getCode() == 403) {
                $block_user = $conn->prepare(
                    "UPDATE subscriptions SET blocked = 1 WHERE telegram_id = ?"
                );
                $block_user->execute([$user]);
            }
            file_put_contents(
                "error.log",
                Carbon::now() . " => " . $exception->getMessage() . PHP_EOL, FILE_APPEND
            );
        }
    }
}
echo Carbon::now() . " => Ending the cron\n";
