<?php

return [
    'max_cidr_ips' => (int) env('RBL_MAX_CIDR_IPS', 8),
    'large_cidr_enabled' => (bool) env('RBL_LARGE_CIDR_ENABLED', true),
    'max_cidr_total_ips' => (int) env('RBL_MAX_CIDR_TOTAL_IPS', 1024),
    'min_cidr_prefix' => (int) env('RBL_MIN_CIDR_PREFIX', 22),
    'batch_ips_per_run' => (int) env('RBL_BATCH_IPS_PER_RUN', 8),
    'max_checks_per_target' => (int) env('RBL_MAX_CHECKS_PER_TARGET', 40),
    'max_seconds_per_target' => (int) env('RBL_MAX_SECONDS_PER_TARGET', 20),
    'alerts_enabled' => (bool) env('RBL_ALERTS_ENABLED', false),
    'alert_on_listed' => (bool) env('RBL_ALERT_ON_LISTED', true),
    'alert_on_resolved' => (bool) env('RBL_ALERT_ON_RESOLVED', true),
    'alert_channels' => ['telegram'],
    'include_response_codes' => (bool) env('RBL_ALERT_INCLUDE_RESPONSE_CODES', true),
    'include_group' => (bool) env('RBL_ALERT_INCLUDE_GROUP', true),
];
