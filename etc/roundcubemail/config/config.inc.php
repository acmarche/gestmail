<?php

/* Local configuration for Roundcube Webmail */

// ----------------------------------
// SQL DATABASE
// ----------------------------------
// Database connection string (DSN) for read+write operations
// Format (compatible with PEAR MDB2): db_provider://user:password@host/database
// Currently supported db_providers: mysql, pgsql, sqlite, mssql, sqlsrv, oracle
// For examples see http://pear.php.net/manual/en/package.database.mdb2.intro-dsn.php
// Note: for SQLite use absolute path (Linux): 'sqlite:////full/path/to/sqlite.db?mode=0646'
//       or (Windows): 'sqlite:///C:/full/path/to/sqlite.db'
// Note: Various drivers support various additional arguments for connection,
//       for Mysql: key, cipher, cert, capath, ca, verify_server_cert,
//       for Postgres: application_name, sslmode, sslcert, sslkey, sslrootcert, sslcrl, sslcompression, service.
//       e.g. 'mysql://roundcube:@localhost/roundcubemail?verify_server_cert=false'
//$config['db_dsnw'] = 'mysql://root:homer@localhost/roundcubemail';
$config['db_dsnw'] = 'mysql://round:*****@localhost/roundcubewebmail';

// Name your service. This is displayed on the login screen and in the window title
$config['product_name'] = 'Webmail Citoyen';

// ----------------------------------
// IMAP
// ----------------------------------
// The IMAP host (and optionally port number) chosen to perform the log-in.
// Leave blank to show a textbox at login, give a list of hosts
// to display a pulldown menu or set one host as string.
// Enter hostname with prefix ssl:// to use Implicit TLS, or use
// prefix tls:// to use STARTTLS.
// If port number is omitted it will be set to 993 (for ssl://) or 143 otherwise.
// Supported replacement variables:
// %n - hostname ($_SERVER['SERVER_NAME'])
// %t - hostname without the first part
// %d - domain (http hostname $_SERVER['HTTP_HOST'] without the first part)
// %s - domain name after the '@' from e-mail address provided at login screen
// For example %n = mail.domain.tld, %t = domain.tld
// WARNING: After hostname change update of mail_host column in users table is
//          required to match old user data records with the new host.
$config['imap_host'] = 'ssl://citoyen.marche.be:993';

// SMTP server host (for sending mails).
// See defaults.inc.php for the option description.
$config['smtp_host'] = 'tls://citoyen.marche.be';

// SMTP username (if required) if you use %u as the username Roundcube
// will use the current username for login
$config['smtp_user'] = '%u';

// SMTP password (if required) if you use %p as the password Roundcube
// will use the current user's password for login
$config['smtp_pass'] = '%p';

// provide an URL where a user can get support for this Roundcube installation
// PLEASE DO NOT LINK TO THE ROUNDCUBE.NET WEBSITE HERE!
$config['support_url'] = '';

// This key is used for encrypting purposes, like storing of imap password
// in the session. For historical reasons it's called DES_key, but it's used
// with any configured cipher_method (see below).
// For the default cipher_method a required key length is 24 characters.
$config['des_key'] = '***';

// ----------------------------------
// PLUGINS
// ----------------------------------
// List of active plugins (in plugins/ directory)
$config['plugins'] = [
    'archive',
    'emoticons',
    'managesieve',
    'markasjunk',
    'newmail_notifier',
    'password',
    'zipdownload',
    'username_normalize'
];
$config['enable_installer'] = 0;
// This domain will be used to form e-mail addresses of new users
// Specify an array with 'host' => 'domain' values to support multiple hosts
// Supported replacement variables:
// %h - user's IMAP hostname
// %n - http hostname ($_SERVER['SERVER_NAME'])
// %d - domain (http hostname without the first part)
// %z - IMAP domain (IMAP hostname without the first part)
// For example %n = mail.domain.tld, %t = domain.tld
$config['mail_domain'] = 'marche.be';
// IMAP connection timeout, in seconds. Default: 0 (use default_socket_timeout)
$config['imap_timeout'] = 0;
// If you know your imap's folder vendor, you can specify it here.
// Otherwise it will be determined automatically. Use lower-case
// identifiers, e.g. 'dovecot', 'cyrus', 'gimap', 'hmail', 'uw-imap'.
$config['imap_vendor'] = 'dovecot';
// Maximum number of recipients per message (including To, Cc, Bcc).
// Default: 0 (no limit)
$config['max_recipients'] = 200;
// Log successful/failed logins to <log_dir>/userlogins.log or to syslog
$config['log_logins'] = true;
// Force Roundcube to log the client's real IP address in log entries
$config['log_driver'] = 'file';
$config['syslog_facility'] = LOG_MAIL;
$config['login_rate_limit'] = 6;
// Type of IMAP indexes cache. Supported values: 'db', 'apc' and 'memcache' or 'memcached'.
//$config['imap_cache'] = 'apc';
// provide an URL where a user can get support for this Roundcube installation
// PLEASE DO NOT LINK TO THE ROUNDCUBE.NET WEBSITE HERE!
$config['support_url'] = 'http://www.marche.be/marchois/?cat=6';

