<?php
/**
 * Template Suspended List.
 *
 * @version	0.0.1
 * @author	Leandro Ibarra
 */

if (!defined('ABSPATH')) {
	// Exit if accessed directly
	exit;
}

$defaults = array(
	'id'					=> get_the_ID(),
	'title'					=> false,
	'number'				=> -1,
	'grouptag'				=> 'h4',
	'columns'				=> null,
	'grouping'				=> null,
	'orderby'				=> 'default',
	'order'					=> 'ASC',
	'show_all_players_link'	=> false,
	'show_title'			=> get_option('sportspress_list_show_title', 'yes') == 'yes' ? true : false,
	'show_player_photo'		=> get_option('sportspress_list_show_photos', 'no') == 'yes' ? true : false,
	'show_player_flag'		=> get_option('sportspress_list_show_flags', 'no') == 'yes' ? true : false,
	'link_posts'			=> get_option('sportspress_link_players', 'yes') == 'yes' ? true : false,
	'link_teams'			=> get_option('sportspress_link_teams', 'no') == 'yes' ? true : false,
	'responsive'			=> get_option('sportspress_enable_responsive_tables', 'no') == 'yes' ? true : false,
	'sortable'				=> get_option('sportspress_enable_sortable_tables', 'yes') == 'yes' ? true : false,
	'scrollable'			=> get_option('sportspress_enable_scrollable_tables', 'yes') == 'yes' ? true : false,
	'paginated'				=> get_option('sportspress_list_paginated', 'yes') == 'yes' ? true : false,
	'rows'					=> get_option('sportspress_list_rows', 10),
	'leagues'				=> null,
	'seasons'				=> null,
	'team'					=> null
);

extract($defaults, EXTR_SKIP);

// Backward compatibility
if (isset($performance)) {
	$columns = $performance;
}

// Determine number of players to display
if (-1 === $number) {
	$number = (int) get_post_meta($id, 'sp_number', true);

	if ($number <= 0) {
		$number = -1;
	}
}

// Explode into array
if (null !== $columns && !is_array($columns)) {
	$columns = explode(',', $columns);
}

$list = new SL_Suspended_List($id);

if (isset($columns) && null !== $columns) {
	$list->columns = $columns;
}

$data = $list->data(false, $leagues, $seasons, $team);

// The first row should be labels
$labels = $data[0];

// Create a unique identifier based on the current time in microseconds
$identifier = uniqid('suspendedlist_');

// Remove the first row and 'head' row to leave us with the actual data
unset($data[0]);

if ($orderby == 'default') {
	$orderby = $list->orderby;
	$order = $list->order;
} else {
	$list->priorities = array(
		array(
			'key' => $orderby,
			'order' => $order
		),
	);

	uasort($data, array($list, 'sort'));
}

$output = '';

if ($show_title && false === $title && $id) {
	$caption = $list->caption;
	$title = ($caption) ? $caption : get_the_title($id);
}

if ($title) {
	$output .= "<{$grouptag} class=\"sp-table-caption\">{$title}</{$grouptag}>";
}

if (intval($number) > 0) {
	$limit = $number;
}

$thead = '<thead><tr>';
	
if (!is_array($labels) || array_key_exists('number', $labels)) {
	if (in_array($orderby, array('number', 'name'))) {
		$thead .= '<th class="data-number">#</th>';
	} else {
		$thead .= '<th class="data-rank">'.__('Rank', 'suspended-lists-for-sportspress').'</th>';
	}
}

foreach ($labels as $key=>$label) {
	if ($key !== 'number' && (!is_array($columns) || $key == 'name' || in_array($key, $columns))) {
		$thead .= "<th class=\"data-{$key}\">{$label}</th>";
	}

	if (preg_match("/title=\"(.*?)\"/", $label, $new_label)) {
		$labels[$key] = $label[1];
	}
}

$thead .= '</tr></thead>';

$count = 0;

$tbody = '';

