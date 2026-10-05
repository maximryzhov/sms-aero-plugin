=== SMS Aero for Elementor Forms ===
Contributors: custom-integration
Tags: elementor, elementor-pro, forms, sms, sms-aero
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds a Send SMS action to Elementor Pro Forms through SMS Aero API v2 and records every SMS attempt in a custom audit table.

== Description ==

SMS Aero for Elementor Forms adds **Send SMS** to the Elementor Pro Form widget's **Actions After Submit** selector.

Each action has these settings:

* A static recipient phone number or comma/newline-separated list of numbers.
* A message template, including Elementor field shortcodes such as `[field id="name"]`.
* An optional static sender override; otherwise the global approved sender is used.

Recipient numbers are configured in the action and are never taken from a submitted phone field. The plugin normalizes and deduplicates configured numbers, sends once per valid unique destination, and writes one audit record per destination. A shared batch UUID correlates rows from one form submission.

The protected log screen stores phone numbers, resolved messages, outcomes, provider identifiers/statuses, and the exact SMS Aero response body. Treat this as personal data and establish a suitable retention policy.

An `accepted` result means SMS Aero accepted the request. It does not confirm handset delivery. The plugin does not retry failed or timed-out requests because an ambiguous retry could send a duplicate SMS.

== Installation ==

1. Upload the plugin directory to `/wp-content/plugins/` and activate it.
2. Open **SMS Aero > Settings**.
3. Enter the SMS Aero account email/login, API key, and approved default sender.
4. Edit an Elementor Pro Form and add **Send SMS** under **Actions After Submit**.
5. Enter one or more static recipient numbers and a message template.

The API key input is always blank when rendered. Leaving it blank while saving retains the stored key.

Credentials may instead be supplied with `SMS_AERO_LOGIN`, `SMS_AERO_API_KEY`, and `SMS_AERO_SIGN` constants in `wp-config.php`.

== Frequently Asked Questions ==

= Can the recipient come from a submitted form field? =

No. Recipients are deliberately configured as a single phone number or list in the Send SMS action. Form field shortcodes are supported in the message template only.

= Does accepted mean delivered? =

No. It only means SMS Aero accepted the API request, potentially into its queue or moderation process.

= Does the plugin retry a timeout? =

No. The provider might have accepted the request before the connection timed out, so retrying could create a duplicate SMS.

= What is removed on uninstall? =

Nothing by default. Settings and logs are deleted only if the explicit uninstall-deletion option was enabled beforehand.

== Privacy Notices ==

The custom SMS log table can contain full destination numbers, complete message text, form identifiers, and SMS Aero response data. Access is restricted to administrators with `manage_options`, and recipient numbers are masked in the list view. Site operators remain responsible for notice, retention, export, and deletion obligations applicable to their deployment.

== Changelog ==

= 1.0.0 =
* Initial release with Elementor Pro action, multi-recipient SMS Aero sending, settings, and per-recipient audit logs.