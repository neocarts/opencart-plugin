<?php
namespace Neocarts;

/**
 * The plugin's tables and their migrations. Every statement is idempotent; an adapter runs
 * migrate() on install and whenever its admin page opens, storing VERSION in `schema_version`.
 */
final class Schema
{
    const VERSION = 1;

    /** @return string[] SQL to bring a database from $from to VERSION */
    public static function migrate(string $prefix, int $from): array
    {
        $sql = [];
        if ($from < 1) {
            $sql[] = "CREATE TABLE IF NOT EXISTS `{$prefix}neocarts_order` (
                `neocarts_order_id` VARCHAR(64) NOT NULL, `oc_order_id` INT NOT NULL, `number` VARCHAR(32) NOT NULL DEFAULT '',
                `source` VARCHAR(16) NOT NULL DEFAULT '', `subscription_ids` TEXT, `created_at` DATETIME NOT NULL,
                PRIMARY KEY (`neocarts_order_id`), KEY `oc_order_id` (`oc_order_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            $sql[] = "CREATE TABLE IF NOT EXISTS `{$prefix}neocarts_webhook` (
                `delivery_id` VARCHAR(64) NOT NULL, `event` VARCHAR(64) NOT NULL, `status` VARCHAR(16) NOT NULL,
                `error` TEXT, `body` MEDIUMTEXT, `received_at` DATETIME NOT NULL,
                PRIMARY KEY (`delivery_id`), KEY `received_at` (`received_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            // subscription is on for everything; a row switches it off — `42` the whole product,
            // `42-17` every variant with option value 17 (Items::disabled())
            $sql[] = "CREATE TABLE IF NOT EXISTS `{$prefix}neocarts_product` (
                `external_id` VARCHAR(64) NOT NULL, `product_id` INT NOT NULL, `updated_at` DATETIME NOT NULL,
                PRIMARY KEY (`external_id`), KEY `product_id` (`product_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        }

        return $sql;
    }

    /** @return string[] */
    public static function drop(string $prefix): array
    {
        return array_map(function ($t) use ($prefix) {
            return "DROP TABLE IF EXISTS `{$prefix}{$t}`";
        }, ['neocarts_order', 'neocarts_webhook', 'neocarts_product']);
    }
}
