# CookieYes Integration

Version **0.2.0** · Author **Amsive** · GPL-2.0-or-later

A universal WordPress integration for your CookieYes banner. Each site uses its own website key; no customer names, domains or tracking IDs are embedded in the plugin.

## Install

Download `cookieyes-integration-vX.Y.Z.zip` from [GitHub Releases](https://github.com/Amsive-Digital/wp-cookieyes-integration/releases), upload it in WordPress under **Plugins → Add New → Upload Plugin**, and activate it. Use the release asset rather than GitHub's automatically generated source archive.

Open **Settings → CookieYes Integration**. Copy only the website key between `/client_data/` and `/script.js` from CookieYes **Advanced Settings → Get Installation Code**. Save the key, then explicitly enable output after checking the site's integration. The installed version appears beside the settings title.

Activation leaves banner output disabled by default. The key input accepts only a safe 8–128 character ASCII alphanumeric, underscore or hyphen segment. Invalid edits preserve the previous key; disabling still works with an invalid edited key.

## Integration

The plugin prints the vendor's blocking script at `wp_head` priority 0. Tracking hardcoded before `wp_head()` still precedes it and needs a theme change. Verify actual HTML and network behavior; ordering alone does not configure consent-aware tags.

Use one installation path: this plugin, the official CookieYes plugin, or the official GTM integration. This plugin does not detect or remove other snippets. Exclude CookieYes from optimizations that delay or reorder scripts. Sites using CSP must permit the vendor's required script and network connections; this direct tag does not add a CSP nonce.

Banner design, consent categories, languages, logging and consent rules remain in CookieYes. This plugin does not configure GTM, GA4, Consent Mode, cookie policies or migrations from another consent service.

## Updates and releases

The native WordPress updater checks stable public GitHub releases; no customer GitHub token is required. Enable **Auto-updates** on WordPress's Plugins screen to opt in. WordPress and host policies still apply. Development Git checkouts are not forced to update. For preparation, publishing and rollback rules see [RELEASE.md](RELEASE.md).

Update checks contact `api.github.com`; downloads use GitHub. Banner loading contacts `cdn-cookieyes.com` only when configured and enabled. See [CookieYes terms](https://www.cookieyes.com/terms-and-conditions/) and [privacy policy](https://www.cookieyes.com/privacy-policy/).

## Requirements and development

WordPress 6.0+, PHP 7.4+. CI checks PHP 7.4, 8.2 and 8.4. Isolated contract tests use WordPress/HTTP doubles; real browser consent and update-installation checks remain separate.

```sh
php tests/plugin-contract.php
php tests/updater-contract.php
python3 tests/release-contract.py
python3 scripts/release.py check
python3 scripts/release.py build
```

Settings use the WordPress Settings API, capability checks and nonces. On multisite, website keys are configured separately per site. Deactivation stops script output. Deletion clears cached release metadata but retains `amsive_cookieyes_settings` for reinstall. Remove that option per site explicitly for permanent cleanup; clear relevant HTML caches separately.

## Official references

- [CookieYes installation code](https://www.cookieyes.com/documentation/banner-installation-code/)
- [CookieYes script order](https://www.cookieyes.com/documentation/custom-data-layer/)
- [WordPress update hooks](https://developer.wordpress.org/reference/hooks/update_plugins_hostname/)
