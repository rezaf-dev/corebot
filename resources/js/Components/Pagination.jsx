import { formatNumber, t } from '@/localization';
import { Link } from '@inertiajs/react';

export default function Pagination({ links = [], meta }) {
    if (!meta || meta.last_page <= 1) {
        return null;
    }

    const prev = links[0];
    const next = links.at(-1);
    const pages = links.slice(1, -1);

    return (
        <nav className="flex flex-wrap items-center justify-between gap-3" aria-label={t("Pagination")}>
            <p className="text-sm text-gray-500 dark:text-gray-400">{t("Showing")}{' '}<span className="font-medium text-gray-700 dark:text-gray-300">{formatNumber(meta.from ?? 0)}</span>
                {' – '}
                <span className="font-medium text-gray-700 dark:text-gray-300">{formatNumber(meta.to ?? 0)}</span>
                {t(" of ")}
                <span className="font-medium text-gray-700 dark:text-gray-300">{formatNumber(meta.total)}</span>
            </p>
            <div className="flex flex-wrap items-center gap-1">
                <PageLink link={prev} label={t("Previous")} />
                {pages.map((link) => (
                    <PageLink key={link.label} link={link} />
                ))}
                <PageLink link={next} label={t("Next")} />
            </div>
        </nav>
    );
}

function PageLink({ link, label }) {
    const text = label || (Number.isFinite(Number(link?.label)) ? formatNumber(Number(link.label)) : stripHtml(link?.label));

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
