<?php

namespace Tests\Feature\AiSales;

use Illuminate\Support\Facades\Http;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class Stage09ArchitectureGuardTest extends UnitContextsTestCase
{
    public function test_existing_yandex_transport_and_secret_config_have_one_server_side_source_of_truth(): void
    {
        $endpointOwners = $this->phpFilesContaining(app_path(), '/v2/web/search');
        $this->assertSame([
            app_path('Services/YandexSearchService.php'),
        ], $endpointOwners);

        $services = file_get_contents(config_path('services.php'));
        $this->assertIsString($services);
        $this->assertSame(1, substr_count($services, "env('YANDEX_SEARCH_API_KEY')"));
        $this->assertSame(1, substr_count($services, "env('YANDEX_SEARCH_FOLDER_ID')"));
        $this->assertStringNotContainsString('VITE_YANDEX_SEARCH', $services);

        foreach ($this->sourceFiles(resource_path('js')) as $file) {
            $contents = file_get_contents($file);
            $this->assertIsString($contents);
            $this->assertStringNotContainsString('YANDEX_SEARCH_API_KEY', $contents, $file);
            $this->assertStringNotContainsString('YANDEX_SEARCH_FOLDER_ID', $contents, $file);
            $this->assertStringNotContainsString('Api-Key ', $contents, $file);
            $this->assertStringNotContainsString('searchapi.api.cloud.yandex.net', $contents, $file);
        }

        Http::assertNothingSent();
    }

    public function test_stage09_runtime_flags_are_default_off_and_no_autonomous_modes_are_enabled(): void
    {
        foreach ([
            'query_planning_enabled',
            'search_execution_enabled',
            'existing_yandex_provider_enabled',
            'page_fetch_enabled',
            'auto_candidate_ingestion_enabled',
            'public_research_enabled',
        ] as $flag) {
            $this->assertFalse((bool) config("ai-sales.prospecting.{$flag}"), $flag);
        }
        $this->assertFalse((bool) config('ai-sales.provider_failover_enabled'));
        $this->assertFalse((bool) config('ai-sales.provider_native_tools_enabled'));
        $this->assertSame('fake_only', config('ai-sales.transport_mode'));

        Http::assertNothingSent();
    }

    /** @return list<string> */
    private function phpFilesContaining(string $directory, string $needle): array
    {
        return array_values(array_filter(
            $this->sourceFiles($directory, 'php'),
            static fn (string $file): bool => str_contains((string) file_get_contents($file), $needle),
        ));
    }

    /** @return list<string> */
    private function sourceFiles(string $directory, ?string $extension = null): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            if (! $file->isFile() || ($extension !== null && $file->getExtension() !== $extension)) {
                continue;
            }
            $files[] = $file->getPathname();
        }
        sort($files, SORT_STRING);

        return $files;
    }
}
