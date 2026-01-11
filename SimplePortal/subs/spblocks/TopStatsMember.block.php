<?php

/**
 * @package SimplePortal
 *
 * @author SimplePortal Team
 * @copyright 2015-2026 SimplePortal Team
 * @license BSD 3-clause
 * @version 2.0.0
 */

use ElkArte\Cache\Cache;
use ElkArte\Database\QueryInterface;

/**
 * Top stats block, shows the top x members who have achieved top position of various stats
 * Designed to be flexible, so adding additional member stats is easy
 *
 * @param array $parameters
 *        'limit' => number of top posters to show
 *        'type' => top stat to show
 *        'sort_asc' => direction to show the list
 *        'last_active_limit'
 *        'enable_label' => use the label
 *        'list_label' => title for the list
 * @param int $id - not used in this block
 * @param bool $return_parameters if true returns the configuration options for the block
 */
class TopStatsMemberBlock extends SPAbstractBlock
{
	protected $sp_topStatsSystem = [];
	protected $color_ids = [];

	/**
	 * Constructor, used to define block parameters
	 *
	 * @param QueryInterface|null $db
	 */
	public function __construct($db = null)
	{
		global $txt;

		$this->block_parameters = [
			'type' => [
				'0' => $txt['sp_topStatsMember_total_time_logged_in'],
				'1' => $txt['sp_topStatsMember_Posts'],
				'2' => $txt['sp_topStatsMember_Karma_Good'],
				'3' => $txt['sp_topStatsMember_Karma_Bad'],
				'4' => $txt['sp_topStatsMember_Karma_Total'],
				'5' => $txt['sp_topStatsMember_Likes_Received'],
				'6' => $txt['sp_topStatsMember_Likes_Given'],
				'7' => $txt['sp_topStatsMember_Likes_Total'],
			],
			'limit' => 'int',
			'sort_asc' => 'check',
			'last_active_limit' => 'int',
			'enable_label' => 'check',
			'list_label' => 'text',
		];

		parent::__construct($db);
		$this->setupSystemArray();
	}

