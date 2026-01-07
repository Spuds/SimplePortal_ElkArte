<?php

/**
 * @package SimplePortal
 *
 * @author SimplePortal Team
 * @copyright 2015-2026 SimplePortal Team
 * @license BSD 3-clause
 * @version 2.0.0
 */

use ElkArte\MembersList;
use ElkArte\User;

/**
 * User info block, shows avatar, group, icons, posts, karma, etc
 *
 * @param array $parameters not used in this block
 * @param int $id - not used in this block
 * @param bool $return_parameters if true returns the configuration options for the block
 */
class UserInfoBlock extends SPAbstractBlock
{
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
		global $context, $scripturl, $txt, $color_profile;

		$this->data['session_id'] = $context['session_id'];
		$this->data['session_var'] = $context['session_var'];

		if (isset($context['login_token_var']))
		{
			$this->data['tokens'] = [
				'login_var' => $context['login_token_var'],
				'login' => $context['login_token']
			];
		}

		$this->data['username'] = !empty(User::$info->username) ? User::$info->username : '';
		$this->data['urls'] = [
			'pm' => $scripturl . '?action=pm',
			'login' => $scripturl . '?action=login2;quicklogin',
			'profile' => $scripturl . '?action=profile',
			'logout' => $scripturl . '?action=logout;' . $context['session_var'] . '=' . $context['session_id'],
			'unread' => $scripturl . '?action=unread',
			'unreadreplies' => $scripturl . '?action=unreadreplies',
		];

		// Give them an opportunity to logon
		if (User::$info->is_guest)
		{
			loadJavascriptFile('sha256.js');
			$this->data['is_guest'] = true;
			$this->data['disable_login_hashing'] = !empty($context['disable_login_hashing']);
			$this->data['welcome_msg'] = replaceBasicActionUrl($txt[$context['can_register'] ? 'welcome_guest_register' : 'welcome_guest']);
		}
		else
		{
			$this->data['is_guest'] = false;
			$context['admin_features'] = $context['admin_features'] ?? [];

			// load up the members details
			MembersList::load(User::$info->id);
			$member = MembersList::get(User::$info->id);
			$member->loadContext();

			$this->data['member_info'] = $member;
			$this->data['member_info']['can_pm'] = allowedTo('pm_read');
			if ($this->data['member_info']['can_pm'])
			{
				$this->data['member_info']['pm'] = [
					'messages' => User::$info->messages,
					'unread' => User::$info->unread_messages,
				];
			}

			if (sp_loadColors($this->data['member_info']['id']) !== false)
			{
				$this->data['member_info']['colored_name'] = $color_profile[$this->data['member_info']['id']]['colored_name'];
			}
			else
			{
				$this->data['member_info']['colored_name'] = $this->data['member_info']['name'];
			}

			// What do they like?
			if (!empty($this->_modSettings['likes_enabled']))
			{
				$this->data['show_likes'] = true;
			}
			else
			{
				$this->data['show_likes'] = false;
			}

			// Is Karma available
			if (!empty($this->_modSettings['karmaMode']))
			{
				$this->data['show_karma'] = true;
				$karma = $this->data['member_info']['karma'];
				$karma['total'] = $karma['good'] - $karma['bad'];
				$this->data['member_info']['karma'] = $karma;
				$this->data['karma_label'] = $this->_modSettings['karmaLabel'];

				if ($this->_modSettings['karmaMode'] == 1)
				{
					$this->data['karma_value'] = $this->data['member_info']['karma']['total'];
				}
				elseif ($this->_modSettings['karmaMode'] == 2)
				{
					$this->data['karma_value'] = '+' . $this->data['member_info']['karma']['good'] . '/-' . $this->data['member_info']['karma']['bad'];
				}
			}
			else
			{
				$this->data['show_karma'] = false;
			}


			$this->data['member_info']['display_group'] = !empty($this->data['member_info']['group']) ? $this->data['member_info']['group'] : $this->data['member_info']['post_group'];
		}

		$this->setTemplate('template_sp_userInfo');
	}
}

/**
 * Main template for this block
 *
 * @param array $data
 */
