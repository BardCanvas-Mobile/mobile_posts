<?php
/**
 * Mobile Controller Services Definition Extender
 * Registers services for the mobile controller.
 *
 * @package    BardCanvas
 * @subpackage mobile_posts
 * @author     Alejandro Caballero - lava.caballero@gmail.com
 */

use hng2_modules\mobile_posts\service;

global $config;

if( empty($config->globals["modules:mobile_controller.services_registry"]) )
    $config->globals["modules:mobile_controller.services_registry"] = array();

$scopes = array("posts_main", "posts_alt1", "posts_alt2", "posts_alt3");

foreach($scopes as $scope)
    $config->globals["modules:mobile_controller.services_registry"]["mobile_posts:{$scope}"] = new service(
        "mobile_posts",
        $scope,
        trim($this_module->language->default_service_types->{$scope}),
        trim($this_module->language->default_service_labels->{$scope}),
        "disabled"
    );
