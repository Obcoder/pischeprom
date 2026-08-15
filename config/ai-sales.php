<?php

use App\Domain\AiSales\Enums\AiModelProfile;
use App\Domain\AiSales\Enums\AiProcessingContour;
use App\Domain\AiSales\Enums\AiTaskProfile;

return [
    /*
    | Stage 04 is deliberately fake-only. Network-capable provider adapters are
    | not registered and the generic external egress switch must remain off.
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

    'transport_mode' => 'fake_only',
    'queue' => [
        'connection' => env('AI_SALES_QUEUE_CONNECTION', env('QUEUE_CONNECTION', 'sync')),
        'name' => env('AI_SALES_QUEUE', 'ai-sales'),
    ],

    'policy_versions' => [
        'classification_registry' => 'stage03-v1',
        'disclosure' => 'stage03-v1',
        'processing_contour' => 'stage04-v1',
        'dlp' => 'stage04-v1',
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
    ],

    /* Metadata only: no adapters, URLs, credentials or activation flags. */
    'future_provider_codes' => [
        'timeweb_ai_gateway' => ['local_ru', 'external_sanitized'],
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
