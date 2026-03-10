-- ========================================================
-- Fix Expiration Attribute for Existing Active Bundle Users
-- ========================================================
-- Problem: Users with active bundles may have:
--   1. No Expiration in radcheck at all
--   2. Wrong format (should be "Mon DD YYYY HH:MM:SS", e.g. "Feb 22 2026 14:00:00")
--   3. Stale Expiration from a previous bundle
--
-- This script sets Expiration from userbillinfo.bundle_expiry_date
-- for all users with bundle_status = 'active' and a valid expiry date.
-- ========================================================

-- --------------------------------------------------------
-- STEP 1: Diagnostic - Show users with active bundles and their current Expiration state
-- Run this first to see what will be fixed
-- --------------------------------------------------------
SELECT
    ubi.id AS user_id,
    ubi.username,
    ubi.bundle_status,
    ubi.bundle_expiry_date,
    rc.value AS current_expiration,
    CASE
        WHEN rc.value IS NULL THEN 'MISSING'
        WHEN rc.value != DATE_FORMAT(ubi.bundle_expiry_date, '%b %d %Y %H:%i:%s') THEN 'WRONG'
        ELSE 'OK'
    END AS status
FROM userbillinfo ubi
LEFT JOIN radcheck rc ON rc.username = ubi.username AND rc.attribute = 'Expiration'
WHERE ubi.bundle_status = 'active'
  AND ubi.bundle_expiry_date IS NOT NULL
  AND ubi.bundle_expiry_date > NOW()
ORDER BY status DESC, ubi.username;


-- --------------------------------------------------------
-- STEP 2: Remove stale/wrong Expiration entries for active bundle users
-- --------------------------------------------------------
DELETE rc FROM radcheck rc
INNER JOIN userbillinfo ubi ON ubi.username = rc.username
WHERE rc.attribute = 'Expiration'
  AND ubi.bundle_status = 'active'
  AND ubi.bundle_expiry_date IS NOT NULL
  AND ubi.bundle_expiry_date > NOW();


-- --------------------------------------------------------
-- STEP 3: Insert correct Expiration for all active bundle users
-- Format: "Mon DD YYYY HH:MM:SS" (e.g. "Feb 22 2026 14:00:00")
-- This is the format FreeRADIUS expects
-- --------------------------------------------------------
INSERT INTO radcheck (username, attribute, op, value)
SELECT
    ubi.username,
    'Expiration',
    ':=',
    DATE_FORMAT(ubi.bundle_expiry_date, '%b %d %Y %H:%i:%s')
FROM userbillinfo ubi
WHERE ubi.bundle_status = 'active'
  AND ubi.bundle_expiry_date IS NOT NULL
  AND ubi.bundle_expiry_date > NOW()
  AND ubi.username IS NOT NULL
  AND ubi.username != '';


-- --------------------------------------------------------
-- STEP 4: Verify - Show the results after fix
-- --------------------------------------------------------
SELECT
    ubi.id AS user_id,
    ubi.username,
    ubi.bundle_status,
    ubi.bundle_expiry_date,
    rc.value AS new_expiration,
    CASE
        WHEN rc.value IS NOT NULL THEN 'FIXED'
        ELSE 'STILL_MISSING'
    END AS fix_status
FROM userbillinfo ubi
LEFT JOIN radcheck rc ON rc.username = ubi.username AND rc.attribute = 'Expiration'
WHERE ubi.bundle_status = 'active'
  AND ubi.bundle_expiry_date IS NOT NULL
  AND ubi.bundle_expiry_date > NOW()
ORDER BY ubi.username;


-- --------------------------------------------------------
-- STEP 5: Also fix users who are blocked but have expired bundles
-- Remove Expiration for expired/cancelled bundles (cleanup)
-- --------------------------------------------------------
DELETE rc FROM radcheck rc
INNER JOIN userbillinfo ubi ON ubi.username = rc.username
WHERE rc.attribute = 'Expiration'
  AND (ubi.bundle_status IN ('expired', 'cancelled') OR ubi.bundle_expiry_date <= NOW());


-- --------------------------------------------------------
-- STEP 6: Remove Auth-Type Reject for users with active bundles
-- (They may have been blocked before their bundle was purchased)
-- --------------------------------------------------------
DELETE rc FROM radcheck rc
INNER JOIN userbillinfo ubi ON ubi.username = rc.username
WHERE rc.attribute = 'Auth-Type'
  AND rc.value = 'Reject'
  AND ubi.bundle_status = 'active'
  AND ubi.bundle_expiry_date > NOW();


-- --------------------------------------------------------
-- STEP 7: Ensure active bundle users are NOT in disabled groups
-- --------------------------------------------------------
DELETE rug FROM radusergroup rug
INNER JOIN userbillinfo ubi ON ubi.username = rug.username
WHERE rug.groupname IN ('daloRADIUS-Disabled-Users', 'block_user')
  AND ubi.bundle_status = 'active'
  AND ubi.bundle_expiry_date > NOW();
