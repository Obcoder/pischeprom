<?php

namespace Tests\Feature;

use App\Mail\GoodInquiryReceivedMail;
use App\Models\Good;
use App\Models\GoodInquiry;
use App\Models\Measure;
use App\Services\Goods\GoodInquiryNotificationService;
use App\Services\MaxMessengerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class GoodInquiryMeasurementNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Mail::fake();
        Http::preventStrayRequests();
        config(['services.max.manager_chat_ids' => ['123'], 'services.yandex_mail.mailboxes' => [], 'services.yandex_mail.address' => null]);
    }

    public function test_manager_notification_uses_saved_kilograms_instead_of_current_product_or_package_weight(): void
    {
        $inquiry = $this->inquiry('кг', 10);
        $inquiry->good->update([
            'measure_id' => Measure::query()->firstOrCreate(['name' => 'коробка'])->id,
            'unit_weight_kg' => 10,
        ]);
        $inquiry->forceFill(['listed_price' => 240, 'proposed_price' => 190])->save();
        $this->mock(MaxMessengerService::class)->shouldReceive('sendToChat')->once()
            ->with('123', Mockery::on(function (string $text): bool {
                $this->assertStringContainsString('Количество: 10 кг / 10 кг', $text);
                $this->assertStringContainsString('Цена на сайте: 240 RUB / кг', $text);
                $this->assertStringContainsString('Предложение: 190 RUB / кг', $text);
                $this->assertStringNotContainsString('100 кг', $text);
                $this->assertStringNotContainsString('упак.', $text);

                return true;
            }))->andReturn(true);

        app(GoodInquiryNotificationService::class)->deliver($inquiry->id);

        Mail::assertSentCount(1);
        $this->assertSame(['123'], $inquiry->fresh()->max_delivered_to);
    }

    public function test_manager_notification_and_email_preserve_fractional_gram_mass(): void
    {
        $inquiry = $this->inquiry('г', 0.001);
        $this->mock(MaxMessengerService::class)->shouldReceive('sendToChat')->once()
            ->with('123', Mockery::on(function (string $text): bool {
                $this->assertStringContainsString('Количество: 0.001 г / 0,000001 кг', $text);

                return true;
            }))->andReturn(true);

        app(GoodInquiryNotificationService::class)->deliver($inquiry->id);

        $this->assertStringContainsString('0,000001 кг', (new GoodInquiryReceivedMail($inquiry))->render());
    }

    public function test_migrated_legacy_inquiry_keeps_package_quantity_but_price_per_kilogram(): void
    {
        $inquiry = $this->inquiry('кг', 2);
        // The rollout migration snapshots old quantities as packages without
        // assigning an accounting measure to these historical inquiries.
        $inquiry->forceFill([
            'kind' => 'bargain', 'measure_id' => null, 'unit_label' => 'упак.',
            'unit_weight_kg' => 10, 'package_weight' => 10, 'price_unit' => 'kg',
            'listed_price' => 240, 'proposed_price' => 190,
        ])->save();
        $html = (new GoodInquiryReceivedMail($inquiry))->render();

        $this->assertStringContainsString('Количество</th><td>2 упак.', $html);
        $this->assertStringContainsString('Общий вес</th><td>20 кг', $html);
        $this->assertStringContainsString('240,00 RUB / кг', $html);
        $this->assertStringContainsString('190,00 RUB / кг', $html);
        $this->assertStringContainsString('Предлагаемая сумма</th><td>3 800,00 RUB', $html);
        $this->mock(MaxMessengerService::class)->shouldReceive('sendToChat')->once()
            ->with('123', Mockery::on(function (string $text): bool {
                $this->assertStringContainsString('Количество: 2 упак. / 20 кг', $text);
                $this->assertStringContainsString('Цена на сайте: 240 RUB / кг', $text);
                $this->assertStringContainsString('Предложение: 190 RUB / кг', $text);

                return true;
            }))->andReturn(true);

        app(GoodInquiryNotificationService::class)->deliver($inquiry->id);
    }

    private function inquiry(string $unit, float $quantity): GoodInquiry
    {
        $good = Good::query()->create([
            'name' => 'Тестовый товар', 'is_published' => true, 'denominator' => 10,
            'measure_id' => Measure::query()->firstOrCreate(['name' => $unit])->id,
        ]);
        $this->postJson('/g/'.$good->id.'/inquiries', [
            'request_token' => (string) Str::uuid(), 'kind' => 'email',
            'quantity' => $quantity, 'measure_id' => $good->measure_id,
            'customer_name' => 'Покупатель', 'customer_email' => 'buyer@example.test',
            'consent' => true,
        ])->assertCreated();

        return GoodInquiry::query()->sole();
    }
}
