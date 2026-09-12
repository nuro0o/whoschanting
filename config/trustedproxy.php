<?php

$proxyAddresses = env('TRUSTED_PROXIES', '');
if (! is_string($proxyAddresses)) {
    throw new InvalidArgumentException('TRUSTED_PROXIES must be a comma-separated string of proxy IPs/CIDRs.');
}

return [
    // Only these reverse proxies may supply the client IP through forwarded headers.
    'proxies' => array_values(array_filter(array_map('trim', explode(',', $proxyAddresses)))),
];
