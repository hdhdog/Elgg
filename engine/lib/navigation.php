<?php
/**
 * Elgg navigation library
 * Functions for managing menus and other navigational elements
 *
 * Breadcrumbs
 * Elgg uses a breadcrumb stack. The page handlers (controllers in MVC terms)
 * push the breadcrumb links onto the stack. @see elgg_push_breadcrumb()
 *
 *
 * Pagination
 * Automatically handled by Elgg when using elgg_list_entities* functions.
 * @see elgg_list_entities()
 *
 *
 * Tabs
 * @see navigation/tabs view
 *
 *
 * Menus
 * Elgg uses a single interface to manage its menus. Menu items are added with
 * {@link elgg_register_menu_item()}. This is generally used for menus that
 * appear only once per page. For dynamic menus (such as the hover
 * menu for user's avatar), a plugin hook is emitted when the menu is being
 * created. The hook is 'register', 'menu:<menu_name>'. For more details on this,
 * @see elgg_view_menu().
 *
 * Menus supported by the Elgg core
 * Standard menus:
 *     site   Site navigation shown on every page.
 *     page   Page menu usually shown in a sidebar. Uses Elgg's context.
 *     topbar Topbar menu shown on every page. The default has two sections.
 *     footer Like the topbar but in the footer.
 *     extras Links about content on the page. The RSS link is added to this.
 *
 * Dynamic menus (also called just-in-time menus):
 *     user_hover  Avatar hover menu. The user entity is passed as a parameter.
 *     entity      The set of links shown in the summary of an entity.
 *     river       Links shown on river items.
 *     owner_block Links shown for a user or group in their owner block.
 *     filter      The tab filter for content (all, mine, friends)
 *     title       The buttons shown next to a content title.
 *     longtext    The links shown above the input/longtext view.
 *     login       Menu of links at bottom of login box
 *
 * @package    Elgg.Core
 * @subpackage Navigation
 */

/**
 * Register an item for an Elgg menu
 *
 * @warning Generally you should not use this in response to the plugin hook:
 * 'register', 'menu:<menu_name>'. If you do, you may end up with many incorrect
 * links on a dynamic menu.
 *
 * @warning A menu item's name must be unique per menu. If more than one menu
 * item with the same name are registered, the last menu item takes priority.
 *
 * @see elgg_view_menu() for the plugin hooks available for modifying a menu as
 * it is being rendered.
 *
 * @see ElggMenuItem::factory() is used to turn an array value of $menu_item into an
 * ElggMenuItem object.
 *
 * @param string $menu_name The name of the menu: site, page, userhover,
 *                          userprofile, groupprofile, or any custom menu
 * @param mixed  $menu_item A \ElggMenuItem object or an array of options in format:
 *                          name        => STR  Menu item identifier (required)
 *                          text        => STR  Menu item display text as HTML (required)
 *                          href        => STR  Menu item URL (required) (false for non-links.
 *                                              @warning If you disable the href the <a> tag will
 *                                              not appear, so the link_class will not apply. If you
 *                                              put <a> tags in manually through the 'text' option
 *                                              the default CSS selector .elgg-menu-$menu > li > a
 *                                              may affect formatting. Wrap in a <span> if it does.)
 *                          contexts    => ARR  Page context strings
 *                          section     => STR  Menu section identifier
 *                          title       => STR  Menu item tooltip
 *                          selected    => BOOL Is this menu item currently selected
 *                          parent_name => STR  Identifier of the parent menu item
 *                          link_class  => STR  A class or classes for the <a> tag
 *                          item_class  => STR  A class or classes for the <li> tag
 *                          deps     => STR  One or more AMD modules to require
 *
 *                          Additional options that the view output/url takes can be
 *							passed in the array. Custom options can be added by using
 *							the 'data' key with the	value being an associative array.
 *
 * @return bool False if the item could not be added
 * @since 1.8.0
 */
