<?php
/**
 * Role Plugin - Restrictions & Access Control
 *
 * Enforces admin menu visibility, direct URL access protection,
 * scoping posts/files to the current author, and author comment ownership.
 *
 * @package RolePlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Role_Plugin_Restrictions {

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'filter_admin_menus' ), 9999 );
		add_action( 'admin_init', array( __CLASS__, 'protect_admin_screens' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'filter_own_posts_and_media' ) );
		add_filter( 'ajax_query_attachments_args', array( __CLASS__, 'filter_ajax_attachments' ) );
		add_filter( 'comments_clauses', array( __CLASS__, 'filter_comments_clauses' ), 10, 2 );
		add_filter( 'wp_count_comments', array( __CLASS__, 'filter_comment_counts' ), 10, 2 );
		add_filter( 'map_meta_cap', array( __CLASS__, 'filter_comment_meta_caps' ), 10, 4 );
		add_filter( 'login_redirect', array( __CLASS__, 'custom_login_redirect' ), 10, 3 );
	}

	/**
	 * Get primary role of a user.
	 */
	public static function get_current_user_primary_role() {
		if ( ! is_user_logged_in() ) {
			return '';
		}
		$user = wp_get_current_user();
		return ! empty( $user->roles ) ? reset( $user->roles ) : '';
	}

	/**
	 * Filter admin menus based on role restrictions.
	 */
	public static function filter_admin_menus() {
		if ( current_user_can( 'manage_options' ) ) {
			return; // Never restrict administrators.
		}

		$role_slug     = self::get_current_user_primary_role();
		$allowed_menus = Role_Plugin_Roles::get_role_allowed_menus( $role_slug );

		if ( null === $allowed_menus ) {
			return; // Unrestricted role.
		}

		global $menu, $submenu;

		// Always keep profile.php so user can manage own account.
		$always_allowed = array( 'profile.php' );

		if ( ! empty( $menu ) && is_array( $menu ) ) {
			foreach ( $menu as $index => $item ) {
				$menu_slug = $item[2];

				// If it's a separator, keep or remove gracefully.
				if ( false !== strpos( $menu_slug, 'separator' ) ) {
					continue;
				}

				// Check if this menu or its base is allowed.
				$is_allowed = false;
				if ( in_array( $menu_slug, $always_allowed, true ) || in_array( $menu_slug, $allowed_menus, true ) ) {
					$is_allowed = true;
				}

				// Specifically handle edit.php vs edit.php?post_type=page.
				if ( 'edit.php?post_type=page' === $menu_slug && ! in_array( 'edit.php?post_type=page', $allowed_menus, true ) ) {
					$is_allowed = false;
				}

				if ( ! $is_allowed ) {
					unset( $menu[ $index ] );
				}
			}
		}

		// Ensure dashboard (index.php) is removed if not explicitly allowed.
		if ( ! in_array( 'index.php', $allowed_menus, true ) ) {
			remove_menu_page( 'index.php' );
		}
	}

	/**
	 * Protect admin screens against direct URL navigation.
	 */
	public static function protect_admin_screens() {
		if ( ! is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		if ( current_user_can( 'manage_options' ) ) {
			return; // Administrators are never blocked.
		}

		$role_slug     = self::get_current_user_primary_role();
		$allowed_menus = Role_Plugin_Roles::get_role_allowed_menus( $role_slug );

		if ( null === $allowed_menus ) {
			return;
		}

		global $pagenow;

		// If on dashboard, redirect to first allowed screen (typically edit.php).
		if ( 'index.php' === $pagenow && ! in_array( 'index.php', $allowed_menus, true ) ) {
			$fallback = in_array( 'edit.php', $allowed_menus, true ) ? 'edit.php' : 'profile.php';
			wp_safe_redirect( admin_url( $fallback ) );
			exit;
		}

		// Allowed core files.
		$always_allowed_files = array(
			'profile.php',
			'user-edit.php',
			'async-upload.php',
			'admin-post.php',
		);

		if ( in_array( $pagenow, $always_allowed_files, true ) ) {
			return;
		}

		// Check post type restrictions (e.g. Pages).
		$post_type = isset( $_GET['post_type'] ) ? sanitize_key( $_GET['post_type'] ) : '';
		if ( empty( $post_type ) && isset( $_GET['post'] ) ) {
			$post_type = get_post_type( (int) $_GET['post'] );
		}

		if ( 'page' === $post_type && ! in_array( 'edit.php?post_type=page', $allowed_menus, true ) ) {
			wp_die(
				esc_html__( 'Нямате права за достъп до страницата.', 'role-plugin' ),
				esc_html__( 'Отказан достъп', 'role-plugin' ),
				array( 'response' => 403, 'back_link' => true )
			);
		}

		// Check standard disallowed admin pages.
		$restricted_screens = array(
			'themes.php',
			'theme-editor.php',
			'plugins.php',
			'plugin-install.php',
			'plugin-editor.php',
			'users.php',
			'user-new.php',
			'tools.php',
			'import.php',
			'export.php',
			'site-health.php',
			'options-general.php',
			'options-writing.php',
			'options-reading.php',
			'options-discussion.php',
			'options-media.php',
			'options-permalink.php',
			'options-privacy.php',
			'options.php',
		);

		if ( in_array( $pagenow, $restricted_screens, true ) ) {
			wp_safe_redirect( admin_url( 'edit.php' ) );
			exit;
		}
	}

	/**
	 * Redirect user to edit.php on login if dashboard is restricted.
	 */
	public static function custom_login_redirect( $redirect_to, $requested_redirect_to, $user ) {
		if ( ! is_wp_error( $user ) && isset( $user->roles ) && is_array( $user->roles ) ) {
			if ( in_array( 'administrator', $user->roles, true ) ) {
				return $redirect_to;
			}

			$role          = reset( $user->roles );
			$allowed_menus = Role_Plugin_Roles::get_role_allowed_menus( $role );

			if ( null !== $allowed_menus && ! in_array( 'index.php', $allowed_menus, true ) ) {
				if ( empty( $requested_redirect_to ) || admin_url() === $requested_redirect_to || admin_url( 'index.php' ) === $requested_redirect_to ) {
					return admin_url( 'edit.php' );
				}
			}
		}
		return $redirect_to;
	}

	/**
	 * Scopes posts and media to author's own items if they cannot edit others.
	 */
	public static function filter_own_posts_and_media( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( current_user_can( 'edit_others_posts' ) ) {
			return; // Director, Redactor, Admin can see and manage all posts & media.
		}

		global $pagenow;

		// On Posts list table (edit.php).
		if ( 'edit.php' === $pagenow && ( ! isset( $_GET['post_type'] ) || 'post' === $_GET['post_type'] ) ) {
			$query->set( 'author', get_current_user_id() );
		}

		// On Media library list table (upload.php).
		if ( 'upload.php' === $pagenow ) {
			$query->set( 'author', get_current_user_id() );
		}
	}

	/**
	 * Scopes media modal and AJAX attachment queries for authors.
	 */
	public static function filter_ajax_attachments( $query ) {
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			$query['author'] = get_current_user_id();
		}
		return $query;
	}

	/**
	 * Filters comment queries so Authors only see comments on their own posts.
	 */
	public static function filter_comments_clauses( $clauses, $wp_comment_query ) {
		if ( ! is_admin() ) {
			return $clauses;
		}

		// If user can edit others' posts (Director, Redactor, Admin), they see all comments.
		if ( current_user_can( 'edit_others_posts' ) ) {
			return $clauses;
		}

		$current_user_id = get_current_user_id();
		if ( ! $current_user_id ) {
			return $clauses;
		}

		global $wpdb;

		// Join posts table if not already joined.
		if ( false === strpos( $clauses['join'], "{$wpdb->posts}" ) ) {
			$clauses['join'] .= " JOIN {$wpdb->posts} AS rpp_posts ON rpp_posts.ID = {$wpdb->comments}.comment_post_ID ";
			$clauses['where'] .= $wpdb->prepare( " AND rpp_posts.post_author = %d ", $current_user_id );
		} else {
			$clauses['where'] .= $wpdb->prepare( " AND {$wpdb->posts}.post_author = %d ", $current_user_id );
		}

		return $clauses;
	}

	/**
	 * Recalculates comment moderation counts for Authors so it matches only their own posts.
	 */
	public static function filter_comment_counts( $stats, $post_id ) {
		if ( ! is_admin() || 0 !== (int) $post_id ) {
			return $stats;
		}

		if ( current_user_can( 'edit_others_posts' ) ) {
			return $stats; // Director, Redactor, Admin see full stats.
		}

		$current_user_id = get_current_user_id();
		if ( ! $current_user_id ) {
			return $stats;
		}

		global $wpdb;

		$sql = "SELECT comment_approved, COUNT( * ) AS num_comments
				FROM {$wpdb->comments}
				JOIN {$wpdb->posts} ON {$wpdb->posts}.ID = {$wpdb->comments}.comment_post_ID
				WHERE {$wpdb->posts}.post_author = %d
				GROUP BY comment_approved";

		$results = $wpdb->get_results( $wpdb->prepare( $sql, $current_user_id ) );

		$custom_stats = array(
			'approved'            => 0,
			'awaiting_moderation' => 0,
			'moderated'           => 0,
			'spam'                => 0,
			'trash'               => 0,
			'post-trashed'        => 0,
			'total_comments'      => 0,
			'all'                 => 0,
		);

		foreach ( (array) $results as $row ) {
			switch ( $row->comment_approved ) {
				case 'trash':
					$custom_stats['trash'] = (int) $row->num_comments;
					break;
				case 'post-trashed':
					$custom_stats['post-trashed'] = (int) $row->num_comments;
					break;
				case 'spam':
					$custom_stats['spam']            = (int) $row->num_comments;
					$custom_stats['total_comments'] += (int) $row->num_comments;
					break;
				case '1':
					$custom_stats['approved']        = (int) $row->num_comments;
					$custom_stats['total_comments'] += (int) $row->num_comments;
					$custom_stats['all']            += (int) $row->num_comments;
					break;
				case '0':
					$custom_stats['moderated']           = (int) $row->num_comments;
					$custom_stats['awaiting_moderation'] = (int) $row->num_comments;
					$custom_stats['total_comments']     += (int) $row->num_comments;
					$custom_stats['all']                += (int) $row->num_comments;
					break;
			}
		}

		return (object) $custom_stats;
	}

	/**
	 * Ensure Authors have full comment editing/deleting rights for comments on their own posts.
	 */
	public static function filter_comment_meta_caps( $caps, $cap, $user_id, $args ) {
		if ( 'edit_comment' === $cap && ! empty( $args[0] ) ) {
			$comment = get_comment( $args[0] );
			if ( $comment ) {
				$post = get_post( $comment->comment_post_ID );
				if ( $post && (int) $post->post_author === (int) $user_id ) {
					// User is the post author: grant permission!
					return array( 'edit_posts' );
				}
			}
		}
		return $caps;
	}
}
