-- city_category fix: align `expense`.`city_category` with the approved A/B/C city list.
-- Rows are matched by ccid AND current value, so re-running it is harmless.
-- No rows are deleted; every ccid keeps its meaning. Take a backup first.
-- Database: expense

START TRANSACTION;

-- 1. Spelling fixes on existing A/B rows (category already correct) ------------
UPDATE `city_category` SET `city_name` = 'Vijayawada'   WHERE `ccid` = 6  AND `city_name` = 'Vijaywada';
UPDATE `city_category` SET `city_name` = 'Aizawl'       WHERE `ccid` = 17 AND `city_name` = 'Aizwal';
UPDATE `city_category` SET `city_name` = 'Bhubaneshwar' WHERE `ccid` = 26 AND `city_name` = 'Bhubaneswar';
UPDATE `city_category` SET `city_name` = 'Kolkata'      WHERE `ccid` = 35 AND `city_name` = 'Kolkatta';
UPDATE `city_category` SET `city_name` = 'Vadodara'     WHERE `ccid` = 38 AND `city_name` = 'Vadodora';
UPDATE `city_category` SET `city_name` = 'Muzzarpur'    WHERE `ccid` = 61 AND `city_name` = 'Muzaffarpur';  -- spelling as in the approved list
UPDATE `city_category` SET `city_name` = 'Bilaspur'     WHERE `ccid` = 62 AND `city_name` = 'Bilas-Pur';
UPDATE `city_category` SET `city_name` = 'Dhanbad'      WHERE `ccid` = 69 AND `city_name` = 'Dhan-Bad';

-- 2. Jammu: B -> A, renamed to "Jammu and Kashmir" ------------------------------
UPDATE `city_category` SET `city_name` = 'Jammu and Kashmir', `city_category` = 'A'
 WHERE `ccid` = 40 AND `city_name` = 'Jammu' AND `city_category` = 'B';

-- 3. "Bokaro, Hazaribag" was two cities in one row -> keep as Hazaribag;
--    Bokaro already exists as ccid 2677 (set to B in section 5).
UPDATE `city_category` SET `city_name` = 'Hazaribag'
 WHERE `ccid` = 58 AND `city_name` = 'Bokaro, Hazaribag';

-- 4. Guwahati: C -> A -----------------------------------------------------------
UPDATE `city_category` SET `city_name` = 'Guwahati', `city_category` = 'A'
 WHERE `ccid` = 111 AND `city_category` = 'C';

-- 5. Duplicate / variant rows wrongly in C: move to A ---------------------------
--    111 handled above; 106 BBSR, 133 Gurgaon Haryana, 165 Bengaluru, 310 New Delhi,
--    533 Vishakapatnam, 744 Vijayawada, 997 Gurugram, 2047 Delhi ISBT, 2093 Kolkata,
--    2133 metro delhi, 2135 Delhi rohilla, 2318 Shivaji Nagar Pune, 2490 Lucknow`,
--    3839 Vishakhapatnam, 3938 delhi Anand
UPDATE `city_category` SET `city_category` = 'A'
 WHERE `city_category` = 'C'
   AND `ccid` IN (106, 133, 165, 310, 533, 744, 997, 2047, 2093, 2133, 2135, 2318, 2490, 3839, 3938);

-- 6. Duplicate / variant rows wrongly in C: move to B ---------------------------
--    137 Karnool, 186 Purnea Bihar, 209 Prayagraj, 249 Bareilley, 258 Hazaribagh,
--    431 NAshik, 438 Silliguri, 501 Baroda, 671 Gunture, 682 Sambhajinagar,
--    1156 Sambhaji Nagar, 1216 Chhatrapati Sambhaji Nagar, 1297 bareilly, 1343 Mysuru,
--    1777/1835/2038/2190 Agra variants, 1798 Bilaspur, 1912 Dhanbad, 2022 jambhaji Nagar,
--    2386 Kanpur Nagar, 2539 Muzzafarpur, 2562 Vadodara, 2677 Bokaro, 3978 Guntur,
--    4048 Prayagraj``
UPDATE `city_category` SET `city_category` = 'B'
 WHERE `city_category` = 'C'
   AND `ccid` IN (137, 186, 209, 249, 258, 431, 438, 501, 671, 682, 1156, 1216, 1297, 1343,
                  1777, 1798, 1835, 1912, 2022, 2038, 2190, 2386, 2539, 2562, 2677, 3978, 4048);

-- 7. Union-territory rows (Daman and Diu / Dadra and Nagar Haveli): C -> A ------
--    1350 Diu, 2078 Nani Daman, 2613 Daman, 3917 Haveli
UPDATE `city_category` SET `city_category` = 'A'
 WHERE `city_category` = 'C' AND `ccid` IN (1350, 2078, 2613, 3917);

-- 8. Missing A-category entries (skipped if the name already exists as A) --------
--    city_name is varchar(30), so the long UT name is abbreviated.
INSERT INTO `city_category` (`city_name`, `city_category`, `crby`, `crdate`)
SELECT v.n, 'A', 0, CURDATE()
FROM (
  SELECT 'Amaravati' AS n
  UNION ALL SELECT 'Andaman and Nicobar'
  UNION ALL SELECT 'Lakshadweep'
  UNION ALL SELECT 'Puducherry'
  UNION ALL SELECT 'Ladakh'
  UNION ALL SELECT 'Dadra Nagar Haveli Daman Diu'
) AS v
WHERE NOT EXISTS (
  SELECT 1 FROM `city_category` c WHERE c.`city_name` = v.n AND c.`city_category` = 'A'
);

COMMIT;

-- Verification (on the dump: A 33 -> 60, B 41 -> 67, C 2315 -> 2268) ---------------
-- SELECT `city_category`, COUNT(*) FROM `city_category` GROUP BY `city_category`;
-- SELECT * FROM `city_category` WHERE `city_category` IN ('A','B') ORDER BY `city_category`, `city_name`;

-- Left untouched on purpose:
--   ccid 44  Aurangabad (B)  - old name of Sambhaji Nagar, kept as an alias
--   ccid 718 Amravati (C)    - Amravati, Maharashtra is a different city from Amaravati (AP capital)
--   ccid 1648 Kashmiri Gate  - a Delhi locality; reassign manually if wanted
