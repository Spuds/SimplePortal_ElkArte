<?php

/**
 * @package SimplePortal ElkArte
 *
 * @author SimplePortal Team
 * @copyright 2015-2026 SimplePortal Team
 * @license BSD 3-clause
 * @version 2.0.0
 */

namespace Addons\SimplePortal\AdminController;

use ElkArte\AbstractController;
use ElkArte\Action;
use ElkArte\Exceptions\Exception;
use ElkArte\Helper\Util;
use ElkArte\Languages\Txt;

/**
 * SimplePortal Shoutbox Administration controller class.
 *
 * - This class handles the administration of the shoutbox
 */
class ManagePortalShoutbox extends AbstractController
{
	/**
	 * This method is executed before any action handler.
	 * Loads common things for all methods
	 */
	public function pre_dispatch()
	{
		// We'll need the utility functions from here.
		require_once(ADDONSDIR . '/SimplePortal/subs/PortalAdmin.subs.php');
		require_once(ADDONSDIR . '/SimplePortal/subs/Portal.subs.php');
		require_once(ADDONSDIR . '/SimplePortal/subs/PortalShoutbox.subs.php');
	}

	/**
	 * Main dispatcher.
	 *
	 * - This function checks permissions and passes control through.
	 */
	public function action_index()
	{
		global $context, $txt;

		// You must have admin or shoutbox privileges to be here
		if (!allowedTo('sp_admin'))
		{
			isAllowedTo('sp_manage_shoutbox');
		}

		theme()->getTemplates()->load('PortalAdminShoutbox');

		// The actions allowed in the shoutbox
		$subActions = [
			'list' => [$this, 'action_list'],
			'add' => [$this, 'action_edit'],
			'edit' => [$this, 'action_edit'],
			'prune' => [$this, 'action_prune'],
			'delete' => [$this, 'action_delete'],
			'status' => [$this, 'action_status'],
			'blockredirect' => [$this, 'action_block_redirect'],
		];

		// Start up the controller, provide a hook since we can
		$action = new Action('portal_shoutbox');

		$context[$context['admin_menu_name']]['tab_data'] = [
			'title' => $txt['sp_admin_shoutbox_title'],
			'help' => 'sp_ShoutboxArea',
			'description' => $txt['sp_admin_shoutbox_desc'],
			'tabs' => [
				'list' => [],
				'add' => [],
			],
		];

		// Default the action to list if none or no valid option is given
		$subAction = $action->initialize($subActions, 'list');
		$context['sub_action'] = $subAction;

		// Shout it on out
		$action->dispatch($subAction);
	}

