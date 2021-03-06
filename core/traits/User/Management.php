<?php
/**
 * @package		CleverStyle CMS
 * @author		Nazar Mokrynskyi <nazar@mokrynskyi.com>
 * @copyright	Copyright (c) 2011-2014, Nazar Mokrynskyi
 * @license		MIT License, see license.txt
 */
namespace	cs\User;
use
	cs\Config,
	cs\Core,
	cs\Trigger,
	cs\User;

/**
 * Trait that contains all methods from <i>>cs\User</i> for working with user management (creation, modification, deletion)
 *
 * @property int				$id
 * @property \cs\Cache\Prefix	$cache
 * @property string				$ip
 */
trait Management {
	/**
	 * User id after registration
	 * @var int
	 */
	protected	$reg_id			= 0;
	/**
	 * Search keyword in login, username and email
	 *
	 * @param string		$search_phrase
	 *
	 * @return int[]|bool
	 */
	function search_users ($search_phrase) {
		$search_phrase = trim($search_phrase, "%\n");
		$found_users = $this->db()->qfas([
			"SELECT `id`
			FROM `[prefix]users`
			WHERE
				(
					`login`		LIKE '%s' OR
					`username`	LIKE '%s' OR
					`email`		LIKE '%s'
				) AND
				`status` != '%s'",
			$search_phrase,
			$search_phrase,
			$search_phrase,
			User::STATUS_NOT_ACTIVATED
		]);
		return $found_users;
	}
	/**
	 * User registration
	 *
	 * @param string 				$email
	 * @param bool					$confirmation	If <b>true</b> - default system option is used, if <b>false</b> - registration will be
	 *												finished without necessity of confirmation, independently from default system option
	 *												(is used for manual registration).
	 * @param bool					$auto_sign_in	If <b>false</b> - no auto sign in, if <b>true</b> - according to system configuration
	 *
	 * @return array|bool|string					<b>exists</b>	- if user with such email is already registered<br>
	 * 												<b>error</b>	- if error occurred<br>
	 * 												<b>false</b>	- if email is incorrect<br>
	 * 												<b>array(<br>
	 * 												&nbsp;'reg_key'		=> *,</b>	//Registration confirmation key, or <b>true</b>
	 * 																					if confirmation is not required<br>
	 * 												&nbsp;<b>'password'	=> *,</b>	//Automatically generated password<br>
	 * 												&nbsp;<b>'id'		=> *</b>	//Id of registered user in DB<br>
	 * 												<b>)</b>
	 */
	function registration ($email, $confirmation = true, $auto_sign_in = true) {
		$email			= mb_strtolower($email);
		if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			return false;
		}
		$this->delete_unconfirmed_users();
		if (!Trigger::instance()->run(
			'System/User/registration/before',
			[
				'email'	=> $email
			]
		)) {
			return false;
		}
		$email_hash		= hash('sha224', $email);
		$login			= strstr($email, '@', true);
		$login_hash		= hash('sha224', $login);
		if ($login && in_array($login, file_get_json(MODULES.'/System/index.json')['profile']) || $this->get_id($login_hash) !== false) {
			$login		= $email;
			$login_hash	= $email_hash;
		}
		if ($this->db_prime()->qf([
			"SELECT `id`
			FROM `[prefix]users`
			WHERE `email_hash` = '%s'
			LIMIT 1",
			$email_hash
		])) {
			return 'exists';
		}
		$Config			= Config::instance();
		$password		= password_generate($Config->core['password_min_length'], $Config->core['password_min_strength']);
		$reg_key		= md5($password.$this->ip);
		$confirmation	= $confirmation && $Config->core['require_registration_confirmation'];
		if ($this->db_prime()->q(
			"INSERT INTO `[prefix]users` (
				`login`,
				`login_hash`,
				`email`,
				`email_hash`,
				`reg_date`,
				`reg_ip`,
				`reg_key`,
				`status`
			) VALUES (
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s'
			)",
			$login,
			$login_hash,
			$email,
			$email_hash,
			TIME,
			ip2hex($this->ip),
			$reg_key,
			!$confirmation ? 1 : -1
		)) {
			$this->reg_id = $this->db_prime()->id();
			$this->set_password($password, $this->reg_id);
			if (!$confirmation) {
				$this->set_groups([User::USER_GROUP_ID], $this->reg_id);
			}
			if (!$confirmation && $auto_sign_in && $Config->core['auto_sign_in_after_registration']) {
				$this->add_session($this->reg_id);
			}
			if (!Trigger::instance()->run(
				'System/User/registration/after',
				[
					'id'	=> $this->reg_id
				]
			)) {
				$this->registration_cancel();
				return false;
			}
			if (!$confirmation) {
				$this->set_groups([User::USER_GROUP_ID], $this->reg_id);
			}
			unset($this->cache->$login_hash);
			return [
				'reg_key'	=> !$confirmation ? true : $reg_key,
				'password'	=> $password,
				'id'		=> $this->reg_id
			];
		} else {
			return 'error';
		}
	}
	/**
	 * Confirmation of registration process
	 *
	 * @param string		$reg_key
	 *
	 * @return array|bool				array('id' => <i>id</i>, 'email' => <i>email</i>, 'password' => <i>password</i>) or <b>false</b> on failure
	 */
	function registration_confirmation ($reg_key) {
		if (!is_md5($reg_key)) {
			return false;
		}
		if (!Trigger::instance()->run(
			'System/User/registration/confirmation/before',
			[
				'reg_key'	=> $reg_key
			]
		)) {
			$this->registration_cancel();
			return false;
		}
		$this->delete_unconfirmed_users();
		$data			= $this->db_prime()->qf([
			"SELECT
				`id`,
				`login_hash`,
				`email`
			FROM `[prefix]users`
			WHERE
				`reg_key`	= '%s' AND
				`status`	= '%s'
			LIMIT 1",
			$reg_key,
			User::STATUS_NOT_ACTIVATED
		]);
		if (!$data) {
			return false;
		}
		$this->reg_id	= $data['id'];
		$Config			= Config::instance();
		$password		= password_generate($Config->core['password_min_length'], $Config->core['password_min_strength']);
		$this->set_password($password, $this->reg_id);
		$this->set('status', User::STATUS_ACTIVE, $this->reg_id);
		$this->set_groups([User::USER_GROUP_ID], $this->reg_id);
		$this->add_session($this->reg_id);
		if (!Trigger::instance()->run(
			'System/User/registration/confirmation/after',
			[
				'id'	=> $this->reg_id
			]
		)) {
			$this->registration_cancel();
			return false;
		}
		unset($this->cache->{$data['login_hash']});
		return [
			'id'		=> $this->reg_id,
			'email'		=> $data['email'],
			'password'	=> $password
		];
	}
	/**
	 * Canceling of bad/failed registration
	 */
	function registration_cancel () {
		if ($this->reg_id == 0) {
			return;
		}
		$this->add_session(User::GUEST_ID);
		$this->del_user($this->reg_id);
		$this->reg_id = 0;
	}
	/**
	 * Checks for unconfirmed registrations and deletes expired
	 */
	protected function delete_unconfirmed_users () {
		$reg_date		= TIME - Config::instance()->core['registration_confirmation_time'] * 86400;	//1 day = 86400 seconds
		$ids			= $this->db_prime()->qfas([
			"SELECT `id`
			FROM `[prefix]users`
			WHERE
				`last_sign_in`	= 0 AND
				`status`		= '%s' AND
				`reg_date`		< $reg_date",
			User::STATUS_NOT_ACTIVATED
		]);
		$this->del_user($ids);

	}
	/**
	 * Proper password setting without any need to deal with low-level implementation
	 *
	 * @param string	$new_password
	 * @param bool		$user
	 * @param bool		$already_prepared	If true - assumed that `sha512(sha512(password) + public_key)` was applied to password
	 *
	 * @return bool
	 */
	function set_password ($new_password, $user = false, $already_prepared = false) {
		$public_key	= Core::instance()->public_key;
		if (!$already_prepared) {
			$new_password	=  hash('sha512', hash('sha512', $new_password).$public_key);
		}
		/**
		 * Do not allow to set password to empty
		 */
		if ($new_password == hash('sha512', hash('sha512', '').$public_key)) {
			return false;
		}
		return $this->set('password_hash', password_hash($new_password, PASSWORD_DEFAULT), $user);
	}
	/**
	 * Proper password validation without any need to deal with low-level implementation
	 *
	 * @param string	$password
	 * @param bool		$user
	 * @param bool		$already_prepared	If true - assumed that `sha512(sha512(password) + public_key)` was applied to password
	 *
	 * @return bool
	 */
	function validate_password ($password, $user = false, $already_prepared = false) {
		if (!$already_prepared) {
			$password	=  hash('sha512', hash('sha512', $password).Core::instance()->public_key);
		}
		$user			= (int)$user ?: $this->id;
		$password_hash	= $this->get('password_hash', $user);
		/**
		 * TODO This is fallback for smooth upgrade of old passwords, will be removed in future
		 */
		if (password_get_info($password_hash)['algo'] === 0 && $password == $password_hash) {
			$current_user_id	= $this->id;
			$this->set_password($password, $user, true);
			if ($current_user_id == $user) {
				$this->add_session($current_user_id);
			}
			return true;
		}
		if (!password_verify($password, $password_hash)) {
			return false;
		}
		/**
		 * Rehash password if needed
		 */
		if (password_needs_rehash($password_hash, PASSWORD_DEFAULT)) {
			$current_user_id	= $this->id;
			$this->set_password($password, $user, true);
			if ($current_user_id == $user) {
				$this->add_session($current_user_id);
			}
		}
		return true;
	}
	/**
	 * Restoring of password
	 *
	 * @param int			$user
	 *
	 * @return bool|string			Key for confirmation or <b>false</b> on failure
	 */
	function restore_password ($user) {
		if ($user && $user != User::GUEST_ID) {
			$reg_key		= md5(MICROTIME.$this->ip);
			if ($this->set('reg_key', $reg_key, $user)) {
				$data					= $this->get('data', $user);
				$data['restore_until']	= TIME + Config::instance()->core['registration_confirmation_time'] * 86400;
				if ($this->set('data', $data, $user)) {
					return $reg_key;
				}
			}
		}
		return false;
	}
	/**
	 * Confirmation of password restoring process
	 *
	 * @param string		$key
	 *
	 * @return array|bool			array('id' => <i>id</i>, 'password' => <i>password</i>) or <b>false</b> on failure
	 */
	function restore_password_confirmation ($key) {
		if (!is_md5($key)) {
			return false;
		}
		$id			= $this->db_prime()->qfs([
			"SELECT `id`
			FROM `[prefix]users`
			WHERE
				`reg_key`	= '%s' AND
				`status`	= '%s'
			LIMIT 1",
			$key,
			User::STATUS_ACTIVE
		]);
		if (!$id) {
			return false;
		}
		$data		= $this->get('data', $id);
		if (!isset($data['restore_until'])) {
			return false;
		} elseif ($data['restore_until'] < TIME) {
			unset($data['restore_until']);
			$this->set('data', $data, $id);
			return false;
		}
		unset($data['restore_until']);
		$Config		= Config::instance();
		$password	= password_generate($Config->core['password_min_length'], $Config->core['password_min_strength']);
		$this->set_password($password, $id);
		$this->set('data', $data, $id);
		$this->add_session($id);
		return [
			'id'		=> $id,
			'password'	=> $password
		];
	}
	/**
	 * Delete specified user or array of users
	 *
	 * @param int|int[]	$user	User id or array of users ids
	 */
	function del_user ($user) {
		$this->del_user_internal($user);
	}
	/**
	 * Delete specified user or array of users
	 *
	 * @param int|int[]	$user
	 * @param bool		$update
	 */
	protected function del_user_internal ($user, $update = true) {
		$Cache	= $this->cache;
		Trigger::instance()->run(
			'System/User/del/before',
			[
				'id'	=> $user
			]
		);
		if (is_array($user)) {
			foreach ($user as $id) {
				$this->del_user_internal($id, false);
			}
			$user = implode(',', $user);
			$this->db_prime()->q(
				"DELETE FROM `[prefix]users`
				WHERE `id` IN ($user)"
			);
			unset($Cache->{'/'});
			return;
		}
		$user = (int)$user;
		if (!$user) {
			return;
		}
		$this->set_groups([], $user);
		$this->del_permissions_all($user);
		if ($update) {
			unset(
				$Cache->{hash('sha224', $this->get('login', $user))},
				$Cache->$user
			);
			$this->db_prime()->q(
				"DELETE FROM `[prefix]users`
				WHERE `id` = $user
				LIMIT 1"
			);
			Trigger::instance()->run(
				'System/User/del/after',
				[
					'id'	=> $user
				]
			);
		}
	}
	/**
	 * Add bot
	 *
	 * @param string	$name		Bot name
	 * @param string	$user_agent	User Agent string or regular expression
	 * @param string	$ip			IP string or regular expression
	 *
	 * @return bool|int				Bot <b>id</b> in DB or <b>false</b> on failure
	 */
	function add_bot ($name, $user_agent, $ip) {
		if ($this->db_prime()->q(
			"INSERT INTO `[prefix]users`
				(
					`username`,
					`login`,
					`email`,
					`status`
				) VALUES (
					'%s',
					'%s',
					'%s',
					'%s'
				)",
			xap($name),
			xap($user_agent),
			xap($ip),
			User::STATUS_ACTIVE
		)) {
			$id	= $this->db_prime()->id();
			$this->set_groups([User::BOT_GROUP_ID], $id);
			Trigger::instance()->run(
				'System/User/add_bot',
				[
					'id'	=> $id
				]
			);
			return $id;
		} else {
			return false;
		}
	}
	/**
	 * Set bot
	 *
	 * @param int		$id			Bot id
	 * @param string	$name		Bot name
	 * @param string	$user_agent	User Agent string or regular expression
	 * @param string	$ip			IP string or regular expression
	 *
	 * @return bool
	 */
	function set_bot ($id, $name, $user_agent, $ip) {
		$result	= $this->set(
			[
				'username'	=> $name,
				'login'		=> $user_agent,
				'email'		=> $ip
			],
			'',
			$id
		);
		unset($this->cache->bots);
		return $result;
	}
	/**
	 * Delete specified bot or array of bots
	 *
	 * @param int|int[]	$bot	Bot id or array of bots ids
	 */
	function del_bot ($bot) {
		$this->del_user($bot);
		unset($this->cache->bots);
	}
	/**
	 * Returns array of user id, that are associated as contacts
	 *
	 * @param	bool|int	$user	If not specified - current user assumed
	 *
	 * @return	int[]				Array of user id
	 */
	function get_contacts ($user = false) {
		$user = (int)$user ?: $this->id;
		if (!$user || $user == User::GUEST_ID) {
			return [];
		}
		$contacts	= [];
		Trigger::instance()->run(
			'System/User/get_contacts',
			[
				'id'		=> $user,
				'contacts'	=> &$contacts
			]
		);
		return array_unique($contacts);
	}
}
