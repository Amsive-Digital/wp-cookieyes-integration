<?php
/** On-demand inspection of the anonymous homepage response, never a consent certification. */
namespace Amsive\CookieYes;

defined( 'ABSPATH' ) || exit;

final class Diagnostics {
    const ACTION = 'amsive_cookieyes_check';
    const LIMIT = 1048576;
    const REPORT_VERSION = 2;

    private static function item( $status, $title, $detail ) {
        return array( 'status' => $status, 'title' => $title, 'detail' => $detail );
    }

    private static function context() {
        return hash( 'sha256', home_url( '/' ) . COOKIEYES_INTEGRATION_VERSION . self::REPORT_VERSION . serialize( Settings::get() ) );
    }

    private static function cache_key() {
        return 'amsive_cookieyes_check_' . get_current_user_id();
    }

    public static function run() {
        $url = home_url( '/' );
        $report = array( 'url' => $url, 'time' => time(), 'context' => self::context(), 'source' => 'server', 'fetch_failed' => false, 'items' => array() );
        // Only the configured site URL is accepted. Never forward admin cookies or follow redirects.
        // wp_remote_get intentionally supports private/local site origins; there is no user-supplied target.
        if ( ! in_array( wp_parse_url( $url, PHP_URL_SCHEME ), array( 'http', 'https' ), true ) ) {
            $report['items'][] = self::item( 'unknown', __( 'Homepage response', 'cookieyes-integration' ), __( 'The configured homepage URL must use HTTP or HTTPS.', 'cookieyes-integration' ) );
            return $report;
        }
        $response = wp_remote_get( $url, array(
            'timeout' => 15, 'redirection' => 0, 'cookies' => array(),
            'limit_response_size' => self::LIMIT + 1,
            'headers' => array( 'Accept' => 'text/html', 'Cache-Control' => 'no-cache' ),
        ) );
        if ( is_wp_error( $response ) ) {
            $report['fetch_failed'] = true;
            $report['items'][] = self::item( 'unknown', __( 'Homepage response', 'cookieyes-integration' ), sprintf( __( 'Could not fetch the homepage: %s. Check loopback access, DNS and the HTTPS certificate trust chain. Certificate verification is not disabled.', 'cookieyes-integration' ), $response->get_error_message() ) );
            return $report;
        }
        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $type = (string) wp_remote_retrieve_header( $response, 'content-type' );
        if ( 200 !== $code || ! trim( $body ) || strlen( $body ) > self::LIMIT || ! preg_match( '~^text/html(?:\s*;|$)~i', $type ) ) {
            $report['fetch_failed'] = true;
            $report['items'][] = self::item( 'unknown', __( 'Homepage response', 'cookieyes-integration' ), sprintf( __( 'Cannot inspect this response (HTTP %d). Expected HTML up to 1 MiB. Redirects are not followed; check the configured homepage URL, access restrictions and response size.', 'cookieyes-integration' ), $code ) );
            return $report;
        }
        $report['items'] = self::analyze( $body, Settings::get(), $url );
        return $report;
    }

    private static function inert( $node ) {
        for ( $parent = $node->parentNode; $parent; $parent = $parent->parentNode ) {
            if ( in_array( strtolower( $parent->nodeName ), array( 'noscript', 'template', 'textarea' ), true ) ) {
                return true;
            }
        }
        return false;
    }

    private static function executable( $node ) {
        $type = strtolower( trim( $node->getAttribute( 'type' ) ) );
        return in_array( $type, array( '', 'text/javascript', 'application/javascript', 'module', 'text/ecmascript', 'application/ecmascript' ), true );
    }

    private static function vendor_key( $src ) {
        // Match the real host and path, not a vendor URL in a foreign URL's query string.
        if ( preg_match( '~^(?:https?:)?//cdn-cookieyes\.com/client_data/([A-Za-z0-9_-]+)/script\.js(?:[?#].*)?$~i', trim( $src ), $m ) ) {
            return $m[1];
        }
        return '';
    }

