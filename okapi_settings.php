<?php
/***************************************************************************
 * Standalone OKAPI settings for Docker stack
 * 
 * Override with env vars where available.
 ***************************************************************************/

namespace okapi;

function get_okapi_settings()
{
    $dbPass = getenv('OKAPI_DB_PASS') ?: '65dd4741d3ed9ed2460041fb81caa992';
    $dbHost = getenv('OKAPI_DB_HOST') ?: 'db';
    $dbName = getenv('OKAPI_DB_NAME') ?: 'oc';
    $dbUser = getenv('OKAPI_DB_USER') ?: 'oc';

    date_default_timezone_set('Europe/Berlin');

    return [
        'OC_BRANCH'                 => 'oc.de',
        'ADMINS'                    => ['noreply@test.opencaching.de'],
        'FROM_FIELD'                => 'noreply@test.opencaching.de',
        'DATA_LICENSE_URL'          => 'https://opencaching.de/articles.php?page=license',
        'DEBUG'                     => true,
        'DEBUG_PREVENT_SEMAPHORES'  => true,
        'DB_SERVER'                 => $dbHost,
        'DB_NAME'                   => $dbName,
        'DB_USERNAME'               => $dbUser,
        'DB_PASSWORD'               => $dbPass,
        'DB_CHARSET'                => 'utf8mb4',
        'SITELANG'                  => 'de',
        'TIMEZONE'                  => 'Europe/Berlin',
        'SITE_URL'                  => 'https://oc3.baiti.net/',
        'REGISTRATION_URL'          => 'https://oc3.baiti.net/register.php',
        'HTTPS_ENABLED'             => false,
        'OC_NODE_ID'                => 4,
        'OC_WAYPOINT_PREFIX'        => 'OC',
        'OC_COOKIE_NAME'            => 'ocdevelopment',
        'OC_COOKIE_DOMAIN'          => '.baiti.net',
        'OC_DEFAULT_LOCALE'         => 'DE',
        'OC_PAGE_TITLE'             => 'OPENCACHING.de',
        'OC_MAIL_FROM'              => 'noreply@test.opencaching.de',
        'OC_W3W_APIKEY'             => 'X27PDW41',
        'OKAPI_PREVENT_EMAILS'      => 1,
        'VAR_DIR'                   => __DIR__ . '/var/okapi',
        'IMAGES_DIR'                => __DIR__ . '/images/uploads',
        'IMAGES_URL'                => 'https://oc3.baiti.net/images/uploads/',
        'IMAGE_MAX_UPLOAD_SIZE'     => 4194304,
        'IMAGE_MAX_PIXEL_COUNT'     => 786432,
        'SITE_LOGO'                 => 'https://oc3.baiti.net/resource2/ocstyle/images/oclogo/oc_logo_alpha3.png',
        'VERSION_FILE'              => __DIR__ . '/okapi/meta.php',
        'GITHUB_ACCESS_TOKEN'       => '',
    ];
}
