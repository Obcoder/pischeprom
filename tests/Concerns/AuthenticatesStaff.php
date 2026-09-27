<?php

namespace Tests\Concerns;

use App\Models\User;

trait AuthenticatesStaff
{
    /** An explicit session actor for tests with a minimal, isolated schema. */
    protected function actingAsStaff(): static
    {
        return $this->actingAs((new User)->forceFill([
            'id' => 1,
            'name' => 'Test employee',
            'email' => 'employee@example.test',
            'email_verified_at' => now(),
            'type' => 'employee',
            'status' => 'active',
        ]));
    }
}
