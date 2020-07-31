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
add_action( 'init', array( $wpzoom_od, 'init' ) );

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
	 * @var    string
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
		// If the plugin has not already been initialized...
		if ( false === $this->initialized ) {
			// Assign the values for the plugins 'root' dir/url
			$this->plugin_dir_path = plugin_dir_path( __FILE__ );
			$this->plugin_dir_url = plugin_dir_url( __FILE__ );

			// [...]
			$this->orphaned_post_types = $this->get_nonexistent_post_types();

			// Load the correct translation files for the plugin
			load_plugin_textdomain( 'wpzoom-orphaned-data', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

			// Enqueue the scripts and styles used in the admin
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );

			// [...]
			add_action( 'admin_menu', array( $this, 'register_menus' ) );

			// [...]
			add_action( 'tool_box', array( $this, 'toolbox_card' ) );

			// [...]
			foreach ( $this->orphaned_post_types as $post_type ) {
				// [...]
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
					'can_export'          => false
				) );
			}

			// Mark the plugin as initialized
			$this->initialized = true;
		}
	}

	/**
	 * [...]
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
	}

	/**
	 * [...]
	 *
	 * @access public
	 * @return void
	 * @since  1.0.0
	 */
	function admin_scripts( $hook ) {
		if ( $hook != $this->main_page_hook ) return;

		wp_enqueue_script( 'wpzoom-orphaned-data-js', trailingslashit( $this->plugin_dir_url ) . 'scripts/index.js', array( 'jquery' ), self::VERSION, true );

		wp_set_script_translations( 'wpzoom-orphaned-data-js', 'wpzoom-orphaned-data', plugin_dir_path( __FILE__ ) . 'languages' );

		wp_localize_script(
			'wpzoom-orphaned-data-js',
			'wpzoomOrphanedData',
			array()
		);
	}

	/**
	 * [...]
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

		$wp_query = new WP_Query( array( 'post_type' => $this->orphaned_post_types ) );

		$orphaned_post_types_table = new WPZOOM_Orphaned_Post_Types( $_list_table_args );
		$orphaned_post_types_table->process_bulk_action();
		$orphaned_post_types_table->prepare_items();

		?><div class="wrap nosubsub">
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
	 * [...]
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
						__( 'If you want to repair orphaned data in WordPress (like posts from post types that no longer exist), use the <a href="%s">WPZOOM Orphaned Data Tool</a>.', 'wpzoom-orphaned-data' ),
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

		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $post_type )
		{
			if ( ! in_array( $post_type->name, $this->orphaned_post_types ) ) {
				echo '<option value="' . $post_type->name . '">' . $post_type->labels->singular_name . '</option>';
			}
		}

		echo '</select>';
		echo '</td>';
	}

	protected function row_actions( $actions, $always_visible = false ) {
		if ( isset( $actions[ 'edit' ] ) ) {
			unset( $actions[ 'edit' ] );
		}

		if ( isset( $actions[ 'inline hide-if-no-js' ] ) ) {
			unset( $actions[ 'inline hide-if-no-js' ] );
		}

		if ( isset( $actions[ 'view' ] ) ) {
			unset( $actions[ 'view' ] );
		}

		if ( isset( $actions[ 'export' ] ) ) {
			unset( $actions[ 'export' ] );
		}

		return parent::row_actions( $actions, $always_visible );
	}

	protected function get_bulk_actions() {
		$actions = parent::get_bulk_actions();

		if ( isset( $actions[ 'edit' ] ) ) {
			unset( $actions[ 'edit' ] );
		}

		$actions[ 'change_type' ] = __( 'Change Post Type', 'wpzoom-orphaned-data' );

		return array_reverse( $actions, true );
	}

	public function inline_edit() {}

	protected function extra_tablenav( $which ) {}
}