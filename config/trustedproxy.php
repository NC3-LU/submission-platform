<?php

$configuredProxies = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('TRUSTED_PROXIES', ''))
)));

return [
    // Trust no forwarded client information unless the deployment explicitly
    // lists its reverse proxy addresses or CIDR ranges.
    'proxies' => $configuredProxies === [] ? null : $configuredProxies,
];
