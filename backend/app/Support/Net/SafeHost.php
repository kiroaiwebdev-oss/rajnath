<?php

namespace App\Support\Net;

/**
 * SSRF guard for admin-entered hostnames (e.g. SMTP host). Rejects hosts that resolve to
 * loopback, private, link-local, carrier-grade NAT, multicast or reserved addresses, unless the
 * operator explicitly allows private hosts via ARJ_ALLOW_PRIVATE_PROVIDER_HOSTS=true (for a mail
 * relay on the same VPS/LAN).
 */
class SafeHost
{
    /** @return string|null error message, or null when the host is acceptable */
    public static function check(string $host, ?callable $resolver = null): ?string
    {
        $host = strtolower(trim($host));
        if ($host === '' || strlen($host) > 253) {
            return 'Enter a valid host name.';
        }
        if (! filter_var($host, FILTER_VALIDATE_IP) && ! preg_match('/^(?=.{1,253}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $host)) {
            return 'Enter a valid host name (for example smtp.zoho.in).';
        }
        if (config('services.providers.allow_private_hosts')) {
            return null;
        }
        $resolver ??= fn (string $h) => self::resolve($h);
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : $resolver($host);
        if ($ips === []) {
            return 'This host name could not be resolved.';
        }
        foreach ($ips as $ip) {
            if (! self::isPublic($ip)) {
                return 'This host points to a private or reserved network address, which is not allowed.';
            }
        }

        return null;
    }

    public static function isPublic(string $ip): bool
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }
        // Ranges PHP's flags do not cover.
        foreach (['100.64.0.0/10', '192.0.0.0/24', '198.18.0.0/15', '224.0.0.0/4', 'fc00::/7', 'fe80::/10', 'ff00::/8'] as $cidr) {
            if (self::inCidr($ip, $cidr)) {
                return false;
            }
        }

        return true;
    }

    private static function inCidr(string $ip, string $cidr): bool
    {
        [$net, $bits] = explode('/', $cidr);
        $a = inet_pton($ip);
        $b = inet_pton($net);
        if ($a === false || $b === false || strlen($a) !== strlen($b)) {
            return false;
        }
        $bytes = intdiv((int) $bits, 8);
        $rem = (int) $bits % 8;
        if (substr($a, 0, $bytes) !== substr($b, 0, $bytes)) {
            return false;
        }
        if ($rem === 0) {
            return true;
        }
        $mask = chr(0xFF << (8 - $rem) & 0xFF);

        return (($a[$bytes] & $mask) === ($b[$bytes] & $mask));
    }

    private static function resolve(string $host): array
    {
        $ips = [];
        foreach ([DNS_A, DNS_AAAA] as $type) {
            foreach (@dns_get_record($host, $type) ?: [] as $r) {
                $ips[] = $r['ip'] ?? $r['ipv6'] ?? null;
            }
        }

        return array_values(array_filter($ips));
    }
}
