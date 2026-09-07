-- Migration: create the bin status view used by the dashboard and reports

CREATE OR REPLACE VIEW `v_bin_status` AS
SELECT
    tb.`bin_id`,
    tb.`bin_name`,
    tb.`bin_code`,
    tb.`location`,
    tb.`bin_type`,
    tb.`status`,
    COALESCE(tb.`fill_level`, bs.`fill_level`, 0) AS `fill_level`,
    COALESCE(tb.`weight_kg`, bs.`weight_kg`, 0) AS `weight_kg`,
    CASE WHEN COALESCE(tb.`fill_level`, bs.`fill_level`, 0) >= 100 THEN 1 ELSE COALESCE(bs.`is_full`, 0) END AS `is_full`,
    0 AS `temperature`,
    0 AS `humidity`,
    COALESCE(tb.`updated_at`, bs.`last_updated`, tb.`created_at`) AS `last_updated`
FROM `trash_bins` tb
LEFT JOIN `bin_status` bs
    ON bs.`bin_type` COLLATE utf8mb4_unicode_ci = tb.`bin_type` COLLATE utf8mb4_unicode_ci;
