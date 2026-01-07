<?php

/**
 * @package SimplePortal ElkArte
 *
 * @author SimplePortal Team
 * @copyright 2015-2026 SimplePortal Team
 * @license BSD 3-clause
 * @version 2.0.0
 */

namespace Addons\SimplePortal\Controller;

use BBC\ParserWrapper;
use BBC\PreparseCode;
use ElkArte\AbstractController;
use ElkArte\Exceptions\Exception;
use ElkArte\Helper\Util;
use ElkArte\User;

/**
 * Shoutbox controller.
 *
 * - This class handles requests for Shoutbox Functionality
 */
class PortalShoutbox extends AbstractController
{
	/**
	 * Need the template for the shoutbox
	 */
	public function pre_dispatch()
	{
		theme()->getTemplates()->load('PortalShoutbox');
		require_once(ADDONSDIR . '/SimplePortal/subs/PortalShoutbox.subs.php');
	}

	/**
	 * Default method
	 */
	public function action_index()
	{
		// We really only have one choice :P
		$this->action_sportal_shoutbox();
	}

	/**
	 * Override default method, just say no for xml
	 */
	public function trackStats($action = '')
	{
		if ($this->getApi() !== false)
		{
			return false;
		}

		return parent::trackStats($action);
	}

	/**
	 * Handles the functionality of a shoutbox within the portal. This method
	 * includes operations such as fetching the shoutbox data, adding new shouts,
	 * deleting shouts, responding to AJAX requests, and displaying shouts.
	 * It ensures the appropriate permissions and validations are enforced
	 * for proper shoutbox interaction.
	 *
	 * @return void Returns no value but processes shoutbox operations, prepares outputs, or
	 * triggers exceptions in case of errors or invalid accesses.
	 * @throws Exception
	 */
	public function action_sportal_shoutbox()
	{
		global $context, $scripturl;

		// ID of the shoutbox we are working on and timestamp
		$shoutbox_id = !empty($_REQUEST['shoutbox_id']) ? (int) $_REQUEST['shoutbox_id'] : 0;
		$request_time = !empty($_REQUEST['time']) ? (int) $_REQUEST['time'] : 0;

		// We need to know which shoutbox this is for/from
		$context['SPortal']['shoutbox'] = sportal_get_shoutbox($shoutbox_id, true, true);

		// Shouting, but no one is there to here you
		if (empty($context['SPortal']['shoutbox']))
		{
			if ($this->getApi() === 'xml')
			{
				obExit(false, false);
			}
			else
			{
				throw new Exception('error_sp_shoutbox_not_exist', false);
			}
		}

		// Any warning title for the shoutbox, like Not For Support ;P
		$parser = ParserWrapper::instance();
		$context['SPortal']['shoutbox']['warning'] = $parser->parseMessage($context['SPortal']['shoutbox']['warning'], true);

		$can_moderate = allowedTo('sp_admin') || allowedTo('sp_manage_shoutbox');
		if (!$can_moderate && !empty($context['SPortal']['shoutbox']['moderator_groups']))
		{
			$can_moderate = count(array_intersect(User::$info['groups'], $context['SPortal']['shoutbox']['moderator_groups'])) > 0;
		}

		// Adding a shout
		if (!empty($_REQUEST['shout']))
		{
			// Pretty basic
			is_not_guest();
			checkSession('request');

			// If you are not flooding the system, add the shout to the box
			if (!($flood = sp_prevent_flood('spsbp', false)))
			{
				require_once(SUBSDIR . '/Post.subs.php');

				$_REQUEST['shout'] = Util::htmlspecialchars(trim($_REQUEST['shout']));
				$preparse = PreparseCode::instance(User::$info['name']);
				$preparse->preparsecode($_REQUEST['shout'], false);
				sportal_create_shout($context['SPortal']['shoutbox'], $_REQUEST['shout']);
			}
			else
			{
				$context['SPortal']['shoutbox']['warning'] = $flood;
			}
		}

		// Removing a shout, regret saying that do you :P
		if (!empty($_REQUEST['delete']))
		{
			checkSession('request');

			if (!$can_moderate)
			{
				throw new Exception('error_sp_cannot_shoutbox_moderate', false);
			}

			$delete = (int) $_REQUEST['delete'];

			if (!empty($delete))
			{
				sportal_delete_shout($shoutbox_id, $delete);
			}
		}

		// Responding to an ajax request
		if ($this->getApi() === 'xml')
		{
			$shout_parameters = [
				'limit' => $context['SPortal']['shoutbox']['num_show'],
				'bbc' => $context['SPortal']['shoutbox']['allowed_bbc'],
				'reverse' => $context['SPortal']['shoutbox']['reverse'],
				'cache' => $context['SPortal']['shoutbox']['caching'],
				'can_moderate' => $can_moderate,
			];

			// Get all the shouts for this box
			$context['SPortal']['shouts'] = sportal_get_shouts($shoutbox_id, $shout_parameters);

			// Return a clean xml response
			$template_layers = theme()->getLayers();
			$template_layers->removeAll();
			$context['sub_template'] = 'shoutbox_xml';
			$context['SPortal']['updated'] = empty($context['SPortal']['shoutbox']['last_update']) || $context['SPortal']['shoutbox']['last_update'] > $request_time;

			return;
		}

		// Show all the shouts in this box
		$total_shouts = sportal_get_shoutbox_count($shoutbox_id);

		$context['per_page'] = $context['SPortal']['shoutbox']['num_show'];
		$context['start'] = !empty($_REQUEST['start']) ? (int) $_REQUEST['start'] : 0;
		$context['page_index'] = constructPageIndex($scripturl . '?action=shoutbox;shoutbox_id=' . $shoutbox_id, $context['start'], $total_shouts, $context['per_page']);

		$shout_parameters = [
			'start' => $context['start'],
			'limit' => $context['per_page'],
			'bbc' => $context['SPortal']['shoutbox']['allowed_bbc'],
			'cache' => $context['SPortal']['shoutbox']['caching'],
			'can_moderate' => $can_moderate,
		];

		$context['SPortal']['shouts_history'] = sportal_get_shouts($shoutbox_id, $shout_parameters);
		$context['SPortal']['shoutbox_id'] = $shoutbox_id;
		$context['sub_template'] = 'shoutbox_all';
		$context['page_title'] = $context['SPortal']['shoutbox']['name'];
	}
}
