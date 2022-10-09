<?php
/**
 * Plugin Name: Suspended Lists for SportsPress
 * Plugin URI: https://wordpress.org/plugins/suspended-lists-for-sportspress
 * Description: Build suspended players lists that discount automatically after each team match day.
 * Author: Leandro Ibarra
 * Author URI:
 *
 * Text Domain: suspended-lists-for-sportspress
 * Domain Path: /languages/
 *
 * Version: 0.0.2
 * License: GPL2
 */

if (!class_exists('Suspended_Lists_For_SportsPress')) {
	/**
	 * Class Suspended_Lists_For_SportsPress.
	 *
	 * Extends SportsPress existing plugin.
	 *
	 * @version	0.0.2
	 * @author	Leandro Ibarra
	 */
	class Suspended_Lists_For_SportsPress {
		/**
		 * @var string
		 */
		public $version = '0.0.2';

		/**
		 * @var string
		 */
		public $text_domain = 'suspended-lists-for-sportspress';

		/**
		 * @var Suspended_Lists_For_SportsPress
		 */
		private static $instance;

		/**
		 * @var array
		 */
		private $meta_boxes;

		/**
		 * Retrieve an instance of this class.
		 *
		 * @return Suspended_Lists_For_SportsPress
		 */
		public static function instance() {
			if (!isset(self::$instance)) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Suspended_Lists_For_SportsPress constructor.
		 */
		public function __construct() {
			$this->meta_boxes = array(
				'sp_suspended_list' => array(
					'shortcode' => array(
						'title' => __('Shortcode', 'suspended-lists-for-sportspress'),
						'output' => 'SL_Meta_Box_Shortcode::output',
						'context' => 'side',
						'priority' => 'default',
						'tag' => 'suspended_list',
						'function' => __CLASS__.'::suspended_list'
					),
					'details' => array(
						'title' => __('Details', 'suspended-lists-for-sportspress'),
						'save' => 'SL_Meta_Box_Details::save',
						'output' => 'SL_Meta_Box_Details::output',
						'context' => 'normal',
						'priority' => 'default'
					)
				)
			);

			add_action('plugins_loaded', array($this, 'init'));
			// roles
			add_action('init', array($this, 'create_capabilities'));
			// post type
			add_action('init', array($this, 'register_post_type'));
			add_filter('sportspress_post_types', array($this, 'add_post_type'));
			// includes
			add_action('admin_init', array($this, 'autoload_admin'));
			// screen id
			add_filter('sportspress_screen_ids', array($this, 'add_screen_ids'));
			// styles
			add_action('admin_enqueue_scripts', array($this, 'admin_styles'));
			add_action('wp_enqueue_scripts', array($this, 'front_styles'));
			// scripts
			add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
			// menu
			add_action('admin_menu', array($this, 'admin_menu'), 26);
			// highlight
			add_action('admin_head', array($this, 'menu_highlight'));

			// meta boxes addition
			add_action('add_meta_boxes', array($this, 'add_meta_boxes'), 32);

			// meta boxes actions
			foreach ($this->meta_boxes as $post_type=>$meta_boxes) {
				$count = 0;

				foreach ($meta_boxes as $key=>$meta_box) {
					if (array_key_exists('tag', $meta_box)) {
						add_shortcode($meta_box['tag'], $meta_box['function']);
					} else if (array_key_exists('save', $meta_box)) {
						add_action('sl_process_'.$post_type.'_meta', $meta_box['save'], ($count + 1) * 10, 2);
					}

					$count++;
				}
			}

			// single template
			add_filter('the_content', array($this, 'list_content'));

			// save post
			add_action('save_post', array($this, 'save_meta_boxes'), 1, 2);

			// Include required files
			$this->includes();
		}

		/**
		 * Initialize the plugin.
		 */
		public function init() {
			// Require core SportsPress plugin
			new Check_Dependencies('sportspress/sportspress.php', 'SportsPress', __FILE__, '2.3', $this->text_domain);

			// Load plugin translated strings
			load_plugin_textdomain($this->text_domain, false, $this->plugin_path().'/languages');
		}

		/**
		 * Create capabilities.
		 */
		public static function create_capabilities() {
			global $wp_roles;

			if (class_exists('WP_Roles')) {
				if (!isset($wp_roles)) {
					$wp_roles = new WP_Roles();
				}
			}
	
			if (is_object($wp_roles)) {
				foreach(
					array(
						'edit_sp_suspended_list',
						'read_sp_suspended_list',
						'delete_sp_suspended_list',
						'edit_sp_suspended_lists',
						'publish_sp_suspended_lists',
						'delete_sp_suspended_lists',
						'delete_published_sp_suspended_lists',
						'edit_published_sp_suspended_lists'
					) as $capability
				) {
					$wp_roles->add_cap('sp_league_manager', $capability);
					$wp_roles->add_cap('administrator', $capability);
				}
			}
		}

		/**
		 * Register sp suspended list post type.
		 */
		public static function register_post_type() {
			register_post_type(
				'sp_suspended_list',
				array(
					'labels' => array(
						'name' 					=> __('Suspended Lists', 'suspended-lists-for-sportspress'),
						'singular_name' 		=> __('Suspended List', 'suspended-lists-for-sportspress'),
						'add_new_item' 			=> __('Add New Suspended List', 'suspended-lists-for-sportspress'),
						'edit_item' 			=> __('Edit Suspended List', 'suspended-lists-for-sportspress'),
						'new_item' 				=> __('New', 'suspended-lists-for-sportspress'),
						'view_item' 			=> __('View Suspended List', 'suspended-lists-for-sportspress'),
						'search_items' 			=> __('Search', 'suspended-lists-for-sportspress'),
						'not_found' 			=> __('No results found.', 'suspended-lists-for-sportspress'),
						'not_found_in_trash' 	=> __('No results found.', 'suspended-lists-for-sportspress'),
					),
					'public' 				=> true,
					'show_ui' 				=> true,
					'capability_type' 		=> 'sp_suspended_list',
					'map_meta_cap' 			=> true,
					'publicly_queryable' 	=> true,
					'exclude_from_search' 	=> false,
					'hierarchical' 			=> false,
					'rewrite' 				=> array('slug' => 'suspended-list'),
					'supports' 				=> array('title', 'page-attributes', 'author', 'thumbnail'),
					'has_archive' 			=> false,
					'show_in_nav_menus' 	=> true,
					'show_in_menu' 			=> 'edit.php?post_type=sp_suspended_list',
					'show_in_admin_bar' 	=> true
				)
			);

			// Flush rules after install
			flush_rewrite_rules();
		}

		/**
		 * Add post type.
		 *
		 * @param	array	$post_types	OPTIONAL
		 *
		 * @return	array	$post_types
		 */
		public static function add_post_type($post_types = array()) {
			$post_types[] = 'sp_suspended_list';

			return $post_types;
		}

		/**
		 * Include necessary admin files.
		 */
		public function autoload_admin() {
			require_once dirname(__FILE__).'/includes/admin/meta-boxes/class-sl-meta-box-shortcode.php';
			require_once dirname(__FILE__).'/includes/admin/meta-boxes/class-sl-meta-box-details.php';
		}

		/**
		 * Add page ids neccesary for some specific SportsPress styles.
		 *
		 * @param	array	$ids
		 *
		 * @return	array
		 */
		public function add_screen_ids($ids) {
			return array_merge($ids, array('sp_suspended_list'));
		}

		/**
		 * Enqueue admin styles.
		 */
		public function admin_styles() {
			wp_enqueue_style('suspended-lists-for-sportspress-admin-styles', $this->plugin_url().'/assets/css/admin/styles.css', array(), $this->version);
		}

		/**
		 * Enqueue front end styles.
		 */
		public function front_styles() {
			wp_enqueue_style('suspended-lists-for-sportspress-front-styles', $this->plugin_url().'/assets/css/front/styles.css', array(), $this->version);
		}

		/**
		 * Enqueue admin scripts.
		 */
		public function admin_scripts() {
			wp_register_script('suspended-lists-for-sportspress-admin-scripts', $this->plugin_url().'/assets/js/admin/scripts.js', array('jquery'), $this->version, true);
			wp_enqueue_script('suspended-lists-for-sportspress-admin-scripts');
		}

		/**
		 * Add submenu item.
		 */
		public function admin_menu() {
			add_submenu_page(
				'edit.php?post_type=sp_player',
				__('Suspended Lists', 'suspended-lists-for-sportspress'),
				__('Suspended Lists', 'suspended-lists-for-sportspress'),
				'manage_sportspress',
				'edit.php?post_type=sp_suspended_list'
			);
		}

		/**
		 * Highlights the correct top level admin menu item for post type add screens.
		 */
		public function menu_highlight() {
			global $typenow, $parent_file, $submenu_file;;

			$screen = get_current_screen();

			if (is_object($screen)) {
				if ($typenow == 'sp_suspended_list') {
					$parent_file = 'edit.php?post_type=sp_player';
					$submenu_file = 'edit.php?post_type=sp_suspended_list';
				}
			}
		}

		/**
		 * Add meta boxes and action related to its.
		 */
		public function add_meta_boxes() {
			foreach ($this->meta_boxes as $post_type=>$meta_boxes) {
				foreach ($meta_boxes as $key=>$meta_box) {
					if (array_key_exists('output', $meta_box)) {
						add_meta_box('sl_'.$key.'div', $meta_box['title'], $meta_box['output'], $post_type, $meta_box['context'], $meta_box['priority']);
					}
				}
			}
		}

		/**
		 * Suspended list shortcode.
		 *
		 * @param	mixed	$attributes
		 * @return	string
		 */
		public static function suspended_list($attributes) {
			return SP_Shortcodes::shortcode_wrapper(array(__CLASS__, __FUNCTION__.'_output'), $attributes);
		}

		/**
		 * Output the suspended list shortcode.
		 *
		 * @param	array	$attributes
		 */
		public static function suspended_list_output($attributes) {
			if (!isset($attributes['id']) && isset($attributes[0]) && is_numeric($attributes[0])) {
				$attributes['id'] = $attributes[0];
			}

			$template_path = self::get_template_path();

			sp_get_template('suspended-lists.php', $attributes, $template_path, $template_path);
		}

		/**
		 * Filter suspended lists the content of the post after it is retrieved from the database and before it is printed to the screen.
		 *
		 * @param	string	$content
		 *
		 * @return	string	$content
		 */
		public function list_content($content) {
			if (is_singular('sp_suspended_list')) {
				$content = self::add_content($content, 'suspended-list');

				// Remove link to the previous post and link to the next post
				add_filter('previous_post_link', 'remove_link');
				add_filter('next_post_link', 'remove_link');
			}

			return $content;
		}

		/**
		 * Retrieve parsed content from custom post type template.
		 *
		 * @param	string	$content
		 * @param	string	$caption	OPTIONAL
		 *
		 * @return	string	$ob
		 */
		public function add_content($content, $caption=null) {
			// Prepend caption to content if given
			if ($content) {
				if ($caption) {
					$content = "<h3 class=\"sp-post-caption\">{$caption}</h3>{$content}";
				}

				$content = "<div class=\"sp-post-content\">{$content}</div>";
			}

			// Get template path
			$template_path = self::get_template_path();

			ob_start();

			// Render the template
			echo '<div class="sp-section-content sp-section-content-data">';

			//call_user_func(sp_get_template('suspended-lists.php', array('id' => get_the_ID()), $template_path, $template_path));
			include_once($this->get_template_path().'/suspended-lists.php');

			echo '</div>';

			$ob = ob_get_clean();

			return $ob;
		}

		/**
		 * Remove link to the previous post and link to the next post.
		 *
		 * @return	boolean
		 */
		function remove_link() {
			return false;
		}

		/**
		 * Check if we are saving, then trigger an action based on the post type.
		 *
		 * @param	integer	$post_id
		 * @param	WP_Post	$post
		 */
		public function save_meta_boxes($post_id, $post) {
			if (
				empty($post_id) || empty($post) ||
				(defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) ||
				is_int(wp_is_post_revision($post)) ||
				is_int(wp_is_post_autosave($post)) ||
				empty($_POST['sp_suspended_lists_meta_nonce']) ||
				!wp_verify_nonce($_POST['sp_suspended_lists_meta_nonce'], 'sp_suspended_lists_save_data') ||
				!apply_filters('sportspress_user_can', current_user_can('edit_post', $post_id), $post_id) ||
				$post->post_type != 'sp_suspended_list'
			) {
				return;
			}

			do_action('sl_process_'.$post->post_type.'_meta', $post_id, $post);
		}

		/**
		 * Include required files.
		 */
		private function includes() {
			require_once dirname(__FILE__).'/includes/class-check-dependencies.php';
			require_once dirname(__FILE__).'/includes/class-sl-suspended-list.php';
		}

		/**
		 * Get the plugin url.
		 *
		 * @return	string
		 */
		private function plugin_url() {
			return untrailingslashit(plugins_url('/', __FILE__));
		}

		/**
		 * Get the plugin path.
		 *
		 * @return	string
		 */
		public function plugin_path() {
			return untrailingslashit(plugin_dir_path(__FILE__));
		}

		/**
		 * Get template path.
		 *
		 * @return	string
		 */
		public static function get_template_path() {
			return untrailingslashit(plugin_dir_path(__FILE__)).'/templates/';
		}
	}

	Suspended_Lists_For_SportsPress::instance();
}