	/**
	 * List all the shouts in the system
	 *
	 * Shout, shout, let it all out, these are the things I can do without
	 * Come on, I'm talking to you, come on
	 */
	public function action_list()
	{
		global $context, $scripturl, $txt, $modSettings;

		// Build the listoption array to display the categories
		$listOptions = [
			'id' => 'portal_shout',
			'title' => $txt['sp_admin_shoutbox_list'],
			'items_per_page' => $modSettings['defaultMaxMessages'],
			'no_items_label' => $txt['error_sp_no_shoutbox'],
			'base_href' => $scripturl . '?action=admin;area=portalshoutbox;sa=list;',
			'default_sort_col' => 'name',
			'get_items' => [
				'function' => [$this, 'list_spLoadShoutbox'],
			],
			'get_count' => [
				'function' => [$this, 'list_spCountShoutbox'],
			],
			'columns' => [
				'name' => [
					'header' => [
						'value' => $txt['sp_admin_shoutbox_col_name'],
					],
					'data' => [
						'db' => 'name',
					],
					'sort' => [
						'default' => 'name',
						'reverse' => 'name DESC',
					],
				],
				'shouts' => [
					'header' => [
						'value' => $txt['sp_admin_shoutbox_col_shouts'],
						'class' => 'centertext',
					],
					'data' => [
						'db' => 'shouts',
						'class' => 'centertext',
					],
					'sort' => [
						'default' => 'shouts',
						'reverse' => 'shouts DESC',
					],
				],
				'caching' => [
					'header' => [
						'value' => $txt['sp_admin_shoutbox_col_caching'],
						'class' => 'centertext',
					],
					'data' => [
						'db' => 'caching',
						'class' => 'centertext',
					],
					'sort' => [
						'default' => 'caching',
						'reverse' => 'caching DESC',
					],
				],
				'status' => [
					'header' => [
						'value' => $txt['sp_admin_shoutbox_col_status'],
						'class' => 'centertext',
					],
					'data' => [
						'db' => 'status_image',
						'class' => 'centertext',
					],
					'sort' => [
						'default' => 'status',
						'reverse' => 'status DESC',
					],
				],
				'action' => [
					'header' => [
						'value' => $txt['sp_admin_shoutbox_col_actions'],
						'class' => 'centertext',
					],
					'data' => [
						'sprintf' => [
							'format' => '
								<a href="?action=admin;area=portalshoutbox;sa=edit;shoutbox_id=%1$s;' . $context['session_var'] . '=' . $context['session_id'] . '" accesskey="m">' . sp_embed_image('modify') . '</a>&nbsp;
								<a href="?action=admin;area=portalshoutbox;sa=prune;shoutbox_id=%1$s;' . $context['session_var'] . '=' . $context['session_id'] . '" accesskey="p">' . sp_embed_image('bin') . '</a>&nbsp;
								<a href="?action=admin;area=portalshoutbox;sa=delete;shoutbox_id=%1$s;' . $context['session_var'] . '=' . $context['session_id'] . '" onclick="return confirm(' . JavaScriptEscape($txt['sp_admin_shoutbox_delete_confirm']) . ') && submitThisOnce(this);" accesskey="d">' . sp_embed_image('trash') . '</a>',
							'params' => [
								'id' => true,
							],
						],
						'class' => 'centertext nowrap',
					],
				],
				'check' => [
					'header' => [
						'value' => '<input type="checkbox" onclick="invertAll(this, this.form);" class="input_check" />',
						'class' => 'centertext',
					],
					'data' => [
						'function' => function ($row)
						{
							return '<input type="checkbox" name="remove[]" value="' . $row['id'] . '" class="input_check" />';
						},
						'class' => 'centertext',
					],
				],
			],
			'form' => [
				'href' => $scripturl . '?action=admin;area=portalshoutbox;sa=remove',
				'include_sort' => true,
				'include_start' => true,
				'hidden_fields' => [
					$context['session_var'] => $context['session_id'],
				],
			],
			'additional_rows' => [
				[
					'position' => 'below_table_data',
					'value' => '<input type="submit" name="remove_shoutbox" value="' . $txt['sp_admin_shoutbox_remove'] . '" class="right_submit" />',
				],
			],
		];

		// Set the context values
		$context['page_title'] = $txt['sp_admin_shoutbox_title'];
		$context['sub_template'] = 'show_list';
		$context['default_list'] = 'portal_shout';

		// Create the list.
		createList($listOptions);
	}

	/**
	 * Callback for createList(),
	 * Returns the number of articles in the system
	 */
	public function list_spCountShoutbox()
	{
		return sp_count_shoutbox();
	}

	/**
	 * Callback for createList()
	 * Returns an array of articles
	 *
	 * @param int $start
	 * @param int $items_per_page
	 * @param string $sort
	 *
	 * @return array
	 */
	public function list_spLoadShoutbox($start, $items_per_page, $sort)
	{
		return sp_load_shoutbox($start, $items_per_page, $sort);
	}

