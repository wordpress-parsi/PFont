# Developer guide

## Architecture

```
universal-custom-fonts.php      bootstrap, requirement check, constants
uninstall.php                   opt-in cleanup (multisite aware)
includes/
  Autoloader.php                PSR-4 for UniversalCustomFonts\ → includes/
  Plugin.php                    boots settings, migrations, adapters, admin
  Core/                         Font (value object), FontRepository (ucf_fonts option),
                                FontRegistry (enabled fonts, per-request cache), FontValidator,
                                FontLoader (per-request queue), Settings, Migrations
  Fonts/                        CdnFonts (presets, Google/Bunny CSS2 URLs), FontFaceGenerator,
                                FontStorage, UploadedFonts, GoogleFontsDownloader
  Integrations/                 AbstractAdapter + one adapter per platform, IntegrationManager,
                                IntegrationDetector, Customizer/ theme providers
  Admin/                        AdminPage, Actions, FontList, FontForm, IntegrationsView,
                                DebugPanel, Assets
  Helpers/FontHelper.php        name/weight utilities
```

## Data model (`ucf_fonts` option)

```php
'vazirmatn-local' => array(
    'id'           => 'vazirmatn-local',
    'name'         => 'Vazirmatn Local',          // label in dropdowns
    'family'       => 'Vazirmatn Local',          // exact CSS family
    'fallback'     => 'sans-serif',
    'source'       => 'upload',                   // upload | cdn
    'preset'       => '',                         // preset ID for CDN presets
    'cdn_url'      => '',                         // optional custom https stylesheet
    'hosting'      => 'remote',                   // remote | local (self-hosted CDN font)
    'weights'      => array( 400, 700 ),
    'styles'       => array( 'normal' ),
    'display'      => 'swap',
    'enabled'      => true,
    'integrations' => array( 'tinymce' => true, 'block_editor' => true, 'elementor' => true, 'astra' => true, 'customizer' => true ),
    'files'        => array( 400 => array( 'normal' => array( 'woff2' => 'uploads/vazirmatn-local/vazirmatn-local-400-normal.woff2' ) ) ),
    'local'        => array(),                    // self-hosted faces (dir, faces, downloaded)
)
```

Paths are relative to `wp-content/uploads/universal-custom-fonts/`.

## Hooks

| Hook | Type | Purpose |
|---|---|---|
| `pfont_loaded` | action | Plugin booted. |
| `pfont_registered_fonts` | filter | Font records before they become `Font` objects. Add fonts from code here. |
| `pfont_cdn_presets` | filter | Built-in CDN presets. |
| `pfont_integrations` | filter | Adapter instances (add your own). |
| `pfont_customizer_providers` | filter | Theme providers for the Customizer adapter. |
| `pfont_max_upload_size` | filter | Maximum bytes per uploaded file (default 10 MB, capped by the server limit). |
| `pfont_fonts_changed` | action | The library was saved or a font deleted. |
| `pfont_admin_font` | filter | The `Font` used for the admin area, or `null` for the WordPress default. |
| `pfont_admin_font_keep` | filter | Selectors that keep their own font in the admin area (icons, code). |

Until 1.3.1 these hooks started with `ucf_`. The old names still fire through `apply_filters_deprecated()` / `do_action_deprecated()`.

### Add a font from code

```php
add_filter( 'pfont_registered_fonts', function ( array $fonts ): array {
    $fonts['brand-sans'] = array(
        'id'      => 'brand-sans',
        'name'    => 'Brand Sans',
        'family'  => 'Brand Sans',
        'source'  => 'cdn',
        'cdn_url' => 'https://cdn.example.com/brand-sans.css',
        'weights' => array( 400, 700 ),
    );
    return $fonts;
} );
```

### Add an integration

```php
use UniversalCustomFonts\Integrations\AbstractAdapter;

final class My_Builder_Adapter extends AbstractAdapter {
    public function id(): string { return 'customizer'; }   // reuse an existing toggle key
    public function label(): string { return 'My Builder'; }
    public function is_available(): bool { return defined( 'MY_BUILDER_VERSION' ); }
    public function register(): void {
        $this->hook( 'filter', 'my_builder_fonts', array( $this, 'add' ) );  // hooks are listed in Debug
    }
    public function add( $fonts ) {
        foreach ( $this->fonts() as $font ) {
            $fonts[ $font->family() ] = $font->name();
        }
        return $fonts;
    }
    public function enqueue_frontend(): void {
        $this->enqueue_mentioned( self::queried_content() );  // or enqueue_all()
    }
}
add_filter( 'pfont_integrations', function ( array $adapters ): array {
    $adapters[] = new My_Builder_Adapter();
    return $adapters;
} );
```

Useful helpers on `AbstractAdapter`: `fonts()`, `enqueue_all( bool $local_only = false )`, `enqueue_mentioned( string $haystack, bool $include_local = false )`, `queried_content()`, `status()`, `native_conflict( Font $font )`.

### Add a Customizer provider

Extend `Integrations\Customizer\ThemeProvider` (`label()`, `detect()`, optional `register()`, `haystack()`, `status()`) and return it from `pfont_customizer_providers`.

## Loader API

- `FontLoader::enqueue( string $font_id, array $weights = array() )` — queue a font for this request.
- `FontLoader::enqueue_for_editor( array $ids, bool $include_remote = false )` — editor/admin previews.
- `FontLoader::get_css( string $font_id )` — generated `@font-face` CSS.

## Coding standards

`phpcs --standard=phpcs.xml.dist .` must report no violations (WordPress-Extra, WordPress-Docs, PHPCompatibilityWP 8.1+).
