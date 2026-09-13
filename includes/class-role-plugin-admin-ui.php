<?php
/**
 * Role Plugin - Admin UI & Settings Page
 *
 * Provides an intuitive admin interface to select roles,
 * customize capabilities, configure visible menus, and create new roles.
 *
 * @package RolePlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Role_Plugin_Admin_UI {

	const PAGE_SLUG = 'role-permissions-manager';

	/**
	 * Initialize admin UI hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_post_role_plugin_save_role', array( __CLASS__, 'handle_save_role' ) );
		add_action( 'admin_post_role_plugin_create_role', array( __CLASS__, 'handle_create_role' ) );
		add_action( 'admin_post_role_plugin_delete_role', array( __CLASS__, 'handle_delete_role' ) );
		add_action( 'admin_post_role_plugin_reset_defaults', array( __CLASS__, 'handle_reset_defaults' ) );
	}

	/**
	 * Register the admin menu entry.
	 */
	public static function add_admin_menu() {
		add_menu_page(
			__( 'Роли и Права', 'role-plugin' ),
			__( 'Роли и Права', 'role-plugin' ),
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' ),
			'dashicons-admin-users',
			71
		);
	}

	/**
	 * Enqueue stylesheet on our plugin page.
	 */
	public static function enqueue_assets( $hook ) {
		if ( false !== strpos( $hook, self::PAGE_SLUG ) ) {
			wp_enqueue_style(
				'role-plugin-admin-css',
				plugins_url( 'assets/admin.css', dirname( __FILE__ ) ),
				array(),
				'1.0.0'
			);
		}
	}

	/**
	 * Handle saving capabilities and menu visibility.
	 */
	public static function handle_save_role() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Нямате администраторски права.', 'role-plugin' ) );
		}

		check_admin_referer( 'role_plugin_save_role_action', 'role_plugin_save_role_nonce' );

		$role_slug = isset( $_POST['selected_role'] ) ? sanitize_key( $_POST['selected_role'] ) : '';
		if ( empty( $role_slug ) || ! get_role( $role_slug ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&error=invalid_role' ) );
			exit;
		}

		// 1. Update capabilities.
		$submitted_caps = isset( $_POST['caps'] ) && is_array( $_POST['caps'] ) ? $_POST['caps'] : array();
		Role_Plugin_Roles::update_role_caps( $role_slug, $submitted_caps );

		// 2. Update allowed menus.
		if ( 'administrator' !== $role_slug ) {
			$submitted_menus = isset( $_POST['allowed_menus'] ) && is_array( $_POST['allowed_menus'] ) ? array_map( 'sanitize_text_field', $_POST['allowed_menus'] ) : array();
			Role_Plugin_Roles::update_role_allowed_menus( $role_slug, $submitted_menus );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&role=' . rawurlencode( $role_slug ) . '&updated=1' ) );
		exit;
	}

	/**
	 * Handle creating a new custom role.
	 */
	public static function handle_create_role() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Нямате администраторски права.', 'role-plugin' ) );
		}

		check_admin_referer( 'role_plugin_create_role_action', 'role_plugin_create_role_nonce' );

		$slug       = isset( $_POST['new_role_slug'] ) ? sanitize_key( $_POST['new_role_slug'] ) : '';
		$name       = isset( $_POST['new_role_name'] ) ? sanitize_text_field( $_POST['new_role_name'] ) : '';
		$clone_from = isset( $_POST['clone_from_role'] ) ? sanitize_key( $_POST['clone_from_role'] ) : '';

		$result = Role_Plugin_Roles::create_custom_role( $slug, $name, $clone_from );

		if ( is_wp_error( $result ) ) {
			$error_code = rawurlencode( $result->get_error_message() );
			wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&role_error=' . $error_code ) );
			exit;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&role=' . rawurlencode( $slug ) . '&created=1' ) );
		exit;
	}

	/**
	 * Handle deleting a custom role.
	 */
	public static function handle_delete_role() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Нямате администраторски права.', 'role-plugin' ) );
		}

		check_admin_referer( 'role_plugin_delete_role_action', 'role_plugin_delete_role_nonce' );

		$slug = isset( $_POST['delete_role_slug'] ) ? sanitize_key( $_POST['delete_role_slug'] ) : '';

		$result = Role_Plugin_Roles::delete_role( $slug );

		if ( is_wp_error( $result ) ) {
			$error_code = rawurlencode( $result->get_error_message() );
			wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&role_error=' . $error_code ) );
			exit;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&deleted=1' ) );
		exit;
	}

	/**
	 * Handle resetting default roles.
	 */
	public static function handle_reset_defaults() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Нямате администраторски права.', 'role-plugin' ) );
		}

		check_admin_referer( 'role_plugin_reset_action', 'role_plugin_reset_nonce' );

		Role_Plugin_Roles::setup_initial_roles();

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&reset=1' ) );
		exit;
	}

	/**
	 * Render the main plugin settings page.
	 */
	public static function render_page() {
		$all_roles = Role_Plugin_Roles::get_all_roles();

		// Determine selected role.
		$selected_role_slug = isset( $_GET['role'] ) ? sanitize_key( $_GET['role'] ) : '';
		if ( empty( $selected_role_slug ) || ! isset( $all_roles[ $selected_role_slug ] ) ) {
			if ( isset( $all_roles['director'] ) ) {
				$selected_role_slug = 'director';
			} elseif ( isset( $all_roles['redactor'] ) ) {
				$selected_role_slug = 'redactor';
			} elseif ( isset( $all_roles['author'] ) ) {
				$selected_role_slug = 'author';
			} else {
				$selected_role_slug = key( $all_roles );
			}
		}

		$current_role_obj = get_role( $selected_role_slug );
		$current_role_caps = $current_role_obj ? (array) $current_role_obj->capabilities : array();
		$allowed_menus     = Role_Plugin_Roles::get_role_allowed_menus( $selected_role_slug );

		$definitions      = Role_Plugin_Roles::get_capability_definitions();
		$manageable_menus = Role_Plugin_Roles::get_manageable_menus();

		$is_builtin = in_array( $selected_role_slug, array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' ), true );
		$is_admin   = ( 'administrator' === $selected_role_slug );

		// Count users per role.
		$user_counts = count_users();
		$role_counts = $user_counts['avail_roles'] ?? array();
		?>
		<div class="wrap role-plugin-wrap">
			<h1 class="role-plugin-title">
				<span class="dashicons dashicons-admin-users"></span>
				<?php esc_html_e( 'Управление на Потребителски Роли и Права', 'role-plugin' ); ?>
			</h1>

			<?php if ( isset( $_GET['updated'] ) ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><strong><?php esc_html_e( 'Настройките за правата и менютата бяха успешно запазени!', 'role-plugin' ); ?></strong></p>
				</div>
			<?php endif; ?>

			<?php if ( isset( $_GET['created'] ) ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><strong><?php esc_html_e( 'Новата потребителска роля бе успешно създадена!', 'role-plugin' ); ?></strong></p>
				</div>
			<?php endif; ?>

			<?php if ( isset( $_GET['deleted'] ) ) : ?>
				<div class="notice notice-warning is-dismissible">
					<p><strong><?php esc_html_e( 'Ролята бе успешно изтрита.', 'role-plugin' ); ?></strong></p>
				</div>
			<?php endif; ?>

			<?php if ( isset( $_GET['reset'] ) ) : ?>
				<div class="notice notice-info is-dismissible">
					<p><strong><?php esc_html_e( 'Препоръчителните настройки за Director, Redactor и Author бяха възстановени!', 'role-plugin' ); ?></strong></p>
				</div>
			<?php endif; ?>

			<?php if ( isset( $_GET['role_error'] ) ) : ?>
				<div class="notice notice-error is-dismissible">
					<p><strong><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['role_error'] ) ) ); ?></strong></p>
				</div>
			<?php endif; ?>

			<!-- Role Selection Navigation Bar -->
			<div class="role-plugin-bar">
				<div class="role-plugin-selector-box">
					<label for="role-select" class="role-select-label">
						<strong><?php esc_html_e( 'Изберете роля за редактиране:', 'role-plugin' ); ?></strong>
					</label>
					<select id="role-select" class="role-plugin-select" onchange="window.location.href='admin.php?page=<?php echo esc_attr( self::PAGE_SLUG ); ?>&role=' + encodeURIComponent(this.value);">
						<?php foreach ( $all_roles as $slug => $role_data ) : ?>
							<?php
							$user_num = isset( $role_counts[ $slug ] ) ? $role_counts[ $slug ] : 0;
							$label    = translate_user_role( $role_data['name'] );
							?>
							<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $selected_role_slug, $slug ); ?>>
								<?php echo esc_html( $label . ' (' . $slug . ') — ' . $user_num . ' потребители' ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="role-plugin-pills">
					<?php
					$featured_roles = array( 'director', 'redactor', 'author', 'administrator' );
					foreach ( $featured_roles as $f_role ) :
						if ( ! isset( $all_roles[ $f_role ] ) ) {
							continue;
						}
						$is_active = ( $f_role === $selected_role_slug );
						$pill_url  = admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&role=' . $f_role );
						?>
						<a href="<?php echo esc_url( $pill_url ); ?>" class="role-pill <?php echo $is_active ? 'role-pill-active' : ''; ?>">
							<?php echo esc_html( translate_user_role( $all_roles[ $f_role ]['name'] ) ); ?>
							<span class="role-pill-count"><?php echo esc_html( $role_counts[ $f_role ] ?? 0 ); ?></span>
						</a>
					<?php endforeach; ?>
				</div>

				<div class="role-plugin-reset-box">
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Възстановяване на препоръчителните настройки за Director, Redactor и Author?');">
						<?php wp_nonce_field( 'role_plugin_reset_action', 'role_plugin_reset_nonce' ); ?>
						<input type="hidden" name="action" value="role_plugin_reset_defaults" />
						<button type="submit" class="button button-secondary">
							<span class="dashicons dashicons-update"></span>
							<?php esc_html_e( 'Възстанови по подразбиране', 'role-plugin' ); ?>
						</button>
					</form>
				</div>
			</div>

			<!-- Active Role Header -->
			<div class="role-header-banner">
				<div class="role-header-details">
					<span class="role-header-badge <?php echo $is_builtin ? 'badge-builtin' : 'badge-custom'; ?>">
						<?php echo $is_builtin ? esc_html__( 'Вградена роля', 'role-plugin' ) : esc_html__( 'Потребителска роля', 'role-plugin' ); ?>
					</span>
					<h2>
						<?php echo esc_html( translate_user_role( $all_roles[ $selected_role_slug ]['name'] ) ); ?>
						<code class="role-slug-tag"><?php echo esc_html( $selected_role_slug ); ?></code>
					</h2>
					<p class="role-summary-text">
						<?php
						if ( 'director' === $selected_role_slug ) {
							esc_html_e( 'Роля Директор: Вижда само Публикации, Файлове и Коментари. Може да редактира и трие всички публикации, файлове и коментари.', 'role-plugin' );
						} elseif ( 'redactor' === $selected_role_slug ) {
							esc_html_e( 'Роля Редактор: Вижда само Публикации, Файлове и Коментари. Може да редактира и трие всички публикации, файлове и коментари.', 'role-plugin' );
						} elseif ( 'author' === $selected_role_slug ) {
							esc_html_e( 'Роля Автор: Вижда само Публикации, Файлове и Коментари. Може да редактира и изтрива САМО своите публикации и файлове, и коментарите по своите публикации.', 'role-plugin' );
						} else {
							esc_html_e( 'Персонализирайте разрешенията и достъпа до менюта за тази роля.', 'role-plugin' );
						}
						?>
					</p>
				</div>

				<?php if ( ! $is_builtin && ! in_array( $selected_role_slug, array( 'director', 'redactor' ), true ) ) : ?>
					<div class="role-header-actions">
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Сигурни ли сте, че искате да изтриете тази роля?');">
							<?php wp_nonce_field( 'role_plugin_delete_role_action', 'role_plugin_delete_role_nonce' ); ?>
							<input type="hidden" name="action" value="role_plugin_delete_role" />
							<input type="hidden" name="delete_role_slug" value="<?php echo esc_attr( $selected_role_slug ); ?>" />
							<button type="submit" class="button button-link-delete">
								<span class="dashicons dashicons-trash"></span>
								<?php esc_html_e( 'Изтрий тази роля', 'role-plugin' ); ?>
							</button>
						</form>
					</div>
				<?php endif; ?>
			</div>

			<!-- Main Form -->
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="role-plugin-form">
				<?php wp_nonce_field( 'role_plugin_save_role_action', 'role_plugin_save_role_nonce' ); ?>
				<input type="hidden" name="action" value="role_plugin_save_role" />
				<input type="hidden" name="selected_role" value="<?php echo esc_attr( $selected_role_slug ); ?>" />

				<div class="role-plugin-grid">
					<!-- Left Column: Capabilities -->
					<div class="role-plugin-col-main">
						<h3 class="column-heading">
							<span class="dashicons dashicons-shield"></span>
							<?php esc_html_e( 'Разрешения и Права (Capabilities)', 'role-plugin' ); ?>
						</h3>

						<?php foreach ( $definitions as $group_key => $group ) : ?>
							<div class="cap-group-card">
								<div class="cap-group-header">
									<h4>
										<span class="dashicons <?php echo esc_attr( $group['icon'] ); ?>"></span>
										<?php echo esc_html( $group['title'] ); ?>
									</h4>
									<div class="cap-group-actions">
										<button type="button" class="button-link js-select-all-group" data-group="<?php echo esc_attr( $group_key ); ?>">
											<?php esc_html_e( 'Всички', 'role-plugin' ); ?>
										</button>
										<span>|</span>
										<button type="button" class="button-link js-deselect-all-group" data-group="<?php echo esc_attr( $group_key ); ?>">
											<?php esc_html_e( 'Никои', 'role-plugin' ); ?>
										</button>
									</div>
								</div>

								<div class="cap-group-body" data-group="<?php echo esc_attr( $group_key ); ?>">
									<?php foreach ( $group['caps'] as $cap_name => $cap_label ) : ?>
										<?php
										$is_checked = ! empty( $current_role_caps[ $cap_name ] );
										$is_disabled = ( $is_admin && in_array( $cap_name, array( 'manage_options', 'read' ), true ) );
										?>
										<label class="cap-checkbox-row <?php echo $is_checked ? 'is-checked' : ''; ?>">
											<input
												type="checkbox"
												name="caps[<?php echo esc_attr( $cap_name ); ?>]"
												value="1"
												class="cap-checkbox js-group-<?php echo esc_attr( $group_key ); ?>"
												<?php checked( $is_checked ); ?>
												<?php disabled( $is_disabled ); ?>
											/>
											<span class="cap-label-content">
												<span class="cap-title-text"><?php echo esc_html( $cap_label ); ?></span>
												<code class="cap-code"><?php echo esc_html( $cap_name ); ?></code>
											</span>
										</label>
									<?php endforeach; ?>
								</div>
							</div>
						<?php endforeach; ?>
					</div>

					<!-- Right Column: Menu Restrictions & Submit -->
					<div class="role-plugin-col-sidebar">
						<div class="sidebar-box sticky-sidebar">
							<div class="sidebar-box-header">
								<h3>
									<span class="dashicons dashicons-menu"></span>
									<?php esc_html_e( 'Видими Менюта в Панела', 'role-plugin' ); ?>
								</h3>
							</div>

							<div class="sidebar-box-content">
								<?php if ( $is_admin ) : ?>
									<div class="notice notice-info inline">
										<p><?php esc_html_e( 'Администраторът има пълен и неограничен достъп до всички менюта в системата.', 'role-plugin' ); ?></p>
									</div>
								<?php else : ?>
									<p class="description">
										<?php esc_html_e( 'Изберете кои менюта в администрацията ще вижда потребителят. Неотметнатите менюта се скриват напълно и достъпът до тях през URL се блокира.', 'role-plugin' ); ?>
									</p>

									<div class="menu-checkbox-list">
										<?php foreach ( $manageable_menus as $menu_key => $menu_meta ) : ?>
											<?php
											$checked = ( null === $allowed_menus ) || in_array( $menu_key, $allowed_menus, true );
											?>
											<label class="menu-checkbox-row">
												<input
													type="checkbox"
													name="allowed_menus[]"
													value="<?php echo esc_attr( $menu_key ); ?>"
													<?php checked( $checked ); ?>
												/>
												<span class="dashicons <?php echo esc_attr( $menu_meta['icon'] ); ?>"></span>
												<span class="menu-title-label"><?php echo esc_html( $menu_meta['title'] ); ?></span>
											</label>
										<?php endforeach; ?>
									</div>
								<?php endif; ?>

								<div class="sidebar-submit-box">
									<button type="submit" class="button button-primary button-hero button-save-roles">
										<span class="dashicons dashicons-saved"></span>
										<?php esc_html_e( 'Запази промените', 'role-plugin' ); ?>
									</button>
								</div>
							</div>
						</div>
					</div>
				</div>
			</form>

			<!-- New Role Creator Card -->
			<div class="new-role-card">
				<div class="new-role-header">
					<h3>
						<span class="dashicons dashicons-plus-alt"></span>
						<?php esc_html_e( 'Създаване на нова потребителска роля', 'role-plugin' ); ?>
					</h3>
				</div>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="new-role-form">
					<?php wp_nonce_field( 'role_plugin_create_role_action', 'role_plugin_create_role_nonce' ); ?>
					<input type="hidden" name="action" value="role_plugin_create_role" />

					<div class="new-role-fields">
						<div class="field-item">
							<label for="new_role_slug"><strong><?php esc_html_e( 'Код на ролята (slug):', 'role-plugin' ); ?></strong></label>
							<input type="text" id="new_role_slug" name="new_role_slug" placeholder="e.g. journalist" required class="regular-text" />
							<span class="description"><?php esc_html_e( 'Малки латински букви без интервали.', 'role-plugin' ); ?></span>
						</div>

						<div class="field-item">
							<label for="new_role_name"><strong><?php esc_html_e( 'Име за показване:', 'role-plugin' ); ?></strong></label>
							<input type="text" id="new_role_name" name="new_role_name" placeholder="e.g. Журналист (Journalist)" required class="regular-text" />
							<span class="description"><?php esc_html_e( 'Име, което ще се вижда в списъка с роли.', 'role-plugin' ); ?></span>
						</div>

						<div class="field-item">
							<label for="clone_from_role"><strong><?php esc_html_e( 'Копирай права от съществуваща роля:', 'role-plugin' ); ?></strong></label>
							<select id="clone_from_role" name="clone_from_role">
								<option value=""><?php esc_html_e( '-- Изчистени основни права --', 'role-plugin' ); ?></option>
								<?php foreach ( $all_roles as $slug => $role_data ) : ?>
									<option value="<?php echo esc_attr( $slug ); ?>">
										<?php echo esc_html( translate_user_role( $role_data['name'] ) . ' (' . $slug . ')' ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>

						<div class="field-item field-item-btn">
							<button type="submit" class="button button-secondary">
								<span class="dashicons dashicons-insert"></span>
								<?php esc_html_e( 'Създай ролята', 'role-plugin' ); ?>
							</button>
						</div>
					</div>
				</form>
			</div>
		</div>

		<script>
		document.addEventListener('DOMContentLoaded', function() {
			// Toggle highlight on row click
			document.querySelectorAll('.cap-checkbox').forEach(function(cb) {
				cb.addEventListener('change', function() {
					if (this.checked) {
						this.closest('.cap-checkbox-row').classList.add('is-checked');
					} else {
						this.closest('.cap-checkbox-row').classList.remove('is-checked');
					}
				});
			});

			// Select all in group
			document.querySelectorAll('.js-select-all-group').forEach(function(btn) {
				btn.addEventListener('click', function(e) {
					e.preventDefault();
					var grp = this.getAttribute('data-group');
					document.querySelectorAll('.js-group-' + grp).forEach(function(cb) {
						if (!cb.disabled) {
							cb.checked = true;
							cb.closest('.cap-checkbox-row').classList.add('is-checked');
						}
					});
				});
			});

			// Deselect all in group
			document.querySelectorAll('.js-deselect-all-group').forEach(function(btn) {
				btn.addEventListener('click', function(e) {
					e.preventDefault();
					var grp = this.getAttribute('data-group');
					document.querySelectorAll('.js-group-' + grp).forEach(function(cb) {
						if (!cb.disabled) {
							cb.checked = false;
							cb.closest('.cap-checkbox-row').classList.remove('is-checked');
						}
					});
				});
			});
		});
		</script>
		<?php
	}
}
