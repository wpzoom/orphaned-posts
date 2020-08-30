<?php
/**
 * WPZOOM Orphaned Data - Repair orphaned data in WordPress (like posts from post types that no longer exist).
 *
 * @package   WPZOOM_Orphaned_Data
 * @author    WPZOOM
 * @copyright 2020 WPZOOM
 * @license   GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name: WPZOOM Orphaned Data
 * Plugin URI:  https://wpzoom.com/plugins/orphaned-data/
 * Description: Repair orphaned data in WordPress (like posts from post types that no longer exist).
 * Author:      WPZOOM
 * Author URI:  https://wpzoom.com
 * Version:     1.0.0
 * License:     GPL2+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

// Needed includes
if ( ! class_exists( 'WP_Posts_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
	require_once( ABSPATH . 'wp-admin/includes/class-wp-posts-list-table.php' );
}

// Instance the plugin
$wpzoom_od = new WPZOOM_Orphaned_Data();

// Hook the plugin into WordPress
add_action( 'init', array( $wpzoom_od, 'init' ), 999 );

/**
 * Class WPZOOM_Orphaned_Data
 *
 * Main container class of the WPZOOM Orphaned Data WordPress plugin.
 *
 * @since 1.0.0
 */
class WPZOOM_Orphaned_Data {
	/**
	 * The version of this plugin.
	 *
	 * @var    string
	 * @access public
	 * @since  1.0.0
	 */
	public const VERSION = '1.0.0';

	/**
	 * Whether the plugin has been initialized.
	 *
	 * @var    boolean
	 * @access public
	 * @since  1.0.0
	 */
	public $initialized = false;

	/**
	 * The path to this plugin's root directory.
	 *
	 * @var    string
	 * @access public
	 * @since  1.0.0
	 */
	public $plugin_dir_path;

	/**
	 * The URL to this plugin's root directory.
	 *
	 * @var    string
	 * @access public
	 * @since  1.0.0
	 */
	public $plugin_dir_url;

	/**
	 * The hook string of the main admin page of this plugin.
	 *
	 * @var    string
	 * @access public
	 * @since  1.0.0
	 */
	public $main_page_hook;

	/**
	 * All currently orphaned post types.
	 *
	 * @var    array
	 * @access public
	 * @since  1.0.0
	 */
	public $orphaned_post_types;

