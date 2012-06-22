<?php
/**
 * Provides next triggers:<br>
 *  System/profile/settings
 */
global $Core, $Config, $L, $User, $Page, $Index;
if (!$User->is('user')) {
	define('ERROR_PAGE', 403);
	$Page->error_page();
	return;
}
$columns = [
	'login',
	'username',
	'language',
	'theme',
	'timezone',
	'gender',
	'birthday',
	'avatar',
	'website',
	'icq',
	'skype',
	'about'
];
if (isset($_POST['user']) && $_POST['edit_settings'] == 'save') {
	$user_data = &$_POST['user'];
	foreach ($user_data as $item => &$value) {
		if (in_array($item, $columns) && $item != 'data') {
			$value = xap($value, false);
		}
	}
	unset($item, $value);
	if ($_POST['user']['birthday'] < TIME) {
		$birthday				= $user_data['birthday'];
		$birthday				= explode('-', $birthday);
		$user_data['birthday']	= mktime(
			0,
			0,
			0,
			$birthday[1],
			$birthday[2],
			$birthday[0]
		);
		unset($birthday);
	} else {
		$user_data['block_until']	= 0;
	}
	if (
		$user_data['login'] &&
		$user_data['login'] != $User->get('login') &&
		(
			(
				!filter_var($user_data['login'], FILTER_VALIDATE_EMAIL) &&
				$User->get_id(hash('sha224', $user_data['login'])) === false
			) ||
			$user_data['login'] == $User->get('email')
		)
	) {
		$user_data['login_hash'] = hash('sha224', $user_data['login']);
	} else {
		if ($user_data['login'] != $User->get('login')) {
			$Page->warning($L->login_occupied_or_is_not_valid);
		}
		unset($user_data['login']);
	}
	if ($user_data['theme']) {
		$theme = _json_decode($user_data['theme']);
		if (!(
			in_array($theme['theme'], $Config->core['active_themes']) &&
			in_array($theme['color_scheme'], $Config->core['color_schemes'][$theme['theme']])
		)) {
			unset($user_data['theme']);
		}
		unset($theme);
	} else {
		unset($user_data['theme']);
	}
	$Index->save($User->set($user_data));
	unset($user_data);
}
$Page->title($L->my_profile);
$Page->title($L->settings);
switch (isset($Config->routing['current'][2]) ? $Config->routing['current'][2] : '') {
	default:
		$Page->content(
			h::{'a.cs-button'}(
				$L->general,
				[
					'href'	=> $Index->action.'/general'
				]
			)
		);
		$Core->run_trigger('System/profile/settings');
		break;
	case 'general':
		$Page->title($L->general);
		$user_data					= $User->get($columns);
		unset($columns);
		$timezones					= get_timezones_list();
		$row						= function ($col1, $col2) {
			return	h::{'th.ui-widget-header.ui-corner-all'}($col1).
				h::{'td.ui-widget-content.ui-corner-all'}($col2);
		};
		$themes						= [
			$L->system_default.' ('.$Config->core['theme'].' - '.$Config->core['color_scheme'].')' => ''
		];
		foreach ($Config->core['active_themes'] as $theme) {
			foreach ($Config->core['color_schemes'][$theme] as $color_scheme) {
				$themes[$theme.' - '.$color_scheme] = _json_encode([
					'theme'			=> $theme,
					'color_scheme'	=> $color_scheme
				]);
			}
		}
		unset($theme, $color_scheme);
		$Index->form				= true;
		$Index->apply_button		= false;
		$Index->cancel_button_back	= true;
		$Page->title($L->settings);
		$Index->content(
			h::{'table#users_edit.cs-fullwidth-table.cs-center-all tr'}([
				$row($L->login, h::{'input.cs-form-element'}([
					'name'		=> 'user[login]',
					'value'		=> $user_data['login']
				])),

				$row($L->username, h::{'input.cs-form-element'}([
					'name'	=> 'user[username]',
					'value'	=> $user_data['username']
				])),

				$row($L->language, h::{'select.cs-form-element'}(
					[
						'in'		=> array_merge([$L->system_default.' ('.$Config->core['language'].')'], $Config->core['active_languages']),
						'value'		=> array_merge([''], $Config->core['active_languages'])
					],
					[
						'name'		=> 'user[language]',
						'selected'	=> $user_data['language'],
						'size'		=> 5
					]
				)),

				$row($L->theme, h::{'select.cs-form-element'}(
					[
						'in'		=> array_keys($themes),
						'value'		=> array_values($themes)
					],
					[
						'name'		=> 'user[theme]',
						'selected'	=> $user_data['theme'],
						'size'		=> 5
					]
				)),

				$row($L->timezone, h::{'select.cs-form-element'}(
					[
						'in'		=> array_merge([$L->system_default.' ('.$Config->core['timezone'].')'], array_keys($timezones)),
						'value'		=> array_merge([''], array_values($timezones))
					],
					[
						'name'		=> 'user[timezone]',
						'selected'	=> $user_data['timezone'],
						'size'		=> 5
					]
				)),

				$row($L->gender, h::{'input.cs-form-element[type=radio]'}([
					'name'		=> 'user[gender]',
					'checked'	=> $user_data['gender'],
					'value'		=> [-1, 0, 1],
					'in'		=> [$L->undefined, $L->male, $L->female]
				])),

				$row(h::info('birthday'), h::{'input.cs-form-element[type=date]'}([
					'name'		=> 'user[birthday]',
					'value'		=> date('Y-m-d', $user_data['birthday'] ?: TIME)
				])),

				$row($L->avatar, h::{'input.cs-form-element'}([
					'name'		=> 'user[avatar]',
					'value'		=> $user_data['avatar']
				])),

				$row($L->website, h::{'input.cs-form-element'}([
					'name'		=> 'user[website]',
					'value'		=> $user_data['website']
				])),

				$row($L->icq, h::{'input.cs-form-element'}([
					'name'		=> 'user[icq]',
					'value'		=> $user_data['icq'] ?: ''
				])),

				$row($L->skype, h::{'input.cs-form-element'}([
					'name'		=> 'user[skype]',
					'value'		=> $user_data['skype']
				])),

				$row($L->about_myself, h::{'textarea.cs-form-element'}(
					$user_data['about'],
					[
						'name'		=> 'user[about]',
					]
				))
			])
		);
	break;
}