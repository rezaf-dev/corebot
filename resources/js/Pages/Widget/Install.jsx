import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

export default function Install({ bots, widgetUrl, defaults, positions, icons }) {
    const [selectedBotId, setSelectedBotId] = useState(bots[0]?.id ?? null);
    const [copied, setCopied] = useState(false);

    const selectedBot = bots.find((bot) => bot.id === selectedBotId);

    const { data, setData, put, processing, errors, recentlySuccessful, reset } = useForm(
        selectedBot?.widget_config || defaults,
    );

    useEffect(() => {
        if (selectedBot) {
            reset(selectedBot.widget_config);
        }
    }, [selectedBotId]);

    const snippet = useMemo(() => {
        if (!selectedBot) return '';
        return buildEmbedSnippet(widgetUrl, selectedBot.public_key, data);
    }, [selectedBot, widgetUrl, data]);

    const submit = (e) => {
        e.preventDefault();
        if (!selectedBot) return;
        put(route('widget.install.update', selectedBot.id), { preserveScroll: true });
    };

    const copySnippet = async () => {
        try {
            await navigator.clipboard.writeText(snippet);
            setCopied(true);
            window.setTimeout(() => setCopied(false), 2000);
        } catch {
            window.prompt('Copy this embed code:', snippet);
        }
    };

    if (bots.length === 0) {
        return (
            <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-gray-800 dark:text-gray-200">Widget</h2>}>
                <Head title="Widget" />
                <div className="mx-auto max-w-3xl p-6">
                    <div className="rounded-lg border border-dashed border-gray-200 bg-white px-6 py-12 text-center dark:border-gray-700 dark:bg-gray-800">
                        <h3 className="text-lg font-semibold text-gray-900 dark:text-white">No active bots</h3>
                        <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">
                            Create and activate a bot before customizing the chat widget.
                        </p>
                    </div>
                </div>
            </AuthenticatedLayout>
        );
    }

    return (
        <AuthenticatedLayout
            header={
                <div>
                    <h2 className="text-xl font-semibold text-gray-800 dark:text-gray-200">Widget</h2>
                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Customize appearance, position, and copy the embed code for each bot.
                    </p>
                </div>
            }
        >
            <Head title="Widget" />

            <div className="mx-auto max-w-7xl space-y-6 p-6">
                <div className="flex flex-wrap gap-2">
                    {bots.map((bot) => (
                        <button
                            key={bot.id}
                            type="button"
                            onClick={() => setSelectedBotId(bot.id)}
                            className={`rounded-lg border px-4 py-2 text-sm font-medium transition ${
                                selectedBotId === bot.id
                                    ? 'border-indigo-500 bg-indigo-50 text-indigo-800 dark:border-indigo-500 dark:bg-indigo-950/40 dark:text-indigo-200'
                                    : 'border-gray-200 bg-white text-gray-700 hover:border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300'
                            }`}
                        >
                            {bot.name}
                        </button>
                    ))}
                </div>

                <div className="grid gap-6 xl:grid-cols-[1fr_380px]">
                    <form onSubmit={submit} className="space-y-6">
                        <section className="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                            <h3 className="text-base font-semibold text-gray-900 dark:text-white">Appearance</h3>
                            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Colors and text shown in the chat header and launcher.
                            </p>
                            <div className="mt-5 grid gap-5 sm:grid-cols-2">
                                <TextField id="title" label="Title" value={data.title} onChange={(v) => setData('title', v)} error={errors.title} />
                                <TextField id="subtitle" label="Subtitle" value={data.subtitle} onChange={(v) => setData('subtitle', v)} error={errors.subtitle} />
                                <ColorField id="primary_color" label="Header & buttons" value={data.primary_color} onChange={(v) => setData('primary_color', v)} error={errors.primary_color} />
                                <ColorField id="accent_color" label="User messages" value={data.accent_color} onChange={(v) => setData('accent_color', v)} error={errors.accent_color} />
                                <ColorField id="background_color" label="Messages background" value={data.background_color} onChange={(v) => setData('background_color', v)} error={errors.background_color} />
                                <ColorField id="surface_color" label="Panel surface" value={data.surface_color} onChange={(v) => setData('surface_color', v)} error={errors.surface_color} />
                                <ColorField id="text_color" label="Text color" value={data.text_color} onChange={(v) => setData('text_color', v)} error={errors.text_color} />
                                <div>
                                    <InputLabel htmlFor="launcher_icon" value="Launcher icon" />
                                    <select
                                        id="launcher_icon"
                                        value={data.launcher_icon}
                                        onChange={(e) => setData('launcher_icon', e.target.value)}
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                    >
                                        {icons.map((icon) => (
                                            <option key={icon} value={icon}>
                                                {icon}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.launcher_icon} className="mt-1" />
                                </div>
                            </div>
                        </section>

                        <section className="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                            <h3 className="text-base font-semibold text-gray-900 dark:text-white">Layout</h3>
                            <div className="mt-5 grid gap-5 sm:grid-cols-2">
                                <div>
                                    <InputLabel htmlFor="position" value="Position" />
                                    <select
                                        id="position"
                                        value={data.position}
                                        onChange={(e) => setData('position', e.target.value)}
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                    >
                                        {positions.map((position) => (
                                            <option key={position} value={position}>
                                                {position.replace('-', ' ')}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.position} className="mt-1" />
                                </div>
                                <RangeField
                                    id="panel_width"
                                    label="Panel width (px)"
                                    value={data.panel_width}
                                    min={320}
                                    max={480}
                                    onChange={(v) => setData('panel_width', v)}
                                    error={errors.panel_width}
                                />
                                <RangeField
                                    id="launcher_size"
                                    label="Launcher size (px)"
                                    value={data.launcher_size}
                                    min={44}
                                    max={72}
                                    onChange={(v) => setData('launcher_size', v)}
                                    error={errors.launcher_size}
                                />
                                <RangeField
                                    id="border_radius"
                                    label="Corner radius (px)"
                                    value={data.border_radius}
                                    min={0}
                                    max={32}
                                    onChange={(v) => setData('border_radius', v)}
                                    error={errors.border_radius}
                                />
                                <RangeField
                                    id="offset_x"
                                    label="Horizontal offset (px)"
                                    value={data.offset_x}
                                    min={0}
                                    max={80}
                                    onChange={(v) => setData('offset_x', v)}
                                    error={errors.offset_x}
                                />
                                <RangeField
                                    id="offset_y"
                                    label="Vertical offset (px)"
                                    value={data.offset_y}
                                    min={0}
                                    max={80}
                                    onChange={(v) => setData('offset_y', v)}
                                    error={errors.offset_y}
                                />
                                <div>
                                    <InputLabel htmlFor="initial_open" value="Initial state" />
                                    <select
                                        id="initial_open"
                                        value={data.initial_open ? 'open' : 'closed'}
                                        onChange={(e) => setData('initial_open', e.target.value === 'open')}
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                    >
                                        <option value="closed">Closed (launcher only)</option>
                                        <option value="open">Open (chat panel visible)</option>
                                    </select>
                                    <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        When open, visitors see the welcome message immediately on page load.
                                    </p>
                                    <InputError message={errors.initial_open} className="mt-1" />
                                </div>
                            </div>
                        </section>

                        <section className="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                            <h3 className="text-base font-semibold text-gray-900 dark:text-white">Labels</h3>
                            <div className="mt-5 grid gap-5 sm:grid-cols-2">
                                <TextField
                                    id="input_placeholder"
                                    label="Input placeholder"
                                    value={data.input_placeholder}
                                    onChange={(v) => setData('input_placeholder', v)}
                                    error={errors.input_placeholder}
                                />
                                <TextField
                                    id="send_button_label"
                                    label="Send button"
                                    value={data.send_button_label}
                                    onChange={(v) => setData('send_button_label', v)}
                                    error={errors.send_button_label}
                                />
                            </div>
                        </section>

                        <div className="flex flex-wrap items-center gap-3">
                            <PrimaryButton disabled={processing}>{processing ? 'Saving…' : 'Save widget settings'}</PrimaryButton>
                            {recentlySuccessful && (
                                <span className="text-sm text-emerald-600 dark:text-emerald-400">Saved.</span>
                            )}
                        </div>
                    </form>

                    <aside className="space-y-6">
                        <WidgetPreview config={data} initialOpen={data.initial_open} />
                        <section className="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                            <div className="flex items-start justify-between gap-3">
                                <div>
                                    <h3 className="text-base font-semibold text-gray-900 dark:text-white">Embed code</h3>
                                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                        Paste before <code className="text-xs">&lt;/body&gt;</code>. Settings are saved on your bot and loaded by the widget automatically.
                                    </p>
                                    <p className="mt-2 font-mono text-xs text-gray-500 dark:text-gray-400">
                                        {selectedBot?.public_key}
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    onClick={copySnippet}
                                    className="shrink-0 text-xs font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400"
                                >
                                    {copied ? 'Copied' : 'Copy'}
                                </button>
                            </div>
                            <pre className="mt-4 max-h-64 overflow-auto rounded-lg bg-gray-950 p-4 text-xs text-gray-100">{snippet}</pre>
                            <p className="mt-3 text-xs text-gray-500 dark:text-gray-400">
                                The script tag includes your current settings as data attributes. The widget also fetches saved settings from the server on load.
                            </p>
                        </section>
                    </aside>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function WidgetPreview({ config, initialOpen }) {
    const positionClass = {
        'bottom-right': 'items-end justify-end',
        'bottom-left': 'items-end justify-start',
        'top-right': 'items-start justify-end',
        'top-left': 'items-start justify-start',
    }[config.position] || 'items-end justify-end';

    return (
        <section className="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
            <h3 className="text-base font-semibold text-gray-900 dark:text-white">Preview</h3>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">Approximate look on your site (desktop).</p>
            <div
                className={`relative mt-4 flex h-80 overflow-hidden rounded-xl border border-gray-200 bg-gradient-to-br from-slate-100 to-slate-200 p-4 dark:border-gray-700 dark:from-gray-900 dark:to-gray-800 ${positionClass}`}
            >
                {initialOpen && (
                <div
                    className="flex w-[min(100%,280px)] flex-col overflow-hidden shadow-lg"
                    style={{
                        borderRadius: config.border_radius + 'px',
                        background: config.surface_color,
                        color: config.text_color,
                    }}
                >
                    <div className="px-3 py-2.5 text-white" style={{ background: config.primary_color }}>
                        <div className="text-sm font-semibold">{config.title}</div>
                        {config.subtitle && <div className="text-xs opacity-75">{config.subtitle}</div>}
                    </div>
                    <div className="space-y-2 p-3" style={{ background: config.background_color }}>
                        <div
                            className="ml-auto max-w-[85%] rounded-xl px-3 py-2 text-xs text-white"
                            style={{ background: config.accent_color }}
                        >
                            Sample question
                        </div>
                        <div
                            className="max-w-[85%] rounded-xl border px-3 py-2 text-xs"
                            style={{ background: config.surface_color, borderColor: config.text_color + '22', color: config.text_color }}
                        >
                            Sample reply
                        </div>
                    </div>
                    <div className="flex gap-2 border-t p-2" style={{ borderColor: config.text_color + '18' }}>
                        <div
                            className="h-8 flex-1 rounded-lg border text-xs leading-8 text-gray-400 px-2 truncate"
                            style={{ borderColor: config.text_color + '22' }}
                        >
                            {config.input_placeholder}
                        </div>
                        <div
                            className="rounded-lg px-3 py-1.5 text-xs font-semibold text-white"
                            style={{ background: config.primary_color }}
                        >
                            {config.send_button_label}
                        </div>
                    </div>
                </div>
                )}
                <div
                    className={`absolute flex items-center justify-center rounded-full text-white shadow-lg ${initialOpen ? 'opacity-0 pointer-events-none' : ''}`}
                    style={{
                        width: config.launcher_size,
                        height: config.launcher_size,
                        background: config.primary_color,
                        ...(config.position === 'bottom-right' && { right: config.offset_x, bottom: config.offset_y }),
                        ...(config.position === 'bottom-left' && { left: config.offset_x, bottom: config.offset_y }),
                        ...(config.position === 'top-right' && { right: config.offset_x, top: config.offset_y }),
                        ...(config.position === 'top-left' && { left: config.offset_x, top: config.offset_y }),
                    }}
                >
                    <span className="text-xs font-bold">●</span>
                </div>
            </div>
        </section>
    );
}

function TextField({ id, label, value, onChange, error }) {
    return (
        <div>
            <InputLabel htmlFor={id} value={label} />
            <TextInput id={id} value={value} onChange={(e) => onChange(e.target.value)} className="mt-1 block w-full" />
            <InputError message={error} className="mt-1" />
        </div>
    );
}

function ColorField({ id, label, value, onChange, error }) {
    return (
        <div>
            <InputLabel htmlFor={id} value={label} />
            <div className="mt-1 flex gap-2">
                <input
                    type="color"
                    id={id + '_picker'}
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                    className="h-10 w-12 cursor-pointer rounded border border-gray-300 dark:border-gray-700"
                />
                <TextInput id={id} value={value} onChange={(e) => onChange(e.target.value)} className="block w-full font-mono text-sm" />
            </div>
            <InputError message={error} className="mt-1" />
        </div>
    );
}

function RangeField({ id, label, value, min, max, onChange, error }) {
    return (
        <div>
            <div className="flex items-center justify-between">
                <InputLabel htmlFor={id} value={label} />
                <span className="font-mono text-sm text-gray-700 dark:text-gray-300">{value}</span>
            </div>
            <input
                id={id}
                type="range"
                min={min}
                max={max}
                value={value}
                onChange={(e) => onChange(Number(e.target.value))}
                className="mt-2 w-full accent-indigo-600"
            />
            <InputError message={error} className="mt-1" />
        </div>
    );
}

function buildEmbedSnippet(widgetUrl, publicKey, config) {
    const attrs = {
        src: widgetUrl,
        'data-bot-key': publicKey,
        'data-title': config.title,
        'data-subtitle': config.subtitle || '',
        'data-primary-color': config.primary_color,
        'data-accent-color': config.accent_color,
        'data-background-color': config.background_color,
        'data-surface-color': config.surface_color,
        'data-text-color': config.text_color,
        'data-position': config.position,
        'data-offset-x': String(config.offset_x),
        'data-offset-y': String(config.offset_y),
        'data-border-radius': String(config.border_radius),
        'data-panel-width': String(config.panel_width),
        'data-launcher-size': String(config.launcher_size),
        'data-send-button-label': config.send_button_label,
        'data-input-placeholder': config.input_placeholder,
        'data-launcher-icon': config.launcher_icon,
        'data-initial-open': config.initial_open ? 'true' : 'false',
    };

    return (
        '<script ' +
        Object.entries(attrs)
            .map(([key, val]) => `${key}="${escapeAttr(val)}"`)
            .join(' ') +
        '></script>'
    );
}

function escapeAttr(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/</g, '&lt;');
}
