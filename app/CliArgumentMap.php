<?php declare(strict_types=1);

namespace App;

class CliArgumentMap {
	public static function map($module, $cmd, ...$args): string
	{
		$signature = "${module}:${cmd}";
		if (empty($args)) {
			return $signature;
		}

		foreach ($args as $key => $arg) {
			$signature .= ' ' . escapeshellarg(self::flatten($arg, $key));
		}

		return $signature;
	}

	private static function flatten($arg, $key = null): string {
		if (is_null($arg)) {
			return 'null';
		}
		if (!is_array($arg)) {
			return $arg;
		}

		$str = '';
		if (\is_bool($arg)) {
			$arg = $arg ? 'true' : 'false';
		}
		if (!\is_int($key)) {
			$str .= $key . ':';
		}
		if (\is_array($arg)) {
			$str .= '[' . implode(',', array_map('self::flatten', $arg, array_keys($arg))) . ']';
		} else {
			$str .= $arg;
		}

		return $str;
	}
}