foreach ($data as $player_id=>$row) {
	if (isset($limit) && $count >= $limit) {
		continue;
	}

	$name = sp_array_value($row, 'name', null);

	if (!$name) {
		continue;
	}

	$tbody .= '<tr class="'.($count % 2 == 0 ? 'odd' : 'even').'">';

	// Rank or number
	if (!is_array($labels) || array_key_exists('number', $labels)) {
		if (isset($orderby) && $orderby != 'number') {
			$tbody .= '<td class="data-rank" data-label="'.$labels['number'].'">'.($count + 1).'</td>';
		} else {
			$tbody .= '<td class="data-number" data-label="'.$labels['number'].'">'.sp_array_value($row, 'number', '&nbsp;').'</td>';
		}
	}

	$name_class = '';

	if ($show_player_photo) {
		if (has_post_thumbnail($player_id)) {
			$logo = get_the_post_thumbnail($player_id, 'sportspress-fit-icon');
			$name = "<span class=\"player-photo\">{$logo}</span>{$name}";
			$name_class .= ' has-photo';
		}
	}

	if ($show_player_flag) {
		$player = new SP_Player($player_id);
		$nationalities = $player->nationalities();

		if (!empty($nationalities)) {
			foreach ($nationalities as $nationality) {
				$name = ''.
					'<span class="player-flag">'.
						'<img src="'.plugin_dir_url(SP_PLUGIN_FILE).'assets/images/flags/'.strtolower($nationality).'.png" alt="'.$nationality.'" />'.
					'</span>'.
					$name;
			}

			$name_class .= ' has-photo';
		}
	}

	if ($link_posts) {
		$permalink = get_post_permalink($player_id);
		$name = "<a href=\"{$permalink}\">{$name}</a>";
	}

	$tbody .= "<td class=\"data-name{$name_class}\" data-label=\"{$labels['name']}\">{$name}</td>";
	
	if (array_key_exists('team', $labels)) {
		$team = sp_array_value($row, 'team', get_post_meta($id, 'sp_current_team', true));
		$team_name = $team ? sp_team_short_name($team) : '-';

		if ($link_teams && false !== get_post_status($team)) {
			$team_name = '<a href="'.get_post_permalink($team).'">'.$team_name.'</a>';
		}

		$tbody .= "<td class=\"data-team\" data-label=\"{$labels['team']}\">{$team_name}</td>";
	}
	
	if (array_key_exists('position', $labels)) {
		$position = sp_array_value($row, 'position', null);

		if (null === $position || !$position) {
			$positions = wp_strip_all_tags(get_the_term_list($player_id, 'sp_position', '', ', '));
		} else {
			$position_term = get_term_by('id', $position, 'sp_position', ARRAY_A);
			$positions = sp_array_value($position_term, 'name', '&mdash;');
		}

		$tbody .= "<td class=\"data-position\" data-label=\"{$labels['position']}\">{$positions}</td>";
	}

	foreach ($labels as $key=>$value) {
		if (in_array($key, array('number', 'name', 'team', 'position'))) {
			continue;
		}

		if (!is_array($columns) || in_array($key, $columns)) {
			$tbody .= '<td class="data-'.$key.'" data-label="'.$labels[$key].'">'.sp_array_value($row, $key, '&mdash;').'</td>';
		}
	}

	$tbody .= '</tr>';

	$count++;
}

$output .= '<div class="sp-template sp-template-player-list">';

$output .= ''.
	'<div class="sp-table-wrapper">'.
		'<table class="sp-player-list sp-data-table'.
			($sortable ? ' sp-sortable-table' : '').
			($responsive ? ' sp-responsive-table '.$identifier : '').
			($scrollable ? ' sp-scrollable-table' : '').
			($paginated ? ' sp-paginated-table' : '').
			'" data-sp-rows="'.$rows.'"'.
		'>';

$output .= "{$thead}<tbody>";

$output .= $tbody;

$output .= '</tbody></table></div>';

if ($show_all_players_link) {
	$output .= ''.
		'<div class="sp-player-list-link sp-view-all-link">'.
			'<a href="'.get_permalink($id).'">'.__('View all players', 'suspended-lists-for-sportspress').'</a>'.
		'</div>';
}

$output .= '</div>';

echo $output;