// Forces conversion of logins to lower case.
// 0 - disabled, 1 - only domain part, 2 - domain and local part.
// If users authentication is case-insensitive this must be enabled.
// Note: After enabling it all user records need to be updated, e.g. with query:
//       UPDATE users SET username = LOWER(username);
$config['login_lc'] = 2;

$config['skin_logo'] = [
    "elastic:login[small]" => "/images/marche.jpg",
    // show the image /images/logo_login.png for the Login screen in the Elastic skin
    "elastic:login" => "/images/marche.jpg",
    // add a link to the logo on the Login screen in the Elastic skin
    "elastic:login[link]" => "https://www.marche.be",
];
// Session lifetime in minutes
$config['session_lifetime'] = 180;
// Allow browser-autocompletion on login form.
// 0 - disabled, 1 - username and host only, 2 - username, host, password
$config['login_autocomplete'] = 2;
// Message size limit. Note that SMTP server(s) may use a different value.
// This limit is verified when user attaches files to a composed message.
// Size in bytes (possible unit suffix: K, M, G)
$config['max_message_size'] = '30M';
// Set identities access level:
// 0 - many identities with possibility to edit all params
// 1 - many identities with possibility to edit all params but not email address
// 2 - one identity with possibility to edit all params
// 3 - one identity with possibility to edit all params but not email address
// 4 - one identity with possibility to edit only signature
$config['identities_level'] = 3;

// default messages sort column. Use empty value for default server's sorting,
// or 'arrival', 'date', 'subject', 'from', 'to', 'fromto', 'size', 'cc'
$config['message_sort_col'] = 'date';

// default messages sort order
$config['message_sort_order'] = 'DESC';
// The addressbook source to store automatically collected recipients in.
// Default: true (the built-in "Collected recipients" addressbook, source id = '1')
// Note: It can be set to any writeable addressbook, e.g. 'sql'
$config['collected_recipients'] = false;

// The addressbook source to store trusted senders in.
// Default: true (the built-in "Trusted senders" addressbook, source id = '2')
// Note: It can be set to any writeable addressbook, e.g. 'sql'
$config['collected_senders'] = false;

// sort contacts by this col (preferably either one of name, firstname, surname)
$config['addressbook_sort_col'] = 'name';

// The way how contact names are displayed in the list.
// 0: prefix firstname middlename surname suffix (only if display name is not set)
// 1: firstname middlename surname
// 2: surname firstname middlename
// 3: surname, firstname middlename
$config['addressbook_name_listing'] = 0;

// Display remote resources (inline images, styles) in HTML messages. Default: 0.
// 0 - Never, always ask
// 1 - Allow from my contacts (all writeable addressbooks + collected senders and recipients)
// 2 - Always allow
// 3 - Allow from trusted senders only
$config['show_images'] = 0;
// compose html formatted messages by default
//  0 - never,
//  1 - always,
//  2 - on reply to HTML message,
//  3 - on forward or reply to HTML message
//  4 - always, except when replying to plain text message
$config['htmleditor'] = 1;
// Compact INBOX on logout
$config['logout_expunge'] = true;
// Default interval for auto-refresh requests (in seconds)
// These are requests for system state updates e.g. checking for new messages, etc.
// Setting it to 0 disables the feature.
$config['refresh_interval'] = 600;
// If true all folders will be checked for recent messages
$config['check_all_folders'] = true;
// When replying:
// -1 - don't cite the original message
// 0  - place cursor below the original message
// 1  - place cursor above original message (top posting)
// 2  - place cursor above original message (top posting), but do not indent the quote
$config['reply_mode'] = 0;
$config['reply_mode'] = 1;

// Password plugin: configured in plugins/password/config.inc.php, not here.
// That file is loaded after this one and rcube_config::merge() lets the later
// array win, so any password_* key set here would be overridden by it anyway.
// The former ldap_simple / password_ldap_* settings were dropped with the
// LDAP -> SQL migration; see MIGRATION.md.
$config['zipdownload_selection'] = true;
$config['username_domain'] = '';
//$config['username_domain_forced'] = true;
$config['blocked_schemes'] = ['http'];