function elgg_register_menu_item($menu_name, $menu_item) {
	global $CONFIG;

	if (is_array($menu_item)) {
		$options = $menu_item;
		$menu_item = \ElggMenuItem::factory($options);
		if (!$menu_item) {
			$menu_item_name = elgg_extract('name', $options, 'MISSING NAME');
			elgg_log("Unable to add menu item '{$menu_item_name}' to '$menu_name' menu", 'WARNING');
			return false;
		}
	}

	if (!$menu_item instanceof ElggMenuItem) {
		elgg_log('Second argument of elgg_register_menu_item() must be an instance of ElggMenuItem or an array of menu item factory options', 'ERROR');
		return false;
	}

	if (!isset($CONFIG->menus[$menu_name])) {
		$CONFIG->menus[$menu_name] = [];
	}
	$CONFIG->menus[$menu_name][] = $menu_item;
	return true;
}

/**
 * Remove an item from a menu
 *
 * @param string $menu_name The name of the menu
 * @param string $item_name The unique identifier for this menu item
 *
 * @return \ElggMenuItem|null
 * @since 1.8.0
 */
function elgg_unregister_menu_item($menu_name, $item_name) {
	global $CONFIG;

	if (empty($CONFIG->menus[$menu_name])) {
		return null;
	}

	foreach ($CONFIG->menus[$menu_name] as $index => $menu_object) {
		/* @var \ElggMenuItem $menu_object */
		if ($menu_object instanceof ElggMenuItem && $menu_object->getName() == $item_name) {
			$item = $CONFIG->menus[$menu_name][$index];
			unset($CONFIG->menus[$menu_name][$index]);
			return $item;
		}
	}

	return null;
}

/**
 * Check if a menu item has been registered
 *
 * @param string $menu_name The name of the menu
 * @param string $item_name The unique identifier for this menu item
 *
 * @return bool
 * @since 1.8.0
 */
function elgg_is_menu_item_registered($menu_name, $item_name) {
	global $CONFIG;

	if (!isset($CONFIG->menus[$menu_name])) {
		return false;
	}

	foreach ($CONFIG->menus[$menu_name] as $menu_object) {
		/* @var \ElggMenuItem $menu_object */
		if ($menu_object->getName() == $item_name) {
			return true;
		}
	}

	return false;
}

/**
 * Get a menu item registered for a menu
 *
 * @param string $menu_name The name of the menu
 * @param string $item_name The unique identifier for this menu item
 *
 * @return ElggMenuItem|null
 * @since 1.9.0
 */
function elgg_get_menu_item($menu_name, $item_name) {
	global $CONFIG;

	if (!isset($CONFIG->menus[$menu_name])) {
		return null;
	}

	foreach ($CONFIG->menus[$menu_name] as $index => $menu_object) {
		/* @var \ElggMenuItem $menu_object */
		if ($menu_object->getName() == $item_name) {
			return $CONFIG->menus[$menu_name][$index];
		}
	}

	return null;
}

/**
 * Convenience function for registering a button to the title menu
 *
 * The URL must be $handler/$name/$guid where $guid is the guid of the page owner.
 * The label of the button is "$handler:$name" so that must be defined in a
 * language file.
 *
 * This is used primarily to support adding an add content button
 *
 * @param string $handler        The handler to use or null to autodetect from context
 * @param string $name           Name of the button (defaults to 'add')
 * @param string $entity_type    Optional entity type to be added (used to verify canWriteToContainer permission)
 * @param string $entity_subtype Optional entity subtype to be added (used to verify canWriteToContainer permission)
 * @return void
 * @since 1.8.0
 */
function elgg_register_title_button($handler = null, $name = 'add', $entity_type = 'all', $entity_subtype = 'all') {
	
	if (!$handler) {
		$handler = elgg_get_context();
	}

	$owner = elgg_get_page_owner_entity();
	if (!$owner) {
		// noone owns the page so this is probably an all site list page
		$owner = elgg_get_logged_in_user_entity();
	}
	if (!$owner || !$owner->canWriteToContainer(0, $entity_type, $entity_subtype)) {
		return;
	}

	elgg_register_menu_item('title', [
		'name' => $name,
		'href' => "$handler/$name/$owner->guid",
		'text' => elgg_echo("$handler:$name"),
		'link_class' => 'elgg-button elgg-button-action',
	]);
}

