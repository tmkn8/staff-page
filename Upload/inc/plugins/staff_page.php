<?php
/**
 * Staff Page pre-alpha 0.1
 * Author: mrnu <mrnuu@icloud.com>
 *
 * Website: https://github.com/mrnu
 * License: http://opensource.org/licenses/MIT
 *
 */

// Disallow direct access to this file for security reasons
if(!defined('IN_MYBB'))
{
	die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
}

// Pre-load templates
if(my_strpos($_SERVER['PHP_SELF'], 'memberlist.php') && strtolower($_GET['action']) == 'staff')
{
	global $templatelist;

	if(isset($templatelist))
	{
		$templatelist .= ',';
	}

	$templatelist .= 'staff_page,staff_page_group_row,staff_page_member_row,staff_page_no_groups,staff_page_no_members,staff_page_user_avatar,postbit_pm,postbit_email';
}

// Public hooks
$plugins->add_hook('memberlist_start', 'staff_page_memberlist');

// Admin CP hooks
$plugins->add_hook('admin_config_menu', 'staff_page_admin_config_menu');
$plugins->add_hook('admin_config_action_handler', 'staff_page_admin_config_action_handler');
$plugins->add_hook('admin_config_permissions', 'staff_page_admin_config_permissions');
$plugins->add_hook('admin_load', 'staff_page_admin');

function staff_page_info()
{
	return array(
		'name'			=> 'Staff Page',
		'description'	=> 'A plugin adds a page, which displays a list of the staff members. The list content can be managed and description of users can be added.',
		'website'		=> 'http://github.com/mrnu/staff-page',
		'author'		=> 'mrnu',
		'authorsite'	=> 'http://github.com/mrnu',
		'version'		=> 'pre-alpha 0.1',
		'guid' 			=> '',
		'compatibility' => '18*'
	);
}

/**
 * Code hooked to memberlist_start.
 * Display generated staff page.
 *
 */
function staff_page_memberlist()
{
	// Only for testing purposes.
	recache_staff_groups();

	global $mybb, $lang;

	// Check if the staff page were requested - memberlist.php?action=staff.
	if(strtolower($mybb->input['action']) == 'staff')
	{
		$lang->load('staff_page');

		add_breadcrumb($lang->staff, 'memberlist.php?action=staff');
		$staff_page_template = display_staff_page();
		output_page($staff_page_template);
		exit();
	}
}


/**
 * Function which generates the staff page.
 *
 * @return string Staff page template.
 */
function display_staff_page()
{
	global $db, $lang, $theme, $templates, $plugins, $mybb, $cache;
	global $header, $headerinclude, $footer;

	$members = get_staff_members($mybb->input['group_id'] ? $mybb->input['group_id'] : 0);
	$members = sort_members_by_group_id($members);
	$groups = get_staff_groups();

	if(count($groups))
	{
		$groups_rows = '';

		foreach($groups as $group)
		{
			// Reset alt_trow()
			$reset = 1;

			if(count($members[$group['id']]))
			{
				// Initialize parser
				require_once MYBB_ROOT.'inc/class_parser.php';
				$parser = new postParser;
				$parser_options = array(
					'allow_html' => 0,
					'allow_mycode' => 1,
					'allow_smilies' => 1,
					'allow_imgcode' => 1,
					'allow_videocode' => 0,
					'filter_badwords' => 0
				);

				$members_rows = '';

				foreach($members[$group['id']] as $member)
				{
					// Get MyBB user details and format it
					$user = get_user($member['user_id']);
					$user['formatted_name'] = format_name($user['username'], $user['usergroup'], $user['displaygroup']);
					$user['profilelink'] = build_profile_link($user['formatted_name'], $user['uid']);

					// Parse member's description
					$description = $parser->parse_message($member['description'], $parser_options);

					// Show "Send email" link
					$emailcode = '';

					if($user['hideemail'] != 1)
					{
						eval("\$emailcode = \"".$templates->get("postbit_email")."\";");
					}

					// Show "Send PM" link
					$pmcode = '';

					if($user['receivepms'] != 0 && $mybb->settings['enablepms'] != 0 && my_strpos(','.$user['ignorelist'].',', ','.$mybb->user['uid'].',') === false)
					{
						$post['uid'] = $user['uid'];
						eval("\$pmcode = \"".$templates->get("postbit_pm")."\";");
					}

					// Show avatar
					$useravatar = format_avatar(htmlspecialchars_uni($user['avatar']), $user['avatardimensions'], my_strtolower($mybb->settings['staff_page_maxavatarsize']));
					eval("\$user['avatar'] = \"".$templates->get("staff_page_user_avatar")."\";");

					// Alternate rows.
					$bgcolor = alt_trow($reset);

					// Don't reset alt_trow()
					$reset = 0;

					// Output member row template
					eval('$members_rows .= "'.$templates->get('staff_page_member_row').'";');


				}
			}
			else
			{
				eval('$members_rows = "'.$templates->get('staff_page_no_members').'";');
			}

			eval('$groups_rows .= "'.$templates->get('staff_page_group_row').'";');
		}
	}
	else
	{
		eval('$groups_rows .= "'.$templates->get('staff_page_no_groups').'";');
	}


	eval('$template = "'.$templates->get('staff_page').'";');
	return $template;
}


