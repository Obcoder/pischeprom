<?php

namespace App\Services\Realtime;

use App\Models\User;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Throwable;

class CommerceRealtimeAccess
{
    public function allowed(?User $user): bool
    {
        if (! config('realtime.enabled') || ! $user || $user->status !== 'active' || ! $user->hasVerifiedEmail()) {
            return false;
        }

        try {
            if ($user->hasRole('admin', 'crm')) {
                return true;
            }

            if ($user->type !== 'employee') {
                return false;
            }

            foreach (['warehouse.view', 'warehouse.move'] as $permission) {
                try {
                    if ($user->hasPermissionTo($permission, 'crm')) {
                        return true;
                    }
                } catch (PermissionDoesNotExist) {
                    // A legacy installation may only have warehouse.move registered.
                }
            }
        } catch (Throwable) {
            // Missing authorization storage must never grant access.
        }

        return false;
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
            'channel' => (string) config('realtime.channel'),
        ];
    }
}
