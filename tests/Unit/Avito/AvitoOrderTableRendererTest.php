<?php

namespace Tests\Unit\Avito;

use App\Models\Apartment;
use App\Models\Building;
use App\Models\City;
use App\Models\Entity;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatus;
use App\Models\Telephone;
use App\Services\Avito\AvitoOrderTableRenderer;
use GdImage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Pivot;
use RuntimeException;
use Tests\TestCase;

class AvitoOrderTableRendererTest extends TestCase
{
    /** @var array<int, string> */
    private array $paths = [];

    protected function tearDown(): void
    {
        foreach ($this->paths as $path) {
            @unlink($path);
        }

        parent::tearDown();
    }

    public function test_it_renders_a_real_jpeg_with_current_customer_order_details_and_no_internal_data(): void
    {
        $renderer = $this->recordingRenderer();
        $order = $this->order();
        $this->paths = $renderer->render($order);

        $this->assertCount(1, $this->paths);
        $this->assertJpeg($this->paths[0]);
        $text = implode(' ', $renderer->texts);
        foreach (['Заказ № PP-123', 'Статус: В обработке', 'Создан: 04.10.2026 11:20', 'Брокколи замороженная', 'Масса единицы: 10 кг', '2', '145,50 ₽', '291,00 ₽', 'Общий вес: 20 кг', 'Дата доставки: 06.10.2026', 'Желаемое время: 10:00–12:00', 'Санкт-Петербург, Примерная, 12, кв. 7', '+7 999 111-22-33'] as $detail) {
            $this->assertStringContainsString($detail, $text);
        }
        foreach (['private-comment-secret', 'private-entity-secret', 'private-snapshot-secret'] as $privateValue) {
            $this->assertStringNotContainsString($privateValue, $text);
        }
    }

    public function test_long_names_and_one_hundred_rows_are_fully_wrapped_and_paginated_without_clipping(): void
    {
        $order = $this->order();
        $items = [];
        for ($index = 1; $index <= 100; $index++) {
            $items[] = new OrderItem([
                'good_name' => 'Позиция-'.sprintf('%03d', $index).str_repeat('Д', 240),
                'quantity' => 999999999,
                'denominator' => 0.125,
                'price_gross' => 9999999.99,
                'line_total' => 999999999.99,
                'currency_code' => 'USD',
            ]);
        }
        $order->setRelation('items', new Collection($items));
        $renderer = $this->recordingRenderer();
        $this->paths = $renderer->render($order);

        $this->assertGreaterThan(1, count($this->paths));
        foreach ($this->paths as $path) {
            $this->assertJpeg($path);
        }
        $textWithoutWraps = str_replace(' ', '', implode('', $renderer->texts));
        foreach ($items as $item) {
            $this->assertStringContainsString($item->good_name, $textWithoutWraps);
        }
        $this->assertSame(100, substr_count(implode(' ', $renderer->texts), 'Масса единицы: 0,125 кг'));
        $this->assertStringContainsString(count($this->paths).' / '.count($this->paths), implode(' ', $renderer->texts));
    }

    public function test_missing_prices_and_delivery_are_explicit_without_fabricated_zero_amounts(): void
    {
        $order = $this->order();
        $order->fill(['total_amount' => null, 'total_weight' => null, 'delivery_date' => null, 'preferred_delivery_time' => null]);
        $order->setRelation('buildings', new Collection);
        $order->setRelation('contactTelephone', null);
        $order->setRelation('items', new Collection([new OrderItem(['good_name' => 'Товар без цены', 'quantity' => 1])]));
        $renderer = $this->recordingRenderer();
        $this->paths = $renderer->render($order);

        $text = implode(' ', $renderer->texts);
        $this->assertStringContainsString('Стоимость позиций без цены уточняется.', $text);
        $this->assertStringContainsString('Общий вес: Уточняется', $text);
        $this->assertStringContainsString('Дата доставки: Уточняется', $text);
        $this->assertStringContainsString('Адрес доставки: Уточняется', $text);
        $this->assertStringNotContainsString('0,00', $text);
        $this->assertStringNotContainsString('Контактный телефон:', $text);
    }

    public function test_partial_totals_are_labelled_and_small_prices_and_weights_keep_their_precision(): void
    {
        $order = $this->order();
        $order->fill(['total_amount' => 0.12, 'total_weight' => 0.0001]);
        $order->setRelation('items', new Collection([
            new OrderItem(['good_name' => 'Малая фасовка', 'quantity' => 1.125, 'denominator' => 0.0001, 'price_gross' => 0.1234, 'line_total' => 0.12]),
            new OrderItem(['good_name' => 'Цена уточняется', 'quantity' => 1]),
        ]));
        $renderer = $this->recordingRenderer();
        $this->paths = $renderer->render($order);

        $text = implode(' ', $renderer->texts);
        $this->assertStringContainsString('ИТОГО ПО УКАЗАННЫМ ЦЕНАМ', $text);
        $this->assertStringContainsString('Стоимость позиций без цены уточняется.', $text);
        $this->assertStringContainsString('0,1234 ₽', $text);
        $this->assertStringContainsString('0,12 ₽', $text);
        $this->assertStringContainsString('1,125', $text);
        $this->assertStringContainsString('Масса единицы: 0,0001 кг', $text);
        $this->assertStringContainsString('Общий вес: 0,0001 кг', $text);
    }

