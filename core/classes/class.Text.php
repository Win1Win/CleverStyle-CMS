<?php
/**
 * @package		CleverStyle CMS
 * @author		Nazar Mokrynskyi <nazar@mokrynskyi.com>
 * @copyright	Copyright (c) 2011-2012, Nazar Mokrynskyi
 * @license		MIT License, see license.txt
 */
namespace cs;
class Text {
	/**
	 * Gets text on current language
	 *
	 * @param int			$database
	 * @param string		$group
	 * @param string		$label
	 * @param int			$id
	 * @param bool			$auto_translation	If <b>false</b> - automatic translation will be disabled,
	 * 											even in case, when it is enabled in system configuration
	 *
	 * @return bool|string
	 */
	function get ($database, $group, $label, $id = null, $auto_translation = true) {
		global $Cache, $L, $db, $Config;
		$id					= (int)$id;
		$cache_key			= 'texts/'.$database.'/'.($id ?: md5($group).md5($label)).'_'.$L->clang;
		if (($text = $Cache->$cache_key) !== false) {
			return $text;
		}
		if ($id) {
			$text = $db->$database->qf([
				"SELECT `d`.`id`, `d`.`lang`, `d`.`text`
				FROM `[prefix]texts` AS `t` LEFT OUTER JOIN `[prefix]texts_data` AS `d`
				ON `t`.`id` = `d`.`id`
				WHERE `t`.`id` = $id AND `d`.`lang` = '%s'
				LIMIT 1",
				$L->clang
			]);
			if (!$text) {
				$text = $db->$database->qf([
					"SELECT `d`.`id`, `d`.`lang`, `d`.`text`
					FROM `[prefix]texts` AS `t` LEFT OUTER JOIN `[prefix]texts_data` AS `d`
					ON `t`.`id` = `d`.`id`
					WHERE `t`.`id` = $id
					LIMIT 1",
					$L->clang
				]);
			}
		} else {
			$text = $db->$database->qf([
				"SELECT `t`.`id`, `d`.`lang`, `d`.`text`
				FROM `[prefix]texts` AS `t` LEFT OUTER JOIN `[prefix]texts_data` AS `d`
				ON (`d`.`id` = `d`.`id`)
				WHERE `t`.`group` = '%s' AND `t`.`label` = '%s' AND `d`.`lang` = '%s'
				LIMIT 1",
				$group,
				$label,
				$L->clang
			]);
			if (!$text) {
				$text = $db->$database->qf([
					"SELECT `t`.`id`, `d`.`lang`, `d`.`text`
					FROM `[prefix]texts` AS `t` LEFT OUTER JOIN `[prefix]texts_data` AS `d`
					ON (`d`.`id` = `d`.`id`)
					WHERE `t`.`group` = '%s' AND `t`.`label` = '%s'
					LIMIT 1",
					$group,
					$label,
					$L->clang
				]);
			}
		}
		if (!$text) {
			return false;
		}
		if ($text['lang'] != $L->clang && $auto_translation && $Config->core['multilingual'] && $Config->core['auto_translation']) {
			$engine_class	= '\\cs\\Text\\'.$Config->core['auto_translation_engine']['name'];
			$text['text']	= $engine_class::translate($text['text'], $text['lang'], $L->clang);
			$db->$database()->q(
				"INSERT INTO `[prefix]texts_data` (`id`, `lang`, `text`) VALUES ('%s', '%s', '%s')",
				$text['id'],
				$L->clang,
				$text['text']
			);
		}
		$Cache->$cache_key	= $text['text'];
		return $text['text'];
	}
	/**
	 * Sets text on current language
	 *
	 * @param int			$database
	 * @param string		$group
	 * @param string		$label
	 * @param string		$text
	 *
	 * @return bool|string				If multilingual support enabled or was enabled and then disabled but translations remains - returns {¶<i>id</i>}<br>
	 * 									otherwise returns original text
	 */
	function set ($database, $group, $label, $text) {
		global $Cache, $L, $db, $Config;
		unset($Cache->{'texts/'.$database.'/'.md5($group).md5($label).'_'.$L->clang});
		$db_object	= $db->$database();
		/**
		 * @var \cs\DB\_Abstract $db_object
		 */
		$text		= str_replace('{¶', '{&para;', $text);
		$id			= $db_object->qf(
			[
				"SELECT `id` FROM `[prefix]texts` WHERE `label` = '%s' AND `group` = '%s' LIMIT 1",
				$label,
				$group
			],
			true
		);
		if (!$id) {
			if (!$Config->core['multilingual']) {
				return $text;
			} else {
				$db_object->q(
					"INSERT INTO `[prefix]texts` (`label`, `group`) VALUES ('%s', '%s')",
					$label,
					$group
				);
				if (!($id = $db_object->id())) {
					return $text;
				}
			}
		}
		unset($Cache->{'texts/'.$database.'/'.$id.'_'.$L->clang});
		if ($dat = $db_object->qf(
			[
				"SELECT `id` FROM `[prefix]texts_data` WHERE `id` = '%s' AND `lang` = '%s' LIMIT 1",
				$id,
				$L->clang
			],
			true
		)) {
			if ($db_object->q(
				"UPDATE `[prefix]texts_data` SET `text` = '%s' WHERE `id` = '%s' AND `lang` = '%s' LIMIT 1",
				$text,
				$id,
				$L->clang
			)) {
				return '{¶'.$id.'}';
			} else {
				return $text;
			}
		} elseif ($Config->core['multilingual']) {
			if (!$db_object->q(
				"INSERT INTO `[prefix]texts_data` (`id`, `id_`, `lang`, `text`) VALUES ('%s', '%s', '%s')",
				$id,
				'{¶'.$id.'}',
				$L->clang,
				$text
			)) {
				$db_object->q("DELETE FROM `[prefix]texts` WHERE `id` = $id");
				return $text;
			}
			global $Config;
			/**
			 * Clean up old texts
			 */
			if ($id && $id % $Config->core['inserts_limit'] == 0) {
				$db_object->aq([
					"DELETE FROM `[prefix]texts` WHERE `label` = '' AND `group` = ''",
					"DELETE FROM `[prefix]texts_data` WHERE `lang` = ''"
				]);
			}
			if ($id) {
				return '{¶'.$id.'}';
			} else {
				return $text;
			}
		} else {
			return $text;
		}
	}
	/**
	 * Deletes text on all languages
	 *
	 * @param int        $database
	 * @param string     $group
	 * @param string     $label
	 *
	 * @return bool
	 */
	function del ($database, $group, $label) {
		global $Cache, $L, $db;
		unset($Cache->{'texts/'.$database.'/'.md5($group).md5($label).'_'.$L->clang});
		$id = $db->$database()->qf(
			[
				"SELECT `id` FROM `[prefix]texts` WHERE `group` = '%s' AND `label` = '%s'",
				$group,
				$label
			],
			true
		);
		if ($id) {
			unset($Cache->{'texts/'.$database.'/'.$id.'_'.$L->clang});
			return $db->$database()->q(
				[
					"UPDATE `[prefix]texts` SET `label` = '', `group` = '' WHERE `id` = '%s'",
					"UPDATE `[prefix]texts_data` SET `lang` = '', `text` = '' WHERE `id` = '%s'"
				],
				$id
			);
		} else {
			return true;
		}
	}
	/**
	 * Process text, and replace {¶([0-9]+)} on real text, is used before showing multilingual information
	 *
	 * @param int					$database
	 * @param string|string[]		$data
	 *
	 * @return bool|string|string[]
	 */
	function process ($database, $data) {
		if (empty($data)) {
			return '';
		}
		if (is_array($data)) {
			foreach ($data as &$d) {
				$d	= $this->process($database, $d);
			}
			return $data;
		}
		return preg_replace_callback(
			'/\{¶([0-9]+)\}/',
			function ($input) use ($database) {
				return $this->get($database, null, null, $input[1]);
			},
			$data
		);
	}
	/**
	 * Cloning restriction
	 *
	 * @final
	 */
	function __clone () {}
}
/**
 * For IDE
 */
if (false) {
	global $Text;
	$Text = new Text;
}