	/**
	 * Initializes the plugin and sets up needed hooks and features.
	 *
	 * @access public
	 * @return void
	 * @since  1.0.0
	 */
	public function init() {
		if ( false === $this->initialized ) {
			$this->plugin_dir_path = plugin_dir_path( __FILE__ );
			$this->plugin_dir_url = plugin_dir_url( __FILE__ );
			$this->orphaned_post_types = $this->get_nonexistent_post_types();

			load_plugin_textdomain( 'wpzoom-orphaned-data', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

			add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ), 999 );
			add_action( 'admin_menu', array( $this, 'register_menus' ), 999 );
			add_action( 'tool_box', array( $this, 'toolbox_card' ), 999 );
			add_filter( 'set_screen_option_edit_orphaned_post_per_page', array( $this, 'set_screen_option' ), 10, 3 );
			add_filter( 'edit_posts_per_page', array( $this, 'filter_posts_per_page' ) );

			foreach ( $this->orphaned_post_types as $post_type ) {
				register_post_type( $post_type, array(
					'label'               => ucwords( trim( str_replace( array( '-', '_' ), ' ', $post_type ) ) ),
					'public'              => false,
					'exclude_from_search' => true,
					'publicly_queryable'  => false,
					'show_ui'             => false,
					'show_in_menu'        => false,
					'show_in_nav_menus'   => false,
					'show_in_admin_bar'   => false,
					'show_in_rest'        => false,
					'rewrite'             => false,
					'query_var'           => false,
					'can_export'          => false,
					'_edit_link'          => 'post.php?post=%d'
				) );
			}

			$this->initialized = true;
		}
	}

	/**
	 * Registers a menu for this plugin.
	 *
	 * @access public
	 * @return void
	 * @since  1.0.0
	 */
	public function register_menus() {
		$this->main_page_hook = add_submenu_page(
			'tools.php',
			__( 'Orphaned Data', 'wpzoom-orphaned-data' ),
			__( 'Orphaned Data', 'wpzoom-orphaned-data' ),
			'edit_posts',
			'wpzoom-orphaned-data',
			array( $this, 'display_main' )
		);

		add_action( 'load-' . $this->main_page_hook, array( $this, 'load_main' ), 999 );
	}

	/**
	 * Takes care of things that need to happen when the main page of this plugin loads.
	 *
	 * @access public
	 * @return void
	 * @since  1.0.0
	 */
	public function load_main() {
		if ( isset( $_POST[ 'wpzod_post_type_filter' ] ) &&
		     ( !isset( $_GET[ 'wpzod_post_type_filter' ] ) || $_GET[ 'wpzod_post_type_filter' ] != $_POST[ 'wpzod_post_type_filter' ] ) ) {
			$post_type = sanitize_key( trim( $_REQUEST[ 'wpzod_post_type_filter' ] ) );

			wp_redirect( admin_url( 'tools.php?page=wpzoom-orphaned-data' . ( '0' != $post_type ? '&wpzod_post_type_filter=' . $post_type : '' ) ) );
			exit;
		}

		add_screen_option(
			'per_page',
			array(
				'default' => 20,
				'option'  => 'edit_orphaned_post_per_page',
			)
		);
	}

	/**
	 * Sets up and enqueues needed scripts in the WordPress admin.
	 *
	 * @access public
	 * @return void
	 * @since  1.0.0
	 */
	function admin_scripts( $hook ) {
		if ( $hook != $this->main_page_hook ) return;

		wp_enqueue_script(
			'wpzoom-orphaned-data-js',
			trailingslashit( $this->plugin_dir_url ) . 'scripts/index.js',
			array( 'jquery' ),
			self::VERSION,
			true
		);

		wp_set_script_translations(
			'wpzoom-orphaned-data-js',
			'wpzoom-orphaned-data',
			plugin_dir_path( __FILE__ ) . 'languages'
		);
	}

	/**
	 * Filters the screen option for the posts per page option on the plugin main page.
	 *
	 * @access public
	 * @return void
	 * @since  1.0.0
	 */
	function set_screen_option( $status, $option, $value ) {
		return $value;
	}

	/**
	 * Filters the posts per page amount on the plugin main page.
	 *
	 * @access public
	 * @return void
	 * @since  1.0.0
	 */
	function filter_posts_per_page( $per_page ) {
		$current_screen = get_current_screen();

		if ( $current_screen && 'tools_page_wpzoom-orphaned-data' == $current_screen->base ) {
			$per_page = (int) get_user_option( 'edit_orphaned_post_per_page' );

			if ( empty( $per_page ) || $per_page < 1 ) {
				$per_page = 20;
			}
		}

		return $per_page;
	}

	/**
	 * Displays the main plugin page.
	 *
	 * @access public
	 * @return void
	 * @since  1.0.0
	 */
	public function display_main() {
		global $wp_query;
		
		$_list_table_args = array(
			'screen'              => convert_to_screen( 'edit-post' ),
			'plural'              => 'orphaned_post_types',
			'singular'            => 'orphaned_post_type',
			'ajax'                => false,
			'orphaned_post_types' => $this->orphaned_post_types
		);

		$per_page = (int) get_user_option( 'edit_orphaned_post_per_page' );
		if ( empty( $per_page ) || $per_page < 1 ) {
			$per_page = 20;
		}

		$post_types = $this->orphaned_post_types;
		if ( isset( $_REQUEST[ 'wpzod_post_type_filter' ] ) ) {
			$post_types = sanitize_key( trim( $_REQUEST[ 'wpzod_post_type_filter' ] ) );
		}

		$wp_query = new WP_Query( array( 'post_type' => $post_types, 'posts_per_page' => $per_page ) );

		$orphaned_post_types_table = new WPZOOM_Orphaned_Post_Types( $_list_table_args );
		$orphaned_post_types_table->process_bulk_action();
		$orphaned_post_types_table->prepare_items();

		?><div id="wpzoom-orphaned-data" class="wrap nosubsub">
			<h1><?php esc_html_e( 'WPZOOM Orphaned Data', 'wpzoom-orphaned-data' ); ?></h1>
			<hr class="wp-header-end" />

			<?php settings_errors(); ?>

			<hr />

			<h2><?php esc_html_e( 'Orphaned Posts', 'wpzoom-orphaned-data' ); ?></h2>

			<form method="post">
				<?php $orphaned_post_types_table->display(); ?>
			</form>
		</div><?php
	}

	/**
	 * A card on the main tools page that links to this plugin's page.
	 *
	 * @access public
	 * @return void
	 * @since  1.0.0
	 */
	public function toolbox_card() {
		if ( current_user_can( 'edit_posts' ) ) {
			?><div class="card">
				<h2 class="title"><?php _e( 'Orphaned Data', 'wpzoom-orphaned-data' ); ?></h2>
				<p>
				<?php
					printf(
						__(
							'If you want to repair orphaned data in WordPress (like posts from post types that no longer exist),
							 use the <a href="%s">WPZOOM Orphaned Data Tool</a>.',
							'wpzoom-orphaned-data'
						),
						'tools.php?page=wpzoom-orphaned-data'
					);
				?>
				</p>
			</div><?php
		}
	}

	/**
	 * Get all nonexistent post types.
	 *
	 * @access private
	 * @return array
	 * @since 1.0.0
	 */
	private function get_nonexistent_post_types() {
		global $wpdb;
		
		$types = array();

		foreach ( $wpdb->get_col( "SELECT post_type FROM $wpdb->posts" ) as $type ) {
			if ( ! post_type_exists( $type ) ) {
				$types[ $type ] = $type;
			}
		}

		if ( isset( $types[ 'attachment' ] ) ) {
			unset( $types[ 'attachment' ] );
		}

		if ( isset( $types[ 'custom_css' ] ) ) {
			unset( $types[ 'custom_css' ] );
		}

		if ( isset( $types[ 'customize_changeset' ] ) ) {
			unset( $types[ 'customize_changeset' ] );
		}

		if ( isset( $types[ 'nav_menu_item' ] ) ) {
			unset( $types[ 'nav_menu_item' ] );
		}

		if ( isset( $types[ 'revision' ] ) ) {
			unset( $types[ 'revision' ] );
		}

		if ( isset( $types[ 'wpzoom' ] ) ) {
			unset( $types[ 'wpzoom' ] );
		}

		return array_values( $types );
	}
}

