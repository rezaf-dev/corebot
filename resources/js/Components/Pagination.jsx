import { Link } from '@inertiajs/react';

export default function Pagination({ links = [], meta }) {
    if (!meta || meta.last_page <= 1) {
        return null;
    }

    const prev = links.find((link) => link.label.includes('Previous'));
    const next = links.find((link) => link.label.includes('Next'));
    const pages = links.filter((link) => !link.label.includes('Previous') && !link.label.includes('Next'));

    return (
        <nav className="flex flex-wrap items-center justify-between gap-3" aria-label="Pagination">
            <p className="text-sm text-gray-500 dark:text-gray-400">
                Showing <span className="font-medium text-gray-700 dark:text-gray-300">{meta.from ?? 0}</span>
                {' – '}
                <span className="font-medium text-gray-700 dark:text-gray-300">{meta.to ?? 0}</span>
                {' of '}
                <span className="font-medium text-gray-700 dark:text-gray-300">{meta.total}</span>
            </p>
            <div className="flex flex-wrap items-center gap-1">
                <PageLink link={prev} label="Previous" />
                {pages.map((link) => (
                    <PageLink key={link.label} link={link} />
                ))}
                <PageLink link={next} label="Next" />
            </div>
        </nav>
    );
}

function PageLink({ link, label }) {
    const text = label || stripHtml(link?.label);

    if (!link?.url) {
        return (
            <span className="inline-flex min-w-[2.25rem] items-center justify-center rounded-md px-3 py-1.5 text-sm text-gray-400 dark:text-gray-500">
                {text}
            </span>
        );
    }

    return (
        <Link
            href={link.url}
            preserveScroll
            preserveState
            className={`inline-flex min-w-[2.25rem] items-center justify-center rounded-md px-3 py-1.5 text-sm transition ${
                link.active
                    ? 'bg-indigo-600 font-semibold text-white'
                    : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700'
            }`}
        >
            {text}
        </Link>
    );
}

function stripHtml(value) {
    return String(value || '').replace(/<[^>]*>/g, '').trim();
}
