<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class Initial extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('servers', function (Blueprint $db) {
			if ($this->hasSqlite()) {
				$db->string('server_name', 64)->unique();
			} else {
				$db->string('server_name', 64)->primary();
			}
            $db->ipAddress('ip')->nullable()->comment("Force IP resolution");
            $db->enum('auth', ['api', 'native', 'ssh']);
            $db->text('auth_key')->nullable()->comment("Authentication key data");
        });

        Schema::create('account_cache', function (Blueprint $db) {
            $db->string('server_name', 64);
            $db->integer('site_id', 4)->unsigned();
            $db->string('prefix', 16)->nullable();
            $db->string('admin', 32)->index();
            $db->unique(['server_name', 'admin']);
            $db->unique(['server_name', 'prefix']);
            $db->foreign('server_name')->references('server_name')->on('servers')->
                onDelete('cascade')->onUpdate('cascade');
        });

        Schema::create('api_keys', function (Blueprint $db) {
			if ($this->hasSqlite()) {
				$db->string('api_key', 64)->unique();
			} else {
				$db->string('api_key', 64)->primary();
			}
			$db->string('username', 32);
			$db->string('domain', 64)->nullable();
			$db->integer('site_id', false, true)->nullable();
			$db->timestamp('last_used', 0);
			$db->string('comment')->nullable();
			$db->string('server_name', 64)->nullable();
			$db->string('invoice', 34)->nullable();
			$db->index(['username', 'domain']);


			$db->foreign('server_name')->references('server_name')->on('servers')->
                onDelete('cascade')->onUpdate('cascade');
        });

        Schema::create('domains', function (Blueprint $db) {
			$db->string('domain', 64);
			$db->string('original_domain', 64)->nullable();
			$db->string('admin_email')->nullable();
			$db->string('server_name', 64);
			$db->enum('status', ['active', 'disabled', 'deleted']);
			$db->boolean('addon')->default(false);
			$db->unsignedSmallInteger('site_id', false);
			$db->string('di_invoice', 34);
			$db->unique(['domain', 'server_name']);

			$db->index('status');
            $db->index('di_invoice');

            $db->foreign('server_name')->references('server_name')->on('servers')->
                onDelete('cascade')->onUpdate('cascade');
        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        foreach(['domains', 'api_keys', 'account_cache', 'servers'] as $table) {
            if (Schema::hasTable($table)) {
                Schema::drop($table);
            }
        }
    }

    private function hasSqlite(): string
    {
        return app('config')->get('database.default') === 'sqlite';
    }

	private function hasPgsql(): string
	{
		return app('config')->get('database.default') === 'pgsql';
	}
}
