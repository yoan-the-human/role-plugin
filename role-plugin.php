<?php
/**
 * Plugin Name: Role & Permissions Manager (Роли и Права)
 * Plugin URI:  http://matritsata.local
 * Description: Управление на потребителски роли и права. Конфигурира ролите Director (Директор), Redactor (Редактор) и Author (Автор) с ограничение на видимостта на менютата и управление на публикации, файлове и коментари.
 * Version:     1.0.0
 * Author:      Matritsata
 * Text Domain: role-plugin
 *
 * @package RolePlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ROLE_PLUGIN_VERSION', '1.0.0' );
define( 'ROLE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ROLE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Load classes.
require_once ROLE_PLUGIN_DIR . 'includes/class-role-plugin-roles.php';
require_once ROLE_PLUGIN_DIR . 'includes/class-role-plugin-restrictions.php';
require_once ROLE_PLUGIN_DIR . 'includes/class-role-plugin-admin-ui.php';

// Register activation hook.
register_activation_hook( __FILE__, array( 'Role_Plugin_Roles', 'setup_initial_roles' ) );

// Initialize plugin.
add_action( 'plugins_loaded', function() {
	Role_Plugin_Restrictions::init();
	Role_Plugin_Admin_UI::init();

	// Auto-seed if director role does not exist yet.
	if ( ! get_role( 'director' ) || ! get_role( 'redactor' ) ) {
		Role_Plugin_Roles::setup_initial_roles();
	}
} );

// Add quick settings link in the WordPress Plugins table.
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function( $links ) {
	$settings_link = sprintf(
		'<a href="%s">%s</a>',
		esc_url( admin_url( 'admin.php?page=role-permissions-manager' ) ),
		esc_html__( 'Роли и Права', 'role-plugin' )
	);
	array_unshift( $links, $settings_link );
	return $links;
} );
