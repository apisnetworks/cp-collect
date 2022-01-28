<?php

	namespace App\Models;

	use Eloquent;
	use Illuminate\Database\Eloquent\Builder;
	use Illuminate\Database\Eloquent\Factories\HasFactory;
	use Illuminate\Database\Eloquent\Model;

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
	class Domain extends Model
	{
		use HasFactory;

		public $timestamps = false;
		public $incrementing = false;
		protected $guarded = [];
		protected $primaryKey = ['domain', 'server_name'];
		protected $keyType = 'string';

	}