    public function test_a_failure_on_a_later_page_removes_already_rendered_temporary_images(): void
    {
        $order = $this->order();
        $order->setRelation('items', new Collection(array_map(fn ($index) => new OrderItem([
            'good_name' => 'Товар '.$index,
            'quantity' => 1,
            'price_gross' => 10,
            'line_total' => 10,
        ]), range(1, 40))));
        $renderer = new class extends AvitoOrderTableRenderer
        {
            private int $pageCount = 0;

            /** @var array<int, string> */
            public array $createdPaths = [];

            protected function drawText(GdImage $image, string $text, int $x, int $y, int $size, string $hex, bool $bold = false, string $align = 'left'): void
            {
                if ($text === 'ИНФОРМАЦИЯ ПО ЗАКАЗУ' && ++$this->pageCount === 2) {
                    throw new RuntimeException('Synthetic rendering failure');
                }
                parent::drawText($image, $text, $x, $y, $size, $hex, $bold, $align);
            }

            protected function temporaryPath(): string
            {
                $path = parent::temporaryPath();
                $this->createdPaths[] = $path;

                return $path;
            }
        };

        try {
            $renderer->render($order);
            $this->fail('Expected a rendering failure on page 2.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Synthetic rendering failure', $exception->getMessage());
        }

        $this->assertNotEmpty($renderer->createdPaths);
        foreach ($renderer->createdPaths as $path) {
            $this->assertFileDoesNotExist($path);
        }
    }

    public function test_order_measurement_snapshot_is_used_instead_of_packaging(): void
    {
        $order = $this->order();
        $order->setRelation('items', new Collection([new OrderItem([
            'good_name' => 'Сахар',
            'quantity' => 10.125,
            'denominator' => 10,
            'price_gross' => 100,
            'line_total' => 1012.5,
            'snapshot' => ['measurement' => ['measure_id' => 1, 'unit_label' => 'кг', 'kilograms_per_unit' => 1]],
        ])]));
        $renderer = $this->recordingRenderer();
        $this->paths = $renderer->render($order);

        $text = implode(' ', $renderer->texts);
        $this->assertStringContainsString('10,125 кг', $text);
        $this->assertStringContainsString('100,00 ₽ / кг', $text);
        $this->assertStringContainsString('Масса единицы: 1 кг', $text);
        $this->assertStringNotContainsString('Масса единицы: 10 кг', $text);
    }

    private function assertJpeg(string $path): void
    {
        $this->assertSame("\xff\xd8\xff", file_get_contents($path, false, null, 0, 3));
        $dimensions = getimagesize($path);
        $this->assertSame(IMAGETYPE_JPEG, $dimensions[2]);
        $this->assertSame(1400, $dimensions[0]);
        $this->assertLessThanOrEqual(1900, $dimensions[1]);
        $image = imagecreatefromjpeg($path);
        $this->assertInstanceOf(GdImage::class, $image);
        $this->assertNotSame(imagecolorat($image, 0, 0), imagecolorat($image, 60, 50));
        imagedestroy($image);
    }

    private function recordingRenderer(): AvitoOrderTableRenderer
    {
        return new class extends AvitoOrderTableRenderer
        {
            /** @var array<int, string> */
            public array $texts = [];

            protected function drawText(GdImage $image, string $text, int $x, int $y, int $size, string $hex, bool $bold = false, string $align = 'left'): void
            {
                $this->texts[] = $text;
                parent::drawText($image, $text, $x, $y, $size, $hex, $bold, $align);
            }
        };
    }

    private function order(): Order
    {
        $order = new Order([
            'number' => 'PP-123',
            'total_amount' => 291,
            'total_weight' => 20,
            'currency_code' => 'RUB',
            'submitted_at' => '2026-10-04 11:20:00',
            'delivery_date' => '2026-10-06',
            'preferred_delivery_time' => '10:00–12:00',
            'internal_comment' => 'private-comment-secret',
        ]);
        $order->setRelation('status', new OrderStatus(['name' => 'В обработке']));
        $order->setRelation('entity', new Entity(['name' => 'private-entity-secret']));
        $order->setRelation('contactTelephone', new Telephone(['number' => '+7 999 111-22-33']));
        $order->setRelation('items', new Collection([new OrderItem([
            'good_name' => 'Брокколи замороженная',
            'quantity' => 2,
            'denominator' => 10,
            'price_gross' => 145.5,
            'line_total' => 291,
            'currency_code' => 'RUB',
            'snapshot' => ['internal' => 'private-snapshot-secret'],
        ])]));
        $apartment = new Apartment(['number' => '7']);
        $apartment->id = 5;
        $building = new Building(['address' => 'Примерная, 12']);
        $building->setRelation('city', new City(['name' => 'Санкт-Петербург']));
        $building->setRelation('apartments', new Collection([$apartment]));
        $building->setRelation('pivot', new Pivot(['apartment_id' => 5]));
        $order->setRelation('buildings', new Collection([$building]));

        return $order;
    }
}
