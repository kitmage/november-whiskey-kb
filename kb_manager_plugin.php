<?php
/**
 * Plugin Name: KB Manager
 * Description: Knowledge Base post type + hierarchical sections taxonomy + parent-child article support + manual ordering + KB Editor role + restricted media access.
 * Version: 1.1.0
 * Author: Kitmage.com
 * License: GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'KB_Manager_Plugin' ) ) {
	final class KB_Manager_Plugin {
		const VERSION            = '1.1.0';
		const ROLE               = 'kb_editor';
		const POST_TYPE          = 'kb_article';
		const TAXONOMY           = 'kb_section';
		const TERM_ORDER_META    = '_kb_section_order';
		const OPTION_VERSION_KEY = 'kb_manager_plugin_version';

		public static function init() {
			add_action( 'init', array( __CLASS__, 'register_post_type' ) );
			add_action( 'init', array( __CLASS__, 'register_taxonomy' ) );
			add_action( 'init', array( __CLASS__, 'register_role' ), 20 );

			add_action( self::TAXONOMY . '_add_form_fields', array( __CLASS__, 'add_term_order_field' ) );
			add_action( self::TAXONOMY . '_edit_form_fields', array( __CLASS__, 'edit_term_order_field' ), 10, 2 );
			add_action( 'created_' . self::TAXONOMY, array( __CLASS__, 'save_term_order' ) );
			add_action( 'edited_' . self::TAXONOMY, array( __CLASS__, 'save_term_order' ) );

			add_filter( 'manage_edit-' . self::TAXONOMY . '_columns', array( __CLASS__, 'term_columns' ) );
			add_filter( 'manage_' . self::TAXONOMY . '_custom_column', array( __CLASS__, 'term_column_content' ), 10, 3 );
				add_filter( 'manage_edit-' . self::POST_TYPE . '_columns', array( __CLASS__, 'post_columns' ) );
				add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'post_column_content' ), 10, 2 );
				add_action( 'add_meta_boxes', array( __CLASS__, 'register_article_order_metabox' ) );
				add_action( 'save_post_' . self::POST_TYPE, array( __CLASS__, 'save_article_order' ), 10, 2 );

			add_action( 'pre_get_terms', array( __CLASS__, 'sort_terms_in_admin' ) );
			add_action( 'pre_get_posts', array( __CLASS__, 'sort_posts_in_admin' ) );

			add_filter( 'ajax_query_attachments_args', array( __CLASS__, 'limit_media_modal_to_own_uploads' ) );
			add_action( 'pre_get_posts', array( __CLASS__, 'limit_media_library_to_own_uploads' ) );
			add_filter( 'map_meta_cap', array( __CLASS__, 'restrict_attachment_deletes_to_owners' ), 10, 4 );

				add_action( 'admin_menu', array( __CLASS__, 'restrict_admin_menu' ), 999 );
				add_action( 'admin_menu', array( __CLASS__, 'register_organize_kb_submenu' ) );
				add_action( 'admin_init', array( __CLASS__, 'restrict_admin_screens' ) );
				add_action( 'admin_init', array( __CLASS__, 'handle_organize_kb_submission' ) );
				add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_organize_kb_assets' ) );
				add_filter( 'views_upload', array( __CLASS__, 'prune_media_views' ) );
				add_filter( 'editable_roles', array( __CLASS__, 'hide_roles_from_kb_editor' ) );

				add_shortcode( 'kb_sections', array( __CLASS__, 'kb_sections_shortcode' ) );
				add_shortcode( 'kb_section_articles', array( __CLASS__, 'kb_section_articles_shortcode' ) );
				add_shortcode( 'kb_all_sections_articles', array( __CLASS__, 'kb_all_sections_articles_shortcode' ) );
				add_shortcode( 'kb_article_titles', array( __CLASS__, 'kb_article_titles_shortcode' ) );
			}

		public static function activate() {
			self::register_post_type();
			self::register_taxonomy();
			self::register_role();
			update_option( self::OPTION_VERSION_KEY, self::VERSION );
			flush_rewrite_rules();
		}

		public static function deactivate() {
			flush_rewrite_rules();
		}

		public static function register_post_type() {
			$labels = array(
				'name'               => __( 'Knowledge Base', 'kb-manager' ),
				'singular_name'      => __( 'KB Article', 'kb-manager' ),
				'menu_name'          => __( 'Knowledge Base', 'kb-manager' ),
				'name_admin_bar'     => __( 'KB Article', 'kb-manager' ),
				'add_new'            => __( 'Add New', 'kb-manager' ),
				'add_new_item'       => __( 'Add New KB Article', 'kb-manager' ),
				'new_item'           => __( 'New KB Article', 'kb-manager' ),
				'edit_item'          => __( 'Edit KB Article', 'kb-manager' ),
				'view_item'          => __( 'View KB Article', 'kb-manager' ),
				'all_items'          => __( 'All KB Articles', 'kb-manager' ),
				'search_items'       => __( 'Search KB Articles', 'kb-manager' ),
				'not_found'          => __( 'No KB articles found.', 'kb-manager' ),
				'not_found_in_trash' => __( 'No KB articles found in Trash.', 'kb-manager' ),
			);

			register_post_type(
				self::POST_TYPE,
				array(
					'labels'             => $labels,
					'public'             => true,
					'show_in_rest'       => true,
					'has_archive'        => true,
					'rewrite'            => array( 'slug' => 'knowledge-base' ),
					'menu_icon'          => 'dashicons-book-alt',
					'supports'           => array( 'title', 'editor', 'author', 'revisions', 'excerpt', 'thumbnail', 'page-attributes' ),
					'taxonomies'         => array( self::TAXONOMY ),
					'hierarchical'       => true,
					'capability_type'    => array( 'kb_article', 'kb_articles' ),
					'map_meta_cap'       => true,
					'delete_with_user'   => false,
					'show_in_menu'       => true,
				)
			);
		}

		public static function register_taxonomy() {
			$labels = array(
				'name'              => __( 'KB Sections', 'kb-manager' ),
				'singular_name'     => __( 'KB Section', 'kb-manager' ),
				'search_items'      => __( 'Search KB Sections', 'kb-manager' ),
				'all_items'         => __( 'All KB Sections', 'kb-manager' ),
				'parent_item'       => __( 'Parent KB Section', 'kb-manager' ),
				'parent_item_colon' => __( 'Parent KB Section:', 'kb-manager' ),
				'edit_item'         => __( 'Edit KB Section', 'kb-manager' ),
				'update_item'       => __( 'Update KB Section', 'kb-manager' ),
				'add_new_item'      => __( 'Add New KB Section', 'kb-manager' ),
				'new_item_name'     => __( 'New KB Section Name', 'kb-manager' ),
				'menu_name'         => __( 'KB Sections', 'kb-manager' ),
			);

			register_taxonomy(
				self::TAXONOMY,
				array( self::POST_TYPE ),
				array(
					'hierarchical'      => true,
					'labels'            => $labels,
					'show_ui'           => true,
					'show_admin_column' => true,
					'show_in_rest'      => true,
					'rewrite'           => array( 'slug' => 'kb-section' ),
					'capabilities'      => array(
						'manage_terms' => 'manage_kb_sections',
						'edit_terms'   => 'edit_kb_sections',
						'delete_terms' => 'delete_kb_sections',
						'assign_terms' => 'assign_kb_sections',
					),
				)
			);
		}

			public static function register_role() {
				$caps = array(
				'read'                          => true,
				'upload_files'                  => true,

				'edit_kb_article'               => true,
				'read_kb_article'               => true,
				'delete_kb_article'             => true,

				'edit_kb_articles'              => true,
				'edit_others_kb_articles'       => true,
				'edit_published_kb_articles'    => true,
				'publish_kb_articles'           => true,
				'read_private_kb_articles'      => true,
				'edit_private_kb_articles'      => true,

				'manage_kb_sections'            => true,
				'edit_kb_sections'              => true,
				'delete_kb_sections'            => true,
				'assign_kb_sections'            => true,

				'delete_kb_articles'            => true,
				'delete_published_kb_articles'  => true,
				'delete_private_kb_articles'    => true,
				'delete_others_kb_articles'     => true,
			);

			add_role( self::ROLE, __( 'KB Editor', 'kb-manager' ), $caps );

				$role = get_role( self::ROLE );
				if ( $role ) {
					foreach ( $caps as $cap => $grant ) {
						if ( $grant && ! $role->has_cap( $cap ) ) {
							$role->add_cap( $cap );
						}
					}
				}

				$admin_role = get_role( 'administrator' );
				if ( $admin_role ) {
					foreach ( $caps as $cap => $grant ) {
						if ( $grant && ! $admin_role->has_cap( $cap ) ) {
							$admin_role->add_cap( $cap );
						}
					}
				}
			}

		public static function add_term_order_field() {
			?>
			<div class="form-field term-order-wrap">
				<label for="kb-section-order"><?php esc_html_e( 'Order', 'kb-manager' ); ?></label>
				<input name="kb_section_order" id="kb-section-order" type="number" min="0" step="1" value="0" />
				<p><?php esc_html_e( 'Lower numbers appear first within the same parent section.', 'kb-manager' ); ?></p>
			</div>
			<?php
		}

		public static function edit_term_order_field( $term ) {
			$value = (int) get_term_meta( $term->term_id, self::TERM_ORDER_META, true );
			?>
			<tr class="form-field term-order-wrap">
				<th scope="row"><label for="kb-section-order"><?php esc_html_e( 'Order', 'kb-manager' ); ?></label></th>
				<td>
					<input name="kb_section_order" id="kb-section-order" type="number" min="0" step="1" value="<?php echo esc_attr( $value ); ?>" />
					<p class="description"><?php esc_html_e( 'Lower numbers appear first within the same parent section.', 'kb-manager' ); ?></p>
				</td>
			</tr>
			<?php
		}

		public static function save_term_order( $term_id ) {
			if ( ! isset( $_POST['kb_section_order'] ) ) {
				return;
			}

			if ( ! current_user_can( 'manage_kb_sections' ) ) {
				return;
			}

			$order = max( 0, (int) wp_unslash( $_POST['kb_section_order'] ) );
			update_term_meta( $term_id, self::TERM_ORDER_META, $order );
		}

		public static function term_columns( $columns ) {
			$columns['kb_order'] = __( 'Order', 'kb-manager' );
			return $columns;
		}

		public static function term_column_content( $content, $column_name, $term_id ) {
			if ( 'kb_order' === $column_name ) {
				$content = (string) (int) get_term_meta( $term_id, self::TERM_ORDER_META, true );
			}
			return $content;
		}

		public static function post_columns( $columns ) {
			$insert_after = array();
			foreach ( $columns as $key => $label ) {
				$insert_after[ $key ] = $label;
				if ( 'title' === $key ) {
					$insert_after['kb_order'] = __( 'Order', 'kb-manager' );
				}
			}
			return $insert_after;
		}

			public static function post_column_content( $column, $post_id ) {
				if ( 'kb_order' === $column ) {
					$post = get_post( $post_id );
					if ( $post ) {
						echo esc_html( (string) (int) $post->menu_order );
					}
				}
			}

			public static function register_article_order_metabox() {
				add_meta_box(
					'kb-article-order',
					__( 'Article Order', 'kb-manager' ),
					array( __CLASS__, 'render_article_order_metabox' ),
					self::POST_TYPE,
					'side',
					'default'
				);
			}

			public static function render_article_order_metabox( $post ) {
				wp_nonce_field( 'kb_article_order_save', 'kb_article_order_nonce' );
				?>
				<p>
					<label for="kb-article-order-field"><?php esc_html_e( 'Order', 'kb-manager' ); ?></label>
					<input
						type="number"
						min="0"
						step="1"
						id="kb-article-order-field"
						name="kb_article_order"
						value="<?php echo esc_attr( (string) (int) $post->menu_order ); ?>"
						class="small-text"
					/>
				</p>
				<p class="description"><?php esc_html_e( 'Lower numbers appear first.', 'kb-manager' ); ?></p>
				<?php
			}

			public static function save_article_order( $post_id, $post ) {
				if ( ! $post instanceof WP_Post || self::POST_TYPE !== $post->post_type ) {
					return;
				}

				if ( ! isset( $_POST['kb_article_order_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['kb_article_order_nonce'] ) ), 'kb_article_order_save' ) ) {
					return;
				}

				if ( ! current_user_can( 'edit_post', $post_id ) ) {
					return;
				}

				if ( wp_is_post_revision( $post_id ) ) {
					return;
				}

				$order = isset( $_POST['kb_article_order'] ) ? max( 0, (int) wp_unslash( $_POST['kb_article_order'] ) ) : 0;
				if ( (int) $post->menu_order === $order ) {
					return;
				}

				remove_action( 'save_post_' . self::POST_TYPE, array( __CLASS__, 'save_article_order' ), 10 );
				wp_update_post(
					array(
						'ID'         => $post_id,
						'menu_order' => $order,
					)
				);
				add_action( 'save_post_' . self::POST_TYPE, array( __CLASS__, 'save_article_order' ), 10, 2 );
			}

		public static function sort_terms_in_admin( $query ) {
			if ( ! is_admin() || ! $query instanceof WP_Term_Query ) {
				return;
			}

			$taxonomy = $query->query_vars['taxonomy'] ?? null;
			if ( self::TAXONOMY !== $taxonomy ) {
				return;
			}

			if ( empty( $query->query_vars['orderby'] ) || 'name' === $query->query_vars['orderby'] ) {
				$query->query_vars['meta_key'] = self::TERM_ORDER_META;
				$query->query_vars['orderby']  = 'meta_value_num';
				$query->query_vars['order']    = 'ASC';
			}
		}

		public static function sort_posts_in_admin( $query ) {
			if ( ! is_admin() || ! $query instanceof WP_Query || ! $query->is_main_query() ) {
				return;
			}

			global $pagenow;
			if ( 'edit.php' !== $pagenow ) {
				return;
			}

			if ( self::POST_TYPE !== $query->get( 'post_type' ) ) {
				return;
			}

			if ( empty( $query->get( 'orderby' ) ) ) {
				$query->set( 'orderby', array( 'menu_order' => 'ASC', 'title' => 'ASC' ) );
			}
		}

		protected static function is_kb_editor() {
			$user = wp_get_current_user();
			return $user && in_array( self::ROLE, (array) $user->roles, true );
		}

		public static function limit_media_modal_to_own_uploads( $query ) {
			if ( self::is_kb_editor() ) {
				$query['author'] = get_current_user_id();
			}
			return $query;
		}

		public static function limit_media_library_to_own_uploads( $query ) {
			if ( ! is_admin() || ! $query instanceof WP_Query || ! $query->is_main_query() ) {
				return;
			}

			global $pagenow;
			if ( 'upload.php' !== $pagenow ) {
				return;
			}

			if ( self::is_kb_editor() ) {
				$query->set( 'author', get_current_user_id() );
			}
		}

		public static function restrict_attachment_deletes_to_owners( $caps, $cap, $user_id, $args ) {
			if ( 'delete_post' !== $cap || empty( $args[0] ) ) {
				return $caps;
			}

			$post = get_post( (int) $args[0] );
			if ( ! $post || 'attachment' !== $post->post_type ) {
				return $caps;
			}

			$user = get_user_by( 'id', $user_id );
			if ( ! $user || ! in_array( self::ROLE, (array) $user->roles, true ) ) {
				return $caps;
			}

			if ( (int) $post->post_author !== (int) $user_id ) {
				return array( 'do_not_allow' );
			}

			return $caps;
		}

		public static function restrict_admin_menu() {
			if ( ! self::is_kb_editor() ) {
				return;
			}

			remove_menu_page( 'index.php' );
			remove_menu_page( 'edit.php' );
			remove_menu_page( 'edit.php?post_type=page' );
			remove_menu_page( 'edit-comments.php' );
			remove_menu_page( 'themes.php' );
			remove_menu_page( 'plugins.php' );
			remove_menu_page( 'users.php' );
			remove_menu_page( 'tools.php' );
			remove_menu_page( 'options-general.php' );
			remove_menu_page( 'profile.php' );

			add_menu_page(
				__( 'Profile', 'kb-manager' ),
				__( 'Profile', 'kb-manager' ),
				'read',
				'profile.php',
				'',
				'dashicons-admin-users',
				999
			);
		}

			public static function restrict_admin_screens() {
			if ( ! is_admin() || ! self::is_kb_editor() ) {
				return;
			}

			global $pagenow;

			$allowed_post_type_screens = array(
				'post.php',
				'post-new.php',
				'edit.php',
			);

			$allowed_taxonomy_screen = 'edit-tags.php';
			$allowed_core_screens    = array(
				'upload.php',
				'media-new.php',
				'profile.php',
				'admin-ajax.php',
				'async-upload.php',
				'index.php',
			);

				if ( in_array( $pagenow, $allowed_core_screens, true ) ) {
					if ( 'index.php' === $pagenow ) {
						wp_safe_redirect( admin_url( 'edit.php?post_type=' . self::POST_TYPE ) );
						exit;
					}
					return;
				}

				if ( 'admin.php' === $pagenow ) {
					$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
					if ( 'kb-organize' === $page ) {
						return;
					}
				}

			if ( in_array( $pagenow, $allowed_post_type_screens, true ) ) {
				$post_type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : '';

				if ( 'post.php' === $pagenow || 'post-new.php' === $pagenow ) {
					$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
					if ( $post_id ) {
						$post = get_post( $post_id );
						$post_type = $post ? $post->post_type : $post_type;
					}
				}

				if ( self::POST_TYPE !== $post_type ) {
					wp_die( esc_html__( 'You do not have access to that screen.', 'kb-manager' ), 403 );
				}
				return;
			}

			if ( $allowed_taxonomy_screen === $pagenow ) {
				$taxonomy = isset( $_GET['taxonomy'] ) ? sanitize_key( wp_unslash( $_GET['taxonomy'] ) ) : '';
				if ( self::TAXONOMY !== $taxonomy ) {
					wp_die( esc_html__( 'You do not have access to that taxonomy screen.', 'kb-manager' ), 403 );
				}
				return;
			}

			wp_safe_redirect( admin_url( 'edit.php?post_type=' . self::POST_TYPE ) );
			exit;
		}

		public static function prune_media_views( $views ) {
			if ( self::is_kb_editor() ) {
				unset( $views['mine'], $views['detached'], $views['unattached'] );
			}
			return $views;
		}

			public static function hide_roles_from_kb_editor( $roles ) {
				if ( self::is_kb_editor() ) {
					return array();
				}
				return $roles;
			}

			public static function register_organize_kb_submenu() {
				add_submenu_page(
					'edit.php?post_type=' . self::POST_TYPE,
					__( 'Organize KB', 'kb-manager' ),
					__( 'Organize KB', 'kb-manager' ),
					'edit_kb_articles',
					'kb-organize',
					array( __CLASS__, 'render_organize_kb_page' )
				);
			}

			public static function enqueue_organize_kb_assets( $hook_suffix ) {
				if ( 'kb_article_page_kb-organize' !== $hook_suffix ) {
					return;
				}

				wp_enqueue_script( 'jquery-ui-sortable' );
			}

			public static function handle_organize_kb_submission() {
				if ( ! is_admin() ) {
					return;
				}

				if ( ! isset( $_POST['kb_organize_action'] ) || 'save' !== $_POST['kb_organize_action'] ) {
					return;
				}

				if ( ! current_user_can( 'edit_kb_articles' ) ) {
					return;
				}

				if ( ! isset( $_POST['kb_organize_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['kb_organize_nonce'] ) ), 'kb_organize_save' ) ) {
					return;
				}

				$payload_json = isset( $_POST['kb_organize_payload'] ) ? wp_unslash( $_POST['kb_organize_payload'] ) : '';
				$payload      = json_decode( (string) $payload_json, true );
				if ( ! is_array( $payload ) || ! isset( $payload['sections'] ) || ! is_array( $payload['sections'] ) ) {
					return;
				}

				foreach ( $payload['sections'] as $section_index => $section_item ) {
					$term_id = isset( $section_item['term_id'] ) ? (int) $section_item['term_id'] : 0;
					if ( $term_id <= 0 ) {
						continue;
					}

					update_term_meta( $term_id, self::TERM_ORDER_META, max( 0, (int) $section_index ) );

					$articles = isset( $section_item['articles'] ) && is_array( $section_item['articles'] ) ? $section_item['articles'] : array();
					foreach ( $articles as $article_index => $article_id ) {
						$post_id = (int) $article_id;
						if ( $post_id <= 0 ) {
							continue;
						}

						$post = get_post( $post_id );
						if ( ! $post || self::POST_TYPE !== $post->post_type ) {
							continue;
						}

						wp_update_post(
							array(
								'ID'         => $post_id,
								'menu_order' => max( 0, (int) $article_index ),
							)
						);
						wp_set_object_terms( $post_id, array( $term_id ), self::TAXONOMY, false );
					}
				}

				wp_safe_redirect(
					add_query_arg(
						array(
							'post_type' => self::POST_TYPE,
							'page'      => 'kb-organize',
							'updated'   => '1',
						),
						admin_url( 'edit.php' )
					)
				);
				exit;
			}

			protected static function get_articles_for_section( $term_id ) {
				return get_posts(
					array(
						'post_type'      => self::POST_TYPE,
						'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
						'posts_per_page' => -1,
						'orderby'        => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
						'tax_query'      => array(
							array(
								'taxonomy' => self::TAXONOMY,
								'field'    => 'term_id',
								'terms'    => array( (int) $term_id ),
							),
						),
					)
				);
			}

			public static function render_organize_kb_page() {
				if ( ! current_user_can( 'edit_kb_articles' ) ) {
					wp_die( esc_html__( 'You do not have permission to organize the knowledge base.', 'kb-manager' ), 403 );
				}

				$sections = self::get_ordered_kb_terms(
					array(
						'parent'     => 0,
						'hide_empty' => false,
					)
				);
				?>
				<div class="wrap">
					<h1><?php esc_html_e( 'Organize KB', 'kb-manager' ); ?></h1>
					<?php if ( isset( $_GET['updated'] ) && '1' === $_GET['updated'] ) : ?>
						<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Knowledge base ordering updated.', 'kb-manager' ); ?></p></div>
					<?php endif; ?>

					<form method="post" id="kb-organize-form">
						<?php wp_nonce_field( 'kb_organize_save', 'kb_organize_nonce' ); ?>
						<input type="hidden" name="kb_organize_action" value="save" />
						<input type="hidden" name="kb_organize_payload" id="kb-organize-payload" value="" />

						<div id="kb-organize-sections">
							<?php if ( is_wp_error( $sections ) || empty( $sections ) ) : ?>
								<p><?php esc_html_e( 'No sections found.', 'kb-manager' ); ?></p>
							<?php else : ?>
								<?php foreach ( $sections as $section ) : ?>
									<div class="kb-section-box" data-term-id="<?php echo esc_attr( (string) (int) $section->term_id ); ?>">
										<h2 class="kb-section-title">
											<span class="dashicons dashicons-move"></span>
											<a href="<?php echo esc_url( get_edit_term_link( $section, self::TAXONOMY ) ); ?>"><?php echo esc_html( $section->name ); ?></a>
										</h2>
										<ul class="kb-article-list" data-term-id="<?php echo esc_attr( (string) (int) $section->term_id ); ?>">
											<?php foreach ( self::get_articles_for_section( (int) $section->term_id ) as $article ) : ?>
												<li class="kb-article-item" data-post-id="<?php echo esc_attr( (string) (int) $article->ID ); ?>">
													<a href="<?php echo esc_url( get_edit_post_link( $article->ID ) ); ?>"><?php echo esc_html( get_the_title( $article->ID ) ); ?></a>
												</li>
											<?php endforeach; ?>
										</ul>
									</div>
								<?php endforeach; ?>
							<?php endif; ?>
						</div>

						<?php submit_button( __( 'Save KB Order', 'kb-manager' ) ); ?>
					</form>
				</div>

				<style>
					#kb-organize-sections { max-width: 900px; }
					.kb-section-box { border: 1px solid #dcdcde; margin: 0 0 14px; background: #fff; }
					.kb-section-title { margin: 0; padding: 10px 12px; border-bottom: 1px solid #dcdcde; background: #f6f7f7; cursor: move; display: flex; gap: 8px; align-items: center; }
					.kb-section-box-placeholder { border: 1px dashed #8c8f94; background: #f6f7f7; height: 56px; margin-bottom: 14px; }
					.kb-article-list { margin: 0; padding: 10px 12px; min-height: 20px; }
					.kb-article-item { margin: 0 0 8px; padding: 7px 10px; border: 1px solid #dcdcde; background: #fff; cursor: move; list-style: none; }
					.kb-article-placeholder { border: 1px dashed #8c8f94; background: #f0f6fc; height: 36px; margin-bottom: 8px; list-style: none; }
				</style>

				<script>
					jQuery(function($) {
						var $sections = $('#kb-organize-sections');

						$sections.sortable({
							items: '.kb-section-box',
							handle: '.kb-section-title',
							placeholder: 'kb-section-box-placeholder'
						});

						$('.kb-article-list').sortable({
							connectWith: '.kb-article-list',
							items: '> .kb-article-item',
							placeholder: 'kb-article-placeholder'
						});

						$('#kb-organize-form').on('submit', function() {
							var payload = { sections: [] };

							$sections.find('> .kb-section-box').each(function() {
								var $section = $(this);
								var item = {
									term_id: parseInt($section.data('term-id'), 10) || 0,
									articles: []
								};

								$section.find('> .kb-article-list > .kb-article-item').each(function() {
									item.articles.push(parseInt($(this).data('post-id'), 10) || 0);
								});

								payload.sections.push(item);
							});

							$('#kb-organize-payload').val(JSON.stringify(payload));
						});
					});
				</script>
				<?php
			}
			public static function kb_sections_shortcode( $atts ) {
			$atts = shortcode_atts(
				array(
					'parent'     => 0,
					'hide_empty' => false,
				),
				$atts,
				'kb_sections'
			);

				$terms = self::get_ordered_kb_terms(
					array(
						'parent'     => (int) $atts['parent'],
						'hide_empty' => filter_var( $atts['hide_empty'], FILTER_VALIDATE_BOOLEAN ),
					)
				);

			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				return '';
			}

			ob_start();
			echo '<ul class="kb-sections">';
			foreach ( $terms as $term ) {
				printf(
					'<li><a href="%1$s">%2$s</a></li>',
					esc_url( get_term_link( $term ) ),
					esc_html( $term->name )
				);
			}
			echo '</ul>';
			return ob_get_clean();
		}

			public static function kb_section_articles_shortcode( $atts ) {
			$atts = shortcode_atts(
				array(
					'section'        => '',
					'posts_per_page' => -1,
				),
				$atts,
				'kb_section_articles'
			);

			$term = null;
			if ( is_numeric( $atts['section'] ) ) {
				$term = get_term( (int) $atts['section'], self::TAXONOMY );
			} elseif ( '' !== $atts['section'] ) {
				$term = get_term_by( 'slug', sanitize_title( $atts['section'] ), self::TAXONOMY );
			} elseif ( is_tax( self::TAXONOMY ) ) {
				$term = get_queried_object();
			}

			if ( ! $term || is_wp_error( $term ) ) {
				return '';
			}

			$query = new WP_Query(
				array(
					'post_type'      => self::POST_TYPE,
					'post_status'    => 'publish',
					'posts_per_page' => (int) $atts['posts_per_page'],
					'orderby'        => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
					'tax_query'      => array(
						array(
							'taxonomy' => self::TAXONOMY,
							'field'    => 'term_id',
							'terms'    => array( (int) $term->term_id ),
						),
					),
				)
			);

			if ( ! $query->have_posts() ) {
				return '';
			}

			ob_start();
			echo '<ul class="kb-section-articles">';
			while ( $query->have_posts() ) {
				$query->the_post();
				printf(
					'<li><a href="%1$s">%2$s</a></li>',
					esc_url( get_permalink() ),
					esc_html( get_the_title() )
				);
			}
			echo '</ul>';
				wp_reset_postdata();
				return ob_get_clean();
			}

			protected static function render_sections_with_articles_list( $parent = 0 ) {
					$terms = self::get_ordered_kb_terms(
						array(
							'parent'     => (int) $parent,
							'hide_empty' => true,
						)
					);

				if ( is_wp_error( $terms ) || empty( $terms ) ) {
					return '';
				}

				ob_start();
				echo '<ul class="kb-all-sections-articles">';

				foreach ( $terms as $term ) {
					echo '<li>';
					printf(
						'<a href="%1$s">%2$s</a>',
						esc_url( get_term_link( $term ) ),
						esc_html( $term->name )
					);

					$articles = new WP_Query(
						array(
							'post_type'      => self::POST_TYPE,
							'post_status'    => 'publish',
							'posts_per_page' => -1,
							'orderby'        => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
							'tax_query'      => array(
								array(
									'taxonomy' => self::TAXONOMY,
									'field'    => 'term_id',
									'terms'    => array( (int) $term->term_id ),
								),
							),
						)
					);

					if ( $articles->have_posts() ) {
						echo '<ul class="kb-all-sections-articles-posts">';
						while ( $articles->have_posts() ) {
							$articles->the_post();
							printf(
								'<li><a href="%1$s">%2$s</a></li>',
								esc_url( get_permalink() ),
								esc_html( get_the_title() )
							);
						}
						echo '</ul>';
						wp_reset_postdata();
					}

					$children_html = self::render_sections_with_articles_list( (int) $term->term_id );
					if ( '' !== $children_html ) {
						echo $children_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					}

					echo '</li>';
				}

				echo '</ul>';
				return ob_get_clean();
			}


			public static function kb_article_titles_shortcode() {
				$articles = get_posts(
					array(
						'post_type'      => self::POST_TYPE,
						'post_status'    => 'publish',
						'posts_per_page' => -1,
						'post_parent'    => 0,
						'orderby'        => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
						'order'          => 'ASC',
					)
				);

				if ( empty( $articles ) ) {
					return '';
				}

				ob_start();
				echo '<ul class="kb-article-titles">';

				foreach ( $articles as $index => $article ) {
					self::render_kb_article_title_item( $article, 0, $index + 1 );
				}

				echo '</ul>';
				return ob_get_clean();
			}

			protected static function render_kb_article_title_item( $article, $depth = 0, $sibling_index = 1 ) {
				$item_classes = array( 'kb-article-item', 'kb-depth-' . (int) $depth, 'kb-child-index-' . (int) $sibling_index );

				if ( 0 === (int) $depth ) {
					$item_classes[] = 'kb-parent';
				} else {
					$item_classes[] = 'kb-descendant';
				}

				printf(
					'<li class="%1$s"><a href="%2$s">%3$s</a>',
					esc_attr( implode( ' ', $item_classes ) ),
					esc_url( get_permalink( $article->ID ) ),
					esc_html( get_the_title( $article->ID ) )
				);

				$children = get_posts(
					array(
						'post_type'      => self::POST_TYPE,
						'post_status'    => 'publish',
						'posts_per_page' => -1,
						'post_parent'    => (int) $article->ID,
						'orderby'        => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
						'order'          => 'ASC',
					)
				);

				if ( ! empty( $children ) ) {
					echo '<ul class="kb-article-children kb-depth-' . esc_attr( (string) ( (int) $depth + 1 ) ) . '">';
					foreach ( $children as $index => $child ) {
						self::render_kb_article_title_item( $child, (int) $depth + 1, $index + 1 );
					}
					echo '</ul>';
				}

				echo '</li>';
			}


			public static function kb_all_sections_articles_shortcode() {
				return self::render_sections_with_articles_list( 0 );
			}

			protected static function get_ordered_kb_terms( $args = array() ) {
				$query_args = wp_parse_args(
					$args,
					array(
						'taxonomy'   => self::TAXONOMY,
						'parent'     => 0,
						'hide_empty' => false,
						'meta_key'   => self::TERM_ORDER_META,
						'orderby'    => 'meta_value_num',
						'order'      => 'ASC',
					)
				);

				$terms = get_terms( $query_args );
				if ( is_wp_error( $terms ) || empty( $terms ) ) {
					return $terms;
				}

				usort(
					$terms,
					static function ( $left, $right ) {
						$left_order  = (int) get_term_meta( $left->term_id, self::TERM_ORDER_META, true );
						$right_order = (int) get_term_meta( $right->term_id, self::TERM_ORDER_META, true );

						if ( $left_order === $right_order ) {
							return strcasecmp( $left->name, $right->name );
						}

						return $left_order <=> $right_order;
					}
				);

				return $terms;
			}
		}

	KB_Manager_Plugin::init();
	register_activation_hook( __FILE__, array( 'KB_Manager_Plugin', 'activate' ) );
	register_deactivation_hook( __FILE__, array( 'KB_Manager_Plugin', 'deactivate' ) );
}
