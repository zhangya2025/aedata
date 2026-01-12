# Aegis Mail

Aegis Mail is a minimal SMTP channel plugin that routes WordPress `wp_mail()` through a single SMTP configuration. It intentionally avoids mail logs, queues/retries, statistics, or third-party API integrations.

## Configuration constants

You can define the following constants in `wp-config.php` to override the database settings:

- `AEGIS_MAIL_ENABLED` (bool)
- `AEGIS_MAIL_SMTP_HOST` (string)
- `AEGIS_MAIL_SMTP_PORT` (int)
- `AEGIS_MAIL_SMTP_ENCRYPTION` (`ssl` | `tls` | `none`)
- `AEGIS_MAIL_SMTP_AUTOTLS` (bool)
- `AEGIS_MAIL_SMTP_USERNAME` (string)
- `AEGIS_MAIL_SMTP_PASSWORD` (string)
- `AEGIS_MAIL_FROM_EMAIL` (string)
- `AEGIS_MAIL_FROM_NAME` (string)
- `AEGIS_MAIL_FORCE_FROM` (bool)

When `AEGIS_MAIL_SMTP_PASSWORD` is defined, the plugin will not store the SMTP password in the database.

## Safety notes

- The settings UI never renders the stored SMTP password in plain text.
- If the plugin is disabled or the SMTP configuration is incomplete, WordPress uses its default mail behavior.
