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
            $table->string('name');
            $table->string('fist_name');
            $table->string('last_name');
            $table->string('city');
            $table->string('street');
            $table->string('email')->unique();
            $table->string('password');
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
