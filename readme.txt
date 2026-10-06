=== PFont ===
Contributors: wordpress-parsi
Tags: fonts, custom fonts, google fonts, elementor, persian
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.5.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

One font library for the Classic Editor, Block Editor, Elementor and Astra. Upload or self-host Google Fonts; pages load only the fonts they use.

== Description ==

PFont keeps one central font library and adds those fonts to the native font dropdowns of the editors and builders you already use. It only uses documented, verified hooks and never edits core, theme or builder files.

**What it does**

* Upload WOFF2, WOFF, TTF and OTF files. Every file is checked by its real contents (magic bytes), not only by its name.
* Add Google Fonts from the CDN (Google or the privacy-friendlier Bunny Fonts), or download them to your own server with one click.
* Ships five Persian/Arabic presets you can add in one click: Vazirmatn, Estedad, Parastoo, Lalezar and Markazi Text (all SIL Open Font License). Nothing is enabled automatically.
* Loads a font only on pages that actually use it. Pages without custom fonts get no extra CSS, requests or JavaScript.
* WOFF2 first, `font-display: swap` by default, preconnect only when a CDN font is used.
* Honest integration status screen: every integration shows Active, Limited, Unsupported or Not detected, with the reason.
* A searchable library that previews every font in your own text, in Persian or Latin, at any size.
* A type tester for each font: weight, italic, size, line height, alignment and right-to-left, plus a size waterfall, the character set and copyable CSS.
* Optional: use one of your fonts for the WordPress admin area (Settings → WordPress admin). Icons, code fields and the content you edit keep their own fonts.

**Integrations**

* Classic Editor (TinyMCE): font dropdown, fonts preview inside the editor.
* Block Editor: fonts appear in Typography → Font for every block and in Global Styles. Theme fonts are kept (the list is appended, not replaced).
* Elementor: own "PFont" group in every typography control. Elementor's own Google entries are never overwritten by CDN copies.
* Astra: fonts appear in the Customizer typography controls. Local fonts are never sent to Google.
* Kadence: fonts appear in the Customizer. GeneratePress: type the family name in its Font Manager; the plugin loads the files.

Themes without a public font hook are reported as unsupported instead of being hacked.

Divi and WoodMart are not integrated: both already include their own custom font upload, so add the font there (Divi: the Upload button inside any font menu; WoodMart: Theme Settings → Typography → Custom fonts).

**Privacy**

Fonts loaded from a CDN send visitors' IP addresses to that CDN. A German court (LG München I, 20 January 2022, 3 O 17493/20) awarded damages for embedding Google Fonts without consent. Use "Host on this server" (or uploaded files) to avoid third-party requests. This is not legal advice.

== External services ==

This plugin can connect to one of two font services. Which one is used depends on Settings → Font CDN (Google Fonts by default). Uploaded fonts and fonts hosted on your server use no external service.

**Google Fonts** (fonts.googleapis.com and fonts.gstatic.com), provided by Google.

* When: on pages that use a font you added from Google and did not host locally; in the editors, so the font can be previewed; on the plugin's Library screen, to preview the Persian and Arabic presets; when you save such a font (to check the name and weights); and once when you choose "Host on this server" (to download the files).
* What is sent: the font family name, the weights and styles requested, and, as with any web request, the IP address and user agent of the browser or server making it. No account, form or content data is sent.
* Google Fonts privacy FAQ: https://developers.google.com/fonts/faq/privacy
* Terms: https://policies.google.com/terms
* Privacy policy: https://policies.google.com/privacy

**Bunny Fonts** (fonts.bunny.net), provided by BunnyWay d.o.o. Used instead of Google Fonts, in the same situations and with the same data, when you select it under Settings → Font CDN.

* About: https://fonts.bunny.net/about
* Terms: https://bunny.net/tos/
* Privacy policy: https://bunny.net/privacy/


== Installation ==

1. Upload the plugin ZIP under Plugins → Add New → Upload Plugin, then activate it.
2. Open Settings → PFont.
3. Add one of the available fonts on the Library screen, or upload your own under "Add new font".
4. Choose where each font should appear in the library table, then pick the font in your editor or builder.

== Screenshots ==

1. Font library page
2. Add new font page
3. Integrations page
4. Settings page

== Frequently Asked Questions ==

= Why is a font I added from Google not in Elementor's "PFont" group? =

Elementor already ships many Google Fonts (including Vazirmatn, Lalezar and Markazi Text). A CDN copy would only duplicate it, so the plugin leaves Elementor's own entry in place and Elementor loads it. If you host the font on your server or upload it, the plugin takes over that entry so Elementor stops requesting it from Google.

= Does it slow my site down? =

No global CSS is added. Each page gets at most one small inline style block with the @font-face rules it needs, plus a CDN stylesheet only if a CDN font is used on that page.

= Which file format should I upload? =

WOFF2. It is the smallest format and supported by all current browsers.

= Can I change the font of the WordPress admin area? =

Yes. Under Settings → WordPress admin, choose any enabled font from your library. It is applied to the dashboard screens and the admin toolbar. Icons, code fields and the content you edit keep their own fonts. The login page, the Customizer and the toolbar on the front end are not changed. If the font loads from a CDN it is requested on every admin screen, so hosting it on your server is the better choice.

