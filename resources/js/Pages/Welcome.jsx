import { Head, Link } from '@inertiajs/react';

export default function Welcome({ auth }) {
    return (
        <>
            <Head title="CRM AI Support Bot" />

            <main className="min-h-screen bg-slate-950 text-white">
                <header className="mx-auto flex max-w-6xl items-center justify-between px-6 py-6">
                    <Link href="/" className="text-sm font-semibold tracking-wide">
                        CRM AI Support Bot
                    </Link>

                    <nav className="flex items-center gap-3 text-sm">
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

                <section className="mx-auto grid max-w-6xl items-center gap-10 px-6 py-20 lg:grid-cols-[1.1fr_0.9fr]">
                    <div>
                        <p className="text-sm font-medium uppercase tracking-[0.18em] text-cyan-300">
                            Multi-tenant CRM support
                        </p>
                        <h1 className="mt-5 max-w-3xl text-4xl font-semibold tracking-tight sm:text-6xl">
                            AI answers from your approved CRM knowledge.
                        </h1>
                        <p className="mt-6 max-w-2xl text-lg leading-8 text-slate-300">
                            Create tenant-owned support bots, upload CRM workflow
                            documents, connect each client&apos;s OpenAI key, and embed a
                            focused chat widget on their CRM or website.
                        </p>

                        <div className="mt-8 flex flex-wrap gap-3">
                            <Link
                                href={auth.user ? route('dashboard') : route('login')}
                                className="rounded-md bg-cyan-300 px-5 py-3 text-sm font-semibold text-slate-950"
                            >
                                Open dashboard
                            </Link>
                            <a
                                href="#workflow"
                                className="rounded-md border border-slate-700 px-5 py-3 text-sm font-semibold text-slate-100"
                            >
                                View workflow
                            </a>
                        </div>
                    </div>

                    <div className="rounded-lg border border-slate-800 bg-slate-900 p-5 shadow-2xl">
                        <div className="rounded-md bg-slate-950 p-4">
                            <div className="flex items-center justify-between border-b border-slate-800 pb-3">
                                <span className="text-sm font-semibold">CRM Support</span>
                                <span className="rounded-full bg-emerald-400/10 px-2 py-1 text-xs text-emerald-300">
                                    Active
                                </span>
                            </div>
                            <div className="space-y-3 py-5 text-sm">
                                <div className="max-w-[85%] rounded-md bg-slate-800 p-3 text-slate-200">
                                    How do I change an order from Processing to Completed?
                                </div>
                                <div className="ml-auto max-w-[90%] rounded-md bg-cyan-300 p-3 text-slate-950">
                                    Completed orders are locked for editing except by managers.
                                    Confirm the order is ready, then update the status from the
                                    Orders tab.
                                </div>
                                <div className="max-w-[80%] rounded-md bg-slate-800 p-3 text-slate-200">
                                    Can I promise a refund?
                                </div>
                                <div className="ml-auto max-w-[90%] rounded-md bg-cyan-300 p-3 text-slate-950">
                                    No. Refunds require manager approval before staff confirm
                                    anything to the customer.
                                </div>
                            </div>
                            <div className="rounded-md border border-slate-800 px-3 py-2 text-sm text-slate-500">
                                Ask a CRM workflow question
                            </div>
                        </div>
                    </div>
                </section>

                <section
                    id="workflow"
                    className="mx-auto grid max-w-6xl gap-4 px-6 pb-20 sm:grid-cols-2 lg:grid-cols-4"
                >
                    {[
                        ['Tenants', 'Create isolated CRM client workspaces.'],
                        ['Knowledge', 'Upload text, FAQ, PDF, and DOCX sources.'],
                        ['RAG Chat', 'Retrieve tenant chunks before answering.'],
                        ['Widget', 'Embed a vanilla JS chat panel anywhere.'],
                    ].map(([title, body]) => (
                        <div
                            key={title}
                            className="rounded-lg border border-slate-800 bg-slate-900 p-5"
                        >
                            <h2 className="font-semibold">{title}</h2>
                            <p className="mt-2 text-sm leading-6 text-slate-400">{body}</p>
                        </div>
                    ))}
                </section>
            </main>
        </>
    );
}
