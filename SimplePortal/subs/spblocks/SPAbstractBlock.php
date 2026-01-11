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
use ElkArte\Helper\ValuesContainer;

/**
 * Abstract Simple Portal block
 *
 * - Sets base functionality for use in all blocks
 */
abstract class SPAbstractBlock
{
	/** @var QueryInterface */
	protected $_db;

	/** @var array|ValuesContainer */
	protected $_modSettings = [];

	/** @var array Block parameters */
	protected $block_parameters = [];

	/** @var array Data array for use in the blocks */
	protected $data = [];

	/** @var string Name of the template function to call */
	protected $template = '';

	/** @var array If the block supports refreshing, sets the time in seconds */
	protected $refresh = [];

	/**
	 * Class constructor makes db and modSettings available to the blocks
	 *
	 * - Called by sp_instantiate_block function (via block constructor)
	 *
	 * @param QueryInterface|null $db
	 */
	public function __construct($db = null)
	{
		global $modSettings;

		$this->_db = $db;

		$this->_modSettings = new ValuesContainer($modSettings ?: []);
	}

	/**
	 * Returns block parameters used to build the ACP block "options" configuration page
	 *
	 * - Possible Params include boards|boards_select|check|int|select|text|textarea
	 *
	 * @return array
	 */
	public function parameters()
	{
		return $this->block_parameters;
	}

	/**
	 * Sets the template name that will be called via render
	 *
	 * @param string $template
	 */
	public function setTemplate($template)
	{
		$this->template = $template;
	}

	/**
	 * Called as part of the sportal_load_blocks process to initiate a block prior
	 * to its being displayed.
	 *
	 * @param array $parameters
	 * @param int $id
	 */
	abstract public function setup($parameters, $id);

	/**
	 * Renders a block with a given template and data
	 */
	public function render()
	{
		if (is_callable($this->template))
		{
			call_user_func_array($this->template, [$this->data]);
		}
	}

	/**
	 * Validates that a user can access a block, defaults to yes they can
	 *
	 * @return string[]
	 */
	public static function permissionsRequired()
	{
		return [];
	}

	/**
	 * Sets the name of the block in $txt string for use with custom
	 * blocks. $txt['sp_function_BlockName_label']
	 *
	 * @return string
	 */
	public static function blockName()
	{
		return '';
	}

	/**
	 * Sets the description of the block in $txt for use with custom
	 * blocks.  $txt['sp_function_BlockName_desc']
	 *
	 * @return string
	 */
	public static function blockDescription()
	{
		return '';
	}

	/**
	 * Adds javascript to make a block refresh call in the background
	 */
	public function auto_refresh()
	{
		// Be reasonable on the refresh, do not beat on the server
		$refresh = (max((int) $this->refresh['refresh_value'], 30)) * 1000;

		theme()->addInlineJavascript('
			document.addEventListener("DOMContentLoaded", () => {
				let block = document.getElementById("sp_block_' . (int) $this->refresh['id'] . '"),
					container = block ? block.querySelector("' . $this->refresh['class'] . '") : null;

				if (container === null)
				{
					return;
				}

				let spRefreshParams = new URLSearchParams({"block": ' . (int) $this->refresh['id'] . ', [elk_session_var]: elk_session_id});

				setInterval(() => {
					fetch(elk_prepareScriptUrl(sp_script_url) + "action=portalrefresh;sa=' . $this->refresh['sa'] . ';api=html", {
						method: "POST",
						body: spRefreshParams,
						headers: {
							"X-Requested-With": "XMLHttpRequest"
						}
					})
					.then(response => response.ok ? response.text() : "")
					.then(result => {
						if (result !== "")
						{
							container.innerHTML = result;
						}
					});
				}, ' . $refresh . ');
			});
		');
	}
}
