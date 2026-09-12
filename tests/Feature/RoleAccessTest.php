<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_user_can_access_sales_routes_but_not_management_routes(): void
    {
        $user = User::factory()->create(['role' => 'sales']);

        $this->actingAs($user)->get(route('sales.index'))->assertOk();
        $this->actingAs($user)->get(route('customers.index'))->assertOk();
        $this->actingAs($user)->get(route('purchases.index'))->assertForbidden();
        $this->actingAs($user)->get(route('inventory.index'))->assertForbidden();
        $this->actingAs($user)->get(route('users.index'))->assertForbidden();
    }

    public function test_manager_can_access_purchasing_and_inventory_but_not_sales_or_admin(): void
    {
        $user = User::factory()->create(['role' => 'manager']);

        $this->actingAs($user)->get(route('purchases.index'))->assertOk();
        $this->actingAs($user)->get(route('inventory.index'))->assertOk();
        $this->actingAs($user)->get(route('products.index'))->assertOk();
        $this->actingAs($user)->get(route('sales.index'))->assertForbidden();
        $this->actingAs($user)->get(route('settings.index'))->assertForbidden();
    }

    public function test_admin_can_access_each_role_area(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)->get(route('sales.index'))->assertOk();
        $this->actingAs($user)->get(route('purchases.index'))->assertOk();
        $this->actingAs($user)->get(route('inventory.index'))->assertOk();
        $this->actingAs($user)->get(route('users.index'))->assertOk();
        $this->actingAs($user)->get(route('settings.index'))->assertOk();
    }

    public function test_legacy_staff_role_has_no_privileged_access(): void
    {
        $user = User::factory()->create(['role' => 'staff']);

        $this->actingAs($user)->get(route('sales.index'))->assertForbidden();
        $this->actingAs($user)->get(route('purchases.index'))->assertForbidden();
        $this->actingAs($user)->get(route('settings.index'))->assertForbidden();
    }
}