/**
 * Adds a breadcrumb to the breadcrumbs stack.
 *
 * See elgg_get_breadcrumbs() and the navigation/breadcrumbs view.
 *
 * @param string $title The title to display. During rendering this is HTML encoded.
 * @param string $link  Optional. The link for the title. During rendering links are
 *                      normalized via elgg_normalize_url().
 *
 * @return void
 * @since 1.8.0
 * @see elgg_get_breadcrumbs
 */
function elgg_push_breadcrumb($title, $link = null) {
	$breadcrumbs = (array) elgg_get_config('breadcrumbs');
	$breadcrumbs[] = ['title' => $title, 'link' => $link];
	elgg_set_config('breadcrumbs', $breadcrumbs);
}

/**
 * Removes last breadcrumb entry.
 *
 * @return array popped breadcrumb array or empty array
 * @since 1.8.0
 */
function elgg_pop_breadcrumb() {
	$breadcrumbs = (array) elgg_get_config('breadcrumbs');

	if (empty($breadcrumbs)) {
		return [];
	}

	$popped = array_pop($breadcrumbs);
	elgg_set_config('breadcrumbs', $breadcrumbs);

	return $popped;
}

/**
 * Returns all breadcrumbs as an array
 * <code>
 * [
 *    [
 *       'title' => 'Breadcrumb title',
 *       'link' => '/path/to/page',
 *    ]
 * ]
 * </code>
 *
 * Breadcrumbs are filtered through the plugin hook [prepare, breadcrumbs] before
 * being returned.
 *
 * @param array $breadcrumbs An array of breadcrumbs
 *                           If set, will override breadcrumbs in the stack
 * @return array
 * @since 1.8.0
 * @see elgg_prepare_breadcrumbs
 */
function elgg_get_breadcrumbs(array $breadcrumbs = null) {
	if (!isset($breadcrumbs)) {
		// if no crumbs set, still allow hook to populate it
		$breadcrumbs = (array) elgg_get_config('breadcrumbs');
	}

	if (!is_array($breadcrumbs)) {
		_elgg_services()->logger->error(__FUNCTION__ . ' expects breadcrumbs as an array');
		$breadcrumbs = [];
	}
	
	$params = [
		'breadcrumbs' => $breadcrumbs,
	];

	$params['identifier'] = _elgg_services()->request->getFirstUrlSegment();
	$params['segments'] = _elgg_services()->request->getUrlSegments();
	array_shift($params['segments']);

	$breadcrumbs = elgg_trigger_plugin_hook('prepare', 'breadcrumbs', $params, $breadcrumbs);
	if (!is_array($breadcrumbs)) {
		_elgg_services()->logger->error('"prepare, breadcrumbs" hook must return an array of breadcrumbs');
		return [];
	}

	return $breadcrumbs;
}

/**
 * Prepare breadcrumbs before display. This turns titles into 100-character excerpts, and also
 * removes the last crumb if it's not a link.
 *
 * @param string $hook        "prepare"
 * @param string $type        "breadcrumbs"
 * @param array  $breadcrumbs Breadcrumbs to be altered
 * @param array  $params      Hook parameters
 *
 * @return array
 * @since 1.11
 */
function elgg_prepare_breadcrumbs($hook, $type, $breadcrumbs, $params) {
	// remove last crumb if not a link
	$last_crumb = end($breadcrumbs);
	if (empty($last_crumb['link'])) {
		array_pop($breadcrumbs);
	}

	// apply excerpt to titles
	foreach (array_keys($breadcrumbs) as $i) {
		$breadcrumbs[$i]['title'] = elgg_get_excerpt($breadcrumbs[$i]['title'], 100);
	}
	return $breadcrumbs;
}

/**
 * Returns default filter tabs (All, Mine, Friends) for the user
 *
 * @param string   $context  Context to be used to prefix tab URLs
 * @param string   $selected Name of the selected tab
 * @param ElggUser $user     User who owns the layout (defaults to logged in user)
 * @param array    $vars     Additional vars
 * @return ElggMenuItem[]
 * @since 2.3
 */
