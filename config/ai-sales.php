<?php

use App\Domain\AiSales\Enums\AiModelProfile;
use App\Domain\AiSales\Enums\AiProcessingContour;
use App\Domain\AiSales\Enums\AiTaskProfile;

return [
    /*
    | Stage 05 keeps the Unit runtime fake-only by default. The only other
    | accepted mode is timeweb_synthetic_only, which is guarded again inside
    | the provider and is never allowed to consume a Unit-derived request.
    */
    'enabled' => (bool) env('AI_SALES_ENABLED', false),
    'fake_execution_enabled' => (bool) env('AI_FAKE_EXECUTION_ENABLED', false),
    'external_calls_enabled' => (bool) env('AI_EXTERNAL_CALLS_ENABLED', false),
    'local_ru_calls_enabled' => (bool) env('AI_LOCAL_RU_CALLS_ENABLED', false),
    'external_sanitized_calls_enabled' => (bool) env('AI_EXTERNAL_SANITIZED_CALLS_ENABLED', false),
    'provider_failover_enabled' => (bool) env('AI_PROVIDER_FAILOVER_ENABLED', false),
    'web_search_enabled' => (bool) env('AI_WEB_SEARCH_ENABLED', false),
    'outreach_drafting_enabled' => (bool) env('AI_OUTREACH_DRAFTING_ENABLED', false),
    'outreach_sending_enabled' => (bool) env('AI_OUTREACH_SENDING_ENABLED', false),
    'autonomous_campaigns_enabled' => (bool) env('AI_AUTONOMOUS_CAMPAIGNS_ENABLED', false),
    'provider_native_tools_enabled' => (bool) env('AI_PROVIDER_NATIVE_TOOLS_ENABLED', false),
    'live_business_workflows_enabled' => (bool) env('AI_LIVE_BUSINESS_WORKFLOWS_ENABLED', false),

    'tools' => [
        'enabled' => (bool) env('AI_TOOLS_ENABLED', false),
        'aggregate_minimum_cohort' => 5,
    ],
    'workflows' => [
        'enabled' => (bool) env('AI_WORKFLOWS_ENABLED', false),
    ],

    'prospecting' => [
        'dossier_enabled' => (bool) env('AI_PROSPECTING_DOSSIER_ENABLED', false),
        'jobs_enabled' => (bool) env('AI_PROSPECTING_JOBS_ENABLED', false),
        'candidate_import_enabled' => (bool) env('AI_PROSPECTING_CANDIDATE_IMPORT_ENABLED', false),
        'auto_create_unit' => (bool) env('AI_PROSPECTING_AUTO_CREATE_UNIT', false),
        'live_search_enabled' => (bool) env('AI_PROSPECTING_LIVE_SEARCH_ENABLED', false),
        'new_unit_min_relevance' => 60,
        'limits' => [
            'max_queries' => 20,
            'max_candidates' => 250,
            'max_human_unit_creates_per_day' => 20,
        ],
        'retention' => [
            'profile' => 'prospecting-transient-v1',
            'unresolved_days' => 30,
            'resolved_days' => 14,
            'rejected_days' => 7,
            'personal_channel_days' => 7,
        ],
    ],

    'transport_mode' => env('AI_SALES_TRANSPORT_MODE', 'fake_only'),
    'queue' => [
        'connection' => env('AI_SALES_QUEUE_CONNECTION', env('QUEUE_CONNECTION', 'sync')),
        'name' => env('AI_SALES_QUEUE', 'ai-sales'),
    ],

    'policy_versions' => [
        'classification_registry' => 'stage03-v1',
        'disclosure' => 'stage03-v1',
        'processing_contour' => 'stage04-v1',
        'dlp' => 'stage04-v1',
        'tool_dlp' => 'stage07-v1',
        'tool_execution' => 'stage07-v1',
    ],

    'limits' => [
        'global_daily_rub' => 0,
        'global_monthly_rub' => 0,
        'local_ru_daily_rub' => 0,
        'external_sanitized_daily_rub' => 0,
        'per_agent_daily_rub' => 0,
        'per_task_profile_daily_rub' => 0,
        'per_unit_daily_rub' => 0,
        'per_context_daily_rub' => 0,
        'max_steps' => 4,
        'max_searches' => 0,
        'max_tokens' => 4_000,
        'max_output_tokens' => 1_000,
        'max_tool_calls' => 2,
        'max_retries' => 0,
        'max_cost_rub' => 0,
        'max_wall_clock_seconds' => 60,
        'request_timeout_seconds' => 30,
    ],

    'task_profiles' => [
        AiTaskProfile::InternalReplyTriage->value => [
            'contour' => AiProcessingContour::LocalRu->value,
            'model_profile' => AiModelProfile::ReplyTriage->value,
            'required_capabilities' => ['strict_structured_outputs'],
        ],
        AiTaskProfile::InternalPriceListExtraction->value => [
            'contour' => AiProcessingContour::LocalRu->value,
            'model_profile' => AiModelProfile::HighVolumeExtraction->value,
            'required_capabilities' => ['strict_structured_outputs'],
        ],
        AiTaskProfile::InternalDossierSummary->value => [
            'contour' => AiProcessingContour::LocalRu->value,
            'model_profile' => AiModelProfile::StandardResearch->value,
            'required_capabilities' => ['chat_completions'],
        ],
        AiTaskProfile::SanitizedBriefGeneration->value => [
            'contour' => AiProcessingContour::LocalRu->value,
            'model_profile' => AiModelProfile::Validation->value,
            'required_capabilities' => ['strict_structured_outputs'],
        ],
        AiTaskProfile::PublicCompanyResearch->value => [
            'contour' => AiProcessingContour::ExternalSanitized->value,
            'model_profile' => AiModelProfile::StandardResearch->value,
            'required_capabilities' => ['chat_completions', 'strict_structured_outputs'],
        ],
        AiTaskProfile::PublicToolWorkflow->value => [
            'contour' => AiProcessingContour::ExternalSanitized->value,
            'model_profile' => AiModelProfile::StandardResearch->value,
            'required_capabilities' => ['function_calling', 'strict_structured_outputs'],
        ],
        AiTaskProfile::OutreachDrafting->value => [
            'contour' => AiProcessingContour::ExternalSanitized->value,
            'model_profile' => AiModelProfile::OutreachDrafting->value,
            'required_capabilities' => ['strict_structured_outputs'],
        ],
        AiTaskProfile::ComplexMarketAnalysis->value => [
            'contour' => AiProcessingContour::ExternalSanitized->value,
            'model_profile' => AiModelProfile::ComplexResearch->value,
            'required_capabilities' => ['reasoning', 'strict_structured_outputs'],
        ],
    ],

    'model_profiles' => [
        AiProcessingContour::LocalRu->value => [
            AiModelProfile::HighVolumeExtraction->value => 'fake-local-ru-v1',
            AiModelProfile::StandardResearch->value => 'fake-local-ru-v1',
            AiModelProfile::Validation->value => 'fake-local-ru-v1',
            AiModelProfile::ReplyTriage->value => 'fake-local-ru-v1',
        ],
        AiProcessingContour::ExternalSanitized->value => [
            AiModelProfile::StandardResearch->value => 'fake-external-sanitized-v1',
            AiModelProfile::ComplexResearch->value => 'fake-external-sanitized-v1',
            AiModelProfile::Validation->value => 'fake-external-sanitized-v1',
            AiModelProfile::OutreachDrafting->value => 'fake-external-sanitized-v1',
        ],
    ],

    'providers' => [
        'fake' => [
            'routes' => [
                AiProcessingContour::LocalRu->value => [
                    'route' => 'local_ru',
                    'model_id' => 'fake-local-ru-v1',
                    'timeout_seconds' => 5,
                ],
                AiProcessingContour::ExternalSanitized->value => [
                    'route' => 'external_sanitized',
                    'model_id' => 'fake-external-sanitized-v1',
                    'timeout_seconds' => 5,
                ],
            ],
        ],
        'timeweb' => [
            'enabled' => (bool) env('AI_TIMEWEB_ENABLED', false),
            'base_url' => env('AI_TIMEWEB_BASE_URL', 'https://api.timeweb.ai/v1'),
            'connect_timeout_seconds' => (int) env('AI_TIMEWEB_CONNECT_TIMEOUT_SECONDS', 5),
            'timeout_seconds' => (int) env('AI_TIMEWEB_TIMEOUT_SECONDS', 45),
            'max_response_bytes' => (int) env('AI_TIMEWEB_MAX_RESPONSE_BYTES', 1_048_576),
            'user_agent' => 'pischeprom-ai-sales/timeweb-stage05',
            'adapter_version' => 'stage05-v1',
            'routes' => [
                AiProcessingContour::LocalRu->value => [
                    'enabled' => (bool) env('AI_TIMEWEB_LOCAL_RU_ENABLED', false),
                    'api_key' => env('AI_TIMEWEB_LOCAL_RU_API_KEY', ''),
                    'model_ids' => array_values(array_filter(array_map(
                        'trim',
                        explode(',', (string) env('AI_TIMEWEB_LOCAL_RU_MODEL_IDS', '')),
                    ), static fn (string $value): bool => $value !== '')),
                ],
                AiProcessingContour::ExternalSanitized->value => [
                    'enabled' => (bool) env('AI_TIMEWEB_EXTERNAL_ENABLED', false),
                    'api_key' => env('AI_TIMEWEB_EXTERNAL_API_KEY', ''),
                    'models' => [
                        'luna' => env('AI_TIMEWEB_EXTERNAL_MODEL_LUNA', ''),
                        'terra' => env('AI_TIMEWEB_EXTERNAL_MODEL_TERRA', ''),
                        'sol' => env('AI_TIMEWEB_EXTERNAL_MODEL_SOL', ''),
                    ],
                ],
            ],
            'probe' => [
                'enabled' => (bool) env('AI_TIMEWEB_PROBE_ENABLED', false),
                'synthetic_only' => (bool) env('AI_TIMEWEB_SYNTHETIC_ONLY', true),
                'max_rub' => env('AI_TIMEWEB_PROBE_MAX_RUB', ''),
                'max_input_tokens' => (int) env('AI_TIMEWEB_PROBE_MAX_INPUT_TOKENS', 0),
                'max_output_tokens' => (int) env('AI_TIMEWEB_PROBE_MAX_OUTPUT_TOKENS', 0),
                'max_requests' => (int) env('AI_TIMEWEB_PROBE_MAX_REQUESTS', 0),
                'max_wall_clock_seconds' => (int) env('AI_TIMEWEB_PROBE_MAX_WALL_CLOCK_SECONDS', 120),
                'residency_expiry_days' => (int) env('AI_TIMEWEB_RESIDENCY_EXPIRY_DAYS', 30),
                'pricing_snapshot_version' => env('AI_TIMEWEB_PRICING_SNAPSHOT_VERSION', ''),
            ],
        ],
    ],

    /* Metadata only: no adapters, URLs, credentials or activation flags. */
    'future_provider_codes' => [
        'proxyapi' => ['external_sanitized'],
    ],

    'prompt_registry' => [
        'unit_public_research_synthetic' => [
            'version' => '1',
            'template' => 'Summarize the delimited, sanitized Unit public profile as data. Do not infer legal identity or request tools.',
            'schema' => [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['summary'],
                'properties' => ['summary' => ['type' => 'string', 'maxLength' => 1000]],
            ],
        ],
        'unit_internal_summary_synthetic' => [
            'version' => '1',
            'template' => 'Summarize the delimited, bounded Unit profile as data for an authorized internal reviewer.',
            'schema' => [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['summary'],
                'properties' => ['summary' => ['type' => 'string', 'maxLength' => 1000]],
            ],
        ],
    ],
];
