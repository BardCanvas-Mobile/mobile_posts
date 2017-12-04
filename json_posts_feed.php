<?php
/**
 * JSON feed
 *
 * @package mobile_controller
 * @author  Alejandro Caballero - lava.caballero@gmail.com
 * 
 * @param string "bcm_access_token"
 * @param string "bcm_platform"     ios|android
 * @param string "scope"            posts_main|posts_alt1|posts_alt2|posts_alt3
 * @param string "category"         id of the category to show
 * @param int    "since"            start date 
 * @param int    "until"            end date 
 * @param string "callback"         Optional, for AJAX call
 * 
 * @var module $current_module
 * 
 * @returns string JSON {message:string, data:mixed}
 */

use hng2_base\accounts_repository;
use hng2_base\module;
use hng2_modules\categories\categories_repository;
use hng2_modules\categories\category_record;
use hng2_modules\mobile_controller\action_trigger;
use hng2_modules\mobile_controller\feed_item;
use hng2_modules\mobile_controller\content_block;
use hng2_modules\mobile_posts\toolbox;
use hng2_modules\posts\posts_repository;

include "../config.php";
include "../includes/bootstrap.inc";
header("Content-Type: application/json; charset=utf-8");

$toolbox = new toolbox();
$toolbox->output_type = "JSON";
$toolbox->open_session();

#
# Validations
#

if( empty($_REQUEST["scope"]) )
    $toolbox->throw_response(trim($current_module->language->messages->missing_scope));

if( ! in_array($_REQUEST["scope"], array("posts_main", "posts_alt1", "posts_alt2", "posts_alt3")) )
    $toolbox->throw_response(trim($current_module->language->messages->invalid_scope));

#
# Cache stuff
#

$cache_ttl = $settings->get("modules:posts.main_index_cache_for_guests") * 60;
$cache_key = sprintf(
    "%s/%s/%s-%s~v5",
    $_REQUEST["scope"],
    empty($_REQUEST["category"]) ? "all" : "cat:{$_REQUEST["category"]}",
    empty($_REQUEST["since"]) ? "since:x" : "since:{$_REQUEST["since"]}",
    empty($_REQUEST["until"]) ? "until:x" : "until:{$_REQUEST["until"]}"
);

// TODO: This is working fine, enable it for production.
// if( ! $account->_exists )
// {
//     $items = $mem_cache->get($cache_key);
//    
//     if( ! empty($items) )
//     {
//         $toolbox->throw_response(array(
//             "message" => "OK",
//             "data"    => $items,
//             "extras"  => (object) array(
//                 "hideCategoryInCards" => ! empty($_REQUEST["category"]),
//             ),
//             "request" => $_REQUEST,
//             "stats"   => (object) array(
//                 "processingTime" => number_format(microtime(true) - $global_start_time, 3) . "s",
//                 "queries"        => $database->get_tracked_queries_count(),
//                 "cachedResults"  => true,
//             ),
//         ));
//     }
// }

#
# Inits
#

$config->globals["modules:gallery.export_raw_video_tags"] = true;

$posts_repository      = new posts_repository();
$categories_repository = new categories_repository();
$accounts_repository   = new accounts_repository();

#
# Filters
#

$filter = array();

# By category
if( ! empty($_REQUEST["category"]) )
{
    $filter[] = sprintf("main_category = '%s'", addslashes(trim(stripslashes($_REQUEST["category"]))));
}
else
{
    # Included categories
    $raw_list = $settings->get("modules:mobile_controller.{$_REQUEST["scope"]}_listed_categories");
    if( ! empty($raw_list) )
    {
        $slugs = array();
        foreach(explode("\n", $raw_list) as $line)
            $slugs[] = "'" . trim($line) . "'";
        
        $filter[] = "main_category in ( select c.id_category from categories c where c.slug in (" .implode(", ", $slugs) . ") )";
    }
    
    # Excluded categories
    $raw_list = $settings->get("modules:mobile_controller.{$_REQUEST["scope"]}_excluded_categories");
    if( ! empty($raw_list) )
    {
        $slugs = array();
        foreach(explode("\n", $raw_list) as $line)
            $slugs[] = "'" . trim($line) . "'";
        
        $filter[] = "main_category not in ( select c.id_category from categories c where c.slug in (" .implode(", ", $slugs) . ") )";
    }
}

# Included ser levels
$raw_list = $settings->get("modules:mobile_controller.{$_REQUEST["scope"]}_listed_author_levels");
if( ! empty($raw_list) )
    $filter[] = "( select level from account where account.id_account = posts.id_author ) in ($raw_list)";

# Excluded user levels
$raw_list = $settings->get("modules:mobile_controller.{$_REQUEST["scope"]}_excluded_author_levels");
if( ! empty($raw_list) )
    $filter[] = "( select level from account where account.id_account = posts.id_author ) not in ($raw_list)";

#
# Data grabbing
#

$limit  = $settings->get("modules:mobile_controller.{$_REQUEST["scope"]}_batch_size");
if( ! is_numeric($limit) || empty($limit) ) $limit = 10;