/**
 * Get members of staff.
 * @param int $group_id Group ID.
 *
 * @return array Members list.
 */
function get_staff_members($group_id = 0)
{
	global $db;

	$members = array();

	$query = $db->simple_select('staff_page_members', '*', $group_id ? ('group_id = ' . intval($group_id)) : '1' );

	if($db->num_rows($query))
	{
		while($row = $db->fetch_array($query))
		{
			$members[] = $row;
		}
	}

	return $members;
}

/**
 * Update the staff groups cache.
 *
 */
function recache_staff_groups()
{
	global $db, $cache;

	$query = $db->simple_select('staff_page_groups', '*', '1', array('order_by' => '`order`', 'order_dir' => 'asc'));

	$groups = array();

	if($db->num_rows($query))
	{
		while($row = $db->fetch_array($query))
		{
			$groups[] = $row;
		}
	}

	$cache->update('staff_page_groups', $groups);
}

/**
 * Get the staff groups from cachestore.
 *
 * @return array List of staff groups.
 */
function get_staff_groups()
{
	global $cache;

	$groups = $cache->read('staff_page_groups');

	if(!is_array($groups))
	{
		return array();
	}

	return $groups;
}

/**
 * Sort members array by group ID.
 * Adds group ID as a main key.
 *
 * @return array
 */
function sort_members_by_group_id($members_array)
{
	if(!count($members_array))
	{
		return array();
	}

	$new_array = array();

	foreach($members_array as $row)
	{
		$new_array[$row['group_id']][] = $row;
	}

	return $new_array;
}

/**
 *
 */
function staff_page_admin_config_menu($sub_menu)
{
	global $lang;

	$lang->load('staff_page');

	$sub_menu[] = array('id' => 'staff_page', 'title' => $lang->staff_page, 'link' => 'index.php?module=config-staff_page');

	return $sub_menu;
}

/**
 *
 */
function staff_page_admin_config_action_handler($actions)
{
	$actions['staff_page'] = array('active' => 'staff_page', 'file' => 'staff_page');

	return $actions;
}

/**
 *
 */
function staff_page_admin_config_permissions($admin_permissions)
{
	global $lang;

	$lang->load('staff_page');

	$admin_permissions['staff_page'] = $lang->staff_page_admin_permission;

	return $admin_permissions;
}

