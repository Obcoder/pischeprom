# AI tool and workflow capability gating

## Server-owned versus provider-native tools

Stage 07 executes fixed Laravel handlers before the provider and sends the resulting Safe DTO with `toolSchemas=[]`. `AI_PROVIDER_NATIVE_TOOLS_ENABLED=false` is fail-closed. A provider tool call is a protocol violation, not a request for execution.

## Evidence ceiling

| Provider/model | Permitted evidenced profile | Blocked profile |
|---|---|---|
| Timeweb local `timeweb/gpt-oss-120b` | basic Chat Completions/text and usage normalization only | Responses, strict output or native tools while capability remains unknown |
| Timeweb external `openai/gpt-5.6-luna` | Responses API, strict structured output, `store=false`, usage normalization | native provider tool calling; request ID remains unknown |
| Stage 07 runtime | deterministic fake provider capabilities | any Timeweb/live business provider |

`AiWorkflowCapabilityGuard` rejects local workflows whose requirements exceed basic chat/usage and rejects any native-tool requirement. The only Stage 07 workflow uses the external fake with verified synthetic `chat_completions`, `strict_structured_outputs` and `store_false` rows.

The executor requires `transport_mode=fake_only`, external HTTP off and failover off. It never switches `local_ru -> external_sanitized` or the reverse. Timeweb capability/residency/pricing evidence from Stage 05B is not modified, upgraded or treated as production approval. Retention, upstream processors, contractual residency and ZDR remain unresolved; production remains blocked.
