<?php

namespace App\Services\Leads;

use App\Mail\LeadCapturedMail;
use App\Models\Bot;
use App\Models\ChatConversation;
use App\Support\BotContactConfig;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class LeadCaptureService
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{ok: bool, message: string}
     */
    public function capture(Bot $bot, ChatConversation $conversation, array $data): array
    {
        $payload = $this->normalizePayload($data);
        $this->validate($bot, $payload);

        $conversation->update(collect($payload)->only(['visitor_name', 'visitor_email', 'visitor_phone'])->all());
        $this->notify($bot, $conversation->fresh());

        return [
            'ok' => true,
            'message' => 'Lead saved for this conversation.',
        ];
    }

    public function notify(Bot $bot, ChatConversation $conversation): void
    {
        if ($conversation->contact_notified_at !== null) {
            return;
        }

        $recipient = $bot->notification_email;

        if (! is_string($recipient) || $recipient === '') {
            return;
        }

        $lastUserMessage = $conversation->messages()
            ->where('role', 'user')
            ->latest()
            ->value('content');

        Mail::to($recipient)->queue(new LeadCapturedMail($bot, $conversation, $lastUserMessage));

        $conversation->update(['contact_notified_at' => now()]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{visitor_name?: string, visitor_email?: string, visitor_phone?: string}
     */
    private function normalizePayload(array $data): array
    {
        return array_filter([
            'visitor_name' => isset($data['visitor_name']) ? trim((string) $data['visitor_name']) : null,
            'visitor_email' => isset($data['visitor_email']) ? trim((string) $data['visitor_email']) : null,
            'visitor_phone' => isset($data['visitor_phone']) ? trim((string) $data['visitor_phone']) : null,
        ], fn ($value) => $value !== null && $value !== '');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function validate(Bot $bot, array $data): void
    {
        $rules = [];

        foreach (BotContactConfig::required($bot) as $field) {
            $column = BotContactConfig::FIELD_MAP[$field];
            $rules[$column] = ['required', 'string', 'max:255'];
        }

        if ($rules !== []) {
            try {
                Validator::make($data, $rules)->validate();
            } catch (ValidationException $e) {
                throw $e;
            }
        }

        $hasValue = collect(BotContactConfig::fields($bot))
            ->contains(fn (string $field): bool => filled($data[BotContactConfig::FIELD_MAP[$field]] ?? null));

        if (! $hasValue) {
            throw ValidationException::withMessages([
                'contact' => ['At least one contact field is required.'],
            ]);
        }
    }
}
