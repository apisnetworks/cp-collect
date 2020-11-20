<?php declare(strict_types=1);

    namespace App\Clients;

	use App\Contracts\CollectionInterface;
	use App\Models\Server;
	use Clue\React\Soap\Client;
	use Clue\React\Soap\Proxy;
	use Psr\Http\Message\ResponseInterface;
	use React\EventLoop\LoopInterface;
	use React\Http\Browser;
	use React\Promise\Promise;

	class Api extends CollectionClient
	{

		const DEFAULT_SERVER = 'localhost';
		const PORT = 2082;
		const SECURE_PORT = 2083;
		const WSDL_PATH = '/apnscp.wsdl';
		/**
		 * @var Api|\SoapClient
		 */
		protected $callee;
		/**
		 * @var LoopInterface
		 */
		protected $loop;

		public function __construct(Server $s, LoopInterface $loop)
		{
			parent::__construct($s, $loop);
			$this->create($s->auth_key, $s->ip ?: $s->server_name);
		}

		/**
		 * Create new API client
		 *
		 * @param       $key
		 * @param null  $server
		 * @param null  $port
		 * @param array $ctor additional constructor arguments to SoapClient
		 * @return self
		 */
		private function create($key, $server = null, $port = null, array $ctor = [])
		{
			if (!$server) {
				$server = self::DEFAULT_SERVER . ':' . self::PORT;
			} else {
				if (!$port) {
					$port = self::PORT;
				}
				$server .= ':' . $port;
			}
			$uri = 'http://' . $server . '/soap';
			$wsdl = str_replace('/soap', '/' . self::WSDL_PATH, $uri);
			$connopts = $ctor + array(
				'connection_timeout' => 30,
				'location'           => $uri,
				'uri'                => 'urn:apnscp.api.soap',
				'trace'              => true
			);
			$connopts['location'] = $uri . '?authkey=' . $key;
			$browser = new Browser($this->loop);

			$browser->get($wsdl)->then(static function (ResponseInterface $response) use ($browser, $connopts) {
				return new Client($browser, (string)$response->getBody(), $connopts);
			})->done(function ($client) {
				$this->callee = new Proxy($client);
			});

			return $browser;
		}

		public function __call($function_name, $arguments): Promise
		{
			if (null === $this->callee) {
				$this->loop->run();
			}
			static $ctr = 0;
			return $this->callee->__call($function_name, $arguments)->then(function ($ret) use (&$ctr, $function_name, $arguments) {
				if ($ret !== null || $ctr >= 5) {
					$ctr = 0;

					return $ret;
				}
				// 50 ms sleep
				usleep(50000);
				$ctr++;

				return $this->__call($function_name, $arguments);
			});

		}

		public function test(): bool
		{
			$resp = null;
			$this->common_whoami()->done(static function($val) use (&$resp) {
				$resp = $val;
			});
			$this->loop->run();
			return (bool)$resp;
		}


		public function collect(): Promise
		{
			$cmd = CollectionClient::COLLECTION_CMD;
			$signature = $cmd[0] . '_' . $cmd[1];
			$args = array_slice($cmd, 2);
			return $this->__call($signature, $args);
		}

	}
