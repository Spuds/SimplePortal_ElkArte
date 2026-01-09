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

use BBC\ParserWrapper;
use BBC\PreparseCode;
use ElkArte\AbstractController;
use ElkArte\Action;
use ElkArte\Converters\Html2BBC;
use ElkArte\Converters\Html2Md;
use ElkArte\Languages\Txt;
use ElkArte\MembersList;
use ElkArte\SettingsForm\SettingsForm;
use Michelf\MarkdownExtra;

/**
 * SimplePortal Configuration controller class.
 * This class handles the general, blocks and articles configuration screens
 */
class ManagePortalMain extends AbstractController
{
	/** @var SettingsForm General settings form */
	protected $_generalSettingsForm;

	/** @var SettingsForm Block settings form */
	protected $_blockSettingsForm;

	/** @var SettingsForm Article settings form */
	protected $_articleSettingsForm;

	/**
	 * Main dispatcher.
	 *
	 * This function checks permissions and passes control through.
	 * If the passed section is not found, it shows the information page.
	 */
	public function action_index()
	{
		global $context, $txt;

		// You need to be an admin or have manage setting permissions to change anything
		if (!allowedTo('sp_admin'))
		{
			isAllowedTo('sp_manage_settings');
		}

		// Some helpful friends
		require_once(ADDONSDIR . '/SimplePortal/subs/PortalAdmin.subs.php');
		require_once(ADDONSDIR . '/SimplePortal/subs/Portal.subs.php');
		loadCSSFile('SimplePortal/portal.css', ['stale' => SPORTAL_STALE]);

		// Load the Simple Portal Help file.
		Txt::load('SimplePortalHelp');

		$subActions = [
			'information' => [$this, 'action_information'],
			'generalsettings' => [$this, 'action_general_settings'],
			'blocksettings' => [$this, 'action_block_settings'],
			'articlesettings' => [$this, 'action_article_settings'],
			'formatchange' => [$this, 'action_format_change'],
		];

		// Start up the controller, provide a hook since we can
		$action = new Action('portal_main');

		$context[$context['admin_menu_name']]['tab_data'] = [
			'title' => $txt['sp-adminConfiguration'],
			'help' => 'sp_ConfigurationArea',
			'description' => $txt['sp-adminConfigurationDesc'],
		];

		// Set the default to the information tab
		$subAction = $action->initialize($subActions, 'information');

		// Right then, off you go
		$action->dispatch($subAction);
	}

	/**
	 * General settings that control global portal actions
	 */
	public function action_general_settings()
	{
		global $context, $scripturl, $txt;

		$context['SPortal']['themes'] = sp_general_load_themes();

		// Initialize the form
		$this->_initGeneralSettingsForm();

		if (isset($_GET['save']))
		{
			checkSession();

			if (!empty($_POST['sp_portal_mode']))
			{
				updateSettings(['front_page' => '\Addons\SimplePortal\Controller\PortalMain']);
			}
			else
			{
				updateSettings(['front_page' => 'MessageIndex']);
			}

			$this->_generalSettingsForm->setConfigValues($_POST);
			$this->_generalSettingsForm->save();
			redirectexit('action=admin;area=portalconfig;sa=generalsettings');
		}

		$context['post_url'] = $scripturl . '?action=admin;area=portalconfig;sa=generalsettings;save';
		$context['settings_title'] = $txt['sp-adminGeneralSettingsName'];
		$context['page_title'] = $txt['sp-adminGeneralSettingsName'];
		$context['sub_template'] = 'show_settings';

		$this->_generalSettingsForm->prepare();
	}

