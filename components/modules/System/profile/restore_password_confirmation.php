<?php
/**
 * @package		CleverStyle CMS
 * @subpackage	System module
 * @category	modules
 * @author		Nazar Mokrynskyi <nazar@mokrynskyi.com>
 * @copyright	Copyright (c) 2011-2012, Nazar Mokrynskyi
 * @license		MIT License, see license.txt
 */
global $Config, $L, $User, $Page, $Mail;
if (isset($_COOKIE['restore_password_confirm'])) {
	_setcookie('restore_password_confirm', '');
	$Page->title($L->restore_password_success_title);
	$Page->notice($L->restore_password_success);
	return;
} elseif (!$User->guest()) {
	$Page->title($L->you_are_already_registered_title);
	$Page->warning($L->you_are_already_registered);
	return;
} elseif (!isset($Config->routing['current'][2])) {
	$Page->title($L->invalid_confirmation_code);
	$Page->warning($L->invalid_confirmation_code);
	return;
}
$result = $User->restore_password_confirmation($Config->routing['current'][2]);
if ($result === false) {
	$Page->title($L->invalid_confirmation_code);
	$Page->warning($L->invalid_confirmation_code);
	return;
}
if ($Mail->send_to(
	$User->get('email', $result['id']),
	$L->restore_password_success_mail(get_core_ml_text('name')),
	$L->restore_password_success_mail_body(
		$User->get_username($result['id']),
		get_core_ml_text('name'),
		$Config->core['base_url'].'/profile/'.$User->get('login', $result['id']),
		$User->get('login', $result['id']),
		$result['password']
	)
)) {
	_setcookie('restore_password_confirm', 1);
	header('Location: '.$Config->server['base_url'].'/'.MODULE.'/profile/restore_password_confirmation');
} else {
	$Page->title($L->sending_reg_mail_error_title);
	$Page->warning($L->sending_reg_mail_error);
}