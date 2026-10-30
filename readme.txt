=== CookieYes Integration ===
Contributors: amsive
Tags: cookieyes, consent, cookies
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 0.3.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Load a CookieYes banner using a website key configured separately on each WordPress site.

== Description ==

Universal direct CookieYes integration by Amsive. Configure the website key under Settings > CookieYes Integration, then enable output. Disabled by default. Banner design and consent rules remain in CookieYes.

This plugin connects visitors' browsers to CookieYes at https://cdn-cookieyes.com to load your configured banner. Review CookieYes terms (https://www.cookieyes.com/terms-and-conditions/) and privacy policy (https://www.cookieyes.com/privacy-policy/) before enabling it. No remote script is output until enabled with a valid key. Your site's CookieYes configuration determines subsequent consent processing.

Use one CookieYes installation only. Tracking printed before wp_head() requires a theme change. Enabled integrations automatically exclude the loader and consent defaults from relevant WP Rocket optimizations. Other optimizers require their own exclusions. Verify consent behavior after integration.

Enabled installations output Google Consent Mode v2 defaults before CookieYes: nonessential types denied, security storage granted and wait_for_update 2000 ms. Enable Support GCM in CookieYes so it updates consent and restores saved choices. For Basic mode, keep Allow Google tags to fire before consent off and load Google tags after CookieYes. Exclude cookieyes-integration-consent-defaults from script delay/reordering too. No Google tag or URL passthrough is enabled by this plugin. Existing default owners can opt out using the cookieyes_integration_consent_defaults_enabled filter; see README.md for limitations and CSP requirements.

== Installation ==

1. Install and activate the plugin.
2. Open Settings > CookieYes Integration.
3. Copy the website key from CookieYes Advanced Settings > Get Installation Code.
4. Save the key and enable script output after checking for duplicate installations.
5. Clear relevant caches and verify consent and tracking in the browser.

== Frequently Asked Questions ==

= Does activation immediately install a banner? =
No. Configure a key and explicitly enable output first.

= Can I use it on unrelated sites? =
Yes. Set the correct CookieYes website key separately on each site.

= Does it replace CookieYes configuration or fix GTM? =
No. Manage consent rules in CookieYes and integrate tracking in the site's theme and tag configuration.

= Are settings deleted on uninstall? =
No. Settings are retained for safe reinstall. Remove the amsive_cookieyes_settings option per site if permanent cleanup is required.

== Changelog ==

= 0.3.2 =
* Add the missing initial Google Consent Mode v2 state before CookieYes, with duplicate guards and a developer opt-out. Preserve site settings and CookieYes-managed updates.

= 0.2.0 =
* Add GitHub release updates, a version label and a documented protected-branch release process.

= 0.1.0 =
* Initial universal CookieYes integration with validated settings and explicit enable control.

== Updates ==

The plugin contacts api.github.com for stable releases from the public Amsive-Digital/wp-cookieyes-integration repository. Updates download from GitHub over HTTPS. No GitHub token or customer CookieYes key is sent in update checks. Use WordPress Enable auto-updates to opt in; host and WordPress policies still apply.
