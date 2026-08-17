<?php

namespace Tests\Feature\AiSales;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class Stage11SyntheticCliTest extends Stage11TestCase
{
    public function test_synthetic_find_buyers_is_http_free_and_rolls_back_all_domain_rows(): void
    {
        Http::preventStrayRequests();
        $this->assertSame(0, Artisan::call('ai-sales:run-synthetic-find-buyers'));
        $output = Artisan::output();
        $this->assertStringContainsString('"fixture":"buyer_broccoli_spb_stage11_v1"', $output);
        $this->assertStringContainsString('"http_requests":0', $output);
        $this->assertStringContainsString('"entity_mutations":0', $output);
        $this->assertStringContainsString('"rolled_back":true', $output);

        foreach (['products', 'goods', 'units', 'entities', 'prospecting_search_jobs', 'prospecting_candidates'] as $table) {
            $this->assertFalse(DB::table($table)->exists(), $table);
        }
        Http::assertNothingSent();
    }
}
