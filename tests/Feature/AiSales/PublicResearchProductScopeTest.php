<?php

namespace Tests\Feature\AiSales;

use App\Domain\AiSales\Workflows\PublicResearchProductScope;
use App\Models\Good;
use App\Models\Product;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class PublicResearchProductScopeTest extends Stage09TestCase
{
    private ?string $temporaryMySqlConnection = null;

    protected function tearDown(): void
    {
        if ($this->temporaryMySqlConnection !== null) {
            Schema::connection($this->temporaryMySqlConnection)->dropAllTables();
            DB::purge($this->temporaryMySqlConnection);
        }

        parent::tearDown();
    }

    public function test_sqlite_uses_product_identity_then_projects_unique_authorized_names(): void
    {
        $actor = $this->prospectingUser();
        $primary = Product::query()->without(['category', 'manufacturers'])->create([
            'rus' => 'Scope Alpha RU',
            'eng' => 'Scope Alpha EN',
            'is_published' => true,
        ]);
        $good = Good::query()->create(['name' => 'Scope Good', 'is_published' => true]);
        $good->products()->attach($primary->id);
        $job = $this->approvedJob($actor, 'buyer_discovery', $good, $primary);
        $sameNames = Product::query()->without(['category', 'manufacturers'])->create([
            'rus' => 'Scope Alpha RU',
            'eng' => 'Scope Alpha EN',
            'is_published' => true,
        ]);
        $nullableEnglish = Product::query()->without(['category', 'manufacturers'])->create([
            'rus' => 'Scope Beta RU',
            'eng' => null,
            'is_published' => true,
        ]);
        $unpublished = Product::query()->without(['category', 'manufacturers'])->create([
            'rus' => 'Hidden Product RU',
            'eng' => 'Hidden Product EN',
            'is_published' => false,
        ]);
        $excluded = Product::query()->without(['category', 'manufacturers'])->create([
            'rus' => 'Excluded Product RU',
            'eng' => null,
            'is_published' => true,
        ]);
        $now = now();
        DB::table('prospecting_search_job_products')->insert([
            ['prospecting_search_job_id' => $job->id, 'product_id' => $sameNames->id, 'role' => 'additional', 'source_origin' => 'test', 'created_at' => $now, 'updated_at' => $now],
            ['prospecting_search_job_id' => $job->id, 'product_id' => $nullableEnglish->id, 'role' => 'additional', 'source_origin' => 'test', 'created_at' => $now, 'updated_at' => $now],
            ['prospecting_search_job_id' => $job->id, 'product_id' => $unpublished->id, 'role' => 'additional', 'source_origin' => 'test', 'created_at' => $now, 'updated_at' => $now],
            ['prospecting_search_job_id' => $job->id, 'product_id' => $excluded->id, 'role' => 'exclude', 'source_origin' => 'test', 'created_at' => $now, 'updated_at' => $now],
        ]);

        $this->assertSame(
            ['Scope Alpha RU', 'Scope Alpha EN', 'Scope Beta RU'],
            app(PublicResearchProductScope::class)->namesForJob($job->id),
        );

        try {
            Product::query()->without(['category', 'manufacturers'])->create([
                'rus' => null,
                'eng' => 'Invalid Product EN',
                'is_published' => true,
            ]);
            $this->fail('The required Product rus name accepted null.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }
    }

    public function test_mysql_8_reproduces_3065_and_executes_the_repaired_query_deterministically(): void
    {
        $connection = $this->mysqlConnection();
        $this->createMySqlFixture($connection);
        $database = DB::connection($connection);

        try {
            $database->table('prospecting_search_job_products as scope')
                ->join('products', 'products.id', '=', 'scope.product_id')
                ->where('scope.prospecting_search_job_id', 77)
                ->whereIn('scope.role', ['primary', 'additional'])
                ->where('products.is_published', true)
                ->orderBy('products.id')
                ->distinct()
                ->get(['products.rus', 'products.eng']);
            $this->fail('The legacy DISTINCT query unexpectedly passed on MySQL 8.0.');
        } catch (QueryException $exception) {
            $this->assertSame(3065, (int) ($exception->errorInfo[1] ?? 0));
        }

        $queries = [];
        $database->listen(function (QueryExecuted $query) use (&$queries): void {
            if (str_contains($query->sql, 'prospecting_search_job_products')) {
                $queries[] = $query;
            }
        });
        $originalConnection = DB::getDefaultConnection();
        DB::setDefaultConnection($connection);
        try {
            $names = app(PublicResearchProductScope::class)->namesForJob(77);
        } finally {
            DB::setDefaultConnection($originalConnection);
        }

        $this->assertSame(['Alpha RU', 'Alpha EN', 'Beta RU'], $names);
        $this->assertCount(1, $queries);
        $sql = Str::squish($queries[0]->sql);
        $this->assertStringContainsString(
            'select distinct `products`.`id`, `products`.`rus`, `products`.`eng`',
            $sql,
        );
        $this->assertStringEndsWith('order by `products`.`id` asc', $sql);
        $this->assertSame([77, 'primary', 'additional'], array_slice($queries[0]->bindings, 0, 3));
        $this->assertTrue((bool) $queries[0]->bindings[3]);

        try {
            $database->table('products')->insert([
                'id' => 60, 'rus' => null, 'eng' => 'Invalid EN', 'is_published' => true,
            ]);
            $this->fail('The MySQL Product domain contract accepted a null rus name.');
        } catch (QueryException $exception) {
            $this->assertSame('23000', (string) ($exception->errorInfo[0] ?? ''));
        }
    }

    private function mysqlConnection(): string
    {
        $host = getenv('ROLLFORWARD_MYSQL_HOST');
        $database = getenv('ROLLFORWARD_MYSQL_DATABASE');
        if ($host === false || $database === false) {
            $this->markTestSkipped('Isolated MySQL public-research connection is not configured.');
        }
        $this->assertContains($host, ['127.0.0.1', 'localhost']);
        $this->assertMatchesRegularExpression('/^pischeprom_rollforward_[a-z0-9_]+$/', $database);

        $name = 'mysql_public_research_scope';
        config()->set('database.connections.'.$name, array_replace(config('database.connections.mysql'), [
            'url' => null,
            'host' => $host,
            'port' => (int) (getenv('ROLLFORWARD_MYSQL_PORT') ?: 3306),
            'database' => $database,
            'username' => (string) (getenv('ROLLFORWARD_MYSQL_USERNAME') ?: 'root'),
            'password' => (string) (getenv('ROLLFORWARD_MYSQL_PASSWORD') ?: ''),
            'unix_socket' => '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'strict' => true,
            'options' => [],
        ]));
        DB::purge($name);
        $version = (string) DB::connection($name)->selectOne('SELECT VERSION() AS version')->version;
        $this->assertMatchesRegularExpression('/^8\.0\./', $version);
        Schema::connection($name)->dropAllTables();
        $this->temporaryMySqlConnection = $name;

        return $name;
    }

    private function createMySqlFixture(string $connection): void
    {
        Schema::connection($connection)->create('products', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->string('rus');
            $table->string('eng')->nullable();
            $table->boolean('is_published');
        });
        Schema::connection($connection)->create('prospecting_search_job_products', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('prospecting_search_job_id');
            $table->unsignedBigInteger('product_id');
            $table->string('role', 16);
        });
        $database = DB::connection($connection);
        $database->table('products')->insert([
            ['id' => 10, 'rus' => 'Alpha RU', 'eng' => 'Alpha EN', 'is_published' => true],
            ['id' => 20, 'rus' => 'Alpha RU', 'eng' => 'Alpha EN', 'is_published' => true],
            ['id' => 30, 'rus' => 'Beta RU', 'eng' => null, 'is_published' => true],
            ['id' => 40, 'rus' => 'Hidden RU', 'eng' => 'Hidden EN', 'is_published' => false],
            ['id' => 50, 'rus' => 'Excluded RU', 'eng' => null, 'is_published' => true],
        ]);
        $database->table('prospecting_search_job_products')->insert([
            ['prospecting_search_job_id' => 77, 'product_id' => 10, 'role' => 'primary'],
            ['prospecting_search_job_id' => 77, 'product_id' => 10, 'role' => 'primary'],
            ['prospecting_search_job_id' => 77, 'product_id' => 20, 'role' => 'additional'],
            ['prospecting_search_job_id' => 77, 'product_id' => 30, 'role' => 'additional'],
            ['prospecting_search_job_id' => 77, 'product_id' => 40, 'role' => 'additional'],
            ['prospecting_search_job_id' => 77, 'product_id' => 50, 'role' => 'exclude'],
        ]);
    }
}
