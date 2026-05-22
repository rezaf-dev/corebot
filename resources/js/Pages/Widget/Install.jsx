import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';

export default function Install({ bots, widgetUrl }) {
    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-gray-800 dark:text-gray-200">Widget Install</h2>}>
            <Head title="Widget Install" />
            <div className="mx-auto max-w-5xl space-y-4 p-6">
                {bots.map((bot) => {
                    const snippet = `<script src="${widgetUrl}" data-bot-key="${bot.public_key}"></script>`;
                    return <div key={bot.id} className="rounded-lg bg-white p-5 shadow dark:bg-gray-800"><h3 className="font-semibold text-gray-900 dark:text-white">{bot.name}</h3><pre className="mt-3 overflow-auto rounded bg-gray-950 p-4 text-sm text-gray-100">{snippet}</pre></div>;
                })}
            </div>
        </AuthenticatedLayout>
    );
}
