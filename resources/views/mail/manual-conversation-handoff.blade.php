A visitor needs a manual reply for {{ $bot->name }}.

Conversation: #{{ $conversation->id }}
Visitor: {{ $conversation->visitor_name ?: 'Anonymous visitor' }}
Email: {{ $conversation->visitor_email ?: 'Not provided' }}
Phone: {{ $conversation->visitor_phone ?: 'Not provided' }}

@if ($lastUserMessage)
Latest message:
{{ $lastUserMessage }}

@endif
Review and handle it here:
{{ route('conversations.show', $conversation) }}
