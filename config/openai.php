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
    'enabled' => env('OPENAI_ENABLED', true),

    // PDF vision fallback: when pure-PHP text extraction yields nothing (scanned
    // or image-only PDFs), send the PDF itself to a multimodal model. gpt-4.1-mini
    // is already multimodal, so the same model handles it — no local OCR, no
    // Ghostscript/Imagick, nothing extra on shared hosting.
    'pdf_fallback'  => (bool) env('OPENAI_PDF_FALLBACK', true),
    'vision_model'  => env('OPENAI_VISION_MODEL', env('OPENAI_EXTRACTION_MODEL', 'gpt-4.1-mini')),
    'max_pdf_bytes' => (int) env('OPENAI_MAX_PDF_BYTES', 15 * 1024 * 1024),

    // Guardrails
    'timeout' => (int) env('OPENAI_TIMEOUT', 120),
    'max_output_tokens' => (int) env('OPENAI_MAX_OUTPUT_TOKENS', 8000),

    // A statement's extracted text is chunked if it exceeds this many characters,
    // to stay within context limits on very long statements.
    'max_input_chars' => (int) env('OPENAI_MAX_INPUT_CHARS', 60000),
];
