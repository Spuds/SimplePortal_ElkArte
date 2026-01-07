<?php

/**
 * @package SimplePortal
 *
 * @author SimplePortal Team
 * @copyright 2015-2026 SimplePortal Team
 * @license BSD 3-clause
 * @version 2.0.0
 */

use ElkArte\Database\QueryInterface;

/**
 * Staff Block, show the list of forum staff members
 *
 * @param array $parameters
 *   'lmod' => set to include local moderators as well
 * @param int $id - not used in this block
 * @param bool $return_parameters if true returns the configuration options for the block
 */
class StaffBlock extends SPAbstractBlock
{
	protected $color_ids = [];

	/**
	 * Constructor, used to define block parameters
	 *
	 * @param QueryInterface|null $db
	 */
	public function __construct($db = null)
	{
		$this->block_parameters = [
			'lmod' => 'check',
		];

		parent::__construct($db);
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
		global $scripturl;

		require_once(SUBSDIR . '/Members.subs.php');

		// Including local board moderators
		$local_mods = [];
		if (empty($parameters['lmod']))
		{
			$this->_db->query('', '
   				SELECT
        			id_member
    			FROM {db_prefix}moderators',
				[]
			)->fetch_callback(
				function ($row) use (&$local_mods) {
					$local_mods[(int) $row['id_member']] = (int) $row['id_member'];
				}
			);

			if (count($local_mods) > 10)
			{
				$local_mods = [];
			}
		}

		// Admins and global moderator list
		$global_mods = membersAllowedTo('moderate_board', 0);
		$admins = membersAllowedTo('admin_forum');

		// You only get one listing, highest authority
		$all_staff = array_merge($local_mods, $global_mods, $admins);
		$all_staff = array_unique($all_staff);

		$this->data['staff_list'] = [];
		$this->_db->query('', '
			SELECT
				m.id_member, m.real_name, m.avatar, m.email_address,
				mg.group_name,
				a.id_attach, a.attachment_type, a.filename
			FROM {db_prefix}members AS m
				LEFT JOIN {db_prefix}attachments AS a ON (a.id_member = m.id_member)
				LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = CASE WHEN m.id_group = {int:reg_group_id} THEN m.id_post_group ELSE m.id_group END)
			WHERE m.id_member IN ({array_int:staff_list})',
			[
				'staff_list' => $all_staff,
				'reg_group_id' => 0,
			]
		)->fetch_callback(
			function ($row) use (&$admins, &$global_mods, $scripturl) {
				$this->color_ids[$row['id_member']] = $row['id_member'];

				if (in_array((int) $row['id_member'], $admins))
				{
					$row['type'] = 1;
				}
				elseif (in_array((int) $row['id_member'], $global_mods))
				{
					$row['type'] = 2;
				}
				else
				{
					$row['type'] = 3;
				}

				$this->data['staff_list'][$row['type'] . '-' . $row['id_member']] = [
					'id' => $row['id_member'],
					'name' => $row['real_name'],
					'link' => '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>',
					'group' => $row['group_name'],
					'type' => $row['type'],
					'avatar' => determineAvatar([
						'avatar' => $row['avatar'],
						'filename' => $row['filename'],
						'id_attach' => $row['id_attach'],
						'email_address' => $row['email_address'],
						'attachment_type' => $row['attachment_type'],
					]),
				];
			}
		);

		// Get this in an order or importance
		ksort($this->data['staff_list']);
		$this->data['staff_count'] = count($this->data['staff_list']);
		$this->data['icons'] = [1 => 'admin', 'gmod', 'lmod'];

		// Color ID's
		$this->_color_ids();

		// How we will display the data
		$this->setTemplate('template_sp_staff');
	}

	/**
	 * Provide the color profile id's
	 */
	private function _color_ids()
	{
		global $color_profile;

		if (sp_loadColors($this->color_ids) !== false)
		{
			foreach ($this->data['staff_list'] as $k => $p)
			{
				if (!empty($color_profile[$p['id']]['link']))
				{
					$this->data['staff_list'][$k]['link'] = $color_profile[$p['id']]['link'];
				}
			}
		}
	}
}

/**
 * Main template for this block
 *
 * @param array $data
 */
function template_sp_staff($data)
{
	global $scripturl;

	echo '
		<table class="sp_fullwidth">';

	$count = 0;
	foreach ($data['staff_list'] as $staff)
	{
		echo '
			<tr>
				<td class="sp_staff centertext">', !empty($staff['avatar']['href']) ? '
					<a href="' . $scripturl . '?action=profile;u=' . $staff['id'] . '">
						<img src="' . $staff['avatar']['href'] . '" alt="' . $staff['name'] . '" />
					</a>' : '', '
				</td>
				<td ', sp_embed_class($data['icons'][$staff['type']], '', 'sp_staff_info' . $data['staff_count'] != ++$count ? ' sp_staff_divider' : ''), '>',
		$staff['link'], '<br />', $staff['group'], '
				</td>
			</tr>';
	}

	echo '
		</table>';
}
