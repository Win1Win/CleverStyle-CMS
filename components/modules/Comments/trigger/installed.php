<?php
/**
 * @package		Comments
 * @category	modules
 * @author		Nazar Mokrynskyi <nazar@mokrynskyi.com>
 * @copyright	Copyright (c) 2011-2014, Nazar Mokrynskyi
 * @license		MIT License, see license.txt
 */
namespace	cs\modules\Comments;
use			cs\Cache,
			cs\User,
			cs\Trigger;
Trigger::instance()->register(
	'admin/System/components/modules/uninstall/process',
	function ($data) {
		if ($data['name'] != 'Comments' || !User::instance()->admin()) {
			return;
		}
		time_limit_pause();
		unset(Cache::instance()->Comments);
		time_limit_pause(false);
	}
);
