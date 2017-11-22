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
 * @param int    "offset"           pagination 
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
use hng2_modules\mobile_controller\feed_item;
use hng2_modules\mobile_controller\feed_item_extra_content_block;
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

$offset = $_REQUEST["offset"];
if( ! is_numeric($offset) ) $offset = 0;

$posts = $posts_repository->lookup($filter, $limit, $offset, "");

/** @var category_record[] $all_categories */
$all_categories = array();
$raw_categories = $categories_repository->find(array(), 0, 0, "id_category asc");
foreach($raw_categories as $category) $all_categories[$category->id_category] = $category;

$all_countries = array();
$res = $database->query("select * from countries order by alpha_2 asc");
while($row = $database->fetch_object($res))
    $all_countries[$row->alpha_2] = $row->name;

$config->globals["modules:gallery.avoid_images_autolinking"] = true;

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
    
    $item->featured_image_path      = $post->featured_image_path;
    $item->featured_image_thumbnail = $post->featured_image_thumbnail;
    
    $item->main_category_title   = $post->main_category_title;
    $item->parent_category_title = $all_categories[$post->main_category]->parent_category_title;
    
    $item->title   = externalize_urls($post->get_processed_title(false));
    $item->excerpt = externalize_urls($post->get_processed_excerpt(true));
    $item->publishing_date = $post->publishing_date;
    
    $item->content           = externalize_urls($post->get_processed_content());
    $item->comments_count    = $post->comments_count;
    $item->creation_ip       = $post->creation_ip;
    $item->creation_location = $post->creation_location;
    
    $item->author_can_be_disabled    = true;
    $item->can_be_deleted            = true;
    $item->can_be_drafted            = true;
    $item->can_be_flagged_for_review = true;
    
    #
    # Extra content blocks
    #
    
    if( ! empty($author->signature) )
    {
        $item->extra_content_blocks[] = new feed_item_extra_content_block(array(
            "title"    => "",
            "class"    => "author_signature",
            "contents" => "{$author->get_processed_signature()}"
        ));
    }
    
    $current_module->load_extensions("json_posts_feed", "extra_content_blocks_for_item");
    
    $items[] = $item;
}

$toolbox->throw_response(array(
    "message" => "OK",
    "data"    => $items,
    "extras"  => (object) array(
        "hideCategoryInCards" => ! empty($_REQUEST["category"]),
    ),
    "stats"   => (object) array(
        "processingTime" => number_format(microtime(true) - $global_start_time, 3) . "s",
        "queries"        => $database->get_tracked_queries_count(),
    ),
));