/**
*
*/
function staff_page_admin()
{
	global $db, $lang, $mybb, $page, $run_module, $action_file;

	if($run_module == 'config' && $action_file == 'staff_page')
	{
		$lang->load('staff_page');

		$page->add_breadcrumb_item($lang->staff_page, 'index.php?module=config-staff_page');

		$sub_tabs['manage_staff_page'] = array(
			'title'       => $lang->staff_page,
			'link'        => 'index.php?module=config-staff_page',
			'description' => $lang->staff_page_description
		);

		$sub_tabs['add_member'] = array(
			'title' => $lang->add_member,
			'link'  => 'index.php?module=config-staff_page&amp;action=add_member',
			'description' => $lang->add_member_description
		);

		$sub_tabs['add_group'] = array(
			'title' => $lang->add_group,
			'link'  => 'index.php?module=config-staff_page&amp;action=add_group',
			'description'	=>	$lang->add_group_description
		);

		if (! $mybb->input['action'])
		{
			$page->output_header($lang->staff_page);
			$page->output_nav_tabs($sub_tabs, 'manage_staff_page');

			$table = new Table;
			$table->construct_header($lang->name);
			$table->construct_header($lang->order);
			$table->construct_header($lang->action, array('class' => "align_center", 'colspan' => 2));

			$members = get_staff_members();
			$members = sort_members_by_group_id($members);
			$groups = get_staff_groups();

			if(count($groups))
			{
				foreach($groups as $group)
				{
					$table->construct_cell('<div class="largetext"><strong>'.$group['name'].'</strong></div><div class="smalltext">'.$group['description'].'</div>');
					$table->construct_cell($group['order']);
					$table->construct_cell("<a href=\"index.php?module=config-staff_page&amp;action=edit_group&amp;uid={$group['id']}\">{$lang->edit}</a>");
					$table->construct_cell("<a href=\"index.php?module=config-staff_page&amp;action=delete_group&amp;uid={$group['id']}\">{$lang->delete}</a>");
					$table->construct_row();

					if(count($members[$group['id']]))
					{
						foreach($members[$group['id']] as $member)
						{
							$user = get_user($member['user_id']);
							$user['formatted_name'] = format_name($user['username'], $user['usergroup'], $user['displaygroup']);

							$table->construct_cell('<div style="padding-left: 40px;" class="largetext">'.$user['formatted_name'].'</div><div class="smalltext" style="padding-left: 50px;">'.$member['description'].'</div>', array('colspan'  => 2));
							$table->construct_cell("<a href=\"index.php?module=config-staff_page&amp;action=edit_member&amp;uid={$member['id']}\">{$lang->edit}</a>");
							$table->construct_cell("<a href=\"index.php?module=config-staff_page&amp;action=delete_member&amp;uid={$member['id']}\">{$lang->delete}</a>");
							$table->construct_row();
						}
					}
				}
			}
			else
			{
				$table->construct_cell($lang->no_groups, array('colspan' => 4));
				$table->construct_row();
			}

			$table->output($lang->staff_page);

			$page->output_footer();
			exit();
		}

		if ($mybb->input['action'] == 'add_group')
		{
			$page->output_header($lang->staff_page.' - '.$lang->add_group);
			$page->output_nav_tabs($sub_tabs, 'add_group');
			$page->add_breadcrumb_item($lang->add_group);

			if($mybb->request_method == 'post')
			{
				if(!trim($mybb->input['name']))
				{
					$errors[] = $lang->empty_name;
				}

				if(!$errors)
				{
					$insert_array = array(
						'name'       => $db->escape_string($mybb->input['name']),
						'description' => $db->escape_string($mybb->input['description'])
					);

					$db->insert_query('staff_page_groups', $insert_array);

					recache_staff_groups();

					admin_redirect('index.php?module=config-staff_page');
				}
			}

			if($errors)
			{
				$page->output_inline_error($errors);
			}

			$form = new Form('index.php?module=config-staff_page&amp;action=add_group', 'post', 'add');
			$form_container = new FormContainer($lang->add_group);
			$form_container->output_row($lang->name.'<em>*</em>', '', $form->generate_text_box('name', $mybb->input['name']));
			$form_container->output_row($lang->description, '', $form->generate_text_box('description', $mybb->input['description']));
			$form_container->end();

			$buttons[] = $form->generate_submit_button($lang->save);

			$form->output_submit_wrapper($buttons);

			$form->end();

			$page->output_footer();
			exit();
		}

		if ($mybb->input['action'] == 'add_member')
		{
			$page->output_header($lang->staff_page.' - '.$lang->add_member);
			echo "<script type=\"text/javascript\" src=\"jscripts/users.js\"></script>";
			$page->output_nav_tabs($sub_tabs, 'add_member');
			$page->add_breadcrumb_item($lang->add_member);

			$groups = get_staff_groups();

			if($mybb->request_method == 'post')
			{
				// Check if chosen group exists
				$i = 0;

				foreach($groups as $group)
				{
					if($group['id'] == $mybb->input['group_id'])
					{
						$i++;
						break;
					}
				}

				if(!$i)
				{
					$errors[] = $lang->wrong_group;
				}

				// Check if chosen user exists
				if($mybb->input['name'])
				{
					$query = $db->simple_select('users', 'uid', 'username = \''.$db->escape_string($mybb->input['name']).'\'');
					$user = $db->fetch_array($query);
				}
				else
				{
					$user = array('uid' => 0);
				}

				if(!$user['uid'])
				{
					$errors[] = $lang->user_not_exist;
				}

				// Insert member
				if(!$errors)
				{
					$insert_array = array(
						'user_id'	=>	$user['uid'],
						'group_id'	=>	intval($mybb->input['group_id'])
					);

					$db->insert_query('staff_page_members', $insert_array);

					admin_redirect('index.php?module=config-staff_page');
				}
			}

			if($errors)
			{
				$page->output_inline_error($errors);
			}

			// Prepare groups array to be a select list
			$groups_select = array();

			foreach($groups as $group)
			{
				$groups_select[$group['id']] = $group['name'];
			}

			// Generate a form
			$form = new Form('index.php?module=config-staff_page&amp;action=add_member', 'post', 'add');
			$form_container = new FormContainer($lang->add_member);
			$form_container->output_row($lang->name, '', $form->generate_text_box('name', $mybb->input['name']));
			$form_container->output_row($lang->group, '', $form->generate_select_box('group_id', $groups_select, $mybb->input['group_id'], array('id' => 'group_id')));
			$form_container->end();

			$buttons[] = $form->generate_submit_button($lang->save);

			$form->output_submit_wrapper($buttons);

			$form->end();

			$page->output_footer();
			exit();
		}
	}
}