/**
 * Class WPZOOM_Orphaned_Post_Types
 *
 * Displays orphaned post types from the database.
 *
 * @since 1.0.0
 * @see WP_List_Table
 */
class WPZOOM_Orphaned_Post_Types extends WP_Posts_List_Table {
	public $orphaned_post_types;

	public function __construct( $args = array() ) {
		$this->orphaned_post_types = $args[ 'orphaned_post_types' ];

		parent::__construct( $args );
	}

	public function get_columns() {
		return array(
			'cb'    => '<input type="checkbox" />',
			'title' => _x( 'Title', 'column name', 'wpzoom-orphaned-data' ),
			'type'  => __( 'Type', 'wpzoom-orphaned-data' ),
			'date'  => __( 'Date', 'wpzoom-orphaned-data' )
		);
	}

	protected function get_sortable_columns() {
		return array(
			'title' => 'title',
			'type'  => 'type',
			'date'  => array( 'date', true )
		);
	}

	public function column_title( $post ) {
		ob_start();
		parent::column_title( $post );
		$title = ob_get_clean();

		echo preg_replace( '/\<strong\>.+\<\/strong\>/i', '<strong>' . _draft_or_post_title() . '</strong>', $title );
	}

	protected function _column_type( $post, $classes, $data, $primary ) {
		echo '<td class="' . $classes . ' post-type" ', $data, '>';
		echo '<select class="widefat">';
		echo '<option selected disabled hidden>' . ucwords( trim( str_replace( array( '-', '_' ), ' ', $post->post_type ) ) ) . '</option>';

		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $post_type ) {
			if ( ! in_array( $post_type->name, $this->orphaned_post_types ) ) {
				echo '<option value="' . $post_type->name . '">' . $post_type->labels->singular_name . '</option>';
			}
		}

