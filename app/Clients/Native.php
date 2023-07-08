<?php declare(strict_types=1);

	namespace App\Clients;

	/**
	 * Class Native
	 *
	 * SSH client dependent active environment
	 *
	 * @package App\Clients
	 */
	class Native extends Ssh
	{
		/**
		 * Get SSH key path
		 *
		 * @return string
		 */
		protected function getKeyPath(): string
		{
			return getenv("HOME") . '/.ssh/id_rsa';
		}
	}