	/**
	 * Initialize General Settings Form.
	 * Retrieve and return the general portal settings.
	 */
	private function _initGeneralSettingsForm()
	{
		global $txt, $context;

		// Instantiate the form
		$this->_generalSettingsForm = new SettingsForm(SettingsForm::DB_ADAPTER);

		$config_vars = [
			['select', 'sp_portal_mode', explode('|', $txt['sp_portal_mode_options'])],
			['check', 'sp_maintenance'],
			['text', 'sp_standalone_url'],
			'',
			['select', 'portaltheme', $context['SPortal']['themes']],
			['check', 'sp_disableColor'],
			['check', 'sp_disableForumRedirect'],
			['check', 'sp_disable_random_bullets'],
			['check', 'sp_disable_php_validation', 'subtext' => $txt['sp_disable_php_validation_desc']],
			['check', 'sp_disable_side_collapse'],
			['check', 'sp_resize_images'],
			['check', 'sp_disableMobile'],
			['check', 'sp_disableUserArrange'],

		];

		$this->_generalSettingsForm->setConfigVars($config_vars);
	}

	/**
	 * Settings that control how blocks behave
	 */
	public function action_block_settings()
	{
		global $context, $scripturl, $txt;

		// Initialize the form
		$this->_initBlockSettingsForm();
		$config_vars = $this->_blockSettingsForm->getConfigVars();

		if (isset($_GET['save']))
		{
			checkSession();

			$width_checkup = ['left', 'right'];
			foreach ($width_checkup as $pos)
			{
				if (!empty($_POST[$pos . 'width']))
				{
					$suffix = 'px';
					if (str_contains($_POST[$pos . 'width'], '%'))
					{
						$suffix = '%';
					}

					preg_match_all('/(\d+)|./', $_POST[$pos . 'width'], $matches);

					$number = (int) implode('', $matches[1]);
					if (!empty($number) && $number > 0)
					{
						$_POST[$pos . 'width'] = $number . $suffix;
					}
					else
					{
						$_POST[$pos . 'width'] = '';
					}
				}
				else
				{
					$_POST[$pos . 'width'] = '';
				}
			}

			unset($config_vars[7]);
			$config_vars = array_merge(
				$config_vars, [
					['check', 'sp_adminIntegrationHide'],
					['check', 'sp_profileIntegrationHide'],
					['check', 'sp_pmIntegrationHide'],
					['check', 'sp_mlistIntegrationHide'],
					['check', 'sp_searchIntegrationHide'],
					['check', 'sp_calendarIntegrationHide'],
					['check', 'sp_moderateIntegrationHide'],
				]
			);

			$this->_blockSettingsForm->setConfigVars($config_vars);
			$this->_blockSettingsForm->setConfigValues($_POST);
			$this->_blockSettingsForm->save();
			redirectexit('action=admin;area=portalconfig;sa=blocksettings');
		}

		$context['post_url'] = $scripturl . '?action=admin;area=portalconfig;sa=blocksettings;save';
		$context['settings_title'] = $txt['sp-adminBlockSettingsName'];
		$context['page_title'] = $txt['sp-adminBlockSettingsName'];
		$context['sub_template'] = 'show_settings';

		$this->_blockSettingsForm->prepare();
	}

	/**
	 * Initialize Block Settings Form.
	 * Retrieve and return the general portal settings.
	 */
	private function _initBlockSettingsForm()
	{
		global $txt;

		// instantiate the block form
		$this->_blockSettingsForm = new SettingsForm(SettingsForm::DB_ADAPTER);

		$config_vars = [
			['check', 'showleft'],
			['check', 'showright'],
			['text', 'leftwidth'],
			['text', 'rightwidth'],
			'',
			['multicheck',
				'sp_IntegrationHide',
				'subsettings' => ['sp_adminIntegrationHide' => $txt['admin'], 'sp_profileIntegrationHide' => $txt['profile'], 'sp_pmIntegrationHide' => $txt['personal_messages'], 'sp_mlistIntegrationHide' => $txt['members_title'], 'sp_searchIntegrationHide' => $txt['search'], 'sp_calendarIntegrationHide' => $txt['calendar'], 'sp_moderateIntegrationHide' => $txt['moderate']],
				'subtext' => $txt['sp_IntegrationHide_desc']
			],
		];

		$this->_blockSettingsForm->setConfigVars($config_vars);
	}

