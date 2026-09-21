# Testing

## Automated checks already run (WordPress 7.1.1, PHP 8.3, SQLite, Elementor 4.2.4, Astra 4.13.12)

- Validator: quote/space normalisation; rejects `Font 2`, CSS injection and generic names; fallback cleanup.
- CSS2 URLs: single weight, variable range, static weight, italic tuples; every preset verified live against Google (Lalezar is 400 only).
- Uploads: real WOFF2 stored; PHP disguised as `.woff2` and a `.php` shell rejected over real HTTP multipart; missing/invalid nonce → HTTP 403.
- Storage: relative paths only; `.htaccess` and `index.php` present; no PHP files in the fonts folder.
- Self-hosting: Google files downloaded, `unicode-range` kept, no `gstatic` URLs left.
- Elementor: UCF group registered; uploaded alias listed; Elementor's own Google Vazirmatn untouched; no duplicate CDN request.
- Astra: fonts in the Customizer list without duplicating Astra's Google entries; local fonts never sent to Google.
- TinyMCE: dropdown filled, fonts preview inside the editor iframe, duplicate buttons removed.
- Block Editor: fonts appended to theme.json; theme fonts kept; faces only in editor contexts.
- Front end: page without custom fonts → no plugin output; classic content, block attribute and Astra body font → only the used font loads.
- All admin tabs render without warnings; opt-in uninstall removes options, transients and files.
- PHPCS (WordPress-Extra, WordPress-Docs, PHPCompatibilityWP 8.1+): no violations.
- Admin UI in headless Chromium (25 checks): search, own preview text (Persian text switches to right-to-left), script switch, size slider, instant-save switches that leave per-editor settings untouched, every type-tester control, copy with and without clipboard permission, form sections, help links, and no JavaScript errors.
- Right-to-left verified with the real Persian (fa_IR) language pack; every admin view renders without PHP notices.

## Manual checklist (run on a staging copy first)

**Admin interface**
- [ ] Library: search, type your own preview text, switch Persian/Latin, change the size.
- [ ] Open a font: try weight, size, line height, alignment and the right-to-left button; select Copy and paste the CSS somewhere.
- [ ] Switch the admin language to Persian (Settings → General) and check that every page mirrors correctly.

**Library**
- [ ] Add Vazirmatn and Lalezar from "Available fonts"; confirm Lalezar only offers weight 400.
- [ ] Upload a WOFF2 (and WOFF) for weights 400 and 700; preview text renders in the table.
- [ ] Try a renamed `.php` or `.exe` file: it must be rejected with a clear message.
- [ ] "Host on this server" on a CDN font: the Source column changes and the Network tab shows no Google requests.
- [ ] Disable a font: it disappears from every dropdown.

**Editors and builders** (for each: pick the font, save, check the front end)
- [ ] Classic Editor: Font Family dropdown lists the fonts; the editor text changes font.
- [ ] Block Editor: Typography → Font on a Paragraph; Site Editor → Styles → Typography.
- [ ] Elementor: Heading → Style → Typography → Family shows the "PFont" group; check the Elementor preview and the published page.
- [ ] Astra: Customizer → Global → Typography lists the fonts under system fonts.
- [ ] Kadence / GeneratePress (if used): Customizer typography.
- [ ] Appearance → Fonts (WordPress 6.5+): the page opens and lists the theme's fonts plus yours.
- [ ] Settings → WordPress admin: pick a font; menus and screens change, icons and code fields do not.
- [ ] Users → Profile → Admin Color Scheme: switch scheme; PFont buttons and switches follow it.

**Front end**
- [ ] View source on a page without custom fonts: no `ucf-fonts` style and no CDN request.
- [ ] On a page with a font: one `ucf-fonts` style, only the used families, `font-display:swap`.
- [ ] RTL: switch the site language to Persian/Arabic and check text shaping and the admin screen layout.
- [ ] Caching/optimisation plugins: clear caches; if CSS is combined, confirm the inline style is kept.

**Clean-up**
- [ ] Delete the plugin with uninstall options off: fonts folder and settings remain.
- [ ] Re-install, enable both uninstall options, delete: folder and options are removed.
