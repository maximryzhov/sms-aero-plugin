# SMS Aero for Elementor Forms

A WordPress plugin that adds a **Send SMS** action to Elementor Pro Forms using [SMS Aero API v2](https://smsaero.ru/integration/documentation/api/). Every intended destination gets a separate audit row in a custom WordPress table.

## Requirements

- WordPress 6.0 or newer
- PHP 7.4 or newer
- Elementor and Elementor Pro Forms
- An SMS Aero account, API key, and approved sender name

## Installation

1. Copy this directory to `wp-content/plugins/sms-aero-elementor`.
2. Activate **SMS Aero for Elementor Forms** in WordPress.
3. Open **SMS Aero → Settings** and enter the SMS Aero account login, API key, and default approved sender.
4. Edit an Elementor Pro Form and add **Send SMS** under **Actions After Submit**.
5. In the **SMS Aero** action section, configure:
   - **Recipient phone numbers:** one static number or a comma/newline-separated list.
   - **Message:** static text and/or Elementor field shortcodes such as `[field id="name"]`.
   - **Sender override:** an optional static approved sender for this form.

Recipient numbers are action configuration. They are not read from submitted form fields.

## Recipient handling

The plugin trims display punctuation, removes a leading `+`, converts an 11-digit Russian number starting with `8` to `7`, validates 10–15 digits, and deduplicates normalized destinations while preserving configured order. It makes one API request and one log row per valid unique destination. Invalid configured entries also receive audit rows but do not trigger API calls.

If a list contains a mixture of valid and invalid destinations, valid destinations are still processed. Elementor receives one generic aggregate error if any destination fails.

## Credentials and deployment constants

The API key is never rendered back into the settings form. Submitting a blank API-key field retains the saved key. These constants may be defined in `wp-config.php` and override saved values:

```php
define( 'SMS_AERO_LOGIN', 'account@example.com' );
define( 'SMS_AERO_API_KEY', 'secret-api-key' );
define( 'SMS_AERO_SIGN', 'Approved Sign' );
```

Credentials and authorization headers are never placed in audit logs, URLs, Elementor settings, template exports, or public form errors.

## Logs and privacy

**SMS Aero → SMS Logs** displays the custom table `{prefix}sms_aero_logs`. It stores destination numbers, full resolved message text, provider response bodies, and form metadata. This is personal data: apply an appropriate retention/access policy and mention it in the site's privacy documentation.

The list view masks destination numbers; users with `manage_options` can open the protected detail screen to inspect the full audit row and escaped raw provider response.

An `accepted` outcome means SMS Aero accepted the request (for example, queued or under moderation). It does not mean that the handset received the message.

## Delivery and failure behavior

Each destination is sent only once. The plugin deliberately performs no automatic retries because a network timeout can occur after SMS Aero accepted a request; retrying could send a duplicate SMS. Transport interruptions that may be ambiguous are logged as `unknown`.

Elementor runs selected actions as a chain. The SMS action does not promise to cancel actions that Elementor runs later. It skips sending if an earlier action has already marked the form response unsuccessful.

## Uninstall

Settings and logs are preserved by default. To delete both on uninstall, enable **Delete SMS Aero settings and all SMS logs when the plugin is uninstalled** before deleting the plugin.

## Development

Production has no Composer runtime dependency. Development commands are:

```sh
composer install
composer test
composer phpcs
composer lint
```

All tests use injected fake HTTP transports and must not contact SMS Aero.