	/**
	 * Settings to control articles
	 */
	public function action_article_settings()
	{
		global $context, $scripturl, $txt;

		// Initialize the form
		$this->_initArticleSettingsForm();

		// Save away
		if (isset($_GET['save']))
		{
			checkSession();

			$this->_articleSettingsForm->setConfigValues($_POST);
			$this->_articleSettingsForm->save();
			redirectexit('action=admin;area=portalconfig;sa=articlesettings');
		}

		// Show the form
		$context['post_url'] = $scripturl . '?action=admin;area=portalconfig;sa=articlesettings;save';
		$context['settings_title'] = $txt['sp-adminArticleSettingsName'];
		$context['page_title'] = $txt['sp-adminArticleSettingsName'];
		$context['sub_template'] = 'show_settings';

		$this->_articleSettingsForm->prepare();
	}

	/**
	 * Initialize Article Settings Form.
	 * Retrieve and return the general portal settings.
	 */
	private function _initArticleSettingsForm()
	{
		global $txt;

		// instantiate the article form
		$this->_articleSettingsForm = new SettingsForm(SettingsForm::DB_ADAPTER);

		$config_vars = [
			['check', 'sp_articles_index'],
			['int', 'sp_articles_index_per_page'],
			['int', 'sp_articles_index_total'],
			['int', 'sp_articles_length'],
			['select', 'sp_articles_index_position', [
				'inline' => $txt['sp_articles_position_inline'],
				'above' => $txt['sp_articles_position_above'],
				'below' => $txt['sp_articles_position_below'],
			]],
			'',
			['int', 'sp_articles_per_page'],
			['int', 'sp_articles_comments_per_page'],
			'',
			['text', 'sp_articles_attachment_dir']
		];

		$this->_articleSettingsForm->setConfigVars($config_vars);
	}

