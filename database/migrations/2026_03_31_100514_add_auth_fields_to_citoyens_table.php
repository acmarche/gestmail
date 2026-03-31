<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('citoyens', function (Blueprint $table) {
            $table->string('auth_token')->nullable()->unique();
            $table->rememberToken();
            $table->timestamp('charter_accepted_at')->nullable();
            $table->string('recovery_email')->nullable();
            $table->string('recovery_phone')->nullable();
        });
    }
};
