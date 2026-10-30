<?php

namespace Tests\Feature;

use App\Enums\FinanceCategoryType;
use App\Livewire\FinanceTransactions\FinanceTransactionTable;
use App\Models\FinanceCategory;
use App\Models\FinanceTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FinanceTransactionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_transaction_table_renders_action_buttons_on_paginated_pages(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $category = FinanceCategory::create([
            'name' => 'General Expense',
            'slug' => 'general-expense',
            'type' => FinanceCategoryType::Expense,
            'is_active' => true,
        ]);

        for ($i = 1; $i <= 15; $i++) {
            FinanceTransaction::create([
                'code' => 'TXN-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'finance_category_id' => $category->id,
                'amount' => 50,
                'transaction_date' => now(),
                'created_by' => $user->id,
                'description' => 'Test transaction '.$i,
            ]);
        }

        Livewire::actingAs($user)
            ->withQueryParams(['page' => 2])
            ->test(FinanceTransactionTable::class)
            ->assertSee('title="View Detail"', false)
            ->assertSee('title="Edit Transaction"', false)
            ->assertSee('title="Delete Transaction"', false);

        $response = $this->actingAs($user)->get(route('finance.transactions.index', ['page' => 2]));
        $response->assertOk();
        $response->assertSee('title="View Detail"', false);
        $response->assertSee('title="Edit Transaction"', false);
        $response->assertSee('title="Delete Transaction"', false);
    }
}
