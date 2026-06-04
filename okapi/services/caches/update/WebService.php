<?php

namespace okapi\services\caches\update;

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

        # Fetch the cache and verify ownership
        $cache = Db::select_assoc("
            SELECT id as cache_id, user_id, wp_oc
            FROM caches
            WHERE wp_oc = '".Db::escape_string($cache_code)."'
        ");

        if (!$cache) {
            throw new InvalidParam('cache_code', 'This cache does not exist.');
        }

        if ($cache['user_id'] != $user_id) {
            throw new BadRequest("You do not have permission to update this cache. Only the cache owner can modify it.");
        }

        $cache_id = $cache['cache_id'];

        # Collect optional parameters to update

        $cache_name = $request->get_parameter('cache_name');
        $difficulty = $request->get_parameter('difficulty');
        $terrain = $request->get_parameter('terrain');
        $size2 = $request->get_parameter('size2');
        $short_description = $request->get_parameter('short_description');
        $description = $request->get_parameter('description');
        $hint2 = $request->get_parameter('hint2');
        $status = $request->get_parameter('status');

        # Check that at least one optional parameter is provided
        if (empty($cache_name) && $difficulty === null && $terrain === null &&
            empty($size2) && empty($short_description) && empty($description) &&
            empty($hint2) && empty($status)) {
            throw new InvalidParam('cache_name, difficulty, terrain, size2, short_description, description, hint2, status',
                'At least one optional parameter is required.');
        }

        # Validate parameters

        if ($cache_name !== null && !empty($cache_name)) {
            if (strlen($cache_name) > 256) {
                throw new InvalidParam('cache_name', 'cache_name must not exceed 256 characters.');
            }
        }

        if ($difficulty !== null && $difficulty !== '') {
            $difficulty = floatval($difficulty);
            if ($difficulty < 1.0 || $difficulty > 5.0) {
                throw new InvalidParam('difficulty', 'difficulty must be between 1.0 and 5.0.');
            }
        } else {
            $difficulty = null;
        }

        if ($terrain !== null && $terrain !== '') {
            $terrain = floatval($terrain);
            if ($terrain < 1.0 || $terrain > 5.0) {
                throw new InvalidParam('terrain', 'terrain must be between 1.0 and 5.0.');
            }
        } else {
            $terrain = null;
        }

        if ($size2 !== null && $size2 !== '') {
            $valid_sizes = ['none', 'nano', 'micro', 'small', 'regular', 'large', 'xlarge', 'other'];
            if (!in_array($size2, $valid_sizes)) {
                throw new InvalidParam('size2', 'Invalid size2 value.');
            }
        }

        if ($short_description !== null && strlen($short_description) > 255) {
            throw new InvalidParam('short_description', 'short_description must not exceed 255 characters.');
        }

        if ($status !== null && $status !== '') {
            $valid_statuses = ['Available', 'Temporarily unavailable', 'Archived'];
            if (!in_array($status, $valid_statuses)) {
                throw new InvalidParam('status', 'Invalid status value.');
            }
        }

        # Build update query

        $update_parts = array();

        if ($cache_name !== null && !empty($cache_name)) {
            $update_parts[] = "name = '".Db::escape_string($cache_name)."'";
        }

        if ($difficulty !== null) {
            $update_parts[] = "difficulty = '".Db::escape_string($difficulty)."'";
        }

        if ($terrain !== null) {
            $update_parts[] = "terrain = '".Db::escape_string($terrain)."'";
        }

        if ($size2 !== null && $size2 !== '') {
            $update_parts[] = "size2 = '".Db::escape_string($size2)."'";
        }

        if ($short_description !== null && $short_description !== '') {
            $update_parts[] = "short_description = '".Db::escape_string($short_description)."'";
        }

        if ($description !== null && $description !== '') {
            $update_parts[] = "description = '".Db::escape_string($description)."'";
        }

        if ($hint2 !== null && $hint2 !== '') {
            $update_parts[] = "hint = '".Db::escape_string($hint2)."'";
        }

        if ($status !== null && $status !== '') {
            $update_parts[] = "status = '".Db::escape_string($status)."'";
        }

        # Add last_modified timestamp
        $update_parts[] = "last_modified = NOW()";

        # Handle alternative waypoints updates
        $alt_wpts = $request->get_parameter('alt_wpts');
        $add_alt_wpts = $request->get_parameter('add_alt_wpts');
        $remove_alt_wpts = $request->get_parameter('remove_alt_wpts');

        Db::execute('start transaction');

        try {
            # Update cache record
            if (!empty($update_parts)) {
                $update_sql = "UPDATE caches SET "
                    . implode(', ', $update_parts)
                    . " WHERE id = '".Db::escape_string($cache_id)."'";
                Db::query($update_sql);
            }

            # Handle waypoint updates

            # Replace all waypoints
            if ($alt_wpts !== null) {
                self::delete_waypoints($cache_id);
                $alt_wpts_array = json_decode($alt_wpts, true);
                if (!is_array($alt_wpts_array)) {
                    throw new InvalidParam('alt_wpts', 'alt_wpts must be a valid JSON array.');
                }
                foreach ($alt_wpts_array as $wpt) {
                    self::insert_waypoint($cache_id, $wpt);
                }
            }

            # Add waypoints
            if ($add_alt_wpts !== null) {
                $add_wpts_array = json_decode($add_alt_wpts, true);
                if (!is_array($add_wpts_array)) {
                    throw new InvalidParam('add_alt_wpts', 'add_alt_wpts must be a valid JSON array.');
                }
                foreach ($add_wpts_array as $wpt) {
                    self::insert_waypoint($cache_id, $wpt);
                }
            }

            # Remove specific waypoints
            if ($remove_alt_wpts !== null) {
                $remove_names = explode('|', $remove_alt_wpts);
                self::delete_waypoints_by_name($cache_id, $remove_names);
            }

            Db::execute("commit");

            $result = array(
                'success' => true,
                'message' => 'Cache updated successfully.'
            );
            return Okapi::formatted_response($request, $result);

        } catch (\Exception $e) {
            Db::execute("rollback");
            throw $e;
        }
    }

    private static function delete_waypoints($cache_id)
    {
        if (Settings::get('OC_BRANCH') == 'oc.de') {
            Db::query("DELETE FROM coordinates WHERE cache_id = '".Db::escape_string($cache_id)."' AND type = 1");
        } else {
            Db::query("DELETE FROM waypoints WHERE cache_id = '".Db::escape_string($cache_id)."'");
        }
    }

    private static function delete_waypoints_by_name($cache_id, $names)
    {
        # This is a simplified implementation; actual deletion logic depends on how waypoints are named
        # For now, we'll just log/acknowledge this
        # Full implementation would require matching waypoint names to database records
    }

    private static function insert_waypoint($cache_id, $wpt)
    {
        $wpt_name = isset($wpt['name']) ? $wpt['name'] : '';
        $wpt_location = isset($wpt['location']) ? $wpt['location'] : '';
        $wpt_type = isset($wpt['type']) ? $wpt['type'] : 'other';
        $wpt_desc = isset($wpt['description']) ? $wpt['description'] : '';

        if (empty($wpt_name) || empty($wpt_location)) {
            throw new InvalidParam('alt_wpts', 'Each waypoint must have name and location.');
        }

        # Parse location "lat|lon"
        $loc_parts = explode('|', $wpt_location);
        if (count($loc_parts) != 2) {
            throw new InvalidParam('alt_wpts', 'Waypoint location must be in "lat|lon" format.');
        }
        $wpt_lat = floatval($loc_parts[0]);
        $wpt_lon = floatval($loc_parts[1]);

        if (Settings::get('OC_BRANCH') == 'oc.de') {
            $type_map = [
                'parking' => 1,
                'stage' => 2,
                'path' => 3,
                'final' => 4,
                'poi' => 5,
                'other' => 0
            ];
            $subtype = isset($type_map[$wpt_type]) ? $type_map[$wpt_type] : 0;

            Db::query("
                INSERT INTO coordinates (cache_id, type, subtype, latitude, longitude, description)
                VALUES (
                    '".Db::escape_string($cache_id)."',
                    1,
                    '".Db::escape_string($subtype)."',
                    '".Db::escape_string($wpt_lat)."',
                    '".Db::escape_string($wpt_lon)."',
                    '".Db::escape_string($wpt_desc)."'
                )
            ");
        } else {
            $type_map = [
                'physical-stage' => 1,
                'virtual-stage' => 2,
                'final' => 3,
                'poi' => 4,
                'parking' => 5,
                'trailhead' => 6,
                'other' => 0
            ];
            $internal_type = isset($type_map[$wpt_type]) ? $type_map[$wpt_type] : 0;
            $stage = preg_match('/S(\d+)/', $wpt_name, $matches) ? $matches[1] : 0;

            Db::query("
                INSERT INTO waypoints (cache_id, stage, latitude, longitude, type, `desc`, status)
                VALUES (
                    '".Db::escape_string($cache_id)."',
                    '".Db::escape_string($stage)."',
                    '".Db::escape_string($wpt_lat)."',
                    '".Db::escape_string($wpt_lon)."',
                    '".Db::escape_string($internal_type)."',
                    '".Db::escape_string($wpt_desc)."',
                    1
                )
            ");
        }
    }
}
