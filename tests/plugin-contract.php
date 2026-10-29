<?php
/** Focused contract checks with WordPress API doubles; no site or database writes. */
define( 'ABSPATH', __DIR__ );
define( 'HOUR_IN_SECONDS', 3600 );
$saved = array();
$hooks = array();
$errors = array();
$can_manage = true;
$registered = array();

function get_current_user_id() { return 1; }
function home_url( $path = '' ) { return 'https://example.test/' . ltrim( $path, '/' ); }
function get_transient( $key ) { return false; }
function wp_nonce_field( $action ) { echo '<input type="hidden" name="_wpnonce" value="test-double">'; }
function get_option( $name, $default = false ) { global $saved; return $saved[ $name ] ?? $default; }
function add_action( $hook, $callback, $priority = 10 ) { global $hooks; $hooks[ $hook ][ $priority ][] = $callback; }
function add_filter( $hook, $callback ) { add_action( $hook, $callback ); }
function plugin_basename( $file ) { return basename( dirname( $file ) ) . '/' . basename( $file ); }
function is_admin() { return false; }
function current_user_can( $cap ) { global $can_manage; return $can_manage; }
function __( $s, $domain = '' ) { return $s; }
function wp_json_encode( $value ) { return json_encode( $value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); }
function esc_html__( $s, $domain = '' ) { return htmlspecialchars( $s, ENT_QUOTES ); }
function esc_url( $s ) { return htmlspecialchars( $s, ENT_QUOTES ); }
function esc_html( $s ) { return htmlspecialchars( $s, ENT_QUOTES ); }
function esc_attr( $s ) { return htmlspecialchars( $s, ENT_QUOTES ); }
function admin_url( $s ) { return 'https://example.test/wp-admin/' . $s; }
function add_settings_error( $option, $code, $message ) { global $errors; $errors[] = $code; }
function register_setting( $group, $option, $args ) { global $registered; $registered[ $option ] = $args; }
function add_options_page( ...$args ) { return true; }
function wp_die( $message ) { throw new RuntimeException( $message ); }
function settings_fields( $group ) { echo '<input type="hidden" name="_wpnonce" value="test-double">'; }
function checked( $value ) { if ( $value ) { echo 'checked="checked"'; } }
function submit_button() { echo '<button type="submit">Save Changes</button>'; }

require dirname( __DIR__ ) . '/cookieyes-integration.php';
use Amsive\CookieYes\Settings;
use Amsive\CookieYes\Plugin;

$checks = 0;
function check( $ok, $message ) {
    global $checks;
    ++$checks;
    if ( ! $ok ) { throw new RuntimeException( $message ); }
}
function output( $callback ) { ob_start(); $callback(); return ob_get_clean(); }
function store( $value ) { global $saved; $saved[ Settings::OPTION ] = $value; }

$settings = new Settings();
$key = '5cfe4f28d3b2e213a2c75d46'; // Public documentation example, never used as a site installation.
check( isset( $hooks['wp_head'][0] ), 'Early frontend hook is missing.' );
check( ! Settings::get()['enabled'], 'Fresh installation must be disabled.' );
check( '' === output( array( new Plugin(), 'render_script' ) ), 'Empty configuration emitted a script.' );
$good = array( 'website_key' => $key, 'enabled' => true );
store( $good );
foreach ( array( '<script>alert(1)</script>', '../escape', 'https://evil.test/key', 'abc?x=y', '" onload="bad', "abc\n12345678", array(), null, 12 ) as $bad ) {
    $errors = array();
    check( $good === $settings->sanitize( array( 'website_key' => $bad, 'enabled' => '1' ) ), 'Malformed key replaced valid configuration.' );
    check( count( $errors ) > 0, 'Rejected settings need actionable feedback.' );
}
foreach ( array( null, 'bad', false, array(), array( 'enabled' => '1' ) ) as $bad ) {
    check( $good === $settings->sanitize( $bad ), 'Malformed settings replaced valid configuration.' );
}
check( $good === $settings->sanitize( array( 'website_key' => '  ' . $key . '  ', 'enabled' => '1' ) ), 'A copied key should trim surrounding whitespace.' );
check( $good === $settings->sanitize( array( 'website_key' => '', 'enabled' => '1' ) ), 'Enabling without a key erased valid settings.' );
$disabled_invalid = $settings->sanitize( array( 'website_key' => '<invalid>' ) );
check( $disabled_invalid === array( 'website_key' => $key, 'enabled' => false ), 'Invalid edited key must not prevent disabling.' );
check( $disabled_invalid === $settings->sanitize( $disabled_invalid ), 'Disabled sanitized settings must be idempotent on first WordPress save.' );
check( $good === $settings->sanitize( $good ), 'Enabled sanitized settings must be idempotent on first WordPress save.' );
$off = $settings->sanitize( array( 'website_key' => $key ) );
check( ! $off['enabled'] && $off['website_key'] === $key, 'Unchecking enable must retain the key.' );
store( $off );
check( '' === output( array( new Plugin(), 'render_script' ) ), 'Disabled installation emitted a script.' );
check( array( 'website_key' => '', 'enabled' => false ) === $settings->sanitize( array( 'website_key' => '' ) ), 'Disabled configuration should allow clearing its key.' );
store( $good );
$plugin = new Plugin();
$html = output( array( $plugin, 'render_script' ) );
check( 1 === substr_count( $html, '<script ' ) && false !== strpos( $html, 'https://cdn-cookieyes.com/client_data/' . $key . '/script.js' ), 'Expected one vendor installation tag.' );
check( false === strpos( $html, 'async' ) && false === strpos( $html, 'defer' ), 'The blocking installation tag was changed.' );
check( '' === output( array( $plugin, 'render_script' ) ), 'Repeated hook invocation duplicated the tag.' );
foreach ( array( 'bad', array( 'website_key' => array(), 'enabled' => '1' ), array( 'website_key' => '../bad', 'enabled' => true ), array( 'website_key' => $key, 'enabled' => 'yes' ) ) as $bad ) {
    store( $bad );
    check( '' === output( array( new Plugin(), 'render_script' ) ), 'Corrupt stored options must not emit a script.' );
}
store( $good );
$settings->register();
check( false === $registered[ Settings::OPTION ]['show_in_rest'], 'Settings should not be exposed through the REST API.' );
check( is_callable( $registered[ Settings::OPTION ]['sanitize_callback'] ), 'Settings sanitizer must be registered.' );
$can_manage = false;
try { output( array( $settings, 'page' ) ); check( false, 'Unauthorized admin page was rendered.' ); }
catch ( RuntimeException $e ) { if ( ob_get_level() ) { ob_end_clean(); } check( false !== strpos( $e->getMessage(), 'permission' ), 'Unexpected access failure.' ); }
check( array() === $plugin->action_links( array() ), 'Unauthorized settings link was shown.' );
$can_manage = true;
$admin = output( array( $settings, 'page' ) );
check( false !== strpos( $admin, 'name="_wpnonce"' ) && false !== strpos( $admin, 'action="options.php"' ), 'Admin form must use WordPress Settings API.' );
check( false !== strpos( $admin, 'value="' . $key . '"' ), 'Saved key is missing from the settings form.' );
check( false !== strpos( $admin, 'v' . COOKIEYES_INTEGRATION_VERSION . ' · by Amsive</small>' ), 'Version label missing.' );
echo "PASS: {$checks} isolated contract checks. WordPress API doubles; no browser or real options.php execution.\n";
