<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Support requests (public form on the welcome page)
    |--------------------------------------------------------------------------
    */

    'support_request_email' => env('SUPPORT_REQUEST_EMAIL'),

    /*
    |--------------------------------------------------------------------------
    | MaxMind GeoLite2 database (GeoIP2 City .mmdb)
    |--------------------------------------------------------------------------
    */

    'geoip_database_path' => env('GEOIP_DATABASE_PATH'),

    /*
    |--------------------------------------------------------------------------
    | Public demo page (/demo)
    |--------------------------------------------------------------------------
    */

    'demo_bot_public_key' => env('DEMO_BOT_PUBLIC_KEY'),

    /*
    |--------------------------------------------------------------------------
    | DOCX text extraction (python-docx via scripts/extract_docx.py)
    |--------------------------------------------------------------------------
    |
    | Absolute path to a Python 3 interpreter (e.g. project venv). When null,
    | Laravel invokes `python3` from the worker process PATH.
    |
    */

    'docx_python' => env('DOCX_PYTHON'),

    /*
    |--------------------------------------------------------------------------
    | Knowledge research (fetch URL / web search helper on Knowledge page)
    |--------------------------------------------------------------------------
    */

    'knowledge_research' => [
        'fetch_timeout' => (int) env('KNOWLEDGE_RESEARCH_FETCH_TIMEOUT', 20),
        'max_content_length' => (int) env('KNOWLEDGE_RESEARCH_MAX_CONTENT_LENGTH', 50000),
        'search_max_results' => (int) env('KNOWLEDGE_RESEARCH_SEARCH_MAX_RESULTS', 3),
        'user_agent' => env('KNOWLEDGE_RESEARCH_USER_AGENT', 'corebot-knowledge-research/1.0'),
        'tavily_api_key' => env('TAVILY_API_KEY'),
    ],

];
