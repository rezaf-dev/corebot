<?php

return [
    'query_embedding_cache_ttl' => (int) env('RAG_QUERY_EMBEDDING_CACHE_TTL', 3600),
    'history_message_limit' => (int) env('RAG_HISTORY_MESSAGE_LIMIT', 20),
];
