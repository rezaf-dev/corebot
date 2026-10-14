import { currentLocale, t } from '@/localization';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { MessageBubble } from '@/Components/ConversationMessage';
import {
    Head,
    Link,
    router,
    useForm,
    usePage,
    usePoll,
} from '@inertiajs/react';
import { useEffect, useLayoutEffect, useRef, useState } from 'react';

const queues = [
    ['needs_reply', 'Needs reply'],
    ['mine', 'Mine'],
    ['unassigned', 'Unassigned'],
    ['all', 'All'],
    ['resolved', 'Resolved'],
    ['leads', 'Has contact'],
];
const control =
    'rounded-lg border-gray-300 bg-white text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100';
const button =
    'rounded-lg border border-gray-200 px-3 py-2 text-xs font-medium hover:bg-gray-50 focus-visible:ring-2 focus-visible:ring-indigo-500 disabled:opacity-50 dark:border-gray-700 dark:hover:bg-gray-800';

export default function Inbox({
    conversations,
    counts,
    filters,
    conversation,
    bots,
    tenants,
    staff,
}) {
    const { auth } = usePage().props;
    const [search, setSearch] = useState(filters.search || '');
    const [details, setDetails] = useState(false);
    const [busy, setBusy] = useState(false);
    const [navigationPending, setNavigationPending] = useState(false);
    const [refreshError, setRefreshError] = useState(false);
    const [selectionMode, setSelectionMode] = useState(false);
    const [selectedIds, setSelectedIds] = useState([]);
    const [bulkError, setBulkError] = useState('');
    const listRef = useRef(null);
    const savedListScroll = useRef(0);
    const previousFilters = useRef(JSON.stringify(filters));
    const query = { ...filters, page: conversations.current_page };
    const visibleIds = conversations.data.map((item) => item.id);
    const allSelected = visibleIds.length > 0 && visibleIds.every((id) => selectedIds.includes(id));
    useEffect(() => {
        setSelectedIds((ids) => ids.filter((id) => visibleIds.includes(id)));
    }, [visibleIds.join(',')]);
    useEffect(() => {
        setSelectedIds([]);
        setBulkError('');
    }, [JSON.stringify(filters), conversations.current_page]);
    const toggleSelection = (id) =>
        setSelectedIds((ids) => ids.includes(id) ? ids.filter((value) => value !== id) : [...ids, id]);
    const bulkAction = (action) => {
        if (busy || !selectedIds.length) return;
        if (action === 'delete' && !window.confirm(t('Delete :count selected conversations? This cannot be undone.', { count: selectedIds.length }))) return;
        setBulkError('');
        router.post(route('conversations.bulk-action'), { action, conversation_ids: selectedIds, filters }, {
            preserveScroll: true,
            onStart: () => setBusy(true),
            onSuccess: () => {
                setSelectedIds([]);
                setSelectionMode(false);
            },
            onError: () => setBulkError(t('Unable to update the selection. Refresh and try again.')),
            onFinish: () => setBusy(false),
        });
    };
    usePoll(5000, {
        only: ['conversations', 'counts', 'conversation', 'staff'],
        preserveScroll: true,
    });
    useEffect(() => {
        const current = JSON.stringify(filters);
        if (previousFilters.current !== current) {
            savedListScroll.current = 0;
            if (listRef.current) listRef.current.scrollTop = 0;
            previousFilters.current = current;
        }
    }, [filters]);
    useEffect(() => {
        const offException = router.on('exception', (event) => {
            event.preventDefault();
            setRefreshError(true);
        });
        const offSuccess = router.on('success', () => setRefreshError(false));
        return () => {
            offException();
            offSuccess();
        };
    }, []);
    useEffect(() => setSearch(filters.search || ''), [filters.search]);
    useEffect(() => {
        if (listRef.current)
            listRef.current.scrollTop = savedListScroll.current;
        setDetails(false);
    }, [conversation?.id]);
    const filter = (values) =>
        router.get(
            route('conversations.index'),
            { ...filters, page: 1, ...values },
            {
                preserveState: true,
                preserveScroll: true,
                onStart: () => setNavigationPending(true),
                onFinish: () => setNavigationPending(false),
            },
        );
    const act = (action, assigned_to) => {
        if (busy) return;
        router.patch(
            route('conversations.update', conversation.id),
            { action, assigned_to },
            {
                preserveScroll: true,
                onStart: () => setBusy(true),
                onFinish: () => setBusy(false),
            },
        );
    };
    return (
        <AuthenticatedLayout workspace>
            <Head
                title={
                    conversation ? visitor(conversation) : t('Conversations')
                }
            />
            {refreshError && (
                <div
                    role="alert"
                    className="flex shrink-0 items-center justify-between gap-3 bg-amber-50 px-4 py-2 text-sm text-amber-900 dark:bg-amber-950 dark:text-amber-200"
                >
                    {t('Unable to refresh. Your draft is safe.')}
                    <button className={button} onClick={() => router.reload()}>
                        {t('Retry')}
                    </button>
                </div>
            )}
            <div className="flex min-h-0 flex-1">
                <section
                    aria-label={t('Conversation list')}
                    className={`${conversation ? 'hidden lg:flex' : 'flex'} w-full shrink-0 flex-col border-e border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 lg:w-80`}
                >
                    <div className="flex items-center justify-between px-4 pb-3 pt-5">
                        <h1 className="text-lg font-semibold">
                            {t('Conversations')}
                        </h1>
                        <div className="flex items-center gap-2">
                            <span className="rounded-full bg-gray-100 px-2 py-1 text-xs dark:bg-gray-800">{counts.all}</span>
                            {auth.capabilities.configure && (
                                <button
                                    type="button"
                                    className={button}
                                    disabled={busy}
                                    aria-pressed={selectionMode}
                                    onClick={() => {
                                        setSelectionMode(!selectionMode);
                                        setSelectedIds([]);
                                        setBulkError('');
                                    }}
                                >
                                    {t(selectionMode ? 'Cancel' : 'Select')}
                                </button>
                            )}
                        </div>
                    </div>
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            filter({ search });
                        }}
                        className="flex gap-2 px-4 pb-3"
                    >
                        <input
                            aria-label={t('Search conversations')}
                            placeholder={t('Search conversations')}
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className={`${control} min-w-0 flex-1`}
                        />
                        <button className={button} type="submit">
                            {t('Search')}
                        </button>
                    </form>
                    <div className="flex gap-2 px-4 pb-3">
                        <select
                            aria-label={t('Bot')}
                            value={filters.bot_id || ''}
                            onChange={(e) => filter({ bot_id: e.target.value })}
                            className={`${control} min-w-0 flex-1`}
                        >
                            <option value="">{t('All bots')}</option>
                            {bots.map((bot) => (
                                <option key={bot.id} value={bot.id}>
                                    {bot.name}
                                </option>
                            ))}
                        </select>
                        {auth.capabilities.platform && (
                            <select
                                aria-label={t('Tenant')}
                                value={filters.tenant_id || ''}
                                onChange={(e) =>
                                    filter({
                                        tenant_id: e.target.value,
                                        bot_id: '',
                                    })
                                }
                                className={`${control} min-w-0 flex-1`}
                            >
                                <option value="">{t('All tenants')}</option>
                                {tenants.map((tenant) => (
                                    <option key={tenant.id} value={tenant.id}>
                                        {tenant.name}
                                    </option>
                                ))}
                            </select>
                        )}
                    </div>
                    <nav
                        aria-label={t('Conversation queues')}
                        className="grid grid-cols-3 gap-1 border-b border-gray-200 px-3 pb-3 dark:border-gray-800"
                    >
                        {queues.map(([tab, label]) => (
                            <button
                                key={tab}
                                type="button"
                                onClick={() => filter({ tab })}
                                aria-pressed={filters.tab === tab}
                                className={`rounded-lg px-2 py-2 text-xs focus-visible:ring-2 focus-visible:ring-indigo-500 ${filters.tab === tab ? 'bg-indigo-50 font-semibold text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300' : 'text-gray-500 hover:bg-gray-50 dark:text-gray-400 dark:hover:bg-gray-800'}`}
                            >
                                {t(label)}
                                <span className="ms-1 opacity-70">
                                    {counts[tab]}
                                </span>
                            </button>
                        ))}
                    </nav>
                    {auth.capabilities.configure && selectionMode && (
                        <div className="space-y-2 border-b border-gray-200 bg-gray-50 p-3 dark:border-gray-800 dark:bg-gray-950">
                            <div className="flex items-center justify-between gap-2">
                                <label className="flex items-center gap-2 text-xs">
                                    <input type="checkbox" checked={allSelected} disabled={busy || !visibleIds.length} onChange={(event) => setSelectedIds(event.target.checked ? visibleIds : [])} className="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900" />
                                    {t('Select this page')}
                                </label>
                                <span role="status" className="text-xs text-gray-500">{t(':count selected', { count: selectedIds.length })}</span>
                            </div>
                            <div className="flex gap-2">
                                <button type="button" className={button} disabled={busy || !selectedIds.length} onClick={() => bulkAction('resolve')}>{t('Resolve selected')}</button>
                                <button type="button" className={`${button} text-red-600 dark:text-red-400`} disabled={busy || !selectedIds.length} onClick={() => bulkAction('delete')}>{t('Delete selected')}</button>
                            </div>
                            {bulkError && <p role="alert" className="text-xs text-red-600 dark:text-red-400">{bulkError}</p>}
                        </div>
                    )}
                    <div
                        aria-busy={navigationPending}
                        ref={listRef}
                        onScroll={(e) => {
                            savedListScroll.current = e.currentTarget.scrollTop;
                        }}
                        className="min-h-0 flex-1 overflow-y-auto"
                    >
                        {navigationPending && (
                            <p
                                role="status"
                                className="animate-pulse px-4 py-2 text-xs text-gray-500"
                            >
                                {t('Loading…')}
                            </p>
                        )}
                        {conversations.data.length === 0 && (
                            <div className="px-6 py-12 text-center">
                                <p className="font-medium">
                                    {t('No conversations match this filter.')}
                                </p>
                                <p className="mt-2 text-sm text-gray-500">
                                    {t('Try another queue or search.')}
                                </p>
                            </div>
                        )}
                        {conversations.data.map((item) => (
                            <div key={item.id} className={`flex border-b border-gray-100 dark:border-gray-800 ${selectedIds.includes(item.id) ? 'bg-indigo-50 dark:bg-indigo-950/40' : ''}`}>
                                {auth.capabilities.configure && selectionMode && (
                                    <div className="flex items-start ps-3 pt-5">
                                        <input
                                            type="checkbox"
                                            aria-label={t('Select conversation :id', { id: item.id })}
                                            checked={selectedIds.includes(item.id)}
                                            disabled={busy}
                                            onChange={() => toggleSelection(item.id)}
                                            className="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900"
                                        />
                                    </div>
                                )}
                                <Link
                                    href={route('conversations.show', {
                                        conversation: item.id,
                                        ...query,
                                    })}
                                    preserveState
                                    preserveScroll
                                    aria-current={
                                        conversation?.id === item.id
                                            ? 'page'
                                            : undefined
                                    }
                                    className={`block min-w-0 flex-1 px-4 py-4 focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-indigo-500 ${conversation?.id === item.id ? 'bg-indigo-50 dark:bg-indigo-950/40' : 'hover:bg-gray-50 dark:hover:bg-gray-800/50'}`}
                                >
                                    <div className="flex items-center justify-between gap-2">
                                        <span className="truncate text-sm font-semibold">
                                            {visitor(item)}
                                        </span>
                                        <time className="shrink-0 text-[11px] text-gray-500">
                                            {date(item.updated_at)}
                                        </time>
                                    </div>
                                    <p
                                        dir="auto"
                                        className="mt-1 truncate text-sm text-gray-500 dark:text-gray-400"
                                    >
                                        {item.latest_message?.content ||
                                            t('No messages yet')}
                                    </p>
                                    <p className="mt-1 truncate text-[11px] text-gray-500 dark:text-gray-400" title={t('Country')}>
                                        {item.country_name || item.country_code || t('Unknown country')}
                                    </p>
                                    <div className="mt-2 flex items-center justify-between gap-2 text-[11px] text-gray-500">
                                        <span className="truncate">
                                            {item.bot?.name}
                                            {' · '}
                                            {item.assignee?.name || t('Unassigned')}
                                        </span>
                                        <span
                                            className={
                                                item.support_mode === 'human' &&
                                                item.status !== 'resolved' &&
                                                (item.needs_reply ||
                                                    item.status === 'escalated')
                                                    ? 'font-semibold text-amber-700 dark:text-amber-300'
                                                    : ''
                                            }
                                        >
                                            {t(
                                                item.status === 'resolved'
                                                    ? 'Resolved'
                                                    : item.support_mode === 'human'
                                                      ? item.needs_reply ||
                                                        item.status === 'escalated'
                                                          ? 'Needs reply'
                                                          : 'Human support'
                                                      : 'AI',
                                            )}
                                        </span>
                                    </div>
                                </Link>
                            </div>
                        ))}
                    </div>
                    {conversations.last_page > 1 && (
                        <div className="flex items-center justify-between border-t border-gray-200 p-3 dark:border-gray-800">
                            <button
                                disabled={conversations.current_page <= 1}
                                className={button}
                                onClick={() =>
                                    filter({
                                        page: conversations.current_page - 1,
                                    })
                                }
                            >
                                {t('Previous')}
                            </button>
                            <span className="text-xs">
                                {conversations.current_page}
                                {' / '}
                                {conversations.last_page}
                            </span>
                            <button
                                disabled={
                                    conversations.current_page >=
                                    conversations.last_page
                                }
                                className={button}
                                onClick={() =>
                                    filter({
                                        page: conversations.current_page + 1,
                                    })
                                }
                            >
                                {t('Next')}
                            </button>
                        </div>
                    )}
                </section>
                {conversation ? (
                    <>
                        <section
                            className={`${details ? 'hidden xl:flex' : 'flex'} min-w-0 flex-1 flex-col`}
                        >
                            <div className="flex shrink-0 flex-wrap items-center justify-between gap-3 border-b border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                                <div className="flex min-w-0 items-center gap-3">
                                    <Link
                                        className={`${button} lg:hidden`}
                                        href={route(
                                            'conversations.index',
                                            query,
                                        )}
                                        preserveState
                                    >
                                        {t('Back')}
                                    </Link>
                                    <div>
                                        <h2 className="truncate font-semibold">
                                            {visitor(conversation)}
                                        </h2>
                                        <p className="text-xs text-gray-500">
                                            {conversation.bot?.name}
                                            {' · #'}
                                            {conversation.id}
                                            {' · '}
                                            {t(
                                                conversation.support_mode ===
                                                    'human'
                                                    ? 'Human support'
                                                    : 'AI',
                                            )}
                                        </p>
                                    </div>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    {!conversation.assigned_to && (
                                        <button
                                            disabled={busy}
                                            className={`${button} bg-indigo-600 text-white hover:bg-indigo-700 dark:hover:bg-indigo-700`}
                                            onClick={() => act('claim')}
                                        >
                                            {t('Claim')}
                                        </button>
                                    )}
                                    {conversation.can_manage && (
                                        <>
                                            <button
                                                disabled={busy}
                                                className={button}
                                                onClick={() =>
                                                    act(
                                                        conversation.status ===
                                                            'resolved'
                                                            ? 'reopen'
                                                            : 'resolve',
                                                    )
                                                }
                                            >
                                                {t(
                                                    conversation.status ===
                                                        'resolved'
                                                        ? 'Reopen'
                                                        : 'Resolve',
                                                )}
                                            </button>
                                            {conversation.support_mode ===
                                                'human' && (
                                                <button
                                                    disabled={busy}
                                                    className={button}
                                                    onClick={() =>
                                                        act('return_to_ai')
                                                    }
                                                >
                                                    {t('Return to AI')}
                                                </button>
                                            )}
                                        </>
                                    )}
                                    <button
                                        className={`${button} xl:hidden`}
                                        onClick={() => setDetails(true)}
                                    >
                                        {t('Customer details')}
                                    </button>
                                </div>
                            </div>
                            <div className="flex shrink-0 items-center gap-2 border-b border-gray-200 bg-white px-4 py-2 text-xs dark:border-gray-800 dark:bg-gray-900">
                                <span className="text-gray-500">
                                    {t('Owner')}
                                </span>
                                {conversation.can_manage ? (
                                    <select
                                        aria-label={t('Assign conversation')}
                                        disabled={busy}
                                        value={conversation.assigned_to || ''}
                                        onChange={(e) =>
                                            act(
                                                'assign',
                                                Number(e.target.value),
                                            )
                                        }
                                        className={`${control} max-w-[220px] py-1 text-xs`}
                                    >
                                        <option disabled value="">
                                            {t('Unassigned')}
                                        </option>
                                        {staff.map((person) => (
                                            <option
                                                key={person.id}
                                                value={person.id}
                                            >
                                                {person.name}
                                            </option>
                                        ))}
                                    </select>
                                ) : (
                                    <span>
                                        {conversation.assignee?.name ||
                                            t('Unassigned')}
                                    </span>
                                )}
                                <span className="ms-auto text-gray-500">
                                    {t(
                                        conversation.status === 'resolved'
                                            ? 'Resolved'
                                            : 'Open',
                                    )}
                                </span>
                            </div>
                            <Thread
                                key={conversation.id}
                                conversation={conversation}
                                query={query}
                            />
                        </section>
                        <aside
                            className={`${details ? 'flex' : 'hidden xl:flex'} w-full shrink-0 flex-col overflow-y-auto border-s border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900 xl:w-72`}
                            aria-label={t('Customer details')}
                        >
                            <div className="flex items-center justify-between">
                                <h2 className="font-semibold">
                                    {t('Customer details')}
                                </h2>
                                <button
                                    className={`${button} xl:hidden`}
                                    onClick={() => setDetails(false)}
                                >
                                    {t('Back')}
                                </button>
                            </div>
                            <dl className="mt-5 flex flex-col gap-4">
                                {[
                                    ['Visitor name', conversation.visitor_name],
                                    ['Email', conversation.visitor_email],
                                    ['Phone', conversation.visitor_phone],
                                    ['Country', conversation.country_name],
                                    ['City', conversation.city],
                                    ['Language', conversation.language],
                                    [
                                        'Handoff reason',
                                        conversation.handoff_reason,
                                    ],
                                ].map(([label, value]) => (
                                    <div key={label}>
                                        <dt className="text-xs text-gray-500">
                                            {t(label)}
                                        </dt>
                                        <dd className="mt-1 break-words text-sm">
                                            {value || '—'}
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                            {conversation.source_url && (
                                <a
                                    href={safeUrl(conversation.source_url)}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="mt-5 break-all text-sm text-indigo-600 underline dark:text-indigo-400"
                                >
                                    {t('Source page')}
                                </a>
                            )}
                            <h3 className="mt-7 border-t border-gray-200 pt-5 text-sm font-semibold dark:border-gray-800">
                                {t('Previous conversations')}
                            </h3>
                            <div className="mt-3 flex flex-col gap-2">
                                {conversation.history.length ? (
                                    conversation.history.map((item) => (
                                        <Link
                                            key={item.id}
                                            href={route('conversations.show', {
                                                conversation: item.id,
                                                ...query,
                                            })}
                                            preserveState
                                            className="rounded-lg bg-gray-50 p-3 text-xs hover:bg-indigo-50 dark:bg-gray-800"
                                        >
                                            {'#'}
                                            {item.id}
                                            {' · '}
                                            {date(item.created_at)}
                                            {' · '}
                                            {t(
                                                item.status === 'resolved'
                                                    ? 'Resolved'
                                                    : 'Open',
                                            )}
                                        </Link>
                                    ))
                                ) : (
                                    <p className="text-xs text-gray-500">
                                        {t('No previous conversations')}
                                    </p>
                                )}
                            </div>
                            {auth.capabilities.configure && (
                                <details className="mt-6 border-t border-gray-200 pt-4 dark:border-gray-800">
                                    <summary className="cursor-pointer text-sm font-medium">
                                        {t('Diagnostics')}
                                    </summary>
                                    <div className="mt-3 flex flex-col gap-3 text-xs">
                                        {[
                                            ...(
                                                conversation.retrieval_logs ||
                                                []
                                            ).map((log) => ({
                                                ...log,
                                                kind: 'Knowledge',
                                                value: log.context_text,
                                            })),
                                            ...(
                                                conversation.integration_action_logs ||
                                                []
                                            ).map((log) => ({
                                                ...log,
                                                kind: 'Integration actions (',
                                                value:
                                                    log.error || log.tool_name,
                                            })),
                                        ].map((log) => (
                                            <details
                                                key={`${log.kind}-${log.id}`}
                                            >
                                                <summary>
                                                    {t(log.kind)}
                                                    {' #'}
                                                    {log.id}
                                                </summary>
                                                <pre className="mt-2 max-h-52 overflow-auto whitespace-pre-wrap break-words rounded bg-gray-100 p-2 dark:bg-gray-800">
                                                    {log.value}
                                                </pre>
                                            </details>
                                        ))}
                                    </div>
                                </details>
                            )}
                            {conversation.can_delete && (
                                <button
                                    className={`${button} mt-6 text-red-600 dark:text-red-400`}
                                    onClick={() => {
                                        if (
                                            window.confirm(
                                                t(
                                                    'Delete this conversation? This cannot be undone.',
                                                ),
                                            )
                                        )
                                            router.delete(
                                                route(
                                                    'conversations.destroy',
                                                    conversation.id,
                                                ),
                                            );
                                    }}
                                >
                                    {t('Delete')}
                                </button>
                            )}
                        </aside>
                    </>
                ) : (
                    <div className="hidden flex-1 items-center justify-center p-8 text-center lg:flex">
                        <div>
                            <span
                                aria-hidden="true"
                                className="text-5xl text-indigo-300"
                            >
                                ◫
                            </span>
                            <h2 className="mt-5 text-lg font-semibold">
                                {t('Your support workspace')}
                            </h2>
                            <p className="mt-2 text-sm text-gray-500">
                                {t('Select a conversation to start helping.')}
                            </p>
                        </div>
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}

function Thread({ conversation, query }) {
    const form = useForm(`reply-${conversation.id}`, { message: '' });
    const [messages, setMessages] = useState(conversation.messages);
    const [hasOlder, setHasOlder] = useState(conversation.has_older_messages);
    const [loadingOlder, setLoadingOlder] = useState(false);
    const [olderError, setOlderError] = useState(false);
    const prependAnchor = useRef(null);
    const mergeMessages = (incoming) =>
        setMessages((current) =>
            [
                ...new Map(
                    [...current, ...incoming].map((message) => [
                        message.id,
                        message,
                    ]),
                ).values(),
            ].sort((a, b) => a.id - b.id),
        );
    useEffect(() => {
        mergeMessages(conversation.messages);
    }, [conversation.messages]);
    const scroller = useRef(null);
    const atBottom = useRef(true);
    const previous = useRef(messages.at(-1)?.id);
    const [newMessages, setNewMessages] = useState(false);
    const bottom = () => {
        if (scroller.current)
            scroller.current.scrollTop = scroller.current.scrollHeight;
        setNewMessages(false);
        atBottom.current = true;
    };
    useEffect(() => {
        bottom();
    }, []);
    useLayoutEffect(() => {
        if (prependAnchor.current && scroller.current) {
            scroller.current.scrollTop +=
                scroller.current.scrollHeight - prependAnchor.current;
            prependAnchor.current = null;
        }
    }, [messages]);
    useEffect(() => {
        const latest = messages.at(-1)?.id;
        if (latest !== previous.current) {
            if (atBottom.current) bottom();
            else setNewMessages(true);
            previous.current = latest;
        }
    }, [messages]);
    const loadOlder = async () => {
        if (loadingOlder || !messages.length) return;
        setLoadingOlder(true);
        setOlderError(false);
        try {
            const response = await fetch(
                route('conversations.messages.index', {
                    conversation: conversation.id,
                    before_id: messages[0].id,
                }),
                { headers: { Accept: 'application/json' } },
            );
            if (!response.ok) throw new Error('Unable to load messages');
            const data = await response.json();
            prependAnchor.current = scroller.current?.scrollHeight;
            mergeMessages(data.messages);
            setHasOlder(data.has_more);
        } catch {
            setOlderError(true);
        } finally {
            setLoadingOlder(false);
        }
    };
    const send = (event) => {
        event.preventDefault();
        if (
            form.processing ||
            !form.data.message.trim() ||
            !conversation.can_manage ||
            conversation.status === 'resolved'
        )
            return;
        form.post(route('conversations.messages.store', conversation.id), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset('message');
                bottom();
            },
        });
    };
    return (
        <>
            <div
                ref={scroller}
                onScroll={(e) => {
                    const node = e.currentTarget;
                    atBottom.current =
                        node.scrollHeight - node.scrollTop - node.clientHeight <
                        80;
                    if (atBottom.current) setNewMessages(false);
                }}
                className="min-h-0 flex-1 overflow-y-auto p-4 sm:p-6"
                aria-label={t('Messages')}
            >
                {hasOlder && (
                    <button
                        disabled={loadingOlder}
                        className={`${button} mb-5 w-full`}
                        onClick={loadOlder}
                    >
                        {t(loadingOlder ? 'Loading…' : 'Load older messages')}
                    </button>
                )}
                {olderError && (
                    <p role="alert" className="mb-3 text-sm text-red-600">
                        {t('Unable to load older messages. Try again.')}
                    </p>
                )}
                <div
                    className="flex flex-col gap-4"
                    aria-live="polite"
                    aria-relevant="additions"
                >
                    {messages.map((message) => (
                        <MessageBubble key={message.id} message={message} />
                    ))}
                </div>
                {!messages.length && (
                    <p className="text-center text-sm text-gray-500">
                        {t('No messages yet')}
                    </p>
                )}
            </div>
            {newMessages && (
                <button
                    className="mx-auto mb-2 rounded-full bg-indigo-600 px-4 py-2 text-xs text-white"
                    onClick={bottom}
                >
                    {t('New messages')}
                </button>
            )}
            <form
                onSubmit={send}
                className="shrink-0 border-t border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900"
            >
                <label
                    htmlFor="reply"
                    className="mb-2 block text-xs font-semibold text-gray-500"
                >
                    {t('Reply to customer')}
                </label>
                <textarea
                    id="reply"
                    disabled={
                        !conversation.can_manage ||
                        conversation.status === 'resolved'
                    }
                    value={form.data.message}
                    onChange={(e) => form.setData('message', e.target.value)}
                    onKeyDown={(e) => {
                        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter')
                            send(e);
                    }}
                    maxLength={2000}
                    rows={3}
                    placeholder={t(
                        conversation.can_manage
                            ? 'Type your reply…'
                            : 'Claim this conversation to reply.',
                    )}
                    className={`${control} block w-full resize-none disabled:opacity-50`}
                />
                {form.errors.message && (
                    <p role="alert" className="mt-2 text-xs text-red-600">
                        {t(form.errors.message)}
                    </p>
                )}
                <div className="mt-3 flex items-center justify-between gap-3">
                    <span className="text-xs text-gray-500">
                        {t(
                            conversation.status === 'resolved'
                                ? 'Reopen to send a reply.'
                                : 'Replies are sent to the visitor’s widget.',
                        )}
                    </span>
                    <button
                        type="submit"
                        disabled={
                            form.processing ||
                            !form.data.message.trim() ||
                            !conversation.can_manage ||
                            conversation.status === 'resolved'
                        }
                        className="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-700 focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 disabled:opacity-50"
                    >
                        {t(form.processing ? 'Sending…' : 'Send reply')}
                    </button>
                </div>
            </form>
        </>
    );
}
function visitor(item) {
    return (
        item.visitor_name ||
        item.visitor_email ||
        item.visitor_phone ||
        t('Anonymous')
    );
}
function date(value) {
    return value
        ? new Intl.DateTimeFormat(currentLocale(), {
              month: 'short',
              day: 'numeric',
              hour: 'numeric',
              minute: '2-digit',
          }).format(new Date(value))
        : '—';
}
function safeUrl(value) {
    try {
        const url = new URL(value);
        return ['https:', 'http:'].includes(url.protocol)
            ? url.href
            : undefined;
    } catch {
        return undefined;
    }
}
