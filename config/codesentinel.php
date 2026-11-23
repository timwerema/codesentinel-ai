<?php

return [

    /*
    | What may be submitted. Extensions map to the language name used for scanning and display.
    | Anything else is refused. Uploaded code is only ever read as text: it is never executed.
    */
    'languages' => [
        'py' => 'python', 'js' => 'javascript', 'ts' => 'typescript', 'tsx' => 'typescript',
        'php' => 'php', 'java' => 'java', 'c' => 'c', 'cpp' => 'cpp',
    ],

    'limits' => [
        'max_files' => (int) env('CODESENTINEL_MAX_FILES', 10),
        'max_file_bytes' => (int) env('CODESENTINEL_MAX_FILE_BYTES', 200 * 1024),
        'max_total_bytes' => (int) env('CODESENTINEL_MAX_TOTAL_BYTES', 1024 * 1024),
        'analyses_per_hour' => (int) env('CODESENTINEL_ANALYSES_PER_HOUR', 20),
    ],

    'scanners' => [
        'semgrep' => [
            'binary' => env('SEMGREP_BINARY', 'semgrep'),
            'rules' => base_path('scanner/rules'),
        ],
        'gitleaks' => [
            'binary' => env('GITLEAKS_BINARY', 'gitleaks'),
        ],
        // Hard limits for every scanner process.
        'timeout_seconds' => (int) env('SCANNER_TIMEOUT', 60),
        'memory_mb' => (int) env('SCANNER_MEMORY_MB', 2048),
        'cpu_seconds' => (int) env('SCANNER_CPU_SECONDS', 90),
        // When the app itself runs as root (some containers), scanners are started as this
        // unprivileged user instead. Ignored when the app already runs unprivileged.
        'drop_to_uid' => (int) env('SCANNER_DROP_UID', 65534),
        'drop_to_gid' => (int) env('SCANNER_DROP_GID', 65534),
    ],

    'ai' => [
        // "auto": Claude when ANTHROPIC_API_KEY is set, otherwise the labelled demo provider.
        // "demo" forces demo mode. "off" skips the AI step.
        'mode' => env('CODESENTINEL_AI', 'auto'),
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-opus-5-5'),
        'timeout_seconds' => (int) env('ANTHROPIC_TIMEOUT', 60),
        'max_output_tokens' => (int) env('ANTHROPIC_MAX_TOKENS', 16000),
        'effort' => env('ANTHROPIC_EFFORT', 'medium'),
        'max_retries' => (int) env('ANTHROPIC_MAX_RETRIES', 2),
        'reviews_per_hour' => (int) env('CODESENTINEL_AI_REVIEWS_PER_HOUR', 10),
        'max_source_chars' => (int) env('CODESENTINEL_AI_MAX_SOURCE_CHARS', 60000),
        // US dollars per million tokens, for the cost estimate only. Defaults are the published
        // claude-opus-5-5 rates when this was written; set your own if you change the model.
        'price_input_per_mtok' => env('ANTHROPIC_PRICE_INPUT', 4.00),
        'price_output_per_mtok' => env('ANTHROPIC_PRICE_OUTPUT', 20.00),
    ],
];
