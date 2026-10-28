<?php
/** Frontend integration. */

namespace Amsive\CookieYes;

defined( 'ABSPATH' ) || exit;

final class Plugin {

    private $rendered = false;

    public function register() {
        add_action( 'wp_head', array( $this, 'render_script' ), 0 );
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
        // Keep the vendor's blocking tag: async/defer would allow later tracking to execute first.
        $src = 'https://cdn-cookieyes.com/client_data/' . $settings['website_key'] . '/script.js';
        echo '<script id="cookieyes" type="text/javascript" src="' . esc_url( $src ) . '"></script>' . "\n";
    }

    public function action_links( $links ) {
        if ( current_user_can( 'manage_options' ) ) {
            array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=' . Settings::PAGE ) ) . '">' . esc_html__( 'Settings', 'cookieyes-integration' ) . '</a>' );
        }
        return $links;
    }
}
