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
use ElkArte\Helper\DataValidator;
use ElkArte\Helper\Util;

/**
 * SimplePortal Menus Administration controller class.
 * This class handles the adding/editing of menus
 */
class ManagePortalMenus extends AbstractController
{
	/**
	 * The starting point for the controller, called before all others
	 */
	public function action_index()
	{
		global $context, $txt;

		// Admin or at least manage menu permissions
		if (!allowedTo('sp_admin'))
		{
			isAllowedTo('sp_manage_menus');
		}

		// Going to need these
		require_once(ADDONSDIR . '/SimplePortal/subs/PortalAdmin.subs.php');
		theme()->getTemplates()->load('PortalAdminMenus');

		$subActions = [
			'listmainitem' => [$this, 'action_main_item_list'],
			'addmainitem' => [$this, 'action_main_item_edit'],
			'editmainitem' => [$this, 'action_main_item_edit'],
			'deletemainitem' => [$this, 'action_main_item_delete'],
			'statuscustomitem' => [$this, 'action_custom_item_status'],

			'listcustommenu' => [$this, 'action_custom_menu_list'],
			'addcustommenu' => [$this, 'action_custom_menu_edit'],
			'editcustommenu' => [$this, 'action_custom_menu_edit'],
			'deletecustommenu' => [$this, 'action_custom_menu_delete'],

			'listcustomitem' => [$this, 'action_custom_item_list'],
			'addcustomitem' => [$this, 'action_custom_item_edit'],
			'editcustomitem' => [$this, 'action_custom_item_edit'],
			'deletecustomitem' => [$this, 'action_custom_item_delete'],
		];

		// Start up the controller, provide a hook since we can
		$action = new Action('portal_menus');

		// Set up the tabs
		$tabs = [
			'listmainitem' => [],
			'addmainitem' => [],
			'listcustommenu' => [],
			'addcustommenu' => [],
			'addcustomitem' => [],
		];

		// Default to list the main menu items
		$subAction = $action->initialize($subActions, 'listmainitem');
		$context['sub_action'] = $subAction;

		if ($context['sub_action'] === 'listcustomitem' && !empty($_REQUEST['menu_id']))
		{
			$tabs['addcustomitem'] = [
				'add_params' => ';menu_id=' . $_REQUEST['menu_id']
			];
		}

		$context[$context['admin_menu_name']]['object']->prepareTabData([
			'title' => $txt['sp_admin_menus_title'],
			'help' => 'sp_MenusArea',
			'description' => $txt['sp_admin_menus_desc'],
			'tabs' => $tabs,
		]);

		// Call the right function for this sub-action.
		$action->dispatch($subAction);
	}

	/**
	 * List the items in the main forum menu
	 */
	public function action_main_item_list()
	{
		$_REQUEST['menu_id'] = 0;
		$this->action_custom_item_list();
	}

	/**
	 * Edit or add a main forum menu item
	 */
	public function action_main_item_edit()
	{
		$_REQUEST['menu_id'] = 0;
		$this->action_custom_item_edit();
	}

	/**
	 * Delete a main forum menu item
	 */
	public function action_main_item_delete()
	{
		$_REQUEST['menu_id'] = 0;
		$this->action_custom_item_delete();
	}