	/**
	 * Edit an existing shoutbox or add a new one
	 */
	public function action_edit()
	{
		global $txt, $context, $modSettings, $editortxt;

		$context['SPortal']['is_new'] = empty($_REQUEST['shoutbox_id']);

		if (!empty($_POST['submit']))
		{
			checkSession();

			if (!isset($_POST['name']) || Util::htmltrim(Util::htmlspecialchars($_POST['name'], ENT_QUOTES)) === '')
			{
				throw new Exception('sp_error_shoutbox_name_empty', false);
			}

			// No two the same
			$has_duplicate = sp_check_duplicate_shoutbox($_POST['name'], $_POST['shoutbox_id']);
			if (!empty($has_duplicate))
			{
				throw new Exception('sp_error_shoutbox_name_duplicate', false);
			}

			if (isset($_POST['moderator_groups']) && is_array($_POST['moderator_groups']) && count($_POST['moderator_groups']) > 0)
			{
				foreach ($_POST['moderator_groups'] as $id => $group)
				{
					$_POST['moderator_groups'][$id] = (int) $group;
				}

				$_POST['moderator_groups'] = implode(',', $_POST['moderator_groups']);
			}
			else
			{
				$_POST['moderator_groups'] = '';
			}

			if (!empty($_POST['allowed_bbc']) && is_array($_POST['allowed_bbc']))
			{
				foreach ($_POST['allowed_bbc'] as $id => $tag)
				{
					$_POST['allowed_bbc'][$id] = Util::htmlspecialchars($tag, ENT_QUOTES);
				}

				$_POST['allowed_bbc'] = implode(',', $_POST['allowed_bbc']);
			}
			else
			{
				$_POST['allowed_bbc'] = '';
			}

			$shoutbox_info = [
				'id' => (int) $_POST['shoutbox_id'],
				'name' => Util::htmlspecialchars($_POST['name'], ENT_QUOTES),
				'permissions' => (int) $_POST['permissions'],
				'moderator_groups' => $_POST['moderator_groups'],
				'warning' => Util::htmlspecialchars($_POST['warning'], ENT_QUOTES),
				'allowed_bbc' => $_POST['allowed_bbc'],
				'height' => (int) $_POST['height'],
				'num_show' => (int) $_POST['num_show'],
				'num_max' => (int) $_POST['num_max'],
				'reverse' => !empty($_POST['reverse']) ? 1 : 0,
				'caching' => !empty($_POST['caching']) ? 1 : 0,
				'refresh' => (int) $_POST['refresh'],
				'status' => !empty($_POST['status']) ? 1 : 0,
			];

			// Update existing or add a new shoutbox
			$shoutbox_info['id'] = sp_edit_shoutbox($shoutbox_info, $context['SPortal']['is_new']);

			sportal_update_shoutbox($shoutbox_info['id']);

			if ($context['SPortal']['is_new'] && (allowedTo(['sp_admin', 'sp_manage_blocks'])))
			{
				redirectexit('action=admin;area=portalshoutbox;sa=blockredirect;shoutbox=' . $shoutbox_info['id']);
			}
			else
			{
				redirectexit('action=admin;area=portalshoutbox');
			}
		}

		if ($context['SPortal']['is_new'])
		{
			$context['SPortal']['shoutbox'] = [
				'id' => 0,
				'name' => $txt['sp_shoutbox_default_name'],
				'permissions' => 3,
				'moderator_groups' => [],
				'warning' => '',
				'allowed_bbc' => ['b', 'i', 'u', 's', 'url', 'code', 'quote', 'me'],
				'height' => 200,
				'num_show' => 20,
				'num_max' => 1000,
				'reverse' => 0,
				'caching' => 1,
				'refresh' => 0,
				'status' => 1,
			];
		}
		else
		{
			$_REQUEST['shoutbox_id'] = (int) $_REQUEST['shoutbox_id'];
			$context['SPortal']['shoutbox'] = sportal_get_shoutbox($_REQUEST['shoutbox_id']);
		}

		Txt::load('Editor');

		$context['SPortal']['shoutbox']['permission_profiles'] = sportal_get_profiles(null, 1, 'name');
		sp_loadMemberGroups($context['SPortal']['shoutbox']['moderator_groups'], 'moderator', 'moderator_groups');

		if (empty($context['SPortal']['shoutbox']['permission_profiles']))
		{
			throw new Exception('error_sp_no_permission_profiles', false);
		}

		// We only allow some BBC in the shoutbox
		$context['allowed_bbc'] = [
			'b' => $editortxt['Bold'],
			'i' => $editortxt['Italic'],
			'u' => $editortxt['Underline'],
			's' => $editortxt['Strikethrough'],
			'pre' => $editortxt['Preformatted Text'],
			'img' => $editortxt['Insert an image'],
			'url' => $editortxt['Insert a link'],
			'email' => $editortxt['Insert an email'],
			'sup' => $editortxt['Superscript'],
			'sub' => $editortxt['Subscript'],
			'tt' => $editortxt['Teletype'],
			'code' => $editortxt['Code'],
			'quote' => $editortxt['Insert a Quote'],
			'size' => $editortxt['Font Size'],
			'font' => $editortxt['Font Name'],
			'color' => $editortxt['Font Color'],
			'me' => 'me',
		];

		// Remove the ones the admin does not allow
		$disabled_tags = [];
		if (!empty($modSettings['disabledBBC']))
		{
			$disabled_tags = explode(',', $modSettings['disabledBBC']);
		}
		if (empty($modSettings['enableEmbeddedFlash']))
		{
			$disabled_tags[] = 'flash';
		}

		foreach ($disabled_tags as $tag)
		{
			if ($tag === 'list')
			{
				$context['disabled_tags']['orderlist'] = true;
			}

			$context['disabled_tags'][trim($tag)] = true;
		}

		$context['page_title'] = $context['SPortal']['is_new']
			? $txt['sp_admin_shoutbox_add']
			: $txt['sp_admin_shoutbox_edit'];
		$context['sub_template'] = 'shoutbox_edit';
	}

