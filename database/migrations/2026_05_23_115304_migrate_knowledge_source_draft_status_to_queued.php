<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('knowledge_sources')->where('status', 'draft')->update(['status' => 'queued']);
    }

    public function down(): void
    {
        DB::table('knowledge_sources')->where('status', 'queued')->update(['status' => 'draft']);
    }
};
