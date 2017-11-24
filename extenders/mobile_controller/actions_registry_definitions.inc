<?php
/**
 * Mobile Controller Action Definitions Extender
 * Registers actions for the mobile controller.
 *
 * @package    BardCanvas
 * @subpackage mobile_posts
 * @author     Alejandro Caballero - lava.caballero@gmail.com
 */

use hng2_modules\mobile_controller\action;

global $modules, $manifest;

if( empty($modules["posts"]) ) return;
if( ! $modules["posts"]->enabled ) return;

$manifest->actionsRegistry["posts:set_as_draft"] = new action(array(
    "id"          => "posts:set_as_draft",
    "module_name" => "posts",
    "script_url"  => "{$modules["posts"]->get_url(true)}/scripts/toolbox.php?action=change_status&new_status=draft",
));

$manifest->actionsRegistry["posts:flag_for_review"] = new action(array(
    "id"          => "posts:flag_for_review",
    "module_name" => "posts",
    "script_url"  => "{$modules["posts"]->get_url(true)}/scripts/toolbox.php?action=change_status&new_status=reviewing",
));

$manifest->actionsRegistry["posts:trash"] = new action(array(
    "id"          => "posts:trash",
    "module_name" => "posts",
    "script_url"  => "{$modules["posts"]->get_url(true)}/scripts/toolbox.php?action=change_status&new_status=trashed",
));