	/**
	 * Our about page etc.
	 *
	 * @param bool $in_admin
	 */
	public function action_information($in_admin = true)
	{
		global $context, $scripturl, $txt, $modSettings;

		theme()->getTemplates()->load('PortalAdmin');

		$context['sp_credits'] = [
			[
				'pretext' => $txt['sp-info_intro'],
				'title' => $txt['sp-info_team'],
				'groups' => [
					[
						'title' => $txt['sp-info_groups_pm'],
						'members' => [
							'Eliana Tamerin',
							'Huw',
						],
					],
					[
						'title' => $txt['sp-info_groups_dev'],
						'members' => [
							'<span onclick="if (this.innerHTML.indexOf(\'Sinan\') === -1) this.innerHTML = \'Sinan &quot;[SiNaN]&quot; &Ccedil;evik\'; return false;">Selman &quot;[SiNaN]&quot; Eser</span>',
							'Spuds',
							'emanuele',
							'Nathaniel Baxter',
							'&#12487;&#12451;&#12531;1031',
						],
					],
					[
						'title' => $txt['sp-info_groups_support'],
						'members' => [
							'<span onclick="if (this.innerHTML.indexOf(\'Queen\') === -1) this.innerHTML = \'Angelina &quot;Queen of Support&quot; Belle\'; return false;">AngelinaBelle</span>',
							'Chen Zhen',
							'andy',
							'Ninja ZX-10RR',
							'phantomm',
						],
					],
					[
						'title' => $txt['sp-info_groups_customize'],
						'members' => [
							'Robbo',
							'Berat &quot;grafitus&quot; Do&#287;an',
							'Blue',
						],
					],
					[
						'title' => $txt['sp-info_groups_language'],
						'members' => [
							'Kryzen',
							'Jade &quot;Alundra&quot; Elizabeth',
							'<span onclick="if (this.innerHTML.indexOf(\'King\') === -1) this.innerHTML = \'130 &quot;King of Pirates&quot; 860\'; return false;">130860</span>',
						],
					],
					[
						'title' => $txt['sp-info_groups_marketing'],
						'members' => [
							'BryanD',
						],
					],
					[
						'title' => $txt['sp-info_groups_beta'],
						'members' => [
							'BurkeKnight',
							'ARG',
							'Old Fossil',
							'David',
							'sharks',
							'Willerby',
							'&#214;zg&#252;r',
							'c23_Mike',
						],
					],
				],
			],
			[
				'title' => $txt['sp-info_special'],
				'posttext' => $txt['sp-info_anyone'],
				'groups' => [
					[
						'title' => $txt['sp-info_groups_translators'],
						'members' => [
							$txt['sp-info_translators_message'],
						],
					],
					[
						'title' => $txt['sp-info_groups_founder'],
						'members' => [],
					],
					[
						'title' => $txt['sp-info_groups_original_pm'],
						'members' => [],
					],
				],
			],
		];

		if (!$in_admin)
		{
			$context['robot_no_index'] = true;
			$context['in_admin'] = false;
		}
		else
		{
			loadJavascriptFile('SimplePortal/portal.js', ['stale' => SPORTAL_STALE, 'defer' => true]);

			$context['in_admin'] = true;
			$context['sp_version'] = SPORTAL_VERSION;
			$context['sp_managers'] = [];

			$portal_mode = explode('|', $txt['sp_portal_mode_options']);
			$context['portal_mode'] = $portal_mode[$modSettings['sp_portal_mode']];

			require_once(SUBSDIR . '/Members.subs.php');
			$manager_ids = MembersList::load(membersAllowedTo('sp_admin'));
			if ($manager_ids)
			{
				foreach ($manager_ids as $member)
				{
					$member = MembersList::get($member);
					$context['sp_managers'][] = '<a href="' . $scripturl . '?action=profile;u=' . $member['id_member'] . '">' . $member['real_name'] . '</a>';
				}
			}
		}

		$context['sub_template'] = 'information';
		$context['page_title'] = $txt['sp-info_title'];
	}

	/**
	 * This is an ajax return function for articles and pages and anything else that
	 * wants to use it, for text format conversion
	 *
	 * Intended to "munge" source formats from a <> b Used when changing the type from
	 * bbc to html or markdown to bbc or php to bbc ..... you get it.
	 */
	public function action_format_change()
	{
		global $context;

		// Pretty basic
		checkSession('request');

		// Responding to an ajax request, that is all we do
		$req = request();
		if ($this->getApi() === 'xml')
		{
			theme()->getTemplates()->load('PortalAdmin');

			$format_parameters = [
				'text' => urldecode($_REQUEST['text']),
				'from' => $_REQUEST['from'],
				'to' => $_REQUEST['to'],
			];

			// Do whatever format juggle is requested
			$func = 'formatTo' . strtoupper($format_parameters['to']);
			if (method_exists($this, $func))
			{
				$this->$func($format_parameters);
			}

			// Return an XML response
			$template_layers = theme()->getLayers();
			$template_layers->removeAll();
			$context['sub_template'] = 'format_xml';
			$context['SPortal']['text'] = $format_parameters['text'];
		}
	}

