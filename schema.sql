CREATE TABLE IF NOT EXISTS `subscriptions` (
    `telegram_id` int PRIMARY KEY,
    `district_id` int NOT NULL,
    `first_name` varchar(255),
    `username` varchar(255)
)
