<?php
/** Stable public GitHub releases through WordPress's native updater. */
namespace Amsive\CookieYes;
defined( 'ABSPATH' ) || exit;

final class Updater {
    const REPOSITORY = 'Amsive-Digital/wp-cookieyes-integration';
    const URI = 'https://github.com/Amsive-Digital/wp-cookieyes-integration/';
    const SLUG = 'cookieyes-integration';
    const CACHE = 'cookieyes_integration_release';

    public static function register() {
        add_filter( 'update_plugins_github.com', array( self::class, 'update' ), 10, 4 );
        add_filter( 'plugins_api', array( self::class, 'information' ), 10, 3 );
        add_action( 'load-update-core.php', array( self::class, 'maybe_refresh' ), 1 );
        add_action( 'load-plugins.php', array( self::class, 'maybe_refresh' ), 1 );
        add_action( 'upgrader_process_complete', array( self::class, 'after_upgrade' ), 10, 2 );
    }

    public static function update( $update, $data, $file, $locales ) {
        if ( plugin_basename( COOKIEYES_INTEGRATION_FILE ) !== $file || ! is_array( $data ) || self::URI !== ( $data['UpdateURI'] ?? '' ) ) {
            return $update;
        }
        $release = self::latest();
        if ( ! $release ) {
            return $update;
        }
        // Return current releases too: core fills response/no_update and preserves the admin's auto-update choice.
        return array(
            'id' => self::URI, 'slug' => self::SLUG, 'plugin' => $file,
            'version' => $release['version'], 'new_version' => $release['version'],
            'url' => $release['url'], 'package' => $release['package'],
            'requires' => '6.0', 'requires_php' => '7.4',
        );
    }

    public static function information( $result, $action, $args ) {
        if ( 'plugin_information' !== $action || ! is_object( $args ) || self::SLUG !== ( $args->slug ?? '' ) ) {
            return $result;
        }
        $release = self::latest();
        if ( ! $release ) {
            return $result;
        }
        return (object) array(
            'name' => 'CookieYes Integration', 'slug' => self::SLUG,
            'version' => $release['version'], 'author' => 'Amsive',
            'homepage' => self::URI, 'requires' => '6.0', 'requires_php' => '7.4',
            'download_link' => $release['package'], 'last_updated' => $release['published_at'],
            'sections' => array(
                'description' => '<p>' . esc_html__( 'Connect WordPress to your CookieYes banner using a per-site website key.', 'cookieyes-integration' ) . '</p>',
                'installation' => '<p>' . esc_html__( 'Install the release ZIP, activate the plugin, then open Settings → CookieYes Integration.', 'cookieyes-integration' ) . '</p>',
                'changelog' => '<pre>' . esc_html( $release['body'] ) . '</pre>',
            ),
        );
    }

    public static function clear() {
        delete_transient( self::CACHE );
    }

    public static function maybe_refresh() {
        if ( isset( $_GET['force-check'] ) ) {
            self::clear();
        }
    }

    public static function after_upgrade( $upgrader, $options ) {
        if ( ! is_array( $options ) || 'plugin' !== ( $options['type'] ?? '' ) || 'update' !== ( $options['action'] ?? '' ) ) {
            return;
        }
        $plugins = (array) ( $options['plugins'] ?? array() );
        if ( isset( $options['plugin'] ) ) {
            $plugins[] = $options['plugin'];
        }
        if ( in_array( plugin_basename( COOKIEYES_INTEGRATION_FILE ), $plugins, true ) ) {
            self::clear();
            delete_site_transient( 'update_plugins' );
        }
    }

    private static function latest() {
        $cached = get_transient( self::CACHE );
        if ( false !== $cached ) {
            return is_array( $cached ) ? self::normalize_cached( $cached ) : array();
        }
        $response = wp_safe_remote_get( 'https://api.github.com/repos/' . self::REPOSITORY . '/releases/latest', array(
            'timeout' => 5, 'redirection' => 0, 'limit_response_size' => 262144,
            'headers' => array( 'Accept' => 'application/vnd.github+json', 'X-GitHub-Api-Version' => '2022-11-28', 'User-Agent' => 'CookieYes-Integration/' . COOKIEYES_INTEGRATION_VERSION ),
        ) );
        $release = array();
        if ( ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ) ) {
            $data = json_decode( wp_remote_retrieve_body( $response ), true );
            $release = is_array( $data ) ? self::normalize( $data ) : array();
        }
        set_transient( self::CACHE, $release, $release ? 12 * HOUR_IN_SECONDS : 5 * MINUTE_IN_SECONDS );
        return $release;
    }

    private static function normalize_cached( $cached ) {
        if ( ! isset( $cached['version'] ) || ! is_string( $cached['version'] ) ) {
            return array();
        }
        // Revalidate the cached URL pair just as strictly as fresh remote data.
        return self::normalize( array(
            'tag_name' => 'v' . $cached['version'], 'draft' => false, 'prerelease' => false,
            'html_url' => $cached['url'] ?? '', 'published_at' => $cached['published_at'] ?? '',
            'body' => $cached['body'] ?? '',
            'assets' => array( array( 'name' => self::SLUG . '-v' . $cached['version'] . '.zip', 'browser_download_url' => $cached['package'] ?? '' ) ),
        ) );
    }

    public static function normalize( $data ) {
        if ( ! is_array( $data ) || false !== ( $data['draft'] ?? null ) || false !== ( $data['prerelease'] ?? null ) ) {
            return array();
        }
        $tag = $data['tag_name'] ?? '';
        if ( ! is_string( $tag ) || ! preg_match( '/\Av((?:0|[1-9][0-9]*)\.(?:0|[1-9][0-9]*)\.(?:0|[1-9][0-9]*))\z/', $tag, $match ) ) {
            return array();
        }
        $base = 'https://github.com/' . self::REPOSITORY . '/releases/';
        if ( $base . 'tag/' . $tag !== ( $data['html_url'] ?? '' ) || ! isset( $data['assets'] ) || ! is_array( $data['assets'] ) ) {
            return array();
        }
        $name = self::SLUG . '-' . $tag . '.zip';
        $package = $base . 'download/' . $tag . '/' . $name;
        foreach ( $data['assets'] as $asset ) {
            if ( is_array( $asset ) && $name === ( $asset['name'] ?? '' ) && $package === ( $asset['browser_download_url'] ?? '' ) ) {
                return array(
                    'version' => $match[1], 'url' => $base . 'tag/' . $tag, 'package' => $package,
                    'published_at' => isset( $data['published_at'] ) && is_string( $data['published_at'] ) ? sanitize_text_field( $data['published_at'] ) : '',
                    'body' => isset( $data['body'] ) && is_string( $data['body'] ) ? $data['body'] : '',
                );
            }
        }
        return array();
    }
}
