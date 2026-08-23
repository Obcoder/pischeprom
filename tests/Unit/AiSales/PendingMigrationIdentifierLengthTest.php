<?php

namespace Tests\Unit\AiSales;

use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;

final class PendingMigrationIdentifierLengthTest extends TestCase
{
    private const FILES = [
        'database/migrations/2026_08_16_211000_create_prospecting_candidates.php',
        'database/migrations/2026_08_16_212000_create_unit_good_matches_and_extend_contact_links.php',
        'database/migrations/2026_08_16_213000_create_product_first_prospecting_tables.php',
        'database/migrations/2026_08_16_214000_link_good_offer_fits_to_product_matches.php',
        'database/migrations/2026_08_17_090000_create_product_first_search_discovery_tables.php',
        'database/migrations/2026_08_17_100000_create_explainable_prospecting_score_tables.php',
        'database/migrations/2026_08_17_110000_add_find_buyers_launch_metadata_to_prospecting_search_jobs.php',
        'database/migrations/2026_08_17_120000_create_authorized_mail_dispatch_attempts.php',
        'database/migrations/2026_08_17_121000_create_communication_permission_ledger.php',
        'database/migrations/2026_08_17_122000_create_outreach_draft_review_tables.php',
        'database/migrations/2026_08_17_123000_harden_unisender_provider_persistence.php',
        'database/migrations/2026_08_17_124000_create_outreach_dispatch_lifecycle_tables.php',
        'database/migrations/2026_08_19_140000_create_ai_sales_campaign_orchestration_tables.php',
    ];

    private const COLUMN_METHODS = [
        'bigInteger', 'unsignedBigInteger', 'integer', 'unsignedInteger', 'tinyInteger',
        'unsignedTinyInteger', 'smallInteger', 'unsignedSmallInteger', 'mediumInteger',
        'unsignedMediumInteger', 'string', 'char', 'text', 'uuid', 'foreignId',
        'timestamp', 'dateTime', 'boolean', 'decimal', 'json',
    ];

    public function test_pending_migrations_do_not_generate_mysql_identifiers_over_64_characters(): void
    {
        $parser = (new ParserFactory)->createForNewestSupportedVersion();
        $finder = new NodeFinder;
        $generated = [];
        $explicit = [];

        foreach (self::FILES as $relativePath) {
            $path = dirname(__DIR__, 3).'/'.$relativePath;
            $ast = $parser->parse((string) file_get_contents($path));
            $this->collectGeneratedNames($finder, $ast, $relativePath, $generated);
            foreach ($finder->findInstanceOf($ast, Node\Scalar\String_::class) as $literal) {
                if (preg_match('/_(?:foreign|fk|index|idx|unique|primary)$/', $literal->value) === 1) {
                    $explicit[] = [$literal->value, $relativePath, $literal->getStartLine()];
                }
            }
        }

        $this->assertGreaterThan(100, count($generated), 'The generated-name scan became unexpectedly narrow.');
        $violations = [];
        foreach (array_merge($generated, $explicit) as [$name, $file, $line]) {
            if (strlen($name) > 64) {
                $violations[] = strlen($name).' '.$name.' '.$file.':'.$line;
            }
        }

        $this->assertSame([], $violations, implode(PHP_EOL, $violations));
    }

    private function collectGeneratedNames(NodeFinder $finder, array $ast, string $file, array &$generated): void
    {
        foreach ($finder->findInstanceOf($ast, Node\Expr\StaticCall::class) as $schemaCall) {
            if (! $schemaCall->class instanceof Node\Name || $schemaCall->class->toString() !== 'Schema') {
                continue;
            }
            $schemaMethod = $schemaCall->name instanceof Node\Identifier ? $schemaCall->name->toString() : '';
            if (! in_array($schemaMethod, ['create', 'table'], true)) {
                continue;
            }
            $table = $this->literalValue($schemaCall->args[0]->value ?? null);
            $closure = $schemaCall->args[1]->value ?? null;
            if (! is_string($table) || ! $closure instanceof Node\Expr\Closure) {
                continue;
            }

            foreach ($finder->findInstanceOf($closure->stmts, Node\Expr\MethodCall::class) as $call) {
                [$type, $columns, $explicit] = $this->indexCall($call);
                if ($type === null || $columns === null || $explicit !== null) {
                    continue;
                }
                $columns = is_array($columns) ? $columns : [$columns];
                $generated[] = [
                    strtolower($table.'_'.implode('_', $columns).'_'.$type),
                    $file,
                    $call->getStartLine(),
                ];
            }
        }
    }

    private function indexCall(Node\Expr\MethodCall $call): array
    {
        $method = $call->name instanceof Node\Identifier ? $call->name->toString() : '';
        if ($method === 'constrained') {
            $origin = $call->var instanceof Node\Expr\MethodCall
                ? $this->chainOrigin($call->var, ['foreignId'])
                : null;
            if ($origin === null) {
                return [null, null, null];
            }
            $explicit = null;
            foreach ($call->args as $position => $argument) {
                if ($argument->name?->toString() === 'indexName' || ($argument->name === null && $position === 2)) {
                    $explicit = $this->literalValue($argument->value);
                }
            }

            return ['foreign', $this->literalValue($origin->args[0]->value ?? null), $explicit];
        }

        if (! in_array($method, ['foreign', 'index', 'unique', 'primary', 'fullText', 'spatialIndex'], true)) {
            return [null, null, null];
        }
        $type = match ($method) {
            'foreign' => 'foreign',
            'unique' => 'unique',
            'primary' => 'primary',
            'fullText' => 'fulltext',
            'spatialIndex' => 'spatialindex',
            default => 'index',
        };
        if ($call->var instanceof Node\Expr\Variable && $call->var->name === 'table') {
            return [
                $type,
                $this->literalValue($call->args[0]->value ?? null),
                $this->literalValue($call->args[1]->value ?? null),
            ];
        }
        if ($call->var instanceof Node\Expr\MethodCall) {
            $origin = $this->chainOrigin($call->var, self::COLUMN_METHODS);
            if ($origin !== null) {
                return [
                    $type,
                    $this->literalValue($origin->args[0]->value ?? null),
                    $this->literalValue($call->args[0]->value ?? null),
                ];
            }
        }

        return [null, null, null];
    }

    private function chainOrigin(Node\Expr\MethodCall $call, array $methods): ?Node\Expr\MethodCall
    {
        $node = $call;
        while ($node instanceof Node\Expr\MethodCall) {
            $method = $node->name instanceof Node\Identifier ? $node->name->toString() : '';
            if (in_array($method, $methods, true)) {
                return $node;
            }
            $node = $node->var;
        }

        return null;
    }

    private function literalValue(?Node $node): mixed
    {
        if ($node instanceof Node\Scalar\String_) {
            return $node->value;
        }
        if ($node instanceof Node\Expr\Array_) {
            $values = [];
            foreach ($node->items as $item) {
                if (! $item?->value instanceof Node\Scalar\String_) {
                    return null;
                }
                $values[] = $item->value->value;
            }

            return $values;
        }

        return null;
    }
}
