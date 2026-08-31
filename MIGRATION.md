# LDAP → SQL password migration

> **Status:** in progress. The full move off LDAP is **not scheduled yet**, so LDAP
> remains authoritative for administrative changes. Citizen self-service changes
> are written to SQL only. This document describes how passwords flow during that
> transition period.

## Overview

Dovecot no longer authenticates against LDAP directly — it authenticates against
the MariaDB `citoyens` table (see `etc/dovecot/dovecot-sql.conf.ext`). Two columns
hold the password:

| Column | Scheme | Meaning |
| --- | --- | --- |
| `legacy_password` | `{SSHA}` (also legacy `{SHA}`, `{crypt}`, `{md5}`) | The password migrated from LDAP. Read-only; never written going forward. |
| `userPassword` | `{ARGON2ID}` (older rows: `{SHA512-CRYPT}`) | The new, stronger hash, written the first time a citizen (or an admin) changes the password. |

Dovecot's `password_query` returns:

```sql
COALESCE(NULLIF(userPassword, ''), legacy_password)
```

So a citizen authenticates with `userPassword` once it is set, and falls back to the
migrated `legacy_password` until then. Every stored value carries its `{SCHEME}`
prefix, so Dovecot detects the algorithm per row regardless of `default_pass_scheme`.

## Dovecot configuration

The reference copies of the Dovecot files live in `etc/dovecot/`. They must be copied
to `/etc/dovecot/` on the mail server for changes to take effect.

### `etc/dovecot/dovecot-sql.conf.ext` → `/etc/dovecot/dovecot-sql.conf.ext`

The SQL passdb/userdb configuration. This is the file that had to be adapted for the
migration. Key settings:

```conf
driver = mysql
connect = host=localhost dbname=citizen user=root password=…
default_pass_scheme = SSHA

# passdb: prefer the new Argon2id userPassword, fall back to the legacy hash.
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
  hash carries a `{SCHEME}` prefix (`{SSHA}`, `{ARGON2ID}`, `{SHA512-CRYPT}`, …), so
  Dovecot detects the algorithm per row and this default is not actually used. This is
  what lets several schemes coexist, so switching the hash algorithm needs **no data
  migration** — rows are upgraded as citizens change their password.

### `etc/dovecot/conf.d/auth-sql.conf.ext` → `/etc/dovecot/conf.d/auth-sql.conf.ext`

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
sudo cp etc/dovecot/dovecot-sql.conf.ext /etc/dovecot/dovecot-sql.conf.ext
sudo chown root:root /etc/dovecot/dovecot-sql.conf.ext   # opened as root
sudo chmod 0600 /etc/dovecot/dovecot-sql.conf.ext        # contains the DB password
sudo doveadm reload                                      # or: systemctl reload dovecot
```

Verify without a mail client:

```bash
sudo doveadm auth test jf.test              # short login → normalized to jf.test@marche.be
sudo doveadm auth test jf.test@marche.be
```

Generate a test `{ARGON2ID}` hash for the `userPassword` column with:

```bash
doveadm pw -s ARGON2ID -p 'ClearPassword'
```

`ARGON2ID` must appear in `doveadm pw -l`; it requires Dovecot 2.3.3+ built with
Argon2 support. Verified on the current server (2.3.21), which also accepts the
PHC strings produced by PHP's `password_hash()`:

```bash
doveadm pw -t "{ARGON2ID}$(php -r 'echo password_hash("ClearPassword", PASSWORD_ARGON2ID);')" -p 'ClearPassword'
```

## Roundcube configuration

Roundcube's `password` plugin changed too: it wrote to LDAP (`ldap_simple` driver) and now
writes to `citizen.citoyens` directly.

`etc/roundcubemail/` holds **only the files that differ** from a stock install — the same
convention as `etc/dovecot/`. The full trees live outside version control:

| Path | What it is |
| --- | --- |
| `data/roundcubemail-1.7.3/` | Pristine upstream 1.7.3, for diffing |
| `data/roundcubemail/` | Copy of the production install |
| `etc/roundcubemail/` | Only the changed files, to be copied onto the server |

### `etc/roundcubemail/plugins/password/config.inc.php`

The file that does the work. Six settings changed:

```php
$config['password_driver']            = 'sql';           // was 'ldap_simple'
$config['password_algorithm']         = 'hash-argon2id'; // was 'hash-argon2i'
$config['password_algorithm_options'] = ['memory_cost' => 32768, 'time_cost' => 4, 'threads' => 1];
$config['password_algorithm_prefix']  = '{ARGON2ID}';    // was '' -- see the warning below
$config['password_db_dsn']            = 'mysql://root:*****@localhost/citizen';
$config['password_query']             = 'UPDATE citoyens SET userPassword = %P, '
                                      . 'legacy_password = NULL, password_changed_at = NOW() '
                                      . 'WHERE uid = %u OR mail = %u';
```

