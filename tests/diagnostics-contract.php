<?php
/** Parser, fetch, authorization and report checks with isolated WordPress doubles. */
define( 'ABSPATH', __DIR__ );
define( 'COOKIEYES_INTEGRATION_VERSION', 'test' );
define( 'HOUR_IN_SECONDS', 3600 );
$settings = array( 'website_key' => 'example1234', 'enabled' => true );
$transients = array();
$allowed = true;
$nonce_ok = true;
$requests = array();
$http = array( 'response' => array( 'code' => 200 ), 'headers' => array( 'content-type' => 'text/html; charset=UTF-8' ), 'body' => '' );
function __( $s, $d = '' ) { return $s; }
function wp_json_encode( $value ) { return json_encode( $value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); }
function esc_html__( $s, $d = '' ) { return htmlspecialchars( $s, ENT_QUOTES ); }
function esc_html( $s ) { return htmlspecialchars( $s, ENT_QUOTES ); }
function esc_attr( $s ) { return htmlspecialchars( $s, ENT_QUOTES ); }
function esc_url( $s ) { return htmlspecialchars( $s, ENT_QUOTES ); }
function get_option( $key, $default = false ) { global $settings; return $settings; }
function home_url( $path ) { return 'https://example.test/'; }
function admin_url( $path ) { return 'https://example.test/wp-admin/' . $path; }
function get_current_user_id() { return 1; }
function current_user_can( $cap ) { global $allowed; return $allowed; }
function wp_unslash( $text ) { return stripslashes( $text ); }
function check_ajax_referer( $action ) { check_admin_referer( $action ); }
function wp_send_json_error( $message, $status ) { throw new RuntimeException( 'ajax-error-' . $status ); }
function wp_send_json_success( $data = null ) { global $ajax_data; $ajax_data = $data; throw new RuntimeException( 'ajax-success' ); }
function check_admin_referer( $action ) { global $nonce_ok; if ( ! $nonce_ok ) { throw new RuntimeException( 'Invalid nonce' ); } }
function wp_nonce_field( $action ) { echo '<input type="hidden" name="_wpnonce" value="test">'; }
function get_transient( $key ) { global $transients; return $transients[$key] ?? false; }
function set_transient( $key, $data, $ttl ) { global $transients; $transients[$key] = $data; }
function wp_date( $format, $time ) { return gmdate( $format, $time ); }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function wp_remote_get( $url, $args ) { global $http, $requests; $requests[] = array( $url, $args ); return $http; }
function wp_remote_retrieve_response_code( $r ) { return $r['response']['code']; }
function wp_remote_retrieve_body( $r ) { return $r['body']; }
function wp_remote_retrieve_header( $r, $name ) { return $r['headers'][$name] ?? ''; }
function is_wp_error( $r ) { return $r instanceof WP_Error; }
class WP_Error { public function get_error_message() { return '<script>untrusted</script>'; } }
require dirname( __DIR__ ) . '/includes/class-settings.php';
require dirname( __DIR__ ) . '/includes/class-diagnostics.php';
use Amsive\CookieYes\Diagnostics;
$checks = 0;
function check( $condition, $message ) { global $checks; ++$checks; if ( ! $condition ) { throw new RuntimeException( $message ); } }
function page( $head, $body = '<p>Content</p>' ) { return '<!doctype html><html><head>' . $head . '</head><body>' . $body . '</body></html>'; }
function findings( $head, $body = '<p>Content</p>' ) { global $settings; $items = Diagnostics::analyze( page( $head, $body ), $settings ); $out = array(); foreach ( $items as $item ) { $out[$item['title']] = $item['status']; } return $out; }
function detail( $head, $title ) { global $settings; foreach ( Diagnostics::analyze( page( $head ), $settings ) as $item ) { if ( $item['title'] === $title ) { return $item['detail']; } } throw new RuntimeException( 'Missing finding: ' . $title ); }
function render() { ob_start(); try { Diagnostics::prepare(); Diagnostics::render(); return ob_get_contents(); } finally { ob_end_clean(); } }
$cmp = '<script id="cookieyes" src="https://cdn-cookieyes.com/client_data/example1234/script.js"></script>';
$gtm = '<script src="https://www.googletagmanager.com/gtm.js?id=GTM-EXAMPLE"></script>';
$f = findings( $cmp . $gtm );
check( 'pass' === $f['Google script order'], 'Correct order should pass the static check.' );
check( ! in_array( 'problem', $f, true ), 'Correct sample reported an error.' );
check( 'pass' === $f['Cache and JavaScript optimization'], 'Original CDN tag should have no optimization finding.' );
check( 'pass' === findings( str_replace( '<script ', '<script data-no-minify="1" data-minify="0" data-nowprocket ', $cmp ) )['Cache and JavaScript optimization'], 'Exclusion attributes must not be mistaken for an optimization.' );
$cached = '<script data-minify="1" id="cookieyes" type="text/javascript" src="https://example.test/wp-content/cache/min/1/client_data/example1234/script.js?ver=1234567890"></script>';
$cached_findings = findings( $cached . $gtm );
check( 'problem' === $cached_findings['Cache and JavaScript optimization'], 'WP Rocket local minification was missed.' );
check( 'unknown' === $cached_findings['Website key'], 'A matching cache path must not be reported as a wrong key or a verified CDN source.' );
check( 'pass' === $cached_findings['Loading attributes and placement'], 'URL rewriting alone must not imply unsafe loading attributes.' );
check( 'Cache and JavaScript optimization' === Diagnostics::summary( array( 'items' => Diagnostics::analyze( page( $cached . $gtm ), $settings ) ) )['title'], 'Optimization should be the main actionable problem for a rewritten tag.' );
$optimization = detail( $cached, 'Cache and JavaScript optimization' );
foreach ( array( 'WP Rocket → File Optimization → JavaScript → Excluded JavaScript Files (minification/combination)', 'cdn-cookieyes.com', 'Clearing caches alone recreates', 'Save changes', 'WP Engine', 'rerun Check integration' ) as $instruction ) {
    check( false !== strpos( $optimization, $instruction ), 'Missing optimization remedy: ' . $instruction );
}
check( false !== strpos( detail( $cached, 'Website key' ), 'matches the saved key' ), 'Cache-path key observation should be explained.' );
foreach ( array( str_replace( ' data-minify="1"', '', $cached ), str_replace( 'https://example.test', '', $cached ), str_replace( 'https://example.test', '//example.test', $cached ), str_replace( 'https://example.test', 'https://example.test:443', $cached ), str_replace( ' id="cookieyes"', '', $cached ) ) as $local_copy ) {
    check( 'problem' === findings( $local_copy )['Cache and JavaScript optimization'] && 'unknown' === findings( $local_copy )['Website key'], 'Local cache recognition must not depend on the id/marker or absolute URL.' );
}
$local_copy = str_replace( 'https://cdn-cookieyes.com', 'https://example.test', $cmp );
check( 'problem' === findings( $local_copy )['Cache and JavaScript optimization'] && 'unknown' === findings( $local_copy )['Website key'], 'Unminified local hosting must still be reported without claiming CDN verification.' );
$combined = str_replace( 'client_data/example1234/script.js', 'combined-example.js', $cached );
check( 'unknown' === findings( $combined )['Website key'] && 'problem' === findings( $combined )['Cache and JavaScript optimization'], 'An identified combined file must not invent a website key.' );
check( 'problem' === findings( str_replace( 'example1234', 'different123', $cached ) )['Website key'], 'A real key mismatch in a local cache URL must still fail.' );
foreach ( array( 'https://evil.test', 'https://example.test.evil.test', 'https://example.test:444', 'https://user@example.test', 'https://example.test@evil.test' ) as $host ) {
    check( 'problem' === findings( str_replace( 'https://example.test', $host, $cached ) )['Website key'], 'A cache marker/path must not make an untrusted origin acceptable: ' . $host );
}
foreach ( array( '//evil.test/wp-content/cache/min/1/client_data/example1234/script.js', '/\\evil.test/wp-content/cache/min/1/client_data/example1234/script.js', 'data:text/javascript,example1234' ) as $src ) {
    check( 'problem' === findings( '<script id="cookieyes" src="' . $src . '"></script>' )['Website key'], 'A non-local source must not be accepted as a relative cache URL.' );
}
$foreign_with_hint = str_replace( 'src="https://cdn-cookieyes.com', 'data-src="https://cdn-cookieyes.com', $cmp );
$foreign_with_hint = str_replace( '<script ', '<script src="https://evil.test/script.js" ', $foreign_with_hint );
check( 'problem' === findings( $foreign_with_hint )['Website key'], 'A delayed CDN hint must not validate an active foreign script URL.' );
$query_hint = '<script id="cookieyes" data-minify="1" src="https://evil.test/script.js?original=https://cdn-cookieyes.com/client_data/example1234/script.js"></script>';
check( 'problem' === findings( $query_hint )['Website key'], 'A vendor URL hidden in a foreign query must not validate the source.' );
$delayed = str_replace( ' src=', ' type="text/rocketlazyloadscript" data-rocket-src=', $cmp );
check( 'problem' === findings( $delayed )['Cache and JavaScript optimization'] && 'problem' === findings( $delayed )['Loading attributes and placement'], 'Current WP Rocket delayed-source markup was missed.' );
check( 'problem' === findings( str_replace( 'example1234', 'different123', $delayed ) )['Website key'], 'A real key mismatch must remain a problem with delayed loading.' );
check( false !== strpos( detail( $delayed, 'Cache and JavaScript optimization' ), 'Delay JavaScript execution → Excluded JavaScript Files' ), 'Delay-specific exclusion instructions are missing.' );
$deferred = str_replace( '<script ', '<script data-rocket-defer defer ', $cmp );
check( 'problem' === findings( $deferred )['Cache and JavaScript optimization'] && 'problem' === findings( $deferred )['Loading attributes and placement'], 'WP Rocket defer was missed.' );
check( false !== strpos( detail( $deferred, 'Cache and JavaScript optimization' ), 'Load JavaScript deferred → Excluded JavaScript Files' ), 'Defer-specific exclusion instructions are missing.' );
$cached_delayed = str_replace( ' src=', ' data-rocket-src=', str_replace( 'type="text/javascript"', 'type="text/rocketlazyloadscript"', $cached ) );
check( 'unknown' === findings( $cached_delayed )['Website key'] && 'problem' === findings( $cached_delayed )['Loading attributes and placement'], 'Cached and delayed scripts must retain both findings.' );
check( false !== strpos( detail( $cached_delayed, 'Cache and JavaScript optimization' ), 'domain-based delay/defer exclusions do not match a locally hosted URL' ), 'Combined minification/delay must explain why the original CDN domain needs restoring.' );
check( 'problem' === findings( $cached . $cmp )['CookieYes installation'] && 'problem' === findings( $cached . $cmp )['Cache and JavaScript optimization'], 'Optimization and duplicate installation findings must coexist.' );
check( 'unknown' === findings( '' )['Cache and JavaScript optimization'], 'A missing tag cannot pass optimization checks.' );
$relative_copy = str_replace( 'https://example.test', '', $cached );
foreach ( array( 'https://untrusted.example/', '//untrusted.example/', 'https://example.test:444/' ) as $base ) {
    check( 'problem' === findings( '<base href="' . $base . '">' . $relative_copy )['Website key'], 'A base href must not disguise an external relative script as a local copy.' );
    check( 'unknown' === findings( '<base href="' . $base . '">' . $cached )['Website key'], 'An absolute local URL must be unaffected by a base href.' );
}
foreach ( array( '', '/assets/', 'assets/', 'https://example.test/assets/' ) as $base ) {
    check( 'unknown' === findings( '<base href="' . $base . '">' . $relative_copy )['Website key'], 'A same-origin or empty base must preserve local-copy detection.' );
}
check( 'unknown' === findings( '<base target="_blank"><base href="/"><base href="https://untrusted.example/">' . $relative_copy )['Website key'], 'Only the first base with href controls URL resolution.' );
check( 'unknown' === findings( '<template><base href="https://untrusted.example/"></template>' . $relative_copy )['Website key'], 'An inert base href must not control script origins.' );
$relative_vendor = '<base href="https://cdn-cookieyes.com/client_data/example1234/"><script id="cookieyes" src="script.js"></script>';
check( 'pass' === findings( $relative_vendor )['Website key'] && 'pass' === findings( $relative_vendor )['Loading attributes and placement'], 'A relative source must resolve against its CDN base URL.' );
check( 'problem' === findings( str_replace( 'example1234', 'different123', $relative_vendor ) )['Website key'], 'A mismatched key from a resolved CDN URL must still fail.' );
$http_base = '<base href="http://example.test/">' . $relative_copy;
check( 'problem' === findings( $http_base )['Loading attributes and placement'] && false !== strpos( detail( $http_base, 'Loading attributes and placement' ), 'mixed content' ), 'An HTTP base must expose mixed-content loading on an HTTPS page.' );
check( 'problem' === findings( '<base href="http://example.test/">' . str_replace( 'https://cdn-cookieyes.com', '//cdn-cookieyes.com', $cmp ) )['Loading attributes and placement'], 'Protocol-relative sources inherit the base scheme, not the page scheme.' );
check( 'problem' === findings( str_replace( 'https://example.test', 'http://example.test', $cached ) )['Loading attributes and placement'], 'An explicit HTTP local cache copy must still fail mixed-content checks.' );
foreach ( array( '<base href="https://untrusted.example\\path/">', '<base href="https://untrusted.example/">' ) as $base ) {
    check( 'problem' === findings( $base . $relative_copy )['Website key'], 'An unsafe or foreign base must never validate a relative local key.' );
}
foreach ( array( str_replace( '<script ', '<script defer ', $cmp ), str_replace( ' src=', ' type="litespeed/javascript" data-src=', $cmp ), str_replace( ' src=', ' type="pmdelayedscript" data-src=', $cmp ), $local_copy ) as $non_rocket ) {
    $instructions = detail( $non_rocket, 'Cache and JavaScript optimization' );
    check( false !== strpos( $instructions, 'If WP Rocket is used' ) && false === strpos( $instructions, 'In WP Rocket →' ), 'Non-Rocket sources must get conditional WP Rocket instructions.' );
    check( false === strpos( $instructions, 'Clearing caches alone recreates' ), 'A manual copy or non-minification marker must not imply cache regeneration.' );
}
$external_copy = str_replace( 'https://example.test', 'https://static.example-cdn.test', $cached );
check( 'problem' === findings( $external_copy )['Website key'] && 'problem' === findings( $external_copy )['Cache and JavaScript optimization'], 'An optimized external CDN must remain an untrusted source.' );
check( 'pass' === findings( $external_copy )['Loading attributes and placement'], 'Source trust must not imply wrong loading attributes or placement.' );
check( 'problem' === findings( str_replace( '<script ', '<script defer ', $external_copy ) )['Loading attributes and placement'], 'An external copy with actual defer must still fail loading checks.' );
check( 'problem' === findings( '', $external_copy )['Loading attributes and placement'], 'An external copy outside head must still fail placement checks.' );
$hidden_combined = '<script data-minify="1" src="/wp-content/cache/min/1/combined-example.js"></script>';
check( 'problem' === findings( $hidden_combined )['CookieYes installation'] && 'unknown' === findings( $hidden_combined )['Cache and JavaScript optimization'], 'A combined file cannot prove a CookieYes installation.' );
check( false !== strpos( detail( $hidden_combined, 'CookieYes installation' ), 'Combination may have hidden' ) && false !== strpos( detail( $hidden_combined, 'CookieYes installation' ), 'If WP Rocket is used' ), 'The first missing-tag finding must explain possible combination and exclusions.' );
check( false !== strpos( detail( $hidden_combined, 'Cache and JavaScript optimization' ), 'does not prove CookieYes is inside' ), 'A combined-file hint must state the limit of the evidence.' );
foreach ( array( '<!--' . $hidden_combined . '-->', '<template>' . $hidden_combined . '</template>', str_replace( '<script ', '<script type="application/ld+json" ', $hidden_combined ) ) as $inert_combined ) {
    check( false === strpos( detail( $inert_combined, 'CookieYes installation' ), 'optimized JavaScript is present' ), 'An inert or commented optimizer marker must not affect missing-tag guidance.' );
}
check( 'problem' === findings( $gtm . $cmp )['Google script order'], 'Early GTM was missed.' );
check( 'problem' === findings( $gtm )['CookieYes installation'], 'Missing CMP was missed.' );
check( 'problem' === findings( $cmp . $cmp )['CookieYes installation'], 'Duplicate CMP was missed.' );
check( 'problem' === findings( str_replace( 'example1234', 'different123', $cmp ) )['Website key'], 'Wrong key was missed.' );
check( 'problem' === findings( str_replace( 'https://cdn-cookieyes.com', 'https://evil.test', $cmp ) )['Website key'], 'Foreign host accepted.' );
foreach ( array( 'async', 'defer', 'nomodule', 'type="module"', 'type="text/plain"', 'type="rocketlazyloadscript"', 'data-cfasync="true"' ) as $attr ) {
    check( 'problem' === findings( str_replace( '<script ', '<script ' . $attr . ' ', $cmp ) . $gtm )['Loading attributes and placement'], 'Unsafe CMP loading missed: ' . $attr );
}
$http_cmp = str_replace( 'https://cdn-cookieyes.com', 'http://cdn-cookieyes.com', $cmp );
check( 'problem' === findings( $http_cmp . $gtm )['Loading attributes and placement'], 'HTTP CMP on HTTPS must not pass as loadable.' );
check( 'pass' === findings( str_replace( 'https://cdn-cookieyes.com', '//cdn-cookieyes.com', $cmp ) . $gtm )['Loading attributes and placement'], 'Protocol-relative CMP inherits HTTPS and should pass.' );
$http_page_items = Diagnostics::analyze( page( $http_cmp . $gtm ), $settings, 'http://example.test/' );
$http_page_status = array_column( $http_page_items, 'status', 'title' );
check( 'pass' === $http_page_status['Loading attributes and placement'], 'HTTP script on HTTP page must not be incorrectly flagged as mixed content.' );
$public_login = findings( $cmp . $gtm, '<main><h1>Public homepage</h1><form id="loginform" action="/wp-login.php"><input name="log"></form></main>' );
check( isset( $public_login['CookieYes installation'] ) && 'pass' === $public_login['CookieYes installation'], 'Public login widget must not prevent integration checks.' );
$public_password = findings( $cmp . $gtm, '<main><h1>Public homepage</h1><form class="post-password-form" action="/wp-login.php?action=postpass"></form></main>' );
check( isset( $public_password['CookieYes installation'] ) && 'pass' === $public_password['CookieYes installation'], 'An embedded password form must not reject the entire homepage.' );
check( 'pass' === findings( str_replace( '<script ', '<script data-cfasync="false" ', $cmp ) . $gtm )['Loading attributes and placement'], 'Rocket Loader exclusion should be accepted.' );
check( 'problem' === findings( str_replace( ' src=', ' data-src=', $cmp ) )['Loading attributes and placement'], 'Delayed source missed.' );
check( 'problem' === findings( '', $cmp . $gtm )['Loading attributes and placement'], 'CMP outside head missed.' );
foreach ( array( '<!--' . $gtm . '-->', '<noscript>' . $gtm . '</noscript>', '<template>' . $gtm . '</template>', '<script type="text/plain" src="https://www.googletagmanager.com/gtm.js"></script>', '<script type="application/ld+json">{"url":"https://www.googletagmanager.com/gtm.js"}</script>' ) as $inert ) {
    check( 'pass' === findings( $inert . $cmp . $gtm )['Google script order'], 'Inert or commented content counted as early GTM.' );
}
check( 'unknown' === findings( $cmp )['Google script order'], 'Absent tracker must be unverified.' );
check( 'unknown' === findings( '<script type="rocketlazyloadscript" data-src="https://www.googletagmanager.com/gtm.js"></script>' . $cmp . $gtm )['Google script order'], 'Delayed Google loader must make order unverified.' );
check( 'problem' === findings( $cmp . '<script src="https://consent.cookiebot.com/uc.js"></script>' )['Legacy Cookiebot script'], 'Legacy CMP missed.' );
check( 'pass' === findings( $cmp . '<!--<script src="https://consent.cookiebot.com/uc.js"></script>-->' )['Legacy Cookiebot script'], 'Commented CMP must be ignored.' );
$inline = '<script>(function(){var x=document.createElement("script");x.src="https://www.googletagmanager.com/gtm.js";var y="gtm.start";})();</script>';
check( 'problem' === findings( $inline . $cmp )['Google script order'], 'Inline GTM loader missed.' );
check( 'problem' === findings( '<script>gtag("config", "G-EXAMPLE");</script>' . $cmp )['Google script order'], 'Early gtag config missed.' );
check( 'unknown' === Diagnostics::analyze( '', $settings )[0]['status'], 'Empty HTML must not pass.' );
check( 'unknown' === Diagnostics::analyze( '<!doctype html><html><head><title>Log In</title></head><body class="login login-action-login"><div id="login"><form id="loginform" action="/wp-login.php"></form></div></body></html>', $settings )[0]['status'], 'Login response must be unverified.' );
$http['body'] = page( $cmp . $gtm );
$report = Diagnostics::run();
check( 'pass' === $report['items'][0]['status'], 'Fetch did not analyze valid HTML.' );
check( 'https://example.test/' === $requests[0][0] && 0 === $requests[0][1]['redirection'] && array() === $requests[0][1]['cookies'], 'Fetch must use fixed homepage without redirects/admin cookies.' );
check( ! isset( $requests[0][1]['sslverify'] ) || $requests[0][1]['sslverify'], 'TLS must remain enabled.' );
foreach ( array( 301, 401, 500 ) as $code ) { $http['response']['code'] = $code; check( 'unknown' === Diagnostics::run()['items'][0]['status'], 'HTTP failure must not pass.' ); }
$http['response']['code'] = 200;
$http['headers']['content-type'] = 'application/json';
check( 'unknown' === Diagnostics::run()['items'][0]['status'], 'JSON must not pass.' );
$http['headers']['content-type'] = 'text/html';
$http['body'] = str_repeat( 'x', Diagnostics::LIMIT + 1 );
check( 'unknown' === Diagnostics::run()['items'][0]['status'], 'Oversize response must not pass.' );
$http = new WP_Error();
check( 'unknown' === Diagnostics::run()['items'][0]['status'], 'Fetch errors must not pass.' );
$_POST = array( 'amsive_cookieyes_check' => '1' );
$allowed = false;
$before = count( $requests );
check( '' === render() && $before === count( $requests ), 'Unauthorized check executed.' );
$allowed = true; $nonce_ok = false;
try { render(); check( false, 'Bad nonce accepted.' ); } catch ( RuntimeException $e ) { check( 'Invalid nonce' === $e->getMessage() && $before === count( $requests ), 'Nonce failure must precede HTTP.' ); }
$nonce_ok = true;
$output = render();
check( false === strpos( $output, '<script>untrusted</script>' ) && false !== strpos( $output, '&lt;script&gt;' ), 'Fetched error must be escaped.' );
$_POST = array();
$before = count( $requests );
render();
check( $before === count( $requests ), 'GET must not execute another check.' );
$settings['website_key'] = 'newexample123';
check( false === strpos( render(), 'Checked at (site timezone):' ), 'Changed settings must invalidate old report.' );
$_POST = array( 'html' => addslashes( page( $cmp . $gtm ) ) );
$allowed = false;
try { Diagnostics::browser_check(); check( false, 'Unauthorized browser check accepted.' ); } catch ( RuntimeException $e ) { check( 'ajax-error-403' === $e->getMessage(), 'Browser check must enforce capability.' ); }
$allowed = true; $nonce_ok = false;
try { Diagnostics::browser_check(); check( false, 'Browser check accepted bad nonce.' ); } catch ( RuntimeException $e ) { check( 'Invalid nonce' === $e->getMessage(), 'Browser check must enforce nonce.' ); }
$nonce_ok = true;
$_POST['html'] = str_repeat( 'x', Diagnostics::LIMIT + 1 );
try { Diagnostics::browser_check(); check( false, 'Oversize browser payload accepted.' ); } catch ( RuntimeException $e ) { check( 'ajax-error-400' === $e->getMessage(), 'Browser payload size must be bounded.' ); }
$_POST['html'] = addslashes( page( $cmp . $gtm ) );
$settings['website_key'] = 'example1234';
try { Diagnostics::browser_check(); check( false, 'No browser response.' ); } catch ( RuntimeException $e ) { check( 'ajax-success' === $e->getMessage(), 'Browser payload not analyzed.' ); }
check( 'browser' === $transients['amsive_cookieyes_check_1']['source'], 'Report must identify browser provenance.' );
$_POST = array();
check( false !== strpos( render(), 'This browser (without login cookies)' ), 'Browser method missing from report.' );
$transients['amsive_cookieyes_check_1']['time'] -= 3601;
check( false === strpos( render(), 'Checked at (site timezone):' ), 'Expired report must not remain current.' );
$http = new WP_Error();
$allowed = false;
try { Diagnostics::server_check(); check( false, 'Unauthorized server check accepted.' ); } catch ( RuntimeException $e ) { check( 'ajax-error-403' === $e->getMessage(), 'Server check must enforce capability.' ); }
$allowed = true; $nonce_ok = false;
try { Diagnostics::server_check(); check( false, 'Server check accepted bad nonce.' ); } catch ( RuntimeException $e ) { check( 'Invalid nonce' === $e->getMessage(), 'Server check must enforce nonce.' ); }
$nonce_ok = true;
try { Diagnostics::server_check(); } catch ( RuntimeException $e ) { check( 'ajax-success' === $e->getMessage(), 'Server check failed to respond.' ); }
check( true === $ajax_data['browser_fallback'], 'Fetch failure must enable browser fallback.' );
$http = array( 'response' => array( 'code' => 200 ), 'headers' => array( 'content-type' => 'text/html' ), 'body' => page( $gtm . $cmp ) );
try { Diagnostics::server_check(); } catch ( RuntimeException $e ) { check( 'ajax-success' === $e->getMessage(), 'Problem result failed to respond.' ); }
check( false === $ajax_data['browser_fallback'], 'A detected integration problem must not trigger fallback.' );
check( in_array( 'problem', array_column( $transients['amsive_cookieyes_check_1']['items'], 'status' ), true ), 'Original problem result must be preserved.' );
$http['body'] = page( $cmp . $gtm );
try { Diagnostics::server_check(); } catch ( RuntimeException $e ) { check( 'ajax-success' === $e->getMessage(), 'Passed result failed to respond.' ); }
check( false === $ajax_data['browser_fallback'], 'Successful HTML analysis must not trigger fallback.' );
check( 'info' === Diagnostics::summary( false )['status'], 'Fresh install should prompt a check.' );
check( 'pass' === Diagnostics::summary( array( 'items' => array( array( 'status' => 'pass', 'title' => 'OK', 'detail' => 'OK' ) ) ) )['status'], 'All passed HTML checks should hide top notice.' );
check( 'unknown' === Diagnostics::summary( array( 'items' => array( array( 'status' => 'unknown', 'title' => 'Fetch failed', 'detail' => 'Try again' ) ) ) )['status'], 'An incomplete check must show a warning.' );
$summary = Diagnostics::summary( array( 'items' => array( array( 'status' => 'unknown', 'title' => 'Unknown', 'detail' => 'Unknown' ), array( 'status' => 'problem', 'title' => 'Missing key', 'detail' => 'Save key' ), array( 'status' => 'problem', 'title' => 'Missing script', 'detail' => 'Enable' ) ) ) );
check( 'problem' === $summary['status'] && 'Missing key' === $summary['title'], 'Top notice must prioritize the main problem over unknown checks and cascades.' );
$_POST = array();
try { Diagnostics::server_check(); } catch ( RuntimeException $e ) {}
ob_start(); Diagnostics::notice(); $notice = ob_get_clean();
check( false !== strpos( $notice, ' hidden' ), 'Passed report must hide the top notice.' );
$transients['amsive_cookieyes_check_1']['time'] -= 3601;
ob_start(); Diagnostics::notice(); $notice = ob_get_clean();
check( false !== strpos( $notice, 'Run the integration check' ) && false === strpos( $notice, ' hidden' ), 'Expired results must restore the initial prompt.' );
echo "PASS: {$checks} diagnostics checks using isolated WordPress/HTTP doubles.\n";
