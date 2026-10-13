<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\SupplierProfile;
use App\Models\SupplierProfileEvent;
use App\Models\Unit;
use App\Models\User;
use App\Services\Suppliers\SupplierProfileWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierProfileController extends Controller
{
    public function options(SupplierProfileWriter $writer): JsonResponse
    {
        $owners = User::where('status', 'active')->where(fn ($query) => $query->where('type', 'employee')
            ->orWhereHas('roles', fn ($roles) => $roles->where('guard_name', 'crm')->whereIn('name', ['admin', 'manager'])))
            ->orderBy('name')->get(['id', 'name'])->map(fn ($user) => ['id' => $user->id, 'name' => $user->name]);
        $choices = fn (array $labels) => collect($labels)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values();
        $today = now(SupplierProfileWriter::TIMEZONE)->startOfDay();

        return response()->json([
            'owners' => $owners, 'statuses' => $choices(SupplierProfile::STATUSES), 'priorities' => $choices(SupplierProfile::PRIORITIES),
            'today' => $today->toDateString(), 'default_date_from' => $today->copy()->subDays(89)->toDateString(),
            'default_date_to' => $today->toDateString(),
        ]);
    }

    public function store(Request $request, SupplierProfileWriter $writer): JsonResponse
    {
        $result = $writer->create($request->all(), $request->user());

        return response()->json([
            'unit' => ['id' => $result['unit']->id, 'name' => $result['unit']->name],
            'profile' => $writer->data($result['profile'], $result['unit']->id),
        ], $result['created'] ? 201 : 200);
    }

    public function update(Request $request, Unit $unit, SupplierProfileWriter $writer): JsonResponse
    {
        return response()->json($writer->data($writer->update($unit, $request->all(), $request->user()), $unit->id));
    }

    public function review(Request $request, Unit $unit, SupplierProfileWriter $writer): JsonResponse
    {
        return response()->json($writer->data($writer->review($unit, $request->all(), $request->user()), $unit->id));
    }

    public function note(Request $request, Unit $unit, SupplierProfileWriter $writer): JsonResponse
    {
        return response()->json($writer->data($writer->note($unit, $request->all(), $request->user()), $unit->id));
    }

    public function events(Request $request, Unit $unit): JsonResponse
    {
        $request->validate(['page' => ['nullable', 'integer', 'min:1']]);
        $profile = SupplierProfile::where('unit_id', $unit->id)->first(['id']);
        $events = SupplierProfileEvent::where('supplier_profile_id', $profile?->id ?? 0)->orderByDesc('id')->paginate(30);
        $events->through(fn ($event) => [
            'id' => $event->id, 'type' => $event->type, 'body' => $event->body,
            'actor' => $event->actor_name !== null ? ['id' => $event->actor_id, 'name' => $event->actor_name] : null,
            'created_at' => $event->created_at?->toISOString(), 'changes' => $event->changes,
        ]);

        return response()->json($events);
    }
}
