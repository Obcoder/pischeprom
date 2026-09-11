<?php

namespace Tests\Feature\Mail;

use App\Domain\AiPriceLists\Contracts\OcrProviderInterface;
use App\Domain\AiPriceLists\DTO\OcrResponse;
use App\Domain\AiPriceLists\Providers\FakeOcrProvider;
use App\Models\MailMessage;
use App\Models\MailMessageAttachment;
use App\Services\Mail\MailAttachmentAnalyzer;
use App\Services\Mail\YandexMailboxService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class MailAttachmentAnalysisTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'ai-price-lists.ai.enabled' => false,
        ]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        Storage::fake('local');

        Schema::create('mail_messages', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
            $table->string('mailbox');
            $table->string('folder');
            $table->string('direction');
            $table->unsignedBigInteger('imap_uid')->nullable();
            $table->string('subject')->nullable();
            $table->string('from_address')->nullable();
            $table->boolean('has_attachments')->default(true);
        });
        Schema::create('mail_message_attachments', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
            $table->foreignId('mail_message_id');
            $table->string('disk');
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->string('file_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
        });
    }

    public function test_saved_pdf_invoice_returns_copyable_text_and_issuer_fields(): void
    {
        $message = $this->message();
        $attachment = $this->attachment($message, $this->pdf([
            'Счёт на оплату № А-105/26 от 11 сентября 2026 г.',
            'Поставщик: ООО «Ромашка», ИНН 7701234567',
            'Покупатель: ООО «Пищепром»',
        ]));

        $this->postJson($this->url($message), ['attachment_id' => $attachment->id])
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('status', 'invoice')
            ->assertJsonPath('fields.number', 'А-105/26')
            ->assertJsonPath('fields.date', '2026-09-11')
            ->assertJsonPath('fields.counterparty', 'ООО «Ромашка»')
            ->assertJsonPath('fields.heading', 'Счёт на оплату № А-105/26 от 11 сентября 2026 г.')
            ->assertJsonPath('pages', 1)
            ->assertJsonPath('source', 'text')
            ->assertJsonPath('ocr_available', false)
            ->assertJsonPath('text', fn (string $text): bool => str_contains($text, 'Покупатель: ООО «Пищепром»'));
    }

    public function test_attachment_from_another_message_cannot_be_analyzed(): void
    {
        $message = $this->message();
        $other = $this->attachment($this->message(), $this->pdf(['Private data']));
        $mailbox = Mockery::mock(YandexMailboxService::class);
        $mailbox->shouldNotReceive('downloadAttachment');
        $this->app->instance(YandexMailboxService::class, $mailbox);

        $this->postJson($this->url($message), ['attachment_id' => $other->id])->assertNotFound();
        $this->postJson($this->url($message), ['attachment_id' => 999])->assertNotFound();
    }

    public function test_imap_pdf_can_be_analyzed_without_saving_it(): void
    {
        $message = $this->message(['imap_uid' => 42]);
        $mailbox = Mockery::mock(YandexMailboxService::class);
        $mailbox->shouldReceive('downloadAttachment')->once()
            ->with(Mockery::on(fn (MailMessage $value): bool => $value->is($message)), 0)
            ->andReturn(['name' => 'notice.pdf', 'content' => $this->pdf(['Delivery notice'])]);
        $this->app->instance(YandexMailboxService::class, $mailbox);

        $this->postJson($this->url($message))
            ->assertOk()
            ->assertJsonPath('status', 'not_invoice')
            ->assertJsonPath('fields.number', null)
            ->assertJsonPath('text', 'Delivery notice');
        $this->assertDatabaseCount('mail_message_attachments', 0);
    }

    public function test_a_pdf_without_text_reports_that_it_needs_ocr(): void
    {
        $message = $this->message();
        $this->attachment($message, $this->pdf([]));

        $this->postJson($this->url($message))->assertOk()
            ->assertJsonPath('status', 'no_text')
            ->assertJsonPath('text', '')
            ->assertJsonPath('fields.number', null)
            ->assertJsonPath('ocr_available', false);
        $this->postJson($this->url($message), ['ocr' => true])->assertUnprocessable();
    }

    public function test_missing_files_and_non_pdf_or_corrupt_files_have_safe_errors(): void
    {
        $message = $this->message();
        $attachment = $this->attachment($message, '<html>not a PDF</html>');
        $payload = ['attachment_id' => $attachment->id];

        $this->postJson($this->url($message), $payload)->assertUnprocessable()
            ->assertJsonPath('message', 'Распознавание доступно только для PDF-файлов.');

        Storage::disk('local')->put($attachment->path, '%PDF-1.4 damaged-secret-internal-data');
        $response = $this->postJson($this->url($message), $payload)->assertUnprocessable();
        $this->assertStringNotContainsString('secret-internal-data', $response->getContent());

        Storage::disk('local')->delete($attachment->path);
        $this->postJson($this->url($message), $payload)->assertNotFound();
    }

    public function test_actual_bytes_and_page_count_are_bounded(): void
    {
        $message = $this->message();
        $attachment = $this->attachment($message, $this->pdf(['Small']));
        Storage::disk('local')->put($attachment->path, '%PDF-'.str_repeat('x', MailAttachmentAnalyzer::MAX_FILE_BYTES));

        $this->postJson($this->url($message))->assertUnprocessable()
            ->assertJsonPath('message', 'Для распознавания выберите PDF размером до 10 МБ.');

        Storage::disk('local')->put($attachment->path, $this->pdf(['Page'], 21));
        $this->postJson($this->url($message))->assertUnprocessable()
            ->assertJsonPath('message', 'Для распознавания выберите PDF от 1 до 20 страниц.');
    }

    public function test_parallel_analysis_of_the_same_attachment_is_rejected(): void
    {
        $message = $this->message();
        $lock = Cache::lock('mail-attachment-analysis:'.$message->id.':index-0', 600);
        $this->assertTrue($lock->get());

        try {
            $this->postJson($this->url($message))->assertTooManyRequests();
        } finally {
            $lock->release();
        }
    }

    public function test_manual_ocr_records_usage_and_obeys_the_existing_page_budget(): void
    {
        $this->createUsageTable();
        config([
            'ai-price-lists.ai.daily_ocr_page_limit' => 1,
            'ai-price-lists.ai.monthly_ocr_page_limit' => 5,
        ]);
        $provider = Mockery::mock(OcrProviderInterface::class);
        $provider->shouldReceive('configured')->andReturn(true);
        $provider->shouldReceive('recognize')->once()->andReturn(new OcrResponse([
            ['text' => 'Счёт на оплату № 78 от 11.09.2026'],
            ['text' => 'Поставщик: ООО «Север»'],
        ], 1, 'test-ocr-request', 25));
        $this->app->instance(OcrProviderInterface::class, $provider);
        $message = $this->message();
        $attachment = $this->attachment($message, $this->pdf([]));

        $this->postJson($this->url($message))->assertOk()->assertJsonPath('ocr_available', true);
        $this->postJson($this->url($message), ['ocr' => true])->assertOk()
            ->assertJsonPath('status', 'invoice')
            ->assertJsonPath('fields.number', '78')
            ->assertJsonPath('source', 'ocr');
        $this->assertDatabaseHas('ai_usage_records', ['operation' => 'ocr', 'pages' => 1, 'status' => 'success']);
        $this->assertStringNotContainsString('Север', json_encode(DB::table('ai_usage_records')->get(), JSON_UNESCAPED_UNICODE));
        $this->postJson($this->url($message), ['ocr' => true, 'attachment_id' => $attachment->id])->assertOk()
            ->assertJsonPath('fields.number', '78');
        $this->assertDatabaseCount('ai_usage_records', 1);
        $cached = Cache::get('mail-attachment-ocr:v1:'.$message->id.':'.hash('sha256', $this->pdf([])));
        $this->assertIsString($cached);
        $this->assertStringNotContainsString('Север', $cached);
        $otherMessage = $this->message();
        $this->attachment($otherMessage, $this->pdf([]));
        $this->postJson($this->url($otherMessage), ['ocr' => true])->assertTooManyRequests();
        $this->travel(6)->minutes();
        $this->postJson($this->url($message), ['ocr' => true])->assertTooManyRequests()
            ->assertJsonPath('message', 'Лимит распознавания сканов исчерпан. Попробуйте позже.');
    }

    public function test_multipage_pdfs_are_not_sent_to_the_single_page_ocr_endpoint(): void
    {
        $this->createUsageTable();
        $provider = Mockery::mock(OcrProviderInterface::class);
        $provider->shouldReceive('configured')->andReturn(true);
        $provider->shouldNotReceive('recognize');
        $this->app->instance(OcrProviderInterface::class, $provider);
        $message = $this->message();
        $this->attachment($message, $this->pdf([], 2));

        $this->postJson($this->url($message))->assertOk()->assertJsonPath('ocr_available', false)
            ->assertJsonPath('ocr_message', 'Для распознавания сканов поддерживаются одностраничные PDF.');
        $this->postJson($this->url($message), ['ocr' => true])->assertUnprocessable();
    }

    public function test_fake_ocr_provider_is_not_available_in_production(): void
    {
        $this->createUsageTable();
        $this->app->instance(OcrProviderInterface::class, new FakeOcrProvider);
        $this->app->instance('env', 'production');
        $message = $this->message();
        $this->attachment($message, $this->pdf([]));

        $this->postJson($this->url($message))->assertOk()->assertJsonPath('ocr_available', false);
        $this->postJson($this->url($message), ['ocr' => true])->assertUnprocessable();
    }

    private function message(array $attributes = []): MailMessage
    {
        return MailMessage::query()->create($attributes + [
            'mailbox' => 'office@example.test',
            'folder' => 'INBOX',
            'direction' => 'incoming',
            'imap_uid' => null,
            'subject' => 'PDF attachment',
            'from_address' => 'supplier@example.test',
        ]);
    }

    private function attachment(MailMessage $message, string $content): MailMessageAttachment
    {
        $path = 'mail/'.$message->id.'/invoice.pdf';
        Storage::disk('local')->put($path, $content);

        return $message->attachments()->create([
            'disk' => 'local',
            'path' => $path,
            'original_name' => 'invoice.pdf',
            'mime_type' => 'application/pdf',
            'size' => strlen($content),
        ]);
    }

    private function url(MailMessage $message): string
    {
        return '/api/mail-messages/'.$message->id.'/attachments/0/analyze';
    }

    private function createUsageTable(): void
    {
        Schema::create('ai_usage_records', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
            $table->string('provider');
            $table->string('operation');
            $table->string('model')->nullable();
            $table->string('external_request_id')->nullable();
            $table->unsignedInteger('pages')->nullable();
            $table->unsignedInteger('units')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->string('status');
            $table->boolean('cost_is_estimate');
            $table->json('metadata')->nullable();
        });
    }

    /** Build a real Unicode PDF so feature tests exercise the installed PDF parser. */
    private function pdf(array $lines, int $pageCount = 1): string
    {
        $text = implode('', $lines);
        $characters = array_unique(preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: []);
        $mappings = [];

        foreach ($characters as $character) {
            $hex = strtoupper(bin2hex(mb_convert_encoding($character, 'UTF-16BE', 'UTF-8')));
            $mappings[] = '<'.$hex.'> <'.$hex.'>';
        }

        $cmap = "/CIDInit /ProcSet findresource begin\n12 dict begin\nbegincmap\n/CIDSystemInfo << /Registry (Adobe) /Ordering (UCS) /Supplement 0 >> def\n/CMapName /TestUnicode def\n/CMapType 2 def\n1 begincodespacerange\n<0000> <FFFF>\nendcodespacerange\n".count($mappings)." beginbfchar\n".implode("\n", $mappings)."\nendbfchar\nendcmap\nCMapName currentdict /CMap defineresource pop\nend\nend";
        $stream = "BT /F1 12 Tf 50 750 Td\n";

        foreach ($lines as $line) {
            $stream .= '<'.bin2hex(mb_convert_encoding($line, 'UTF-16BE', 'UTF-8'))."> Tj 0 -20 Td\n";
        }

        $stream .= 'ET';
        $kids = implode(' ', array_map(fn (int $index): string => (7 + $index).' 0 R', range(0, $pageCount - 1)));
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Count '.$pageCount.' /Kids [ '.$kids.' ] >>',
            '<< /Type /Font /Subtype /Type0 /BaseFont /Test /Encoding /Identity-H /DescendantFonts [4 0 R] /ToUnicode 5 0 R >>',
            '<< /Type /Font /Subtype /CIDFontType2 /BaseFont /Test /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> >>',
            '<< /Length '.strlen($cmap).">>\nstream\n".$cmap."\nendstream",
            '<< /Length '.strlen($stream).">>\nstream\n".$stream."\nendstream",
        ];

        for ($page = 0; $page < $pageCount; $page++) {
            $objects[] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents 6 0 R >>';
        }

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n".$object."\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= 'xref'."\n0 ".count($offsets)."\n0000000000 65535 f \n";

        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= sprintf('%010d 00000 n ', $offset)."\n";
        }

        return $pdf.'trailer << /Size '.count($offsets)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF\n";
    }
}
