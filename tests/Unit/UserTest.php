<?php

namespace Tests\Unit;

use App\Models\User;
use Tests\TestCase;

class UserTest extends TestCase
{
    public function test_uses_professional_email(): void
    {
        $user = new User(['email' => 'john@entreprise.com']);
        $this->assertTrue($user->usesProfessionalEmail());
    }

    public function test_uses_non_professional_email(): void
    {
        $user = new User(['email' => 'john@gmail.com']);
        $this->assertFalse($user->usesProfessionalEmail());
    }
}
