export const ACTIVE_STATUSES = ['queued', 'processing', 'draft'];

export function normalizeStatus(status) {
    return status === 'draft' ? 'queued' : status;
}

export function isInProgress(status) {
    return ACTIVE_STATUSES.includes(status);
}

export function canReprocess(status) {
    return ['ready', 'failed', 'cancelled'].includes(normalizeStatus(status));
}

export function canEdit(status) {
    return ['ready', 'failed'].includes(normalizeStatus(status));
}

export function canCancel(status) {
    return isInProgress(status);
}

export function statusLabel(status) {
    const labels = {
        queued: 'Queued',
        processing: 'Indexing',
        ready: 'Ready',
        failed: 'Failed',
        cancelled: 'Cancelled',
    };

    return labels[normalizeStatus(status)] || status;
}

export function statusDescription(status) {
    const descriptions = {
        queued: 'Waiting for a worker to start chunking and embedding.',
        processing: 'Extracting text, splitting into chunks, and generating embeddings.',
        ready: 'Indexed and available for retrieval.',
        failed: 'Processing stopped due to an error. You can edit or reprocess.',
        cancelled: 'Processing was cancelled before completion.',
    };

    return descriptions[normalizeStatus(status)] || '';
}

export function statusStyles(status) {
    const key = normalizeStatus(status);

    return {
        queued: 'bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-200',
        processing: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
        ready: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
        failed: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
        cancelled: 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
    }[key] || 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300';
}
