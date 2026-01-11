<?php

/**
 * @package SimplePortal
 *
 * @author SimplePortal Team
 * @copyright 2015-2026 SimplePortal Team
 * @license BSD 3-clause
 * @version 2.0.0
 */

use BBC\ParserWrapper;
use BBC\PreparseCode;
use ElkArte\Cache\Cache;
use ElkArte\Database\QueryInterface;
use ElkArte\Exceptions\Exception;
use ElkArte\Helper\Util;
use ElkArte\Languages\Txt;
use ElkArte\User;

/**
 * Shoutbox Block, show the shoutbox thoughts box
 *
 * @param array $parameters
 *        'shoutbox' => list of shoutboxes to choose from
 * @param int $id - not used in this block
 * @param bool $return_parameters if true returns the configuration options for the block
 */
class ShoutboxBlock extends SPAbstractBlock
{
	/**
	 * Constructor, used to define block parameters
	 *
	 * @param QueryInterface|null $db
	 */
	public function __construct($db = null)
	{
		require_once(ADDONSDIR . '/SimplePortal/subs/PortalShoutbox.subs.php');

		$this->block_parameters = [
			'shoutbox' => [],
		];

		parent::__construct($db);
	}

	/**
	 * Returns optional block parameters
	 *
	 * @return array
	 * @throws Exception
	 */
	public function parameters()
	{
		global $scripturl, $txt;

		$shoutboxes = sportal_get_shoutbox();

		$in_use = [];
		$this->_db->query('', '
			SELECT
				id_block, value
			FROM {db_prefix}sp_parameters
			WHERE variable = {string:name}',
			[
				'name' => 'shoutbox',
			]
		)->fetch_callback(function ($row) use (&$in_use) {
			if (empty($_REQUEST['block_id']) || $_REQUEST['block_id'] != $row['id_block'])
			{
				$in_use[] = $row['value'];
			}
		});

		// Load up all the shoutboxes that are NOT being used
		foreach ($shoutboxes as $shoutbox)
		{
			if (!in_array($shoutbox['id'], $in_use))
			{
				$this->block_parameters['shoutbox'][$shoutbox['id']] = $shoutbox['name'];
			}
		}

		if (empty($this->block_parameters['shoutbox']))
		{
			throw new Exception(allowedTo(['sp_admin', 'sp_manage_shoutbox']) ? $txt['error_sp_no_shoutbox'] . '<br />' . sprintf($txt['error_sp_no_shoutbox_sp_moderator'], $scripturl . '?action=admin;area=portalshoutbox;sa=add') : $txt['error_sp_no_shoutbox_normaluser'], false);
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
	 * @throws Exception
	 */
	public function setup($parameters, $id)
	{
		global $context, $settings, $txt;

		theme()->getTemplates()->load('PortalShoutbox');
		Txt::load('Editor');
		Txt::load('Post');

		$this->data = sportal_get_shoutbox($parameters['shoutbox'], true, true);

		// To add a shoutbox, you must have one or more defined for use
		if (empty($this->data))
		{
			$this->data['error_msg'] = $txt['error_sp_shoutbox_not_exist'];
			$this->setTemplate('template_sp_shoutbox_error');

			return;
		}

		// Going to add to the shoutbox
		if (!empty($_POST['new_shout'])
			&& !empty($_POST['submit_shout'])
			&& !empty($_POST['shoutbox_id'])
			&& $_POST['shoutbox_id'] == $this->data['id'])
		{
			// Make sure things are in order
			checkSession();
			is_not_guest();

			if (!($flood = sp_prevent_flood('spsbp', false)))
			{
				require_once(SUBSDIR . '/Post.subs.php');

				$_POST['new_shout'] = Util::htmlspecialchars(trim($_POST['new_shout']));
				$preparse = PreparseCode::instance('');
				$preparse->preparsecode($_POST['new_shout']);

				if (!empty($_POST['new_shout']))
				{
					sportal_create_shout($this->data, $_POST['new_shout']);
				}
			}
			else
			{
				$this->data['warning'] = $flood;
			}
		}

		// Can they moderate the shoutbox (delete shouts?)
		$can_moderate = allowedTo('sp_admin') || allowedTo('sp_manage_shoutbox');
		if (!$can_moderate && !empty($this->data['moderator_groups']))
		{
			$can_moderate = count(array_intersect(User::$info->groups, $this->data['moderator_groups'])) > 0;
		}

		$shout_parameters = [
			'limit' => $this->data['num_show'],
			'bbc' => $this->data['allowed_bbc'],
			'reverse' => $this->data['reverse'],
			'cache' => $this->data['caching'],
			'can_moderate' => $can_moderate,
		];
		$this->data['shouts'] = sportal_get_shouts($this->data['id'], $shout_parameters);

		$parser = ParserWrapper::instance();
		$this->data['warning'] = $parser->parseMessage($this->data['warning'], true);
		$context['can_shout'] = $context['user']['is_logged'];

		if ($context['can_shout'])
		{
			// Set up the smiley tags for the shoutbox
			$this->data['smileys'] = ['normal' => [], 'popup' => []];
			$settings['smileys_url'] = $context['smiley_path'];

			// No Smileys, then just some defaults
			if (empty($context['smiley_enabled']))
			{
				$this->data['smileys']['normal'] = $this->_smileys();
			}
			elseif (Cache::instance()->get('shoutbox_smileys', 3600) === null)
			{
				require_once(SUBSDIR . '/Smileys.subs.php');
				$smileys = getEditorSmileys();

				// Flatten the smiley array structure
				$normal_smileys = [];
				foreach ($smileys['postform'] as $row) {
					$normal_smileys = array_merge($normal_smileys, $row['smileys']);
				}
				$this->data['smileys']['normal'] = $normal_smileys;

				$popup_smileys = [];
				foreach ($smileys['popup'] as $row) {
					$popup_smileys = array_merge($popup_smileys, $row['smileys']);
				}
				$this->data['smileys']['popup'] = $popup_smileys;

				Cache::instance()->put('shoutbox_smileys', $this->data['smileys'], 3600);
			}
			else
			{
				$this->data['smileys'] = Cache::instance()->get('shoutbox_smileys', 3600);
			}

			foreach (array_keys($this->data['smileys']) as $location)
			{
				$n = count($this->data['smileys'][$location]);
				foreach ($this->data['smileys'][$location] as $i => $iValue)
				{
					$this->data['smileys'][$location][$i]['code'] = addslashes($iValue['code']);
					$this->data['smileys'][$location][$i]['js_description'] = addslashes($iValue['description']);
					$this->data['smileys'][$location][$i]['url'] = (isset($iValue['emoji']) ? $context['emoji_path'] : $context['smiley_path']) . $iValue['filename'];
				}

				if (!empty($this->data['smileys'][$location]))
				{
					$this->data['smileys'][$location][$n - 1]['last'] = true;
				}
			}

			// Basic shoutbox bbc we allow
			$this->data['bbc'] = $this->_bbc();
		}

		$this->setTemplate('template_shoutbox_embed');
	}

	/**
	 * Load in the available BBC codes that a shoutbox can use
	 *
	 * @return array
	 */
	private function _bbc()
	{
		global $editortxt;

		return [
			'bold' => ['code' => 'b', 'before' => '[b]', 'after' => '[/b]', 'description' => $editortxt['Bold']],
			'italicize' => ['code' => 'i', 'before' => '[i]', 'after' => '[/i]', 'description' => $editortxt['Italic']],
			'underline' => ['code' => 'u', 'before' => '[u]', 'after' => '[/u]', 'description' => $editortxt['Underline']],
			'strike' => ['code' => 's', 'before' => '[s]', 'after' => '[/s]', 'description' => $editortxt['Strikethrough']],
			'pre' => ['code' => 'pre', 'before' => '[pre]', 'after' => '[/pre]', 'description' => $editortxt['Preformatted Text']],
			'img' => ['code' => 'img', 'before' => '[img]', 'after' => '[/img]', 'description' => $editortxt['Insert an image']],
			'url' => ['code' => 'url', 'before' => '[url]', 'after' => '[/url]', 'description' => $editortxt['Insert a link']],
			'email' => ['code' => 'email', 'before' => '[email]', 'after' => '[/email]', 'description' => $editortxt['Insert an email']],
			'sup' => ['code' => 'sup', 'before' => '[sup]', 'after' => '[/sup]', 'description' => $editortxt['Superscript']],
			'sub' => ['code' => 'sub', 'before' => '[sub]', 'after' => '[/sub]', 'description' => $editortxt['Subscript']],
			'tele' => ['code' => 'tt', 'before' => '[tt]', 'after' => '[/tt]', 'description' => $editortxt['Teletype']],
			'code' => ['code' => 'code', 'before' => '[code]', 'after' => '[/code]', 'description' => $editortxt['Code']],
			'quote' => ['code' => 'quote', 'before' => '[quote]', 'after' => '[/quote]', 'description' => $editortxt['Insert a Quote']],
		];
	}

	/**
	 * Load up the standard smileys for use
	 * @return array
	 */
	private function _smileys()
	{
		global $txt;

		return [
			['code' => ':)', 'filename' => 'smiley.svg', 'description' => $txt['icon_smiley']],
			['code' => ';)', 'filename' => 'wink.svg', 'description' => $txt['icon_wink']],
			['code' => ':D', 'filename' => 'cheesy.svg', 'description' => $txt['icon_cheesy']],
			['code' => ';D', 'filename' => 'grin.svg', 'description' => $txt['icon_grin']],
			['code' => '>:(', 'filename' => 'angry.svg', 'description' => $txt['icon_angry']],
			['code' => ':(', 'filename' => 'sad.svg', 'description' => $txt['icon_sad']],
			['code' => ':o', 'filename' => 'shocked.svg', 'description' => $txt['icon_shocked']],
			['code' => '8)', 'filename' => 'cool.svg', 'description' => $txt['icon_cool']],
			['code' => '???', 'filename' => 'huh.svg', 'description' => $txt['icon_huh']],
			['code' => '::)', 'filename' => 'rolleyes.svg', 'description' => $txt['icon_rolleyes']],
			['code' => ':P', 'filename' => 'tongue.svg', 'description' => $txt['icon_tongue']],
			['code' => ':-[', 'filename' => 'embarrassed.svg', 'description' => $txt['icon_embarrassed']],
			['code' => ':-X', 'filename' => 'lipsrsealed.svg', 'description' => $txt['icon_lips']],
			['code' => ':-\\', 'filename' => 'undecided.svg', 'description' => $txt['icon_undecided']],
			['code' => ':-*', 'filename' => 'kiss.svg', 'description' => $txt['icon_kiss']],
			['code' => ':\'(', 'filename' => 'cry.svg', 'description' => $txt['icon_cry']]
		];
	}
}

/**
 * Error template for this block
 *
 * @param array $data
 */
function template_sp_shoutbox_error($data)
{
	echo '
		', $data['error_msg'];
}
