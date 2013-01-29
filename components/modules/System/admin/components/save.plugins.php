<?php
/**
 * @package		CleverStyle CMS
 * @subpackage	System module
 * @category	modules
 * @author		Nazar Mokrynskyi <nazar@mokrynskyi.com>
 * @copyright	Copyright (c) 2011-2013, Nazar Mokrynskyi
 * @license		MIT License, see license.txt
 */
/**
 * Provides next triggers:<br>
 *  admin/System/components/plugins/enable<br>
 *  ['name'	=> <i>plugin_name</i>]<br>
 *  admin/System/components/plugins/disable<br>
 *  ['name'	=> <i>plugin_name</i>]
 */
global $Config, $Index, $Core;
$rc			= $Config->route;
$plugins	= get_files_list(PLUGINS, false, 'd');
if (isset($_POST['mode'], $_POST['plugin'])) {
	switch ($_POST['mode']) {
		case 'enable':
			if (!in_array($_POST['plugin'], $Config->components['plugins']) && in_array($_POST['plugin'], $plugins)) {
				$Config->components['plugins'][] = $_POST['plugin'];
				$Index->save();
				$Core->run_trigger(
					'admin/System/components/plugins/enable',
					[
						'name' => $_POST['plugin']
					]
				);
			}
		break;
		case 'disable':
			if (in_array($_POST['plugin'], $Config->components['plugins'])) {
				foreach ($Config->components['plugins'] as $i => $plugin) {
					if ($plugin == $_POST['plugin'] || !in_array($_POST['plugin'], $plugins)) {
						unset($Config->components['plugins'][$i], $i, $plugin);
						break;
					}
				}
				unset($i, $plugin);
				$Index->save();
				$Core->run_trigger(
					'admin/System/components/plugins/disable',
					[
						'name' => $_POST['plugin']
					]
				);
			}
		break;
	}
}