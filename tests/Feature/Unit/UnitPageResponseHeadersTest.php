<?php

namespace Tests\Feature\Unit;

use App\Models\Unit;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Vite;
use Inertia\Ssr\SsrState;
use Tests\TestCase;

class UnitPageResponseHeadersTest extends TestCase
{
    use RefreshDatabase;

    private string $assetDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        Storage::fake('yandex');
    }

    protected function tearDown(): void
    {
        if (isset($this->assetDirectory)) {
            File::deleteDirectory($this->assetDirectory);
        }

        parent::tearDown();
    }

    private function useSyntheticAssetBuild(): void
    {

        // Normal feature tests replace Vite, hiding headers generated only for
        // full HTML loads. Exercise the real renderer without requiring a build.
        $this->assetDirectory = sys_get_temp_dir().'/unit-page-vite-'.bin2hex(random_bytes(8));
        File::makeDirectory($this->assetDirectory.'/build', 0755, true);
        $this->app->usePublicPath($this->assetDirectory);
        $this->withVite();
        Vite::useHotFile($this->assetDirectory.'/hot');

        $manifest = [
            'resources/js/app.js' => [
                'src' => 'resources/js/app.js',
                'file' => 'assets/app-test.js',
                'isEntry' => true,
            ],
            'resources/js/Pages/Ameise/Unit.vue' => [
                'src' => 'resources/js/Pages/Ameise/Unit.vue',
                'file' => 'assets/Unit-test.js',
                'isDynamicEntry' => true,
                'imports' => [],
                'css' => ['assets/Unit-test.css'],
            ],
        ];

        // A realistic rich CRM page has dozens of shared chunks. Mirroring all
        // their preload tags into Link exceeds the usual 4 KB FastCGI header
        // buffer once the session cookies and other response headers are added.
        for ($index = 0; $index < 40; $index++) {
            $name = '_UnitWorkspaceSharedComponent'.$index.'.js';
            $manifest[$name] = ['file' => 'assets/UnitWorkspaceSharedComponent'.$index.'-test.js'];
            $manifest['resources/js/Pages/Ameise/Unit.vue']['imports'][] = $name;
        }

        File::put($this->assetDirectory.'/build/manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR));
    }

    public function test_unit_hard_reload_keeps_asset_hints_in_html_without_oversized_response_headers(): void
    {
        $this->useSyntheticAssetBuild();
        $user = User::factory()->create([
            'type' => 'employee',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $unit = Unit::query()->create(['name' => 'Supplier reload regression']);
        $url = 'https://xn----dtbhbbn3apgclecj7i.xn--p1ai/Ameise/unit/'.$unit->id;

        $this->actingAs($user);

        foreach ([1, 2] as $reload) {
            $response = $this->get($url)
                ->assertOk()
                ->assertHeaderMissing('Link')
                ->assertSee('rel="modulepreload"', false)
                ->assertSee('rel="stylesheet"', false)
                ->assertSee('/build/assets/app-test.js', false)
                ->assertSee('/build/assets/Unit-test.js', false)
                ->assertSee('/build/assets/UnitWorkspaceSharedComponent39-test.js', false);

            $headerBytes = strlen("HTTP/1.1 200 OK\r\n".(string) $response->baseResponse->headers."\r\n");
            $this->assertLessThan(4096, $headerBytes, "Reload {$reload} must fit the default FastCGI header buffer, including cookies.");
        }

        $this->get($url, [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $response->viewData('page')['version'],
        ])
            ->assertOk()
            ->assertHeader('X-Inertia', 'true')
            ->assertHeaderMissing('Link')
            ->assertJsonPath('component', 'Ameise/Unit')
            ->assertJsonPath('props.unit.id', $unit->id);

        Http::assertNothingSent();
    }

    public function test_built_unit_page_reloads_with_real_ssr_and_compact_headers(): void
    {
        if (getenv('RUN_UNIT_BUILD_TEST') !== '1') {
            $this->markTestSkipped('Set RUN_UNIT_BUILD_TEST=1 after npm build and start the built Inertia SSR server.');
        }

        $this->withVite();
        config()->set([
            'inertia.ssr.enabled' => true,
            'inertia.ssr.throw_on_error' => true,
            'inertia.ssr.url' => 'http://127.0.0.1:13714',
            'services.yandex_metrica.counter_id' => null,
        ]);
        Http::allowStrayRequests(['http://127.0.0.1:13714/*']);

        $user = User::factory()->create([
            'type' => 'employee',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $unit = Unit::query()->create(['name' => 'Supplier actual SSR reload']);
        $url = 'https://xn----dtbhbbn3apgclecj7i.xn--p1ai/Ameise/unit/'.$unit->id;
        $this->actingAs($user);

        foreach ([1, 2] as $reload) {
            $this->app->forgetInstance(SsrState::class);
            $response = $this->get($url)
                ->assertOk()
                ->assertHeaderMissing('Link')
                ->assertSee('rel="modulepreload"', false)
                ->assertSee('rel="stylesheet"', false);

            $headerBytes = strlen("HTTP/1.1 200 OK\r\n".(string) $response->baseResponse->headers."\r\n");
            $this->assertLessThan(4096, $headerBytes, "Built Unit reload {$reload} must fit the FastCGI header buffer, including cookies.");

            $html = new DOMDocument;
            @$html->loadHTML('<?xml encoding="UTF-8">'.$response->getContent(), LIBXML_NONET);
            $xpath = new DOMXPath($html);
            $this->assertSame($unit->name, trim($xpath->evaluate('string(//main//h1)')));
            $this->assertSame(1, $xpath->query('//head/title')->length);
            $this->assertSame('Unit: '.$unit->name, trim($xpath->evaluate('string(//head/title)')));
            $this->assertSame('Информация о блоке '.$unit->name, $xpath->evaluate('string(//head/meta[@name="description"]/@content)'));
        }
    }
}
