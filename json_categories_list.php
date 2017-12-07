<?php
/**
 * JSON categories list
 *
 * @package mobile_controller
 * @author  Alejandro Caballero - lava.caballero@gmail.com
 * 
 * @param string "bcm_access_token"
 * @param string "bcm_platform"     ios|android
 * @param string "scope"            posts_main|posts_alt1|posts_alt2|posts_alt3
 * @param bool   "for_selection"    Optional, if provided, first item will have a "select category" caption.
 * @param string "callback"         Optional, for AJAX call
 * 
 * @returns string JSON {message:string, data:mixed}
 */

use hng2_modules\categories\category_record;
use hng2_modules\mobile_posts\toolbox;

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

$caption = $_REQUEST["for_selection"] == "true"
         ? trim($current_module->language->select_category)
         : trim($current_module->language->all_categories);
$dummy   = new category_record(array("id_category" => "", "title" => $caption, "description" => ""));
$dummy   = $dummy->get_as_associative_array();
$data    = $toolbox->get_categories_list($_REQUEST["scope"]);
array_unshift($data, $dummy);

$final_data = array();
foreach($data as $item) $final_data[] = (object) array(
    "id"          => $item["id_category"],
    "caption"     => empty($item["parent_category_title"])
                     ? $item["title"]
                     : $item["parent_category_title"] . "/" . $item["title"],
    "description" => $item["description"],
);

$toolbox->throw_response(array(
    "message" => "OK",
    "data"    => $final_data,
    "stats"   => (object) array(
        "processingTime" => number_format(microtime(true) - $global_start_time, 3) . "s",
        "queries"        => $database->get_tracked_queries_count(),
    ),
));
