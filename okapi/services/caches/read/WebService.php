<?php

namespace okapi\services\caches\read;

use okapi\core\Exception\InvalidParam;
use okapi\core\Exception\ParamMissing;
use okapi\core\Okapi;
use okapi\core\OkapiServiceRunner;
use okapi\core\Request\OkapiInternalRequest;
use okapi\core\Request\OkapiRequest;

class WebService
{
    public static function options()
    {
        return array(
            'min_auth_level' => 1
        );
    }

    public static function call(OkapiRequest $request)
    {
        # This service delegates to the existing geocaches service for consistency
        # and to reuse the comprehensive cache data retrieval logic.

        $cache_code = $request->get_parameter('cache_code');
        if (!$cache_code) throw new ParamMissing('cache_code');
        if (strpos($cache_code, "|") !== false) throw new InvalidParam('cache_code', 'Invalid cache code format.');

        $fields = $request->get_parameter('fields');
        if (!$fields) $fields = "code|name|location|type|status|difficulty|terrain|owner";

        $owner_fields = $request->get_parameter('owner_fields');
        if (!$owner_fields) $owner_fields = "uuid|username|profile_url";

        $langpref = $request->get_parameter('langpref');
        if (!$langpref) $langpref = "en";

        # Call the existing geocaches service
        $params = array(
            'cache_codes' => $cache_code,
            'fields' => $fields,
            'owner_fields' => $owner_fields,
            'langpref' => $langpref,
        );

        # Pass through optional parameters if provided
        $my_location = $request->get_parameter('my_location');
        if ($my_location)
            $params['my_location'] = $my_location;

        $user_uuid = $request->get_parameter('user_uuid');
        if ($user_uuid)
            $params['user_uuid'] = $user_uuid;

        $lpc = $request->get_parameter('lpc');
        if ($lpc)
            $params['lpc'] = $lpc;

        $log_fields = $request->get_parameter('log_fields');
        if ($log_fields)
            $params['log_fields'] = $log_fields;

        $log_user_fields = $request->get_parameter('log_user_fields');
        if ($log_user_fields)
            $params['log_user_fields'] = $log_user_fields;

        $user_logs_only = $request->get_parameter('user_logs_only');
        if ($user_logs_only !== null)
            $params['user_logs_only'] = $user_logs_only;

        $results = OkapiServiceRunner::call('services/caches/geocaches', new OkapiInternalRequest(
            $request->consumer, $request->token, $params));

        $result = $results[$cache_code];
        if ($result === null)
            throw new InvalidParam('cache_code', "This cache does not exist or is not accessible.");

        return Okapi::formatted_response($request, $result);
    }
}
