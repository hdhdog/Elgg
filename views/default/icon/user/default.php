<?php
/**
 * Elgg user icon
 *
 * Rounded avatar corners - CSS3 method
 * uses avatar as background image so we can clip it with border-radius in supported browsers
 *
 * @uses $vars['entity']     The user entity. If none specified, the current user is assumed.
 * @uses $vars['size']       The size - tiny, small, medium or large. (medium)
 * @uses $vars['use_hover']  Display the hover menu? (true)
 * @uses $vars['use_link']   Wrap a link around image? (true)
 * @uses $vars['class']      Optional class added to the .elgg-avatar div
 * @uses $vars['img_class']  Optional CSS class added to img
 * @uses $vars['link_class'] Optional CSS class for the link
 * @uses $vars['href']       Optional override of the link href
 */

$user = elgg_extract('entity', $vars, elgg_get_logged_in_user_entity());
$size = elgg_extract('size', $vars, 'medium');

if (!($user instanceof ElggUser)) {
	return;
}

$icon_sizes = elgg_get_icon_sizes('user');
if (!array_key_exists($size, $icon_sizes)) {
	$size = 'medium';
}

$name = htmlspecialchars($user->name, ENT_QUOTES, 'UTF-8', false);
$username = $user->username;

$class = "elgg-avatar elgg-avatar-$size";
if (isset($vars['class'])) {
	$class = "$class {$vars['class']}";
}
if ($user->isBanned()) {
	$class .= ' elgg-state-banned';
	$banned_text = elgg_echo('banned');
	$name .= " ($banned_text)";
}

$use_link = elgg_extract('use_link', $vars, true);

$icontime = $user->icontime;
if (!$icontime) {
	$icontime = "default";
}

$img_class = '';
if (isset($vars['img_class'])) {
	$img_class = $vars['img_class'];
}


$use_hover = elgg_extract('use_hover', $vars, true);
if (isset($vars['hover'])) {
	// only 1.8.0 was released with 'hover' as the key
	$use_hover = $vars['hover'];
}

$icon = elgg_view('output/img', [
	'src' => $user->getIconURL($size),
	'alt' => $name,
	'title' => $name,
	'class' => $img_class,
]);

$show_menu = $use_hover && (elgg_is_admin_logged_in() || !$user->isBanned());

?>
<div class="<?php echo $class; ?>">
<?php

if ($show_menu) {
	$params = [
		'entity' => $user,
		'username' => $username,
		'name' => $name,
	];
	echo elgg_view_icon('hover-menu');
	echo elgg_view('navigation/menu/user_hover/placeholder', ['entity' => $user]);
}

if ($use_link) {
	$class = elgg_extract('link_class', $vars, '');
	$url = elgg_extract('href', $vars, $user->getURL());
	echo elgg_view('output/url', [
		'href' => $url,
		'text' => $icon,
		'is_trusted' => true,
		'class' => $class,
	]);
} else {
	echo "<a>$icon</a>";
}
?>
</div>
