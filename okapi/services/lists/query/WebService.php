<?php

namespace okapi\services\lists\query;

use okapi\core\Db;
use okapi\core\Okapi;
use okapi\core\Request\OkapiRequest;
use okapi\Settings;

class WebService
{
    public static function options()
    {
        return array(
            'min_auth_level' => 3
        );
    }

    public static function call(OkapiRequest $request)
    {
        $result = array(
            'success' => false
        );

        if (Settings::get('OC_BRANCH') == 'oc.de')
        {
            $user_id = $request->token->user_id;
            $rs = Db::query("
                SELECT
                    id,
                    name,
                    date_created,
                    last_modified,
                    last_added,
                    description,
                    is_public,
                    (
                        SELECT COUNT(*)
                        FROM cache_list_items
                        WHERE cache_list_id = cache_lists.id
                    ) AS caches_count,
                    (
                        SELECT COUNT(*)
                        FROM cache_list_watches
                        WHERE cache_list_id = cache_lists.id
                    ) AS watches_count
                FROM cache_lists
                WHERE user_id = '".Db::escape_string($user_id)."'
            ");

            $lists = [];
            while ($list = Db::fetch_assoc($rs))
            {
                $lists[] = $list;
            }

            $result = array(
                'success' => true,
                'lists' => $lists
            );
        }
        return Okapi::formatted_response($request, $result);
    }
}
