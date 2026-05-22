<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $appName }} — Live demo</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: linear-gradient(160deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
            color: #e2e8f0;
            min-height: 100vh;
        }
        .wrap { max-width: 720px; margin: 0 auto; padding: 48px 24px 120px; }
        .badge {
            display: inline-block;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #67e8f9;
            margin-bottom: 12px;
        }
        h1 { font-size: 2rem; font-weight: 700; margin: 0 0 12px; color: #fff; }
        p { line-height: 1.6; color: #94a3b8; margin: 0 0 16px; }
        .card {
            margin-top: 28px;
            padding: 24px;
            border-radius: 12px;
            border: 1px solid #334155;
            background: rgba(15, 23, 42, 0.6);
        }
        .card h2 { font-size: 1rem; margin: 0 0 8px; color: #f1f5f9; }
        .card p { font-size: 14px; margin: 0; }
        .warn {
            border-color: #b45309;
            background: rgba(120, 53, 15, 0.25);
        }
        .warn p { color: #fcd34d; }
        code {
            font-size: 13px;
            background: #0f172a;
            padding: 2px 6px;
            border-radius: 4px;
            color: #a5f3fc;
        }
        a { color: #67e8f9; text-decoration: none; }
        a:hover { text-decoration: underline; }
        ul { margin: 12px 0 0; padding-left: 20px; color: #94a3b8; font-size: 14px; }
        li { margin-bottom: 6px; }
        .links { margin-top: 24px; display: flex; flex-wrap: wrap; gap: 16px; font-size: 14px; }
    </style>
</head>
<body>
    <div class="wrap">
        <span class="badge">Live demo</span>
        <h1>{{ $appName }} chat widget</h1>
        <p>
            This page embeds the same vanilla JavaScript widget your clients install on their CRM or website.
            Open the chat launcher in the corner to start a conversation.
        </p>

        @if ($botPublicKey)
            <div class="card">
                <h2>Widget active</h2>
                <p>
                    Bot key loaded from <code>DEMO_BOT_PUBLIC_KEY</code>. Messages use your configured OpenAI
                    settings and knowledge for that bot.
                </p>
                <ul>
                    <li>Ensure the bot is <strong>active</strong> in the admin dashboard.</li>
                    <li>Configure <strong>AI settings</strong> and run the <strong>queue worker</strong> for knowledge indexing.</li>
                    <li>Add this demo host to the bot&apos;s <strong>allowed domains</strong> if domain restrictions are enabled.</li>
                </ul>
            </div>
        @else
            <div class="card warn">
                <h2>Demo not configured</h2>
                <p>
                    Set <code>DEMO_BOT_PUBLIC_KEY</code> in your <code>.env</code> to the <code>public_key</code> of an
                    active bot (from <strong>Bots</strong> in the admin or seed data), then refresh this page.
                </p>
            </div>
        @endif

        <div class="links">
            <a href="{{ url('/') }}">← Project home</a>
        </div>
    </div>

    @if ($botPublicKey)
        <script src="{{ $widgetUrl }}" data-bot-key="{{ $botPublicKey }}"></script>
    @endif
</body>
</html>
