<?php
/**
 * Class SL_Suspended_List.
 *
 * Handles individual suspended list data.
 *
 * @version	0.0.2
 * @author	Leandro Ibarra
 */
class SL_Suspended_List {
	/**
	 * @var integer
	 */
	public $ID;

	/**
	 * @var WP_Post
	 */
	public $post;

	/**
	 * @var string
	 */
	public $column;

	/**
	 * @var array
	 */
	public $columns;

	/**
	 * @var array
	 */
	public $priorities;

	/**
	 * SL_Suspended_List constructor.
	 */
	public function __construct($post) {
		if ($post instanceof WP_Post) {
			$this->ID = absint($post->ID);
			$this->post = $post;
		} else {
			$this->ID = absint($post);
			$this->post = get_post($this->ID);
		}

		$this->columns = get_post_meta($this->ID, 'sp_columns', true);

		if (is_array($this->columns)) {
			$this->columns = array_filter($this->columns);
		} else {
			$this->columns = array('number', 'team', 'position');
		}

		$this->column = get_post_meta($this->ID, 'sp_column', true);
	}

	/**
	 * Returns formatted data.
	 *
	 * @param	boolean	$admin		OPTIONAL
	 * @param	array	$leagues	OPTIONAL
	 * @param	array	$seasons	OPTIONAL
	 * @param	array	$team_id	OPTIONAL
	 *
	 * @return	array
	 */
	public function data($admin=false, $leagues=null, $seasons=null, $team_id=null) {
		if (!is_null($leagues) && '0' != $leagues) {
			$league_ids = explode(",", $leagues);
		} else {
			$league_ids = sp_get_the_term_ids($this->ID, 'sp_league');
		}

		if (!is_null($seasons) && '0' != $seasons) {
			$season_ids = explode(",", $seasons);
		} else {
			$season_ids = sp_get_the_term_ids($this->ID, 'sp_season');
		}

		if (!is_null($team_id) && '0' != $team_id) {
			$team = $team_id;
		} else {
			$team = get_post_meta($this->ID, 'sp_team', true);
		}

		$adjustments = get_post_meta($this->ID, 'sp_adjustments', true);
		$orderby = get_post_meta($this->ID, 'sp_orderby', true);
		$crop = get_post_meta($this->ID, 'sp_crop', true);

		// Force to skip if zero
		$crop = true;

		$order = get_post_meta($this->ID, 'sp_order', true);

		// Apply defaults
		if (empty($orderby)) {
			$orderby = 'number';
		}
		if (empty($order)) {
			$order = 'ASC';
		}

		// Get labels from performance variables
		$performance_labels = (array) sp_get_var_labels('sp_performance');

		// Get labels from outcome variables
		$outcome_labels = (array) sp_get_var_labels('sp_outcome');

		// Get labels from result variables
		$result_labels = (array) sp_get_var_labels('sp_result');

		// Get players automatically
		$player_ids = array();

		$args = array(
			'post_type' => 'sp_player',
			'numberposts' => -1,
			'posts_per_page' => -1,
			'meta_key' => 'sp_number',
			'orderby' => 'meta_value_num',
			'order' => 'ASC',
			'tax_query' => array(
				'relation' => 'AND',
			),
		);

		if ($league_ids) {
			$args['tax_query'][] = array(
				'taxonomy' => 'sp_league',
				'field' => 'term_id',
				'terms' => $league_ids
			);
		}

		if ($season_ids) {
			$args['tax_query'][] = array(
				'taxonomy' => 'sp_season',
				'field' => 'term_id',
				'terms' => $season_ids
			);
		}

		$team_key = 'sp_team';

		if ($team) {
			$args['meta_query'] = array(
				array(
					'key' => $team_key,
					'value' => $team
				)
			);
		}

		$args = apply_filters('sportspress_player_list_args', $args, $team);

		$players = (array) get_posts($args);

		$players = apply_filters('sportspress_player_list_players', $players, $args, $team, $team_key);

		if ($players && is_array($players)) {
			foreach ($players as $player) {
				$player_ids[] = $player->ID;
			}
		}

		// Get all leagues populated with stats where available
		$tempdata = sp_array_combine($player_ids);

		// Create entry for each player in totals
		$totals = array();
		$placeholders = array();

		// Initialize columns
		$columns = array();

		$args = array(
			'post_type' => array('sp_performance', 'sp_metric', 'sp_statistic'),
			'numberposts' => -1,
			'posts_per_page' => -1,
	  		'orderby' => 'menu_order',
	  		'order' => 'ASC',
			'meta_query' => array(
        		'relation' => 'OR',
				array(
					'key' => 'sp_format',
					'value' => 'number',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key' => 'sp_format',
					'value' => array('equation', 'text'),
					'compare' => 'NOT IN',
				),
			),
		);
		$stats = get_posts($args);

		$formats = array();
		$sendoffs = array();
		$data = array();
		$merged = array();
		$column_order = array();
		$ordered_columns = array();

		if ($stats) {
			foreach ($stats as $stat) {
				// Get post meta
				$meta = get_post_meta($stat->ID);

				// Add equation to object
				if ($stat->post_type == 'sp_metric') {
					$stat->equation = null;
				} else {
					$stat->equation = sp_array_value(sp_array_value($meta, 'sp_equation', array()), 0, 0);
				}

				// Add precision to object
				$stat->precision = sp_array_value(sp_array_value($meta, 'sp_precision', array()), 0, 0) + 0;

				// Add column icons to columns were is available
				if (get_option('sportspress_player_statistics_mode', 'values') == 'icons' && ($stat->post_type == 'sp_performance' || $stat->post_type == 'sp_statistic')) {
					$icon = apply_filters('sportspress_event_performance_icons', '', $stat->ID, 1);

					$columns[$stat->post_name] = ($icon != '') ? $icon : $stat->post_title;
				} else {
					$columns[$stat->post_name] = $stat->post_title;
				}

				// Add format
				$format = get_post_meta($stat->ID, 'sp_format', true);
				if ('' === $format) {
					$format = 'number';
				}
				$formats[$stat->post_name] = $format;

				// Add sendoffs
				$sendoff = get_post_meta($stat->ID, 'sp_sendoff', true);
				if ($sendoff) {
					$sendoffs[] = $stat->post_name;
				}

				$column_order[] = $stat->post_name;
			}
		}

		foreach ($column_order as $slug) {
			if (!in_array($slug, $this->columns)) {
				continue;
			}

			$ordered_columns[] = $slug;
		}

		$diff = array_diff($this->columns, $ordered_columns);
		$this->columns = array_merge($diff, $ordered_columns);

		foreach ($player_ids as $player_id) {
			if (!$player_id) {
				continue;
			}

			// Initialize player totals
			$totals[$player_id] = array(
				'eventsattended' => 0,
				'eventsplayed' => 0,
				'eventsstarted' => 0,
				'eventssubbed' => 0,
				'eventminutes' => 0,
				// Data to calculate remaining suspension
				'suspension_discount_data' => array()
			);

			foreach ($performance_labels as $key=>$value) {
				$totals[$player_id][$key] = 0;
			}

			foreach ($outcome_labels as $key=>$value) {
				$totals[$player_id][$key] = 0;
			}

			foreach ($result_labels as $key=>$value) {
				$totals[$player_id]["{$key}for"] = $totals[$player_id]["{$key}against"] = 0;
			}

			// Get metrics
			$metrics = (array) get_post_meta($player_id, 'sp_metrics', true);
			foreach ($metrics as $key=>$value) {
				$adjustment = sp_array_value(sp_array_value($adjustments, $player_id, array()), $key, null);

				if ($adjustment != null) {
					$metrics[$key] += $adjustment;
				}
			}

			// Get static stats
			$static = get_post_meta($player_id, 'sp_statistics', true);

			// Get league and season arrays for static stats
			$static_league_ids = (empty($league_ids) ? array(0) : $league_ids);
			$static_season_ids = (empty($season_ids) ? array(0) : $season_ids);

			// Add static stats to placeholders
			if ($static_league_ids && $static_season_ids) {
				foreach ($static_league_ids as $league_id) {
					foreach ($static_season_ids as $season_id) {
						$player_league_season_stats = sp_array_value(sp_array_value($static, $league_id, array()), $season_id, array());

						if (is_array($player_league_season_stats)) {
							foreach ($player_league_season_stats as $key=>$value) {
								$current_value = sp_array_value(sp_array_value($placeholders, $player_id, array()), $key, 0);
								$value = floatval($value);
								$placeholders[$player_id][$key] = $current_value + $value;
							}
						}
					}
				}
			} else {
				$placeholders[$player_id] = sp_array_value(sp_array_value($static, 0, array()), 0, array());
			}

			// Add metrics to placeholders
			$placeholders[$player_id] = array_merge($metrics, sp_array_value($placeholders, $player_id, array()));
		}

		$args = array(
			'post_type' => 'sp_event',
			'numberposts' => -1,
			'posts_per_page' => -1,
			'order' => 'ASC', //'DESC',
			'meta_query' => array(
				array(
					'key' => 'sp_format',
					'value' => apply_filters('sportspress_competitive_event_formats', array('league')),
					'compare' => 'IN',
				),
			),
			'tax_query' => array(
				'relation' => 'AND',
			)
		);

		if ($league_ids) {
			$args['tax_query'][] = array(
				'taxonomy' => 'sp_league',
				'field' => 'term_id',
				'terms' => $league_ids
			);
		}

		if ($season_ids) {
			$args['tax_query'][] = array(
				'taxonomy' => 'sp_season',
				'field' => 'term_id',
				'terms' => $season_ids
			);
		}

		$args = apply_filters('sportspress_list_data_event_args', $args);

		$events = get_posts($args);

		// Event loop
		foreach ($events as $index=>$event) {
			$results = (array) get_post_meta($event->ID, 'sp_results', true);
			$team_performance = get_post_meta($event->ID, 'sp_players', true);
			$timeline = (array) get_post_meta($event->ID, 'sp_timeline', true);
			$minutes = get_post_meta($event->ID, 'sp_minutes', true);

			if ($minutes === '') {
				$minutes = get_option('sportspress_event_minutes', 90);
			}

			// Add all team performance
			if (is_array($team_performance)) {
				foreach ($team_performance as $team_id=>$players) {
					if (is_array($players)) {
						foreach ($players as $player_id=>$player_performance) {
							if (array_key_exists($player_id, $totals) && is_array($totals[$player_id])) {
								// Data to calculate remaining suspension
								if (array_key_exists($this->column, $player_performance) && $player_performance[$this->column]) {
									if (!array_key_exists($event->ID, $totals[$player_id]['suspension_discount_data'])) {
										$totals[$player_id]['suspension_discount_data'][$event->ID] = array(
											'event_date' => $event->post_date,
											'amount' => $player_performance[$this->column],
											'remaining' => $player_performance[$this->column],
											'team_id' => $team_id
										);
									}
								}

								$player_performance = sp_array_value($players, $player_id, array());

								foreach ($player_performance as $key=>$value) {
									if ('outcome' == $key) {
										// Increment events attended, played, and started
										$totals[$player_id]['eventsattended']++;
										$totals[$player_id]['eventsplayed']++;
										$totals[$player_id]['eventsstarted']++;
										$totals[$player_id]['eventminutes'] += $minutes;

										// Convert to array
										if (!is_array($value)) {
											$value = array($value);
										}

										foreach ($value as $outcome) {
											if ($outcome && $outcome != '-1') {
												// Increment events attended and outcome count
												if (array_key_exists($outcome, $totals[$player_id])) {
													$totals[$player_id][$outcome]++;
												}
											}
										}
									} elseif (array_key_exists($key, $totals[$player_id])) {
										$value = floatval($value);

										$totals[$player_id][$key] += $value;
									}
								}

								$team_results = sp_array_value($results, $team_id, array());

								// Loop through home team
								foreach ($team_results as $result_slug=>$team_result) {
									if ('outcome' == $result_slug) {
										// Increment events attended
										$totals[$player_id]['eventsattended']++;

										// Continue with incrementing values if active in event
										if (sp_array_value($player_performance, 'status') != 'sub' || sp_array_value($player_performance, 'sub', 0)) {
											$totals[$player_id]['eventsplayed']++;

											// Initialize played minutes
											$played_minutes = $minutes;

											// Adjust for sendoffs and substitution time
											if (sp_array_value($player_performance, 'status') === 'sub') {
												// Substituted for another player
												$timeline_performance = sp_array_value(sp_array_value($timeline, $team_id, array()), $player_id, array());

												if (empty($timeline_performance)) {
													continue;
												}

												foreach ($sendoffs as $sendoff_key) {
													if (!array_key_exists($sendoff_key, $timeline_performance)) {
														continue;
													}

													$sendoff_times = (array) sp_array_value(sp_array_value(sp_array_value($timeline, $team_id), $player_id), $sendoff_key, array());
													$sendoff_times = array_filter($sendoff_times);
													$sendoff_time = end($sendoff_times);

													if (!$sendoff_time) {
														$sendoff_time = 0;
													}

													// Count minutes until being sent off
													$played_minutes = $sendoff_time;
												}

												// Subtract minutes prior to substitution
												$substitution_time = (int) sp_array_value(sp_array_value(sp_array_value(sp_array_value($timeline, $team_id), $player_id), 'sub'), 0, 0);
												$played_minutes -= $substitution_time;
											} else {
												// Starting lineup with possible substitution
												$subbed_out = false;

												foreach ($timeline as $timeline_team=>$timeline_players) {
													if (!is_array($timeline_players)) {
														continue;
													}

													foreach ($timeline_players as $timeline_player=>$timeline_performance) {
														if ('sub' === sp_array_value(sp_array_value($players, $timeline_player, array()), 'status') && $player_id === (int) sp_array_value(sp_array_value($players, $timeline_player, array()), 'sub', 0)) {
															$substitution_time = sp_array_value(sp_array_value(sp_array_value(sp_array_value($timeline, $team_id), $timeline_player), 'sub'), 0, 0);

															if ($substitution_time) {
																// Count minutes until substitution
																$played_minutes = $substitution_time;
																$subbed_out = true;
															}
														}
													}

													// No need to check for sendoffs if subbed out
													if ($subbed_out) {
														continue;
													}

													// Check for sendoffs
													$timeline_performance = sp_array_value($timeline_players, $player_id, array());

													if (empty($timeline_performance)) {
														continue;
													}

													foreach ($sendoffs as $sendoff_key) {
														if (!array_key_exists($sendoff_key, $timeline_performance)) {
															continue;
														}

														if (!sp_array_value($player_performance, $sendoff_key, 0)) {
															continue;
														}

														$sendoff_times = sp_array_value(sp_array_value(sp_array_value($timeline, $team_id), $player_id), $sendoff_key);
														$sendoff_times = array_filter($sendoff_times);
														$sendoff_time = end($sendoff_times);

														if (false === $sendoff_time) {
															continue;
														}

														// Count minutes until being sent off
														$played_minutes = $sendoff_time;
													}
												}
											}

											$totals[$player_id]['eventminutes'] += max(0, $played_minutes);

											if (sp_array_value($player_performance, 'status') == 'lineup') {
												$totals[$player_id]['eventsstarted']++;
											} elseif (sp_array_value($player_performance, 'status') == 'sub' && sp_array_value($player_performance, 'sub', 0)) {
												$totals[$player_id]['eventssubbed']++;
											}

											$value = $team_result;

											// Convert to array
											if (!is_array($value)) {
												$value = array($value);
											}

											foreach ($value as $outcome) {
												if ($outcome && $outcome != '-1') {

													// Increment events attended and outcome count
													if (array_key_exists($outcome, $totals[$player_id])) {
														$totals[$player_id][$outcome]++;
													}
												}
											}
										}
									} else {
										// Add to total
										$value = sp_array_value($totals[$player_id], $result_slug . 'for', 0);
										$value += floatval($team_result);
										$totals[$player_id][$result_slug.'for'] = $value;

										// Add subset
										$totals[$player_id][$result_slug.'for'.($index + 1)] = $team_result;
									}
								}

								// Loop through away teams
								if (sizeof($results)) {
									foreach ($results as $id=>$team_results) {
										if ($team_id == $id) {
											continue;
										}

										$team_results['outcome'] = null;
										unset($team_results['outcome']);

										foreach ($team_results as $result_slug=>$team_result) {
											// Add to total
											$value = sp_array_value($totals[$player_id], $result_slug.'against', 0);
											$value += floatval($team_result);
											$totals[$player_id][$result_slug.'against'] = $value;

											// Add subset
											$totals[$player_id][$result_slug.'against'.($index + 1)] = $team_result;
										}
									}
								}
							}
						}
					}
				}
			}

			$index++;
		}

		/**
		 * BEGIN
		 * Calculate remaining suspension
		 */
		foreach ($totals as $player_id=>$total) {
			if ($total['suspension_discount_data']) {
				foreach ($total['suspension_discount_data'] as $event_id=>$suspension_discount_data) {
					// Obtain next events
					$args = array(
						'post_type' => 'sp_event',
						'numberposts' => -1,
						'posts_per_page' => -1,
						'order' => 'ASC',
						'meta_query' => array(
							array(
								'key' => 'sp_status',
								'value' => array('ok', 'cancelled'),
								'compare' => 'IN'
							),
							array(
								'key' => 'sp_team',
								'value' => $suspension_discount_data['team_id']
							)
						),
						'date_query' => array(
							'after' => $suspension_discount_data['event_date']
						),
						'tax_query' => array(
							'relation' => 'AND'
						)
					);

					if ($league_ids) {
						$args['tax_query'][] = array(
							'taxonomy' => 'sp_league',
							'field' => 'term_id',
							'terms' => $league_ids
						);
					}

					if ($season_ids) {
						$args['tax_query'][] = array(
							'taxonomy' => 'sp_season',
							'field' => 'term_id',
							'terms' => $season_ids
						);
					}

					$args = apply_filters('sportspress_list_data_event_args', $args);

					$events = get_posts($args);

					// Events loop
					foreach ($events as $index=>$event) {
						$status = get_post_meta($event->ID, 'sp_status', true);
						$results = get_post_meta($event->ID, 'sp_results', true);

						$team_results = sp_array_value($results, $suspension_discount_data['team_id'], array());

						if ((array_key_exists('outcome', $team_results) && $status == 'ok') || $status == 'cancelled') {
							if ($suspension_discount_data['event_date'] < $event->post_date && $suspension_discount_data['remaining'] > 0) {
								$totals[$player_id]['suspension_discount_data'][$event_id]['remaining']--;
							}
						}
					}
				}
			}
		}
		/**
		 * END
		 * Calculate remaining suspension
		 */

		// Fill in empty placeholder values for each player
		foreach ($player_ids as $player_id) {
			if (!$player_id) {
				continue;
			}

			/**
			 * BEGIN
			 * Replace suspended column with calculated value
			 */

			// Backup original suspension data
			$totals[$player_id]["{$this->column}_original"] = $totals[$player_id][$this->column];

			// Calculate new value and replace the original value
			$remaining = 0;
			foreach ($totals[$player_id]['suspension_discount_data'] as $event_id=>$suspension_discount_data) {
				$remaining += $suspension_discount_data['remaining'];
			}
			$totals[$player_id][$this->column] = $remaining;

			// Remove suspension discount data
			unset($totals[$player_id]['suspension_discount_data']);

			/**
			 * END
			 * Replace suspended column with calculated value
			 */

			$placeholders[$player_id] = array_merge(sp_array_value($totals, $player_id, array()), array_filter(sp_array_value($placeholders, $player_id, array())));

			// Player adjustments
			$player_adjustments = sp_array_value($adjustments, $player_id, array());

			foreach ($stats as $stat) {
				if ($stat->equation === null) {
					$placeholder = sp_array_value($player_adjustments, $stat->post_name, null);
					if ($placeholder == null) {
						$placeholder = '-';
					}
				} else {
					// Solve
					$placeholder = sp_solve($stat->equation, $placeholders[$player_id], $stat->precision);

					// Adjustment
					$adjustment = sp_array_value($player_adjustments, $stat->post_name, 0);

					// Apply adjustment
					if ($adjustment != 0) {
						$placeholder += $adjustment;
						$placeholder = number_format($placeholder, $stat->precision ? $stat->precision : 0, '.', '');
					}
				}

				if ($placeholder !== '' && is_numeric($placeholder)) {
					$placeholder = sp_array_value($placeholders[$player_id], $stat->post_name, 0) + $placeholder;
				} else {
					$placeholder = sp_array_value($placeholders[$player_id], $stat->post_name, '-');
				}

				if (is_numeric($placeholder) && $stat->precision) {
					$placeholder = number_format($placeholder, $stat->precision, '.', '');
				}

				$placeholders[$player_id][$stat->post_name] = $placeholder;
			}
		}

		// Merge the data and placeholders arrays
		foreach ($placeholders as $player_id=>$player_data) {
			$player_data = array_merge($column_order, $player_data);
			$placeholders[$player_id] = $player_data;

			// Add player number and name to row
			$merged[$player_id] = array();
			if (in_array('number', $this->columns)) {
				$player_data['number'] = get_post_meta($player_id, 'sp_number', true);
			}

			$player_data['name'] = get_the_title($player_id);

			if (in_array('team', $this->columns)) {
				$player_data['team'] = get_post_meta($player_id, 'sp_team', true);
			}

			if (in_array('position', $this->columns)) {
				$player_data['position'] = null;
			}

			foreach ($player_data as $key=>$value) {
				// Use static data if key exists and value is not empty, else use placeholder
				if (array_key_exists($player_id, $tempdata) && array_key_exists($key, $tempdata[$player_id]) && $tempdata[$player_id][$key] != '') {
					$value = $tempdata[$player_id][$key];
				}

				$merged[$player_id][$key] = $value;
			}
		}

		if ($orderby != 'number' || $order != 'ASC') {
			$this->priorities = array(
				array(
					'key' => $orderby,
					'order' => $order,
				),
			);
			uasort($merged, array($this, 'sort'));
		}

		// Rearrange data array to reflect values
		foreach ($merged as $key=>$value) {
			if ($crop && !(float) sp_array_value($value, $orderby, 0)) {
				// Crop
				unset($merged[$key]);
			} else {
				// Add to main data array
				$data[$key] = $tempdata[$key];
			}
		}

		if ($admin) {
			// Convert to time notation
			if (in_array('time', $formats)) {
				foreach ($placeholders as $player=>$stats) {
					if (!is_array($stats)) {
						continue;
					}

					foreach ($stats as $key=>$value) {
						// Continue if not time format
						if ('time' !== sp_array_value($formats, $key)) {
							continue;
						}

						$intval = intval($value);
						$timeval = gmdate('i:s', $intval);
						$hours = floor($intval / 3600);

						if ('00' != $hours) {
							$timeval = "{$hours}:{$timeval}";
						}

						$timeval = preg_replace('/^0/', '', $timeval);

						$placeholders[$player][$key] = $timeval;
					}
				}
			}

			$labels = array();
			foreach ($this->columns as $key) {
				if ($key == 'number') {
					$labels[$key] = '#';
				} elseif ($key == 'team') {
					$labels[$key] = __('Team', 'suspended-lists-for-sportspress');
				} elseif ($key == 'position' ) {
					$labels[$key] = __('Position', 'suspended-lists-for-sportspress');
				} elseif (array_key_exists($key, $columns)) {
					$labels[$key] = $columns[$key];
				}
			}

			return array($labels, $data, $placeholders, $merged, $orderby);
		} else {
			// Convert to time notation
			if (in_array('time', $formats)) {
				foreach ($merged as $player=>$stats) {
					if (!is_array($stats)) {
						continue;
					}

					foreach ($stats as $key=>$value) {
						// Continue if not time format
						if ('time' !== sp_array_value($formats, $key)) {
							continue;
						}

						$intval = intval($value);
						$timeval = gmdate('i:s', $intval);
						$hours = floor($intval / 3600);

						if ('00' != $hours) {
							$timeval = "{$hours}:{$timeval}";
						}

						$timeval = preg_replace('/^0/', '', $timeval);

						$merged[$player][$key] = $timeval;
					}
				}
			}

			if (!is_array($this->columns)) {
				$this->columns = array();
			}

			foreach ($columns as $key=>$label) {
				if (!in_array($key, $this->columns)) {
					unset($columns[$key]);
				}
			}

			$labels = array();
			if (in_array('number', $this->columns)) {
				$labels['number'] = '#';
			}

			$labels['name'] = __('Player', 'suspended-lists-for-sportspress');

			if (in_array('team', $this->columns)) {
				$labels['team'] = __('Team', 'suspended-lists-for-sportspress');
			}

			if (in_array('position', $this->columns)) {
				$labels['position'] = __('Position', 'suspended-lists-for-sportspress');
			}

			$merged[0] = array_merge($labels, $columns);

			return $merged;
		}
	}

