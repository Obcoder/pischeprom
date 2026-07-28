<?php

namespace App\Http\Controllers\API\Logistics;

use App\Http\Controllers\Controller;
use App\Http\Resources\Logistics\RoutingRunResource;
use App\Models\LogisticsRoutingRun;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RoutingRunController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $this->authorizeLogistics('viewAny', \App\Models\LogisticsCityDistance::class);

        return RoutingRunResource::collection(
            LogisticsRoutingRun::query()->with('initiator:id,name')->latest()->paginate(25)
        );
    }

    public function show(LogisticsRoutingRun $run): RoutingRunResource
    {
        $this->authorizeLogistics('viewAny', \App\Models\LogisticsCityDistance::class);

        return new RoutingRunResource($run->load('initiator:id,name'));
    }
}
