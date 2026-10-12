<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\Entity;
use App\Models\Good;
use App\Models\GoodEntityPrice;
use App\Models\GoodPriceHistory;
use App\Models\GoodPriceTypeValue;
use App\Models\PriceType;
use App\Models\User;
use App\Services\Goods\GoodPricingCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class GoodPricingWorkspaceController extends Controller
{
    public function index(Good $good, GoodPricingCalculator $calculator): JsonResponse
    {
        $sources = $calculator->sources($good);
        $manufacturers = DB::table('good_product as gp')->where('gp.good_id', $good->id)
            ->join('manufacturers as m', 'm.product_id', '=', 'gp.product_id')
            ->join('units as u', 'u.id', '=', 'm.unit_id')->distinct()->orderBy('u.name')->pluck('u.name');
        $common = $good->priceTypeValues()->with(['priceType.currency', 'currency'])->orderBy('id')->get();
        $private = GoodEntityPrice::where('good_id', $good->id)->with(['entity' => fn ($query) => $query->without(['buildings', 'classification', 'country'])->select('id', 'name'), 'currency'])->orderBy('id')->get();

        return response()->json([
            'rows' => $common->map(fn ($price) => $this->row($price, 'common'))->concat($private->map(fn ($price) => $this->row($price, 'entity')))->values(),
            'sources' => $sources,
            'partners' => $sources->pluck('supplier')->filter()->unique()->map(fn ($name) => ['role' => 'Поставщик', 'name' => $name])
                ->concat($manufacturers->map(fn ($name) => ['role' => 'Производитель', 'name' => $name]))->values(),
            'types' => PriceType::with('currency')->orderBy('sort_order')->orderBy('name')->get(),
            'currencies' => Currency::orderBy('code')->get(['id', 'code']),
            'measurement' => $good->measurement(), 'vat_rate' => $good->vatRate?->rate ?? 20,
            'calculations' => $good->priceCalculations()->with('currency')->latest('id')->get(),
        ]);
    }

    public function preview(Request $request, Good $good, GoodPricingCalculator $calculator): JsonResponse
    {
        return response()->json($calculator->calculate($good, $this->validated($request)));
    }

    public function save(Request $request, Good $good, GoodPricingCalculator $calculator): JsonResponse
    {
        $input = $this->validated($request, true);

        return DB::transaction(function () use ($good, $input, $calculator): JsonResponse {
            $good = Good::lockForUpdate()->findOrFail($good->id);
            $private = $input['scope'] === 'entity';
            $target = $private ? Entity::findOrFail($input['target_id']) : PriceType::findOrFail($input['target_id']);
            if (! $private && ! $target->is_active && $input['active']) {
                throw \Illuminate\Validation\ValidationException::withMessages(['target_id' => 'Сначала включите этот вид цены в справочнике типов цен.']);
            }
            $class = $private ? GoodEntityPrice::class : GoodPriceTypeValue::class;
            $key = $private ? 'entity_id' : 'price_type_id';
            $query = $class::where('good_id', $good->id)->where($key, $target->id);
            $price = ! empty($input['id']) ? $query->findOrFail($input['id']) : $query->first();
            abort_if($price && (int) $price->revision !== (int) $input['revision'], 409, 'Цена уже изменена другим сотрудником. Обновите таблицу и повторите изменение.');
            abort_if(! $price && (int) $input['revision'] !== 0, 409, 'Цена удалена. Обновите таблицу.');
            $result = $calculator->calculate($good, $input);
            $price ??= new $class(['good_id' => $good->id, $key => $target->id]);
            $price->fill([
                'currency_id' => $result['currency_id'], 'price_net' => $result['price_net'], 'price_gross' => $result['price_gross'],
                'vat_rate' => $result['vat_rate'], 'valid_from' => $input['valid_from'] ?? null, 'valid_to' => $input['valid_to'] ?? null,
                'manual_comment' => $input['comment'] ?? null,
                'pricing_input' => collect($input)->except(['id', 'revision', 'target_id', 'scope', 'measurement'])->all(),
                'pricing_result' => $result,
                ...($private ? ['is_active' => $input['active']] : ['is_published' => $input['active'], 'is_manual' => $input['method'] === 'manual', 'calculation_id' => $input['method'] === 'saved' ? $input['calculation_id'] : null]),
            ]);
            $price->save();

            return response()->json($this->row($price->fresh($private ? ['entity', 'currency'] : ['priceType.currency', 'currency']), $input['scope']));
        });
    }

    public function destroy(Request $request, Good $good, string $scope, int $record): JsonResponse
    {
        $request->validate(['revision' => ['required', 'integer', 'min:1']]);

        return DB::transaction(function () use ($request, $good, $scope, $record): JsonResponse {
            Good::lockForUpdate()->findOrFail($good->id);
            $class = $scope === 'entity' ? GoodEntityPrice::class : GoodPriceTypeValue::class;
            $price = $class::where('good_id', $good->id)->lockForUpdate()->findOrFail($record);
            abort_unless((int) $price->revision === (int) $request->input('revision'), 409, 'Цена уже изменена. Обновите таблицу.');
            $price->delete();

            return response()->json(null, 204);
        });
    }

    public function history(Request $request, Good $good): JsonResponse
    {
        $input = $request->validate(['scope' => ['nullable', Rule::in(['common', 'entity'])], 'page' => ['nullable', 'integer', 'min:1']]);

        return response()->json(GoodPriceHistory::where('good_id', $good->id)
            ->when($input['scope'] ?? null, fn ($query, $scope) => $query->where('scope', $scope))
            ->orderByDesc('id')->paginate(30));
    }

    public function entities(Request $request): JsonResponse
    {
        $input = $request->validate(['search' => ['nullable', 'string', 'max:150']]);
        $search = trim($input['search'] ?? '');

        return response()->json(Entity::without(['buildings', 'classification', 'country'])
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q->where('name', 'like', '%'.$search.'%')->orWhere('INN', 'like', '%'.$search.'%')))
            ->orderBy('name')->paginate(30, ['id', 'name', 'INN']));
    }

    public function access(Entity $entity): JsonResponse
    {
        $granted = DB::table('entity_price_access')->where('entity_id', $entity->id)->pluck('user_id')->all();

        return response()->json($entity->users()->where('users.status', 'active')->wherePivot('status', 'active')->get(['users.id', 'users.name', 'users.email', 'users.status'])
            ->map(fn ($user) => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email,
                'owner' => (int) $entity->customer_created_by_user_id === (int) $user->id,
                'allowed' => (int) $entity->customer_created_by_user_id === (int) $user->id || in_array($user->id, $granted),
            ]));
    }

    public function grant(Request $request, Entity $entity, User $user): JsonResponse
    {
        $input = $request->validate(['allowed' => ['required', 'boolean']]);
        abort_unless($entity->users()->whereKey($user->id)->wherePivot('status', 'active')->exists(), 404);
        if ($input['allowed']) {
            DB::table('entity_price_access')->updateOrInsert(['entity_id' => $entity->id, 'user_id' => $user->id],
                ['granted_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
        } else {
            DB::table('entity_price_access')->where('entity_id', $entity->id)->where('user_id', $user->id)->delete();
        }

        return $this->access($entity);
    }

    private function row($price, string $scope): array
    {
        $target = $scope === 'entity' ? $price->entity : $price->priceType;

        return [
            'id' => $price->id, 'scope' => $scope, 'target_id' => $target->id, 'target_name' => $target->name,
            'revision' => $price->revision, 'price_net' => $price->price_net, 'price_gross' => $price->price_gross,
            'vat_rate' => $price->vat_rate, 'currency_id' => $price->currency_id ?? $target->currency_id,
            'currency_code' => $price->currency?->code ?? ($scope === 'common' ? $target->currency?->code : null),
            'active' => $scope === 'entity' ? $price->is_active : $price->is_published,
            'public_type' => $scope === 'common' && $target->is_public,
            'valid_from' => $price->valid_from?->toDateString(), 'valid_to' => $price->valid_to?->toDateString(),
            'comment' => $price->manual_comment, 'input' => $price->pricing_input, 'result' => $price->pricing_result,
        ];
    }

    private function validated(Request $request, bool $saving = false): array
    {
        return $request->validate([
            'id' => ['nullable', 'integer', 'min:1'], 'revision' => [$saving ? 'required' : 'nullable', 'integer', 'min:0'],
            'scope' => ['required', Rule::in(['common', 'entity'])], 'target_id' => [$saving ? 'required' : 'nullable', 'integer', 'min:1'],
            'method' => ['required', Rule::in(['manual', 'markup', 'absolute', 'margin', 'saved'])],
            'calculation_id' => ['required_if:method,saved', 'nullable', 'integer', 'min:1'],
            'source_key' => ['required_unless:method,manual,saved', 'nullable', 'string', 'max:80'],
            'currency_id' => ['required', 'integer', 'exists:currencies,id'],
            'amount' => ['required_if:method,manual', 'nullable', 'numeric', 'gt:0', 'max:999999999999', 'decimal:0,4'],
            'amount_includes_vat' => ['required', 'boolean'], 'source_includes_vat' => ['required', 'boolean'],
            'source_vat_rate' => ['required', 'numeric', 'between:0,100', 'decimal:0,4'],
            'vat_rate' => ['required', 'numeric', 'between:0,100', 'decimal:0,4'],
            'parameter' => ['nullable', 'numeric', 'between:0,100000000', 'decimal:0,4'],
            'rounding_step' => ['nullable', 'numeric', 'between:0,1000000', 'decimal:0,4'],
            'active' => ['required', 'boolean'], 'comment' => ['nullable', 'string', 'max:2000'],
            'valid_from' => ['nullable', 'date_format:Y-m-d'], 'valid_to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('valid_from') ? ['after_or_equal:valid_from'] : [])],
            'measurement' => ['required', 'array:measure_id,unit_label,kilograms_per_unit'],
            'measurement.measure_id' => ['required', 'integer'], 'measurement.unit_label' => ['nullable', 'string', 'max:255'],
            'measurement.kilograms_per_unit' => ['nullable', 'numeric', 'gt:0'],
        ]);
    }
}
