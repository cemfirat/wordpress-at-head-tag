<p align="center">
  <img src="https://raw.githubusercontent.com/cemfirat/repository-governance/main/assets/brand-banner.webp" alt="Cem Firat creative consultancy artwork" width="900" />
</p>

# At Head Tag

A small WordPress plugin for adding trusted HTML, CSS, JavaScript, meta and link snippets to the front-end `<head>`.

**Author:** [Cem Firat](https://cemfirat.com/)  
**License:** GPL-2.0-or-later  
**Requirements:** WordPress 5.8+ and PHP 7.4+.

## What it does

At Head Tag stores one trusted source snippet and prints it inside clear source markers in `wp_head`:

```html
<!-- at-head-tag START -->
<meta name="example" content="value">
<script defer src="https://example.com/app.js"></script>
<!-- at-head-tag END -->
```

The settings screen provides a WordPress code editor, an enable/disable switch, configurable hook priority, escaped output preview, non-blocking lint feedback for common head-code mistakes, and a real front-end output check.

## Security model

Raw head code is powerful. Editing therefore requires both `manage_options` and WordPress's `unfiltered_html` capability. On Multisite this normally means a Super Admin. Unauthorized submissions retain the previously saved snippet instead of silently rewriting it.

The plugin stores trusted source as entered, apart from invalid text encoding/NUL handling. The preview is escaped and never executes the saved snippet.

## Lint checks

The plugin can warn about:

- elements that normally do not belong in `<head>`
- multiple `meta charset` or `title` elements
- HTTP resources on HTTPS-oriented sites
- preload links without an `as` attribute
- external scripts without `async` or `defer`
- parser problems when PHP DOM is available

These are warnings, not an HTML policy engine. They do not replace browser or application testing.

## Install

1. Download **at-head-tag.zip** from the [latest release](https://github.com/cemfirat/wordpress-at-head-tag/releases/latest).
2. In WordPress open **Plugins → Add New → Upload Plugin** and upload the ZIP.
3. Activate **At Head Tag**.
4. Open **Settings → At Head Tag**.
5. Add trusted head code and save.

Existing installations of the earlier single-file plugin should install the release ZIP once. After that, stable GitHub releases can be discovered through WordPress's native plugin update flow.

## Updates and privacy

Stable updates are delivered from this public GitHub repository using WordPress's `Update URI` integration. Update checks contact `api.github.com`; package downloads use `github.com`. Saved head code is never sent to GitHub.

The settings screen includes **Check updates now**, which uses WordPress's explicit update refresh and clears both the plugin release cache and WordPress plugin-update state before the new check. It also includes **Check front-end output**, which fetches the home page with a cache-busting query and confirms whether the saved snippet is actually present inside `<head>`.

## Development

Run:

```sh
php tests/run.php
python3 scripts/package.py
```

GitHub Actions also runs syntax checks, WordPress 5.8/PHP 7.4 integration tests, latest WordPress/PHP 8.3 integration tests, real ZIP upgrades and real front-end output checks.

## Project links

- Releases: https://github.com/cemfirat/wordpress-at-head-tag/releases
- Issues: https://github.com/cemfirat/wordpress-at-head-tag/issues
- Author: https://cemfirat.com/
- Changelog: [CHANGELOG.md](CHANGELOG.md)

Copyright © 2026 Cem Firat. At Head Tag is licensed under GPL-2.0-or-later.
