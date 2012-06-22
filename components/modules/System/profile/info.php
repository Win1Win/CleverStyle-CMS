<?php
/**
 * Provides next triggers:<br>
 *  System/profile/info<code>
 *  [
 *   'id'	=> <i>user_id</i><br>
 *  ]</code>
 */
global $Core, $Config, $L, $User, $Page;
if (!isset($Config->routing['current'][2]) || !($id = $User->get_id(hash('sha224', $Config->routing['current'][2])))) {
	define('ERROR_PAGE', 404);
	$Page->error_page();
	return;
}
$data	= $User->get(
	[
		'username',
		'login',
		'reg_date',
		'status',
		'block_until',
		'last_login',
		'gender',
		'birthday',
		'avatar',
		'website',
		'icq',
		'skype',
		'about'
	],
	$id
);
$state	= '';
if ($data['status'] == -1) {
	define('ERROR_PAGE', 404);
	$Page->error_page();
	return;
} elseif ($data['status'] == 0) {
	$state	= h::tr([
		$L->account_disabled
	]);
} elseif ($data['block_until'] > TIME) {
	$state	= h::tr([
		$L->account_temporarily_blocked
	]);
}
$name	= $data['username'] ? $data['username'].($data['username'] != $data['login'] ? ' aka '.$data['login'] : '') : $data['login'];
$Page->title($L->profile_of_user($name));
$Page->content(
	h::{'table.cs-fullwidth-table.cs-profile-table tr'}([
		h::{'td.cs-profile-avatar[rowspan=2] img'}([
			'src'	=> $data['avatar'] ? h::url($data['avatar']) : '/includes/img/guest.gif',
			'alt'	=> $name,
			'title'	=> $name
		]).
		h::{'td h1'}(
			$L->profile_of_user($name)
		),

		h::{'td table.cs-right-odd.cs-left-even tr'}([
			($data['birthday'] ? h::td([
				h::h2($L->birth_date.':'),
				h::h2($L->to_locale(date($L->birth_date_format, $data['birthday'])))
			])  : false),

			($data['gender'] != -1 ? h::td([
				h::h2($L->gender.':'),
				h::h2($L->{$data['gender'] == 0 ? 'male' : 'female'})
			]) : false),

			($data['website'] ? h::td([
				h::h2($L->website.':'),
				h::{'h2 a'}(
					$data['website'],
					[
						'href'	=> (substr($data['website'], 0, 4) != 'http' ? 'http://' : '').$data['website']
					]
				)
			]) : false),

			($data['icq'] ? h::td([
				h::h2($L->icq.':'),
				h::h2($data['icq'])
			]) : false),

			($data['skype'] ? h::td([
				h::h2($L->skype.':'),
				h::{'h2 a'}(
					$data['skype'],
					[
						'href'	=> 'skype:'.$data['skype']
					]
				)
			]) : false),

			($data['reg_date'] ? h::td([
				h::h2($L->reg_date.':'),
				h::h2($L->to_locale(date($L->reg_date_format, $data['reg_date'])))
			])  : false),

			($data['about'] ? h::td([
				h::h2($L->about_myself.':'),
				h::h2(str_replace("\n", h::br(), $data['about']))
			]) : false)
		])
	])
);
$Core->run_trigger(
	'System/profile/info',
	[
		'id'	=> $id
	]
);