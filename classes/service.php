<?php
namespace hng2_modules\mobile_posts;

use hng2_modules\mobile_controller\service as base_service;

class service extends base_service
{
    private $toolbox;
    
    public function __construct($module, $key, $type, $label, $status)
    {
        parent::__construct($module, $key, $type, $label, $status);
        
        $this->toolbox = new toolbox();
    }
    
    public function forge( &$manifest )
    {
        global $settings, $modules;
        
        $current_module = $modules["mobile_posts"];
        
        $key      = $this->key;
        $root_url = $settings->get("modules:mobile_controller.root_url");
        
        $style = $settings->get("modules:mobile_controller.{$key}_style");
        if( empty($style) ) $style = "feed/cards:simple";
        
        $icon = $settings->get("modules:mobile_controller.{$key}_image_icon");
        if( ! empty($icon) )
        {
            $icon = $root_url . ltrim($icon, "/");
        }
        else
        {
            $icon = $settings->get("modules:mobile_controller.{$key}_standard_icon");
            if( empty($icon) )
            {
                switch($key)
                {
                    case "posts_main":
                        $icon = "home,fa-home"; break;
                    case "posts_alt1":
                    case "posts_alt2":
                    case "posts_alt3":
                        $icon = "book,fa-book"; break;
                    default:
                        $icon = "folder,fa-folder-o"; break;
                }
            }
        }
        
        $options = (object) array();
        
        $options->autoRefreshMinutes   = (int) $settings->get("modules:mobile_controller.{$key}_auto_refresh");
        $options->colorTheme           = $settings->get("modules:mobile_controller.{$key}_color_theme");
        $options->showsCommentsOnIndex = $settings->get("modules:mobile_controller.{$key}_comments_in_index") == "true";
        $options->hasNavbar            = false;
        
        if( $settings->get("modules:mobile_controller.{$key}_hide_category_selector") != "true" ||
            $settings->get("modules:mobile_controller.{$key}_hide_search_helper")     != "true" )
        {
            $options->navbarHelpers = array();
            $categories_count       = $this->toolbox->get_categories_count($key);
            
            if( $settings->get("modules:mobile_controller.{$key}_hide_category_selector") != "true" && $categories_count > 1 )
            {
                $options->hasNavbar       = true;
                $options->navbarHelpers[] = (object) array(
                    "type"             => "selector",
                    "contentsProvider" => ltrim("{$current_module->get_url(false)}/json_categories_list.php?scope={$key}", "/"),
                    "paramName"        => "category"
                );
            }
            
            if( $settings->get("modules:mobile_controller.{$key}_hide_search_helper") != "true" )
            {
                $options->hasNavbar       = true;
                $options->navbarHelpers[] = (object) array(
                    "type"             => "searchbox",
                    "contentsProvider" => ltrim("{$current_module->get_url(false)}/json_search.php", "/")
                );
            }
            
            $options->showsMultipleCategories = $categories_count > 1;
        }
        
        $options->showAuthors = $settings->get("modules:mobile_controller.{$key}_show_authors") == "true";
        
        $manifest->services[] = (object) array(
            "id"         => "{$this->module}-{$this->key}",
            "caption"    => $this->label,
            "type"       => $style,
            "url"        => ltrim("{$current_module->get_url(false)}/json_posts_feed.php?scope={$key}", "/"),
            "icon"       => $icon,
            "options"    => $options,
        );
        
    }
}
