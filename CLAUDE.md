# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

DaloRADIUS is a RADIUS web management platform for ISP/hotspot businesses. It provides user management, accounting, billing, and FreeRADIUS integration. MariaDB is the only fully supported DBMS.

## Running with Docker

```bash
docker-compose up -d
# Three containers: radius-mysql (MariaDB 10), radius (FreeRADIUS), radius-web (Apache/PHP)
# Operators portal: http://localhost:8000
# Users portal: http://localhost:80
# RADIUS: ports 1812/1813 UDP
```

The `init.sh` script handles first-run setup: copies `daloradius.conf.php.sample` to `daloradius.conf.php`, injects environment variables, waits for MySQL, and loads the schema from `contrib/db/mariadb-daloradius.sql`.

## Architecture

### Two Portals, One Codebase

- **Operators portal** (`app/operators/`, port 8000) — Admin interface for managing users, billing, NAS, groups, reports
- **Users portal** (`app/users/`, port 80) — Self-service interface for end users; also hosts the REST API at `app/users/api/`

Each portal has its own Apache VirtualHost (see `contrib/docker/operators.conf` and `contrib/docker/users.conf`).

### File-Based Routing

No framework or router. Each `.php` file is a directly-accessible page. URL path maps 1:1 to file path.

### Two Database Layers

The codebase uses two DB abstraction layers that coexist:

1. **PEAR DB** (`$dbSocket`) — Used by all main operator/user pages. Opened in `app/common/includes/db_open.php`, closed in `db_close.php`.
   - `$dbSocket->getOne($sql)`, `->getRow($sql)`, `->getAll($sql)`, `->query($sql)`
   - Always check results with `DB::isError($res)`

2. **mysqli** — Used by library classes (`ActionLogger`, `BalanceManager`, `BundleManager`, `RadiusAccessManager`). These create their own `mysqli` connections because they can't use the PEAR DB `$dbSocket`.

### Page Include Pattern

Every operator page follows this structure:
```php
include("library/checklogin.php");              // Session auth check
include('../common/includes/config_read.php');   // Load $configValues
include('library/check_operator_perm.php');      // ACL permission check
include_once("lang/main.php");                   // Language strings
include("../common/includes/db_open.php");       // Open PEAR DB $dbSocket
// ... page logic ...
include("../common/includes/db_close.php");      // Close DB
```

### Configuration

All config lives in `$configValues` array, loaded from `app/common/includes/daloradius.conf.php` (sample: `daloradius.conf.php.sample`). Table names are configurable via `$configValues['CONFIG_DB_TBL_*']` keys.

### Authentication & ACL

- **Operator auth**: `app/operators/dologin.php` validates against `operators` table, sets `$_SESSION['operator_user']` and `$_SESSION['operator_id']`
- **ACL check**: `check_operator_perm.php` queries `operators_acl` table. The ACL file key is derived from the script filename: hyphens replaced with underscores, `.php` stripped (e.g., `mng-edit.php` becomes `mng_edit`)
- **CSRF**: All POST forms require `dalo_csrf_token()` / `dalo_check_csrf_token()`

### Message Display

```php
$successMsg = "Done";   // Green alert via actionMessages.php
$failureMsg = "Error";  // Red alert via actionMessages.php
```

### ActionLogger Pattern

Since main pages use PEAR DB, ActionLogger needs a separate mysqli connection. Always wrap in try/catch to never break the host page:
```php
try {
    require_once(__DIR__ . '/../common/library/ActionLogger.php');
    $mysqli_log = new mysqli($configValues['CONFIG_DB_HOST'], $configValues['CONFIG_DB_USER'],
        $configValues['CONFIG_DB_PASS'], $configValues['CONFIG_DB_NAME'], $configValues['CONFIG_DB_PORT']);
    if (!$mysqli_log->connect_error) {
        $mysqli_log->set_charset('utf8mb4');
        $actionLogger = new ActionLogger($mysqli_log);
        $actionLogger->log('action_type', 'target_type', $targetId, $description);
        $mysqli_log->close();
    }
} catch (Exception $logEx) { error_log("ActionLogger error: " . $logEx->getMessage()); }
```

## Key Directories

| Path | Purpose |
|------|---------|
| `app/common/includes/` | Config, DB open/close, validation regexes, shared functions |
| `app/common/library/` | Reusable classes: ActionLogger, BalanceManager, BundleManager, RadiusAccessManager |
| `app/operators/include/menu/sidebar/` | Sidebar menu definitions per section |
| `app/operators/lang/` | Language files (`en.php`, etc.); `main.php` is the master |
| `app/users/api/` | REST API endpoints (agent topup, bundle purchase, user balance) |
| `contrib/db/` | SQL schema files; `erp_integration/` has migration scripts |
| `contrib/scripts/` | Cron jobs (bundle expiry, billing, suspend/reactivate) |

## Database Schema

- **FreeRADIUS tables**: `radcheck`, `radreply`, `radusergroup`, `radacct`, `nas`, `radippool`, `radhuntgroup` (from `contrib/db/fr3-mariadb-freeradius.sql`)
- **DaloRADIUS tables**: `operators`, `operators_acl`, `userinfo`, `userbillinfo`, `billing_plans`, `invoice`, `payment` (from `contrib/db/mariadb-daloradius.sql`)
- **ERP extensions**: `system_action_log`, `plan_price_history`, bundle tables (from `contrib/db/erp_integration/`)

## Subscription Types

- `1` = Monthly
- `2` = Prepaid (bundles)
- `3` = Outdoor/ADSL (bandwidth-only RADIUS)

## Frontend

Bootstrap 5 with card-based layouts. Vanilla JavaScript (ES5, no build tools or transpilation). Static assets in `app/common/static/`.

## No Build/Lint/Test Pipeline

There is no package manager, build step, linter, or test framework configured. PHP files are served directly by Apache.