function elgg_get_filter_tabs($context = null, $selected = null, ElggUser $user = null, array $vars = []) {

	if (!isset($selected)) {
		$selected = 'all';
	}

	if (!$user) {
		$user = elgg_get_logged_in_user_entity();
	}

	$items = [];
	if ($user) {
		$items[] = ElggMenuItem::factory([
			'name' => 'all',
			'text' => elgg_echo('all'),
			'href' => (isset($vars['all_link'])) ? $vars['all_link'] : "$context/all",
			'selected' => ($selected == 'all'),
			'priority' => 200,
		]);
		$items[] = ElggMenuItem::factory([
			'name' => 'mine',
			'text' => elgg_echo('mine'),
			'href' => (isset($vars['mine_link'])) ? $vars['mine_link'] : "$context/owner/{$user->username}",
			'selected' => ($selected == 'mine'),
			'priority' => 300,
		]);
	}

	$params = [
		'selected' => $selected,
		'user' => $user,
		'vars' => $vars,
	];
	$items = _elgg_services()->hooks->trigger('filter_tabs', $context, $params, $items);

	return $items;
}

/**
 * Set up the site menu
 *
 * Handles default, featured, and custom menu items
 *
 * @access private
 */
function _elgg_site_menu_setup($hook, $type, $return, $params) {

	$featured_menu_names = elgg_get_config('site_featured_menu_names');
	$custom_menu_items = elgg_get_config('site_custom_menu_items');
	if ($featured_menu_names || $custom_menu_items) {
		// we have featured or custom menu items

		$registered = $return['default'];
		/* @var \ElggMenuItem[] $registered */

		// set up featured menu items
		$featured = [];
		foreach ($featured_menu_names as $name) {
			foreach ($registered as $index => $item) {
				if ($item->getName() == $name) {
					$featured[] = $item;
					unset($registered[$index]);
				}
			}
		}

		// add custom menu items
		$n = 1;
		foreach ($custom_menu_items as $title => $url) {
			$item = new \ElggMenuItem("custom$n", $title, $url);
			$featured[] = $item;
			$n++;
		}

		$return['default'] = $featured;
		if (count($registered) > 0) {
			$return['more'] = $registered;
		}
	} else {
		// no featured menu items set
		$max_display_items = 5;

		// the first n are shown, rest added to more list
		// if only one item on more menu, stick it with the rest
		$num_menu_items = count($return['default']);
		if ($num_menu_items > ($max_display_items + 1)) {
			$return['more'] = array_splice($return['default'], $max_display_items);
		}
	}
	
	// check if we have anything selected
	$selected = false;
	foreach ($return as $section) {
		/* @var \ElggMenuItem[] $section */

		foreach ($section as $item) {
			if ($item->getSelected()) {
				$selected = true;
				break 2;
			}
		}
	}
	
	if (!$selected) {
		// nothing selected, match name to context or match url
		$current_url = current_page_url();
		foreach ($return as $section_name => $section) {
			foreach ($section as $key => $item) {
				// only highlight internal links
				if (strpos($item->getHref(), elgg_get_site_url()) === 0) {
					if ($item->getName() == elgg_get_context()) {
						$return[$section_name][$key]->setSelected(true);
						break 2;
					}
					if ($item->getHref() == $current_url) {
						$return[$section_name][$key]->setSelected(true);
						break 2;
					}
				}
			}
		}
	}

	return $return;
}

/**
 * Prepare page menu
 * Sets the display child menu option to "toggle" if not set
 * Recursively marks parents of the selected item as selected (expanded)
 *
 * @param \Elgg\Hook $hook
 * @access private
 */
function _elgg_page_menu_setup(\Elgg\Hook $hook) {
	$menu = $hook->getValue();

	foreach ($menu as $section => $menu_items) {
		foreach ($menu_items as $menu_item) {
			if ($menu_item instanceof ElggMenuItem) {
				$child_menu_vars = $menu_item->getChildMenuOptions();
				if (empty($child_menu_vars['display'])) {
					$child_menu_vars['display'] = 'toggle';
				}
				$menu_item->setChildMenuOptions($child_menu_vars);
			}
		}
	}

	$selected_item = $hook->getParam('selected_item');
	if ($selected_item instanceof \ElggMenuItem) {
		$parent = $selected_item->getParent();
		while ($parent instanceof \ElggMenuItem) {
			$parent->setSelected();
			$parent = $parent->getParent();
		}
	}

	return $menu;
}

