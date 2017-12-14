<?php
/**
 * Single item JSON deliverer
 *
 * @package mobile_controller
 * @author  Alejandro Caballero - lava.caballero@gmail.com
 * 
 * @param string "bcm_access_token"
 * @param string "bcm_platform"     ios|android
 * @param string "id"               id of the post
 * @param string "callback"         Optional, for AJAX call
 * 
 * @var module $current_module
 * 
 * @returns string JSON {message:string, data:mixed}
 */

use hng2_base\accounts_repository;
use hng2_base\module;
use hng2_modules\categories\categories_repository;
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

if( empty($_REQUEST["id"]) )
    $toolbox->throw_response(trim($current_module->language->messages->missing_post_id));

if( ! is_numeric($_REQUEST["id"]) )
    $toolbox->throw_response(trim($current_module->language->messages->missing_post_id));

#
# Inits
#

$config->globals["modules:gallery.export_raw_video_tags"] = true;

$posts_repository      = new posts_repository();
$categories_repository = new categories_repository();
$accounts_repository   = new accounts_repository();

#
# Data grabbing
#

$post = $posts_repository->get($_REQUEST["id"]);
if( is_null($post) )
    $toolbox->throw_response(trim($current_module->language->messages->post_not_found));

$posts = array($post);

$config->globals["modules:gallery.avoid_images_autolinking"] = true;

$current_module->load_extensions("json_posts_feed", "before_loop_start");

$item = new post_item();
$item->prepare($post);

$toolbox->throw_response(array(
    "message" => "OK",
    "data"    => $item,
    "request" => $_REQUEST,
    "stats"   => (object) array(
        "processingTime" => number_format(microtime(true) - $global_start_time, 3) . "s",
        "queries"        => $database->get_tracked_queries_count(),
        "cachedResults"  => false,
    ),
));
