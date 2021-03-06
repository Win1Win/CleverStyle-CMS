<?php
/**
 * @package		Static Pages
 * @category	modules
 * @author		Nazar Mokrynskyi <nazar@mokrynskyi.com>
 * @copyright	Copyright (c) 2011-2014, Nazar Mokrynskyi
 * @license		MIT License, see license.txt
 */
namespace	cs\modules\Static_pages;
use			h,
			cs\Config,
			cs\Index,
			cs\Language,
			cs\Page;
$Config						= Config::instance();
$Index						= Index::instance();
$L							= Language::instance();
Page::instance()->title($L->addition_of_page_category);
$Index->apply_button		= false;
$Index->cancel_button_back	= true;
$Index->action				= 'admin/Static_pages';
$Index->content(
	h::{'h2.cs-center'}(
		$L->addition_of_page_category
	).
	h::{'cs-table[center] cs-table-row| cs-table-cell'}(
		[
			$L->parent_category,
			$L->category_title,
			h::info('category_path')
		],
		[
			h::{'select[name=parent][size=5]'}(
				get_categories_list(),
				[
					'selected'	=> isset($Config->route[1]) ? (int)$Config->route[1] : 0
				]
			),
			h::{'input[name=title]'}(),
			h::{'input[name=path]'}()
		]
	).
	h::{'input[type=hidden][name=mode][value=add_category]'}()
);
