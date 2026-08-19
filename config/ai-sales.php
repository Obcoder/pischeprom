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
        'live_probe_enabled' => (bool) env('AI_PROSPECTING_LIVE_PROBE_ENABLED', false),
        'query_planning_enabled' => (bool) env('AI_PROSPECTING_QUERY_PLANNING_ENABLED', false),
        'search_execution_enabled' => (bool) env('AI_PROSPECTING_SEARCH_EXECUTION_ENABLED', false),
        'existing_yandex_provider_enabled' => (bool) env('AI_PROSPECTING_EXISTING_YANDEX_PROVIDER_ENABLED', false),
        'page_fetch_enabled' => (bool) env('AI_PROSPECTING_PAGE_FETCH_ENABLED', false),
        'auto_candidate_ingestion_enabled' => (bool) env('AI_PROSPECTING_AUTO_CANDIDATE_INGESTION', false),
        'public_research_enabled' => (bool) env('AI_PROSPECTING_PUBLIC_RESEARCH_ENABLED', false),
        'scoring_enabled' => (bool) env('AI_PROSPECTING_SCORING_ENABLED', false),
        'auto_scoring_enabled' => (bool) env('AI_PROSPECTING_AUTO_SCORING_ENABLED', false),
        'score_overrides_enabled' => (bool) env('AI_PROSPECTING_SCORE_OVERRIDES_ENABLED', false),
        'ai_evidence_enabled' => (bool) env('AI_PROSPECTING_AI_EVIDENCE_ENABLED', false),
        'live_scoring_enabled' => (bool) env('AI_PROSPECTING_LIVE_SCORING_ENABLED', false),
        'new_unit_min_relevance' => 60,
        'limits' => [
            'max_queries' => 20,
            'max_candidates' => 250,
            'max_human_unit_creates_per_day' => 20,
            'max_search_requests_per_job' => 20,
            'max_search_results_per_query' => 50,
            'max_search_result_bytes' => 1_048_576,
            'max_public_fetches_per_domain' => 5,
            'max_public_fetch_bytes' => 524_288,
            'max_public_fetch_redirects' => 2,
            'max_public_text_bytes' => 24_576,
            'public_fetch_timeout_seconds' => 10,
            'public_fetch_connect_timeout_seconds' => 3,
        ],
        'search_profiles' => [
            'product_page_search',
            'prospecting_b2b_discovery',
        ],
        'retention' => [
            'profile' => 'prospecting-transient-v1',
            'unresolved_days' => 30,
            'resolved_days' => 14,
            'rejected_days' => 7,
            'personal_channel_days' => 7,
        ],
    ],

    'find_buyers' => [
        'ui_enabled' => (bool) env('AI_FIND_BUYERS_UI_ENABLED', false),
        'drafts_enabled' => (bool) env('AI_FIND_BUYERS_DRAFTS_ENABLED', false),
        'live_execution_enabled' => (bool) env('AI_FIND_BUYERS_LIVE_EXECUTION_ENABLED', false),
        'auto_research_enabled' => (bool) env('AI_FIND_BUYERS_AUTO_RESEARCH_ENABLED', false),
        'auto_scoring_enabled' => (bool) env('AI_FIND_BUYERS_AUTO_SCORING_ENABLED', false),
        'wizard_version' => 'stage11-v1',
        'limits' => [
            'additional_products' => 10,
            'excluded_products' => 10,
            'industries' => 10,
            'categories' => 10,
            'max_queries' => 10,
            'max_results_per_query' => 20,
            'max_domains' => 10,
            'max_page_fetch_attempts' => 5,
            'max_candidates' => 50,
        ],
    ],

    'campaigns' => [
        'enabled' => (bool) env('AI_SALES_CAMPAIGNS_ENABLED', false),
        'scheduler_enabled' => (bool) env('AI_SALES_CAMPAIGN_SCHEDULER_ENABLED', false),
        'live_search_enabled' => (bool) env('AI_SALES_CAMPAIGN_LIVE_SEARCH_ENABLED', false),
        'live_research_enabled' => (bool) env('AI_SALES_CAMPAIGN_LIVE_RESEARCH_ENABLED', false),
        'auto_ingest_enabled' => (bool) env('AI_SALES_CAMPAIGN_AUTO_INGEST_ENABLED', false),
        'auto_create_unit_enabled' => (bool) env('AI_SALES_CAMPAIGN_AUTO_CREATE_UNIT_ENABLED', false),
        'auto_scoring_enabled' => (bool) env('AI_SALES_CAMPAIGN_AUTO_SCORING_ENABLED', false),
        'auto_draft_enabled' => (bool) env('AI_SALES_CAMPAIGN_AUTO_DRAFT_ENABLED', false),
        'notifications_enabled' => (bool) env('AI_SALES_CAMPAIGN_NOTIFICATIONS_ENABLED', false),
        /* Process-local command/test guard; deliberately not environment-controlled. */
        'synthetic_fixture_mode' => false,
        'workflow_code' => 'buyer_acquisition_campaign.v1',
        'workflow_version' => '1',
        'wizard_version' => 'stage14-campaign-v1',
        'policies' => [
            'auto_unit' => [
                'code' => 'autonomous_unit_creation.v1',
                'version' => '1',
                'minimum_independent_sources' => 2,
            ],
            'auto_draft' => [
                'code' => 'autonomous_outreach_draft.v1',
                'version' => '1',
                'minimum_product_relevance' => 60,
                'minimum_confidence' => 70,
                'minimum_prospect_priority' => 50,
            ],
        ],
        'limits' => [
            'scheduler_batch' => (int) env('AI_SALES_CAMPAIGN_SCHEDULER_BATCH', 0),
            'max_active_runs' => (int) env('AI_SALES_CAMPAIGN_MAX_ACTIVE_RUNS', 0),
            'max_runs_per_day' => (int) env('AI_SALES_CAMPAIGN_MAX_RUNS_PER_DAY', 0),
            'max_runs_per_month' => (int) env('AI_SALES_CAMPAIGN_MAX_RUNS_PER_MONTH', 0),
            'max_search_requests_per_run' => (int) env('AI_SALES_CAMPAIGN_MAX_SEARCH_REQUESTS_PER_RUN', 0),
            'max_search_results_per_run' => (int) env('AI_SALES_CAMPAIGN_MAX_SEARCH_RESULTS_PER_RUN', 0),
            'max_research_pages_per_run' => (int) env('AI_SALES_CAMPAIGN_MAX_RESEARCH_PAGES_PER_RUN', 0),
            'max_domains_per_run' => (int) env('AI_SALES_CAMPAIGN_MAX_DOMAINS_PER_RUN', 0),
            'max_candidates_per_run' => (int) env('AI_SALES_CAMPAIGN_MAX_CANDIDATES_PER_RUN', 0),
            'global_units_per_day' => (int) env('AI_SALES_CAMPAIGN_GLOBAL_UNITS_PER_DAY', 0),
            'global_units_per_month' => (int) env('AI_SALES_CAMPAIGN_GLOBAL_UNITS_PER_MONTH', 0),
            'global_drafts_per_day' => (int) env('AI_SALES_CAMPAIGN_GLOBAL_DRAFTS_PER_DAY', 0),
            'global_drafts_per_month' => (int) env('AI_SALES_CAMPAIGN_GLOBAL_DRAFTS_PER_MONTH', 0),
        ],
    ],

    'outreach' => [
        'ui_enabled' => (bool) env('AI_OUTREACH_UI_ENABLED', false),
        'drafts_enabled' => (bool) env('AI_OUTREACH_DRAFTS_ENABLED', false),
        'fake_generation_enabled' => (bool) env('AI_OUTREACH_FAKE_GENERATION_ENABLED', false),
        'permission_ledger_enabled' => (bool) env('AI_COMMUNICATION_PERMISSION_LEDGER_ENABLED', false),
        'suppression_management_enabled' => (bool) env('AI_COMMUNICATION_SUPPRESSION_MANAGEMENT_ENABLED', false),
        'dispatch_enabled' => (bool) env('AI_OUTREACH_DISPATCH_ENABLED', false),
        'dispatch_pipeline_enabled' => (bool) env('AI_OUTREACH_DISPATCH_PIPELINE_ENABLED', false),
        'queue_enabled' => (bool) env('AI_OUTREACH_QUEUE_ENABLED', false),
        'provider_send_enabled' => (bool) env('AI_OUTREACH_PROVIDER_SEND_ENABLED', false),
        'event_ingestion_enabled' => (bool) env('AI_OUTREACH_EVENT_INGESTION_ENABLED', false),
        'reply_correlation_enabled' => (bool) env('AI_OUTREACH_REPLY_CORRELATION_ENABLED', false),
        'reply_triage_enabled' => (bool) env('AI_OUTREACH_REPLY_TRIAGE_ENABLED', false),
        'followup_planning_enabled' => (bool) env('AI_OUTREACH_FOLLOWUP_PLANNING_ENABLED', false),
        'auto_followup_enabled' => (bool) env('AI_OUTREACH_AUTO_FOLLOWUP_ENABLED', false),
        'live_generation_enabled' => (bool) env('AI_OUTREACH_LIVE_GENERATION_ENABLED', false),
        /* Process-local Stage 12B CLI guard; deliberately not environment-controlled. */
        'live_synthetic_canary_enabled' => false,
        'live_synthetic_canary_model_id' => 'openai/gpt-5.6-luna',
        /* Stage 13B secrets/evidence are read only by the CLI-only owner canary. */
        'owner_canary' => [
            'environment' => env('AI_OUTREACH_CANARY_ENVIRONMENT'),
            'recipient' => env('AI_OUTREACH_CANARY_RECIPIENT'),
            'permission_evidence_reference' => env('AI_OUTREACH_CANARY_PERMISSION_EVIDENCE_REFERENCE'),
            'permission_evidence_sha256' => env('AI_OUTREACH_CANARY_PERMISSION_EVIDENCE_SHA256'),
            'security_evidence_reference' => env('AI_OUTREACH_CANARY_SECURITY_EVIDENCE_REFERENCE'),
            'security_evidence_sha256' => env('AI_OUTREACH_CANARY_SECURITY_EVIDENCE_SHA256'),
            'security_verified_at' => env('AI_OUTREACH_CANARY_SECURITY_VERIFIED_AT'),
        ],
        'auto_send_enabled' => (bool) env('AI_OUTREACH_AUTO_SEND_ENABLED', false),
        'transport_mode' => env('AI_OUTREACH_TRANSPORT_MODE', 'fake_only'),
        'policy_version' => 'stage12-v1',
        'dispatch_policy_version' => 'stage13-v1',
        'reply_triage_profile' => 'outreach_reply_triage.v1',
        'renderer_version' => 'stage12-renderer-v1',
        'template_profile' => 'product-first-corporate-v1',
        'template_version' => '1',
        'sender_scope' => 'pischeprom-manual-review',
        'limits' => [
            'subject_chars' => 160,
            'paragraphs' => 6,
            'plain_bytes' => 12_000,
            'html_bytes' => 24_000,
            'claims' => 10,
            'revisions' => 25,
            'global_daily_sends' => 0,
            'per_domain_daily_sends' => 0,
            'max_follow_ups' => 0,
            'provider_retries' => 0,
            'provider_failover' => false,
            'recipient_cooldown_hours' => 24,
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
        'search_query_planning' => 'stage09-v1',
        'public_fetch' => 'stage09-v1',
        'public_research' => 'stage09-v1',
        'prospecting_scoring' => 'stage10-v1',
        'find_buyers_disclosure' => 'stage11-v1',
        'outreach_permission' => 'stage12-v1',
        'outreach_suppression' => 'stage12-v1',
        'outreach_dlp' => 'stage12-v1',
        'outreach_rendering' => 'stage12-v1',
        'outreach_dispatch_eligibility' => 'stage12-v1',
        'outreach_dispatch' => 'stage13-v1',
        'outreach_reply_triage' => 'stage13-v1',
        'outreach_followup' => 'stage13-v1',
        'campaign_orchestration' => 'stage14-v1',
        'autonomous_unit_creation' => 'autonomous_unit_creation.v1',
        'autonomous_outreach_draft' => 'autonomous_outreach_draft.v1',
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
