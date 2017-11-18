<?php
namespace hng2_modules\mobile_posts;

use hng2_modules\mobile_controller\toolbox as base_toolbox;
use hng2_modules\categories\categories_repository;

class toolbox extends base_toolbox
{
    private $categories_repository;
    
    public function __construct()
    {
        $this->categories_repository = new categories_repository();
    }
    
    public function get_categories_count($scope)
    {
        $filter = $this->get_categories_filter($scope);
        
        return $this->categories_repository->get_record_count($filter);
    }
    
    public function get_categories_list($scope)
    {
        $filter = $this->get_categories_filter($scope);
        $list   = $this->categories_repository->find($filter, 0, 0, "parent_category_title asc, title asc");
        $return = array();
        
        foreach($list as $category) $return[] = $category->get_as_associative_array();
        return $return;
    }
    
    private function get_categories_filter($scope)
    {
        global $settings, $account;
        
        $where = array();
        
        if( ! $account->_exists )
            $where[] = "visibility = 'public'";
        else
            $where[] = "(
                          visibility = 'public' or visibility = 'users' or 
                          (visibility = 'level_based' and '{$account->level}' >= min_level) 
                        )";
        
        $raw_list = $settings->get("modules:mobile_controller.{$scope}_listed_categories");
        if( ! empty($raw_list) )
        {
            $slugs = array();
            foreach(explode("\n", $raw_list) as $line)
                $slugs[] = "'" . trim($line) . "'";
            
            $where[] = "slug in (" .implode(", ", $slugs) . ")";
        }
        
        return $where;
    }
}
