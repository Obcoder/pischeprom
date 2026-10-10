<?php

namespace Tests\Feature;

use App\Models\Good;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublicGoodTradeCodesTest extends TestCase
{
    use RefreshDatabase;

    private const PUBLIC_CODES = [
        'tn_ved_code' => '0101210000',
        'okpd2_code' => '10.20.25.110',
        'hs_code' => '010121',
    ];

    private const STAFF_CODES = [
        'incoming_code' => 'SUPPLIER-INTERNAL-CODE',
        'cn_code' => '87654321',
        'taric_code' => '8765432100',
        'htsus_code' => '9876543210',
        'schedule_b_code' => '6789012345',
        'gtin' => '00012345600012',
        'unspsc_code' => '50121538',
        'cas_number' => '7647-14-5',
        'eccn_code' => 'EAR99',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        config()->set('app.asset_url', 'https://assets.example.test');
        $this->withHeader('X-Inertia-Version', hash('xxh128', 'https://assets.example.test'));
    }

    #[DataProvider('storefrontVisitors')]
    public function test_landing_exposes_only_three_trade_codes_regardless_of_visitor_type(?string $type): void
    {
        if ($type !== null) {
            $this->actingAs(User::factory()->create(['type' => $type, 'status' => 'active']));
        }

        $good = $this->good();
        $related = $this->good('Другой товар');

        $response = $this->get(route('public.goods.show', $good->slug), ['X-Inertia' => 'true'])
            ->assertOk()
            ->assertJsonPath('props.good.id', $good->id)
            ->assertJsonPath('props.relatedGoods.0.id', $related->id);

        foreach (self::PUBLIC_CODES as $field => $value) {
            $response->assertJsonPath('props.good.'.$field, $value);
        }

        foreach (self::STAFF_CODES as $field => $value) {
            $response->assertJsonMissingPath('props.good.'.$field)
                ->assertJsonMissingPath('props.relatedGoods.0.'.$field);
            $this->assertStringNotContainsString($value, $response->getContent());
        }

        Http::assertNothingSent();
    }

    public static function storefrontVisitors(): array
    {
        return [
            'guest' => [null],
            'customer' => ['customer'],
            'employee viewing the storefront' => ['employee'],
        ];
    }

    public function test_public_goods_lists_do_not_expose_staff_codes(): void
    {
        $good = $this->good();

        $catalog = $this->get(route('public.goods.index'), ['X-Inertia' => 'true'])
            ->assertOk()
            ->assertJsonPath('props.goods.0.id', $good->id);
        $published = $this->getJson(route('goods.published'))
            ->assertOk()
            ->assertJsonPath('0.id', $good->id);

        foreach (self::STAFF_CODES as $field => $value) {
            $catalog->assertJsonMissingPath('props.goods.0.'.$field);
            $published->assertJsonMissingPath('0.'.$field);
            $this->assertStringNotContainsString($value, $catalog->getContent());
            $this->assertStringNotContainsString($value, $published->getContent());
        }

        foreach (self::PUBLIC_CODES as $field => $value) {
            $published->assertJsonPath('0.'.$field, $value);
        }

        Http::assertNothingSent();
    }

    public function test_all_codes_remain_available_in_the_staff_product_card_only(): void
    {
        $good = $this->good();
        $url = route('good.fetch', ['id' => $good->id]);

        $this->getJson($url)->assertUnauthorized();
        $this->actingAs(User::factory()->create(['type' => 'customer', 'status' => 'active']))
            ->getJson($url)->assertForbidden();
        $response = $this->actingAs(User::factory()->create(['type' => 'employee', 'status' => 'active']))
            ->getJson($url)->assertOk();

        foreach ([...self::PUBLIC_CODES, ...self::STAFF_CODES] as $field => $value) {
            $response->assertJsonPath($field, $value);
        }

        Http::assertNothingSent();
    }

    private function good(string $name = 'Товар с кодами'): Good
    {
        return Good::query()->create([
            'name' => $name,
            'is_published' => true,
            ...self::PUBLIC_CODES,
            ...self::STAFF_CODES,
        ]);
    }
}
