<?php

namespace App\Http\Controllers\API\AiSales;

use App\Http\Controllers\Controller;
use App\Http\Resources\AiSales\AiAgentDefinitionResource;
use App\Models\AiAgentDefinition;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class AiAgentDefinitionController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', AiAgentDefinition::class);

        return AiAgentDefinitionResource::collection(
            AiAgentDefinition::query()->orderBy('code')->orderBy('version')->limit(100)->get(),
        );
    }
}
