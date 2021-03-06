###*
 * @package		CleverStyle CMS
 * @author		Nazar Mokrynskyi <nazar@mokrynskyi.com>
 * @copyright	Copyright (c) 2014, Nazar Mokrynskyi
 * @license		MIT License, see license.txt
###
###
 # Load configuration from special template elements
###
Language	= cs?.Language
[].forEach.call(
	document.head.querySelectorAll('.cs-config')
	(config) ->
		target		= config.getAttribute('target').split('.')
		destination	= window
		last_part	= null
		target.forEach (target_part) ->
			if target_part != 'window' && last_part
				if !destination[last_part]
					destination[last_part]	= {}
				destination	= destination[last_part]
			last_part	= target_part
		destination[last_part]	= JSON.parse(
			config.innerHTML.substring(4, config.innerHTML.length - 3).replace('-  ', '-', 'g')
		)
)
if Language
	cs.Language	= Language
