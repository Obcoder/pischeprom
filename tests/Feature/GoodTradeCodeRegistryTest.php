<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Goods\GoodTradeCodeRegistryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GoodTradeCodeRegistryTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        config()->set(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
    }

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    public function test_guests_cannot_check_codes(): void
    {
        $this->postJson('/api/goods/trade-codes/verify', ['codes' => ['hs_code' => '160420']])->assertUnauthorized();
        Http::assertNothingSent();
    }

    #[DataProvider('unauthorizedUsers')]
    public function test_only_active_verified_staff_can_check_codes(array $attributes): void
    {
        $this->actingAs(User::factory()->create(['type' => 'employee', 'status' => 'active', ...$attributes]));
        $this->postJson('/api/goods/trade-codes/verify', ['codes' => ['hs_code' => '160420']])->assertForbidden();
        Http::assertNothingSent();
    }

    public static function unauthorizedUsers(): array
    {
        return ['customer' => [['type' => 'customer']], 'blocked' => [['status' => 'blocked']],
            'unverified' => [['email_verified_at' => null]]];
    }

    public function test_checking_normalizes_codes_and_does_not_need_ai_or_modify_the_catalog(): void
    {
        config()->set('goods-trade-codes-ai.enabled', false);
        $this->mock(GoodTradeCodeRegistryService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('lookup')->once()->with('hs_code', '010121')->andReturn($this->found('hs_code', '010121'));
            $mock->shouldReceive('lookup')->once()->with('tn_ved_code', '0101210000')->andReturn($this->found('tn_ved_code', '0101210000'));
        });
        $this->actingAs($this->employee())->postJson('/api/goods/trade-codes/verify', [
            'codes' => ['hs_code' => '0101.21', 'tn_ved_code' => '0101 21 000 0'],
            'name' => 'Unrelated text must not be sent to the registry',
            'source_url' => 'http://127.0.0.1/private',
        ])->assertOk()->assertJsonCount(2, 'results')
            ->assertJsonPath('results.0.code', '010121')->assertJsonPath('results.1.code', '0101210000')
            ->assertJsonPath('results.0.status', 'found')->assertJsonStructure(['scope']);
        $this->assertDatabaseCount('goods', 0);
        Http::assertNothingSent();
    }

    public function test_unsupported_sources_and_unavailable_sources_are_reported_per_code(): void
    {
        $this->mock(GoodTradeCodeRegistryService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('lookup')->once()->with('hs_code', '160420')
                ->andReturn([...$this->found('hs_code', '160420'), 'status' => 'unavailable', 'title' => null]);
            $mock->shouldReceive('lookup')->once()->with('okpd2_code', '10.20.25.110')
                ->andReturn([...$this->found('okpd2_code', '10.20.25.110'), 'status' => 'not_supported', 'title' => null]);
        });
        $this->actingAs($this->employee())->postJson('/api/goods/trade-codes/verify', [
            'codes' => ['hs_code' => '160420', 'okpd2_code' => '102025110'],
        ])->assertOk()->assertJsonPath('results.0.status', 'unavailable')->assertJsonPath('results.1.status', 'not_supported');
    }

    #[DataProvider('invalidCodes')]
    public function test_invalid_input_is_rejected_before_any_registry_access(mixed $codes): void
    {
        $this->mock(GoodTradeCodeRegistryService::class, fn (MockInterface $mock) => $mock->shouldNotReceive('lookup'));
        $this->actingAs($this->employee())->postJson('/api/goods/trade-codes/verify', ['codes' => $codes])->assertUnprocessable();
        Http::assertNothingSent();
    }

    public static function invalidCodes(): array
    {
        return [
            'empty' => [[]], 'null' => [null], 'scalar' => ['160420'],
            'unknown source' => [['source_url' => 'https://attacker.example/']],
            'numeric loses leading zeroes' => [['hs_code' => 10121]],
            'wrong length' => [['hs_code' => '16042']],
            'nested input' => [['hs_code' => ['160420']]],
            'empty code' => [['hs_code' => ' ']],
            'oversized' => [['hs_code' => str_repeat('1', 1000)]],
        ];
    }

    private function employee(): User
    {
        return User::factory()->create(['type' => 'employee', 'status' => 'active']);
    }

    private function found(string $field, string $code): array
    {
        return [
            'field' => $field, 'code' => $code, 'status' => 'found', 'title' => 'Official classification entry',
            'source_url' => 'https://comtradeapi.un.org/files/v1/app/reference/H6.json', 'source_name' => 'UN Comtrade',
            'version' => 'HS2022', 'checked_at' => now()->toIso8601String(), 'message' => '',
        ];
    }
}
