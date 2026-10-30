<?php
/**
 * Plugin Name: CookieYes Integration
 * Description: Connect any WordPress site to its CookieYes banner with a site-specific website key.
 * Version: 0.3.1
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Amsive
 * Plugin URI: https://github.com/Amsive-Digital/wp-cookieyes-integration
 * Update URI: https://github.com/Amsive-Digital/wp-cookieyes-integration/
 * License: GPL-2.0-or-later
 * Text Domain: cookieyes-integration
 * Domain Path: /languages
 */

namespace Amsive\CookieYes;

defined( 'ABSPATH' ) || exit;

define( 'COOKIEYES_INTEGRATION_VERSION', '0.3.1' );
define( 'COOKIEYES_INTEGRATION_FILE', __FILE__ );

require_once __DIR__ . '/includes/class-updater.php';
Updater::register();

require_once __DIR__ . '/includes/class-settings.php';
require_once __DIR__ . '/includes/class-diagnostics.php';
require_once __DIR__ . '/includes/class-plugin.php';

$amsive_cookieyes = new Plugin();
$amsive_cookieyes->register();
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $amsive_cookieyes, 'action_links' ) );
