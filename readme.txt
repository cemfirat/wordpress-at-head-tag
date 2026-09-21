=== At Head Tag ===
Contributors: cemfirat
Tags: head, html, javascript, css, meta
Requires at least: 5.8
Requires PHP: 7.4
Stable tag: 1.4.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Add trusted HTML, CSS, JavaScript, meta and link snippets to the front-end head in WordPress.

== Description ==

At Head Tag stores one trusted source snippet and prints it inside the front-end wp_head hook.

Version 1.4 adds a source-safe WordPress code editor, enable/disable switch, configurable hook priority, escaped output preview, capability-safe raw-code storage, optional DOM-aware linting, GitHub release updates and automated integration testing.

Raw code editing requires both manage_options and unfiltered_html. On Multisite this normally requires a Super Admin. Unauthorized changes retain the previously saved snippet.

The plugin can warn about common head-code issues such as content elements inside head, duplicate charset/title elements, HTTP resources, preload links without an as attribute and external scripts without async/defer.

Stable updates are delivered from the public GitHub repository. Update checks contact api.github.com and package downloads use github.com. Saved head code is not sent to GitHub.

== Installation ==

1. Download at-head-tag.zip from the latest GitHub release.
2. In WordPress open Plugins > Add New > Upload Plugin and upload the ZIP.
3. Activate At Head Tag.
4. Open Settings > At Head Tag.
5. Add trusted source and save.

Existing single-file installations should install the release ZIP once before automatic GitHub updates are used.

== Frequently Asked Questions ==

= Who can edit raw head code? =

Users need both manage_options and unfiltered_html. On Multisite this normally means a Super Admin.

= Does the preview execute my code? =

No. The output preview is escaped text only.

= What happens if PHP DOM is unavailable? =

The plugin continues to work. Structural parser linting is skipped and a notice explains that the DOM-based check was unavailable.

= Can I temporarily disable the snippet? =

Yes. Disable output in Settings > At Head Tag. The saved source remains stored.

= How are updates delivered? =

Stable releases are discovered through the plugin's GitHub Update URI integration. The settings page includes a Check updates now link for an explicit fresh update scan.

== Changelog ==

= 1.4.0 =
* Replace the TinyMCE-based editor with WordPress's source code editor so snippets are not rewritten by a visual editor.
* Require manage_options and unfiltered_html for raw code editing while preserving existing content on unauthorized saves.
* Add an enable/disable switch without deleting the saved snippet.
* Keep configurable wp_head priority and escaped output preview.
* Make DOM parser linting optional so hosts without ext-dom do not fatal.
* Standardize English UI, text domain, author metadata and GPL-2.0-or-later licensing.
* Add stable GitHub release updates with immediate Check again cache refresh.
* Add regression, WordPress integration, ZIP upgrade and real front-end output tests.

= 1.3.1 =
* Previous single-file release supplied by the original plugin package.