	/**
	 * Convert various formats to BBC
	 *
	 * Convert MD->BBC, PHP->BBC, and HTML->BBC
	 *
	 * @param $format_parameters
	 */
	private function formatToBBC(&$format_parameters)
	{
		// From MD to BBC, the round about way
		if ($format_parameters['from'] === 'markdown')
		{
			// MD to HTML
			//require_once(EXTDIR . '/markdown/markdown.php');

			$parser = new MarkdownExtra();
			$parser->hashtag_protection = true;
			$format_parameters['text'] = $parser->transform($format_parameters['text']);

			// HTML to BBC
			$parser = new Html2BBC($format_parameters['text']);
			$format_parameters['text'] = $parser->get_bbc();
			$format_parameters['text'] = str_replace('[br]', "\n\n", $format_parameters['text']);
			$format_parameters['text'] = un_htmlspecialchars($format_parameters['text']);
			return;
		}

		// From php to BBC ?
		if ($format_parameters['from'] === 'php')
		{
			$format_parameters['text'] = htmlspecialchars($format_parameters['text']);
			$format_parameters['text'] = '[code]' . $format_parameters['text'] . '[/code]';
			return;
		}

		// HTML to BBC
		if ($format_parameters['from'] === 'html')
		{
			// The converter does not treat <Hx> tags as block level :(
			$format_parameters['text'] = preg_replace('~(<h\d>.*?</h\d>)~', '<br>$1<br>', $format_parameters['text']);

			$bbc_converter = new Html2BBC($format_parameters['text']);
			$bbc_converter->skip_tags(['font', 'span']);
			$bbc_converter->skip_styles(['font-family']);
			$format_parameters['text'] = $bbc_converter->get_bbc();
			$format_parameters['text'] = un_htmlspecialchars($format_parameters['text']);
			$format_parameters['text'] = str_replace('[br]', "\n", $format_parameters['text']);
		}
	}

	/**
	 * Convert various formats to HTML
	 *
	 * Markdown->HTML, PHP->HTML, and BBC->HTML
	 *
	 * @param array $format_parameters
	 */
	private function formatToHTML(&$format_parameters)
	{
		// From MD to HTML
		if ($format_parameters['from'] === 'markdown')
		{
			$parser = new MarkdownExtra();
			$parser->hashtag_protection = true;
			$format_parameters['text'] = htmlspecialchars($parser->transform($format_parameters['text']));

			return;
		}

		// From PHP to HTML ?
		if ($format_parameters['from'] === 'php')
		{
			$format_parameters['text'] = "<code>\n" . $format_parameters['text'] . "\n</code>";
			$format_parameters['text'] = htmlspecialchars($format_parameters['text']);
			return;
		}

		// BBC to HTML
		if ($format_parameters['from'] === 'bbc')
		{
			PreparseCode::instance('')->preparsecode($format_parameters['text']);
			$format_parameters['text'] = ParserWrapper::instance()->parseMessage($format_parameters['text'] , true);

			$format_parameters['text'] = strtr($format_parameters['text'], ['&nbsp;' => ' ', '<br />' => "\n<br />"]);
			$format_parameters['text'] = htmlspecialchars($format_parameters['text']);
		}
	}

	/**
	 * Convert various formats to MarkDown
	 *
	 * Convert BBC->Markdown, PHP->Markdown, and HTML->Markdown
	 *
	 * @param array $format_parameters
	 */
	private function formatToMARKDOWN(&$format_parameters)
	{
		// From BBC to MD, should have a direct way, but ...
		if ($format_parameters['from'] === 'bbc')
		{
			PreparseCode::instance('')->preparsecode($format_parameters['text']);
			$format_parameters['text'] = ParserWrapper::instance()->parseMessage($format_parameters['text'] , true);

			// Convert this to markdown
			$parser = new Html2Md($format_parameters['text']);
			$format_parameters['text'] = $parser->get_markdown();
			$format_parameters['text'] = htmlspecialchars($format_parameters['text']);
			return;
		}

		// From PHP to MD ?
		if ($format_parameters['from'] === 'php')
		{
			$format_parameters['text'] = '<code>' . un_htmlspecialchars($format_parameters['text']) . '</code>';
			$parser = new Html2Md($format_parameters['text']);
			$format_parameters['text'] = htmlspecialchars($parser->get_markdown());
			return;
		}

		// HTML to MD
		if ($format_parameters['from'] === 'html')
		{
			// Convert this to markdown
			$parser = new Html2Md($format_parameters['text']);
			$format_parameters['text'] = $parser->get_markdown();
		}
	}
}