    /** Pure HTML analysis; no JS evaluation, HTTP requests or site changes. */
    public static function analyze( $html, $settings, $page_url = null ) {
        if ( ! trim( $html ) ) {
            return array( self::item( 'unknown', __( 'Homepage HTML', 'cookieyes-integration' ), __( 'The homepage returned empty HTML. Check access and rerun.', 'cookieyes-integration' ) ) );
        }
        if ( ! class_exists( '\DOMDocument' ) ) {
            return array( self::item( 'unknown', __( 'HTML parser', 'cookieyes-integration' ), __( 'PHP DOM is unavailable. Ask the host to enable the DOM extension, then rerun the check.', 'cookieyes-integration' ) ) );
        }
        $doc = new \DOMDocument();
        $previous = libxml_use_internal_errors( true );
        try {
            $loaded = $doc->loadHTML( $html, LIBXML_NONET );
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors( $previous );
        }
        if ( ! $loaded || ! $doc->getElementsByTagName( 'head' )->length || ! $doc->getElementsByTagName( 'body' )->length ) {
            return array( self::item( 'unknown', __( 'Homepage HTML', 'cookieyes-integration' ), __( 'A complete HTML page could not be identified. Check for an access or security challenge and rerun.', 'cookieyes-integration' ) ) );
        }
        // A public page may embed wp_login_form() or password-protected content.
        // Only reject the recognizable WordPress login document, not a form by itself.
        $body = $doc->getElementsByTagName( 'body' )->item( 0 );
        $login_body = 1 === preg_match( '/(?:^|\s)login(?:\s|$)/', $body->getAttribute( 'class' ) );
        foreach ( $doc->getElementsByTagName( 'form' ) as $form ) {
            $login_container = false;
            for ( $parent = $form->parentNode; $parent instanceof \DOMElement; $parent = $parent->parentNode ) {
                if ( 'login' === $parent->getAttribute( 'id' ) ) { $login_container = true; break; }
            }
            $action_path = (string) wp_parse_url( $form->getAttribute( 'action' ), PHP_URL_PATH );
            if ( $login_body && $login_container && 'loginform' === $form->getAttribute( 'id' ) && preg_match( '~(?:^|/)wp-login\.php$~', $action_path ) ) {
                return array( self::item( 'unknown', __( 'Public homepage', 'cookieyes-integration' ), __( 'A WordPress login page was returned. Make the homepage accessible to an anonymous request before checking integration.', 'cookieyes-integration' ) ) );
            }
        }
        $secure_page = 'https' === strtolower( (string) wp_parse_url( null === $page_url ? home_url( '/' ) : $page_url, PHP_URL_SCHEME ) );
        $cookieyes = array();
        $trackers = array();
        $legacy = false;
        $dynamic = false;
        $uncertain_order = false;
        $position = 0;
        foreach ( $doc->getElementsByTagName( 'script' ) as $node ) {
            ++$position;
            if ( self::inert( $node ) ) { continue; }
            $src = trim( $node->getAttribute( 'src' ) );
            $key = self::vendor_key( $src );
            $delayed_src = $node->getAttribute( 'data-src' );
            if ( ! $delayed_src ) { $delayed_src = $node->getAttribute( 'data-rocket-src' ); }
            $delayed_key = self::vendor_key( $delayed_src );
            if ( $key || $delayed_key || 'cookieyes' === $node->getAttribute( 'id' ) ) {
                $cookieyes[] = array(
                    'key' => $key ? $key : $delayed_key,
                    'position' => $position,
                    'head' => 'head' === strtolower( $node->parentNode->nodeName ),
                    'mixed_content' => $secure_page && 0 === stripos( $src, 'http://' ),
                    'blocking' => $key && ! ( $secure_page && 0 === stripos( $src, 'http://' ) ) && ! $node->hasAttribute( 'nomodule' ) && self::executable( $node ) && 'module' !== strtolower( trim( $node->getAttribute( 'type' ) ) ) && ! $node->hasAttribute( 'async' ) && ! $node->hasAttribute( 'defer' ) && ( ! $node->hasAttribute( 'data-cfasync' ) || 'false' === strtolower( $node->getAttribute( 'data-cfasync' ) ) ),
                );
            }
            if ( preg_match( '~^(?:https?:)?//(?:www\.)?googletagmanager\.com/(?:gtm\.js|gtag/js)(?:[?#]|$)~i', $delayed_src ) || ( ! self::executable( $node ) && preg_match( '~rocketlazyloadscript|litespeed/javascript|pmdelayedscript~i', $node->getAttribute( 'type' ) ) ) ) {
                $uncertain_order = true;
            }
            if ( ! self::executable( $node ) ) { continue; }
            $code = $src ? '' : $node->textContent;
            if ( preg_match( '~^(?:https?:)?//(?:consent\.cookiebot\.com|consentcdn\.cookiebot\.com)/~i', $src ) ) { $legacy = true; }
            $name = '';
            if ( preg_match( '~^(?:https?:)?//(?:www\.)?googletagmanager\.com/(gtm\.js|gtag/js)(?:[?#]|$)~i', $src, $m ) ) {
                $name = 'gtm.js' === $m[1] ? 'Google Tag Manager' : 'Google tag';
            } elseif ( preg_match( '~googletagmanager\.com/gtm\.js~i', $code ) && false !== strpos( $code, 'gtm.start' ) && preg_match( '~createElement\s*\(~', $code ) ) {
                $name = 'Google Tag Manager snippet';
            } elseif ( preg_match( '~\bgtag\s*\(\s*[\x22\x27]config[\x22\x27]\s*,~', $code ) ) {
                $name = 'Google tag configuration';
            }
            if ( $name ) { $trackers[] = array( 'position' => $position, 'name' => $name ); }
            if ( ! $key && preg_match( '~cdn-cookieyes\.com|googletagmanager\.com~i', $code ) && ! $name ) { $dynamic = true; }
        }
        $items = array();
        $items[] = self::item( $settings['enabled'] && Settings::valid_key( $settings['website_key'] ) ? 'pass' : 'problem', __( 'Saved plugin configuration', 'cookieyes-integration' ), $settings['enabled'] && Settings::valid_key( $settings['website_key'] ) ? __( 'Output is enabled and the saved website key has a valid format.', 'cookieyes-integration' ) : __( 'Save a valid website key and enable CookieYes when the site is ready. This check does not change settings.', 'cookieyes-integration' ) );
        $count = count( $cookieyes );
        $items[] = self::item( 1 === $count ? 'pass' : 'problem', __( 'CookieYes installation', 'cookieyes-integration' ), 1 === $count ? __( 'One CookieYes installation tag was found in the returned HTML.', 'cookieyes-integration' ) : ( $count ? __( 'Multiple CookieYes tags were found. Keep one installation path and remove duplicate snippets or plugins, then clear the page cache.', 'cookieyes-integration' ) : __( 'No direct CookieYes tag was found. Check activation, saved settings, the theme wp_head() hook and page cache. A GTM-only installation requires a separate browser check.', 'cookieyes-integration' ) ) );
        if ( 1 === $count ) {
            $tag = $cookieyes[0];
            $items[] = self::item( $tag['key'] === $settings['website_key'] && '' !== $tag['key'] ? 'pass' : 'problem', __( 'Website key', 'cookieyes-integration' ), $tag['key'] === $settings['website_key'] && '' !== $tag['key'] ? __( 'The tag uses the saved website key and the expected CookieYes CDN URL.', 'cookieyes-integration' ) : __( 'The tag does not match the saved key or expected CDN URL. Remove an old snippet and clear the page cache.', 'cookieyes-integration' ) );
            $items[] = self::item( $tag['blocking'] && $tag['head'] ? 'pass' : 'problem', __( 'Loading attributes and placement', 'cookieyes-integration' ), $tag['blocking'] && $tag['head'] ? __( 'The tag is in the head without detected async, defer, module, nomodule or delayed-source loading.', 'cookieyes-integration' ) : ( $tag['mixed_content'] ? __( 'The CookieYes script uses HTTP on an HTTPS page and can be blocked as mixed content. Use the HTTPS CookieYes CDN URL, then clear the page cache.', 'cookieyes-integration' ) : __( 'Place the direct CookieYes tag in the head without nomodule and exclude it from script delay, async/defer, module conversion and script combination.', 'cookieyes-integration' ) ) );
            $earlier = array();
            foreach ( $trackers as $tracker ) {
                if ( $tracker['position'] < $tag['position'] ) { $earlier[] = $tracker['name']; }
            }
            $items[] = self::item( $earlier ? 'problem' : ( $trackers && ! $uncertain_order ? 'pass' : 'unknown' ), __( 'Google script order', 'cookieyes-integration' ), $earlier ? sprintf( __( '%s appears before CookieYes. Move the tracking snippet after CookieYes; a snippet hardcoded before wp_head() needs a theme change. This rule applies to this direct installation.', 'cookieyes-integration' ), implode( ', ', array_unique( $earlier ) ) ) : ( $trackers && ! $uncertain_order ? __( 'CookieYes appears before the recognized Google snippets in this HTML. Execution timing and consent defaults still need a browser test.', 'cookieyes-integration' ) : __( 'Google script order could not be fully established. Delayed scripts, GTM-injected tags, custom loaders and first-party proxies need a browser check.', 'cookieyes-integration' ) ) );
        }
        $items[] = self::item( $legacy ? 'problem' : 'pass', __( 'Legacy Cookiebot script', 'cookieyes-integration' ), $legacy ? __( 'An active Cookiebot script was found alongside this integration. Complete the migration and remove the obsolete consent script.', 'cookieyes-integration' ) : __( 'No active script from the recognized Cookiebot hosts was found in this HTML.', 'cookieyes-integration' ) );
        if ( $dynamic || $uncertain_order ) {
            $items[] = self::item( 'unknown', __( 'Custom script loader', 'cookieyes-integration' ), __( 'A custom or delayed script loader was detected. Ask a developer to inspect it in a browser.', 'cookieyes-integration' ) );
        }
        return $items;
    }

