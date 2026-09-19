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
        Schema::table('knowledge_sources', function (Blueprint $table) {
            $table->text('source_url')->nullable()->after('title');
            $table->unsignedSmallInteger('crawl_page_limit')->nullable()->after('source_url');
            $table->unsignedSmallInteger('crawled_pages_count')->default(0)->after('crawl_page_limit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('knowledge_sources', function (Blueprint $table) {
            $table->dropColumn(['source_url', 'crawl_page_limit', 'crawled_pages_count']);
        });
    }
};
