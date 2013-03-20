<?php
/**
 * @package		OAuth2
 * @category	modules
 * @author		Nazar Mokrynskyi <nazar@mokrynskyi.com>
 * @copyright	Copyright (c) 2011-2013, Nazar Mokrynskyi
 * @license		MIT License, see license.txt
 */
namespace	cs\modules\OAuth2;
use			cs\DB\Accessor;
class OAuth2 extends Accessor {
	/**
	 * Returns database index
	 *
	 * @return int
	 */
	protected function cdb () {
		global $Config;
		return $Config->module(basename(__DIR__))->db('oauth2');
	}
	/**
	 * Add new client
	 *
	 * @param string	$name
	 * @param string	$domain
	 * @param int		$active
	 *
	 * @return bool|int					<i>false</i> on failure, id of created client otherwise
	 */
	function add_client ($name, $domain, $active) {
		if (
			!$domain ||
			strpos($domain, '/') !== false
		) {
			return false;
		}
		$this->db_prime()->q(
			"INSERT INTO `[prefix]oauth2_clients`
				(
					`secret`,
					`name`,
					`domain`,
					`active`
				) VALUES (
					'%s',
					'%s',
					'%s',
					'%s'
				)",
			md5(MICROTIME+uniqid('oauth2', true)),
			xap($name),
			xap($domain),
			(int)(bool)$active
		);
		$id	= $this->db_prime()->id();
		global $Cache;
		unset($Cache->{'OAuth2/'.$id});
		return $id;
	}
	/**
	 * Get client data
	 *
	 * @param int				$id
	 *
	 * @return array|bool
	 */
	function get_client ($id) {
		$id	= (int)$id;
		if (!$id) {
			return false;
		}
		global $Cache;
		if (($data = $Cache->{'OAuth2/'.$id}) === false) {
			$data	= $this->db()->qf(
				"SELECT *
				FROM `[prefix]oauth2_clients`
				WHERE `id`	= $id
				LIMIT 1"
			);
			$Cache->{'OAuth2/'.$id}	= $data;
		}
		return $data;
	}
	/**
	 * Set client data
	 *
	 * @param int		$id
	 * @param string	$secret
	 * @param string	$name
	 * @param string	$domain
	 * @param int		$active
	 *
	 * @return bool
	 */
	function set_client ($id, $secret, $name, $domain, $active) {
		$id	= (int)$id;
		if (!$id) {
			return false;
		}
		if (
			!preg_match('/^[0-9a-z]{32}$/', $secret) ||
			!$domain ||
			strpos($domain, '/') !== false
		) {
			return false;
		}
		$result	= $this->db_prime()->q(
			"UPDATE `[prefix]oauth2_clients`
			SET
				`secret`		= '%s',
				`name`			= '%s',
				`domain`		= '%s',
				`active`		= '%s'
			WHERE `id` = '%s'
			LIMIT 1",
			$secret,
			xap($name),
			xap($domain),
			(int)(bool)$active,
			$id
		);
		global $Cache;
		unset($Cache->{'OAuth2/'.$id});
		return $result;
	}
	/**
	 * Delete client
	 *
	 * @param int				$id
	 *
	 * @return bool
	 */
	function del_client ($id) {
		$id	= (int)$id;
		if (!$id) {
			return false;
		}
		$result	= $this->db_prime()->q([
			"DELETE FROM `[prefix]oauth2_clients`
			WHERE `id` = $id
			LIMIT 1",
			"DELETE FROM `[prefix]oauth2_clients_grant_access`
			WHERE `id`	= $id",
			"DELETE FROM `[prefix]oauth2_clients_sessions`
			WHERE `id`	= $id"
		]);
		global $Cache;
		unset($Cache->OAuth2);
		return $result;
	}
	/**
	 * Get clients list in form of associative array
	 *
	 * @return array|bool
	 */
	function clients_list () {
		return $this->db()->qfa(
			"SELECT *
			FROM `[prefix]oauth2_clients`"
		);
	}
	/**
	 * Grant access for specified client
	 *
	 * @param int		$client
	 *
	 * @return bool
	 */
	function add_access ($client) {
		global $User;
		$client	= (int)$client;
		if (!$User->user() || !$this->get_client($client)) {
			return false;
		}
		$result	= $this->db_prime()->q([
			"INSERT IGNORE INTO `[prefix]oauth2_clients_grant_access`
				(
					`id`,
					`user`
				) VALUES (
					'%s',
					'%s'
				)",
			$client,
			$User->id
		]);
		global $Cache;
		unset($Cache->{'OAuth2/grant_access/'.$User->id});
		return $result;
	}
	/**
	 * Check granted access for specified client
	 *
	 * @param int		$client
	 * @param bool|int	$user	If not specified - current user assumed
	 *
	 * @return bool
	 */
	function get_access ($client, $user = false) {
		global $User;
		$user	= $user ?: $User->id;
		$client	= (int)$client;
		if ($user == 1) {
			return false;
		}
		global $Cache;
		if (($data = $Cache->{'OAuth2/grant_access/'.$User->id}) === false) {
			$data	= $this->db()->qfas([
				"SELECT `id`
				FROM `[prefix]oauth2_clients_grant_access`
				WHERE `user`	= '%s'",
				$User->id
			]);
			$Cache->{'OAuth2/grant_access/'.$User->id}	= $data;
		}
		return $data ? in_array($client, $data) : false;
	}
	/**
	 * Deny access for specified client/
	 *
	 * @param int		$client	If 0 - access for all clients will be denied
	 * @param bool|int	$user	If not specified - current user assumed
	 *
	 * @return bool
	 */
	function del_access ($client = 0, $user = false) {
		global $User;
		$user	= $user ?: $User->id;
		$client	= (int)$client;
		if ($user == 1) {
			return false;
		}
		global $Cache;
		$result	= $client ? $this->db_prime()->q([
			"DELETE FROM `[prefix]oauth2_clients_grant_access`
			WHERE
				`user`	= $user AND
				`id`	= $client
			LIMIT 1",
			"DELETE FROM `[prefix]oauth2_clients_sessions`
			WHERE
				`user`	= $user AND
				`id`	= $client"
		]) : $this->db_prime()->q([
			"DELETE FROM `[prefix]oauth2_clients_grant_access`
			WHERE
				`user`	= $user",
			"DELETE FROM `[prefix]oauth2_clients_sessions`
			WHERE
				`user`	= $user"
		]);
		unset($Cache->{'OAuth2/grant_access/'.$user});
		return $result;
	}
	/**
	 * Adds new code for specified client, code is used to obtain token
	 *
	 * @param int			$client
	 * @param string		$response_type	'code' or 'token'
	 * @param string		$redirect_uri
	 *
	 * @return bool|string					<i>false</i> on failure or code for token access otherwise
	 */
	function add_code ($client, $response_type, $redirect_uri = '') {
		global $User;
		$client	= (int)$client;
		if (
			!$User->user() ||
			!$this->get_client($client) ||
			!$this->get_access($client)
		) {
			return false;
		}
		$user_agent					= $User->user_agent;
		$current_session			= $User->get_session();
		$_SERVER['HTTP_USER_AGENT']	= 'OAuth2';
		$new_session				= $User->add_session($User->id);
		$_SERVER['HTTP_USER_AGENT']	= $user_agent;
		$User->get_session($current_session);
		unset($user_agent, $current_session);
		for (
			$i = 0;
			$access_token	= md5(MICROTIME.uniqid($i, true)),
			$refresh_token	= md5($access_token.uniqid($i, true)),
			$code			= md5($refresh_token.uniqid($i, true));
			++$i
		) {
			if ($this->db_prime()->qf(
				"SELECT `id`
				FROM `[prefix]oauth2_clients_sessions`
				WHERE
					`access_token`	= '$access_token' OR
					`refresh_token`	= '$refresh_token' OR
					`code`			= '$code'
				LIMIT 1"
			)) {
				continue;
			}
			$this->db_prime()->q(
				"INSERT INTO `[prefix]oauth2_clients_sessions`
					(
						`id`,
						`user`,
						`session`,
						`created`,
						`expire`,
						`access_token`,
						`refresh_token`,
						`code`,
						`type`,
						`redirect_uri`
					) VALUES (
						'%s',
						'%s',
						'%s',
						'%s',
						'%s',
						'%s',
						'%s',
						'%s',
						'%s',
						'%s'
					)",
				$client,
				$User->id,
				$new_session,
				TIME,
				TIME + 3600,
				$access_token,
				$refresh_token,
				$code,
				$response_type,
				md5($redirect_uri)
			);
			return $code;
		}
		return false;
	}
	/**
	 * Get code data (tokens)
	 *
	 * @param string		$code
	 * @param int			$client			Client id
	 * @param string		$secret			Client secret
	 * @param string		$redirect_uri
	 *
	 * @return array|bool					<i>false</i> on failure, otherwise array
	 * 										['access_token' => md5, 'refresh_token' => md5, 'expires_in' => seconds, 'token_type' => 'bearer']<br>
	 * 										<i>expires_in</i> may be negative
	 */
	function get_code ($code, $client, $secret, $redirect_uri = '') {
		$client	= $this->get_client($client);
		if (!preg_match('/^[0-9a-z]{32}$/', $code) || !$client || $client['secret'] != $secret) {
			return false;
		}
		$data	= $this->db()->qf([
			"SELECT
				`access_token`,
				`refresh_token`,
				`expire`
			FROM `[prefix]oauth2_clients_sessions`
			WHERE
				`id`			= '%s' AND
				`code`			= '%s' AND
				`redirect_uri`	= '%s'
			LIMIT 1",
			$client['id'],
			$code,
			md5($redirect_uri)
		]);
		if (!$data) {
			return false;
		}
		return [
			'access_token'	=> $data['access_token'],
			'refresh_token'	=> $data['refresh_token'],
			'expires_in'	=> $data['expire'] - TIME,
			'token_type'	=> 'bearer'
		];
	}
	/**
	 * Get token data
	 *
	 * @param string		$access_token
	 * @param int			$client			Client id
	 * @param string		$secret			Client secret
	 *
	 * @return array|bool					<i>false</i> on failure, array ['user' => id, 'session' => id, 'expire' => unix time, 'type' => 'code'|'token']
	 */
	function get_token ($access_token, $client, $secret) {
		$client	= $this->get_client($client);
		if (!preg_match('/^[0-9a-z]{32}$/', $access_token) || !$client || $client['secret'] != $secret) {
			return false;
		}
		global $Cache;
		if (($data = $Cache->{'OAuth2/tokens/'.$access_token}) === false) {
			$data	= $this->db()->qf([
				"SELECT
					`user`,
					`session`,
					`expire`,
					`type`
				FROM `[prefix]oauth2_clients_sessions`
				WHERE
					`id`			= '%s' AND
					`access_token`	= '%s'
				LIMIT 1",
				$client['id'],
				$access_token
			]);
			$Cache->{'OAuth2/tokens/'.$access_token}	= $data;
		}
		if ($data && !$this->get_access($client['id'], $data['user'])) {
			$this->db()->q([
				"DELETE FROM `[prefix]oauth2_clients_sessions`
				WHERE
					`id`			= '%s' AND
					`access_token`	= '%s'
				LIMIT 1",
				$client['id'],
				$access_token
			]);
			unset($Cache->{'OAuth2/tokens/'.$access_token});
			$data	= false;
		}
		return $data;
	}
	/**
	 * Get new access_token with refresh_token
	 *
	 * @param string		$refresh_token
	 * @param int			$client			Client id
	 * @param string		$secret			Client secret
	 *
	 * @return array|bool					<i>false</i> on failure,
	 * 										otherwise array ['access_token' => md5, 'refresh_token' => md5, 'expires_in' => seconds, 'token_type' => 'bearer']
	 */
	function refresh_token ($refresh_token, $client, $secret) {
		$client	= $this->get_client($client);
		if (!preg_match('/^[0-9a-z]{32}$/', $refresh_token) || !$client || $client['secret'] != $secret) {
			return false;
		}
		$data	= $this->db_prime()->qf([
			"SELECT
				`access_token`,
				`session`
			FROM `[prefix]oauth2_clients_sessions`
			WHERE
				`id`			= '%s' AND
				`refresh_token`	= '%s'
			LIMIT 1",
			$client['id'],
			$refresh_token
		]);
		global $Cache;
		$this->db_prime()->q(
			"DELETE FROM `[prefix]oauth2_clients_sessions`
			WHERE
				`id`			= '%s' AND
				`refresh_token`	= '%s'
			LIMIT 1",
			$client['id'],
			$refresh_token
		);
		unset($Cache->{'OAuth2/tokens/'.$data['access_token']});
		if (!$data) {
			return false;
		}
		global $User;
		$id	= $User->get_session_user($data['session']);
		if ($id == 1) {
			return false;
		}
		$User->add_session($id);
		$result	= $this->get_code($this->add_code($client['id'], 'code'), $client['id'], $client['secret']);
		$User->del_session();
		return $result;
	}
}
if (false) {
	global $OAuth2;
	$OAuth2	= new OAuth2;
}