/**
*
*/
function staff_page_is_installed()
{
	global $db;

	if($db->table_exists('staff_page_groups'))
 	{
  		return true;
	}

	return false;
}

/**
*
*/
function staff_page_uninstall()
{
	global $db;

	// Delete DB schema
	$db->drop_table('staff_page_members');
	$db->drop_table('staff_page_groups');
}

/**
*
*/
function staff_page_install()
{
	global $db;

	// Create DB schema
	$db->query("CREATE TABLE IF NOT EXISTS " . TABLE_PREFIX . "staff_page_members (
					`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
					`user_id` int(11) DEFAULT NULL,
					`group_id` int(11) DEFAULT NULL,
					`description` text,
					PRIMARY KEY (`id`)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;");

	$db->query("CREATE TABLE IF NOT EXISTS " . TABLE_PREFIX . "staff_page_groups (
					`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
					`name` varchar(256) DEFAULT NULL,
					`order` tinyint(127) NOT NULL DEFAULT '0',
					`description` varchar(256) DEFAULT NULL,
					PRIMARY KEY (`id`)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;");
}

/**
*
*/
function staff_page_deactivate()
{
	global $db;

	// Delete cache
	$db->delete_query('datacache', 'title = \'staff_page_groups\'');

	// Delete templates
	// $db->delete_query('templates', 'title IN ('.$templates_names.')');
}

/**
*
*/
function staff_page_activate()
{
	global $db;

	// Recache groups
	recache_staff_groups();

	// Install templates
}