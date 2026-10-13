import { currentLocale, t } from '@/localization';

export function MessageBubble({ message }) {
    const isUser = message.role === 'user';
    const isAssistant = message.role === 'assistant';
    const isSystem = message.role === 'system';
    const isAdmin = message.role === 'admin';

    return (
        <div className={`flex ${isUser ? 'justify-end' : 'justify-start'}`}>
            <div
                className={`max-w-[85%] rounded-2xl px-4 py-3 ${
                    isUser
                        ? 'bg-indigo-600 text-white'
                        : isAdmin
                          ? 'border border-indigo-200 bg-indigo-50 text-gray-900 dark:border-indigo-900 dark:bg-indigo-950/40 dark:text-gray-100'
                          : isAssistant
                            ? 'border border-gray-200 bg-white text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100'
                            : 'border border-dashed border-gray-300 bg-gray-50 text-gray-600 dark:border-gray-600 dark:bg-gray-900/50 dark:text-gray-400'
                }`}
            >
                <div className="flex items-center justify-between gap-4">
                    <span
                        className={`text-xs font-semibold uppercase tracking-wide ${
                            isUser
                                ? 'text-indigo-200'
                                : isAdmin
                                  ? 'text-indigo-700 dark:text-indigo-300'
                                  : 'text-gray-500 dark:text-gray-400'
                        }`}
                    >
                        {isAdmin
                            ? message.metadata?.admin_name || t('Support team')
                            : t(
                                  isUser
                                      ? 'Visitor'
                                      : isAssistant
                                        ? 'Assistant'
                                        : 'System',
                              )}
                    </span>
                    {message.created_at && (
                        <span
                            className={`text-xs ${isUser ? 'text-indigo-200' : 'text-gray-400'}`}
                        >
                            {formatTime(message.created_at)}
                        </span>
                    )}
                </div>
                <div
                    dir="auto"
                    className={`markdown-message mt-2 text-sm ${isUser ? 'text-white' : ''}`}
                >
                    <MarkdownMessage content={message.content} />
                </div>
                {isSystem && (
                    <p className="mt-2 text-xs opacity-75">
                        {t('System instructions (not shown to visitors)')}
                    </p>
                )}
            </div>
        </div>
    );
}

function MarkdownMessage({ content }) {
    const lines = (content || '').split('\n');
    const blocks = [];
    let paragraph = [];
    let list = [];
    let code = [];
    let inCode = false;

    const flushParagraph = () => {
        if (paragraph.length) {
            blocks.push(
                <p key={`p-${blocks.length}`}>
                    {renderInlineMarkdown(paragraph.join(' '))}
                </p>,
            );
            paragraph = [];
        }
    };
    const flushList = () => {
        if (list.length) {
            blocks.push(
                <ul key={`ul-${blocks.length}`} className="list-disc ps-5">
                    {list.map((item, index) => (
                        <li key={index}>{renderInlineMarkdown(item)}</li>
                    ))}
                </ul>,
            );
            list = [];
        }
    };

    lines.forEach((line, index) => {
        if (line.startsWith('```')) {
            flushParagraph();
            flushList();
            if (inCode) {
                blocks.push(
                    <pre
                        key={`code-${blocks.length}`}
                        className="overflow-x-auto rounded bg-black/10 p-2 dark:bg-black/30"
                    >
                        <code dir="ltr">{code.join('\n')}</code>
                    </pre>,
                );
                code = [];
            }
            inCode = !inCode;
            return;
        }
        if (inCode) {
            code.push(line);
            return;
        }

        const heading = line.match(/^(#{1,3})\s+(.+)$/);
        const listItem = line.match(/^\s*[-*+]\s+(.+)$/);
        if (!line.trim() || heading || listItem) {
            flushParagraph();
        }
        if (heading) {
            flushList();
            const Tag = `h${heading[1].length}`;
            blocks.push(
                <Tag key={`h-${index}`} className="font-semibold">
                    {renderInlineMarkdown(heading[2])}
                </Tag>,
            );
        } else if (listItem) {
            list.push(listItem[1]);
        } else if (!line.trim()) {
            flushList();
        } else {
            flushList();
            paragraph.push(line);
        }
    });

    flushParagraph();
    flushList();
    if (inCode) {
        blocks.push(
            <pre
                key={`code-${blocks.length}`}
                className="overflow-x-auto rounded bg-black/10 p-2 dark:bg-black/30"
            >
                <code dir="ltr">{code.join('\n')}</code>
            </pre>,
        );
    }

    return <div className="space-y-2 break-words">{blocks}</div>;
}

function renderInlineMarkdown(text) {
    const parts = text
        .split(/(`[^`]+`|\*\*[^*]+\*\*|\*[^*]+\*|\[[^\]]+\]\([^)]+\))/g)
        .filter(Boolean);

    return parts.map((part, index) => {
        if (part.startsWith('`') && part.endsWith('`')) {
            return (
                <code
                    key={index}
                    className="rounded bg-black/10 px-1 dark:bg-black/30"
                >
                    {part.slice(1, -1)}
                </code>
            );
        }
        if (part.startsWith('**') && part.endsWith('**')) {
            return <strong key={index}>{part.slice(2, -2)}</strong>;
        }
        if (part.startsWith('*') && part.endsWith('*')) {
            return <em key={index}>{part.slice(1, -1)}</em>;
        }
        const link = part.match(/^\[([^\]]+)\]\(([^)]+)\)$/);
        if (link) {
            try {
                const url = new URL(link[2], window.location.origin);
                if (['http:', 'https:'].includes(url.protocol)) {
                    return (
                        <a
                            key={index}
                            href={url.href}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="underline"
                        >
                            {link[1]}
                        </a>
                    );
                }
            } catch {
                return link[1];
            }
            return link[1];
        }
        return part;
    });
}

function formatTime(value) {
    try {
        return new Intl.DateTimeFormat(currentLocale(), {
            timeStyle: 'short',
        }).format(new Date(value));
    } catch {
        return '';
    }
}
