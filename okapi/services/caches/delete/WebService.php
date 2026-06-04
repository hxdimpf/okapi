<?php

namespace okapi\services\caches\delete;

use okapi\core\Db;
use okapi\core\Exception\BadRequest;
use okapi\core\Exception\InvalidParam;
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
        $user_id = $request->token->user_id;

        # Validate cache_code parameter

        $cache_code = $request->get_parameter('cache_code');
        if (empty($cache_code)) {
            throw new InvalidParam('cache_code', 'cache_code is mandatory.');
        }

        $reason = $request->get_parameter('reason');

        # Fetch the cache and verify ownership
        $cache = Db::select_assoc("
            SELECT id as cache_id, user_id, wp_oc, status
            FROM caches
            WHERE wp_oc = '".Db::escape_string($cache_code)."'
        ");

        if (!$cache) {
            throw new InvalidParam('cache_code', 'This cache does not exist.');
        }

        if ($cache['user_id'] != $user_id) {
            throw new BadRequest("You do not have permission to delete this cache. Only the cache owner can delete it.");
        }

        $cache_id = $cache['cache_id'];

        Db::execute('start transaction');

        try {
            if (Settings::get('OC_BRANCH') == 'oc.de') {
                # OCDE: Move to archive table and mark deletion date
                # Similar to how logs are archived

                Db::execute("
                    insert ignore into caches_archived
                    select
                        *,
                        '0' as deletion_date,
                        '".Db::escape_string($user_id)."' as deleted_by,
                        0 as restored_by
                    from caches
                    where id = '".Db::escape_string($cache_id)."'
                ");

                Db::execute("
                    delete from caches
                    where id = '".Db::escape_string($cache_id)."'
                ");

                # Update deletion date in archive
                Db::execute("
                    update caches_archived
                    set deletion_date = now()
                    where id = '".Db::escape_string($cache_id)."'
                ");

                # Archive associated waypoints
                Db::execute("
                    DELETE FROM coordinates
                    WHERE cache_id = '".Db::escape_string($cache_id)."' AND type = 1
                ");
            } else {
                # OCPL: Mark cache as deleted
                # Do NOT delete logs - they remain in the system as historical records

                Db::execute("
                    UPDATE caches
                    SET status = 'Archived',
                        last_modified = NOW(),
                        deleted = 1,
                        del_by_user_id = '".Db::escape_string($user_id)."'
                    WHERE id = '".Db::escape_string($cache_id)."'
                ");

                # Delete associated waypoints (not archive, just delete)
                Db::execute("
                    DELETE FROM waypoints
                    WHERE cache_id = '".Db::escape_string($cache_id)."'
                ");
            }

            Db::execute("commit");

            $result = array(
                'success' => true,
                'message' => 'Cache has been archived. Associated log entries remain in the system.'
            );
            return Okapi::formatted_response($request, $result);

        } catch (\Exception $e) {
            Db::execute("rollback");
            throw $e;
        }
    }
}
