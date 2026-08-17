# Public Company Research

Workflow identity: `public_company_research.v1`, version `1`.

## Fixed Stage 09 flow

```text
completed safe public fetch
  → PublicCompanyResearchInput
  → PublicResearchSafeDtoPolicy + code-owned classification + DLP
  → FakeExternalSanitizedAiProvider
  → strict response schema validation
  → bounded research record
```

Stage 09 does not send this workflow to Timeweb or any other external AI provider.

## Safe DTO allowlist

`PublicCompanyResearchInput` contains only:

- exact public host;
- redacted page title and meta description;
- redacted headings and bounded visible text;
- published Product names from approved Job scope;
- reviewed geography;
- fixed `trust_level=untrusted`;
- fixed `instruction_authority=none`.

All fields are registered as `public`, `shared_public`, external-exportable only for buyer/supplier discovery purposes and matching prospective audience. Missing/unclassified or differently classified fields block. DTO maximum is 32,768 bytes; relations are loaded explicitly and no Eloquent serialization is used.

Contact values, raw HTML, search/provider body, internal Unit/Entity data, transactions, correspondence, tokens and opposite-lane data are absent.

## Protocol

- contour: `external_sanitized`;
- provider must implement `FakeAiProviderInterface` and have code `fake`;
- app transport mode must be `fake_only`;
- required capabilities: chat completions and strict structured output;
- `store=false` through `AiProviderRequest`;
- `toolSchemas=[]`, provider tool calls must be empty;
- native tools disabled;
- strict output allows bounded summary/activity/location/Product mentions only;
- response/provider/route/model/schema/request ID are validated;
- only normalized output and hashes are persisted.

Page instructions have no authority. Known prompt-injection markers block before research. The workflow cannot read `.env`, change URL/query/provider/model/lane/budget, add a tool, run SQL/shell, send email, or create/link Unit/Entity.

No Stage 10 scoring or outreach logic is implemented.
