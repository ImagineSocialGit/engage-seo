<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_bootstrap_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('client_key', 100);
            $table->string('contributor_key', 100);
            $table->unsignedInteger('version');
            $table->char('checksum', 64);
            $table->timestamp('applied_at');

            $table->unique([
                'client_key',
                'contributor_key',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_bootstrap_runs');
    }
};