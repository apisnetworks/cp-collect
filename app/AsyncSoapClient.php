<?php declare(strict_types=1);


	namespace App;

	use Amp\Deferred;
	use Amp\Http\Client\HttpClient;
	use Amp\Http\Client\Request;
	use Amp\Http\Client\Response;
	use Amp\Loop;
	use Amp\Promise;
	use Exception;
	use SoapClient;
	use function Amp\wait;

	/**
	 * Class AsyncSoapClient
	 *
	 * Based on work by meng-tian (async-soap-artax)
	 *
	 * @package App
	 */
	class AsyncSoapClient extends SoapClient
	{

		private $deferredHttpBinding;
		private $client;

		public static function create($wsdl, array $options = [])
		{
			$deferredHttpBinding = new Deferred;

			$client = new static($wsdl, $options);
			$client->client = $client;
			$client->deferredHttpBinding = $deferredHttpBinding->promise();

			return $client;
		}

		public function __call($name, $arguments)
		{
			return $this->callAsync($name, $arguments);
		}

		public function callAsync(
			$name,
			array $arguments,
			array $options = null,
			$inputHeaders = null,
			array &$outputHeaders = null
		) {
			$deferredResult = new Deferred;
			$this->deferredHttpBinding->onResolve(
				function (Exception $error = null, $httpBinding) use (
					$deferredResult,
					$name,
					$arguments,
					$options,
					$inputHeaders,
					&$outputHeaders
				) {
					if ($error) {
						$deferredResult->fail($error);
					} else {
						$request = new Request;
						/** @var HttpBinding $httpBinding */
						$psrRequest = $httpBinding->request($name, $arguments, $options, $inputHeaders);
						$request->setMethod($psrRequest->getMethod());
						$request->setUri($psrRequest->getUri());
						$request->setHeaders($psrRequest->getHeaders());
						$request->setBody($psrRequest->getBody()->__toString());
						$psrRequest->getBody()->close();

						$this->client->request($request)->when(
							function (Exception $error = null, $response) use (
								$name,
								&$outputHeaders,
								$deferredResult,
								$httpBinding
							) {
								if ($error) {
									$deferredResult->fail($error);
								} else {
									$bodyStream = new Stream('php://temp', 'r+');
									/** @var Response $response */
									$bodyStream->write($response->getBody());
									$bodyStream->rewind();
									$psrResponse = new PsrResponse($bodyStream, $response->getStatus(),
										$response->getAllHeaders());

									try {
										$deferredResult->succeed($httpBinding->response($psrResponse, $name,
											$outputHeaders));
									} catch (Exception $e) {
										$deferredResult->fail($e);
									} finally {
										$psrResponse->getBody()->close();
									}

								}
							}
						);
					}
				}
			);

			return $deferredResult->promise();
		}

		public function call(
			$name,
			array $arguments,
			array $options = null,
			$inputHeaders = null,
			array &$outputHeaders = null
		) {
			$promise = $this->callAsync($name, $arguments, $options, $inputHeaders, $outputHeaders);

			return $promise->wait();
		}
	}