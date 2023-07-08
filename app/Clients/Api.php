<?php declare(strict_types=1);

	namespace App\Clients;

	use App\Contracts\CollectionInterface;
	use App\Models\Server;
	use App\Regex;
	use Clue\React\Soap\Client;
	use Clue\React\Soap\Proxy;
	use Exception;
	use Psr\Http\Message\ResponseInterface;
	use React\Dns\Config\Config as DnsConfig;
	use React\Dns\Config\HostsFile;
	use React\Dns\Query\ExecutorInterface;
	use React\Dns\Query\HostsFileExecutor;
	use React\Dns\Query\UdpTransportExecutor;
	use React\Dns\Resolver\Resolver;
	use React\EventLoop\LoopInterface;
	use React\Http\Browser;
	use React\Promise\Promise;
	use React\Socket\Connector;
	use SoapClient;

	class Api extends CollectionClient
	{

		const DEFAULT_SERVER = 'localhost';
		const PORT = 2082;
		const SECURE_PORT = 2083;
		const WSDL_PATH = 'apnscp.wsdl';
		/**
		 * @var Api|SoapClient
		 */
		protected $callee;

		public function __construct(Server $s, LoopInterface $loop)
		{
			parent::__construct($s, $loop);
			$port = self::SECURE_PORT;
			$host = $s->server_name;
			if (false !== strpos($host, ':')) {
				[$host, $port] = explode($host, ':');
			}
			if ($host && false === strpos($host, '.')) {
				$host .= '.' . (env('DOMAIN') ?: rtrim(shell_exec("dnsdomainname")));
			}
			$this->create($s->auth_key, $host, $port);
		}

		/**
		 * Create new API client
		 *
		 * @param       $key
		 * @param null  $host
		 * @param null  $port
		 * @param array $ctor additional constructor arguments to SoapClient
		 * @return self
		 */
		private function create(string $key, string $host = null, int $port = self::PORT, array $ctor = [])
		{
			if (!$host) {
				$host = self::DEFAULT_SERVER . ':' . self::PORT;
			} else {
				$host .= ':' . $port;
			}
			$proto = $port === self::PORT ? 'http' : 'https';
			$uri = $proto . '://' . $host . '/soap';
			$wsdl = str_replace('/soap', '/' . self::WSDL_PATH, $uri);

			$connopts = $ctor + array(
					'connection_timeout' => 30,
					'location'           => $uri,
					'uri'                => 'urn:apnscp.api.soap',
					'trace'              => true
				);
			$connopts['location'] = $uri;
			$connector = new Connector($this->loop, [
				'dns' => (new Resolver($this->stubHostsFile(strtok($host, ':'), $this->server->ip)))
			]);

			$browser = (new Browser($this->loop, $connector))->withHeader('Auth-Key', $key);

			return $browser->get($wsdl)->then(static function (ResponseInterface $response) use ($browser, $connopts) {
				return new Client($browser, (string)$response->getBody(), $connopts);
			})->done(function (Client $client) {
				$this->callee = new Proxy($client);
			}, function (Exception $e) {
				echo $e->getMessage(), "\n\n", $e->getTraceAsString();
				exit(1);
			});
		}

		private function stubHostsFile(string $domain, ?string $ip): ExecutorInterface
		{
			if (!preg_match(Regex::HOSTNAME, $domain)) {
				throw new \RuntimeException('Invalid domain');
			}
			$ns = DnsConfig::loadSystemConfigBlocking()->nameservers;
			$executor = new UdpTransportExecutor($ns[array_rand($ns)], $this->loop);
			if (!$ip) {
				return $executor;
			}

			return new HostsFileExecutor(
				new HostsFile("$ip $domain"),
				$executor
			);
		}

		public function test(): bool
		{
			$resp = null;
			$this->common_whoami()->done(static function ($val) use (&$resp) {
				$resp = $val;
			}, static function (\Exception $e) {
				dd($e);
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

		public function __call($function_name, $arguments): Promise
		{
			if (null === $this->callee) {
				$this->loop->run();
			}
			static $ctr = 0;

			return $this->callee->__call($function_name, $arguments)->then(function ($ret) use (
				&$ctr,
				$function_name,
				$arguments
			) {
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

	}
