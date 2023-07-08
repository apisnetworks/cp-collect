<?php declare(strict_types=1);

	namespace App\Clients;

	use App\CliArgumentMap;
	use App\Contracts\CollectionInterface;
	use Clue\React\SshProxy\SshProcessConnector;
	use Exception;
	use React\ChildProcess\Process;
	use React\Promise\Deferred;
	use React\Promise\Promise;
	use RuntimeException;

	class Ssh extends CollectionClient
	{

		/**
		 * @var Process
		 */
		protected $client;

		/**
		 * @var resource
		 */
		protected $fp;

		public function __destruct()
		{
			$this->disconnect();
		}

		/**
		 * Disconnect from server
		 *
		 * @return void
		 */
		private function disconnect(): void
		{
			if (!is_null($this->client)) {
				$this->client->close();
				$this->client = null;
			}
		}

		public function collect(): Promise
		{
			$cmd = '. /etc/sysconfig/apnscp ; /bin/sh -c "${APNSCP_ROOT:-/usr/local/apnscp}"' . escapeshellarg("/bin/cmd -o json " . $this->getCommand());
			$deferred = new Deferred();
			$err = $data = '';

			$this->connect($cmd)->stdout->on('data', static function ($chunk) use (&$data) {
				$data .= $chunk;
			});

			$this->client->stderr->on('data', static function ($chunk) use (&$err) {
				$err .= $chunk;
			});

			$this->client->on('exit', static function ($exit) use ($deferred, &$data, &$err) {
				$json = json_decode($data, true);
				if ($exit !== 0) {
					return $deferred->reject(new RuntimeException($err));
				}
				if (null === $json) {

					throw new RuntimeException("Malformed JSON encountered: " . $json);
				}
				$deferred->resolve($json);
			});

			return $deferred->promise();
		}

		protected function getCommand(): string
		{
			return CliArgumentMap::map(...CollectionClient::COLLECTION_CMD);
		}

		/**
		 * Connect over SSH
		 *
		 * @return Process
		 */
		private function connect($command = '/bin/true'): Process
		{
			$host = $this->server->ip ?? $this->server->server_name;
			$port = 22;
			if (false !== strpos($host, ':')) {
				[$host, $port] = explode($host, ':');
			}
			if (!is_readable($key = $this->getKeyPath())) {
				throw new Exception("Cannot access key file ${key}");
			}

			$opts = [
				'PreferredAuthentications' => 'publickey',
				'PubkeyAuthentication'     => 'yes',
				'UserKnownHostsFile'       => '/dev/null',
				'StrictHostKeyChecking'    => 'no'
			];

			$opts = array_map(static function ($v, $k) {
				return '-o ' . $k . '=' . escapeshellarg($v);
			}, $opts, array_keys($opts));
			$command = 'ssh ' .
				implode(' ', $opts) .
				' -p ' . (int)$port .
				' -i ' . escapeshellarg($key) .
				' ' . escapeshellarg($this->getUsername()) . '@' . escapeshellarg($host) .
				' ' . escapeshellarg($command);
			$this->client = new Process($command);
			$this->client->start($this->loop);

			return $this->client;
		}

		/**
		 * Get key path
		 *
		 * @return string
		 */
		protected function getKeyPath(): string
		{
			$this->fp = tmpfile();
			$key = $this->key();
			if (false !== ($pos = strpos($key, ':'))) {
				$key = substr($key, ++$pos);
			}
			fwrite($this->fp, $key);

			return stream_get_meta_data($this->fp)['uri'];
		}

		private function key(): string
		{
			return $this->server->auth_key;
		}

		/**
		 * Get SSH username
		 *
		 * @return string
		 */
		protected function getUsername()
		{
			$key = $this->key();
			if (false !== strpos($key, ':')) {
				return strtok($key, ':');
			}

			return get_current_user();
		}

		/**
		 * Test handler
		 *
		 * @return bool
		 */
		public function test(): bool
		{
			$code = null;
			$this->connect()->on('exit', static function ($ret) use (&$code) {
				$code = $ret;
			});
			$err = '';
			$this->client->stderr->on('data', function ($chunk) use (&$err) {
				$err .= $chunk;
			});
			$this->client->stdout->on('data', function ($chunk) use (&$err) {
				$err .= $chunk;
			});

			$this->loop->run();
			if ($code !== 0) {
				throw new RuntimeException("Failed to verify " . $this->server->server_name . ": " . $err);
			}

			return $code === 0;
		}
	}