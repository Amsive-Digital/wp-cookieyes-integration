# Changelog

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
