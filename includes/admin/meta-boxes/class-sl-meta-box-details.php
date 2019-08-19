<?php
if (!defined('ABSPATH')) {
	// Exit if accessed directly
	exit;
}

/**
 * Class SL_Meta_Box_Details.
 *
 * @version	0.0.1
 * @author	Leandro Ibarra
 */
class SL_Meta_Box_Details {
	/**
	 * Output the metabox.
	 *
	 * @param	WP_Post	$post	OPTIONAL
	 */
	public static function output($post) {
		wp_nonce_field('sp_suspended_lists_save_data', 'sp_suspended_lists_meta_nonce');

		$taxonomies = get_object_taxonomies('sp_list');
		$caption = get_post_meta($post->ID, 'sp_caption', true);
		$team_id = get_post_meta($post->ID, 'sp_team', true);
		$orderby = get_post_meta($post->ID, 'sp_orderby', true);
		$order = get_post_meta($post->ID, 'sp_order', true);
		$number = get_post_meta($post->ID, 'sp_number', true);
		$colum = get_post_meta($post->ID, 'sp_column', true);
		$selected = (array) get_post_meta($post->ID, 'sp_columns', true);
		?>
		<div>
			<div class="sl-row">
				<div class="sl-col-12">
					<p>
						<strong><?php _e('Heading', 'suspended-lists-for-sportspress'); ?></strong>
					</p>
					<p>
						<input type="text" id="sp_caption" name="sp_caption" class="sl-w-100" value="<?php echo esc_attr($caption); ?>" placeholder="<?php echo esc_attr(get_the_title()); ?>" />
					</p>
				</div>
			</div>

			<div class="sl-row">
				<?php foreach (array_intersect(array('sp_league', 'sp_season'), $taxonomies) as $taxonomy) { ?>
				<div class="sl-col-4">
					<?php sp_taxonomy_field($taxonomy, $post, true); ?>
				</div>
				<?php } ?>

				<div class="sl-col-4">
					<p>
						<strong><?php _e('Team', 'suspended-lists-for-sportspress'); ?></strong>
					</p>
					<p class="sp-tab-select sp-team-era-selector">
						<?php
						$args = array(
							'post_type'			=> 'sp_team',
							'name'				=> 'sp_team',
							'show_option_all'	=> __('All', 'suspended-lists-for-sportspress'),
							'selected'			=> $team_id,
							'values'			=> 'ID',
							'class'				=> 'sl-w-100'
						);
						sp_dropdown_pages($args)
						?>
					</p>
				</div>
			</div>

			<div class="sl-row">
				<div class="sl-col-4">
					<p>
						<strong><?php _e('Discounting Column', 'suspended-lists-for-sportspress'); ?></strong>
					</p>
					<p>
						<?php
						sp_dropdown_pages(
							array(
								'post_type'	=> array('sp_performance', 'sp_metric', 'sp_statistic'),
								'name'		=> 'sp_column',
								'selected'	=> $colum,
								'class'		=> 'sl-w-100'
							)
						);
						?>
					</p>
				</div>
				<div class="sl-col-4">
					<p>
						<strong><?php _e('Sort Order', 'suspended-lists-for-sportspress'); ?></strong>
					</p>
					<p>
						<select name="sp_order" class="sl-w-100">
							<option value="ASC" <?php selected('ASC', $order); ?>><?php _e('Ascending', 'suspended-lists-for-sportspress'); ?></option>
							<option value="DESC" <?php selected('DESC', $order); ?>><?php _e('Descending', 'suspended-lists-for-sportspress'); ?></option>
						</select>
					</p>
					<!--p class="description" id="sp_column"><?php _e('Used for contabilize matches suspended remaining and sort players in the list.', 'suspended-lists-for-sportspress'); ?></p-->
				</div>
				<div class="sl-col-4">
					<p>
						<strong><?php _e('Display', 'suspended-lists-for-sportspress'); ?></strong>
					</p>
					<p>
						<input name="sp_number" id="sp_number" type="number" step="1" min="0" class="small-text sl-mr-1" placeholder="<?php _e('All', 'suspended-lists-for-sportspress'); ?>" value="<?php echo $number; ?>" />
						<?php _e('players', 'suspended-lists-for-sportspress'); ?>
					</p>
				</div>
			</div>
			<div class="sl-row">
				<div class="sl-col-12">
					<p>
						<strong><?php _e('Columns', 'suspended-lists-for-sportspress'); ?></strong>
					</p>
					<div class="sp-instance">
						<ul id="sp_column-tabs" class="sp-tab-bar category-tabs">
							<li class="tabs">
								<a href="#sp_general-all"><?php _e('General', 'suspended-lists-for-sportspress'); ?></a>
							</li>
							<li>
								<a href="#sp_performance-all"><?php _e('Performance', 'suspended-lists-for-sportspress'); ?></a>
							</li>
							<li>
								<a href="#sp_metric-all"><?php _e('Metrics', 'suspended-lists-for-sportspress'); ?></a>
							</li>
							<li>
								<a href="#sp_statistic-all"><?php _e('Statistics', 'suspended-lists-for-sportspress'); ?></a>
							</li>
						</ul>

						<div id="sp_general-all" class="posttypediv tabs-panel wp-tab-panel sp-tab-panel sp-select-all-range" style="display: block;">
							<input type="hidden" value="0" name="sp_columns[]" />
							<ul class="categorychecklist form-no-clear">
								<li class="sp-select-all-container">
									<label class="selectit">
										<input type="checkbox" class="sp-select-all" />
										<strong><?php _e('Select All', 'suspended-lists-for-sportspress'); ?></strong>
									</label>
								</li>
								<li class="sp-post">
									<label class="selectit">
										<input value="number" type="checkbox" name="sp_columns[]" id="sp_columns_number" <?php checked(in_array('number', $selected)); ?>>
										<?php
										if (in_array($orderby, array('number', 'name'))) {
											_e('Squad Number', 'suspended-lists-for-sportspress');
										} else {
											_e('Rank', 'suspended-lists-for-sportspress');
										}
										?>	
									</label>
								</li>
								<li class="sp-post">
									<label class="selectit">
										<input value="team" type="checkbox" name="sp_columns[]" id="sp_columns_team" <?php checked(in_array('team', $selected)); ?>>
										<?php _e('Team', 'suspended-lists-for-sportspress'); ?>
									</label>
								</li>
								<li class="sp-post">
									<label class="selectit">
										<input value="position" type="checkbox" name="sp_columns[]" id="sp_columns_position" <?php checked(in_array('position', $selected)); ?>>
										<?php _e('Position', 'suspended-lists-for-sportspress'); ?>
									</label>
								</li>
							</ul>
						</div>

						<?php
						sp_column_checklist($post->ID, 'sp_performance', 'none', $selected);
						sp_column_checklist($post->ID, 'sp_metric', 'none', $selected);
						sp_column_checklist($post->ID, 'sp_statistic', 'none', $selected);
						?>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Save meta box data.
	 *
	 * @param	integer	$post_id
	 * @param	WP_Post $post
	 */
	public static function save($post_id, $post) {
		update_post_meta($post_id, 'sp_caption', esc_attr(sp_array_value($_POST, 'sp_caption', 0)));
		$tax_input = sp_array_value($_POST, 'tax_input', array());
		update_post_meta($post_id, 'sp_main_league', in_array('auto', sp_array_value($tax_input, 'sp_league')));
		update_post_meta($post_id, 'sp_current_season', in_array('auto', sp_array_value($tax_input, 'sp_season')));
		update_post_meta($post_id, 'sp_team', sp_array_value($_POST, 'sp_team', array()));
		update_post_meta($post_id, 'sp_orderby', sp_array_value($_POST, 'sp_column', ''));
		update_post_meta($post_id, 'sp_order', sp_array_value($_POST, 'sp_order', array()));
		update_post_meta($post_id, 'sp_number', sp_array_value($_POST, 'sp_number', array()));
		update_post_meta($post_id, 'sp_column', sp_array_value($_POST, 'sp_column', ''));
		update_post_meta($post_id, 'sp_columns', sp_array_value($_POST, 'sp_columns', array()));
	}
}