- **`password_algorithm_prefix` is not optional.** The sql driver calls `hash_password($passwd)`
  with no explicit method, so `password.php` overwrites its `$prefixed = true` default with
  this config value. Left empty, Roundcube stores a bare hash with no `{SCHEME}` tag, Dovecot
  falls back to `default_pass_scheme = SSHA`, and every changed password silently fails.
- **`password_db_dsn` must be set.** Empty means "use Roundcube's own database"
  (`roundcubewebmail`), which has no `citoyens` table.
- **The query clears `legacy_password`**, mirroring `CitoyenHandler::changePassword()`. Without
  it Dovecot's `COALESCE(...)` would keep accepting the old LDAP password.
- **`uid = %u OR mail = %u`** — the `username_normalize` plugin strips `@marche.be` at login,
  so `%u` is normally the bare uid; matching `mail` too keeps this correct if that changes.
  Both columns are `UNIQUE`, so at most one row is touched.

### `etc/roundcubemail/config/config.inc.php`

Only the obsolete `password_driver = 'ldap_simple'` / `password_ldap_*` block was removed.
Password settings belong in the plugin config: it is loaded *after* the main config and
`rcube_config::merge()` does `array_merge($this->prop, $prefs, ...)`, so the plugin file wins.
Credentials in this reference copy are masked (`*****`); fill them in on the server.

### Verifying the hash is interoperable

PHP's `password_hash()` emits a standard PHC string that Dovecot parses natively:

```bash
doveadm pw -t "{ARGON2ID}$(php -r 'echo password_hash("ClearPassword", PASSWORD_ARGON2ID);')" -p 'ClearPassword'
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

New passwords are hashed with **Argon2id** by `App\Support\DovecotPassword::hash()`,
which returns a value prefixed with `{ARGON2ID}` (e.g.
`{ARGON2ID}$argon2id$v=19$m=32768,t=4,p=1$…`). PHP's `password_hash()` emits the standard
PHC string, which Dovecot parses natively — the parameters are read back from the hash
itself, so they can be raised later without invalidating existing rows.

The memory cost is set to **32 MiB** rather than PHP's 64 MiB default: Dovecot re-verifies
on *every* IMAP/POP/SMTP login, and each auth worker allocates that much for the duration
of the check. Raising it increases the RAM spike on the mail server proportionally.

`DovecotPassword::check()` verifies a clear-text password against a stored Argon2id hash,
with or without the prefix. It deliberately handles only the current scheme: Dovecot performs
the actual authentication and detects any other scheme from its own prefix, so the five rows
still holding a `{SHA512-CRYPT}` hash keep working and move to `{ARGON2ID}` on their next
password change.

## Changing a password

All changes go through `App\Ldap\CitoyenHandler`, which exposes two methods:

- **`changePassword($citoyen, $password)`** — *SQL only*. Writes `{ARGON2ID}`
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

# Information pour DBM

## Migration auth ldap => Sql

### Dovecot

Un exemple d'installation dovecot et la table sql est disponible sur citoyen:/home/jfsenechal/dovecot-with-sql/
Cette config permet de s'authentifier avec le mot de passe actuel, et futur.

### Liste des utilisateurs
Les entrées de LDAP sont copiés dans la base de données sql **citizen.citoyens**

Il y a un champ **legacy_password** contenant le mot de passe actuel de la Ldap.

Le champ **userPassword** contient le mot de passe changé par l'utilisateur ou un administrateur
Ce champ contient un cryptage plus fort que sur la ldap **ARGON2ID**

La synchronisation se fait avec la commande ```php artisan citoyen:sync```

### Roundcube

Le plugin **password** de Roundcube écrivait dans la Ldap. Il écrit maintenant directement
dans la table **citizen.citoyens** (colonne `userPassword`), avec le même cryptage
**ARGON2ID** que l'application.

Les fichiers modifiés sont dans `etc/roundcubemail/` et doivent être copiés sur le serveur :

- `config/config.inc.php` — suppression de l'ancienne configuration Ldap du plugin password
- `plugins/password/config.inc.php` — passage du driver `ldap_simple` au driver `sql`

Lors d'un changement de mot de passe, le champ `legacy_password` (ancien mot de passe Ldap)
est vidé : l'ancien mot de passe ne fonctionne donc plus.
