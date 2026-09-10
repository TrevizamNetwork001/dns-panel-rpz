<?php

return [
    'max_cidr_ips' => (int) env('RBL_MAX_CIDR_IPS', 8),
    'alerts_enabled' => (bool) env('RBL_ALERTS_ENABLED', false),
    'alert_on_listed' => (bool) env('RBL_ALERT_ON_LISTED', true),
    'alert_on_resolved' => (bool) env('RBL_ALERT_ON_RESOLVED', true),
    'alert_channels' => ['telegram'],
    'include_response_codes' => (bool) env('RBL_ALERT_INCLUDE_RESPONSE_CODES', true),
    'include_group' => (bool) env('RBL_ALERT_INCLUDE_GROUP', true),
];
