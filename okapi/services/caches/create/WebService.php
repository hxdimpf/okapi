<?php

namespace okapi\services\caches\create;

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

        # Validate and fetch required parameters

        $cache_name = $request->get_parameter('cache_name');
        if (empty($cache_name)) {
            throw new InvalidParam('cache_name', 'cache_name is mandatory and must not be empty.');
        }
        if (strlen($cache_name) > 256) {
            throw new InvalidParam('cache_name', 'cache_name must not exceed 256 characters.');
        }

        $cache_type = $request->get_parameter('cache_type');
        if (empty($cache_type)) {
            throw new InvalidParam('cache_type', 'cache_type is mandatory.');
        }

        $latitude = $request->get_parameter('latitude');
        $longitude = $request->get_parameter('longitude');
        if ($latitude === null || $longitude === null) {
            throw new InvalidParam('latitude/longitude', 'latitude and longitude are mandatory.');
        }

        $latitude = floatval($latitude);
        $longitude = floatval($longitude);
        if ($latitude < -90 || $latitude > 90) {
            throw new InvalidParam('latitude', 'latitude must be between -90 and 90.');
        }
        if ($longitude < -180 || $longitude > 180) {
            throw new InvalidParam('longitude', 'longitude must be between -180 and 180.');
        }

        # Optional parameters

        $difficulty = $request->get_parameter('difficulty');
        if ($difficulty !== null && $difficulty !== '') {
            $difficulty = floatval($difficulty);
            if ($difficulty < 1.0 || $difficulty > 5.0) {
                throw new InvalidParam('difficulty', 'difficulty must be between 1.0 and 5.0.');
            }
        } else {
            $difficulty = null;
        }

        $terrain = $request->get_parameter('terrain');
        if ($terrain !== null && $terrain !== '') {
            $terrain = floatval($terrain);
            if ($terrain < 1.0 || $terrain > 5.0) {
                throw new InvalidParam('terrain', 'terrain must be between 1.0 and 5.0.');
            }
        } else {
            $terrain = null;
        }

        $size2 = $request->get_parameter('size2');
        if ($size2 !== null && $size2 !== '') {
            $valid_sizes = ['none', 'nano', 'micro', 'small', 'regular', 'large', 'xlarge', 'other'];
            if (!in_array($size2, $valid_sizes)) {
                throw new InvalidParam('size2', 'Invalid size2 value.');
            }
        } else {
            $size2 = 'small'; # default
        }

        $short_description = $request->get_parameter('short_description');
        if ($short_description !== null && strlen($short_description) > 255) {
            throw new InvalidParam('short_description', 'short_description must not exceed 255 characters.');
        }

        $description = $request->get_parameter('description');
        $hint2 = $request->get_parameter('hint2');

        $date_hidden = $request->get_parameter('date_hidden');
        if (!$date_hidden) {
            $date_hidden = date('Y-m-d');
        }

        $req_passwd = $request->get_parameter('req_passwd');
        if ($req_passwd !== null && $req_passwd !== '') {
            $req_passwd = ($req_passwd === 'true' || $req_passwd === '1' || $req_passwd === true) ? 1 : 0;
        } else {
            $req_passwd = 0;
        }

        # Prepare cache insertion

        $cache_code = self::generate_cache_code();

        # Convert difficulty/terrain from floats (1.0-5.0) to tinyint (10-50)
        $difficulty_int = $difficulty !== null ? (int)($difficulty * 10) : 25; # default 2.5
        $terrain_int = $terrain !== null ? (int)($terrain * 10) : 20; # default 2.0

        # Convert size2 to size (tinyint) - simplified mapping
        $size_int = 3; # default small
        if ($size2) {
            $size_map = ['none' => 0, 'nano' => 1, 'micro' => 2, 'small' => 3, 'regular' => 4, 'large' => 5, 'xlarge' => 6, 'other' => 7];
            $size_int = isset($size_map[$size2]) ? $size_map[$size2] : 3;
        }

        Db::execute('start transaction');

        try {
            # Insert cache record
            # Note: description/hint are stored in separate tables, not in the main caches table
            $insert_sql = "
                INSERT INTO caches (
                    wp_oc, name, latitude, longitude, user_id, type,
                    difficulty, terrain, size, date_hidden, status,
                    date_created, last_modified, uuid
                ) VALUES (
                    '".Db::escape_string($cache_code)."',
                    '".Db::escape_string($cache_name)."',
                    '".Db::escape_string($latitude)."',
                    '".Db::escape_string($longitude)."',
                    '".Db::escape_string($user_id)."',
                    '".self::get_type_code($cache_type)."',
                    '".Db::escape_string($difficulty_int)."',
                    '".Db::escape_string($terrain_int)."',
                    '".Db::escape_string($size_int)."',
                    '".Db::escape_string($date_hidden)."',
                    '".self::get_status_code('Available')."',
                    NOW(),
                    NOW(),
                    UUID()
                )
            ";

            Db::query($insert_sql);
            $cache_id = Db::last_insert_id();

            # Handle alternative waypoints
            $alt_wpts_json = $request->get_parameter('alt_wpts');
            $alt_wpts_count = 0;

            if ($alt_wpts_json) {
                $alt_wpts = json_decode($alt_wpts_json, true);
                if (!is_array($alt_wpts)) {
                    throw new InvalidParam('alt_wpts', 'alt_wpts must be a valid JSON array.');
                }

                foreach ($alt_wpts as $wpt) {
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

                    self::insert_waypoint($cache_id, $wpt_name, $wpt_lat, $wpt_lon, $wpt_type, $wpt_desc);
                    $alt_wpts_count++;
                }
            }

            Db::execute("commit");

            # Generate cache URL
            $site_url = Settings::get('SITE_URL');
            $url = $site_url . 'cache.php?id=' . urlencode($cache_code);

            $result = array(
                'success' => true,
                'message' => 'Cache created successfully.',
                'cache_code' => $cache_code,
                'cache_id' => $cache_id,
                'alt_wpts_count' => $alt_wpts_count,
                'url' => $url
            );
            return Okapi::formatted_response($request, $result);

        } catch (\Exception $e) {
            Db::execute("rollback");
            throw $e;
        }
    }

    private static function generate_cache_code()
    {
        # Generate a unique cache code in the format OC-XXXXX
        # This is a simplified approach; actual implementation may vary by installation

        for ($attempts = 0; $attempts < 10; $attempts++) {
            $code = 'OC' . strtoupper(substr(md5(uniqid() . time() . rand()), 0, 5));
            $exists = Db::select_value("SELECT COUNT(*) FROM caches WHERE wp_oc = '".Db::escape_string($code)."'");
            if ($exists == 0) {
                return $code;
            }
        }

        throw new BadRequest("Unable to generate unique cache code. Please try again.");
    }

    private static function get_type_code($cache_type)
    {
        # Map cache type names to tinyint codes
        # These codes vary by installation, so this is a best-effort mapping
        $type_map = [
            'Traditional' => 1,
            'Multi' => 2,
            'Quiz' => 3,
            'Moving' => 4,
            'Virtual' => 5,
            'Webcam' => 6,
            'Event' => 7,
            'Other' => 8,
            'Own' => 9,
            'Podcast' => 10,
        ];
        return isset($type_map[$cache_type]) ? $type_map[$cache_type] : 8; # default 'Other'
    }

    private static function get_status_code($status)
    {
        # Map cache status to tinyint codes
        $status_map = [
            'Available' => 1,
            'Temporarily unavailable' => 2,
            'Archived' => 3,
        ];
        return isset($status_map[$status]) ? $status_map[$status] : 1;
    }

    private static function insert_waypoint($cache_id, $name, $latitude, $longitude, $type, $description)
    {
        # Store waypoint according to OC branch
        # For now, we'll use a generic approach; this may need adjustment based on actual schema

        if (Settings::get('OC_BRANCH') == 'oc.de') {
            # OCDE uses coordinates table with type=1 for waypoints
            # Map OKAPI type to internal_type_id
            $type_map = [
                'parking' => 1,
                'stage' => 2,
                'path' => 3,
                'final' => 4,
                'poi' => 5,
                'other' => 0
            ];
            $subtype = isset($type_map[$type]) ? $type_map[$type] : 0;

            Db::query("
                INSERT INTO coordinates (cache_id, type, subtype, latitude, longitude, description)
                VALUES (
                    '".Db::escape_string($cache_id)."',
                    1,
                    '".Db::escape_string($subtype)."',
                    '".Db::escape_string($latitude)."',
                    '".Db::escape_string($longitude)."',
                    '".Db::escape_string($description)."'
                )
            ");
        } else {
            # OCPL uses waypoints table
            # Map OKAPI type to internal type_id
            $type_map = [
                'physical-stage' => 1,
                'virtual-stage' => 2,
                'final' => 3,
                'poi' => 4,
                'parking' => 5,
                'trailhead' => 6,
                'other' => 0
            ];
            $internal_type = isset($type_map[$type]) ? $type_map[$type] : 0;

            # Extract stage number if present in name (e.g., "S1" -> stage 1)
            $stage = preg_match('/S(\d+)/', $name, $matches) ? $matches[1] : 0;

            Db::query("
                INSERT INTO waypoints (cache_id, stage, latitude, longitude, type, `desc`, status)
                VALUES (
                    '".Db::escape_string($cache_id)."',
                    '".Db::escape_string($stage)."',
                    '".Db::escape_string($latitude)."',
                    '".Db::escape_string($longitude)."',
                    '".Db::escape_string($internal_type)."',
                    '".Db::escape_string($description)."',
                    1
                )
            ");
        }
    }
}
