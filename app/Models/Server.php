<?php

	namespace App\Models;

	use App\Clients\Broker;
	use App\Regex;
	use Eloquent;
	use Illuminate\Database\Eloquent\Builder;
	use Illuminate\Database\Eloquent\Factories\HasFactory;
	use Illuminate\Database\Eloquent\Model;
	use Illuminate\Support\Facades\Crypt;
	use InvalidArgumentException;
	use React\EventLoop\Factory;
	use RuntimeException;

	/**
	 * App\Models\Server
	 *
	 * @property string      $server_name
	 * @property string|null $ip
	 * @property string      $auth
	 * @property string|null $auth_key
	 * @method static Builder|Server newModelQuery()
	 * @method static Builder|Server newQuery()
	 * @method static Builder|Server query()
	 * @method static Builder|Server whereAuth($value)
	 * @method static Builder|Server whereAuthName($value)
	 * @method static Builder|Server whereIp($value)
	 * @method static Builder|Server whereServerName($value)
	 * @mixin Eloquent
	 */
	class Server extends Model
	{
		use HasFactory;

		public $timestamps = false;
		public $incrementing = false;
		protected $guarded = [];
		protected $primaryKey = 'server_name';
		protected $keyType = 'string';

		public function setServerNameAttribute(string $value): void
		{
			if (!preg_match(Regex::NODENAME, $value)) {
				throw new InvalidArgumentException("Invalid nodename");
			}

			$this->attributes['server_name'] = $value;
		}

		public function setIpAttribute(string $value): void
		{
			if (false === inet_pton($value)) {
				throw new InvalidArgumentException("Invalid IP address");
			}

			$this->attributes['ip'] = $value;
		}

		public function setAuthAttribute(string $value): void
		{
			if (!Broker::clientExists($value)) {
				throw new InvalidArgumentException("Invalid auth client");
			}

			$this->attributes['auth'] = $value;
		}

		public function setAuthKeyAttribute(string $value, bool $crypt = true): void
		{
			// @TODO test
			$this->attributes['auth_key'] = $crypt ? Crypt::encryptString($value) : $value;
		}

		public function getAuthKeyAttribute(): ?string
		{
			if (!$this->attributes['auth_key']) {
				return null;
			}

			return Crypt::decryptString($this->attributes['auth_key']);
		}

		public function test(): bool
		{
			if (!Broker::create($this, Factory::create())->test()) {
				throw new RuntimeException(
					"Unable to connect to target server " . $this->attributes['server_name']
				);
			}

			return true;
		}


	}
