<?php

/**
 * @package SimplePortal
 *
 * @author SimplePortal Team
 * @copyright 2015-2026 SimplePortal Team
 * @license BSD 3-clause
 * @version 2.0.0
 */

use ElkArte\Errors\Log;
use ElkArte\Languages\Txt;
use ElkArte\User;

/**
 * Admin info block
 *
 * @param array $parameters not used in this block
 * @param int $id - not used in this block
 * @param bool $return_parameters if true returns the configuration options for the block
 */
class AdminInfoBlock extends SPAbstractBlock
{
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
		$this->data['admin'] = [];
		$this->data['moderation'] = [];

		// Only admins/moderators, no matter what the block permissions say
		if (User::$info->is_admin || allowedTo('moderate_forum'))
		{
			// Function to get the stats
			require_once(SUBSDIR . '/Moderation.subs.php');
			require_once(SUBSDIR . '/Members.subs.php');

			// Moderation totals
			$totals = loadModeratorMenuCounts();

			// The admin may want to know about errors and users waiting activation
			if (User::$info->is_admin)
			{
				$errorLog = new Log(database());
				$this->data['admin']['errors'] = $errorLog->numErrors();

				$activation_numbers = countInactiveMembers();
				$this->data['admin']['awaiting_activation'] = 0;
				foreach ($activation_numbers as $activation_type => $total_members)
				{
					if (in_array($activation_type, [0, 2]))
					{
						$this->data['admin']['awaiting_activation'] += $total_members;
					}
				}
			}

			// Do they have board approval?
			$approve_boards = !empty(User::$info->mod_cache['ap']) ? User::$info->mod_cache['ap'] : boardsAllowedTo('approve_posts');

			// Any posts / topics / attachments awaiting approval
			if ($this->_modSettings['postmod_active'] && !empty($approve_boards))
			{
				$this->data['moderation']['topics'] = $totals['topics'];
				$this->data['moderation']['posts'] = $totals['posts'];
				$this->data['moderation']['attachments'] = $totals['attachments'];
			}

			// Open moderation reports
			if (!empty(User::$info->mod_cache) && User::$info->mod_cache['bq'] !== '0=1')
			{
				$this->data['moderation']['reports'] = $totals['reports'];
			}

			// Failed emails
			if (!empty($this->_modSettings['maillist_enabled']) && allowedTo('approve_emails'))
			{
				$this->data['moderation']['emailmod'] = $totals['emailmod'];
			}

			// Pending group requests
			if (!empty(User::$info->mod_cache) && User::$info->mod_cache['gq'] !== '0=1')
			{
				$this->data['moderation']['groupreq'] = $totals['groupreq'];
			}

			// Members waiting approval (3,4,5 codes)
			if (allowedTo('moderate_forum') && ((!empty($this->_modSettings['registration_method']) && $this->_modSettings['registration_method'] == 2) || !empty($this->_modSettings['approveAccountDeletion'])))
			{
				$this->data['moderation']['memberreq'] = $totals['memberreq'];
			}
		}

		$this->setTemplate('template_sp_admininfo');
	}

	/**
	 * Permissions check fo access to this one
	 */
	public static function permissionsRequired()
	{
		return ['admin_forum'];
	}
}

/**
 * Main template for this block
 *
 * @param array $data
 */
function template_sp_admininfo($data)
{
	global $txt;

	Txt::load('ModerationCenter');

	echo '
		<div>
			<ul class="sp_list">';

	foreach ($data['admin'] as $area => $count)
	{
		echo '
				<li ', sp_embed_class('dot'), '>
					', $txt['sp_admincount_' . $area], ': ', $count, '
				</li>';
	}

	echo
			'</ul>
			<strong>', $txt['sp_admincount_approval'], '</strong>
			<ul class="sp_list">';

	foreach ($data['moderation'] as $area => $count)
	{
		echo '
				<li ', sp_embed_class('dot'), '>
					', $txt['sp_admincount_' . $area], ': ', $count, '
				</li>';
	}

	echo '
			</ul>
		</div>';
}
