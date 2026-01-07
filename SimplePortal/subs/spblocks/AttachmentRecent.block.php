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

/**
 * Attachment Block, Displays a list of recent attachments (by name)
 *
 * @param array $parameters
 *		'limit' => Board(s) to select posts from
 * @param int $id - not used in this block
 * @param bool $return_parameters if true returns the configuration options for the block
 */
class AttachmentRecentBlock extends SPAbstractBlock
{
	/**
	 * Constructor, used to define block parameters
	 *
	 * @param QueryInterface|null $db
	 */
	public function __construct($db = null)
	{
		$this->block_parameters = [
			'limit' => 'int',
		];

		parent::__construct($db);
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
		global $txt;

		$limit = empty($parameters['limit']) ? 5 : (int) $parameters['limit'];

		$this->data['items'] = ssi_recentAttachments($limit, [], 'array');

		// No attachments, at least none that they can see
		if (empty($this->data['items']))
		{
			$this->data['error_msg'] = $txt['error_sp_no_attachments_found'];
			$this->setTemplate('template_sp_attachmentRecent_error');

			return;
		}

		$this->setTemplate('template_sp_attachmentRecent');
	}
}

/**
 * Error template for this block
 *
 * @param array $data
 */
function template_sp_attachmentRecent_error($data)
{
	echo $data['error_msg'];
}

/**
 * Main template for this block
 *
 * @param array $data
 */
function template_sp_attachmentRecent($data)
{
	global $txt;

	echo '
		<ul class="sp_list">';

	foreach ($data['items'] as $item)
	{
		echo '
			<li>
				<i class="icon i-clip"></i><a href="', $item['file']['href'], '">', $item['file']['filename'], '</a>
				<p class="xsmalltext" style="padding: 0 26px;margin: -6px 0 6px 0;">', $txt['downloads'], ': ', $item['file']['downloads'], ' / ', $txt['filesize'], ': ', $item['file']['filesize'], '</p>
			</li>';
	}

	echo '
		</ul>';
}