/**
 * Add the comment and like links to river actions menu
 * @access private
 */
function _elgg_river_menu_setup($hook, $type, $return, $params) {
	if (elgg_is_logged_in()) {
		$item = $params['item'];
		/* @var \ElggRiverItem $item */
		$object = $item->getObjectEntity();
		// add comment link but annotations cannot be commented on
		if ($item->annotation_id == 0) {
			if ($object->canComment()) {
				$options = [
					'name' => 'comment',
					'href' => "#comments-add-{$object->guid}-{$item->id}",
					'text' => elgg_view_icon('speech-bubble'),
					'title' => elgg_echo('comment:this'),
					'rel' => 'toggle',
					'priority' => 50,
				];
				$return[] = \ElggMenuItem::factory($options);
			}
		}
		
		if ($item->canDelete()) {
			$options = [
				'name' => 'delete',
				'href' => elgg_add_action_tokens_to_url("action/river/delete?id={$item->id}"),
				'text' => elgg_view_icon('delete'),
				'title' => elgg_echo('river:delete'),
				'confirm' => elgg_echo('deleteconfirm'),
				'priority' => 200,
			];
			$return[] = \ElggMenuItem::factory($options);
		}
	}

	return $return;
}

/**
 * Entity menu is list of links and info on any entity
 * @access private
 */
function _elgg_entity_menu_setup($hook, $type, $return, $params) {
	if (elgg_in_context('widgets')) {
		return $return;
	}
	
	$entity = $params['entity'];
	/* @var \ElggEntity $entity */
	$handler = elgg_extract('handler', $params, false);
	
	if ($entity->canEdit() && $handler) {
		// edit link
		$options = [
			'name' => 'edit',
			'text' => elgg_echo('edit'),
			'title' => elgg_echo('edit:this'),
			'href' => "$handler/edit/{$entity->getGUID()}",
			'priority' => 200,
		];
		$return[] = \ElggMenuItem::factory($options);
	}

	if ($entity->canDelete() && $handler) {
		// delete link
		if (elgg_action_exists("$handler/delete")) {
			$action = "action/$handler/delete";
		} else {
			$action = "action/entity/delete";
		}
		$options = [
			'name' => 'delete',
			'text' => elgg_view_icon('delete'),
			'title' => elgg_echo('delete:this'),
			'href' => "$action?guid={$entity->getGUID()}",
			'confirm' => elgg_echo('deleteconfirm'),
			'priority' => 300,
		];
		$return[] = \ElggMenuItem::factory($options);
	}

	return $return;
}

/**
 * Widget menu is a set of widget controls
 * @access private
 */
function _elgg_widget_menu_setup($hook, $type, $return, $params) {

	$widget = $params['entity'];
	/* @var \ElggWidget $widget */
	$show_edit = elgg_extract('show_edit', $params, true);

	$collapse = [
		'name' => 'collapse',
		'text' => ' ',
		'href' => "#elgg-widget-content-$widget->guid",
		'link_class' => 'elgg-widget-collapse-button',
		'rel' => 'toggle',
		'priority' => 1,
	];
	$return[] = \ElggMenuItem::factory($collapse);

	if ($widget->canEdit()) {
		$delete = [
			'name' => 'delete',
			'text' => elgg_view_icon('delete-alt'),
			'title' => elgg_echo('widget:delete', [$widget->getTitle()]),
			'href' => "action/widgets/delete?widget_guid=$widget->guid",
			'is_action' => true,
			'link_class' => 'elgg-widget-delete-button',
			'id' => "elgg-widget-delete-button-$widget->guid",
			'data-elgg-widget-type' => $widget->handler,
			'priority' => 900,
		];
		$return[] = \ElggMenuItem::factory($delete);

		if ($show_edit) {
			$edit = [
				'name' => 'settings',
				'text' => elgg_view_icon('settings-alt'),
				'title' => elgg_echo('widget:edit'),
				'href' => "#widget-edit-$widget->guid",
				'link_class' => "elgg-widget-edit-button",
				'rel' => 'toggle',
				'priority' => 800,
			];
			$return[] = \ElggMenuItem::factory($edit);
		}
	}

	return $return;
}