	/**
	 * Who does not like prunes .. or maybe cut down the shout list
	 */
	public function action_prune()
	{
		global $context, $txt;

		$shoutbox_id = empty($_REQUEST['shoutbox_id']) ? 0 : (int) $_REQUEST['shoutbox_id'];
		$context['shoutbox'] = sportal_get_shoutbox($shoutbox_id);

		if (empty($context['shoutbox']))
		{
			throw new Exception('error_sp_shoutbox_not_exist', false);
		}

		// Time to remove some chitta-chatta
		if (!empty($_POST['submit']))
		{
			checkSession();

			if (!empty($_POST['type']))
			{
				$where = ['id_shoutbox = {int:shoutbox_id}'];
				$parameters = ['shoutbox_id' => $shoutbox_id];

				// Prune by days
				if ($_POST['type'] === 'days' && !empty($_POST['days']))
				{
					$where[] = 'log_time < {int:time_limit}';
					$parameters['time_limit'] = time() - $_POST['days'] * 86400;
				}
				// Or maybe by member
				elseif ($_POST['type'] === 'member' && !empty($_POST['member']))
				{
					$member_id = sp_shoutbox_prune_member($_POST['member']);
					if (!empty($member_id))
					{
						$where[] = 'id_member = {int:member_id}';
						$parameters['member_id'] = $member_id;
					}
				}

				// Execute the selective prune or clear the entire box
				if ($_POST['type'] === 'all' || count($where) > 1)
				{
					sp_prune_shoutbox($shoutbox_id, $where, $parameters, $_POST['type'] === 'all');
					sportal_update_shoutbox($shoutbox_id);
				}
			}

			redirectexit('action=admin;area=portalshoutbox');
		}

		loadJavascriptFile('suggest.js');
		$context['page_title'] = $txt['sp_admin_shoutbox_prune'];
		$context['sub_template'] = 'shoutbox_prune';
	}

	/**
	 * Remove a shout from the system
	 */
	public function action_delete()
	{
		$shoutbox_ids = [];

		// Get the page id's to remove
		if (!empty($_POST['remove_shoutbox']) && !empty($_POST['remove']) && is_array($_POST['remove']))
		{
			checkSession();

			foreach ($_POST['remove'] as $index => $page_id)
			{
				$shoutbox_ids[(int) $index] = (int) $page_id;
			}
		}
		elseif (!empty($_REQUEST['shoutbox_id']))
		{
			checkSession('get');
			$shoutbox_ids[] = (int) $_REQUEST['shoutbox_id'];
		}

		// If we have some to remove ....
		if (!empty($shoutbox_ids))
		{
			sp_delete_shoutbox($shoutbox_ids);
		}

		redirectexit('action=admin;area=portalshoutbox');
	}

	/**
	 * Updates the system status of the shoutout, toggle on/off
	 */
	public function action_status()
	{
		checkSession('get');

		$shoutbox_id = !empty($_REQUEST['shoutbox_id']) ? (int) $_REQUEST['shoutbox_id'] : 0;
		sp_changeState('shout', $shoutbox_id);
		redirectexit('action=admin;area=portalshoutbox');
	}

	/**
	 * Used to gain access to the add a shoutbox block
	 */
	public function action_block_redirect()
	{
		global $context, $scripturl, $txt;

		if (!allowedTo('sp_admin'))
		{
			isAllowedTo('sp_manage_blocks');
		}

		$context['page_title'] = $txt['sp_admin_shoutbox_add'];
		$context['redirect_message'] = sprintf($txt['sp_admin_shoutbox_block_redirect_message'], $scripturl . '?action=admin;area=portalblocks;sa=add;selected_type=Shoutbox;parameters[]=shoutbox;shoutbox=' . $_GET['shoutbox'], $scripturl . '?action=admin;area=portalshoutbox');
		$context['sub_template'] = 'shoutbox_block_redirect';
	}
}
