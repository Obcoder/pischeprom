<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Email;
use App\Models\Entity;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class EmailController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        [$sortBy, $sortDesc] = $this->parseSort($request);

        $itemsPerPage = (int) $request->input('itemsPerPage', 25);

        $query = Email::query()
            ->with([
                       'units:id,name',
                       'entities:id,name,entity_classification_id',
                       'entities.classification:id,name',
                   ])
            ->withEmailStats()
            ->search($request->input('search'))
            ->filter($request->input('filters', []))
            ->applySort($sortBy, $sortDesc);

        if ($itemsPerPage === -1) {
            $items = $query->get();

            return response()->json([
                                        'data' => $items,
                                        'total' => $items->count(),
                                    ]);
        }

        $paginator = $query->paginate(
            perPage: max($itemsPerPage, 1),
            page: (int) $request->input('page', 1)
        );

        return response()->json($paginator);
    }

    public function meta(): JsonResponse
    {
        return response()->json([
                                    'units' => Unit::query()
                                        ->without(['fields', 'labels', 'telephones', 'uris'])
                                        ->select('id', 'name')
                                        ->orderBy('name')
                                        ->get(),

                                    'entities' => Entity::query()
                                        ->without(['buildings', 'country'])
                                        ->select('id', 'name', 'entity_classification_id')
                                        ->with('classification:id,name')
                                        ->orderBy('name')
                                        ->get(),

                                    'domains' => Email::query()
                                        ->whereNotNull('domain')
                                        ->distinct()
                                        ->orderBy('domain')
                                        ->pluck('domain'),
                                ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $trashedEmail = Email::withTrashed()
            ->where('address', $data['address'])
            ->first();

        if ($trashedEmail?->trashed()) {
            $trashedEmail->restore();
            $trashedEmail->update($data);

            return response()->json(
                $trashedEmail->fresh()->load(['units:id,name', 'entities:id,name']),
                200
            );
        }

        $email = Email::create($data);

        $email->load(['units:id,name', 'entities:id,name']);
        $email->loadCount(['units', 'entities', 'sendings']);

        return response()->json($email, 201);
    }

    public function show(Email $email): JsonResponse
    {
        $email->load([
                         'units:id,name',
                         'entities:id,name,entity_classification_id',
                         'entities.classification:id,name',
                         'sendings' => fn ($q) => $q->latest()->limit(20),
                     ])->loadCount([
                                       'units',
                                       'entities',
                                       'sendings',
                                       'sentMailMessages as sent_count',
                                       'receivedMailMessages as received_count',
                                   ]);

        return response()->json($email);
    }

    public function update(Request $request, Email $email): JsonResponse
    {
        $data = $this->validated($request, $email);

        $email->update($data);

        $email->load(['units:id,name', 'entities:id,name']);
        $email->loadCount(['units', 'entities', 'sendings']);

        return response()->json($email);
    }

    public function destroy(Email $email): JsonResponse
    {
        $email->delete();

        return response()->json([
                                    'message' => 'Email deleted',
                                ]);
    }

    protected function validated(Request $request, ?Email $email = null): array
    {
        $request->merge([
                            'address' => Str::lower(trim((string) $request->input('address'))),
                            'is_active' => $request->boolean('is_active'),
                        ]);

        return $request->validate([
                                      'address' => [
                                          'required',
                                          'email:rfc',
                                          'max:255',
                                          Rule::unique('emails', 'address')
                                              ->ignore($email?->id)
                                              ->whereNull('deleted_at'),
                                      ],
                                      'name' => ['nullable', 'string', 'max:255'],
                                      'comment' => ['nullable', 'string'],
                                      'source' => ['nullable', 'string', 'max:255'],
                                      'is_active' => ['required', 'boolean'],
                                      'verified_at' => ['nullable', 'date'],
                                      'last_seen_at' => ['nullable', 'date'],
                                  ]);
    }

    protected function parseSort(Request $request): array
    {
        $sort = $request->input('sortBy.0');

        if (!$sort) {
            return [null, false];
        }

        return [
            $sort['key'] ?? null,
            ($sort['order'] ?? 'asc') === 'desc',
        ];
    }
}
