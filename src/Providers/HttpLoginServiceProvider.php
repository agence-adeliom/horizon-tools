<?php

declare(strict_types=1);

namespace Adeliom\SageTools\Providers;

use Roots\Acorn\Sage\SageServiceProvider;

class HttpLoginServiceProvider extends SageServiceProvider
{
	/**
	 * Perform any additional boot required for this application
	 */
	public function boot(): void
	{
		if (!defined('ENABLED_HTTP_LOGIN') || (defined('ENABLED_HTTP_LOGIN') && !ENABLED_HTTP_LOGIN)) {
			return;
		}

		if (php_sapi_name() === "cli") {
			return;
		}

		if (strpos($_SERVER["REQUEST_URI"], "wc-api") !== false || strpos($_SERVER["REQUEST_URI"], "wp-cron") !== false) {
			return;
		}

		if (in_array($_SERVER['REMOTE_ADDR'], $this->getWhitelist())) {
			return;
		}

		$realm = 'Website';
		if (defined('HTTP_REALM')) {
			$realm = HTTP_REALM;
		}
		$realm = apply_filters("http_login_realm", $realm);

		$user = 'adeliom';
		if (defined('HTTP_USER')) {
			$user = HTTP_USER;
		}

		$passwd = password_hash(sprintf('@deliom%s!', date('Y')), PASSWORD_DEFAULT);
		if (defined('HTTP_PASSWD')) {
			$passwd = HTTP_PASSWD;
		}

		$valid_passwords = [ $user => $passwd ];
		$valid_passwords = apply_filters("http_login_accounts", $valid_passwords);
		$valid_users = array_keys($valid_passwords);

		$user = $_SERVER['PHP_AUTH_USER'] ?? null;
		$pass = $_SERVER['PHP_AUTH_PW'] ?? null;

		$validated = (in_array($user, $valid_users)) && (password_verify($pass, $valid_passwords[$user]));

		if (!$validated) {
			header(sprintf('WWW-Authenticate: Basic realm="%s"', $realm));
			header('HTTP/1.0 401 Unauthorized');
			die("Not authorized");
		}
	}

	private function getWhitelist()
	{
		$whitelist = ['127.0.0.1'];
		if (defined('HTTP_WHITELIST')) {
			if (is_array(HTTP_WHITELIST)) {
				$config = HTTP_WHITELIST;
				$list = [];
				array_walk_recursive($config, function ($a) use (&$list) {
					$list[] = $a;
				});
				$whitelist = array_merge($whitelist, $list);
			} elseif (is_string(HTTP_WHITELIST)) {
				$whitelist = array_merge($whitelist, explode(",", HTTP_WHITELIST));
				$whitelist = array_merge($whitelist, explode(";", HTTP_WHITELIST));
				$whitelist = array_merge($whitelist, explode("|", HTTP_WHITELIST));
			}
		}
		return $whitelist;
	}
}
