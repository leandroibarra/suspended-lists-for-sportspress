<?php
/**
 * Contains Check_Dependencies class.
 * 
 * This class is based from original Travis Smith <t@wpsmith.net> WPS-Extend-Plugin class.
 *
 * @see https://github.com/wpsmith/WPS-Extend-Plugin
 * @see https://wpsmith.net/2015/extending-any-plugin-properly
 *
 * @author	Leandro Ibarra <https://github.com/leandroibarra>
 * @license	GPL2
 * @version	0.0.2
 */

if (!class_exists('Check_Dependencies')) {
	/**
	 * Class Check_Dependencies
	 *
	 * Extends an existing plugin.
	 *
	 * @author	Leandro Ibarra
	 * @license	GPL2
	 * @version	0.0.2
	 */
	class Check_Dependencies {
		/**
		 * Dependent plugin path.
		 *
		 * @var string
		 */
		private $plugin_path;

		/**
		 * Dependent plugin name.
		 *
		 * @var string
		 */
		private $plugin_name;

		/**
		 * Current plugin basename. File reference path to root including filename.
		 *
		 * @var string
		 */
		private $basename;

		/**
		 * Dependent plugin minimum version required.
		 *
		 * @var string
		 */
		private $minimum_version;

		/**
		 * Dependent plugin text domain.
		 *
		 * @var string
		 */
		public $text_domain;

		/**
		 * Action being performed on plugins page.
		 *
		 * @var string
		 */
		private $action;

		/**
		 * Message to be displayed.
		 *
		 * @var string
		 */
		public $message;

		/**
		 * Plugin data.
		 *
		 * @var array
		 */
		public $plugin_data;

		/**
		 * Check_Dependencies constructor.
		 *
		 * @param	string	$plugin_path
		 * @param	string	$plugin_name
		 * @param	string	$basename
		 * @param	string	$minimum_version	OPTIONAL
		 * @param	string	$text_domain		OPTIONAL
		 */
		public function __construct($plugin_path, $plugin_name, $basename, $minimum_version='', $text_domain='') {
			// Setup properties
			$this->plugin_path = $plugin_path;
			$this->plugin_name = $plugin_name;
			$this->basename = $basename;
			$this->minimum_version = $minimum_version;
			$this->text_domain = $text_domain;

			// Set message on initial instance
			$this->set_message('');

			if ('plugins.php' === basename($_SERVER['PHP_SELF']) && !(defined('WP_CLI') && WP_CLI)) {
				$this->set_action_type();

				// Add admin notice
				add_action('admin_notices', array($this, 'admin_notice'));

				// Late deactivation so we can output the notifications
				add_filter("plugin_action_links_{$plugin_path}", array($this, 'plugin_action_links_deactivate'));
				add_filter("network_admin_plugin_action_links_{$plugin_path}", array($this, 'plugin_action_links_deactivate'));

				// Fix current plugin action links
				add_filter('plugin_action_links_'.plugin_basename($basename), array($this, 'plugin_action_links'), 10, 4);
				add_filter('network_admin_plugin_action_links_'.plugin_basename($basename), array($this, 'plugin_action_links'), 10, 4);

				// Add notice on plugin row
				add_action('after_plugin_row_'.plugin_basename($basename), array($this, 'plugin_row'));
			} else {
				// Maybe deactivate on update of active_plugins and active_sitewide_plugins options
				// deactivated_plugin action and deactivate_.$plugin do not fire if plugin is being deactivated silently
				add_action('update_option_active_sitewide_plugins', array($this, 'maybe_deactivate'), 10, 2);
				add_action('update_option_active_plugins', array($this, 'maybe_deactivate'), 10, 2);
			}

		}

		/**
		 * Conditional helper function to determine which generic action is being taken.
		 *
		 * @param	string	$action
		 *
		 * @return	boolean	$generic_action
		 */
		private function is_action($action) {
			switch ($action) {
				case 'activate':
				case 'deactivate':
					$generic_action = ($action === $this->action || "{$action}-multi" === $this->action);
					break;
				default:
					$generic_action = false;
					break;
			}

			return $generic_action;
		}

		/**
		 * Sets the action being taken by the plugins.php page.
		 */
		private function set_action_type() {
			if (isset($_REQUEST['deactivate-multi']) && $_REQUEST['deactivate-multi']) {
				$this->action = 'deactivate-multi';
			} elseif (isset($_REQUEST['activate-multi']) && $_REQUEST['activate-multi']) {
				$this->action = 'activate-multi';
			} elseif (isset($_REQUEST['deactivate']) && $_REQUEST['deactivate']) {
				$this->action = 'deactivate';
			} elseif (isset($_REQUEST['activate']) && $_REQUEST['activate']) {
				$this->action = 'activate';
			}
		}

		/**
		 * Maybe fix the action links as WordPress believes the plugin is active when it may have been deactivated.
		 *
		 * @return	array	$actions
		 */
		public function plugin_action_links_deactivate($actions) {
			if (!$this->is_active()) {
				self::deactivate_self($this->basename);
			}

			return $actions;
		}

		/**
		 * Maybe fix the action links as WordPress believes the plugin is active when it may have been deactivated.
		 *
		 * @param	array	$actions
		 * @param	string	$plugin_file
		 * @param	array	$plugin_data
		 * @param	string	$context
		 *
		 * @return	array	$actions
		 */
		public function plugin_action_links($actions, $plugin_file, $plugin_data, $context) {
			if (!$this->is_active()) {
				if (isset($actions['deactivate'])) {
					$params = self::get_url_params($actions['deactivate']);
					$params = wp_parse_args($params, array('s' => ''));
					unset($actions['deactivate']);

					// Change action link deactivate to activate
					$screen = get_current_screen();

					if ($screen->in_admin('network')) {
						if (current_user_can('manage_network_plugins')) {
							$actions['activate'] = '' .
								'<a href="' .
									wp_nonce_url(
										"plugins.php?action=activate&amp;plugin={$plugin_file}&amp;plugin_status={$context}&amp;paged={$params['paged']}&amp;s={$params['s']}",
										"activate-plugin_{$plugin_file}"
									) .
									'" class="edit" aria-label="' .
									esc_attr(
										sprintf(
											__('Network Activate %s'),
											$plugin_data['Name']
										)
									) .
								'">'.__('Network Activate').'</a>';
						}
						if (current_user_can('delete_plugins')) {
							$actions['delete'] = '' .
								'<a href="' .
									wp_nonce_url(
										"plugins.php?action=delete-selected&amp;checked[]={$plugin_file}&amp;plugin_status={$context}&amp;paged={$params['paged']}&amp;s={$params['s']}",
										'bulk-plugins'
									) .
									'" class="delete" aria-label="' .
									esc_attr(
										sprintf(
											__('Delete %s'),
											$plugin_data['Name']
										)
									) .
								'">'.__('Delete').'</a>';
						}
					} else {
						$actions['activate'] = '' .
							'<a href="' .
								wp_nonce_url(
									"plugins.php?action=activate&amp;plugin={plugin_file}&amp;plugin_status={$context}&amp;paged={$params['paged']}&amp;s={$params['s']}",
									"activate-plugin_{$plugin_file}"
								) .
								'" class="edit" aria-label="' .
								esc_attr(
									sprintf(
										__('Activate %s', $this->text_domain),
										$plugin_data['Name']
									)
								) .
							'">'.__('Activate', $this->text_domain).'</a>';

						if (!is_multisite() && current_user_can('delete_plugins')) {
							$actions['delete'] = '' .
								'<a href="' .
									wp_nonce_url(
										'plugins.php?action=delete-selected&amp;checked[]=' . $plugin_file . '&amp;plugin_status=' . $context . '&amp;paged=' . $params['paged'] . '&amp;s=' . $params['s'],
										'bulk-plugins'
									) .
									'" class="delete" aria-label="' .
									esc_attr(
										sprintf(
											__('Delete %s', $this->text_domain),
											$plugin_data['Name']
										)
									) .
								'">'.__('Delete', $this->text_domain).'</a>';
						}
					}
				}
			}

			return $actions;
		}

		/**
		 * Deactivate ourself if dependent plugin is deactivated.
		 *
		 * @param	mixed	$old_value
		 */
		public function maybe_deactivate($old_value) {
			if (!$this->is_active()) {
				self::deactivate_self($this->basename);

				if (defined('WP_CLI') && WP_CLI) {
					WP_CLI::error($this->get_message('deactivate'));
				}
			}
		}

		/**
		 * Returns the message to be displayed.
		 *
		 * @return	string	$this->message
		 */
		private function get_message() {
			return $this->message;
		}

		/**
		 * Sets the message based on the needed notification type.
		 *
		 * @param	string	$type
		 */
		private function set_message($type) {
			$dependency = $this->get_plugin_data('Name') ? $this->get_plugin_data('Name') : $this->plugin_name;
			$current = $this->get_plugin_data('Name', 'current') ? $this->get_plugin_data('Name', 'current') : plugin_basename($this->basename);

			switch ($type) {
				case 'deactivate':
					$this->message = sprintf(
						__('<strong>%1$s</strong> (minimum version %2$s) is required for %3$s. Please before deactivate <strong>%3$s</strong> plugin.', $this->text_domain),
						$dependency,
						$this->minimum_version,
						$current
					);

					break;
				case 'upgrade':
				case 'update':
				case 'activate':
				default:
					if (('update' === $type || 'upgrade' === $type) && !$this->is_plugin_at_minimum_version()) {
						$action = __('update', $this->text_domain);
					} else {
						$action = __('activate', $this->text_domain);
					}

					$this->message = sprintf(
						__('<strong>%1$s</strong> (minimum version %2$s) is required. Please %3$s it before activate <strong>%4$s</strong> plugin.', $this->text_domain),
						$dependency,
						$this->minimum_version,
						$action,
						$current
					);

					break;
			}
		}

		/**
		 * Returns plugin data.
		 *
		 * @param	string			$attribute OPTIONAL
		 * @param	string			$plugin OPTIONAL
		 *
		 * @return	string|array	$plugin_data
		 */
		private function get_plugin_data($attribute='', $plugin='') {
			$plugin_data = '';

			if (!$plugin || in_array($plugin, array('dependency', 'current'))) {
				if (!$plugin || 'dependency' === $plugin) {
					// Dependency plugin, default
					$plugin = $this->plugin_path;
					$plugin_path = trailingslashit(plugin_dir_path(dirname($this->basename))).$this->plugin_path;
				} elseif ('current' === $plugin) {
					// Allow current plugin_data to be returned
					$plugin = plugin_basename($this->basename);
					$plugin_path = plugin_dir_path(dirname($this->basename)).$plugin;
				}

				// Set plugin_data
				if (!isset($this->plugin_data[$plugin]) || (isset($this->plugin_data[$plugin]) && !$this->plugin_data[$plugin])) {
					require_once(ABSPATH.'wp-admin/includes/plugin.php');
					$this->plugin_data = get_plugin_data($plugin_path);
				}

				$plugin_data = $this->plugin_data;

				// Obtain specific attribute if its defined
				if ($attribute && isset($this->plugin_data[$attribute])) {
					$plugin_data = $this->plugin_data[$attribute];
				}
			}

			return $plugin_data;
		}

		/**
		 * Returns an array of parameters from HTML markup containing a link.
		 *
		 * @param	string	$html
		 *
		 * @return	array	$params
		 */
		private static function get_url_params($html) {
			$params = array();

			// Capture parameters
			preg_match("/<a\s[^>]*href=\"([^\"]*)\"[^>]*>(.*)<\/a>/", $html, $output);

			if ($output) {
				preg_match_all('/([^?&=#]+)=([^&#]*)/', html_entity_decode(urldecode($output[1])), $matches);

				// Combine the keys and values onto an assoc array
				$params = array_combine($matches[1], $matches[2]);
			}

			return $params;
		}

		/**
		 * Deactivates the plugin by itself.
		 *
		 * Function attempts to determine whether to deactivate extension plugin based on whether the depdendent plugin is active or not.
		 *
		 * @param	string|array	$plugins
		 * @param	boolean			$network_wide	OPTIONAL
		 */
		public static function deactivate_self($plugins, $network_wide=false) {
			if (is_multisite() && false !== $network_wide) {
				$network_wide = is_plugin_active_for_network($plugins);
			}

			deactivate_plugins(plugin_basename($plugins), true, $network_wide);
		}

		/**
		 * Checks whether the dependent plugin(s) is/are active by checking the active_plugins list.
		 *
		 * @return	boolean	$active
		 */
		public function is_active() {
			if (!is_plugin_active($this->plugin_path)) {
				// Plugin is not activated
				if ($this->is_action('activate')) {
					$this->set_message('activate');
				} elseif ($this->is_action('deactivate')) {
					$this->set_message('deactivate');
				}

				$active = false;
			} elseif (!$this->is_plugin_at_minimum_version()) {
				// Plugin active and not at miminum version
				$this->set_message('update');

				$active = false;
			} else {
				// All good!
				$active = true;
			}

			return $active;
		}

		/**
		 * Determines whether the given plugin is at the minimum version.
		 *
		 * @return	boolean
		 */
		private function is_plugin_at_minimum_version() {
			return (!$this->minimum_version) ? true : ((floatval($this->get_plugin_data('Version')) >= floatval($this->minimum_version)) ? true : false);
		}

		/**
		 * Outputs an admin notice if plugin is trying to be activated when dependent plugin is not activated.
		 */
		public function admin_notice() {
			if (!$this->is_active()) {
				if (isset($_GET['activate'])) {
					unset($_GET['activate']);
				}

				printf('<div class="error notice is-dismissible"><p class="extension-message">%s</p></div>', $this->get_message());
			}
		}

		/**
		 * Adds a notice to the plugin row to inform the user of the dependency.
		 */
		public function plugin_row() {
			if (!$this->is_active()) {
				$current = $this->get_plugin_data('Name', 'current') ? $this->get_plugin_data('Name', 'current') : plugin_basename($this->basename);

				printf(
					'</tr><tr class="plugin-update-tr"><td colspan="3" class="plugin-update"><div class="notice-error notice inline" style="background-color: #FFEBE8;"><p>%s</p></div></td>',
					str_replace(
						"<strong>{$current}</strong>",
						__('this', $this->text_domain),
						$this->get_message()
					)
				);
			}
		}
	}
}