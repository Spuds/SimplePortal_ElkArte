<?php

/**
 * @package SimplePortal
 *
 * @author SimplePortal Team
 * @copyright 2015-2026 SimplePortal Team
 * @license BSD 3-clause
 * @version 2.0.0
 */

use Addons\SimplePortal\PortalIntegrate;

/**
 * Menu Block, creates a sidebar menu block based on the system main menu
 *
 * @param array $parameters -  not used in this block
 * @param int $id - not used in this block
 * @param bool $return_parameters if true returns the configuration options for the block
 *@todo needs updating so it knows right vs left block for the flyout
 *
 */
class MenuBlock extends SPAbstractBlock
{
	/**
	 * Constructor, used to define block parameters
	 *
	 * @param \ElkArte\Database\QueryInterface|null $db
	 */
	public function __construct($db = null)
	{
		$this->block_parameters = [
			'menu' => 'select',
		];

		parent::__construct($db);
	}

	/**
	 * Sets / loads optional parameters for a block
	 */
	public function parameters()
	{
		global $txt;

		require_once(ADDONSDIR . '/SimplePortal/subs/Portal.subs.php');
		$menus = sportal_get_custom_menus();

		$this->block_parameters['menu'][0] = $txt['sp_admin_menus_main_item_list'];
		foreach ($menus as $menu)
		{
			$this->block_parameters['menu'][$menu['id']] = $menu['name'];
		}

		return $this->block_parameters;
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
		global $context;

		$menu_id = !empty($parameters['menu']) ? (int) $parameters['menu'] : 0;

		if ($menu_id === 0)
		{
			if (empty($context['menu_buttons']))
			{
				theme()->setupThemeContext();
			}
			$this->data['menu_buttons'] = $context['menu_buttons'];
		}
		else
		{
			require_once(ADDONSDIR . '/SimplePortal/PortalIntegrate.php');
			$this->data['menu_buttons'] = PortalIntegrate::sp_load_menu_items($menu_id);
		}

		$this->setTemplate('template_sp_menu');
	}
}

/**
 * Main template for this block
 *
 * @param array $data
 */
function template_sp_menu($data)
{
	if (empty($data['menu_buttons']))
	{
		return;
	}

	echo '
		<ul id="sp_menu" class="sp_list">';

	foreach ($data['menu_buttons'] as $act => $button)
	{
		echo '
			<li ', sp_embed_class('dot'), '>
				<a title="', strip_tags($button['title']), '" href="', $button['href'], '">',
					(!empty($button['active_button']) ? '<strong>' : ''), $button['title'], (!empty($button['active_button']) ? '</strong>' : ''), '
				</a>';

		if (!empty($button['sub_buttons']))
		{
			echo '
				<ul class="sp_list">';

			foreach ($button['sub_buttons'] as $sub_button)
			{
				echo '
					<li ', sp_embed_class('dot', '', 'sp_list_indent'), '>
						<a title="', $sub_button['title'], '" href="', $sub_button['href'], '">', $sub_button['title'], '</a>
					</li>';
			}

			echo '
				</ul>';
		}

		echo '</li>';
	}

	echo '
		</ul>';
}
