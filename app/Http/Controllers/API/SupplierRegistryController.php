<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use App\Services\Suppliers\SupplierAnalytics;
use App\Services\Suppliers\SupplierRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupplierRegistryController extends Controller
{
    public function index(Request $request, SupplierRegistry $registry, SupplierAnalytics $analytics): JsonResponse
    {
        $filters = $this->filters($request);

        return response()->json($registry->index($filters, $analytics->period($filters)))->header('Cache-Control', 'no-store, private');
    }

    public function show(Request $request, int $unit, SupplierRegistry $registry): JsonResponse
    {
        $data = $registry->show($unit);
        $identity = new Unit;
        $identity->forceFill($data['unit']);
        $identity->exists = true;

        return response()->json([...$data, 'capabilities' => ['can_send' => Gate::forUser($request->user())->allows('sendMail', $identity)]])
            ->header('Cache-Control', 'no-store, private');
    }

    public function export(Request $request, SupplierRegistry $registry, SupplierAnalytics $analytics): StreamedResponse
    {
        $filters = $this->filters($request);
        $period = $analytics->period($filters);
        $ids = $registry->exportSelection($filters, $period);

        return response()->streamDownload(function () use ($registry, $ids, $period): void {
            $stream = fopen('php://output', 'w');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ['ID', 'Поставщик', 'Статус', 'Приоритет', 'На контроле', 'Ответственный', 'Следующая проверка',
                'Состояние проверки', 'Период с', 'Период по', 'Заявок', 'Заявки: суммы по валютам', 'Строк заявок без цены',
                'Заявок за 30 дней', 'Средний интервал заявок, дней', 'Последняя заявка за всё время',
                'Закупок', 'Закупки: суммы по валютам (без общих юрлиц)', 'Строк закупок без цены',
                'Закупок за 30 дней', 'Средний интервал закупок, дней', 'Последняя закупка за всё время',
                'Закупок общих юрлиц (не включены в суммы)', 'Просроченных поставок', 'Неопределённых отправок'], ';', '"', '');
            foreach ($registry->exportRows($ids, $period) as $row) {
                fputcsv($stream, array_map($this->safeCell(...), $row), ';', '"', '');
            }
            fclose($stream);
        }, 'suppliers-'.$period['date_from'].'-'.$period['date_to'].'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function filters(Request $request): array
    {
        return $request->validate([
            'search' => ['nullable', 'string', 'max:150'], 'scope' => ['nullable', Rule::in(['watched', 'all', 'archived'])],
            'status' => ['nullable', Rule::in(['active', 'potential', 'paused', 'archived'])],
            'priority' => ['nullable', Rule::in(['low', 'normal', 'high', 'critical'])],
            'owner_user_id' => ['nullable', 'integer', 'min:1'],
            'attention' => ['nullable', Rule::in(['all', 'review_due', 'delivery_overdue', 'uncertain_send', 'no_orders'])],
            'sort' => ['nullable', Rule::in(['name', 'order_count', 'last_order', 'next_review'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'date_from' => ['nullable', 'date_format:Y-m-d'], 'date_to' => ['nullable', 'date_format:Y-m-d'],
            'page' => ['nullable', 'integer', 'min:1'], 'per_page' => ['nullable', 'integer', 'between:1,50'],
        ]);
    }

    private function safeCell(mixed $value): string
    {
        $text = (string) ($value ?? '');

        // Spreadsheet applications also evaluate formulas after whitespace/control prefixes.
        return preg_match('/^[\s\x00-\x20]*[=+\-@]/u', $text) ? "'".$text : $text;
    }
}
