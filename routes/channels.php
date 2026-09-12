<?php

use App\Models\User;
use App\Services\Realtime\AvitoRealtimeAccess;
use App\Services\Realtime\CommerceRealtimeAccess;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('commerce.updates', fn (User $user): bool => app(CommerceRealtimeAccess::class)->allowed($user));
Broadcast::channel('avito.updates', fn (User $user): bool => app(AvitoRealtimeAccess::class)->allowed($user));
