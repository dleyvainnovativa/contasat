<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OpenAI — document extraction
    |--------------------------------------------------------------------------
    | Used by the bank-statement extractor (Phase 2). gpt-4.1-mini is the
    | established choice across these projects: cheap enough for 50+ statements
    | a month, strong enough for structured extraction. Key is kept in .env.
    */
    'api_key' => env('OPENAI_API_KEY'),
    'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
    'model' => env('OPENAI_EXTRACTION_MODEL', 'gpt-4.1-mini'),

    // Guardrails
    'timeout' => (int) env('OPENAI_TIMEOUT', 120),
    'max_output_tokens' => (int) env('OPENAI_MAX_OUTPUT_TOKENS', 8000),

    // A statement's extracted text is chunked if it exceeds this many characters,
    // to stay within context limits on very long statements.
    'max_input_chars' => (int) env('OPENAI_MAX_INPUT_CHARS', 60000),
];
