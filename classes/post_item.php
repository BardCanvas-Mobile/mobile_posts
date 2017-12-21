<?php
namespace hng2_modules\mobile_posts;

use hng2_modules\categories\categories_repository;
use hng2_modules\categories\category_record;
use hng2_modules\mobile_controller\action_trigger;
use hng2_modules\mobile_controller\content_block;
use hng2_modules\mobile_controller\feed_item;
use hng2_modules\posts\post_record;

class post_item extends feed_item
{
    public function prepare(post_record $post)
    {
        global $account, $config, $modules, $language;
        
        $current_module = $modules["mobile_posts"];
        
        $author = $post->get_author();
        $all_countries = $this->get_all_countries();
        $all_categories = $this->get_all_categories();
        
        $this->type                 = "post";
        $this->id                   = $post->id_post;
        $this->author_user_name     = $author->user_name;
        $this->author_level         = $author->level;
        $this->author_avatar        = $author->get_avatar_url(true);
        $this->author_display_name  = externalize_urls($author->get_processed_display_name());
        $this->author_creation_date = $author->creation_date;
        $this->author_country_name  = $all_countries[$author->country];
        
        $this->featured_image_id        = $post->id_featured_image;
        $this->featured_image_path      = $post->featured_image_path;
        $this->featured_image_thumbnail = $post->featured_image_thumbnail;
        
        $this->has_featured_image             = ! empty($post->id_featured_image);
        $this->featured_image_not_in_contents = stristr($post->content, $post->id_featured_image) === false;
        
        $this->main_category_title   = $post->main_category_title;
        $this->parent_category_title = $all_categories[$post->main_category]->parent_category_title;
        
        $this->permalink = $post->get_permalink(true);
        $this->title     = externalize_urls($post->get_processed_title(false));
        $this->excerpt   = externalize_urls($post->get_processed_excerpt(true));
        $this->tags_list = $post->tags_list;
        
        $this->publishing_date   = $post->publishing_date;
        $this->content           = externalize_urls($post->get_processed_content());
        $this->creation_ip       = $post->creation_ip;
        $this->creation_location = $post->creation_location;
        
        #
        # Extra content blocks
        #
        
        if( ! empty($author->signature) )
        {
            $this->extra_content_blocks[] = new content_block(array(
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
            
            if( ! empty($post->creation_ip) )
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
                $this->excerpt_extra_blocks[] = new content_block(array(
                    "title"    => "",
                    "class"    => "color-gray",
                    "contents" => implode("\n", $extra_details),
                ));
                
                $this->extra_content_blocks[] = new content_block(array(
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
            # Edit
            $this->index_action_triggers[] = new action_trigger(array(
                "action_id" => "posts:edit",
                "caption"   => trim($current_module->language->actions->edit),
                "icon"      => "fa-pencil",
                "class"     => "color-gray",
                "params"    => array(
                    "edit_post" => $post->id_post,
                ),
            ));
            
            # Set as draft
            $this->index_action_triggers[] = new action_trigger(array(
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
                $this->index_action_triggers[] = new action_trigger(array(
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
            $this->index_action_triggers[] = new action_trigger(array(
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
                $this->index_action_triggers[] = new action_trigger(array(
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
        
        $this->has_index_actions = count($this->index_action_triggers) > 0;
        
        #
        # Item actions
        #
        
        if( $post->can_be_edited() )
        {
            # Edit
            $this->item_action_triggers[] = new action_trigger(array(
                "action_id" => "posts:edit",
                "caption"   => trim($current_module->language->actions->edit),
                "icon"      => "fa-pencil",
                "class"     => "color-gray",
                "params"    => array(
                    "edit_post" => $post->id_post,
                ),
            ));
        }
        
        if( $modules["contact"]->enabled && $account->id_account != $post->id_author && $account->level < $config::MODERATOR_USER_LEVEL )
        {
            # Report post
            $this->item_action_triggers[] = new action_trigger(array(
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
            $this->item_action_triggers[] = new action_trigger(array(
                "action_id" => "messaging:compose",
                "caption"   => trim($language->contact->pm->caption),
                "icon"      => "fa-inbox",
                "class"     => "color-gray",
                "params"    => array(
                    "target"      => $author->id_account,
                    "target_name" => $author->display_name,
                ),
            ));
        }
        
        if($account->level >= $config::MODERATOR_USER_LEVEL)
        {
            # Set as draft
            $this->item_action_triggers[] = new action_trigger(array(
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
                $this->item_action_triggers[] = new action_trigger(array(
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
            $this->item_action_triggers[] = new action_trigger(array(
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
                $this->item_action_triggers[] = new action_trigger(array(
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
        
        $this->has_item_actions = count($this->item_action_triggers) > 0;
        
        #
        # Comments
        #
        
        $this->comments_count = (int) $post->comments_count;
        $current_module->load_extensions("json_posts_feed", "comments_forging");
        
        #
        # Collection update
        #
    }
    
    protected function get_all_countries()
    {
        global $database;
        
        $all_countries = array();
        $res = $database->query("select * from countries order by alpha_2 asc");
        while($row = $database->fetch_object($res))
            $all_countries[$row->alpha_2] = $row->name;
        
        return $all_countries;
    }
    
    /**
     * @return category_record[]
     */
    protected function get_all_categories()
    {
        $categories_repository = new categories_repository();
        
        $all_categories = array();
        $raw_categories = $categories_repository->find(array(), 0, 0, "id_category asc");
        foreach($raw_categories as $category) $all_categories[$category->id_category] = $category;
        
        return $all_categories;
    }
}
