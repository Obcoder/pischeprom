<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\BuildingType;
use App\Models\City;
use App\Models\Country;
use App\Models\Entity;
use App\Models\EntityClassification;
use App\Models\MailMessage;
use App\Models\Unit;
use App\Services\Mail\MailMessageCrmService;
use App\Services\Mail\MailWorkspaceAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MailMessageCrmController extends Controller
{
    public function __construct(private readonly MailWorkspaceAccess $access, private readonly MailMessageCrmService $crm) {}

    public function options(Request $request): JsonResponse
    {
        $this->access->authorize($request->user());

        return response()->json([
            'building_types' => BuildingType::query()->orderBy('name')->get(['id', 'name']),
            'countries' => Country::query()->orderBy('name')->get(['id', 'name']),
            'entity_classifications' => EntityClassification::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function entities(Request $request): JsonResponse
    {
        $search = $this->search($request);
        $items = Entity::query()->withoutEagerLoads()->when($search !== '', fn ($query) => $query
            ->where(fn ($query) => $query->where('name', 'like', '%'.$search.'%')
                ->orWhere('full_name', 'like', '%'.$search.'%')->orWhere('INN', 'like', '%'.$search.'%')))
            ->orderBy('name')->limit(30)->get(['id', 'name', 'full_name', 'INN']);

        return response()->json(['items' => $items]);
    }

    public function units(Request $request): JsonResponse
    {
        $search = $this->search($request);

        return response()->json(['items' => Unit::query()->withoutEagerLoads()
            ->when($search !== '', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
            ->orderBy('name')->limit(30)->get(['id', 'name'])]);
    }

    public function cities(Request $request): JsonResponse
    {
        $search = $this->search($request);
        $cities = City::query()->withoutEagerLoads()->with('region:id,name')
            ->when($search !== '', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
            ->orderBy('name')->limit(30)->get(['id', 'name', 'region_id']);

        return response()->json(['items' => $cities->map(fn (City $city) => [
            'id' => $city->id, 'name' => $city->name,
            'label' => collect([$city->name, $city->region?->name])->filter()->implode(' · '),
        ])]);
    }

    public function show(Request $request, MailMessage $mailMessage): JsonResponse
    {
        $this->access->authorize($request->user());

        return response()->json($this->crm->context($mailMessage));
    }

    public function storeEntity(Request $request, MailMessage $mailMessage): JsonResponse
    {
        return $this->save($request, $mailMessage, 'entity', [
            ...$this->targetRules(),
            'name' => ['required_without:entity_id', 'nullable', 'string', 'max:255'],
            'full_name' => ['nullable', 'string', 'max:1024'],
            'INN' => ['nullable', 'string', 'max:32'],
            'KPP' => ['nullable', 'string', 'max:32'],
            'OGRN' => ['nullable', 'string', 'max:32'],
            'legal_address' => ['nullable', 'string', 'max:1024'],
            'country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'entity_classification_id' => ['nullable', 'integer', 'exists:entity_classifications,id'],
            'email_address' => ['nullable', 'string', 'email:rfc', 'max:254'],
            'dadata_raw' => ['nullable', 'array', function ($attribute, $value, $fail): void {
                if (strlen(json_encode($value, JSON_THROW_ON_ERROR)) > 100000) {
                    $fail('Ответ справочника юридических лиц слишком большой.');
                }
            }],
        ]);
    }

    public function storeUnit(Request $request, MailMessage $mailMessage): JsonResponse
    {
        return $this->save($request, $mailMessage, 'unit', [
            ...$this->targetRules(),
            'name' => ['required_without:unit_id', 'nullable', 'string', 'max:255'],
            'is_customer' => ['sometimes', 'boolean'],
            'is_supplier' => ['sometimes', 'boolean'],
            'email_address' => ['nullable', 'string', 'email:rfc', 'max:254'],
        ]);
    }

    public function storeTelephone(Request $request, MailMessage $mailMessage): JsonResponse
    {
        return $this->save($request, $mailMessage, 'telephone', [
            ...$this->targetRules(), 'number' => ['required', 'string', 'max:40'],
        ]);
    }

    public function storeEmail(Request $request, MailMessage $mailMessage): JsonResponse
    {
        return $this->save($request, $mailMessage, 'email', [
            'entity_id' => ['required_without:unit_id', 'nullable', 'integer', 'exists:entities,id'],
            'unit_id' => ['required_without:entity_id', 'nullable', 'integer', 'exists:units,id'],
            'address' => ['nullable', 'string', 'email:rfc', 'max:254'],
        ]);
    }

    public function storeWebsite(Request $request, MailMessage $mailMessage): JsonResponse
    {
        return $this->save($request, $mailMessage, 'website', [
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'address' => ['required', 'string', 'max:255'],
        ]);
    }

    public function storeBuilding(Request $request, MailMessage $mailMessage): JsonResponse
    {
        return $this->save($request, $mailMessage, 'building', [
            'entity_id' => ['required_without:unit_id', 'nullable', 'integer', 'exists:entities,id'],
            'unit_id' => ['required_without:entity_id', 'nullable', 'integer', 'exists:units,id'],
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'building_type_id' => ['nullable', 'integer', 'exists:building_types,id'],
            'address' => ['required', 'string', 'max:255'],
            'postcode' => ['nullable', 'string', 'max:32'],
        ]);
    }

    private function save(Request $request, MailMessage $message, string $kind, array $rules): JsonResponse
    {
        $this->access->authorize($request->user());
        $result = $this->crm->save($message, $request->user(), $kind, $request->validate($rules));

        return response()->json($result, $result['created'] ? 201 : 200);
    }

    private function search(Request $request): string
    {
        $this->access->authorize($request->user());
        $data = $request->validate(['search' => ['nullable', 'string', 'max:200']]);

        return trim((string) ($data['search'] ?? ''));
    }

    private function targetRules(): array
    {
        return [
            'entity_id' => ['nullable', 'integer', 'exists:entities,id'],
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
        ];
    }
}
