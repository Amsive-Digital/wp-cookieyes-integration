# CookieYes Integration

Version **0.3.1** · Author **Amsive** · GPL-2.0-or-later

A universal WordPress integration for your CookieYes banner. Each site uses its own website key; no customer names, domains or tracking IDs are embedded in the plugin.

## Install

Download `cookieyes-integration-vX.Y.Z.zip` from [GitHub Releases](https://github.com/Amsive-Digital/wp-cookieyes-integration/releases), upload it in WordPress under **Plugins → Add New → Upload Plugin**, and activate it. Use the release asset rather than GitHub's automatically generated source archive.

Open **Settings → CookieYes Integration**. Copy only the website key between `/client_data/` and `/script.js` from CookieYes **Advanced Settings → Get Installation Code**. Save the key, then explicitly enable output after checking the site's integration. The installed version appears beside the settings title.

Activation leaves banner output disabled by default. The key input accepts only a safe 8–128 character ASCII alphanumeric, underscore or hyphen segment. Invalid edits preserve the previous key; disabling still works with an invalid edited key.

## Integration

The plugin prints the vendor's blocking script at `wp_head` priority 0. Tracking hardcoded before `wp_head()` still precedes it and needs a theme change. Verify actual HTML and network behavior; ordering alone does not configure consent-aware tags.

Use one installation path: this plugin, the official CookieYes plugin, or the official GTM integration. The manual frontend check detects recognized direct snippets in the homepage HTML; it does not remove them. Exclude CookieYes from optimizations that delay or reorder scripts. Sites using CSP must permit the vendor's required script and network connections; this direct tag does not add a CSP nonce.

Banner design, consent categories, languages, logging and consent rules remain in CookieYes. This plugin does not configure GTM, GA4, Consent Mode, cookie policies or migrations from another consent service.

## Frontend integration check

Open **Settings → CookieYes Integration → Check integration** after saving settings. The plugin fetches the configured public homepage without your login cookies, follows no redirects, and checks the HTML for the saved CookieYes key, duplicate tags, head placement, async/defer/delayed loading, recognized Google snippet order and active legacy Cookiebot scripts. The request stays on the configured homepage URL and keeps HTTPS certificate verification enabled, including on local installations.

The single **Check integration** button tries the server first. If the server cannot fetch usable homepage HTML, it automatically fetches HTML through this browser without login cookies and sends it to the same site for analysis. A completed analysis that finds problems does not trigger a fallback. It keeps server certificate verification enabled and does not execute the fetched page scripts. The browser method uses a fresh request and may receive a different response from the server check. Results identify the fetch method, checked URL, site-local time, findings and next actions. They are cached per administrator for one hour and discarded when the configured URL, settings or plugin version changes. A failed fetch, inaccessible PHP DOM parser or unrecognized script order is **not verified**, never a pass. The homepage check may still receive cached or region-specific HTML and does not cover every page or identify every custom/proxied tracker. Browser tests are still required to establish actual script execution, CDN/network delivery, consent defaults, cookies and accept/reject/category/reopen behavior. The plugin sends no developer messages and changes no theme, GTM or consent settings.

The top notice asks for a check when no current result is available, shows progress while checking, and highlights the main detected issue or an incomplete check. When all HTML checks pass, the top notice is hidden; the detailed result and scope remain below. Installation guidance is ordinary help text rather than a permanent warning.

The **Cache and JavaScript optimization** finding detects identifiable local copies, WP Rocket-style minification markers and delay/defer. A matching key in a local copy URL is **not verified**, since the original CDN source and copy contents cannot be confirmed from HTML. A visible key mismatch or untrusted script source remains a **problem**. Source trust is separate from loading attributes: a foreign host does not imply defer or wrong placement. Relative URLs use the first active HTML base href when checking script origin and mixed content. If optimized JavaScript is present but the CookieYes tag is missing, the first finding explains that combination may have hidden it; this does not prove CookieYes is inside the combined file. Developer inspection is still required.

If WP Rocket is used, open **File Optimization → JavaScript → Excluded JavaScript Files** for minification/combination and add `cdn-cookieyes.com`. If delayed or deferred loading is detected, add it to the separate **Delay JavaScript execution** or **Load JavaScript deferred → Excluded JavaScript Files** field as appropriate. With another optimizer or a theme loader, exclude CookieYes in that tool or remove the loading attribute at its source. Restore the original CDN URL first: domain-based delay/defer exclusions do not match a locally hosted URL. Save changes, clear the relevant optimizer and hosting/page caches (for example, WP Engine), then rerun **Check integration**. Clearing caches alone recreates optimized copies while the responsible optimization stays enabled; a manually hosted copy needs its installation URL corrected. These are manual instructions; the plugin changes no optimizer settings and static HTML results do not establish consent correctness.

## Updates and releases

The native WordPress updater checks stable public GitHub releases; no customer GitHub token is required. Enable **Auto-updates** on WordPress's Plugins screen to opt in. WordPress and host policies still apply. Development Git checkouts are not forced to update. For preparation, publishing and rollback rules see [RELEASE.md](RELEASE.md).

Update checks contact `api.github.com`; downloads use GitHub. Banner loading contacts `cdn-cookieyes.com` only when configured and enabled. See [CookieYes terms](https://www.cookieyes.com/terms-and-conditions/) and [privacy policy](https://www.cookieyes.com/privacy-policy/).

## Requirements and development

WordPress 6.0+, PHP 7.4+. CI checks PHP 7.4, 8.2 and 8.4. Isolated contract tests use WordPress/HTTP doubles; real browser consent and update-installation checks remain separate.

```sh
php tests/plugin-contract.php
php tests/updater-contract.php
php tests/diagnostics-contract.php
python3 tests/release-contract.py
python3 scripts/release.py check
python3 scripts/release.py build
```

Settings use the WordPress Settings API, capability checks and nonces. On multisite, website keys are configured separately per site. Deactivation stops script output. Deletion clears cached release metadata but retains `amsive_cookieyes_settings` for reinstall. Remove that option per site explicitly for permanent cleanup; clear relevant HTML caches separately.

## Official references

- [CookieYes installation code](https://www.cookieyes.com/documentation/banner-installation-code/)
- [CookieYes script order](https://www.cookieyes.com/documentation/custom-data-layer/)
- [WordPress update hooks](https://developer.wordpress.org/reference/hooks/update_plugins_hostname/)
- [WP Rocket JavaScript minification and combination](https://docs.wp-rocket.me/article/1351-javascript-minify-combine)
- [WP Rocket external JavaScript exclusions](https://docs.wp-rocket.me/article/39-excluding-external-js-from-concatenation)
- [WP Rocket delay and exclusions](https://docs.wp-rocket.me/article/1349-delay-javascript-execution)
- [WP Rocket defer exclusions](https://docs.wp-rocket.me/article/976-exclude-files-from-defer-js)
