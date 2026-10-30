<?php
/** Frontend integration. */

namespace Amsive\CookieYes;

defined( 'ABSPATH' ) || exit;

final class Plugin {

    private $rendered = false;

    public function register() {
        add_action( 'wp_head', array( $this, 'render_script' ), 0 );
        add_action( 'wp_ajax_amsive_cookieyes_server_check', array( Diagnostics::class, 'server_check' ) );
        add_action( 'wp_ajax_amsive_cookieyes_browser_check', array( Diagnostics::class, 'browser_check' ) );
        $settings = new Settings();
        add_action( 'admin_init', array( $settings, 'register' ) );
        add_action( 'admin_menu', array( $settings, 'menu' ) );
    }

    public function render_script() {
        $settings = Settings::get();
        if ( $this->rendered || is_admin() || ! $settings['enabled'] || ! Settings::valid_key( $settings['website_key'] ) ) {
            return;
        }

        $this->rendered = true;
        // One owner for defaults. Sites with another consent initializer can opt out.
        if ( apply_filters( 'cookieyes_integration_consent_defaults_enabled', true ) ) {
            $this->render_consent_defaults();
        }
        // Keep the vendor's blocking tag: async/defer would allow later tracking to execute first.
        $src = 'https://cdn-cookieyes.com/client_data/' . $settings['website_key'] . '/script.js';
        echo '<script id="cookieyes" type="text/javascript" src="' . esc_url( $src ) . '"></script>' . "\n";
    }

    private function render_consent_defaults() {
        // Queue only the initial state; CookieYes owns updates and saved preferences.
        ?>
<script id="cookieyes-integration-consent-defaults">
(function (w) {
    w.dataLayer = w.dataLayer || [];
    if (typeof w.gtag !== 'function') {
        w.gtag = function () { w.dataLayer.push(arguments); };
    }
    if (w.amsiveCookieYesConsentInitialized) { return; }
    for (var i = 0; i < w.dataLayer.length; i++) {
        var command = w.dataLayer[i];
        if (Object.prototype.toString.call(command) === '[object Arguments]' && command[0] === 'consent' && command[1] === 'default') { return; }
    }
    w.amsiveCookieYesConsentInitialized = true;
    w.gtag('consent', 'default', {
        ad_storage: 'denied',
        ad_user_data: 'denied',
        ad_personalization: 'denied',
        analytics_storage: 'denied',
        functionality_storage: 'denied',
        personalization_storage: 'denied',
        security_storage: 'granted',
        wait_for_update: 2000
    });
})(window);
</script>
        <?php
    }

    public function action_links( $links ) {
        if ( current_user_can( 'manage_options' ) ) {
            array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=' . Settings::PAGE ) ) . '">' . esc_html__( 'Settings', 'cookieyes-integration' ) . '</a>' );
        }
        return $links;
    }
}
