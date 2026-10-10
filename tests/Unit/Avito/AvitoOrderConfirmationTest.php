<?php

namespace Tests\Unit\Avito;

use App\Models\AvitoChat;
use App\Models\AvitoMessage;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatus;
use App\Models\Telephone;
use App\Services\Avito\AvitoCrmOutboundService;
use App\Services\Avito\AvitoMessengerService;
use App\Services\Avito\AvitoOrderTableRenderer;
use App\Services\Goods\GoodStockService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class AvitoOrderConfirmationTest extends TestCase
{
    public function test_current_order_is_sent_as_copyable_text_and_table_and_temporary_files_are_removed(): void
    {
        $order = $this->order();
        $chat = new AvitoChat;
        $path = $this->temporaryImage();
        $messenger = Mockery::mock(AvitoMessengerService::class);
        $messenger->shouldReceive('sendText')->once()->with($chat, Mockery::on(function (string $text): bool {
            $this->assertStringContainsString('Заказ PP-42', $text);
            $this->assertStringContainsString('Статус: Новый', $text);
            $this->assertStringContainsString('Кол-во │ Цена │ Сумма', $text);
            $this->assertStringContainsString('1 │ Сахар', $text);
            $this->assertStringContainsString('2,125 кор. │ 150,1234 RUB / кор. │ 319,01 RUB', $text);
            $this->assertStringContainsString('Масса 1 кор.: 25 кг', $text);
            $this->assertStringContainsString('Итого: 319,01 RUB', $text);
            $this->assertStringContainsString('Дата доставки: 06.10.2026', $text);
            $this->assertStringContainsString('Желаемое время: После 18:00', $text);
            $this->assertStringContainsString('Телефон: +79991234567', $text);
            $this->assertStringNotContainsString('Внутренний комментарий', $text);
            $this->assertStringNotContainsString('создан.', $text);
            $this->assertStringNotContainsString('•', $text);

            return true;
        }))->andReturn(new AvitoMessage);
        $messenger->shouldReceive('sendImage')->once()->with($chat, Mockery::on(
            fn (UploadedFile $file) => $file->getPathname() === $path && $file->getClientMimeType() === 'image/jpeg'
        ))->andReturn(new AvitoMessage);
        $renderer = Mockery::mock(AvitoOrderTableRenderer::class);
        $renderer->shouldReceive('render')->once()->with($order)->andReturn([$path]);

        try {
            $result = $this->outbound($messenger, $renderer)->sendOrderConfirmation($chat, $order);
            $this->assertSame(['sent' => 2, 'warnings' => []], $result);
            $this->assertFileDoesNotExist($path);
        } finally {
            @unlink($path);
        }
    }

    public function test_long_order_text_is_complete_and_each_message_respects_avito_limit(): void
    {
        $order = $this->order();
        $items = [];
        foreach (range(1, 20) as $index) {
            $items[] = new OrderItem([
                'good_name' => "Товар {$index} ".str_repeat('Очень длинное название ', 7),
                'quantity' => 1,
                'price_gross' => null,
                'line_total' => null,
                'currency_code' => 'RUB',
            ]);
        }
        $order->setRelation('items', new Collection($items));
        $texts = [];
        $messenger = Mockery::mock(AvitoMessengerService::class);
        $messenger->shouldReceive('sendText')->andReturnUsing(function (AvitoChat $chat, string $text) use (&$texts): AvitoMessage {
            $this->assertLessThanOrEqual(1000, mb_strlen($text));
            $texts[] = $text;

            return new AvitoMessage;
        });
        $renderer = Mockery::mock(AvitoOrderTableRenderer::class);
        $renderer->shouldReceive('render')->once()->andReturn([]);

        $result = $this->outbound($messenger, $renderer)->sendOrderConfirmation(new AvitoChat, $order);

        $this->assertGreaterThan(1, count($texts));
        $this->assertSame(count($texts), $result['sent']);
        $combined = implode("\n", $texts);
        foreach ($items as $item) {
            $this->assertStringContainsString(trim($item->good_name), $combined);
        }
        $this->assertStringContainsString('Уточняется', $combined);
        $this->assertStringContainsString('Итого по указанным ценам:', $combined);
        $this->assertStringContainsString('Желаемое время: После 18:00', $combined);
    }

    public function test_rendering_failure_preserves_text_and_returns_warning(): void
    {
        $messenger = Mockery::mock(AvitoMessengerService::class);
        $messenger->shouldReceive('sendText')->once()->andReturn(new AvitoMessage);
        $messenger->shouldNotReceive('sendImage');
        $renderer = Mockery::mock(AvitoOrderTableRenderer::class);
        $renderer->shouldReceive('render')->once()->andThrow(new RuntimeException('Render failed'));

        $result = $this->outbound($messenger, $renderer)->sendOrderConfirmation(new AvitoChat, $this->order());

        $this->assertSame(1, $result['sent']);
        $this->assertStringContainsString('Текст заказа отправлен', $result['warnings'][0]);
    }

    public function test_failed_image_send_cleans_all_pages_and_reports_partial_delivery(): void
    {
        $paths = [$this->temporaryImage(), $this->temporaryImage()];
        $messenger = Mockery::mock(AvitoMessengerService::class);
        $messenger->shouldReceive('sendText')->once()->andReturn(new AvitoMessage);
        $messenger->shouldReceive('sendImage')->once()->andThrow(new RuntimeException('Upload failed'));
        $renderer = Mockery::mock(AvitoOrderTableRenderer::class);
        $renderer->shouldReceive('render')->once()->andReturn($paths);

        try {
            $result = $this->outbound($messenger, $renderer)->sendOrderConfirmation(new AvitoChat, $this->order());
            $this->assertSame(1, $result['sent']);
            $this->assertNotEmpty($result['warnings']);
            foreach ($paths as $path) {
                $this->assertFileDoesNotExist($path);
            }
        } finally {
            foreach ($paths as $path) {
                @unlink($path);
            }
        }
    }

    public function test_failed_text_send_does_not_report_order_creation_or_render_table(): void
    {
        $messenger = Mockery::mock(AvitoMessengerService::class);
        $messenger->shouldReceive('sendText')->once()->andThrow(new RuntimeException('Send failed'));
        $messenger->shouldNotReceive('sendImage');
        $renderer = Mockery::mock(AvitoOrderTableRenderer::class);
        $renderer->shouldNotReceive('render');

        $result = $this->outbound($messenger, $renderer)->sendOrderConfirmation(new AvitoChat, $this->order());

        $this->assertSame(0, $result['sent']);
        $this->assertStringContainsString('Не удалось отправить', $result['warnings'][0]);
        $this->assertStringNotContainsString('создан', $result['warnings'][0]);
    }

    private function order(): Order
    {
        $order = new Order([
            'number' => 'PP-42',
            'currency_code' => 'RUB',
            'total_amount' => 319.01,
            'total_weight' => 53.125,
            'delivery_date' => '2026-10-06',
            'preferred_delivery_time' => 'После 18:00',
            'internal_comment' => 'Внутренний комментарий',
        ]);
        $order->setRelation('items', new Collection([new OrderItem([
            'good_name' => 'Сахар',
            'quantity' => 2.125,
            'snapshot' => ['measurement' => ['measure_id' => 2, 'unit_label' => 'кор.', 'kilograms_per_unit' => 25]],
            'denominator' => 25,
            'price_gross' => 150.1234,
            'line_total' => 319.01,
            'currency_code' => 'RUB',
        ])]));
        $order->setRelation('status', new OrderStatus(['name' => 'Новый']));
        $order->setRelation('buildings', new Collection);
        $order->setRelation('contactTelephone', new Telephone(['number' => '+79991234567']));

        return $order;
    }

    private function outbound(AvitoMessengerService $messenger, AvitoOrderTableRenderer $renderer): AvitoCrmOutboundService
    {
        return new AvitoCrmOutboundService($messenger, Mockery::mock(GoodStockService::class), $renderer);
    }

    private function temporaryImage(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'avito-order-test-');
        file_put_contents($path, 'test image');

        return $path;
    }
}
