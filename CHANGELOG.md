# Changelog

## 0.3.2

- Initialize Google Consent Mode v2 before the blocking CookieYes loader, denying nonessential consent types and granting security storage with a 2000 ms update wait.
- Preserve existing queues and gtag functions, avoid repeated or already queued defaults, and provide a developer opt-out for another default owner. CookieYes continues to manage consent updates and saved preferences.
- Keep disabled, invalid-key and admin requests silent; preserve stored settings and Basic mode behavior without enabling Google tags or URL passthrough.
- Add executable JavaScript coverage of the emitted initializer alongside PHP, diagnostics and release checks.

## 0.3.1

- Identify local CookieYes script copies and WP Rocket-style minification, delay and defer in a separate cache/optimization finding with specific exclusion and cache-clearing instructions.
- Distinguish an unverifiable key in an optimized copy from an actual key mismatch or untrusted script source, while retaining loading and placement checks.
- Invalidate older diagnostic reports and cover optimization cases with synthetic HTML fixtures.
- Resolve relative script URLs against the first active HTML base href before checking source trust and mixed content.
- Keep WP Rocket instructions conditional, separate source trust from loading attributes, and explain possible combination when optimization hides the installation tag.

## 0.3.0

- Flag mixed-content and nomodule CookieYes scripts, and distinguish public login widgets from full WordPress login pages.

- Show a state-based top notice before checks, during checks and for the main issue; hide it when all HTML checks pass.
- Display by Amsive beside the installed version.

- Add an on-demand administrator check of anonymous homepage HTML, including CookieYes configuration, duplicates, loading attributes, Google script order and legacy Cookiebot scripts.
- Use one check button with automatic browser fallback when the server cannot fetch the homepage.
- Add a browser-assisted HTML check for local HTTPS or loopback restrictions without disabling server TLS verification.
- Show actionable findings, URL, timestamp and explicit browser-verification limits without changing settings or sending notifications.

## 0.2.0

- Show the installed plugin version beside the settings title.
- Add stable public GitHub releases through the native WordPress updater and plugin-information modal.
- Add reproducible installation packages, checksums, version preparation and manual release publication.
- Add PHP 7.4/8.2/8.4 CI and release/updater regression checks.

## 0.1.2

- Correct the product prefix to CookieYes: CookieYes Integration, with cookieyes-integration as the folder, entry-file, settings-page and text-domain slug.

## 0.1.1

- Rename the plugin to cookie.js integration and its folder, entry file, settings page and text domain to cookie-js-integration. Author and saved configuration remain unchanged.

## 0.1.0

- Add a reusable CookieYes direct installation with a per-site website key.
- Add administrator settings, validated inputs, and an explicit enable control.
- Output the blocking vendor script at wp_head priority 0, once per request.
- Keep saved configuration on deactivation and deletion for safe reinstall.
- Prevent unrelated wordpress.org updates with an explicit Update URI header.
- Allow disabling even when a newly edited website key is invalid.
