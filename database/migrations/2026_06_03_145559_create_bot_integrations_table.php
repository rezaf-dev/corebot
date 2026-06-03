<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bot_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32);
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('config')->nullable();
            $table->text('credentials')->nullable();
            $table->json('allowed_domains')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->index(['bot_id', 'enabled']);
            $table->index(['tenant_id', 'bot_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_integrations');
    }
};
