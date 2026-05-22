New corebot support / customization request

Type: {{ ucfirst(str_replace('_', ' ', $request['type'])) }}
Name: {{ $request['name'] }}
Email: {{ $request['email'] }}
@if (! empty($request['company']))
Company: {{ $request['company'] }}
@endif

Message:
{{ $request['message'] }}

---
Sent from {{ config('app.url') }}
