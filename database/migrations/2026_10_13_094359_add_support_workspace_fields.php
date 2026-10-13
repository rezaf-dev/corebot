<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true);
        });
        Schema::table('chat_conversations', function (Blueprint $table) {
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('support_mode')->default('ai');
            $table->timestamp('resolved_at')->nullable();
            $table->index(['tenant_id', 'assigned_to', 'status']);
            $table->index(['tenant_id', 'updated_at']);
            $table->index(['tenant_id', 'bot_id', 'visitor_id']);
        });
    }

    public function down(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'assigned_to', 'status']);
            $table->dropIndex(['tenant_id', 'updated_at']);
            $table->dropIndex(['tenant_id', 'bot_id', 'visitor_id']);
            $table->dropConstrainedForeignId('assigned_to');
            $table->dropColumn(['support_mode', 'resolved_at']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
