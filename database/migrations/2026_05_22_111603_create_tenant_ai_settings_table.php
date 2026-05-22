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
        Schema::create('tenant_ai_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('provider')->default('openai');
            $table->text('api_key_encrypted')->nullable();
            $table->string('base_url')->default('https://api.openai.com/v1');
            $table->string('chat_model')->default('gpt-4o-mini');
            $table->string('embedding_model')->default('text-embedding-3-small');
            $table->unsignedSmallInteger('embedding_dimensions')->default(1536);
            $table->unsignedBigInteger('monthly_token_limit')->nullable();
            $table->unsignedBigInteger('monthly_message_limit')->nullable();
            $table->boolean('is_active')->default(false)->index();
            $table->timestamp('last_tested_at')->nullable();
            $table->string('last_test_status')->nullable();
            $table->text('last_test_error')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_ai_settings');
    }
};