	/**
	 * Configures the system array used for the top statistics block.
	 *
	 * - Initializes a predefined array that specifies different statistics and
	 *   their associated configurations, such as data fields, sorting, output formats,
	 *   and additional processing logic.
	 * - Supports multiple statistics types, such as posts, karma, and likes, with
	 *   each type tailored for different functionalities and outputs.
	 *
	 * @return void
	 */
	private function setupSystemArray()
	{
		global $txt;

		/**
		 * Configuration for the Top Stats system.
		 *
		 * Each array key corresponds to a 'type' index defined in the constructor.
		 *
		 * @type string $name Internal identifier for the stat.
		 * @type string $field DB fields to select (must use 'mem.' prefix for member columns).
		 * @type string $order The DB column name or expression used for sorting.
		 * @type string $where (Optional) Additional DB WHERE clause constraints.
		 * @type string $output_text The display string. Placeholders wrapped in % (e.g., %posts%)
		 * are replaced by the corresponding database field value.
		 * @type callable $output_function (Optional) Anonymous function to process $row by reference
		 * before output. Useful for formatting dates or calculations.
		 * @type bool $reverse If true, the default sorting direction is inverted.
		 * @type bool $enabled Whether this stat type is available for use (e.g., check mod settings).
		 * @type string $error_msg The language string to display if 'enabled' is false.
		 */
		$this->sp_topStatsSystem = [
			'0' => [
				'name' => 'Total time logged in',
				'field' => 'mem.total_time_logged_in',
				'order' => 'mem.total_time_logged_in',
				'output_function' => function(&$row) {
					global $txt;

					// Figure out the days, hours and minutes.
					$timeDays = floor($row['total_time_logged_in'] / 86400);
					$timeHours = floor(($row['total_time_logged_in'] % 86400) / 3600);

					// Figure out which things to show... (days, hours, minutes, etc.)
					$timelogged = '';
					if ($timeDays > 0)
					{
						$timelogged .= $timeDays . $txt['totalTimeLogged5'];
					}

					if ($timeHours > 0)
					{
						$timelogged .= $timeHours . $txt['totalTimeLogged6'];
					}

					$timelogged .= floor(($row['total_time_logged_in'] % 3600) / 60) . $txt['totalTimeLogged7'];
					$row['timelogged'] = $timelogged;
				},
				'output_text' => ' %timelogged%',
				'reverse_sort_asc' => false,
				'enabled' => true,
			],
			'1' => [
				'name' => 'Posts',
				'field' => 'mem.posts',
				'order' => 'mem.posts',
				'output_text' => ' %posts% ' . $txt['posts'],
				'enabled' => true,
			],
			'2' => [
				'name' => 'Karma Good',
				'field' => 'mem.karma_good, mem.karma_bad',
				'order' => 'mem.karma_good',
				'output_function' => function(&$row) {
					$row['karma_total'] = $row['karma_good'] - $row['karma_bad'];
				},
				'output_text' => (!empty($this->_modSettings['karmaLabel']) ? $this->_modSettings['karmaLabel'] : '') . ($this->_modSettings['karmaMode'] == 1 ? ' %karma_total%' : ' +%karma_good%\-%karma_bad%'),
				'enabled' => !empty($this->_modSettings['karmaMode']),
				'error_msg' => $txt['sp_karma_is_disabled'],
			],
			'3' => [
				'name' => 'Karma Bad',
				'field' => 'mem.karma_good, mem.karma_bad',
				'order' => 'mem.karma_bad',
				'output_function' => function(&$row) {
					$row['karma_total'] = $row['karma_good'] - $row['karma_bad'];
				},
				'output_text' => (!empty($this->_modSettings['karmaLabel']) ? $this->_modSettings['karmaLabel'] : '') . ($this->_modSettings['karmaMode'] == 1 ? ' %karma_total%' : ' +%karma_good%\-%karma_bad%'),
				'enabled' => !empty($this->_modSettings['karmaMode']),
				'error_msg' => $txt['sp_karma_is_disabled'],
			],
			'4' => [
				'name' => 'Karma Total',
				'field' => 'mem.karma_good, mem.karma_bad',
				'order' => 'FLOOR(1000000+karma_good-karma_bad)',
				'output_function' => function(&$row) {
					$row['karma_total'] = $row['karma_good'] - $row['karma_bad'];
				},
				'output_text' => $this->_modSettings['karmaLabel'] . ($this->_modSettings['karmaMode'] == 1 ? ' %karma_total%' : ' &plusmn;%karma_good%\%karma_bad%'),
				'enabled' => !empty($this->_modSettings['karmaMode']),
				'error_msg' => $txt['sp_karma_is_disabled'],
			],
			'5' => [
				'name' => 'Likes Received/Given',
				'field' => 'mem.likes_received',
				'order' => 'mem.likes_received',
				'output_text' => '%likes_received% ' . $txt['sp_topStatsMember_Likes_Received'],
				'enabled' => !empty($this->_modSettings['likes_enabled']),
				'error_msg' => $txt['sp_likes_is_disabled'],
			],
			'6' => [
				'name' => 'Likes Given',
				'field' => 'mem.likes_given',
				'order' => 'mem.likes_given',
				'output_text' => '%likes_given% ' . $txt['sp_topStatsMember_Likes_Given'],
				'enabled' => !empty($this->_modSettings['likes_enabled']),
				'error_msg' => $txt['sp_likes_is_disabled'],
			],
			'7' => [
				'name' => 'Likes Totals',
				'field' => 'mem.likes_received, mem.likes_given',
				'order' => 'mem.likes_received',
				'output_text' => $txt['sp_topStatsMember_Likes_Received'] . ':&nbsp;%likes_received% / ' . $txt['sp_topStatsMember_Likes_Given'] . ':&nbsp;%likes_given%',
				'enabled' => !empty($this->_modSettings['likes_enabled']),
				'error_msg' => $txt['sp_likes_is_disabled'],
			],
		];
	}

