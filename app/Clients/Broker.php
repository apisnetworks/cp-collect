<?php declare(strict_types=1);

namespace App\Clients;

use App\Concerns\NamespaceTools;
use App\Contracts\CollectionInterface;
use App\Models\Server;
use Illuminate\Support\Str;
use React\EventLoop\LoopInterface;

abstract class Broker {
	use NamespaceTools;

	public static function create(Server $s, LoopInterface $loop): CollectionClient
	{
		$auth = $s->auth;
		if (!static::clientExists($auth)) {
			throw new \RuntimeException("Unknown client type ${auth}");
		}

		$class = self::appendNamespace(Str::studly($auth));

		return new $class($s, $loop);
	}

	public static function clientExists(string $client): bool
	{
		$class = self::appendNamespace(Str::studly($client));
		return class_exists($class);
	}
}