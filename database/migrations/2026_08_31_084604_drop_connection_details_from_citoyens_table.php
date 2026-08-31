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
            $table->dropColumn(['protocol_connection', 'port_connection', 'secure_connection']);
        });
    }

    public function down(): void
    {
        Schema::table('citoyens', function (Blueprint $table) {
            $table->string('protocol_connection')->nullable()->after('last_connection');
            $table->integer('port_connection')->nullable()->after('protocol_connection');
            $table->boolean('secure_connection')->nullable()->after('port_connection');
        });
    }
};
