<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `virtual_users` and `citoyens` already exist in production, where Dovecot reads
 * them: this migration only creates them on an empty database.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('virtual_users')) {
            Schema::create('virtual_users', function (Blueprint $table): void {
                $table->increments('id');
                $table->string('username', 64)->unique();
                $table->string('userPassword')->nullable();
                $table->string('legacy_password')->nullable();
                $table->bigInteger('quota_bytes')->default(0);
                $table->boolean('active')->default(true);
                $table->dateTime('created_at')->nullable()->useCurrent();
                $table->dateTime('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();
            });
        }

        if (! Schema::hasTable('citoyens')) {
            Schema::create('citoyens', function (Blueprint $table): void {
                $table->id();
                $table->string('uid')->unique();
                $table->string('mail')->unique();
                $table->string('givenName')->nullable();
                $table->string('sn')->nullable();
                $table->text('l')->nullable();
                $table->text('postalAddress')->nullable();
                $table->text('employeeNumber')->nullable();
                $table->string('postalCode')->nullable();
                $table->string('homeDirectory');
                $table->text('description')->nullable();
                $table->date('last_connection')->nullable();
                $table->timestamps();
                $table->string('auth_token')->nullable()->unique();
                $table->rememberToken();
                $table->timestamp('charter_accepted_at')->nullable();
                $table->text('recovery_email')->nullable();
                $table->text('recovery_phone')->nullable();
                $table->string('hand_password')->nullable();

                $table->foreign('mail')->references('username')->on('virtual_users')->cascadeOnUpdate();
            });
        }
    }
};
