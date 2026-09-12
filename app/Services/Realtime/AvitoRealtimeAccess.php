<?php

namespace App\Services\Realtime;

use App\Models\User;
use Throwable;

class AvitoRealtimeAccess
{
    public function canAccess(?User $user): bool
    {
        if (! $user || $user->status !== 'active' || ! $user->hasVerifiedEmail()) {
            return false;
        }

        if ($user->type === 'employee') {
            return true;
        }

        try {
            return $user->hasRole('admin', 'crm');
        } catch (Throwable) {
            return false;
        }
    }

    public function allowed(?User $user): bool
    {
        return (bool) config('realtime.enabled') && $this->canAccess($user);
    }

    public function clientConfig(?User $user): array
    {
        if (! $this->allowed($user) || ! config('broadcasting.connections.reverb.key')) {
            return ['enabled' => false];
        }

        return [
            'enabled' => true,
            'key' => (string) config('broadcasting.connections.reverb.key'),
            'host' => (string) config('realtime.client.host'),
            'port' => (int) config('realtime.client.port'),
            'scheme' => (string) config('realtime.client.scheme'),
            'path' => (string) config('realtime.client.path'),
            'channel' => 'avito.updates',
        ];
    }
}
