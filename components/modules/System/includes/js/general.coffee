###*
 * @package		CleverStyle CMS
 * @author		Nazar Mokrynskyi <nazar@mokrynskyi.com>
 * @copyright	Copyright (c) 2011-2014, Nazar Mokrynskyi
 * @license		MIT License, see license.txt
###
$ ->
	cs.async_call [
		->
			if cs.in_admin
				$('.cs-reload-button').click ->
					location.reload()
				$('#change_active_languages').change ->
					$(@)
						.find("option[value='" + $('#change_language').val() + "']")
						.prop('selected', true)
				$('#cs-system-license-open').click ->
					$('#cs-system-license').cs().modal('show')
				$('.cs-permissions-invert').click ->
					$(@)
						.parentsUntil('div')
						.find(':radio:not(:checked)[value!=-1]')
						.prop('checked', true)
						.change()
				$('.cs-permissions-allow-all').click ->
					$(@)
						.parentsUntil('div')
						.find(':radio[value=1]')
						.prop('checked', true)
						.change()
				$('.cs-permissions-deny-all').click ->
					$(@)
						.parentsUntil('div')
						.find(':radio[value=0]')
						.prop('checked', true)
						.change()
				$('#cs-users-search-columns li').click ->
					$this = $(@)
					if $this.hasClass('uk-button-primary')
						$this.removeClass('uk-button-primary')
					else
						$this.addClass('uk-button-primary')
					$('#cs-users-search-selected-columns').val(
						$this.parent().children('.uk-button-primary')
							.map ->
								$.trim(@.innerHTML)
							.get()
							.join(';')
					)
				$('#block_users_search')
					.keyup (event) ->
						if event.which != 13
							return
						$('.cs-block-users-changed')
							.removeClass('cs-block-users-changed')
							.appendTo('#cs-block-users-changed-permissions')
							.each ->
								id		= $(@).find(':radio:first').attr('name')
								found	= $('#cs-block-users-search-found')
								found.val(
									found.val() + ',' + id.substring(6, id.length-1)
								)
						$.ajax
							url		: "api/System/admin/blocks_search_users"
							data	:
								found_users		: $('#cs-block-users-search-found').val()
								permission		: $(@).attr('permission')
								search_phrase	: $(@).val()
							type	: 'get'
							success	: (result) ->
								$('#block_users_search_results')
									.html(result)
									.find(':radio')
									.change ->
										$(@)
											.parentsUntil('tr')
											.parent()
											.addClass('cs-block-users-changed')
					.keydown (event) ->
						event.which != 13
				$('#cs-top-blocks-items, #cs-left-blocks-items, #cs-floating-blocks-items, #cs-right-blocks-items, #cs-bottom-blocks-items')
					.sortable
						connectWith	: '.cs-blocks-items'
						items		: 'li:not(:first)'
					.on(
						'sortupdate'
						->
							$('#cs-blocks-position').val(
								JSON.stringify(
									top			: $('#cs-top-blocks-items li:not(:first)')
										.map ->
											$(@).data('id')
										.get()
									left		: $('#cs-left-blocks-items li:not(:first)')
										.map ->
											$(@).data('id')
										.get()
									floating	: $('#cs-floating-blocks-items li:not(:first)')
										.map ->
											$(@).data('id')
										.get()
									right		: $('#cs-right-blocks-items li:not(:first)')
										.map ->
											$(@).data('id')
										.get()
									bottom		: $('#cs-bottom-blocks-items li:not(:first)')
										.map ->
											$(@).data('id')
										.get()
								)
							)
					)
				$('#cs-users-groups-list, #cs-users-groups-list-selected')
					.sortable
						connectWith	: '#cs-users-groups-list, #cs-users-groups-list-selected'
						items		: 'li:not(:first)'
					.on(
						'sortupdate'
						->
							$('#cs-users-groups-list')
								.find('.uk-alert-success')
								.removeClass('uk-alert-success')
								.addClass('uk-alert-warning')
							selected	= $('#cs-users-groups-list-selected')
							selected
								.find('.uk-alert-warning')
								.removeClass('uk-alert-warning')
								.addClass('uk-alert-success')
							$('#cs-user-groups').val(
								JSON.stringify(
									selected
										.children('li:not(:first)')
										.map ->
											$(@).data('id')
										.get()
								)
							)
					)
	]
	return
