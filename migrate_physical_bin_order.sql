START TRANSACTION;

UPDATE trash_bins
SET bin_name = 'Plastic Bottle', bin_type = 'plastic'
WHERE bin_code = 'BIN-001'
  AND bin_id = 11
  AND (bin_name <> 'Plastic Bottle' OR bin_type <> 'plastic');

UPDATE trash_bins
SET bin_name = 'Thin Plastic', bin_type = 'plastic'
WHERE bin_code = 'BIN-002'
  AND bin_id = 12
  AND (bin_name <> 'Thin Plastic' OR bin_type <> 'plastic');

UPDATE trash_bins
SET bin_name = 'Metal Waste', bin_type = 'metal'
WHERE bin_code = 'BIN-003'
  AND bin_id = 13
  AND (bin_name <> 'Metal Waste' OR bin_type <> 'metal');

UPDATE trash_bins
SET bin_name = 'Bio Waste', bin_type = 'biodegradable'
WHERE bin_code = 'BIN-004'
  AND bin_id = 14
  AND (bin_name <> 'Bio Waste' OR bin_type <> 'biodegradable');

UPDATE trash_bins
SET bin_name = 'Reject Waste', bin_type = 'general'
WHERE bin_code = 'BIN-005'
  AND bin_id = 15
  AND (bin_name <> 'Reject Waste' OR bin_type <> 'general');

COMMIT;

SELECT bin_id, bin_name, bin_code, bin_type, status
FROM trash_bins
WHERE bin_code IN ('BIN-001', 'BIN-002', 'BIN-003', 'BIN-004', 'BIN-005')
ORDER BY bin_id;