New lead from {{ $bot->name }}

Conversation #{{ $conversation->id }}

Contact
-----
Name:  {{ $conversation->visitor_name ?: '—' }}
Email: {{ $conversation->visitor_email ?: '—' }}
Phone: {{ $conversation->visitor_phone ?: '—' }}

Visitor
-------
Visitor ID: {{ $conversation->visitor_id ?: '—' }}
Source URL: {{ $conversation->source_url ?: '—' }}
Referrer:   {{ $conversation->referrer_url ?: '—' }}
Country:    {{ $conversation->country_name ? $conversation->country_name.' ('.$conversation->country_code.')' : '—' }}
City:       {{ $conversation->city ?: '—' }}
IP:         {{ $conversation->ip_address ?: '—' }}
Language:   {{ $conversation->language ?: '—' }}
Timezone:   {{ $conversation->timezone ?: '—' }}

@if($conversation->utm_source || $conversation->utm_medium || $conversation->utm_campaign)
Campaign
--------
Source:   {{ $conversation->utm_source ?: '—' }}
Medium:   {{ $conversation->utm_medium ?: '—' }}
Campaign: {{ $conversation->utm_campaign ?: '—' }}
@endif

@if($lastUserMessage)
Last visitor message
--------------------
{{ $lastUserMessage }}
@endif

View in admin: {{ url('/conversations/'.$conversation->id) }}
