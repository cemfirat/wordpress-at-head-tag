# Changelog

## 1.4.1

- Add a post-release production smoke test that installs the previous public release and updates it through the real GitHub updater.
- Verify the public `releases/latest` endpoint, WordPress update discovery, release ZIP download, directory preservation and final installed version together.
- No runtime behavior changes from 1.4.0.

## 1.4.0

- Replace the TinyMCE-based editor with WordPress's source code editor so trusted source snippets are not rewritten by a visual editor.
- Require both `manage_options` and `unfiltered_html` for raw code editing; unauthorized submissions retain the previous value.
- Add an enable/disable switch that pauses output without deleting the saved snippet.
- Preserve configurable `wp_head` priority and provide an escaped text preview.
- Make DOM parser linting optional so the plugin still works on PHP installations without ext-dom.
- Standardize the public UI and plugin metadata in English and use a consistent `at-head-tag` text domain.
- Add `GPL-2.0-or-later` metadata and a complete GPL v2 license file.
- Add stable GitHub release updates with a correct manual **Check again** cache refresh path.
- Add dependency-free regression tests, real WordPress integration tests, real ZIP upgrade tests and front-end output checks.
- Package releases reproducibly as `at-head-tag.zip` in the `wordpress-at-head-tag` plugin directory.

## 1.3.1

Previous single-file release supplied by the original plugin package.
