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
use ElkArte\Errors\ErrorContext;
use ElkArte\Helper\DataValidator;

/**
 * SimplePortal Category Administration controller class.
 *
 * - This class handles the adding/editing/listing of categories
 */
class ManagePortalCategories extends AbstractController
{
	/** @var bool If we are adding a new category*/
	protected $_is_new;

	/** @var ErrorContext */
	protected $category_errors;

	/**
	 * Main dispatcher.
	 * This function checks permissions and passes control through.
	 */
	public function action_index()
	{
		global $context, $txt;

		// You need to be an admin or have manage permissions to change category settings
		if (!allowedTo('sp_admin'))
		{
			isAllowedTo('sp_manage_categories');
		}

		// We'll need the utility functions from here.
		require_once(ADDONSDIR . '/SimplePortal/subs/PortalAdmin.subs.php');
		require_once(ADDONSDIR . '/SimplePortal/subs/Portal.subs.php');

		$subActions = [
			'list' => [$this, 'action_list'],
			'add' => [$this, 'action_edit'],
			'edit' => [$this, 'action_edit'],
			'status' => [$this, 'action_status'],
			'delete' => [$this, 'action_delete'],
		];

		// Start up the controller, provide a hook since we can
		$action = new Action('portal_categories');

		// Set up the tabs
		$context[$context['admin_menu_name']]['tab_data'] = [
			'title' => $txt['sp_admin_categories_title'],
			'help' => 'sp_CategoriesArea',
			'description' => $txt['sp_admin_categories_desc'],
			'tabs' => [
				'list' => [],
				'add' => [],
			],
		];

		// Default to list the categories
		$subAction = $action->initialize($subActions, 'list');
		$context['sub_action'] = $subAction;

		// Call the right function for this sub-action.
		$action->dispatch($subAction);
	}

