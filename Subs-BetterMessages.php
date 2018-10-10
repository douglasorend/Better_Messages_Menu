<?php
/**********************************************************************************
* Subs-BetterMessages.php - Subs of the Lazy Admin Menu mod
*********************************************************************************
* This program is distributed in the hope that it is and will be useful, but
* WITHOUT ANY WARRANTIES; without even any implied warranty of MERCHANTABILITY
* or FITNESS FOR A PARTICULAR PURPOSE,
**********************************************************************************/
if (!defined('SMF'))
	die('Hacking attempt...');

/**********************************************************************************
* Better Profile Menu hook
**********************************************************************************/
function BetterMessages_Verify_User()
{
	if (isset($_GET['action']) && $_GET['action'] == 'pm' && isset($_GET['sa']) && $_GET['sa'] == 'BetterMessages_ucp')
		return isset($_GET['u']) ? (int) $_GET['u'] : 0;
}

function BetterMessages_Load_Theme()
{
	// This admin hook must be last hook executed!
	add_integration_function('integrate_menu_buttons', 'BetterMessages_Menu_Buttons', false);
}

function BetterMessages_Hook()
{
	global $user_info, $scripturl, $txt, $context;

	// Keep from triggering the Forum Hard Hit mod:
	if (!empty($context['HHP_time']))
		unset($_SESSION['HHP_Visits'][$context['HHP_time']]);
			
	// Rebuild the PM menu:
	$myPM_areas = $context['pm_areas'];
	$cached = array();
	foreach ($myPM_areas as $id1 => $area1)
	{
		// Build first level menu:
		$cached[$id1] = array(
			'title' => $area1['title'],
			'show' => true,
			'sub_buttons' => array(),
		);
		$first = $last = false;
		if (isset($area1['custom_url']) && !empty($area1['custom_url']))
			$first = $cached[$id1]['href'] = $area1['custom_url'];
			
		// Build second level menus:
		foreach ($area1['areas'] as $id2 => $area2)
		{
			if (empty($area2['label']))
				continue;
			if (!$first)
				$first = $cached[$id1]['href'] = $scripturl . '?action=profile;area=' . $id2;

			// Add the entry into the custom menu we're building:
			$link = isset($area2['custom_url']) ? $area2['custom_url'] : $scripturl . '?action=pm;area=' . $id2;
			$show = (!isset($area2['enabled']) || $area2['enabled']) && (empty($area2['permission']['own']) || (!empty($area2['permission']['own']) && allowedTo($area2['permission']['own'])));
			$cached[$id1]['sub_buttons'][$last = $id2] = array(
				'title' => $area2['label'],
				'href' => $link,
				'show' => $show,
			);

			// Let's add the "Show Posts" area to the menu under "Show Topics":
			if ($id2 == 'showposts')
				$cached[$id1]['sub_buttons'][$last = 'showtopics'] = array(
					'title' => $area2['label'] . ': ' . $txt['topics'],
					'href' => $link . ';sa=topics',
					'show' => $show,
				);
		}
		$cached[$id1]['sub_buttons'][$last]['is_last'] = true;
	}

	// Cache the menu we just built for the calling user:
	$func = function_exists('safe_serialize') ? 'safe_serialize' : 'serialize';
	echo $func($cached);
	exit;
}

function BetterMessages_Menu_Buttons(&$areas)
{
	global $txt, $scripturl, $user_info;

	// Gotta prevent an infinite loop here:
	if (isset($_GET['action']) && $_GET['action'] == 'pm' && isset($_GET['area']) && $_GET['area'] == 'BetterMessages_ucp')
		return;

	// Are you a guest, can't send PMs, or mod turned off?  Then why bother?
	if (empty($user_info['id']) || !allowedTo('pm_read') || empty($user_info['bmm_mode']))
		return;

	// Attempt to get the cached Messages menu:
	$MyPM = &$areas['pm'];
	if (!empty($user_info['new_pm']) || ($cached = cache_get_data('BetterMessages_' . $user_info['id'], 86400)) == null)
	{
		// Force the profile code to build our new My Messages menu:
		$contents = @file_get_contents($scripturl . '?action=pm;sa=BetterMessages_ucp;u=' . $user_info['id']);
		if (substr($contents, 0, 2) == 'a:')
		{
			$func = function_exists('safe_unserialize') ? 'safe_unserialize' : 'unserialize';
			$cached = @$func($contents);
			cache_put_data('BetterMessages_' . $user_info['id'], $cached, 86400 * 7);
		}
	}
	if (is_array($cached))
	{
		if ($user_info['bmm_mode'] == 2)
			$MyPM['href'] = '#" onclick="return false;';
		$MyPM['sub_buttons'] = $cached;
	}
}

function BetterMessages_Profile(&$profile_fields)
{
	global $txt, $user_info;

	$profile_fields['bmm_mode'] = array(
		'type' => 'select',
		'label' => $txt['bmm_mode'],
		'options' => 'global $txt; return array(0 => $txt["bmm_disabled"], 1 => $txt["bmm_enabled"], 2 => $txt["bmm_enabled_no_click"]);',
		'permission' => 'profile_extra',
		'value' => $user_info['bmm_mode'],
	);
}

?>