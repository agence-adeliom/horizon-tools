<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Providers;

use Roots\Acorn\Sage\SageServiceProvider;

class AdminIpRestrictionServiceProvider extends SageServiceProvider
{
    public function boot(): void
    {
        $allowedIps = $this->getAllowedIps();

        // Si la liste est vide ou non définie, on n'applique aucune restriction
        if (empty($allowedIps)) {
            return;
        }

        // Pas de restriction en CLI
        if (php_sapi_name() === 'cli') {
            return;
        }

        $callback = function () use ($allowedIps): void {
            if (defined('DOING_CRON') && DOING_CRON) {
                return;
            }

            if (defined('WP_CLI') && WP_CLI) {
                return;
            }

            $clientIp = $this->getClientIp();

            if (!$this->isIpAllowed($clientIp, $allowedIps)) {
                wp_die(
                    sprintf(
                        __('Accès refusé. Votre adresse IP (%s) n’est pas autorisée à accéder à cette zone.', 'horizon-tools'),
                        esc_html($clientIp)
                    ),
                    __('Accès interdit', 'horizon-tools'),
                    ['response' => 403]
                );
            }
        };

        // Page de connexion (wp-login.php)
        add_action('login_init', $callback, 1);

        // Back-office WordPress (wp-admin/*)
        add_action('admin_init', $callback, 1);
    }

    private function getAllowedIps(): array
    {
        if (!defined('ADMIN_ALLOWED_IPS')) {
            return [];
        }

        if (is_array(ADMIN_ALLOWED_IPS)) {
            $list = [];
            $config = ADMIN_ALLOWED_IPS;
            array_walk_recursive($config, function ($ip) use (&$list): void {
                $list[] = trim((string) $ip);
            });
            return array_values(array_filter($list));
        }

        if (is_string(ADMIN_ALLOWED_IPS) && ADMIN_ALLOWED_IPS !== '') {
            $ips = preg_split('/[,;|]/', ADMIN_ALLOWED_IPS) ?: [];
            return array_values(array_filter(array_map('trim', $ips)));
        }

        return [];
    }

    /**
     * Retourne l'IP cliente.
     *
     * Par défaut, utilise REMOTE_ADDR (connexion directe).
     * Si ADMIN_IP_TRUST_PROXY est défini et vrai, lit les headers de forwarding
     * (X-Real-IP puis X-Forwarded-For). À n'activer que si le proxy amont
     * écrase lui-même ces headers pour éviter le spoofing.
     */
    private function getClientIp(): string
    {
        if (defined('ADMIN_IP_TRUST_PROXY') && ADMIN_IP_TRUST_PROXY) {
            if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
                return trim($_SERVER['HTTP_X_REAL_IP']);
            }

            if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
                return trim($ips[0]);
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '';
    }

    private function isIpAllowed(string $clientIp, array $allowedIps): bool
    {
        foreach ($allowedIps as $allowedIp) {
            if ($clientIp === $allowedIp) {
                return true;
            }

            if (str_contains($allowedIp, '/') && $this->ipInCidr($clientIp, $allowedIp)) {
                return true;
            }
        }

        return false;
    }

    private function ipInCidr(string $ip, string $cidr): bool
    {
        [$subnet, $prefixStr] = explode('/', $cidr, 2);
        $prefix = (int) $prefixStr;

        $ipBin = inet_pton($ip);
        $subnetBin = inet_pton($subnet);

        if ($ipBin === false || $subnetBin === false) {
            return false;
        }

        $length = strlen($ipBin);

        $mask = '';
        for ($i = 0; $i < $length; $i++) {
            $bits = max(0, min(8, $prefix - $i * 8));
            $mask .= chr($bits > 0 ? 0xff & (0xff << 8 - $bits) : 0x00);
        }

        return ($ipBin & $mask) === ($subnetBin & $mask);
    }
}
