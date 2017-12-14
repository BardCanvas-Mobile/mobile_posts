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
use hng2_modules\mobile_posts\post_item;
use hng2_modules\mobile_posts\toolbox;
use hng2_modules\posts\posts_repository;

include "../config.php";
include "../includes/bootstrap.inc";
header("Content-Type: application/json; charset=utf-8");
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');

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

$config->globals["modules:gallery.avoid_images_autolinking"] = true;

$current_module->load_extensions("json_posts_feed", "before_loop_start");

$items = array();
foreach($posts as &$post)
{
    $item = new post_item();
    $item->prepare($post);
    
    $items[] = $item;
}

# if( ! $account->_exists ) $mem_cache->set($cache_key, $items, 0, $cache_ttl);

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