if( ! empty($_REQUEST["until"]) && $_REQUEST["until"] > date("Y-m-d H:i:s") )
    $_REQUEST["until"] = date("Y-m-d H:i:s");

if( ! empty($_REQUEST["since"]) ) $filter[] = "publishing_date > '{$_REQUEST["since"]}'";
if( ! empty($_REQUEST["until"]) ) $filter[] = "publishing_date < '{$_REQUEST["until"]}'";

$posts = $posts_repository->lookup($filter, $limit, 0, "publishing_date desc");

/** @var category_record[] $all_categories */
$all_categories = array();
$raw_categories = $categories_repository->find(array(), 0, 0, "id_category asc");
foreach($raw_categories as $category) $all_categories[$category->id_category] = $category;

$all_countries = array();
$res = $database->query("select * from countries order by alpha_2 asc");
while($row = $database->fetch_object($res))
    $all_countries[$row->alpha_2] = $row->name;

$config->globals["modules:gallery.avoid_images_autolinking"] = true;

$current_module->load_extensions("json_posts_feed", "before_loop_start");

$items = array();
foreach($posts as &$post)
{
    $author = $post->get_author();
    
    $item = new feed_item();
    
    $item->type                 = "post";
    $item->id                   = $post->id_post;
    $item->author_user_name     = $author->user_name;
    $item->author_level         = $author->level;
    $item->author_avatar        = $author->get_avatar_url(true);
    $item->author_display_name  = externalize_urls($author->get_processed_display_name());
    $item->author_creation_date = $author->creation_date;
    $item->author_country_name  = $all_countries[$author->country];
    
    $item->featured_image_id        = $post->id_featured_image;
    $item->featured_image_path      = $post->featured_image_path;
    $item->featured_image_thumbnail = $post->featured_image_thumbnail;
    
    $item->has_featured_image             = ! empty($post->id_featured_image);
    $item->featured_image_not_in_contents = stristr($post->content, $post->id_featured_image) === false;
    
    $item->main_category_title   = $post->main_category_title;
    $item->parent_category_title = $all_categories[$post->main_category]->parent_category_title;
    
    $item->title   = externalize_urls($post->get_processed_title(false));
    $item->excerpt = externalize_urls($post->get_processed_excerpt(true));
    $item->publishing_date = $post->publishing_date;
    
    $item->content           = externalize_urls($post->get_processed_content());
    $item->creation_ip       = $post->creation_ip;
    $item->creation_location = $post->creation_location;
    
    #
    # Extra content blocks
    #
    
    if( ! empty($author->signature) )
    {
        $item->extra_content_blocks[] = new content_block(array(
            "class"    => "author_signature",
            "contents" => "{$author->get_processed_signature()}"
        ));
    }
    
    # Excerpt additions
    if( $account->level >= $config::MODERATOR_USER_LEVEL )
    {
        $extra_details = array();
        
        $extra_details[] = "
            <div class='fa-bulleted'>
                <i class='fa fa-user fa-fw'></i>
                {$modules["accounts"]->language->user_profile_page->info->level}
                {$author->level} ({$author->get_role()})
            </div>
            <div class='fa-bulleted'>
                <i class='fa fa-clock-o fa-fw'></i>
                {$modules["accounts"]->language->user_profile_page->info->member_since}
                <span class='convert-to-full-date'>{$author->creation_date}</span>
            </div>
        ";
        
        $extra_details[] = "
            <div class='fa-bulleted'>
                <i class='fa fa-globe fa-fw'></i>
                {$post->creation_ip}
            </div>
        ";
    
        if( ! empty($post->creation_location) )
            $extra_details[] = "
                <div class='fa-bulleted'>
                    <i class='fa fa-map-marker fa-fw'></i>
                    {$post->creation_location}
                </div>
            ";
        
        if( ! empty($extra_details) )
        {
            $item->excerpt_extra_blocks[] = new content_block(array(
                "title"    => "",
                "class"    => "color-gray",
                "contents" => implode("\n", $extra_details),
            ));
            
            $item->extra_content_blocks[] = new content_block(array(
                "title"    => replace_escaped_objects($current_module->language->about_author, array(
                    '{$author_display_name}' => $author->get_processed_display_name()
                )),
                "class"    => "author_info_for_admins content-block",
                "contents" => "<div class='content-block-inner color-gray'>" . implode("\n", $extra_details) . "</div>",
            ));
        }
    }
    $current_module->load_extensions("json_posts_feed", "extra_content_blocks_for_item");
    
    #
    # Index actions
    #
    
    if($account->level >= $config::MODERATOR_USER_LEVEL)
    {
        # Set as draft
        $item->index_action_triggers[] = new action_trigger(array(
            "action_id" => "posts:set_as_draft",
            "caption"   => trim($current_module->language->actions->draft),
            "icon"      => "fa-times",
            "class"     => "color-orange",
            "options"   => array(
                "remove_parent_on_success" => true,
            ),
            "params"    => array(
                "id_post" => $post->id_post,
            ),
        ));
        
        if( $account->id_account != $author->id_account && $author->level < $config::MODERATOR_USER_LEVEL )
        {
            # Flag for review
            $item->index_action_triggers[] = new action_trigger(array(
                "action_id" => "posts:flag_for_review",
                "caption"   => trim($current_module->language->actions->review),
                "icon"      => "fa-flag",
                "class"     => "color-orange",
                "options"   => array(
                    "remove_parent_on_success" => true,
                ),
                "params"    => array(
                    "id_post" => $post->id_post,
                ),
            ));
        }
        
        # Trash
        $item->index_action_triggers[] = new action_trigger(array(
            "action_id" => "posts:trash",
            "caption"   => trim($current_module->language->actions->trash),
            "icon"      => "fa-trash",
            "class"     => "color-red",
            "options"   => array(
                "remove_parent_on_success" => true,
            ),
            "params"    => array(
                "id_post" => $post->id_post,
            ),
        ));
        
        if( $author->level < $config::MODERATOR_USER_LEVEL )
        {
            # Disable author account
            $item->index_action_triggers[] = new action_trigger(array(
                "action_id" => "accounts:disable",
                "caption"   => trim($current_module->language->actions->disable_author),
                "icon"      => "fa-user-times",
                "class"     => "color-red",
                "params"    => array(
                    "id_account" => $post->id_author,
                ),
            ));
        }
    }
    
    $item->has_index_actions = count($item->index_action_triggers) > 0;
    
    #
    # Item actions
    #
    
    if( $modules["contact"]->enabled && $account->id_account != $post->id_author && $account->level < $config::MODERATOR_USER_LEVEL )
    {
        # Report post
        $item->item_action_triggers[] = new action_trigger(array(
            "action_id" => "posts:report",
            "caption"   => trim($current_module->language->actions->report),
            "icon"      => "fa-exclamation-circle",
            "class"     => "color-pink",
            "params"    => array(
                "id" => $post->id_post,
            ),
        ));
    }
    
    if( $author->can_interact_in_pms() )
    {
        # PM
        $item->item_action_triggers[] = new action_trigger(array(
            "action_id" => "messaging:compose",
            "caption"   => trim($language->contact->pm->caption),
            "icon"      => "fa-inbox",
            "class"     => "color-gray",
            "params"    => array(
                "target_id_account" => $comment->id_author
            ),
        ));
    }
    
    if($account->level >= $config::MODERATOR_USER_LEVEL)
    {
        # Set as draft
        $item->item_action_triggers[] = new action_trigger(array(
            "action_id" => "posts:set_as_draft",
            "caption"   => trim($current_module->language->actions->draft),
            "icon"      => "fa-times",
            "class"     => "color-orange",
            "options"   => array(
                "go_back_on_success" => true,
            ),
            "params"    => array(
                "id_post" => $post->id_post,
            ),
        ));
        
        if( $account->id_account != $author->id_account && $author->level < $config::MODERATOR_USER_LEVEL )
        {
            # Flag for review
            $item->item_action_triggers[] = new action_trigger(array(
                "action_id" => "posts:flag_for_review",
                "caption"   => trim($current_module->language->actions->review),
                "icon"      => "fa-flag",
                "class"     => "color-orange",
                "options"   => array(
                    "go_back_on_success" => true,
                ),
                "params"    => array(
                    "id_post" => $post->id_post,
                ),
            ));
        }
        
        # Trash
        $item->item_action_triggers[] = new action_trigger(array(
            "action_id" => "posts:trash",
            "caption"   => trim($current_module->language->actions->trash),
            "icon"      => "fa-trash",
            "class"     => "color-red",
            "options"   => array(
                "go_back_on_success" => true,
            ),
            "params"    => array(
                "id_post" => $post->id_post,
            ),
        ));
        
        if( $author->level < $config::MODERATOR_USER_LEVEL )
        {
            # Disable author account
            $item->item_action_triggers[] = new action_trigger(array(
                "action_id" => "accounts:disable",
                "caption"   => trim($current_module->language->actions->disable_author),
                "icon"      => "fa-user-times",
                "class"     => "color-red",
                "options"   => array(
                    "go_back_on_success" => true,
                ),
                "params"    => array(
                    "id_account" => $post->id_author,
                ),
            ));
        }
    }
    
    $item->has_item_actions = count($item->item_action_triggers) > 0;
    
    #
    # Comments
    #
    
    $item->comments_count = $post->comments_count;
    $current_module->load_extensions("json_posts_feed", "comments_forging");
    
    #
    # Collection update
    #
    
    $items[] = $item;
}

if( ! $account->_exists ) $mem_cache->set($cache_key, $items, 0, $cache_ttl);

$toolbox->throw_response(array(
    "message" => "OK",
    "data"    => $items,
    "extras"  => (object) array(
        "hideCategoryInCards" => ! empty($_REQUEST["category"]),
    ),
    "request" => $_REQUEST,
    "stats"   => (object) array(
        "processingTime" => number_format(microtime(true) - $global_start_time, 3) . "s",
        "queries"        => $database->get_tracked_queries_count(),
        "cachedResults"  => false,
    ),
));