function template_sp_userInfo($data)
{
	global $txt, $scripturl;

	echo '
		<div class="centertext">';

	// Show the guests a login area
	if ($data['is_guest'])
	{
		echo '
			<form action="', $data['urls']['login'], '" method="post" accept-charset="UTF-8"', empty($data['disable_login_hashing']) ? ' onsubmit="hashLoginPassword(this, \'' . $data['session_id'] . '\');"' : '', ' >
			<table>
					<tr>
						<td class="righttext">
							<label for="sp_user">', $txt['username'], ':</label>&nbsp;
						</td>
						<td>
							<input type="text" autocomplete="off" id="sp_user" name="user" value="', $data['username'], '" />
						</td>
					</tr>
					<tr>
						<td class="righttext">
							<label for="sp_passwrd">', $txt['password'], ':</label>&nbsp;
						</td>
						<td>
							<input type="password" name="passwrd" id="sp_passwrd" />
						</td>
					</tr>
					<tr>
						<td>
							<label for="cookielength">
								<select id="cookielength" name="cookielength">
									<option value="60">', $txt['one_hour'], '</option>
									<option value="1440">', $txt['one_day'], '</option>
									<option value="10080">', $txt['one_week'], '</option>
									<option value="43200">', $txt['one_month'], '</option>
									<option value="-1" selected="selected">', $txt['forever'], '</option>
								</select>
							</label>
						</td>
						<td>
							<input type="submit" value="', $txt['login'], '" class="button_submit" />
						</td>
					</tr>
				</table>
				<input type="hidden" name="hash_passwrd" value="" />
				<input type="hidden" name="old_hash_passwrd" value="" />
				<input type="hidden" name="', $data['session_var'], '" value="', $data['session_id'], '" />
				<input type="hidden" name="', $data['tokens']['login_var'], '" value="', $data['tokens']['login'], '" />
			</form>', $data['welcome_msg'];
	}
	// A logged in member then
	else
	{
		echo '
			', $txt['hello_member'], ' <strong>', $data['member_info']['colored_name'], '</strong>
			<br /><br />';

		if (!empty($data['member_info']['avatar']['image']))
		{
			echo '
			<a href="', $data['urls']['profile'], '">', $data['member_info']['avatar']['image'], '</a>
			<br /><br />';
		}

		echo '
			', $data['member_info']['display_group'], '<br />
			', $data['member_info']['group_icons'], '
			<br />
			<br />
			<ul class="sp_list">
				<li ', sp_embed_class('dot'), '>
					<strong>', $txt['posts'], ':</strong> ', $data['member_info']['posts'], '
				</li>';

		if ($data['show_karma'])
		{
			echo '
				<li ', sp_embed_class('dot'), '>
					<strong>', $data['karma_label'], '</strong> ', $data['karma_value'], '
				</li>';
		}

		if ($data['show_likes'])
		{
			echo '
				<li ', sp_embed_class('dot'), '>
					<strong>', $txt['likes'], ': </strong><a href="', $scripturl, '?action=profile;area=showlikes;sa=given;u=', User::$info->id, '">', $data['member_info']['likes']['given'], ' <span ', sp_embed_class('given'), '></span></a> / <a href="', $scripturl, '?action=profile;area=showlikes;sa=received;u=', User::$info->id, '">', $data['member_info']['likes']['received'], ' <span ', sp_embed_class('received'), '></span></a>
				</li>';
		}

		if ($data['member_info']['can_pm'])
		{
			echo '
				<li ', sp_embed_class('dot'), '>
					<strong>', $txt['sp-usertmessage'], ': </strong><a href="', $data['urls']['pm'], '">', $data['member_info']['pm']['messages'], '</a>
				</li>
				<li ', sp_embed_class('dot'), '>
					<strong>', $txt['sp-usernmessage'], ': </strong> ', $data['member_info']['pm']['unread'], '
				</li>';
		}

		echo '
				<li ', sp_embed_class('dot'), '>
					<a href="', $data['urls']['unread'], '">', $txt['unread_topics_visit'], '</a>
				</li>
				<li ', sp_embed_class('dot'), '>
					<a href="', $data['urls']['unreadreplies'], '">', $txt['unread_replies'], '</a>
				</li>
			</ul>
			<br />
			<a class="dot arrow" href="', $data['urls']['profile'], '">', $txt['profile'], '</a>
			<a class="dot arrow" href="', $data['urls']['logout'], '">', $txt['logout'], '</a>';
	}

	echo '
		</div>';
}