= Why is there no Divi or WoodMart integration? =

Both already include a custom font upload of their own, so a second layer is not needed. Upload the font in Divi (the Upload button inside any font menu) or in WoodMart (Theme Settings → Typography → Custom fonts). Version 1.2.0 removed the earlier Divi and WoodMart support. Fonts and settings you saved are kept; Media Library copies made by the old WoodMart helper are not deleted, so WoodMart keeps working.

= What happens to my fonts when I delete the plugin? =

Nothing, unless you enabled the uninstall options under Settings. Font files and settings are kept by default.

= Can I add fonts from code? =

Yes. Use the `pfont_registered_fonts` filter to add font records, `pfont_cdn_presets` to change the preset list and `pfont_integrations` to add or replace an integration adapter. Every hook is documented in the source with a docblock.

== Changelog ==

= 1.5.0 =
* Removed the "Custom stylesheet URL" field. Loading a stylesheet from an arbitrary address is not allowed in the plugin directory. Fonts come from Google Fonts or Bunny Fonts, are hosted on your server, or are uploaded. A font that was saved with a custom URL is kept but disabled; open it and choose a Google font or upload its files.
* Security: every form value is sanitized the moment it is read, before validation, and only the sanitized copy is kept when a save fails.
* Every option, transient, form field, nonce, action, CSS class and script handle now uses the `pfont` prefix. Saved fonts and settings are moved to the new option names automatically on the first request after the update.
* Removed the deprecated `ucf_*` hook names that 1.4.0 still fired next to the `pfont_*` ones. Only the `pfont_*` hooks remain.
* Block Editor: the font preset slug is now `pfont-{id}` instead of `ucf-{id}`. Fonts picked in the Block Editor before this update must be picked again in the block or in Styles.

= 1.4.0 =
* Plugin Check: all reported errors and warnings fixed.
* Developers: hooks, global functions and constants now use the `pfont_` / `PFONT_` prefix, because WordPress.org asks for a prefix of at least four characters. The old hook names still worked in this version and showed a deprecation notice.
* Removed the `load_plugin_textdomain()` call. WordPress loads the translations by itself.
* Replaced `array_is_list()` with an own helper, so the plugin keeps working with "Requires at least: 6.4".
* The development file `phpcs.xml.dist` is now `phpcs.xml` (".dist" files are not allowed in the plugin directory).

= 1.3.1 =
* Library: when the library is empty, the Persian and Arabic fonts are shown first and the upload card below them.
* "Add font" is now "Add new font" (sidebar and page title) and "Upload files" is now "Upload custom font".
* Add/Edit font: the font details and its files (or weights and styles) are one section instead of two.
* Fixed: a PHP warning ("Undefined array key files") when adding a new uploaded font. On servers that display errors it could stop the redirect after saving.

= 1.3.0 =
* The plugin is now called PFont. Only the name changed: your fonts, settings, files and the fonts already chosen in your content are untouched.
* Buttons, switches, checkboxes, radio buttons, chips and sliders now use your WordPress admin colour scheme (Users → Profile → Admin Color Scheme) instead of black.
* Library: the "Add font" button next to the page title was removed. "Add font" stays in the sidebar.
* Elementor: the font group is now labelled "PFont".

= 1.2.0 =
* New: choose a library font for the WordPress admin area under Settings → WordPress admin.
* Fixed: on themes with their own fonts (for example Twenty Twenty-Five), the theme's fonts were replaced by one nameless entry. Fonts were missing from the Block Editor font list and Appearance → Fonts stopped with "Something went wrong" (can't access property "localeCompare", name is undefined). Theme fonts are now kept and your fonts are appended.
* Fixed: CDN fonts were loaded inside the Block Editor canvas but not on the editor screen around it.
* Removed: Divi 4, Divi 5 and WoodMart support. Both products include their own custom font upload. Saved fonts are not affected.
* Readme: new "External services" section.

= 1.1.0 =
* New admin interface: sidebar navigation, card-based settings with switches, and a Help page.
* Library: live search, your own preview text, Persian/Latin samples and a size slider; switches save instantly.
* New font page with a type tester, size waterfall, character set, details and where the font appears.
* Integrations: platforms that are not installed no longer show font counts; Astra, Divi and WoodMart sites no longer see a misleading "Unsupported" Customizer entry.
* Copy buttons fall back gracefully when the browser refuses clipboard access.

= 1.0.0 =
* First release.

== Upgrade Notice ==

= 1.5.0 =
The custom stylesheet URL field is gone and all stored data moves to prefixed option names (done automatically). Fonts chosen in the Block Editor must be picked again. Old `ucf_*` hook names no longer fire.

= 1.4.0 =
Passes Plugin Check. If you hook into the plugin from your own code, rename `ucf_` hooks to `pfont_` (the old names still work in this version).

= 1.3.0 =
Renamed to PFont (nothing to migrate). The interface now follows your WordPress admin colour scheme.

= 1.2.0 =
Fixes missing fonts in the Block Editor and the crash on Appearance → Fonts. Adds an admin area font. Divi and WoodMart support was removed; upload fonts in those products directly.

= 1.1.0 =
New admin interface with a type tester for every font. No settings change.
