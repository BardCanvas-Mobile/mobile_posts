<?php
/**
 * Mobile Controller Action Definitions Extender
 * Registers actions for the mobile controller.
 *
 * @package    BardCanvas
 * @subpackage mobile_posts
 * @author     Alejandro Caballero - lava.caballero@gmail.com
 * 
 * @var module[] $modules
 */

use hng2_base\module;
use hng2_modules\mobile_controller\action;

global $modules, $manifest;

if( empty($modules["posts"]) ) return;
if( ! $modules["posts"]->enabled ) return;

# Set as draft
$manifest->actionsRegistry["posts:set_as_draft"] = new action(array(
    "id"          => "posts:set_as_draft",
    "module_name" => "posts",
    "script_url"  => "{$modules["posts"]->get_url(true)}/scripts/toolbox.php?action=change_status&new_status=draft",
    "options"     => array(
        "requires_confirmation" => true,
        "confirmation_message"  => unindent($this_module->language->messages->prompts->set_as_draft),
        "success_notification"  => unindent($this_module->language->messages->success_notifications->post_changed),
    ),
));

# Flag for review
$manifest->actionsRegistry["posts:flag_for_review"] = new action(array(
    "id"          => "posts:flag_for_review",
    "module_name" => "posts",
    "script_url"  => "{$modules["posts"]->get_url(true)}/scripts/toolbox.php?action=change_status&new_status=reviewing",
    "options"     => array(
        "requires_confirmation" => true,
        "confirmation_message"  => unindent($this_module->language->messages->prompts->flag_for_review),
        "success_notification"  => unindent($this_module->language->messages->success_notifications->post_changed),
    ),
));

# Trash
$manifest->actionsRegistry["posts:trash"] = new action(array(
    "id"          => "posts:trash",
    "module_name" => "posts",
    "script_url"  => "{$modules["posts"]->get_url(true)}/scripts/toolbox.php?action=change_status&new_status=trashed",
    "options"     => array(
        "requires_confirmation" => true,
        "confirmation_message"  => unindent($this_module->language->messages->prompts->trash),
        "success_notification"  => unindent($this_module->language->messages->success_notifications->post_changed),
    ),
));

# Report
if( $modules["contact"]->enabled )
{
    $manifest->actionsRegistry["posts:report"] = new action(array(
        "id"          => "posts:report",
        "module_name" => "contact",
        "script_url"  => "{$modules["contact"]->get_url(true)}/index_bcm.php?action=report&type=post&stop_on_success=true",
        "call_method" => "frame",
    ));
}
