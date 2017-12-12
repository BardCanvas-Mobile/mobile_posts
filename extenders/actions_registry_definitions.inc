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

#
# Quick post
#

$composer_fields = (object) array();

$composer_fields->attachments_type = (object) array(
    "type"  => "hidden",
    "value" => "preforged",
);
$composer_fields->raw_success_confirmation = (object) array(
    "type"  => "hidden",
    "value" => "true",
);
$composer_fields->status = (object) array(
    "type"  => "hidden",
    "value" => "published",
);
$composer_fields->is_quick_post = (object) array(
    "type"  => "hidden",
    "value" => "true",
);
$composer_fields->ok_with_url = (object) array(
    "type"  => "hidden",
    "value" => "false",
);
$composer_fields->title = (object) array(
    "type"        => "textarea/expandible",
    "placeholder" => trim($modules["posts"]->language->form->fields->title->quick_form_expanded_placeholder),
);
$composer_fields->main_category = (object) array(
    "type"       => "select",
    "attributes" => "data-remotely-filled=\"true\" data-options-src=\"{$this_module->get_url(true)}/json_categories_list.php?scope={{service.vars.scope}}&for_selection=true\"",
);

$config->globals["modules:mobile_posts.composer_fields"] =& $composer_fields;
$this_module->load_extensions("actions_registry_definitions", "set_composer_fields_before_content");

$composer_fields->content = (object) array(
    "type"        => "textarea/tinymce",
    "placeholder" => trim($this_module->language->remote_quick_post->contents->placeholder),
);
$composer_fields->attachments = (object) array(
    "type"        => "local_media_selector",
    "title"       => trim($this_module->language->remote_quick_post->attachments->title),
);

$manifest->actionsRegistry["posts:quickpost"] = new action(array(
    "id"          => "posts:quickpost",
    "module_name" => "posts",
    "script_url"  => "{$modules["posts"]->get_url(true)}/scripts/save.php",
    "call_method" => "posting_form_composer",
    "options"     => (object) array(
        "composer" => (object) array(
            "title"                => trim($this_module->language->remote_quick_post->title),
            "success_notification" => unindent($this_module->language->messages->success_notifications->post_saved),
            "fields"               => $composer_fields
        )
    ),
));

# Edit
$manifest->actionsRegistry["posts:edit"] = new action(array(
    "id"          => "posts:edit",
    "call_method" => "frame",
    "module_name" => "posts",
    "script_url"  => "{$modules["posts"]->get_url(true)}/index.php?bcm_minimalistic_mode_enforced=true&bcm_minimalistic_sandboxing_disabled=true&hide_browser=true&iframed_message_on_sucessful_save=true&bcm_output_type=HTML&post_form_iframed_mode=true&hide_post_preview=true",
));
