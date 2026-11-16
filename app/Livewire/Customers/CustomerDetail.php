<?php

namespace App\Livewire\Customers;

use App\Models\Customer;
use App\Services\CustomerService;
use Livewire\Attributes\On;
use Livewire\Component;

class CustomerDetail extends Component
{
    public ?Customer $customer = null;

    public string $search = '';

    public array $expandedSales = [];

    public function render(CustomerService $customerService)
    {
        $metrics = [];
        $sales = collect();

        if ($this->customer) {
            $data = $customerService->getCustomerTransactionsData($this->customer, $this->search);
            $metrics = $data['metrics'];
            $sales = $data['sales'];
        }

        return view('livewire.customers.customer-detail', [
            'metrics' => $metrics,
            'sales' => $sales,
        ]);
    }

    #[On('show-customer')]
    public function show(Customer $customer, CustomerService $customerService): void
    {
        $this->customer = $customer;
        $this->search = '';
        $data = $customerService->getCustomerTransactionsData($this->customer);
        $firstSale = $data['sales']->first();
        $this->expandedSales = $firstSale ? [$firstSale['id']] : [];
        $this->dispatch('open-modal', name: 'customer-detail-modal');
    }

    public function toggleSaleAccordion(int $saleId): void
    {
        if (in_array($saleId, $this->expandedSales, true)) {
            $this->expandedSales = array_values(array_filter($this->expandedSales, fn ($id) => $id !== $saleId));
        } else {
            $this->expandedSales[] = $saleId;
        }
    }

    public function expandAllSales(array $saleIds): void
    {
        $this->expandedSales = $saleIds;
    }

    public function collapseAllSales(): void
    {
        $this->expandedSales = [];
    }

    public function edit(): void
    {
        if ($this->customer) {
            $this->dispatch('close-modal', name: 'customer-detail-modal');
            $this->dispatch('edit-customer', ['customer' => $this->customer->id]);
        }
    }
}
