# PFont

One font library for WordPress: upload fonts or use/self-host Google Fonts, and use them in the **native** font dropdowns of the Classic Editor, Block Editor, Elementor, Astra, Kadence and GeneratePress. Optionally, use one of your fonts for the WordPress admin area.

- Requires WordPress 6.4+ and PHP 8.1+. Tested on WordPress 7.1.1, PHP 8.3, Elementor 4.2.4 and Astra 4.13.12.
- Text domain `universal-custom-fonts`, prefix `pfont_` for hooks, functions and constants (options keep their `ucf_` names so saved data is untouched), namespace `UniversalCustomFonts`.
- No Composer at runtime, no custom tables (options `ucf_fonts`, `ucf_settings`, `ucf_db_version`).

## Integration status

| Integration | Status | How |
|---|---|---|
| Classic Editor (TinyMCE) | Active | `tiny_mce_before_init`, `mce_buttons_2`, `mce_css` |
| Block Editor / Site Editor | Active | `wp_theme_json_data_theme` (appends; theme fonts kept) |
| Elementor | Active | `elementor/fonts/additional_fonts`, `elementor/fonts/groups`, `elementor/fonts/print_font_links/ucf` |
| Astra | Active | `astra_system_fonts`, `astra_render_fonts` |
| Kadence | Active | `kadence_theme_add_custom_fonts` |
| GeneratePress 3 | Limited | no font-list hook; fonts named in its Font Manager are loaded |
| WordPress admin area | Optional | Settings → WordPress admin; one inline rule on `admin_enqueue_scripts`, icons and code excluded, no `!important` |
| Divi, WoodMart | Not integrated | removed in 1.2.0; both include their own custom font upload |
| Other themes | Unsupported | reported honestly; fonts still work in the editors above |

Every hook above was checked against the real source of the named versions.

## How loading works

1. Each adapter tells the loader which fonts a page uses: Elementor and Astra report them exactly; the Classic Editor, Block Editor and Customizer adapters look for the family (or block slug) in the content and saved settings.
2. The loader prints one inline `<style>` with only those `@font-face` rules (WOFF2 first, `font-display: swap`) and, only when needed, one CDN stylesheet plus a preconnect.
3. Fonts discovered after `wp_head` (for example inside the content) are printed in the footer.

A page that uses no custom font gets **no** plugin output. WordPress core still defines tiny CSS variables for Block Editor presets (`--wp--preset--font-family--ucf-*`).

## Security

- `manage_options` capability and a nonce on every action (admin-post handlers).
- Uploads: extension allowlist, real format detected from magic bytes (WOFF2/WOFF/TTF/OTF), `finfo` check, size limit, `wp_handle_upload()`; disguised PHP files are rejected.
- Files live in `uploads/universal-custom-fonts/` with `.htaccess` and `index.php`; only relative paths are stored.
- Family names are validated (no CSS injection), all output is escaped, remote URLs must be HTTPS.

## Privacy (GDPR)

CDN fonts expose visitor IPs to the CDN (see LG München I, 3 O 17493/20, 20 January 2022). Choose Bunny Fonts, or better, **Host on this server**: the plugin downloads the files once, keeps the `unicode-range` subsets and serves them locally. Not legal advice.

## Known limitations

- The admin area font does not use `!important`, so another plugin's more specific CSS can keep its own font in places. The login page, the Customizer and the front-end toolbar are not changed.
- For variable Google Fonts, two or more weights download the same file as the full range.
- The WordPress 6.5+ Font Library (Site Editor) is separate; fonts added there are not managed by this plugin.

## Development

```bash
phpcs --standard=phpcs.xml .   # WordPress-Extra, WordPress-Docs, PHPCompatibilityWP (8.1+)
```

See [docs/DEVELOPER.md](docs/DEVELOPER.md) and [docs/TESTING.md](docs/TESTING.md).
