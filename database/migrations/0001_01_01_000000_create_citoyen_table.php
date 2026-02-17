<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('citoyens', function (Blueprint $table) {
            $table->id();
            $table->string('uid')->unique();
            $table->string('dn')->unique();
            $table->string('mail')->unique();
            $table->string('givenName')->nullable();
            $table->string('sn');
            $table->string('cn');
            $table->string('l')->nullable();
            $table->string('postalAddress');
            $table->string('employeeNumber');
            $table->string('postalCode');
            $table->float('gosaMailQuota');
            $table->string('homeDirectory');
            $table->string('gosaMailForwardingAddress');
            $table->string('gosaMailAlternateAddress')->nullable();
            $table->string('userPassword')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('hands', function (Blueprint $table) {
            $table->id();
            $table->string('uid')->unique();
            $table->string('password');
            $table->timestamps();
        });

        Schema::create('login', function (Blueprint $table) {
            $table->id();
            $table->string('username')->unique();
            $table->date('date_connect');
            $table->string('protocol');
            $table->boolean('secure');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('citoyens');
    }
};
