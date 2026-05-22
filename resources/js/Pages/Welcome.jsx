import ApplicationLogo from '@/Components/ApplicationLogo';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { Head, Link, useForm, usePage } from '@inertiajs/react';

const GITHUB_URL = 'https://github.com/rezaf-dev/corebot';
const UPWORK_URL = 'https://www.upwork.com/freelancers/rezafathi?mp_source=share';
const PRODUCT_URL = 'https://corefixlab.com/corebot';

const REQUEST_TYPES = [
    { value: 'customization', label: 'Customization' },
    { value: 'installation', label: 'Installation help' },
    { value: 'support', label: 'Technical support' },
    { value: 'other', label: 'Other' },
];

export default function Welcome({ auth }) {
    const { flash, demo_url: demoUrl } = usePage().props;

    const { data, setData, post, processing, errors, recentlySuccessful, reset } = useForm({
        name: '',
        email: '',
        company: '',
        type: 'customization',
        message: '',
    });

    const submitSupport = (e) => {
        e.preventDefault();
        post(route('support-requests.store'), {
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    };

    return (
        <>
            <Head title="corebot — Open-source CRM AI support bot" />

            <main className="min-h-screen bg-slate-950 text-white">
                <header className="mx-auto flex max-w-6xl items-center justify-between px-6 py-6">
                    <Link href={route('home')} aria-label="corebot home">
                        <ApplicationLogo
                            showWordmark
                            className="text-white"
                            markClassName="h-9 w-9"
                            wordmarkClassName="text-lg"
                        />
                    </Link>

                    <nav className="flex flex-wrap items-center justify-end gap-2 text-sm">
                        <a
                            href={GITHUB_URL}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="inline-flex items-center gap-2 rounded-md px-3 py-2 text-slate-300 transition hover:bg-slate-800 hover:text-white"
                            aria-label="View corebot on GitHub"
                        >
                            <GitHubIcon className="h-5 w-5" />
                            <span className="hidden sm:inline">GitHub</span>
                        </a>
                        <a
                            href={demoUrl}
                            className="rounded-md px-3 py-2 text-slate-300 hover:bg-slate-800 hover:text-white"
                        >
                            Live demo
                        </a>
                        {auth.user ? (
                            <Link
                                href={route('dashboard')}
                                className="rounded-md bg-white px-4 py-2 font-medium text-slate-950"
                            >
                                Dashboard
                            </Link>
                        ) : (
                            <>
                                <Link
                                    href={route('login')}
                                    className="rounded-md px-4 py-2 text-slate-200 hover:text-white"
                                >
                                    Log in
                                </Link>
                                <Link
                                    href={route('register')}
                                    className="rounded-md bg-white px-4 py-2 font-medium text-slate-950"
                                >
                                    Register
                                </Link>
                            </>
                        )}
                    </nav>
                </header>

                <section className="mx-auto grid max-w-6xl items-center gap-10 px-6 py-16 lg:grid-cols-[1.1fr_0.9fr]">
                    <div>
                        <p className="text-sm font-medium uppercase tracking-[0.18em] text-cyan-300">
                            Open source · Multi-tenant CRM support
                        </p>
                        <h1 className="mt-5 max-w-3xl text-4xl font-semibold tracking-tight sm:text-6xl">
                            corebot answers from your approved CRM knowledge.
                        </h1>
                        <p className="mt-6 max-w-2xl text-lg leading-8 text-slate-300">
                            Self-host a Laravel admin app, connect each client&apos;s OpenAI key, index CRM
                            documentation, and embed a customizable chat widget on any site — without inventing
                            policies or account-specific answers.
                        </p>

                        <div className="mt-8 flex flex-wrap gap-3">
                            <Link
                                href={auth.user ? route('dashboard') : route('login')}
                                className="rounded-md bg-cyan-300 px-5 py-3 text-sm font-semibold text-slate-950"
                            >
                                Open dashboard
                            </Link>
                            <a
                                href={demoUrl}
                                className="rounded-md border border-slate-700 px-5 py-3 text-sm font-semibold text-slate-100 hover:border-slate-500"
                            >
                                Try live demo
                            </a>
                            <a
                                href={GITHUB_URL}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="inline-flex items-center gap-2 rounded-md border border-slate-700 px-5 py-3 text-sm font-semibold text-slate-100 hover:border-slate-500"
                            >
                                <GitHubIcon className="h-4 w-4" />
                                Source code
                            </a>
                        </div>

                        <p className="mt-6 text-sm text-slate-400">
                            Need installation, integrations, or a tailored rollout?{' '}
                            <a
                                href={UPWORK_URL}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="font-medium text-cyan-300 hover:text-cyan-200"
                            >
                                Get in touch on Upwork
                            </a>{' '}
                            or send a message below.
                        </p>
                    </div>

                    <div className="rounded-lg border border-slate-800 bg-slate-900 p-5 shadow-2xl">
                        <div className="rounded-md bg-slate-950 p-4">
                            <div className="flex items-center justify-between border-b border-slate-800 pb-3">
                                <span className="text-sm font-semibold">CRM Support</span>
                                <span className="rounded-full bg-emerald-400/10 px-2 py-1 text-xs text-emerald-300">
                                    RAG · Streaming
                                </span>
                            </div>
                            <div className="space-y-3 py-5 text-sm">
                                <div className="max-w-[85%] rounded-md bg-slate-800 p-3 text-slate-200">
                                    How do I change an order from Processing to Completed?
                                </div>
                                <div className="ml-auto max-w-[90%] rounded-md bg-cyan-300 p-3 text-slate-950">
                                    Completed orders are locked for editing except by managers. Confirm the order
                                    is ready, then update the status from the Orders tab.
                                </div>
                            </div>
                            <div className="rounded-md border border-slate-800 px-3 py-2 text-sm text-slate-500">
                                Answers grounded in your knowledge base only
                            </div>
                        </div>
                    </div>
                </section>

                <section className="mx-auto max-w-6xl px-6 pb-12">
                    <h2 className="text-center text-2xl font-semibold">What you get</h2>
                    <div className="mt-8 grid gap-5 sm:grid-cols-2">
                        {[
                            {
                                title: 'Tenant-safe by design',
                                body: 'Each CRM client is a separate tenant. Bots, knowledge, conversations, and API keys never cross boundaries — enforced in queries, not a generic tenancy package.',
                            },
                            {
                                title: 'Bring your own OpenAI key',
                                body: 'Tenants store their own API key (encrypted). Test the connection from the admin UI before chat or indexing is allowed.',
                            },
                            {
                                title: 'Knowledge that matches your CRM',
                                body: 'Upload text, FAQs, PDFs, and Word documents. Content is chunked, embedded with pgvector, and searched per bot before every answer.',
                            },
                            {
                                title: 'Controlled answers',
                                body: 'Strict RAG prompts, similarity thresholds, and fallback messages. When context is missing, the bot escalates instead of guessing.',
                            },
                            {
                                title: 'Embeddable widget',
                                body: 'Vanilla JavaScript embed with configurable colors, position, labels, and launcher icon. Streaming replies and visitor contact capture.',
                            },
                            {
                                title: 'Operator visibility',
                                body: 'Review conversations, retrieval logs, chunk context, and AI usage from a React admin dashboard.',
                            },
                        ].map(({ title, body }) => (
                            <div key={title} className="rounded-lg border border-slate-800 bg-slate-900/80 p-5">
                                <h3 className="font-semibold text-cyan-200">{title}</h3>
                                <p className="mt-2 text-sm leading-6 text-slate-400">{body}</p>
                            </div>
                        ))}
                    </div>
                </section>

                <section id="support" className="mx-auto max-w-3xl px-6 pb-20">
                    <div className="rounded-lg border border-slate-800 bg-slate-900 p-6 sm:p-8">
                        <h2 className="text-2xl font-semibold">Work with us</h2>
                        <p className="mt-2 text-sm leading-6 text-slate-400">
                            Whether you need help installing corebot, adapting it to your stack, white-labeling the
                            widget, or building custom CRM integrations — we&apos;d like to hear from you. Share a
                            few details and we&apos;ll respond by email.
                        </p>

                        {(flash?.success || flash?.error) && (
                            <div
                                className={`mt-4 rounded-lg border px-4 py-3 text-sm ${
                                    flash?.error
                                        ? 'border-red-800/60 bg-red-950/40 text-red-200'
                                        : 'border-emerald-800/60 bg-emerald-950/40 text-emerald-200'
                                }`}
                            >
                                {flash.error || flash.success}
                            </div>
                        )}

                        <form onSubmit={submitSupport} className="mt-6 space-y-5">
                            <div className="grid gap-5 sm:grid-cols-2">
                                <div>
                                    <InputLabel htmlFor="support_name" value="Name" className="text-slate-200" />
                                    <TextInput
                                        id="support_name"
                                        value={data.name}
                                        onChange={(e) => setData('name', e.target.value)}
                                        className="mt-1 block w-full border-slate-700 bg-slate-950 text-white"
                                        required
                                    />
                                    <InputError message={errors.name} className="mt-1" />
                                </div>
                                <div>
                                    <InputLabel htmlFor="support_email" value="Email" className="text-slate-200" />
                                    <TextInput
                                        id="support_email"
                                        type="email"
                                        value={data.email}
                                        onChange={(e) => setData('email', e.target.value)}
                                        className="mt-1 block w-full border-slate-700 bg-slate-950 text-white"
                                        required
                                    />
                                    <InputError message={errors.email} className="mt-1" />
                                </div>
                            </div>

                            <div className="grid gap-5 sm:grid-cols-2">
                                <div>
                                    <InputLabel htmlFor="support_company" value="Company (optional)" className="text-slate-200" />
                                    <TextInput
                                        id="support_company"
                                        value={data.company}
                                        onChange={(e) => setData('company', e.target.value)}
                                        className="mt-1 block w-full border-slate-700 bg-slate-950 text-white"
                                    />
                                    <InputError message={errors.company} className="mt-1" />
                                </div>
                                <div>
                                    <InputLabel htmlFor="support_type" value="Request type" className="text-slate-200" />
                                    <select
                                        id="support_type"
                                        value={data.type}
                                        onChange={(e) => setData('type', e.target.value)}
                                        className="mt-1 block w-full rounded-md border-slate-700 bg-slate-950 text-white shadow-sm focus:border-cyan-500 focus:ring-cyan-500"
                                    >
                                        {REQUEST_TYPES.map((option) => (
                                            <option key={option.value} value={option.value}>
                                                {option.label}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.type} className="mt-1" />
                                </div>
                            </div>

                            <div>
                                <InputLabel htmlFor="support_message" value="How can we help?" className="text-slate-200" />
                                <textarea
                                    id="support_message"
                                    rows={5}
                                    value={data.message}
                                    onChange={(e) => setData('message', e.target.value)}
                                    required
                                    className="mt-1 block w-full rounded-md border-slate-700 bg-slate-950 text-white shadow-sm focus:border-cyan-500 focus:ring-cyan-500"
                                    placeholder="Tell us about your CRM, timeline, and what you need…"
                                />
                                <InputError message={errors.message} className="mt-1" />
                            </div>

                            <div className="flex flex-wrap items-center gap-3">
                                <PrimaryButton disabled={processing} className="!normal-case !tracking-normal">
                                    {processing ? 'Sending…' : 'Send message'}
                                </PrimaryButton>
                                {recentlySuccessful && (
                                    <span className="text-sm text-emerald-400">Thank you — we&apos;ll be in touch.</span>
                                )}
                            </div>
                        </form>

                        <div className="mt-6 flex flex-wrap gap-4 border-t border-slate-800 pt-6 text-sm">
                            <a
                                href={UPWORK_URL}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="text-cyan-300 hover:text-cyan-200"
                            >
                                Upwork →
                            </a>
                            <a
                                href={GITHUB_URL}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="inline-flex items-center gap-1.5 text-cyan-300 hover:text-cyan-200"
                            >
                                <GitHubIcon className="h-4 w-4" />
                                GitHub
                            </a>
                            <a
                                href={PRODUCT_URL}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="text-cyan-300 hover:text-cyan-200"
                            >
                                CoreFix Lab →
                            </a>
                        </div>
                    </div>
                </section>
            </main>
        </>
    );
}

function GitHubIcon({ className = 'h-5 w-5' }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
            <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z" />
        </svg>
    );
}
