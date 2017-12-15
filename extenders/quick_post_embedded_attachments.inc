<?php
/**
 * Posts extender for processing embedded attachments
 * Called from posts/scripts/save.inc for quick posts,
 * before extracting media items from the contents.
 *
 * @package    BardCanvas
 * @subpackage mobile_posts
 * @author     Alejandro Caballero - lava.caballero@gmail.com
 *
 * @var module[] $modules
 */

use hng2_base\module;
use hng2_modules\mobile_controller\toolbox;

if( empty($_POST["embedded_attachments"]) ) return;
if( ! is_array($_POST["embedded_attachments"]) ) return;

global $post, $account, $tags, $media_repository;

$toolbox = new toolbox();

try
{
    $uploads = $toolbox->extract_embedded_media();
}
catch(\Exception $e)
{
    die( $e->getMessage() );
}

if( empty($uploads) ) return;

/** @var array $uploads two dimensions. First is either images or videos and second level is $_FILE compatible array. */
foreach($uploads as $type => $files)
{
    /** @var  array $file [name, type, tmp_name, error, size] */
    foreach($files as $index => $file)
    {
        $file_title = "{$account->display_name} - {$file["name"]}";
        
        $item_data = array(
            "title"          => $file_title,
            "description"    => "{$post->title}\n\n{$post->excerpt}" .
                                (empty($tags) ? "" : "\n\n#" . implode(" #", $tags)),
            "main_category"  => $post->main_category,
            "visibility"     => $post->visibility,
            "status"         => "published",
            "password"       => "",
            "allow_comments" => "1",
        );
        $res = $media_repository->receive_and_save($item_data, $file, true, true);
        
        if( is_string($res) ) die($res);
        
        if( $type == "image" )
            $post->content .= "\n<p><img src='{$res->get_item_url()}'
                data-media-type='image' data-id-media='{$res->id_media}'></p>\n";
        else
            $post->content .= "\n<p><img src='{$res->get_thumbnail_url()}' 
                data-media-type='video' data-id-media='{$res->id_media}'></p>\n";
        
        sleep(1);
    }
}
