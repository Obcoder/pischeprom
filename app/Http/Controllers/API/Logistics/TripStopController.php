<?php

namespace App\Http\Controllers\API\Logistics;

use App\Http\Controllers\Controller;
use App\Http\Resources\Logistics\LogisticsTripResource;
use App\Models\LogisticsTrip;
use App\Models\LogisticsTripStop;
use App\Services\Logistics\TripWriterService;
use Illuminate\Http\Request;

class TripStopController extends Controller
{
    public function __construct(private readonly TripWriterService $writer) {}

    public function move(Request $request, LogisticsTrip $trip, LogisticsTripStop $stop): LogisticsTripResource
    {
        $this->authorizeLogistics('update', $trip);
        $payload = $request->validate(['direction' => ['required', 'string', 'in:up,down']]);

        return new LogisticsTripResource($this->writer->moveStop($trip, $stop, $payload['direction']));
    }
}