	/**
	 * List the custom menus in the system
	 */
	public function action_custom_menu_list()
	{
		global $context, $scripturl, $txt, $modSettings;

		// Want to remove some menus
		if (!empty($_POST['remove_menus']) && !empty($_POST['remove']) && is_array($_POST['remove']))
		{
			checkSession();

			$remove_ids = [];
			foreach ($_POST['remove'] as $index => $menu_id)
			{
				$remove_ids[(int) $index] = (int) $menu_id;
			}

			sp_remove_menu($remove_ids);
		}

		// Build the list option array to display the menus
		$listOptions = [
			'id' => 'portal_menus',
			'title' => $txt['sp_admin_menus_custom_menu_list'],
			'items_per_page' => $modSettings['defaultMaxMessages'],
			'no_items_label' => $txt['sp_error_no_custom_menus'],
			'base_href' => $scripturl . '?action=admin;area=portalmenus;sa=listcustommenu;',
			'default_sort_col' => 'name',
			'get_items' => [
				'function' => [$this, 'list_spLoadMenus'],
			],
			'get_count' => [
				'function' => [$this, 'list_spCountMenus'],
			],
			'columns' => [
				'name' => [
					'header' => [
						'value' => $txt['sp_admin_menus_col_name'],
					],
					'data' => [
						'db' => 'name',
					],
					'sort' => [
						'default' => 'cm.name ASC',
						'reverse' => 'cm.name DESC',
					],
				],
				'items' => [
					'header' => [
						'value' => $txt['sp_admin_menus_col_items'],
					],
					'data' => [
						'db' => 'items',
					],
					'sort' => [
						'default' => 'items',
						'reverse' => 'items DESC',
					],
				],
				'action' => [
					'header' => [
						'value' => $txt['sp_admin_menus_col_actions'],
						'class' => ' grid8 centertext',
					],
					'data' => [
						'sprintf' => [
							'format' => '
								<a href="' . $scripturl . '?action=admin;area=portalmenus;sa=addcustomitem;menu_id=%1$s;' . $context['session_var'] . '=' . $context['session_id'] . '">' . sp_embed_image('add') . '</a>
								<a href="' . $scripturl . '?action=admin;area=portalmenus;sa=listcustomitem;menu_id=%1$s;' . $context['session_var'] . '=' . $context['session_id'] . '">' . sp_embed_image('items') . '</a>
						 		<br />
						 		<a href="' . $scripturl . '?action=admin;area=portalmenus;sa=editcustommenu;menu_id=%1$s;' . $context['session_var'] . '=' . $context['session_id'] . '">' . sp_embed_image('modify') . '</a>
								<a href="' . $scripturl . '?action=admin;area=portalmenus;sa=deletecustommenu;menu_id=%1$s;' . $context['session_var'] . '=' . $context['session_id'] . '" onclick="return confirm(\'' . $txt['sp_admin_menus_menu_delete_confirm'] . '\');">' . sp_embed_image('delete') . '</a>',
							'params' => [
								'id' => true,
							]
						],
						'class' => 'centertext nowrap',
					],
				],
			],
			'form' => [
				'href' => $scripturl . '?action=admin;area=portalmenus;sa=listcustommenu',
				'include_sort' => true,
				'include_start' => true,
				'hidden_fields' => [
					$context['session_var'] => $context['session_id'],
				],
			],
		];

		// Set the context values
		$context['page_title'] = $txt['sp_admin_menus_custom_menu_list'];
		$context['sub_template'] = 'show_list';
		$context['default_list'] = 'portal_menus';

		// Create the list.
		createList($listOptions);
	}

	/**
	 * Returns the number of menus in the system
	 * Callback for createList()
	 */
	public function list_spCountMenus()
	{
		return sp_menu_count();
	}

	/**
	 * Returns an array of menus
	 * Callback for createList()
	 *
	 * @param int $start
	 * @param int $items_per_page
	 * @param string $sort
	 *
	 * @return array
	 */
	public function list_spLoadMenus($start, $items_per_page, $sort)
	{
		return sp_custom_menu_items($start, $items_per_page, $sort);
	}

	/**
	 * Create or edit a menu
	 */
	public function action_custom_menu_edit()
	{
		global $context, $txt;

		// New menu or existing menu
		$is_new = empty($_REQUEST['menu_id']);

		// Saving the edit?
		if (!empty($_POST['submit']))
		{
			checkSession();

			if (!isset($_POST['name']) || Util::htmltrim(Util::htmlspecialchars($_POST['name'], ENT_QUOTES)) === '')
			{
				throw new Exception('sp_error_menu_name_empty', false);
			}

			$menu_info = [
				'id' => (int) $_POST['menu_id'],
				'name' => Util::htmlspecialchars($_POST['name'], ENT_QUOTES),
			];

			sp_add_menu($menu_info, $is_new);

			redirectexit('action=admin;area=portalmenus;sa=listcustommenu');
		}

		// Not saving so set up for the template display
		if ($is_new)
		{
			$context['menu'] = [
				'id' => 0,
				'name' => $txt['sp_menus_default_custom_menu_name'],
			];
		}
		else
		{
			$menu_id = (int) $_REQUEST['menu_id'];
			$context['menu'] = sportal_get_custom_menus($menu_id);
		}

		// Final template bits
		$context['page_title'] = $is_new ? $txt['sp_admin_menus_custom_menu_add'] : $txt['sp_admin_menus_custom_menu_edit'];
		$context['sub_template'] = 'menus_custom_menu_edit';
	}

	/**
	 * Delete a custom menu and its items
	 */
	public function action_custom_menu_delete()
	{
		checkSession('get');

		$menu_id = !empty($_REQUEST['menu_id']) ? (int) $_REQUEST['menu_id'] : 0;

		sp_remove_menu_items($menu_id);
		sp_remove_menu($menu_id);

		redirectexit('action=admin;area=portalmenus;sa=listcustommenu');
	}

