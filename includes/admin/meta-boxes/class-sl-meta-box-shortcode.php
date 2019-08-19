<?php
if (!defined('ABSPATH')) {
	// Exit if accessed directly
	exit;
}

/**
 * Class SL_Meta_Box_Shortcode.
 *
 * @version	0.0.1
 * @author	Leandro Ibarra
 */
class SL_Meta_Box_Shortcode {
	/**
	 * Output the metabox.
	 *
	 * @param	WP_Post	$post	OPTIONAL
	 */
	public static function output($post) {
		$format = get_post_meta($post->ID, 'sp_format', true);
		if (!$format) {
			$format = 'list';
		}
		?>
		<p class="howto">
			<?php _e('Copy this code and paste it into your post, page or text widget content.', 'suspended-lists-for-sportspress'); ?>
		</p>
		<p>
			<input type="text" value="<?php sp_shortcode_template('suspended_'.$format, $post->ID); ?>" readonly="readonly" class="code widefat" />
		</p>
		<?php
	}
}