		echo '</select>';
		echo '</td>';
	}

	protected function handle_row_actions( $post, $column_name, $primary ) {
		if ( $primary !== $column_name ) {
			return '';
		}

		$actions = array();

		if ( current_user_can( 'delete_posts' ) ) {
			$actions[ 'delete' ] = sprintf(
				'<a href="%s" class="submitdelete" aria-label="%s">%s</a>',
				get_delete_post_link( $post->ID, '', true ),
				esc_attr( sprintf( __( 'Delete &#8220;%s&#8221; permanently', 'wpzoom-orphaned-data' ), _draft_or_post_title() ) ),
				__( 'Delete', 'wpzoom-orphaned-data' )
			);
		}

		return parent::row_actions( $actions );
	}

	protected function get_bulk_actions() {
		$actions = array();

		if ( current_user_can( 'edit_posts' ) ) {
			$actions[ 'change_type' ] = __( 'Change Post Type', 'wpzoom-orphaned-data' );
		}

		if ( current_user_can( 'delete_posts' ) ) {
			$actions[ 'delete' ] = __( 'Delete', 'wpzoom-orphaned-data' );
		}

		return $actions;
	}

	protected function bulk_actions( $which = '' ) {
		ob_start();
		parent::bulk_actions( $which );
		$actions = ob_get_clean();

		$post_types = '';
		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $post_type ) {
			if ( ! in_array( $post_type->name, $this->orphaned_post_types ) ) {
				$post_types .= '<option value="' . $post_type->name . '">' . $post_type->labels->singular_name . '</option>';
			}
		}

		$html = '<select class="bulkactions-post-type hidden" name="wpzod_post_type">
			<option selected disabled>' . __( 'Select New Post Type', 'wpzoom-orphaned-data' ) . '</option>
			' . $post_types . '
		</select>';

		echo preg_replace( '/\<\/select\>/i', '</select>' . $html, $actions );
	}

	public function process_bulk_action() {
		$action = $this->current_action();
		$posts = isset( $_REQUEST[ 'post' ] ) ? wp_parse_id_list( wp_unslash( $_REQUEST[ 'post' ] ) ) : array();
		$count = 0;

		switch ( $action ) {
			case 'delete':
				foreach ( $posts as $post ) {
					if ( wp_delete_post( $post, true ) ) {
						$count ++;
					}
				}

				add_settings_error(
					'bulk_action',
					'bulk_action',
					sprintf( _n( 'Deleted %d post', 'Deleted %d posts', $count, 'wpzoom-orphaned-data' ), $count ),
					'success'
				);
				break;
			case 'change_type':
				if ( isset( $_REQUEST[ 'wpzod_post_type' ] ) && get_post_type_object( $_REQUEST[ 'wpzod_post_type' ] ) ) {
					foreach ( $posts as $post ) {
						if ( set_post_type( $post, $_REQUEST[ 'wpzod_post_type' ] ) ) {
							$count++;
						}
					}
				}

				add_settings_error(
					'bulk_action',
					'bulk_action',
					sprintf( _n( 'Post type changed for %d post', 'Post type changed for %d posts', $count, 'wpzoom-orphaned-data' ), $count ),
					'success'
				);
				break;
		}
	}

	public function inline_edit() {}

	protected function extra_tablenav( $which ) {
		?><div class="alignleft actions">
			<?php
			if ( 'top' === $which ) {
				$output = $this->types_dropdown();

				if ( ! empty( $output ) ) {
					echo $output;
					submit_button( __( 'Filter', 'wpzoom-orphaned-data' ), '', 'filter_action', false, array( 'id' => 'post-query-submit' ) );
				}
			}
			?>
		</div><?php
	}

	protected function types_dropdown() {
		$output = '';
		$post_types = $this->orphaned_post_types;

		if ( !empty( $post_types ) ) {
			$selected = '0';
			if ( isset( $_REQUEST[ 'wpzod_post_type_filter' ] ) ) {
				$selected = sanitize_key( trim( $_REQUEST[ 'wpzod_post_type_filter' ] ) );
			}

			$output = '<label class="screen-reader-text" for="wpzod_post_type_filter">' . __( 'Filter by post type', 'wpzoom-orphaned-data' ) . '</label>';
			$output .= '<select name="wpzod_post_type_filter" id="wpzod_post_type_filter" class="postform">';
			$output .= '<option value="0"' . ( '0' == $selected ? ' selected' : '' ) . '>' . __( 'All Post Types', 'wpzoom-orphaned-data' ) . '</option>';

			foreach ( $post_types as $post_type ) {
				$selected = $post_type == $selected ? ' selected': '';
				$nicename = ucwords( trim( str_replace( array( '-', '_' ), ' ', $post_type ) ) );
				$output .= '<option value="' . esc_attr( $post_type ) . '"' . $selected . '>' . $nicename . '</option>';
			}

			$output .= '</select>';
		}

		return $output;
	}
}