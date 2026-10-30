<?php
/** WordPress Settings API configuration. */

namespace Amsive\CookieYes;

defined( 'ABSPATH' ) || exit;

final class Settings {

    const OPTION = 'amsive_cookieyes_settings';
    const GROUP  = 'amsive_cookieyes';
    const PAGE   = 'cookieyes-integration';

    public static function valid_key( $key ) {
        // Validate a single safe CDN path segment; never accept a URL or executable code.
        return is_string( $key ) && 1 === preg_match( '/\A[A-Za-z0-9_-]{8,128}\z/D', $key );
    }

    public static function get() {
        $saved = get_option( self::OPTION, array() );
        $saved = is_array( $saved ) ? $saved : array();
        $key = isset( $saved['website_key'] ) && is_string( $saved['website_key'] ) ? $saved['website_key'] : '';
        return array(
            'website_key' => self::valid_key( $key ) ? $key : '',
            'enabled'     => isset( $saved['enabled'] ) && in_array( $saved['enabled'], array( true, 1, '1' ), true ),
        );
    }

    public function register() {
        register_setting( self::GROUP, self::OPTION, array(
            'type'              => 'array',
            'sanitize_callback' => array( $this, 'sanitize' ),
            'show_in_rest'      => false,
            'default'           => array( 'website_key' => '', 'enabled' => false ),
        ) );
    }

    public function menu() {
        add_options_page( __( 'CookieYes Integration', 'cookieyes-integration' ), __( 'CookieYes Integration', 'cookieyes-integration' ), 'manage_options', self::PAGE, array( $this, 'page' ) );
    }

    public function sanitize( $input ) {
        $previous = self::get();
        if ( ! is_array( $input ) || ! isset( $input['website_key'] ) || ! is_string( $input['website_key'] ) ) {
            add_settings_error( self::OPTION, 'invalid_settings', __( 'Invalid settings. Previous settings were kept.', 'cookieyes-integration' ) );
            return $previous;
        }

        $key = trim( $input['website_key'] );
        $enabled = isset( $input['enabled'] ) && in_array( $input['enabled'], array( true, 1, '1' ), true );
        if ( ( '' !== $key && ! self::valid_key( $key ) ) || ( $enabled && '' === $key ) ) {
            if ( ! $enabled ) {
                add_settings_error( self::OPTION, 'disabled_invalid_key', __( 'CookieYes was disabled. The invalid website key was not saved; the previous key was kept.', 'cookieyes-integration' ), 'warning' );
                return array( 'website_key' => $previous['website_key'], 'enabled' => false );
            }
            add_settings_error( self::OPTION, 'invalid_key', __( 'Enter only the CookieYes website key from your installation code. Previous settings were kept.', 'cookieyes-integration' ) );
            return $previous;
        }

        return array( 'website_key' => $key, 'enabled' => $enabled );
    }

    public function page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to manage these settings.', 'cookieyes-integration' ) );
        }
        Diagnostics::prepare();
        $settings = self::get();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__( 'CookieYes Integration', 'cookieyes-integration' ); ?> <small style="font-size:13px;font-weight:400;color:#646970;vertical-align:middle">v<?php echo esc_html( COOKIEYES_INTEGRATION_VERSION ); ?> · <?php echo esc_html__( 'by Amsive', 'cookieyes-integration' ); ?></small></h1>
            <p><?php echo esc_html__( 'Connect this site to your CookieYes account. Manage banner design, categories, languages, and consent rules in CookieYes.', 'cookieyes-integration' ); ?></p>
            <?php Diagnostics::notice(); ?>
            <form action="options.php" method="post">
                <?php settings_fields( self::GROUP ); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="amsive-cookieyes-key"><?php echo esc_html__( 'CookieYes website key', 'cookieyes-integration' ); ?></label></th>
                        <td>
                            <input id="amsive-cookieyes-key" class="regular-text" type="text" name="<?php echo esc_attr( self::OPTION ); ?>[website_key]" value="<?php echo esc_attr( $settings['website_key'] ); ?>" maxlength="128" autocomplete="off" spellcheck="false" aria-describedby="amsive-cookieyes-key-help">
                            <p id="amsive-cookieyes-key-help" class="description"><?php echo esc_html__( 'In CookieYes, open Advanced Settings → Get Installation Code. Copy only the key between /client_data/ and /script.js, not the full URL or script tag.', 'cookieyes-integration' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__( 'Installation', 'cookieyes-integration' ); ?></th>
                        <td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[enabled]" value="1" <?php checked( $settings['enabled'] ); ?>> <?php echo esc_html__( 'Enable CookieYes', 'cookieyes-integration' ); ?></label></td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
            <p><?php echo esc_html__( 'After saving, clear the relevant page cache and verify the banner and tracking consent in a fresh browser session. Exclude the CookieYes script and cookieyes-integration-consent-defaults from script delay, combination, and async/defer optimizations.', 'cookieyes-integration' ); ?></p>
            <p class="description"><?php echo esc_html__( 'This installation sets the initial Google consent state before CookieYes. Enable Support GCM in CookieYes to apply visitor choices. For Basic Consent Mode, keep Allow Google tags to fire before consent off and load Google tags after CookieYes. If another integration already sets consent defaults, have your developer choose one default owner before updating.', 'cookieyes-integration' ); ?></p>
            <p class="description"><?php echo esc_html__( 'Use one CookieYes installation only. Remove duplicate direct snippets, GTM installations or other CookieYes plugins. Tracking hardcoded before wp_head() requires a theme change.', 'cookieyes-integration' ); ?></p>
            <p><a href="https://www.cookieyes.com/documentation/banner-installation-code/" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'CookieYes installation instructions', 'cookieyes-integration' ); ?></a></p>
            <?php Diagnostics::render(); ?>
        </div>
        <?php
    }
}