	/**
	 * Show a listing of categories in the system
	 */
	public function action_list()
	{
		global $context, $scripturl, $txt, $modSettings;

		// Build the listoption array to display the categories
		$listOptions = [
			'id' => 'portal_categories',
			'title' => $txt['sp_admin_categories_list'],
			'items_per_page' => $modSettings['defaultMaxMessages'],
			'no_items_label' => $txt['error_sp_no_categories'],
			'base_href' => $scripturl . '?action=admin;area=portalcategories;sa=list;',
			'default_sort_col' => 'name',
			'get_items' => [
				'function' => [$this, 'list_spLoadCategories'],
			],
			'get_count' => [
				'function' => [$this, 'list_spCountCategories'],
			],
			'columns' => [
				'name' => [
					'header' => [
						'value' => $txt['sp_admin_categories_col_name'],
					],
					'data' => [
						'db' => 'name',
					],
					'sort' => [
						'default' => 'name',
						'reverse' => 'name DESC',
					],
				],
				'namespace' => [
					'header' => [
						'value' => $txt['sp_admin_categories_col_namespace'],
					],
					'data' => [
						'db' => 'category_id',
					],
					'sort' => [
						'default' => 'category_id',
						'reverse' => 'category_id DESC',
					],
				],
				'articles' => [
					'header' => [
						'value' => $txt['sp_admin_categories_col_articles'],
						'class' => 'centertext',
					],
					'data' => [
						'db' => 'articles',
						'class' => 'centertext',
					],
					'sort' => [
						'default' => 'articles',
						'reverse' => 'articles DESC',
					],
				],
				'status' => [
					'header' => [
						'value' => $txt['sp_admin_categories_col_status'],
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
						'value' => $txt['sp_admin_categories_col_actions'],
						'class' => 'centertext',
					],
					'data' => [
						'sprintf' => [
							'format' => '
								<a href="?action=admin;area=portalcategories;sa=edit;category_id=%1$s;' . $context['session_var'] . '=' . $context['session_id'] . '" accesskey="e">' . sp_embed_image('edit') . '</a>&nbsp;
								<a href="?action=admin;area=portalcategories;sa=delete;category_id=%1$s;' . $context['session_var'] . '=' . $context['session_id'] . '" onclick="return confirm(' . JavaScriptEscape($txt['sp_admin_categories_delete_confirm']) . ') && submitThisOnce(this);" accesskey="d">' . sp_embed_image('trash') . '</a>',
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
				'href' => $scripturl . '?action=admin;area=portalcategories;sa=remove',
				'include_sort' => true,
				'include_start' => true,
				'hidden_fields' => [
					$context['session_var'] => $context['session_id'],
				],
			],
			'additional_rows' => [
				[
					'class' => 'submitbutton',
					'position' => 'below_table_data',
					'value' => '<a class="linkbutton" href="?action=admin;area=portalcategories;sa=add;' . $context['session_var'] . '=' . $context['session_id'] . '" accesskey="a">' . $txt['sp_admin_categories_add'] . '</a>
						<input type="submit" name="remove_categories" value="' . $txt['sp_admin_categories_remove'] . '" />',
				],
			],
		];

		// Set the context values
		$context['page_title'] = $txt['sp_admin_categories_title'];
		$context['sub_template'] = 'show_list';
		$context['default_list'] = 'portal_categories';

		// Create the list.
		createList($listOptions);
	}

	/**
	 * Callback for createList(),
	 * Returns the number of categories in the system
	 */
	public function list_spCountCategories()
	{
		return sp_count_categories();
	}

	/**
	 * Callback for createList()
	 * Returns an array of categories
	 *
	 * @param int $start
	 * @param int $items_per_page
	 * @param string $sort
	 *
	 * @return array
	 */
	public function list_spLoadCategories($start, $items_per_page, $sort)
	{
		return sp_load_categories($start, $items_per_page, $sort);
	}

	/**
	 * Edit or add a category
	 */
	public function action_edit()
	{
		global $context, $txt;

		theme()->getTemplates()->load('PortalAdminCategories');
		$this->category_errors = ErrorContext::context('category', 0);
		$this->_is_new = empty($this->_req->getRequest('category_id'));

		// Saving the category form
		if (!empty($_POST['submit']))
		{
			checkSession();

			// Clean and Review the data for compliance
			$validator = new DataValidator();
			$validator->sanitation_rules([
				'category_name' => 'Util::htmltrim|Util::htmlspecialchars',
				'category_namespace' => 'trim|Util::htmlspecialchars',
				'category_description' => 'trim|Util::htmlspecialchars',
				'category_permissions' => 'intval',
				'category_id' => 'intval'
			]);
			$validator->validation_rules([
				'category_name' => 'required',
				'category_namespace' => 'alpha_numeric|required',
				'category_description' => 'required'
			]);
			$validator->text_replacements([
				'category_name' => $txt['sp_admin_categories_col_name'],
				'category_namespace' => $txt['sp_admin_categories_col_namespace'],
				'category_description' => $txt['sp_admin_categories_col_description']
			]);

			// If you messed this up, tell them why
			if (!$validator->validate($_POST))
			{
				foreach ($validator->validation_errors() as $id => $error)
				{
					$this->category_errors->addError($error);
				}
			}

			if (sp_check_duplicate_category($validator->category_id, $validator->category_namespace))
			{
				$this->category_errors->addError('sp_error_category_namespace_duplicate');
			}

			if ($validator->category_namespace !== '' && preg_replace('~\d+~', '', $_POST['category_namespace']) === '')
			{
				$this->category_errors->addError('sp_error_category_namespace_numeric');
			}

			$category_info = [
				'id' => $validator->category_id,
				'namespace' => $validator->category_namespace,
				'name' => $validator->category_name,
				'description' => $validator->category_description,
				'permissions' => $validator->category_permissions,
				'status' => $this->_req->hasPost('category_status') ? 1 : 0,
			];

			// None shall pass ... with errors
			if ($this->category_errors->hasErrors())
			{
				// Return what we have to the form, show them the issues
				$context['category'] = $category_info;
				$context['category_errors'] = [
					'errors' => $this->category_errors->prepareErrors(),
					'type' => 'minor',
					'title' => $txt['sp_form_errors_detected'],
				];
				unset($_POST['submit']);
			}
			else
			{
				// Clear to save
				sp_update_category($category_info, $this->_is_new);
				redirectexit('action=admin;area=portalcategories');
			}
		}
		// Creating a new category, lets set up some defaults for the form
		elseif ($this->_is_new)
		{
			$context['category'] = [
				'id' => 0,
				'namespace' => 'category' . random_int(1, 5000),
				'name' => $txt['sp_categories_default_name'],
				'description' => '',
				'permissions' => 3,
				'groups_allowed' => [],
				'groups_denied' => [],
				'status' => 1,
			];
		}
		else
		{
			$category_id = $this->_req->getQuery('category_id', 'intval', 0);
			$context['category'] = sportal_get_categories($category_id);
		}

		$context['is_new'] = $this->_is_new;
		$context['category']['permission_profiles'] = sportal_get_profiles(null, 1, 'name');
		$context['category']['groups'] = sp_load_membergroups();
		$context['page_title'] = $this->_is_new ? $txt['sp_admin_categories_add'] : $txt['sp_admin_categories_edit'];
		$context['sub_template'] = 'categories_edit';
	}

	/**
	 * Switch the active status (on/off) of a category
	 */
	public function action_status()
	{
		global $context;

		checkSession($this->getApi() === 'xml' ? '' : 'get');

		$category_id = $this->_req->getRequest('category_id', 'intval', 0);
		$state = sp_changeState('category', $category_id);

		// Doing this the ajax way?
		if ($this->getApi() === 'xml')
		{
			$context['item_id'] = $category_id;
			$context['status'] = !empty($state) ? 'active' : 'deactive';

			// Clear out any template layers, add the xml response
			theme()->getTemplates()->load('PortalAdmin');
			$template_layers = theme()->getLayers();
			$template_layers->removeAll();
			$context['sub_template'] = 'change_status';

			obExit();
		}

		redirectexit('action=admin;area=portalcategories');
	}

	/**
	 * Delete a category or group of categories by id
	 */
	public function action_delete()
	{
		$category_ids = [];

		// Receive the cat ids to remove
		if ($this->_req->hasPost('remove_categories')
			&& $this->_req->hasPost('remove')
			&& is_array($this->_req->getPost('remove')))
		{
			checkSession();

			foreach ($this->_req->getPost('remove') as $index => $category_id)
			{
				$category_ids[(int) $index] = (int) $category_id;
			}
		}
		elseif (!empty($this->_req->getRequest('category_id')))
		{
			checkSession('get');
			$category_ids[] = $this->_req->getRequest('category_id', 'intval', 0);
		}

		// If we have some to remove
		if (!empty($category_ids))
		{
			sp_delete_categories($category_ids);
		}

		redirectexit('action=admin;area=portalcategories');
	}
}
