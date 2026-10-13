<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('chat_conversations')->where('status', 'escalated')->update(['support_mode' => 'human']);
    }

    public function down(): void
    {
        DB::table('chat_conversations')->where('status', 'escalated')->whereNull('assigned_to')->update(['support_mode' => 'ai']);
    }
};