	/**
	 * Initializes a block for use.
	 *
	 * - Called from portal.subs as part of the sportal_load_blocks process
	 *
	 * @param array $parameters
	 * @param int $id
	 */
	public function setup($parameters, $id)
	{
		global $context, $scripturl;

		// Standard Variables
		$type = !empty($parameters['type']) ? $parameters['type'] : 0;
		$limit = !empty($parameters['limit']) ? (int) $parameters['limit'] : 5;
		$sort_asc = !empty($parameters['sort_asc']);

		// Time is in days, but we need seconds
		$last_active_limit = !empty($parameters['last_active_limit']) ? $parameters['last_active_limit'] * 86400 : 0;
		$this->data['enable_label'] = !empty($parameters['enable_label']);
		$this->data['list_label'] = !empty($parameters['list_label']) ? $parameters['list_label'] : '';

		// Set up the current block type
		$current_system = $this->sp_topStatsSystem[$type];

		// Possible to output?
		if (empty($current_system['enabled']))
		{
			if (!empty($current_system['error_msg']))
			{
				$this->setTemplate('template_sp_topStatsMember_error');
				$this->data['error_msg'] = $current_system['error_msg'];
			}

			return;
		}

		// Sort in reverse?
		$sort_asc = !empty($current_system['reverse']) ? !$sort_asc : $sort_asc;

		// Build the where statement
		$where = [];

		// If this is already cached, use it
		$cache_id = 'sp_cache_' . $id . '_topStatsMember';
		$cache_data = Cache::instance()->get($cache_id, 300);
		if (empty($this->_modSettings['sp_disableCache']) && $cache_data !== null)
		{
			if ($cache_data[0] == $type && $cache_data[1] == $limit && !empty($cache_data[2]) == $sort_asc)
			{
				$where[] = 'mem.id_member IN (' . $cache_data[4] . ')';
			}
		}

		// Last active remove
		if (!empty($last_active_limit))
		{
			$timeLimit = time() - $last_active_limit;
			$where[] = 'last_login > ' . $timeLimit;
		}

		if (!empty($current_system['where']))
		{
			$where[] = $current_system['where'];
		}

		if (!empty($where))
		{
			$where = 'WHERE (' . implode(')
				AND (', $where) . ')';
		}
		else
		{
			$where = '';
		}

		// Finally, make the query with the parameters we built
		$this->data['members'] = [];
		$count = 1;
		$cache_member_ids = [];
		$this->_db->query('', '
			SELECT
				mem.id_member, mem.real_name, mem.avatar, mem.email_address,
				a.id_attach, a.attachment_type, a.filename,
				{raw:field}
			FROM {db_prefix}members as mem
				LEFT JOIN {db_prefix}attachments AS a ON (a.id_member = mem.id_member)
			{raw:where}
			ORDER BY {raw:order} {raw:sort}
			LIMIT {int:limit}',
			[
				'limit' => isset($context['common_stats']['total_members']) && $context['common_stats']['total_members'] > 100 ? ($limit + 5) : $limit,
				'field' => $current_system['field'],
				'where' => $where,
				'order' => $current_system['order'],
				'sort' => ($sort_asc ? 'ASC' : 'DESC'),
			]
		)->fetch_callback((function($row) use (&$count, $limit, &$cache_member_ids, $current_system, $scripturl) {
			// Collect some to cache data
			$cache_member_ids[$row['id_member']] = $row['id_member'];
			if ($count++ > $limit) {
				return;
			}

			$this->color_ids[$row['id_member']] = $row['id_member'];

			// Set up the row
			$output = '';

			// Prepare some data of the row?
			if (!empty($current_system['output_function'])) {
				$current_system['output_function']($row);
			}

			if (!empty($current_system['output_text'])) {
				$output = $current_system['output_text'];
				foreach ($row as $item => $replacewith) {
					$output = str_replace('%' . $item . '%', $replacewith, $output);
				}
			}

			$this->data['members'][] = [
				'id' => $row['id_member'],
				'name' => $row['real_name'],
				'href' => $scripturl . '?action=profile;u=' . $row['id_member'],
				'link' => '<a style="font-size: 90%" href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>',
				'avatar' => determineAvatar([
					'avatar' => $row['avatar'],
					'filename' => $row['filename'],
					'id_attach' => $row['id_attach'],
					'email_address' => $row['email_address'],
					'attachment_type' => $row['attachment_type'],
				]),
				'output' => $output,
				'complete_row' => $row,
			];
		})->bindTo($this));

		// Update the cache, at least around 100 members are needed for a good working version
		if (empty($this->_modSettings['sp_disableCache']) && isset($context['common_stats']['total_members']) && $context['common_stats']['total_members'] > 0 && !empty($cache_member_ids) && count($cache_member_ids) > $limit && $cache_data === null)
		{
			$toCache = [$type, $limit, ($sort_asc ? 1 : 0), time(), implode(',', $cache_member_ids)];
			Cache::instance()->put($cache_id, $toCache, 300);

			// Clean up the old modSettings cache if it exists
			if (!empty($this->_modSettings[$cache_id]))
			{
				updateSettings([$cache_id => null]);
			}
		}

		// Color the id's
		$this->_colorids();

		// Set the template to use
		$this->setTemplate('template_sp_topStatsMember');
	}

	/**
	 * Provide the color profile id's
	 */
	private function _colorids()
	{
		global $color_profile;

		if (sp_loadColors($this->color_ids) !== false)
		{
			foreach ($this->data['members'] as $k => $p)
			{
				if (!empty($color_profile[$p['id']]['link']))
				{
					$this->data['members'][$k]['link'] = $color_profile[$p['id']]['link'];
				}
			}
		}
	}
}

/**
 * Error template for this block
 *
 * @param array $data
 */
function template_sp_topStatsMember_error($data)
{
	echo $data['error_msg'];
}

/**
 * Main template for this block
 *
 * @param array $data
 */
function template_sp_topStatsMember($data)
{
	global $scripturl, $txt;

	// No one found, let them know
	if (empty($data['members']))
	{
		echo '
			', $txt['error_sp_no_members_found'];

		return;
	}

	// Finally, output the block
	echo '
			<table class="sp_fullwidth">';

	if ($data['enable_label'])
	{
		echo '
				<tr>
					<td class="sp_top_poster centertext" colspan="2">
						<strong>', $data['list_label'], '</strong>
					</td>
				</tr>';
	}

	foreach ($data['members'] as $member)
	{
		echo '
				<tr>
					<td class="sp_top_poster centertext">', !empty($member['avatar']['href']) ? '
						<a href="' . $scripturl . '?action=profile;u=' . $member['id'] . '">
							<img src="' . $member['avatar']['href'] . '" alt="' . $member['name'] . '" />
						</a>' : '', '
					</td>
					<td>
						', $member['link'], ' : <span class="smalltext">', $member['output'], '</span>
					</td>
				</tr>';
	}

	echo '
			</table>';
}
