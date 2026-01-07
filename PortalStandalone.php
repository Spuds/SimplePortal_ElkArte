<?php

/**
 * @package SimplePortal ElkArte
 *
 * @author SimplePortal Team
 * @copyright 2015-2026 SimplePortal Team
 * @license BSD 3-clause
 * @version 2.0.0
 *
 * This version of SimplePortal is based on SimplePortal core 2.4
 *
 * This file here, unbelievably, has your portal within.
 *
 * To use SimplePortal in standalone mode:
 * - Go to "SPortal Admin" >> "Configuration" >> "General Settings"
 * - Select "Standalone" mode as "Portal Mode"
 * - Set "Standalone URL" as the full url of this file.
 * - Edit path to the forum ($forum_dir) in this file.
 *
 * See? It's just magic!
 *
 */

use Addons\SimplePortal\Controller\PortalMain;
use ElkArte\EventManager;

global $sp_standalone;

// Should be the full path!
$forum_dir = '/var/www/elkarte';

// Let them know the mode.
$sp_standalone = true;

// Hmm, wrong forum dir?
if (!file_exists($forum_dir . '/index.php'))
{
	die('Wrong $forum_dir value. Please make sure that the $forum_dir variable points to your forum\'s directory.');
}

// Load the SSI magic.
require_once($forum_dir . '/SSI.php');

// Get out the forum's Elkarte version number.
$data = substr(file_get_contents($forum_dir . '/index.php'), 0, 4096);
if (preg_match('~\*\s@version\s+(.+)[\s]{2}~i', $data, $match))
{
	$forum_version = 'ElkArte ' . $match[1];
	if (!defined('FORUM_VERSION'))
	{
		define('FORUM_VERSION', $forum_version);
	}
}

// Its all about the blocks
require_once(ADDONSDIR . '/SimplePortal/subs/spblocks/SPAbstractBlock.php');

// Initialize SP in standalone mode mode
theme()->getTemplates()->load('Portal', 'portal');
sportal_init(true);

// We'll catch you...
writeLog();

// Articles
$controller = new PortalMain(new EventManager());
$controller->pre_dispatch();
$controller->action_index();

// Here we go!
obExit(true);
