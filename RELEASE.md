# Releases

The public repository is `Amsive-Digital/wp-cookieyes-integration`. The installable plugin slug remains `cookieyes-integration`.

## Prepare

1. Start a working branch from current `main`. Keep fixes and small changes in the final version component; use a minor increment for a new feature.
2. Add a nonempty `## X.Y.Z` entry to `CHANGELOG.md`.
3. Run `python3 scripts/release.py prepare --version X.Y.Z`. This synchronizes the plugin header, version constant, readme stable tag, and README version.
4. Run PHP syntax checks, `php tests/plugin-contract.php`, `php tests/updater-contract.php`, `php tests/diagnostics-contract.php`, `node tests/consent-contract.js` (Node.js 18+), and `python3 tests/release-contract.py`.
5. Commit, push, and open a PR into `main`. Merge only after the protected branch requirements pass. Approval/merge authorization remains a separate delivery decision.

## Publish

From **Actions → Publish release → Run workflow**, choose **main** and enter the version already merged there. The workflow validates the exact dispatch commit, reruns checks, builds the ZIP and SHA-256 file, uploads both to a draft release, verifies the GitHub asset digests, and only then publishes it.

Packages use `cookieyes-integration-vX.Y.Z.zip` and contain the root `cookieyes-integration/`. Only explicit runtime and documentation files are packaged. Tests, workflows, scripts, Git data, customer configuration, and development paths are excluded.

Exact reruns are accepted. The publisher refuses a tag pointing at another commit, a prerelease with the same tag, a mismatched existing asset, or an incomplete already-published release. Never replace or move a published tag/asset; fix forward with a new patch version. A failed draft may be resumed only with the same version and commit.

## Verify

Confirm the Actions run completed, the release is public and stable, its tag matches the intended main commit, both expected assets exist, and the downloaded checksum passes. Verify ZIP root and version. Test update discovery on a nonproduction WordPress installation before relying on unattended updates.

## WordPress updates

The plugin reads stable releases from the public GitHub API and uses the native WordPress update hooks. No GitHub token is stored on customer sites. Update metadata is cached for 12 hours (failed lookups for 5 minutes), refreshed when an explicit WordPress force-check is requested, before core runs its check, and cleared after this plugin's upgrades. Drafts, prereleases, malformed versions, foreign repositories and wrong package URLs are rejected.

Enable **Auto-updates** for this plugin from WordPress's Plugins screen when appropriate. Hosting policy, WP-Cron, filesystem permissions, VCS checkout detection, and WordPress-wide update settings still apply. Development checkouts are not forced to update themselves. On multisite, WordPress controls updates at network level.

GitHub Releases are code distribution only; publishing does not configure CookieYes, activate the banner, change a theme, or deploy a customer site.

Release publication uses GitHub release IDs for drafts; tag lookup is reserved for published tags. The publisher refuses to mark a version older than an existing stable release as latest. Critical validations are explicit exceptions and remain enabled under Python optimization.
