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
        Schema::create('bots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('public_key')->unique();
            $table->string('status')->default('active')->index();
            $table->boolean('use_tenant_ai_settings')->default(true);
            $table->text('welcome_message')->nullable();
            $table->text('fallback_message')->nullable();
            $table->longText('system_prompt')->nullable();
            $table->json('allowed_domains')->nullable();
            $table->decimal('temperature', 3, 2)->default(0.20);
            $table->unsignedTinyInteger('max_context_chunks')->default(6);
            $table->decimal('similarity_threshold', 4, 3)->default(0.550);
            $table->boolean('collect_visitor_email')->default(true);
            $table->boolean('collect_visitor_phone')->default(false);
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bots');
    }
};
