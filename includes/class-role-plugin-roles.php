<?php
/**
 * Role Plugin - Roles and Capabilities Manager
 *
 * Handles creation, capability management, and persistence for user roles.
 *
 * @package RolePlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Role_Plugin_Roles {

	const MENU_RESTRICTIONS_OPTION = 'role_plugin_menu_restrictions';

	/**
	 * Default allowed menus for director, redactor, and author.
	 */
	public static function get_default_allowed_menus() {
		return array(
			'edit.php',           // Публикации
			'upload.php',         // Медия / Файлове
			'edit-comments.php',  // Коментари
		);
	}

	/**
	 * Setup and seed the initial roles and permissions.
	 */
	public static function setup_initial_roles() {
		// Collect all known capabilities to allow clean resets.
		$definitions = self::get_capability_definitions();
		$known_caps  = array();
		foreach ( $definitions as $group ) {
			foreach ( array_keys( $group['caps'] ) as $cap ) {
				$known_caps[] = $cap;
			}
		}

		// 1. Setup Director role.
		$director_caps = array(
			'read'                   => true,
			'upload_files'           => true,
			'edit_posts'             => true,
			'edit_others_posts'      => true,
			'edit_published_posts'   => true,
			'publish_posts'          => true,
			'delete_posts'           => true,
			'delete_others_posts'    => true,
			'delete_published_posts' => true,
			'delete_private_posts'   => true,
			'edit_private_posts'     => true,
			'read_private_posts'     => true,
			'manage_categories'      => true,
			'moderate_comments'      => true,
		);

		if ( ! get_role( 'director' ) ) {
			add_role( 'director', __( 'Директор (Director)', 'role-plugin' ), $director_caps );
		} else {
			$role = get_role( 'director' );
			foreach ( $known_caps as $cap ) {
				if ( ! empty( $director_caps[ $cap ] ) ) {
					$role->add_cap( $cap, true );
				} else {
					$role->remove_cap( $cap );
				}
			}
		}

		// 2. Setup Redactor role.
		$redactor_caps = $director_caps; // Can edit and delete all posts, files, comments.

		if ( ! get_role( 'redactor' ) ) {
			add_role( 'redactor', __( 'Редактор (Redactor)', 'role-plugin' ), $redactor_caps );
		} else {
			$role = get_role( 'redactor' );
			foreach ( $known_caps as $cap ) {
				if ( ! empty( $redactor_caps[ $cap ] ) ) {
					$role->add_cap( $cap, true );
				} else {
					$role->remove_cap( $cap );
				}
			}
		}

		// 3. Setup Author role (ensure restricted to own posts & files).
		$author_caps = array(
			'read'                   => true,
			'upload_files'           => true,
			'edit_posts'             => true,
			'publish_posts'          => true,
			'delete_posts'           => true,
			'edit_published_posts'   => true,
			'delete_published_posts' => true,
		);

		$author_role = get_role( 'author' );
		if ( ! $author_role ) {
			add_role( 'author', __( 'Автор (Author)', 'role-plugin' ), $author_caps );
		} else {
			foreach ( $known_caps as $cap ) {
				if ( ! empty( $author_caps[ $cap ] ) ) {
					$author_role->add_cap( $cap, true );
				} else {
					$author_role->remove_cap( $cap );
				}
			}
		}

		// 4. Setup default menu restrictions in options.
		$restrictions = get_option( self::MENU_RESTRICTIONS_OPTION, array() );
		$restrictions['director'] = self::get_default_allowed_menus();
		$restrictions['redactor'] = self::get_default_allowed_menus();
		$restrictions['author']   = self::get_default_allowed_menus();
		update_option( self::MENU_RESTRICTIONS_OPTION, $restrictions );
	}

	/**
	 * Get all registered WordPress roles.
	 */
	public static function get_all_roles() {
		global $wp_roles;
		if ( ! isset( $wp_roles ) ) {
			$wp_roles = new WP_Roles();
		}
		return $wp_roles->roles;
	}

	/**
	 * Get available capabilities grouped by module.
	 */
	public static function get_capability_definitions() {
		return array(
			'posts' => array(
				'title' => __( 'Публикации (Posts)', 'role-plugin' ),
				'icon'  => 'dashicons-admin-post',
				'caps'  => array(
					'edit_posts'             => __( 'Създаване и редакция на собствени публикации (Edit Own Posts)', 'role-plugin' ),
					'edit_others_posts'      => __( 'Редакция на чужди публикации (Edit Others Posts)', 'role-plugin' ),
					'publish_posts'          => __( 'Публикуване на публикации (Publish Posts)', 'role-plugin' ),
					'edit_published_posts'   => __( 'Редакция на вече публикувани (Edit Published Posts)', 'role-plugin' ),
					'delete_posts'           => __( 'Изтриване на собствени публикации (Delete Own Posts)', 'role-plugin' ),
					'delete_others_posts'    => __( 'Изтриване на чужди публикации (Delete Others Posts)', 'role-plugin' ),
					'delete_published_posts' => __( 'Изтриване на публикувани (Delete Published Posts)', 'role-plugin' ),
					'read_private_posts'     => __( 'Преглед на лични публикации (Read Private Posts)', 'role-plugin' ),
					'edit_private_posts'     => __( 'Редакция на лични публикации (Edit Private Posts)', 'role-plugin' ),
					'delete_private_posts'   => __( 'Изтриване на лични публикации (Delete Private Posts)', 'role-plugin' ),
					'manage_categories'      => __( 'Управление на категории и етикети (Manage Categories)', 'role-plugin' ),
				),
			),
			'media' => array(
				'title' => __( 'Файлове и Медия (Files & Media)', 'role-plugin' ),
				'icon'  => 'dashicons-admin-media',
				'caps'  => array(
					'upload_files' => __( 'Качване и управление на файлове (Upload Files)', 'role-plugin' ),
				),
			),
			'comments' => array(
				'title' => __( 'Коментари (Comments)', 'role-plugin' ),
				'icon'  => 'dashicons-admin-comments',
				'caps'  => array(
					'moderate_comments' => __( 'Модериране на ВСИЧКИ коментари в сайта (Moderate All Comments). Забележка: Авторите без това право управляват само коментарите по собствените си публикации.', 'role-plugin' ),
				),
			),
			'pages' => array(
				'title' => __( 'Страници (Pages)', 'role-plugin' ),
				'icon'  => 'dashicons-admin-page',
				'caps'  => array(
					'edit_pages'             => __( 'Редакция на собствени страници (Edit Pages)', 'role-plugin' ),
					'edit_others_pages'      => __( 'Редакция на чужди страници (Edit Others Pages)', 'role-plugin' ),
					'publish_pages'          => __( 'Публикуване на страници (Publish Pages)', 'role-plugin' ),
					'edit_published_pages'   => __( 'Редакция на публикувани страници (Edit Published Pages)', 'role-plugin' ),
					'delete_pages'           => __( 'Изтриване на собствени страници (Delete Pages)', 'role-plugin' ),
					'delete_others_pages'    => __( 'Изтриване на чужди страници (Delete Others Pages)', 'role-plugin' ),
					'delete_published_pages' => __( 'Изтриване на публикувани страници (Delete Published Pages)', 'role-plugin' ),
					'read_private_pages'     => __( 'Преглед на лични страници (Read Private Pages)', 'role-plugin' ),
				),
			),
			'users' => array(
				'title' => __( 'Потребители (Users)', 'role-plugin' ),
				'icon'  => 'dashicons-admin-users',
				'caps'  => array(
					'list_users'   => __( 'Преглед на списъка с потребители (List Users)', 'role-plugin' ),
					'create_users' => __( 'Създаване на потребители (Create Users)', 'role-plugin' ),
					'edit_users'   => __( 'Редакция на потребители (Edit Users)', 'role-plugin' ),
					'delete_users' => __( 'Изтриване на потребители (Delete Users)', 'role-plugin' ),
				),
			),
			'admin' => array(
				'title' => __( 'Администрация и система (Administration)', 'role-plugin' ),
				'icon'  => 'dashicons-admin-settings',
				'caps'  => array(
					'read'               => __( 'Достъп до административния панел (Read / Admin Access)', 'role-plugin' ),
					'manage_options'     => __( 'Управление на настройките на сайта (Manage Options)', 'role-plugin' ),
					'switch_themes'      => __( 'Смяна на теми (Switch Themes)', 'role-plugin' ),
					'edit_theme_options' => __( 'Персонализиране на тема и менюта (Edit Theme Options)', 'role-plugin' ),
					'activate_plugins'   => __( 'Активиране на плъгини (Activate Plugins)', 'role-plugin' ),
				),
			),
		);
	}

	/**
	 * Get list of configurable admin menu items.
	 */
	public static function get_manageable_menus() {
		return array(
			'edit.php'                => array(
				'title' => __( 'Публикации (Posts)', 'role-plugin' ),
				'icon'  => 'dashicons-admin-post',
			),
			'upload.php'              => array(
				'title' => __( 'Медия / Файлове (Media)', 'role-plugin' ),
				'icon'  => 'dashicons-admin-media',
			),
			'edit-comments.php'       => array(
				'title' => __( 'Коментари (Comments)', 'role-plugin' ),
				'icon'  => 'dashicons-admin-comments',
			),
			'edit.php?post_type=page' => array(
				'title' => __( 'Страници (Pages)', 'role-plugin' ),
				'icon'  => 'dashicons-admin-page',
			),
			'themes.php'              => array(
				'title' => __( 'Външен вид (Themes)', 'role-plugin' ),
				'icon'  => 'dashicons-admin-appearance',
			),
			'plugins.php'             => array(
				'title' => __( 'Разширения (Plugins)', 'role-plugin' ),
				'icon'  => 'dashicons-admin-plugins',
			),
			'users.php'               => array(
				'title' => __( 'Потребители (Users)', 'role-plugin' ),
				'icon'  => 'dashicons-admin-users',
			),
			'tools.php'               => array(
				'title' => __( 'Инструменти (Tools)', 'role-plugin' ),
				'icon'  => 'dashicons-admin-tools',
			),
			'options-general.php'     => array(
				'title' => __( 'Настройки (Settings)', 'role-plugin' ),
				'icon'  => 'dashicons-admin-settings',
			),
		);
	}

	/**
	 * Get allowed menus for a role. Returns null if unrestricted.
	 */
	public static function get_role_allowed_menus( $role_slug ) {
		if ( 'administrator' === $role_slug ) {
			return null; // Administrator is never restricted.
		}

		$restrictions = get_option( self::MENU_RESTRICTIONS_OPTION, array() );
		if ( isset( $restrictions[ $role_slug ] ) && is_array( $restrictions[ $role_slug ] ) ) {
			return $restrictions[ $role_slug ];
		}

		// If director, redactor, author and not set yet, return default.
		if ( in_array( $role_slug, array( 'director', 'redactor', 'author' ), true ) ) {
			return self::get_default_allowed_menus();
		}

		return null;
	}

	/**
	 * Save updated capabilities for a role.
	 */
	public static function update_role_caps( $role_slug, $submitted_caps ) {
		$role = get_role( $role_slug );
		if ( ! $role ) {
			return false;
		}

		// Prevent removing manage_options or read from administrator.
		if ( 'administrator' === $role_slug ) {
			$submitted_caps['manage_options'] = true;
			$submitted_caps['read']           = true;
		}

		$definitions = self::get_capability_definitions();
		$known_caps  = array();
		foreach ( $definitions as $group ) {
			foreach ( array_keys( $group['caps'] ) as $cap ) {
				$known_caps[] = $cap;
			}
		}

		// Ensure 'read' is kept so user can log in.
		if ( ! empty( $submitted_caps ) && ! isset( $submitted_caps['read'] ) && 'administrator' !== $role_slug ) {
			$submitted_caps['read'] = true;
		}

		foreach ( $known_caps as $cap ) {
			if ( ! empty( $submitted_caps[ $cap ] ) ) {
				$role->add_cap( $cap, true );
			} else {
				$role->remove_cap( $cap );
			}
		}

		return true;
	}

	/**
	 * Save allowed admin menus for a role.
	 */
	public static function update_role_allowed_menus( $role_slug, $allowed_menus ) {
		if ( 'administrator' === $role_slug ) {
			return true;
		}

		$restrictions = get_option( self::MENU_RESTRICTIONS_OPTION, array() );
		$restrictions[ $role_slug ] = is_array( $allowed_menus ) ? array_values( $allowed_menus ) : array();
		update_option( self::MENU_RESTRICTIONS_OPTION, $restrictions );

		return true;
	}

	/**
	 * Create a new custom role.
	 */
	public static function create_custom_role( $slug, $display_name, $clone_from = '' ) {
		$slug         = sanitize_key( $slug );
		$display_name = sanitize_text_field( $display_name );

		if ( empty( $slug ) || empty( $display_name ) ) {
			return new WP_Error( 'empty_fields', __( 'Моля попълнете код и име на ролята.', 'role-plugin' ) );
		}

		if ( get_role( $slug ) ) {
			return new WP_Error( 'role_exists', __( 'Роля с такъв код вече съществува.', 'role-plugin' ) );
		}

		$base_caps = array( 'read' => true );
		if ( ! empty( $clone_from ) ) {
			$base_role_obj = get_role( $clone_from );
			if ( $base_role_obj && ! empty( $base_role_obj->capabilities ) ) {
				$base_caps = $base_role_obj->capabilities;
			}
		}

		$result = add_role( $slug, $display_name, $base_caps );
		if ( ! $result ) {
			return new WP_Error( 'creation_failed', __( 'Възникна грешка при създаването на ролята.', 'role-plugin' ) );
		}

		// Copy menu restrictions if cloning.
		if ( ! empty( $clone_from ) ) {
			$clone_menus = self::get_role_allowed_menus( $clone_from );
			if ( null !== $clone_menus ) {
				self::update_role_allowed_menus( $slug, $clone_menus );
			}
		}

		return true;
	}

	/**
	 * Delete a custom role (built-in WP roles are protected).
	 */
	public static function delete_role( $slug ) {
		$protected = array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' );
		if ( in_array( $slug, $protected, true ) ) {
			return new WP_Error( 'protected_role', __( 'Вградените стандартни роли не могат да бъдат изтривани.', 'role-plugin' ) );
		}

		remove_role( $slug );

		$restrictions = get_option( self::MENU_RESTRICTIONS_OPTION, array() );
		if ( isset( $restrictions[ $slug ] ) ) {
			unset( $restrictions[ $slug ] );
			update_option( self::MENU_RESTRICTIONS_OPTION, $restrictions );
		}

		return true;
	}
}
