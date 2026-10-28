<?php
/** Updater tests: HTTP/cache doubles, no actual GitHub or WordPress writes. */
define( 'ABSPATH', __DIR__ );
define( 'COOKIEYES_INTEGRATION_FILE', dirname( __DIR__ ) . '/cookieyes-integration.php' );
define( 'COOKIEYES_INTEGRATION_VERSION', '0.2.0' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );
$cache = false;
$requests = 0;
$cleared = false;
$response = array( 'code' => 200, 'body' => '' );
function plugin_basename( $p ) { return 'cookieyes-integration/' . basename( $p ); }
function add_filter( ...$args ) {}
function add_action( ...$args ) {}
function get_transient( $key ) { global $cache; return $cache; }
function set_transient( $key, $value, $ttl ) { global $cache; $cache = $value; }
function delete_transient( $key ) { global $cache; $cache = false; }
function delete_site_transient( $key ) { global $cleared; $cleared = true; }
function wp_safe_remote_get( $url, $args ) { global $response, $requests; ++$requests; return $response; }
function is_wp_error( $r ) { return $r instanceof Exception; }
function wp_remote_retrieve_response_code( $r ) { return $r['code']; }
function wp_remote_retrieve_body( $r ) { return $r['body']; }
function sanitize_text_field( $s ) { return strip_tags( $s ); }
function esc_html( $s ) { return htmlspecialchars( $s, ENT_QUOTES ); }
function esc_html__( $s, $domain ) { return esc_html( $s ); }
require dirname( __DIR__ ) . '/includes/class-updater.php';
use Amsive\CookieYes\Updater;
$checks = 0;
function verify( $ok, $message ) { global $checks; ++$checks; if ( ! $ok ) { throw new RuntimeException( $message ); } }
$tag = 'v0.3.0';
$base = 'https://github.com/Amsive-Digital/wp-cookieyes-integration/releases/';
$good = array(
    'draft' => false, 'prerelease' => false, 'tag_name' => $tag, 'html_url' => $base . 'tag/' . $tag,
    'published_at' => '2026-10-28T20:00:00Z', 'body' => '<script>bad()</script>',
    'assets' => array( array( 'name' => 'cookieyes-integration-' . $tag . '.zip', 'browser_download_url' => $base . 'download/' . $tag . '/cookieyes-integration-' . $tag . '.zip' ) ),
);
verify( Updater::normalize( $good )['version'] === '0.3.0', 'Stable release rejected.' );
foreach ( array( array( 'draft', true ), array( 'prerelease', true ), array( 'draft', 'false' ), array( 'tag_name', 'v0.3.0-beta' ), array( 'tag_name', 'v00.3.0' ), array( 'tag_name', array() ), array( 'html_url', 'https://evil.test/release' ), array( 'assets', null ), array( 'assets', array() ) ) as $change ) {
    $bad = $good; $bad[ $change[0] ] = $change[1];
    verify( array() === Updater::normalize( $bad ), 'Unsafe release accepted.' );
}
foreach ( array( '?extra=1', '#fragment', '/../wrong.zip', '@evil.test' ) as $suffix ) {
    $bad = $good; $bad['assets'][0]['browser_download_url'] .= $suffix;
    verify( array() === Updater::normalize( $bad ), 'Non-exact package URL accepted.' );
}
$response['body'] = json_encode( $good );
$file = 'cookieyes-integration/cookieyes-integration.php';
$data = array( 'UpdateURI' => Updater::URI );
verify( 'keep' === Updater::update( 'keep', $data, 'other/other.php', array() ), 'Another plugin update was modified.' );
verify( 0 === $requests, 'Unrelated plugin triggered a network request.' );
$update = Updater::update( false, $data, $file, array() );
verify( $update['version'] === '0.3.0' && $update['slug'] === 'cookieyes-integration', 'Native update payload invalid.' );
verify( ! isset( $update['autoupdate'] ) && ! isset( $update['disable_autoupdate'] ), 'Admin auto-update choice must not be forced.' );
Updater::update( false, $data, $file, array() );
verify( 1 === $requests, 'Release cache was not reused.' );
$info = Updater::information( false, 'plugin_information', (object) array( 'slug' => 'cookieyes-integration' ) );
verify( is_array( $info->sections ) && $info->download_link === $update['package'], 'Plugin information payload invalid.' );
verify( false === strpos( $info->sections['changelog'], '<script>' ), 'Release notes were not escaped.' );
verify( 'keep' === Updater::information( 'keep', 'other', (object) array() ), 'Unrelated information call modified.' );
Updater::after_upgrade( null, array( 'type' => 'plugin', 'action' => 'update', 'plugin' => 'other/other.php' ) );
verify( false !== $cache, 'Unrelated upgrade cleared cache.' );
Updater::after_upgrade( null, array( 'type' => 'plugin', 'action' => 'update', 'plugin' => $file ) );
verify( false === $cache && $cleared, 'Single update did not clear caches.' );
$cache = Updater::normalize( $good );
Updater::after_upgrade( null, array( 'type' => 'plugin', 'action' => 'update', 'plugins' => array( $file ) ) );
verify( false === $cache, 'Bulk update did not clear cache.' );
$response = new Exception( 'Offline' );
verify( false === Updater::update( false, $data, $file, array() ), 'Offline request offered an update.' );
$cache = array( 'version' => '0.3.0', 'url' => $base . 'tag/' . $tag, 'package' => 'https://evil.test/a.zip' );
verify( false === Updater::update( false, $data, $file, array() ), 'Poisoned cached URL was accepted.' );
echo "PASS: {$checks} updater contract checks (HTTP and WordPress doubles).\n";
