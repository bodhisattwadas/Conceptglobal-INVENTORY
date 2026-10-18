<?php

namespace Tests\Feature;

use App\Livewire\Profile\EditProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Livewire::test(EditProfile::class)
            ->set('name', 'Test User')
            ->set('username', 'testuser')
            ->set('email', 'test@example.com')
            ->call('updateProfile')
            ->assertHasNoErrors();

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('testuser', $user->username);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Livewire::test(EditProfile::class)
            ->set('name', 'Test User')
            ->set('username', $user->username)
            ->set('email', $user->email)
            ->call('updateProfile')
            ->assertHasNoErrors();

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_username_must_be_unique_when_profile_is_updated(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->actingAs($user);

        Livewire::test(EditProfile::class)
            ->set('name', 'Test User')
            ->set('username', $otherUser->username)
            ->set('email', $user->email)
            ->call('updateProfile')
            ->assertHasErrors(['username']);

        $this->assertNotSame($otherUser->username, $user->refresh()->username);
    }

    public function test_email_must_be_unique_when_profile_is_updated(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->actingAs($user);

        Livewire::test(EditProfile::class)
            ->set('name', 'Test User')
            ->set('username', $user->username)
            ->set('email', $otherUser->email)
            ->call('updateProfile')
            ->assertHasErrors(['email']);

        $this->assertNotSame($otherUser->email, $user->refresh()->email);
    }
}