    /** A completed analysis never triggers a second check, even when it finds problems. */
    public static function server_check() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'You do not have permission to run this check.', 'cookieyes-integration' ), 403 );
            return;
        }
        check_ajax_referer( self::ACTION );
        $report = self::run();
        set_transient( self::cache_key(), $report, HOUR_IN_SECONDS );
        wp_send_json_success( array( 'browser_fallback' => $report['fetch_failed'] ) );
    }

    /** Analyze HTML fetched by the administrator's browser without disabling server TLS checks. */
    public static function browser_check() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'You do not have permission to run this check.', 'cookieyes-integration' ), 403 );
            return;
        }
        check_ajax_referer( self::ACTION );
        $html = isset( $_POST['html'] ) && is_string( $_POST['html'] ) ? wp_unslash( $_POST['html'] ) : '';
        if ( ! trim( $html ) || strlen( $html ) > self::LIMIT ) {
            wp_send_json_error( __( 'Expected homepage HTML up to 1 MiB.', 'cookieyes-integration' ), 400 );
            return;
        }
        $report = array( 'url' => home_url( '/' ), 'time' => time(), 'context' => self::context(), 'source' => 'browser', 'items' => self::analyze( $html, Settings::get() ) );
        set_transient( self::cache_key(), $report, HOUR_IN_SECONDS );
        wp_send_json_success();
    }

    /** Only current results may drive the top notice. */
    public static function current_report() {
        $report = get_transient( self::cache_key() );
        if ( ! is_array( $report ) || ! isset( $report['context'], $report['time'], $report['items'] ) || ! is_array( $report['items'] ) || ! $report['items'] || $report['context'] !== self::context() || $report['time'] < time() - HOUR_IN_SECONDS ) {
            return false;
        }
        return $report;
    }

    public static function prepare() {
        if ( current_user_can( 'manage_options' ) && isset( $_POST['amsive_cookieyes_check'] ) ) {
            check_admin_referer( self::ACTION );
            set_transient( self::cache_key(), self::run(), HOUR_IN_SECONDS );
        }
    }

    /** Problems take precedence over unknown checks; success stays quiet at the top. */
    public static function summary( $report ) {
        if ( ! $report ) {
            return array( 'status' => 'info', 'title' => __( 'Run the integration check', 'cookieyes-integration' ), 'detail' => __( 'Save your settings, then check the public homepage HTML to identify integration problems.', 'cookieyes-integration' ) );
        }
        foreach ( array( 'problem', 'unknown' ) as $status ) {
            foreach ( $report['items'] as $item ) {
                if ( $status === $item['status'] ) {
                    return array( 'status' => $status, 'title' => $item['title'], 'detail' => $item['detail'] );
                }
            }
        }
        return array( 'status' => 'pass', 'title' => '', 'detail' => '' );
    }

    public static function notice() {
        if ( ! current_user_can( 'manage_options' ) ) { return; }
        $summary = self::summary( self::current_report() );
        $classes = array( 'info' => 'info', 'problem' => 'error', 'unknown' => 'warning', 'pass' => 'success' );
        ?>
        <div id="amsive-cookieyes-integration-notice" class="notice notice-<?php echo esc_attr( $classes[$summary['status']] ); ?> inline" role="status"<?php if ( 'pass' === $summary['status'] ) { echo ' hidden'; } ?>><p><span class="amsive-cookieyes-notice-message"><strong><?php echo esc_html( $summary['title'] ); ?></strong> <?php echo esc_html( $summary['detail'] ); ?></span> <a href="#amsive-cookieyes-diagnostics" class="amsive-cookieyes-notice-link"><?php echo esc_html__( 'View integration check', 'cookieyes-integration' ); ?></a></p></div>
        <?php
    }

    public static function render() {
        if ( ! current_user_can( 'manage_options' ) ) { return; }
        $report = self::current_report();
        ?>
        <hr>
        <h2><?php echo esc_html__( 'Frontend integration check', 'cookieyes-integration' ); ?></h2>
        <p><?php echo esc_html__( 'Inspect the public homepage HTML without login cookies. Save settings first. The check starts on the server and automatically uses this browser if the server cannot fetch the page.', 'cookieyes-integration' ); ?></p>
        <form id="amsive-cookieyes-diagnostics" method="post" style="display:flex;flex-direction:column;align-items:flex-start;gap:12px;max-width:640px;margin:20px 0" action="<?php echo esc_url( admin_url( 'options-general.php?page=' . Settings::PAGE ) ); ?>">
            <?php wp_nonce_field( self::ACTION ); ?>
            <button type="submit" name="amsive_cookieyes_check" value="1" class="button button-secondary"><?php echo esc_html__( 'Check integration', 'cookieyes-integration' ); ?></button>
            <span id="amsive-cookieyes-check-progress" role="status" aria-live="polite" style="text-align:left;line-height:1.5;max-width:100%" hidden></span>
        </form>
        <script>
        (function () {
            'use strict';
            var form = document.getElementById('amsive-cookieyes-diagnostics');
            var progress = document.getElementById('amsive-cookieyes-check-progress');
            var notice = document.getElementById('amsive-cookieyes-integration-notice');
            function updateNotice(state, message) {
                if (!notice) { return; }
                notice.hidden = false;
                notice.className = 'notice notice-' + state + ' inline';
                notice.querySelector('.amsive-cookieyes-notice-message').textContent = message;
                notice.querySelector('.amsive-cookieyes-notice-link').hidden = state === 'info';
            }
            form.addEventListener('submit', async function (event) {
                event.preventDefault();
                var controller = new AbortController();
                var timer = setTimeout(function () { controller.abort(); }, 40000);
                var buttons = form.querySelectorAll('button');
                buttons.forEach(function (item) { item.disabled = true; });
                progress.hidden = false;
                updateNotice('info', <?php echo wp_json_encode( __( 'Checking your homepage HTML…', 'cookieyes-integration' ) ); ?>);
                progress.textContent = <?php echo wp_json_encode( __( 'Checking from the server…', 'cookieyes-integration' ) ); ?>;
                try {
                    var endpoint = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
                    var nonce = form.querySelector('[name="_wpnonce"]').value;
                    var serverResult = await fetch(endpoint, {method: 'POST', credentials: 'same-origin', signal: controller.signal, body: new URLSearchParams({action: 'amsive_cookieyes_server_check', _ajax_nonce: nonce})});
                    var serverData = await serverResult.json();
                    if (!serverResult.ok || !serverData.success) {
                        throw new Error(typeof serverData.data === 'string' ? serverData.data : 'Server check could not be completed.');
                    }
                    if (!serverData.data.browser_fallback) {
                        window.location.assign(form.action);
                        return;
                    }
                    progress.textContent = <?php echo wp_json_encode( __( 'Server could not fetch the page. Checking through this browser…', 'cookieyes-integration' ) ); ?>;
                    var response = await fetch(<?php echo wp_json_encode( home_url( '/' ) ); ?>, {credentials: 'omit', redirect: 'error', cache: 'no-store', signal: controller.signal});
                    if (!response.ok || !/^text\/html(?:\s*;|$)/i.test(response.headers.get('Content-Type') || '')) {
                        throw new Error(<?php echo wp_json_encode( __( 'The public homepage did not return a successful HTML response.', 'cookieyes-integration' ) ); ?>);
                    }
                    var reader = response.body.getReader();
                    var chunks = [];
                    var bytes = 0;
                    while (true) {
                        var part = await reader.read();
                        if (part.done) { break; }
                        bytes += part.value.byteLength;
                        if (bytes > <?php echo (int) self::LIMIT; ?>) {
                            await reader.cancel();
                            throw new Error(<?php echo wp_json_encode( __( 'The homepage exceeds the 1 MiB check limit.', 'cookieyes-integration' ) ); ?>);
                        }
                        chunks.push(part.value);
                    }
                    var html = await new Blob(chunks).text();
                    var payload = new URLSearchParams({action: 'amsive_cookieyes_browser_check', _ajax_nonce: nonce, html: html});
                    var result = await fetch(<?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>, {method: 'POST', credentials: 'same-origin', signal: controller.signal, body: payload});
                    var data = await result.json();
                    if (!result.ok || !data.success) { throw new Error(typeof data.data === 'string' ? data.data : 'Check could not be completed.'); }
                    window.location.assign(form.action);
                } catch (error) {
                    progress.textContent = <?php echo wp_json_encode( __( 'Unable to verify:', 'cookieyes-integration' ) ); ?> + ' ' + error.message;
                    updateNotice('warning', progress.textContent);
                    buttons.forEach(function (item) { item.disabled = false; });
                } finally { clearTimeout(timer); }
            });
        }());
        </script>
        <?php if ( is_array( $report ) ) :
            $statuses = array_column( $report['items'], 'status' );
            $status = in_array( 'problem', $statuses, true ) ? 'problem' : ( in_array( 'unknown', $statuses, true ) ? 'unknown' : 'pass' );
            $labels = array( 'pass' => __( 'Passed HTML checks', 'cookieyes-integration' ), 'problem' => __( 'Problem detected', 'cookieyes-integration' ), 'unknown' => __( 'Unable to fully verify', 'cookieyes-integration' ) );
            $row_labels = array( 'pass' => __( 'Passed', 'cookieyes-integration' ), 'problem' => __( 'Problem', 'cookieyes-integration' ), 'unknown' => __( 'Not verified', 'cookieyes-integration' ) );
            $badge_styles = array(
                'pass' => 'color:#005c12;background:#edfaef;border-color:#008a20',
                'problem' => 'color:#8a2424;background:#fcf0f1;border-color:#d63638',
                'unknown' => 'color:#614200;background:#fcf9e8;border-color:#dba617',
            );
            ?>
            <h3><?php echo esc_html( $labels[$status] ); ?></h3>
            <p><?php echo esc_html__( 'Checked URL:', 'cookieyes-integration' ); ?> <a href="<?php echo esc_url( $report['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $report['url'] ); ?></a><br>
            <?php echo esc_html__( 'HTML fetched by:', 'cookieyes-integration' ); ?> <?php echo esc_html( isset( $report['source'] ) && 'browser' === $report['source'] ? __( 'This browser (without login cookies)', 'cookieyes-integration' ) : __( 'WordPress server (without login cookies)', 'cookieyes-integration' ) ); ?><br>
            <?php echo esc_html__( 'Checked at (site timezone):', 'cookieyes-integration' ); ?> <?php echo esc_html( wp_date( 'Y-m-d H:i:s T', $report['time'] ) ); ?></p>
            <table class="widefat striped" style="max-width:1100px;margin-top:16px">
                <thead><tr><th scope="col"><?php echo esc_html__( 'Check', 'cookieyes-integration' ); ?></th><th scope="col"><?php echo esc_html__( 'Result', 'cookieyes-integration' ); ?></th><th scope="col"><?php echo esc_html__( 'Finding / next action', 'cookieyes-integration' ); ?></th></tr></thead>
                <tbody><?php foreach ( $report['items'] as $item ) : ?><tr><th scope="row"><?php echo esc_html( $item['title'] ); ?></th><td><strong style="display:inline-block;padding:2px 8px;border:1px solid;border-radius:3px;white-space:nowrap;<?php echo esc_attr( $badge_styles[$item['status']] ); ?>"><?php echo esc_html( $row_labels[$item['status']] ); ?></strong></td><td><?php echo esc_html( $item['detail'] ); ?></td></tr><?php endforeach; ?></tbody>
            </table>
        <?php endif; ?>
        <p class="description" style="max-width:1100px;margin-top:16px"><?php echo esc_html__( 'Scope: one homepage HTML response from the selected fetch method, which may still differ from other visitors. Results are kept for one hour per administrator and invalidated when plugin settings or version change. This does not execute JavaScript, verify CDN delivery, inspect every page or prove consent behavior. Custom loaders, regional responses and proxy-served tags may require developer review.', 'cookieyes-integration' ); ?></p>
        <p class="description" style="max-width:1100px"><?php echo esc_html__( 'Browser verification required: test a fresh visitor, rejection, acceptance, category choices and reopening preferences; inspect consent defaults, network requests and cookies. No automatic repairs or developer notifications are sent.', 'cookieyes-integration' ); ?></p>
        <?php
    }
}
