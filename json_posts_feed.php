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
 * @returns string JSON {message:string, data:mixed}
 */

use hng2_base\accounts_repository;
use hng2_modules\categories\categories_repository;
use hng2_modules\categories\category_record;
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

$filter = array();
if( ! empty($_REQUEST["category"]) )
{
    $filter[] = sprintf("main_category = '%s'", addslashes(trim(stripslashes($_REQUEST["category"]))));
}
else
{
    $raw_list = $settings->get("modules:mobile_controller.{$_REQUEST["scope"]}_listed_categories");
    if( ! empty($raw_list) )
    {
        $slugs = array();
        foreach(explode("\n", $raw_list) as $line)
            $slugs[] = "'" . trim($line) . "'";
        
        $filter[] = "main_category in ( select c.id_category from categories c where c.slug in (" .implode(", ", $slugs) . ") )";
    }
}

#
# Data grabbing
#

$limit  = $settings->get("modules:mobile_controller.{$_REQUEST["scope"]}_batch_size");
if( ! is_numeric($limit) || empty($limit) ) $limit = 10;

$offset = $_REQUEST["offset"];
if( ! is_numeric($offset) ) $offset = 0;

$posts = $posts_repository->lookup($filter, $limit, $offset, "");

$raw_categories = $categories_repository->find(array(), 0, 0, "id_category asc");

/** @var category_record[] $all_categories */
$all_categories = array();
foreach($raw_categories as $category) $all_categories[$category->id_category] = $category;

$all_countries = array();
$res = $database->query("select * from countries order by alpha_2 asc");
while($row = $database->fetch_object($res))
    $all_countries[$row->alpha_2] = $row->name;

foreach($posts as &$post)
{
    $author                          = $post->get_author();
    $post->author_avatar             = $author->get_avatar_url(true);
    $post->author_country_name       = $all_countries[$author->country];
    $post->author_creation_date      = $author->creation_date;
    $post->author_can_be_disabled    = true;
    $post->can_be_deleted            = true;
    $post->can_be_drafted            = true;
    $post->can_be_flagged_for_review = true;
    $post->parent_category_title     = $all_categories[$post->main_category]->parent_category_title;
    
    $post->title   = externalize_urls($post->get_processed_title(false));
    $post->excerpt = externalize_urls($post->get_processed_excerpt(true));
    $post->content = externalize_urls($post->get_processed_content());
}

$toolbox->throw_response(array(
    "message" => "OK",
    "data"    => $posts,
    "extras"  => (object) array(
        "hideCategoryInCards" => ! empty($_REQUEST["category"]),
    ),
    "stats"   => (object) array(
        "processingTime" => number_format(microtime(true) - $global_start_time, 3) . "s",
        "queries"        => $database->get_tracked_queries_count(),
    ),
));
