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

## Dovecot configuration

The reference copies of the Dovecot files live in `data/dovecot/`. They must be copied
to `/etc/dovecot/` on the mail server for changes to take effect.

### `data/dovecot/dovecot-sql.conf.ext` → `/etc/dovecot/dovecot-sql.conf.ext`

The SQL passdb/userdb configuration. This is the file that had to be adapted for the
migration. Key settings:

```conf
driver = mysql
connect = host=localhost dbname=citizen user=root password=…
default_pass_scheme = SSHA

# passdb: prefer the new SHA512-CRYPT userPassword, fall back to the legacy hash.
password_query = SELECT mail AS user, COALESCE(NULLIF(userPassword, ''), legacy_password) AS password \
  FROM citoyens WHERE mail = IF('%u' LIKE '%%@%%', '%u', CONCAT('%u', '@marche.be'));

# userdb: home dir + fixed uid/gid.
user_query = SELECT homeDirectory AS home, 5000 AS uid, 5000 AS gid \
  FROM citoyens WHERE mail = IF('%u' LIKE '%%@%%', '%u', CONCAT('%u', '@marche.be'));

iterate_query = SELECT mail AS user FROM citoyens
```

What was adapted, and why:

- **`password_query` → `COALESCE(NULLIF(userPassword, ''), legacy_password)`** — makes
  Dovecot authenticate against the new `userPassword` column when it is set, and fall
  back to the migrated `legacy_password` otherwise. `NULLIF(..., '')` guards against an
  empty (non-null) value.
- **Domain-optional login** — `WHERE mail = IF('%u' LIKE '%%@%%', '%u', CONCAT('%u', '@marche.be'))`
  lets a citizen log in as either `jf.test` or `jf.test@marche.be`; when the login has
  no `@`, `@marche.be` is appended. **Gotcha:** `%` is a Dovecot variable prefix, so a
  literal SQL `%` must be written `%%` (hence `'%%@%%'`).
- **`default_pass_scheme = SSHA`** — kept as a fallback only. In practice every stored
  hash carries a `{SCHEME}` prefix (`{SSHA}`, `{SHA512-CRYPT}`, …), so Dovecot detects
  the algorithm per row and this default is not actually used.

### `data/dovecot/conf.d/auth-sql.conf.ext` → `/etc/dovecot/conf.d/auth-sql.conf.ext`

Wires both `passdb` and `userdb` to the SQL config file above:

```conf
passdb {
    driver = sql
    args = /etc/dovecot/dovecot-sql.conf.ext
}
userdb {
    driver = sql
    args = /etc/dovecot/dovecot-sql.conf.ext
}
```

This file must be enabled from `/etc/dovecot/conf.d/10-auth.conf` via an active
`!include auth-sql.conf.ext` line.

### Deploying a config change

```bash
sudo cp data/dovecot/dovecot-sql.conf.ext /etc/dovecot/dovecot-sql.conf.ext
sudo chown root:root /etc/dovecot/dovecot-sql.conf.ext   # opened as root
sudo chmod 0600 /etc/dovecot/dovecot-sql.conf.ext        # contains the DB password
sudo doveadm reload                                      # or: systemctl reload dovecot
```

Verify without a mail client:

```bash
sudo doveadm auth test jf.test              # short login → normalized to jf.test@marche.be
sudo doveadm auth test jf.test@marche.be
```

Generate a test `{SHA512-CRYPT}` hash for the `userPassword` column with:

```bash
doveadm pw -s SHA512-CRYPT -p 'ClearPassword'
```

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
