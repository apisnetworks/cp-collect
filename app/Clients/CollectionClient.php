<?php declare(strict_types=1);

	namespace App\Clients;

	use App\Models\Server;
	use React\EventLoop\LoopInterface;

	abstract class CollectionClient {

		public const COLLECTION_CMD = [
			'admin',   // module
			'collect', // method
			[
				// service arguments
				'aliases.aliases',
				'billing.invoice',
				'billing.parent_invoice',
				'siteinfo.domain',
				'siteinfo.email'
			]
		];

		/**
		 * @var Server
		 */
		protected $server;
		/**
		 * @var LoopInterface
		 */
		protected $loop;

		public function __construct(Server $s, LoopInterface $loop)
		{
			$this->server = $s;
			$this->loop = $loop;
		}

		public function getName(): string
		{
			return $this->server->server_name;
		}

		abstract public function test(): bool;

}