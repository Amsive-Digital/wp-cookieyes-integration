<?php
/**
 * Retain configuration on deletion for safe reinstall and rollback.
 * Administrators may delete the amsive_cookieyes_settings option explicitly.
 */
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Release metadata is disposable; keep the user's configured website key.
delete_transient( 'cookieyes_integration_release' );