	/**
	 * List the items contained in a custom menu
	 */
	public function action_custom_item_list()
	{
		global $context, $scripturl, $txt, $modSettings;

		// Want to remove some items from a menu?
		if (!empty($_POST['remove_items']) && !empty($_POST['remove']) && is_array($_POST['remove']))
		{
			checkSession();

			$remove = [];
			foreach ($_POST['remove'] as $index => $item_id)
			{
				$remove[(int) $index] = (int) $item_id;
			}

			sp_remove_menu_items($remove);
		}

		$menu_id = $this->getMenuContext();
		if (empty($context['menu']))
		{
			throw new Exception('error_sp_menu_not_found', false);
		}

		// Build the list option array to display the custom items in this custom menu
		$listOptions = [
			'id' => 'portal_items',
			'title' => $menu_id === 0 ? $txt['sp_admin_menus_main_item_list'] : $txt['sp_admin_menus_custom_item_list'],
			'items_per_page' => $modSettings['defaultMaxMessages'],
			'no_items_label' => $txt['sp_error_no_custom_menus'],
			'base_href' => $scripturl . '?action=admin;area=portalmenus;sa=' . ($menu_id === 0 ? 'listmainitem' : 'listcustomitem') . ';',
			'default_sort_col' => 'title',
			'get_items' => [
				'function' => [$this, 'list_sp_menu_item'],
				'params' => [
					$menu_id,
				],
			],
			'get_count' => [
				'function' => [$this, 'list_sp_menu_item_count'],
				'params' => [
					$menu_id,
				],
			],
			'columns' => [
				'title' => [
					'header' => [
						'value' => $txt['sp_admin_menus_col_title'],
					],
					'data' => [
						'db' => 'title',
					],
					'sort' => [
						'default' => 'title ASC',
						'reverse' => 'title DESC',
					],
				],
				'namespace' => [
					'header' => [
						'value' => $txt['sp_admin_menus_col_namespace'],
					],
					'data' => [
						'db' => 'namespace',
					],
					'sort' => [
						'default' => 'namespace ASC',
						'reverse' => 'namespace DESC',
					],
				],
				'target' => [
					'header' => [
						'value' => $txt['sp_admin_menus_col_target'],
					],
					'data' => [
						'db' => 'target',
					],
					'sort' => [
						'default' => 'target',
						'reverse' => 'target DESC',
					],
				],
				'status' => [
					'header' => [
						'value' => $txt['sp_admin_menus_col_status'],
						'class' => 'centertext',
					],
					'data' => [
						'db' => 'status_image',
						'class' => 'centertext',
					],
					'sort' => [
						'default' => 'state DESC',
						'reverse' => 'state',
					],
				],
				'action' => [
					'header' => [
						'value' => $txt['sp_admin_menus_col_actions'],
						'class' => ' grid8 centertext',
					],
					'data' => [
						'sprintf' => [
							'format' => '
								<a href="' . $scripturl . '?action=admin;area=portalmenus;sa=' . ($menu_id === 0 ? 'editmainitem' : 'editcustomitem') . ';menu_id=%1$s;item_id=%2$s;' . $context['session_var'] . '=' . $context['session_id'] . '">' . sp_embed_image('edit') . '</a>
								<a href="' . $scripturl . '?action=admin;area=portalmenus;sa=' . ($menu_id === 0 ? 'deletemainitem' : 'deletecustomitem') . ';menu_id=%1$s;item_id=%2$s;' . $context['session_var'] . '=' . $context['session_id'] . '" onclick="return confirm(\'' . $txt['sp_admin_menus_item_delete_confirm'] . '\');">' . sp_embed_image('delete') . '</a>',
							'params' => [
								'menu' => true,
								'id' => true,
							]
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
						'function' => function ($row) {
							return '<input type="checkbox" name="remove[]" value="' . $row['id'] . '" class="input_check" />';
						},
						'class' => 'centertext',
					],
				],
			],
			'form' => [
				'href' => $scripturl . '?action=admin;area=portalmenus;sa=listcustomitem;menu_id=' . $menu_id,
				'include_sort' => true,
				'include_start' => true,
				'hidden_fields' => [
					$context['session_var'] => $context['session_id'],
				],
			],
			'additional_rows' => [
				[
					'position' => 'below_table_data',
					'value' => '
						<input type="submit" name="remove_items" value="' . $txt['sp_admin_items_remove'] . '" class="right_submit" />',
				],
			],
		];

		// Set the context values
		$context['page_title'] = $txt['sp_admin_menus_custom_item_list'];
		$context['sub_template'] = 'show_list';
		$context['default_list'] = 'portal_items';

		// Create the list.
		createList($listOptions);
	}

	/**
	 * Returns the number of menus in the system
	 * Callback for createList()
	 *
	 * @param int $menu_id
	 *
	 * @return int
	 */
	public function list_sp_menu_item_count($menu_id)
	{
		return sp_menu_item_count($menu_id);
	}

	/**
	 * Returns an array of menus
	 * Callback for createList()
	 *
	 * @param int $start
	 * @param int $items_per_page
	 * @param string $sort
	 * @param int $menu_id
	 *
	 * @return array
	 */
	public function list_sp_menu_item($start, $items_per_page, $sort, $menu_id)
	{
		return sp_menu_items($start, $items_per_page, $sort, $menu_id);
	}

	/**
	 * Add or edit menu items
	 */
	public function action_custom_item_edit()
	{
		global $context, $txt;

		// No menu, no further
		$this->getMenuContext();
		if (empty($context['menu']))
		{
			throw new Exception('error_sp_menu_not_found', false);
		}

		// Need to know if we are adding or editing
		$is_new = empty($_REQUEST['item_id']);

		// Saving the form.
		if (!empty($_POST['submit']))
		{
			checkSession();

			// Use our standard validation functions
			$validator = new DataValidator();

			// Clean and Review the post data for compliance
			$validator->sanitation_rules([
				'title' => 'Util::htmltrim|Util::htmlspecialchars',
				'namespace' => 'Util::htmltrim|Util::htmlspecialchars',
				'item_id' => 'intval',
				'id_profile' => 'intval',
				'url' => 'Util::htmlspecialchars',
				'target' => 'intval',
				'state' => 'intval'
			]);
			$validator->validation_rules([
				'title' => 'required',
				'namespace' => 'alpha_numeric|required',
				'item_id' => 'required',
			]);
			$validator->text_replacements([
				'title' => $txt['sp_error_item_title_empty'],
				'namespace' => $txt['sp_error_item_namespace_empty'],
			]);

			// If you messed this up, back you go
			if (!$validator->validate($_POST))
			{
				// @todo, should set ErrorContext::context and display in template instead
				foreach ($validator->validation_errors() as $error)
				{
					throw new Exception($error, false);
				}
			}

			// Can't have the same name in the same menu twice
			$has_duplicate = sp_menu_check_duplicate_items($validator->item_id, $validator->namespace);
			if (!empty($has_duplicate))
			{
				throw new Exception('sp_error_item_namespace_duplicate', false);
			}

			// Can't have a simple numeric namespace
			if (preg_replace('~\d+~', '', $validator->namespace) === '')
			{
				throw new Exception('sp_error_item_namespace_numeric', false);
			}

			$item_info = [
				'id' => $validator->item_id,
				'id_menu' => $context['menu']['id'],
				'id_profile' => $validator->id_profile,
				'namespace' => $validator->namespace,
				'title' => $validator->title,
				'href' => $validator->url,
				'target' => $validator->target,
				'state' => isset($validator->state) ? 1 : 0,
				'placement' => !empty($_POST['placement']) ? Util::htmlspecialchars($_POST['placement']) : '',
				'placement_after' => !empty($_POST['placement_after']) ? Util::htmlspecialchars($_POST['placement_after']) : '',
			];

			// Adjust the url for the link type
			$link_type = !empty($_POST['link_type']) ? $_POST['link_type'] : '';
			$link_item = !empty($_POST['link_item']) ? $_POST['link_item'] : '';
			if ($link_type !== 'custom')
			{
				if (preg_match('~^(?:([abcpm])?(\d+)|([A-Za-z0-9_\-]+))$~', $link_item, $match))
				{
					if (!empty($match[2]))
					{
						$link_item_id = $match[2];
					}
					else
					{
						$link_item_id = $match[3] ?? $match[0];
					}
				}
				else
				{
					throw new Exception('sp_error_item_link_item_invalid', false);
				}

				switch ($link_type)
				{
					case 'action':
					case 'page':
					case 'category':
					case 'article':
					case 'menu':
						$item_info['href'] = '$scripturl?' . $link_type . '=' . $link_item_id;
						break;
					case 'board':
						$item_info['href'] = '$scripturl?' . $link_type . '=' . $link_item_id . '.0';
						break;
				}
			}

			// Add or update the item
			sp_add_menu_item($item_info, $is_new);

			$sa = $context['menu']['id'] === 0 ? 'listmainitem' : 'listcustomitem';
			redirectexit('action=admin;area=portalmenus;sa=' . $sa . ';menu_id=' . $context['menu']['id']);
		}

		// Prepare the items for the template
		if ($is_new)
		{
			$context['item'] = [
				'id' => 0,
				'namespace' => 'item' . random_int(1, 5000),
				'title' => $txt['sp_menus_default_menu_item_name'],
				'url' => '',
				'target' => 0,
				'id_profile' => 1,
				'placement' => '',
				'placement_after' => 'forum',
			];
		}
		// Not new, so fetch what we know about the item
		else
		{
			$_REQUEST['item_id'] = (int) $_REQUEST['item_id'];
			$context['item'] = sportal_get_menu_items($_REQUEST['item_id']);

 		    // Reverse engineer the URL to get the link type and item
			$context['item']['link_type'] = 'custom';
			$context['item']['link_item'] = '';
			$context['item']['id_profile'] = 0;
			if (preg_match('~\$scripturl\?([a-z]+)=([A-Za-z0-9_\-]+)(?:\.0)?$~', $context['item']['url'], $match))
			{
				$context['item']['link_type'] = $match[1];
				$context['item']['link_item'] = $match[2];

				// If it's board/page/category/article/menu, we need the prefix back for the JS
				$prefixes = ['board' => 'b', 'page' => 'p', 'category' => 'c', 'article' => 'a', 'menu' => 'm'];
				if (isset($prefixes[$context['item']['link_type']]))
				{
					$context['item']['link_item'] = $prefixes[$context['item']['link_type']] . $context['item']['link_item'];
				}
			}
		}

		// Menu action items
		$context['items']['action'] = sp_fetch_actions();
		$context['items'] = array_merge($context['items'], sp_block_template_helpers());

		// Permission profiles
		$context['profiles'] = sportal_get_profiles(null, 1);

		// Get the main menu buttons for placement selection
		if ($context['menu']['id'] === 0)
		{
			if (empty($context['menu_buttons']))
			{
				theme()->setupThemeContext();
			}

			$context['main_menu_buttons'] = [];
			foreach ($context['menu_buttons'] as $key => $button)
			{
				// Don't place it after itself, or the profile button, or a button that is not shown
				if ($button['show'] === true && $key !== 'profile' && $key !== $context['item']['namespace'])
				{
					$context['main_menu_buttons'][$key] = preg_replace('~<span class="pm_indicator"[^>]*>.*?</span>~i', '', $button['title']);
				}
			}
		}

		$context['page_title'] = $is_new ? $txt['sp_admin_menus_custom_item_add'] : $txt['sp_admin_menus_custom_item_edit'];
		$context['sub_template'] = 'menus_custom_item_edit';
	}

	/**
	 * Remove an item from a menu
	 */
	public function action_custom_item_delete()
	{
		checkSession('get');

		$menu_id = !empty($_REQUEST['menu_id']) ? (int) $_REQUEST['menu_id'] : 0;
		$item_id = !empty($_REQUEST['item_id']) ? (int) $_REQUEST['item_id'] : 0;

		sp_remove_menu_items($item_id);
		$sa = $menu_id === 0 ? 'listmainitem' : 'listcustomitem';

		redirectexit('action=admin;area=portalmenus;sa=' . $sa . ';menu_id=' . $menu_id);
	}

	/**
	 * Toggle the active state of a menu item
	 */
	public function action_custom_item_status()
	{
		global $context;

		checkSession($this->getApi() === 'xml' ? '' : 'get');

		$item_id = $this->_req->getRequest('item_id', 'intval', 0);
		$state = sp_changeState('menu_item', $item_id);

		if ($this->getApi() === 'xml')
		{
			$context['item_id'] = $item_id;
			$context['status'] = !empty($state) ? 'active' : 'deactive';

			theme()->getTemplates()->load('PortalAdmin');
			$template_layers = theme()->getLayers();
			$template_layers->removeAll();
			$context['sub_template'] = 'change_status';

			obExit();
		}

		redirectexit('action=admin;area=portalmenus;sa=listmainitem;menu_id=0');
	}

	/**
	 * Get the context for the menu
	 */
	public function getMenuContext()
	{
		global $context, $txt;

		$menu_id = isset($_REQUEST['menu_id']) ? (int) $_REQUEST['menu_id'] : 0;
		if ($menu_id === 0)
		{
			$context['menu'] = [
				'id' => 0,
				'name' => $txt['sp_admin_menus_main_item_list'],
			];
		}
		else
		{
			$context['menu'] = sportal_get_custom_menus($menu_id);
		}

		return $menu_id;
	}
}
