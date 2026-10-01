<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Traffic Guard & Adaptive Load Shedding Configuration
    |--------------------------------------------------------------------------
    |
    | Designed specifically for resource-constrained environments (e.g., 1 vCPU / 1GB RAM).
    | When concurrent requests exceed the threshold or CPU load spikes,
    | the system drops (sheds) unauthenticated guest requests to keep the server
    | responsive and protects active/logged-in users from downtime.
    |
    */

    // Enable or disable the traffic guard
    'enabled' => env('TRAFFIC_GUARD_ENABLED', true),

    // Maximum concurrent requests before load shedding kicks in.
    // For 1 vCPU with 2 Octane workers, 20-25 is the optimal safe ceiling.
    'max_concurrent_requests' => (int) env('TRAFFIC_GUARD_MAX_CONCURRENT', 25),

    // CPU 1-minute load average threshold on a 1-core machine
    'cpu_load_threshold' => (float) env('TRAFFIC_GUARD_LOAD_THRESHOLD', 2.8),

    // Suggested retry time (in seconds) sent in Retry-After header
    'retry_after' => (int) env('TRAFFIC_GUARD_RETRY_AFTER', 5),
];