/**
 * Add the register and forgot password links to login menu
 * @access private
 */
function _elgg_login_menu_setup($hook, $type, $return, $params) {

	if (elgg_get_config('allow_registration')) {
		$return[] = \ElggMenuItem::factory([
			'name' => 'register',
			'href' => elgg_get_registration_url(),
			'text' => elgg_echo('register'),
			'link_class' => 'registration_link',
		]);
	}

	$return[] = \ElggMenuItem::factory([
		'name' => 'forgotpassword',
		'href' => 'forgotpassword',
		'text' => elgg_echo('user:password:lost'),
		'link_class' => 'forgot_link',
	]);

	return $return;
}

/**
 * Add the RSS link to the extras menu
 * @access private
 */
function _elgg_extras_menu_setup($hook, $type, $return, $params) {

	if (!elgg_is_logged_in()) {
		return;
	}
	
	if (!_elgg_has_rss_link()) {
		return;
	}

	$url = current_page_url();
	$return[] = ElggMenuItem::factory([
		'name' => 'rss',
		'text' => elgg_echo('feed:rss'),
		'icon' => 'rss',
		'href' => elgg_http_add_url_query_elements($url, [
			'view' => 'rss',
		]),
		'title' => elgg_echo('feed:rss'),
	]);

	return $return;
}

/**
 * Navigation initialization
 * @access private
 */
function _elgg_nav_init() {
	elgg_register_plugin_hook_handler('prepare', 'breadcrumbs', 'elgg_prepare_breadcrumbs');

	elgg_register_plugin_hook_handler('prepare', 'menu:site', '_elgg_site_menu_setup');
	elgg_register_plugin_hook_handler('prepare', 'menu:page', '_elgg_page_menu_setup', 999);

	elgg_register_plugin_hook_handler('register', 'menu:river', '_elgg_river_menu_setup');
	elgg_register_plugin_hook_handler('register', 'menu:entity', '_elgg_entity_menu_setup');
	elgg_register_plugin_hook_handler('register', 'menu:widget', '_elgg_widget_menu_setup');
	elgg_register_plugin_hook_handler('register', 'menu:login', '_elgg_login_menu_setup');
	elgg_register_plugin_hook_handler('register', 'menu:extras', '_elgg_extras_menu_setup');

	elgg_register_plugin_hook_handler('public_pages', 'walled_garden', '_elgg_nav_public_pages');

	elgg_register_menu_item('footer', \ElggMenuItem::factory([
		'name' => 'powered',
		'text' => elgg_echo("elgg:powered"),
		'href' => 'http://elgg.org',
		'title' => 'Elgg ' . elgg_get_version(true),
		'section' => 'meta',
	]));

	elgg_register_ajax_view('navigation/menu/user_hover/contents');

	// Using a view extension to ensure that themes that have replaced the item view
	// still load the required AMD modules
	elgg_extend_view('navigation/menu/elements/item', 'navigation/menu/elements/item_deps');
}

/**
 * Extend public pages
 *
 * @param string   $hook_name    "public_pages"
 * @param string   $entity_type  "walled_garden"
 * @param string[] $return_value Paths accessible outside the "walled garden"
 * @param mixed    $params       unused
 *
 * @return string[]
 * @access private
 * @since 1.11.0
 */
function _elgg_nav_public_pages($hook_name, $entity_type, $return_value, $params) {
	if (is_array($return_value)) {
		$return_value[] = 'navigation/menu/user_hover/contents';
	}

	return $return_value;
}

return function(\Elgg\EventsService $events, \Elgg\HooksRegistrationService $hooks) {
	$events->registerHandler('init', 'system', '_elgg_nav_init');
};
