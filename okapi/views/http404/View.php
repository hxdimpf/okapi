<?php

namespace okapi\views\http404;

use okapi\Core\Okapi;
use okapi\Core\Response\OkapiHttpResponse;
use okapi\Settings;
use okapi\views\menu\OkapiMenu;

class View
{
    public static function call()
    {
        $vars = array(
            'okapi_base_url' => Settings::get('SITE_URL')."okapi/",
            'menu' => OkapiMenu::get_menu_html(),
            'installations' => OkapiMenu::get_installations(),
            'okapi_rev' => Okapi::$version_number,
        );

        $response = new OkapiHttpResponse();
        $response->status = "404 Not Found";
        $response->content_type = "text/html; charset=utf-8";
        ob_start();
        include __DIR__ . '/http404.tpl.php';
        $response->body = ob_get_clean();
        return $response;
    }
}
