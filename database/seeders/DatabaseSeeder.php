<?php

namespace Database\Seeders;

use App\Models\Bot;
use App\Models\KnowledgeSource;
use App\Models\Tenant;
use App\Models\TenantAiSetting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'super@example.com'],
            ['name' => 'Super Admin', 'password' => Hash::make('password'), 'role' => 'super_admin']
        );

        $tenant = Tenant::firstOrCreate(
            ['slug' => 'corefix-demo-crm'],
            ['name' => 'CoreFix Demo CRM', 'status' => 'active']
        );

        User::firstOrCreate(
            ['email' => 'tenant@example.com'],
            ['tenant_id' => $tenant->id, 'name' => 'Tenant Admin', 'password' => Hash::make('password'), 'role' => 'tenant_admin']
        );

        TenantAiSetting::firstOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'provider' => 'openai',
                'base_url' => 'https://api.openai.com/v1',
                'chat_model' => 'gpt-4o-mini',
                'embedding_model' => 'text-embedding-3-small',
                'embedding_dimensions' => 1536,
                'is_active' => false,
            ]
        );

        $bot = Bot::firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'CRM Support Bot'],
            ['public_key' => 'bot_'.Str::random(32), 'status' => 'active']
        );

        KnowledgeSource::firstOrCreate(
            ['tenant_id' => $tenant->id, 'bot_id' => $bot->id, 'title' => 'CRM Workflow Demo Knowledge'],
            [
                'type' => 'text',
                'status' => 'draft',
                'raw_text' => $this->demoKnowledge(),
            ]
        );
    }

    private function demoKnowledge(): string
    {
        return <<<'TEXT'
Lead management: New leads start in New status. Staff should qualify the lead, add a note, assign an owner, and move qualified leads to Contacted. Leads can be converted only after a phone number or email is present.

Order statuses: Draft orders are not visible to customers. Confirmed orders have passed staff review. Processing orders are being prepared. Completed orders are locked for editing except by managers. Cancelled orders require a cancellation reason note.

Payment statuses: Pending means payment has not been received. Paid means the transaction is complete. Failed means the payment gateway rejected the transaction. Refunded means a manager approved and processed a refund.

Refund flow: Staff open the order, verify the payment status is Paid, add a refund reason, and escalate to a manager. Managers approve refunds from the Payments tab. Staff must not promise a refund before manager approval.

Customer notes: Notes can be public or internal. Internal notes are visible only to staff. Sensitive health, legal, or payment-card details should not be stored in notes.

Staff permissions: Agents can view leads, orders, tickets, and customer notes. Managers can edit completed orders, approve refunds, and reassign tickets. Admins can manage staff and CRM settings.

Ticket escalation: Tickets marked Urgent should be assigned to a manager. If a customer reports a payment problem, staff should verify the payment status and escalate if the transaction is missing or duplicated.

Renewal reminders: Renewal reminders are generated 14 days before expiry. Staff can snooze a reminder for 7 days or mark it complete after the customer confirms renewal.

Document upload rules: PDF and DOCX documents can be attached to customer records. Files should be under 10 MB. Staff should use clear filenames that include the customer name and record type.
TEXT;
    }
}