	/**
	 * Sort the table by priorities.
	 *
	 * @param	array	$a
	 * @param	array	$b
	 *
	 * @return	integer
	 */
	public function sort($a, $b) {
		// Loop through priorities
		if (is_array($this->priorities)) {
			foreach ($this->priorities as $priority) {
				// Proceed if columns are not equal
				if (sp_array_value($a, $priority['key'], 0) != sp_array_value($b, $priority['key'], 0)) {
					if ($priority['key'] == 'name') {
						$output = strcmp(sp_array_value($a, 'name', null), sp_array_value($b, 'name', null));
					} else {
						// Compare performance values
						$output = floatval(sp_array_value($a, $priority['key'], 0)) - floatval(sp_array_value($b, $priority['key'], 0));
					}

					// Flip value if descending order
					if ($priority['order'] == 'DESC') {
						$output = 0 - $output;
					}

					return ($output > 0);
				}
			}
		}

		// Default sort by number
		return floatval(sp_array_value($a, 'number', 0)) - floatval(sp_array_value($b, 'number', 0));
	}

	/**
	 * Determine if a meta key is set for the post ID.
	 *
	 * @param	mixed	$key
	 * @return	boolean
	 */
	public function __isset($key) {
		return metadata_exists('post', $this->ID, 'sp_'.$key);
	}

	/**
	 * Retrieve post meta field for the post ID.
	 *
	 * @param	mixed	$key
	 * @return	boolean
	 */
	public function __get($key) {
		if (!isset($key)) {
			$value = $this->post;
		} else {
			$value = get_post_meta($this->ID, 'sp_'.$key, true);
		}

		return $value;
	}
}
