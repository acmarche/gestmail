# LDAP → SQL password migration

> **Status:** in progress. The full move off LDAP is **not scheduled yet**, so LDAP
> remains authoritative for administrative changes. Citizen self-service changes
> are written to SQL only. This document describes how passwords flow during that
> transition period.

## Overview

Dovecot no longer authenticates against LDAP directly — it authenticates against
the MariaDB `citoyens` table (see `data/dovecot/dovecot-sql.conf.ext`). Two columns
hold the password:

| Column | Scheme | Meaning |
| --- | --- | --- |
| `legacy_password` | `{SSHA}` (also legacy `{SHA}`, `{crypt}`, `{md5}`) | The password migrated from LDAP. Read-only; never written going forward. |
| `userPassword` | `{SHA512-CRYPT}` | The new, stronger hash, written the first time a citizen (or an admin) changes the password. |

Dovecot's `password_query` returns:

```sql
COALESCE(NULLIF(userPassword, ''), legacy_password)
```

So a citizen authenticates with `userPassword` once it is set, and falls back to the
migrated `legacy_password` until then. Every stored value carries its `{SCHEME}`
prefix, so Dovecot detects the algorithm per row regardless of `default_pass_scheme`.

## How the SQL table is populated

The table is filled from LDAP by:

```bash
php artisan citoyen:sync
```

Each row is built by `Citoyen::generateDataFromLdap()`, which maps the LDAP
`userPassword` attribute into `legacy_password` and leaves the SQL `userPassword`
column `null`.

## Password hashing

New passwords are hashed with **SHA512-CRYPT** by `App\Support\DovecotPassword::hash()`,
which returns a value prefixed with `{SHA512-CRYPT}` (e.g. `{SHA512-CRYPT}$6$…`).
`DovecotPassword::check()` verifies a clear-text password against a stored hash.

## Changing a password

All changes go through `App\Ldap\CitoyenHandler`, which exposes two methods:

- **`changePassword($citoyen, $password)`** — *SQL only*. Writes `{SHA512-CRYPT}`
  to `userPassword`, sets `legacy_password` to `null` (so the old hash can no longer
  be used), and stamps `password_changed_at`.
- **`changePasswordWithLdap($citoyen, $password)`** — changes the password on **LDAP
  first** (still `{SSHA}`, keeping LDAP authoritative during the transition), then
  calls `changePassword()` to mirror it into SQL.

### Where passwords can be changed

| # | Entry point | Class | Target |
| --- | --- | --- | --- |
| 1 | Admin page | `App\Filament\Resources\Citoyens\Pages\ViewCitoyen` | LDAP **then** SQL (`changePasswordWithLdap`) |
| 2 | My space (citizen) | `App\Filament\Citoyen\Pages\ChangePassword` | SQL only (`changePassword`) |
| 3 | Wizard (citizen onboarding) | `App\Filament\Citoyen\Pages\Onboarding` | SQL only (`changePassword`) |
| 4 | Command | `App\Console\Commands\PasswordCommand` (`citoyen:password`) | LDAP **then** SQL (`changePasswordWithLdap`) |

Administrative changes (1, 4) keep LDAP in sync so nothing else that still reads LDAP
breaks. Citizen self-service changes (2, 3) target SQL only, per the transition plan.

## Password policy

The strength policy is centralized once in `App\Providers\AppServiceProvider::boot()`:

```php
Password::defaults(fn (): Password => Password::min(12)->letters()->mixedCase()->numbers());
```

Every validation site references `Password::defaults()`, so the rule is changed in a
single place. Minimum length is **12 characters**.

## Important: sync must not clobber a migrated password

Because self-service changes write only to SQL, a later `citoyen:sync` run must **not**
reset `userPassword` back to `null` or resurrect the stale `legacy_password` from LDAP —
that would silently revert the citizen's new password.

This is handled by `Citoyen::syncableDataFromLdap()`, which `SyncCommand` uses on update:
once a row has a `userPassword`, the sync leaves both password columns untouched and only
syncs the non-password attributes (name, address, quota, forwarding, …).
