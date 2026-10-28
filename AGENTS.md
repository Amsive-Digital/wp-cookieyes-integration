# CookieYes Integration

- Universal WordPress plugin. Author: Amsive. Never embed customer domains, website keys or GTM IDs.
- Runtime folder and text domain: `cookieyes-integration`. GitHub repository: `Amsive-Digital/wp-cookieyes-integration`.
- Keep `main` protected. Use PRs and required CI checks after repository bootstrap.
- Fixes and small changes increment the final version component. Use `scripts/release.py prepare` to synchronize metadata after writing release notes.
- Run the focused PHP and release contract checks before committing. Do not claim a stub-based test is a real WordPress/browser update.
- Preserve existing site settings. Do not force-enable auto-updates or banner output on a development checkout.
- Follow RELEASE.md. Published tags and assets are immutable; never use force pushes or asset overwrite to repair a release.
- Commit/push, merge, release publication, activation and customer deployments have separate authorization boundaries. A request to build a release process does not itself authorize publishing every future release.
