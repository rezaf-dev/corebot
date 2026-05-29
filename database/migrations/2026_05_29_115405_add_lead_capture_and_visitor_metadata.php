<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bots', function (Blueprint $table) {
            $table->json('contact_fields')->nullable()->after('collect_visitor_phone');
            $table->json('contact_required')->nullable()->after('contact_fields');
            $table->string('notification_email')->nullable()->after('contact_required');
            $table->boolean('collect_contact_on_start')->default(false)->after('notification_email');
        });

        Schema::table('chat_conversations', function (Blueprint $table) {
            $table->string('ip_address', 45)->nullable()->after('source_url');
            $table->text('user_agent')->nullable()->after('ip_address');
            $table->text('referrer_url')->nullable()->after('user_agent');
            $table->string('country_code', 2)->nullable()->after('referrer_url');
            $table->string('country_name')->nullable()->after('country_code');
            $table->string('city')->nullable()->after('country_name');
            $table->string('timezone', 64)->nullable()->after('city');
            $table->string('language', 32)->nullable()->after('timezone');
            $table->string('utm_source')->nullable()->after('language');
            $table->string('utm_medium')->nullable()->after('utm_source');
            $table->string('utm_campaign')->nullable()->after('utm_medium');
            $table->timestamp('contact_notified_at')->nullable()->after('status');
        });

        $this->backfillBotContactFields();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table) {
            $table->dropColumn([
                'ip_address',
                'user_agent',
                'referrer_url',
                'country_code',
                'country_name',
                'city',
                'timezone',
                'language',
                'utm_source',
                'utm_medium',
                'utm_campaign',
                'contact_notified_at',
            ]);
        });

        Schema::table('bots', function (Blueprint $table) {
            $table->dropColumn([
                'contact_fields',
                'contact_required',
                'notification_email',
                'collect_contact_on_start',
            ]);
        });
    }

    private function backfillBotContactFields(): void
    {
        DB::table('bots')->orderBy('id')->each(function (object $bot): void {
            $fields = ['name'];

            if ($bot->collect_visitor_email) {
                $fields[] = 'email';
            }

            if ($bot->collect_visitor_phone) {
                $fields[] = 'phone';
            }

            $required = $bot->collect_visitor_email ? ['email'] : [];

            DB::table('bots')->where('id', $bot->id)->update([
                'contact_fields' => json_encode(array_values(array_unique($fields))),
                'contact_required' => json_encode(array_values(array_unique($required))),
            ]);
        });
    }
};
