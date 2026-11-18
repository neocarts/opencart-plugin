-- The demo shops sell in hryvnias: UAH is the default currency, the others are converted from it.
INSERT INTO oc_currency (title, code, symbol_left, symbol_right, decimal_place, value, status, date_modified)
VALUES ('Гривня', 'UAH', '', ' ₴', '2', 1.00000000, 1, NOW());

UPDATE oc_currency SET value = 0.02400000 WHERE code = 'USD';
UPDATE oc_currency SET value = 0.02100000 WHERE code = 'EUR';
UPDATE oc_currency SET value = 0.01800000 WHERE code = 'GBP';

UPDATE oc_setting SET value = 'UAH' WHERE `key` = 'config_currency';
UPDATE oc_setting SET value = '0' WHERE `key` = 'config_currency_auto';

-- Sample prices were in dollars: make them look like hryvnias.
UPDATE oc_product SET price = ROUND(price * 40);
UPDATE oc_product_special SET price = ROUND(price * 40);
UPDATE oc_product_discount SET price = ROUND(price * 40);
UPDATE oc_product_option_value SET price = ROUND(price * 40);
