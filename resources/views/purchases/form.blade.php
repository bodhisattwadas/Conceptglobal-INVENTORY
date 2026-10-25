<div class="space-y-6">
    <div class="grid grid-cols-1 gap-6 rounded-lg border border-gray-200 bg-gray-50 p-4 md:grid-cols-2">
        <div class="space-y-2">
            <x-input-label for="supplier_id" :value="__('Supplier / Vendor')" required hint="Supplier for this purchase order. Example: Beauty World Distributors." />
            <select id="supplier_id" name="supplier_id" x-init="initSupplierSelect($el)" x-model="supplier_id" autocomplete="off">
                <option value=""></option>
                @if(old('supplier_id'))
                    @php($oldSupplier = \App\Models\Supplier::find(old('supplier_id')))
                    @if($oldSupplier)
                        <option value="{{ $oldSupplier->id }}" selected>{{ $oldSupplier->name }}</option>
                    @endif
                @elseif(isset($purchase) && $purchase->supplier)
                    <option value="{{ $purchase->supplier_id }}" selected>{{ $purchase->supplier->name }}</option>
                @endif
            </select>
            <x-input-error :messages="$errors->get('supplier_id')" />
        </div>

        <div class="space-y-2">
            <x-input-label for="invoice_number_display" :value="__('PO Reference')" hint="System-generated purchase order reference. Example: PO-20260814-0001." />
            <x-text-input
                id="invoice_number_display"
                x-bind:value="po_reference || '{{ $purchase->invoice_number ?? __('Auto Generated') }}'"
                readonly
                class="bg-muted text-muted-foreground cursor-not-allowed"
            />
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="space-y-2">
                <x-input-label for="purchase_date" :value="__('PO Date')" required hint="Date the purchase order is created. Example: 2026-08-14." />
                <x-text-input id="purchase_date" type="date" name="purchase_date" x-model="purchase_date" @change="handlePurchaseDateChange" />
                <x-input-error :messages="$errors->get('purchase_date')" />
            </div>
            <div class="space-y-2">
                <x-input-label for="due_date" :value="__('Expected Delivery')" hint="Expected date for receiving goods. Example: 2026-08-20." />
                <x-text-input id="due_date" type="date" name="due_date" x-model="due_date" x-bind:min="minDueDate" />
                <x-input-error :messages="$errors->get('due_date')" />
            </div>
        </div>

        <div class="md:col-span-2 space-y-2">
            <x-input-label for="notes" :value="__('PO Notes / Terms')" hint="Purchase terms or delivery instructions. Example: Deliver before 5 PM." />
            <textarea id="notes" name="notes" rows="2" class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2" placeholder="Terms, delivery instructions, or vendor notes...">{{ old('notes', $purchase->notes ?? '') }}</textarea>
            <x-input-error :messages="$errors->get('notes')" />
        </div>
    </div>

    <div class="space-y-4">
        <input type="hidden" name="company_id" :value="headerCompanyId">
        <x-input-error :messages="$errors->get('company_id')" />

        <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow">
            <div class="overflow-auto" style="max-height: 600px;">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="w-[240px] min-w-[220px] px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Brand</th>
                            <th class="min-w-[340px] px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Product</th>
                            <th class="w-16 px-2 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500 whitespace-nowrap">Qty</th>
                            <th class="w-28 px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 whitespace-nowrap">MRP</th>
                            <th class="w-32 px-4 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500 whitespace-nowrap">MRP Discount %</th>
                            <th class="w-28 px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 whitespace-nowrap">Subtotal</th>
                            <th class="w-14 px-2 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500 whitespace-nowrap"><span class="sr-only">Action</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <template x-for="(item, index) in items" :key="item.key">
                            <tr>
                                <td class="w-[240px] min-w-[220px] px-4 py-3 align-top">
                                    <select
                                        :id="`company_id_${item.key}`"
                                        x-init="initRowBrandSelect($el, item)"
                                        autocomplete="off"
                                    ></select>
                                    <input type="hidden" :name="item.product_id ? `items[${index}][company_id]` : null" :value="item.company_id">
                                </td>
                                <td class="min-w-[340px] px-4 py-3 align-top">
                                    <select
                                        :id="`product_search_${item.key}`"
                                        x-init="initRowProductSelect($el, item)"
                                        autocomplete="off"
                                    ></select>
                                    <input type="hidden" :name="item.product_id ? `items[${index}][product_id]` : null" :value="item.product_id">
                                    <div x-show="item.product_code" class="mt-1">
                                        <span class="inline-flex items-center rounded bg-emerald-50 px-2 py-0.5 text-xs font-medium font-mono text-emerald-700 border border-emerald-200" x-text="item.product_code"></span>
                                    </div>
                                </td>
                                <td class="w-16 px-2 py-3 text-center align-top whitespace-nowrap">
                                    <input type="number" :name="item.product_id ? `items[${index}][quantity]` : null" x-model.number="item.quantity" @input="calculateLine(index)" class="w-12 rounded-md border-gray-300 px-1 py-1.5 text-center text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" min="1">
                                </td>
                                <td class="w-28 px-4 py-3 text-right text-sm align-top whitespace-nowrap" x-text="window.formatMoney(item.mrp || 0)"></td>
                                <td class="w-32 px-4 py-3 text-center align-top whitespace-nowrap">
                                    <div class="inline-flex items-center gap-1">
                                        <input type="number" :name="item.product_id ? `items[${index}][discount_percent]` : null" x-model.number="item.discount_percent" @input="calculateLine(index)" class="w-20 rounded-md border-gray-300 text-right text-sm" min="0" max="100" step="0.01">
                                        <span class="text-sm text-gray-500">%</span>
                                    </div>
                                    <input type="hidden" :name="item.product_id ? `items[${index}][unit_price]` : null" :value="item.unit_price">
                                    <input type="hidden" :name="item.product_id ? `items[${index}][selling_price]` : null" :value="item.mrp || item.unit_price || 0">
                                    <div class="mt-1 text-xs text-gray-500" x-text="'Agreed: ' + window.formatMoney(item.unit_price)"></div>
                                </td>
                                <td class="w-28 px-4 py-3 text-right text-sm font-semibold align-top whitespace-nowrap" x-text="window.formatMoney(item.subtotal)"></td>
                                <td class="w-14 px-2 py-3 text-center align-top whitespace-nowrap">
                                    <button type="button" @click="removeItem(index)" class="inline-flex items-center justify-center rounded-md p-1.5 text-red-600 hover:bg-red-100 hover:text-red-700 focus:outline-none transition-colors" title="{{ __('Remove product') }}">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                        </template>
                        <tr>
                            <td colspan="7" class="px-4 py-3 bg-white">
                                <button
                                    type="button"
                                    @click="addRow()"
                                    class="inline-flex items-center gap-1.5 rounded-md bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-emerald-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 transition-colors"
                                >
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                    </svg>
                                    <span>{{ __('Add Product') }}</span>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                    <tfoot class="bg-gray-50 border-t border-gray-200">
                        <tr>
                            <td colspan="2" class="px-4 py-4 text-right font-bold text-gray-700">Total Qty</td>
                            <td class="w-16 px-2 py-4 text-center font-bold text-base text-gray-900" x-text="totalQuantity"></td>
                            <td colspan="2" class="px-4 py-4 text-right font-bold text-gray-700">Total PO</td>
                            <td class="w-28 px-4 py-4 text-right text-lg font-bold text-blue-600 whitespace-nowrap" x-text="window.formatMoney(total)"></td>
                            <td class="w-14"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <div class="flex items-center justify-end gap-x-4 border-t border-gray-200 pt-6">
        <a href="{{ route('purchases.index') }}" class="inline-flex h-10 items-center justify-center rounded-md border border-input bg-background px-4 py-2 text-sm font-medium hover:bg-accent hover:text-accent-foreground">
            {{ __('Cancel') }}
        </a>

        <x-primary-button class="flex items-center gap-2" ::disabled="loading">
            <span x-text="loading ? 'Processing...' : ({{ isset($purchase->id) ? '`Update Purchase Order`' : '`Create Purchase Order`' }})"></span>
        </x-primary-button>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('purchaseForm', (initialData) => ({
            money(value) {
                return Number((parseFloat(value) || 0).toFixed(2));
            },
            defaultDiscountPercent: 17,
            items: [],
            supplier_id: initialData.supplier_id || '',
            purchase_date: initialData.purchase_date || '{{ old('purchase_date', $purchase->purchase_date ? \Carbon\Carbon::parse($purchase->purchase_date)->format('Y-m-d') : date('Y-m-d')) }}',
            due_date: initialData.due_date || '{{ old('due_date', $purchase->due_date ? \Carbon\Carbon::parse($purchase->due_date)->format('Y-m-d') : '') }}',
            po_reference: initialData.po_reference || '',
            is_editing: initialData.is_editing || false,
            loading: false,
            errors: initialData.errors || {},

            init() {
                const seededItems = (initialData.items || []).map(item => this.normalizeItem(item));
                this.items = seededItems.length ? seededItems : [this.blankItem()];
                this.initScannerListener();
            },

            submitForm(e) {
                if (this.loading) return;
                this.loading = true;
                e.target.submit();
            },

            removeItem(index) {
                this.destroyRowSelects(this.items[index]);
                this.items.splice(index, 1);
                if (this.items.length === 0) {
                    this.addRow();
                }
                this.syncAllProductDropdowns();
            },

            calculateLine(index) {
                const item = this.items[index];
                item.discount_percent = Math.max(0, Math.min(100, parseFloat(item.discount_percent) || 0));
                item.unit_price = this.money((parseFloat(item.mrp) || 0) * (1 - item.discount_percent / 100));
                item.subtotal = this.money((parseInt(item.quantity) || 0) * (parseFloat(item.unit_price) || 0));
            },

            get total() {
                return this.money(this.items.reduce((sum, item) => sum + (parseFloat(item.subtotal) || 0), 0));
            },

            get totalQuantity() {
                return this.items.reduce((sum, item) => sum + (item.product_id ? (parseInt(item.quantity) || 0) : 0), 0);
            },

            get headerCompanyId() {
                const selected = this.items.find(item => item.product_id && item.company_id);
                return selected ? selected.company_id : '';
            },

            get minDueDate() {
                if (!this.purchase_date) return '';

                const date = new Date(`${this.purchase_date}T00:00:00`);
                date.setDate(date.getDate() + 1);

                return date.toISOString().slice(0, 10);
            },

            waitForTomSelect(callback) {
                if (window.TomSelect) callback();
                else setTimeout(() => this.waitForTomSelect(callback), 50);
            },

            initSupplierSelect(el) {
                this.initRemoteSelect(el, '{{ route("ajax.suppliers.search") }}', value => {
                    if (this.supplier_id == value) return;
                    this.supplier_id = value;
                    this.items.forEach(item => this.destroyRowSelects(item));
                    this.items = [this.blankItem()];
                    this.$nextTick(() => {
                        this.items.forEach(item => {
                            const pSelect = document.getElementById(`product_search_${item.key}`)?.tomselect;
                            if (pSelect) this.reloadProductSelect(pSelect, item);
                            const bSelect = document.getElementById(`company_id_${item.key}`)?.tomselect;
                            if (bSelect) {
                                bSelect.enable();
                                bSelect.load('');
                            }
                        });
                    });
                }, 'Select Supplier / Vendor...');
            },

            fetchOptions(url, data, callback) {
                fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify(data)
                }).then(r => r.json()).then(callback).catch(() => callback([]));
            },

            updatePoReference() {
                if (this.is_editing) return;

                if (!this.purchase_date) {
                    this.po_reference = '';
                    return;
                }

                fetch('{{ route("ajax.purchases.preview-reference") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({ purchase_date: this.purchase_date })
                })
                    .then(response => response.json())
                    .then(data => {
                        this.po_reference = data.reference || '';
                    })
                    .catch(() => {
                        this.po_reference = `PO-${this.purchase_date.replaceAll('-', '')}-0001`;
                    });
            },

            handlePurchaseDateChange() {
                if (this.due_date && this.due_date <= this.purchase_date) {
                    this.due_date = '';
                }

                this.updatePoReference();
            },

            initRemoteSelect(el, url, onChange, placeholder) {
                this.waitForTomSelect(() => {
                    new TomSelect(el, {
                        placeholder,
                        preload: 'focus',
                        valueField: 'value',
                        labelField: 'text',
                        searchField: 'text',
                        onChange,
                        load(query, callback) {
                            fetch(url, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                                },
                                body: JSON.stringify({ q: query })
                            }).then(r => r.json()).then(callback).catch(() => callback());
                        }
                    });
                });
            },

            blankItem() {
                return {
                    key: Math.random().toString(36).slice(2),
                    company_id: '',
                    brand: '',
                    product_id: '',
                    product_name: '',
                    product_code: '',
                    mrp: 0,
                    discount_percent: this.defaultDiscountPercent,
                    quantity: 1,
                    unit_price: 0,
                    subtotal: 0
                };
            },

            normalizeItem(item) {
                const mrp = this.money(item.mrp || item.selling_price || 0);
                const unitPrice = this.money(item.unit_price || 0);
                const discount = item.discount_percent ?? (mrp > 0
                    ? Math.max(0, Math.min(100, Number((100 - ((unitPrice || 0) / mrp * 100)).toFixed(4))))
                    : this.defaultDiscountPercent);

                return {
                    ...this.blankItem(),
                    ...item,
                    key: item.key || Math.random().toString(36).slice(2),
                    company_id: item.company_id || item.brand_id || '',
                    mrp,
                    discount_percent: discount,
                    unit_price: unitPrice,
                    subtotal: this.money(item.subtotal || ((parseInt(item.quantity) || 0) * unitPrice))
                };
            },

            focusRowSelect(item) {
                this.$nextTick(() => {
                    const select = document.getElementById(`product_search_${item.key}`)?.tomselect;
                    if (select) {
                        select.focus();
                    } else {
                        setTimeout(() => {
                            document.getElementById(`product_search_${item.key}`)?.tomselect?.focus();
                        }, 80);
                    }
                });
            },

            addRow() {
                const existingBlank = this.items.find(item => !item.product_id);
                if (existingBlank) {
                    this.focusRowSelect(existingBlank);
                    this.$dispatch('toast', { message: 'Please select a product for the existing blank row first.', type: 'warning' });
                    return;
                }
                const newItem = this.blankItem();
                this.items.push(newItem);
                this.focusRowSelect(newItem);
            },

            removeItem(index) {
                const item = this.items[index];
                this.destroyRowSelects(item);
                this.items.splice(index, 1);
                if (this.items.length === 0) {
                    const newItem = this.blankItem();
                    this.items.push(newItem);
                    this.focusRowSelect(newItem);
                }
                this.syncAllProductDropdowns();
            },

            destroyRowSelects(item) {
                if (!item) return;
                document.getElementById(`company_id_${item.key}`)?.tomselect?.destroy();
                document.getElementById(`product_search_${item.key}`)?.tomselect?.destroy();
            },

            clearRowProduct(item) {
                item.product_id = '';
                item.product_name = '';
                item.product_code = '';
                item.mrp = 0;
                item.unit_price = 0;
                item.subtotal = 0;
                this.syncAllProductDropdowns(item);
            },

            setRowProduct(item, product) {
                const mrp = this.money(product.mrp || product.price || 0);
                const discount = this.defaultDiscountPercent;
                const unitPrice = this.money(mrp * (1 - discount / 100));

                item.product_id = product.value || product.id;
                item.product_name = product.text || product.name;
                item.product_code = product.sku || '';
                item.brand = product.brand || item.brand;
                item.company_id = product.brand_id || item.company_id;
                item.mrp = mrp;
                item.discount_percent = discount;
                item.quantity = item.quantity && item.quantity > 0 ? item.quantity : 1;
                item.unit_price = unitPrice;
                item.subtotal = this.money((parseInt(item.quantity) || 0) * unitPrice);

                if (product.brand_id && product.brand) {
                    const brandSelect = document.getElementById(`company_id_${item.key}`)?.tomselect;
                    if (brandSelect && String(brandSelect.getValue()) !== String(product.brand_id)) {
                        brandSelect.addOption({ value: product.brand_id, text: product.brand });
                        brandSelect.setValue(product.brand_id, true);
                    }
                }

                this.syncAllProductDropdowns(item);
            },

            selectedProductIds(exceptItem = null) {
                const exceptKey = exceptItem?.key;
                return this.items
                    .filter(row => row.key !== exceptKey)
                    .map(row => String(row.product_id || ''))
                    .filter(Boolean);
            },

            filterAvailableProducts(products, item) {
                const selectedIds = this.selectedProductIds(item);
                return (Array.isArray(products) ? products : []).filter(product => {
                    const id = String(product.value ?? product.id ?? '');
                    return id && !selectedIds.includes(id);
                });
            },

            syncAllProductDropdowns(exceptItem = null) {
                const exceptKey = exceptItem?.key;
                this.items.forEach(row => {
                    if (row.key === exceptKey) return;

                    const select = document.getElementById(`product_search_${row.key}`)?.tomselect;
                    if (!select) return;

                    const forbiddenIds = this.selectedProductIds(row);
                    let changed = false;

                    forbiddenIds.forEach(id => {
                        if (select.options[id] && String(select.getValue()) !== String(id)) {
                            select.removeOption(id);
                            changed = true;
                        }
                    });

                    if (changed) {
                        select.refreshOptions(false);
                    }

                    if (typeof select.clearCache === 'function') {
                        select.clearCache();
                    }
                    select.loadedSearches = {};
                });
            },

            reloadProductSelect(select, item, autoFocus = false) {
                if (!select) return;

                if (typeof select.clearCache === 'function') {
                    select.clearCache();
                }

                select.clearOptions();
                select.loadedSearches = {};
                select.wrapper?.classList.remove('preloaded');

                if (item.product_id && item.product_name) {
                    select.addOption({
                        value: item.product_id,
                        id: item.product_id,
                        text: item.product_name,
                        name: item.product_name,
                        sku: item.product_code,
                        mrp: item.mrp,
                        price: item.mrp,
                        brand: item.brand,
                        brand_id: item.company_id
                    });
                    select.setValue(item.product_id, true);
                }

                if (!this.supplier_id) {
                    select.disable();
                    select.settings.placeholder = 'Select vendor first...';
                    const placeholderEl = select.control?.querySelector('.items-placeholder');
                    if (placeholderEl) placeholderEl.placeholder = select.settings.placeholder;
                    select.inputState();
                    return;
                }

                select.enable();
                select.settings.placeholder = item.company_id
                    ? 'Search products for selected brand...'
                    : 'Search product or scan barcode...';
                const placeholderEl = select.control?.querySelector('.items-placeholder');
                if (placeholderEl) placeholderEl.placeholder = select.settings.placeholder;
                select.inputState();

                // Fetch and populate available products immediately
                this.fetchOptions('{{ route("ajax.products.search") }}', {
                    q: '',
                    supplier_id: this.supplier_id || undefined,
                    company_id: item.company_id || undefined,
                    for_purchase: true
                }, (products) => {
                    const available = this.filterAvailableProducts(products, item);
                    select.clearOptions();
                    select.loadedSearches[''] = true;

                    if (item.product_id && item.product_name) {
                        select.addOption({
                            value: item.product_id,
                            id: item.product_id,
                            text: item.product_name,
                            name: item.product_name,
                            sku: item.product_code,
                            mrp: item.mrp,
                            price: item.mrp,
                            brand: item.brand,
                            brand_id: item.company_id
                        });
                        select.setValue(item.product_id, true);
                    }

                    available.forEach(p => {
                        select.addOption(p);
                    });

                    select.refreshOptions(false);

                    if (autoFocus) {
                        this.$nextTick(() => {
                            select.focus();
                            select.open();
                        });
                    }
                });
            },

            reloadRowProductSelect(item, autoFocus = false) {
                const reload = () => {
                    const productSelect = document.getElementById(`product_search_${item.key}`)?.tomselect;
                    if (productSelect) {
                        this.reloadProductSelect(productSelect, item, autoFocus);
                    }
                };

                const productSelect = document.getElementById(`product_search_${item.key}`)?.tomselect;
                if (productSelect) {
                    this.reloadProductSelect(productSelect, item, autoFocus);
                } else {
                    setTimeout(reload, 50);
                }
            },

            initRowBrandSelect(el, item) {
                this.waitForTomSelect(() => {
                    const select = new TomSelect(el, {
                        placeholder: this.supplier_id ? 'Select Brand...' : 'Select vendor first...',
                        preload: 'focus',
                        valueField: 'value',
                        labelField: 'text',
                        searchField: 'text',
                        plugins: ['dropdown_input'],
                        load: (query, callback) => {
                            if (!this.supplier_id) return callback([]);
                            this.fetchOptions('{{ route("ajax.companies.search") }}', { q: query, supplier_id: this.supplier_id }, callback);
                        },
                        onChange: value => {
                            if (String(item.company_id || '') === String(value || '')) {
                                return;
                            }
                            item.company_id = value;
                            const option = value ? select.options[value] : null;
                            item.brand = option ? option.text : '';
                            this.clearRowProduct(item);

                            const productSelect = document.getElementById(`product_search_${item.key}`)?.tomselect;
                            productSelect?.clear(true);
                            this.reloadRowProductSelect(item, true);
                        }
                    });

                    if (select.control_input) {
                        select.control_input.placeholder = 'Search brand...';
                    }

                    if (!this.supplier_id) {
                        select.disable();
                    } else {
                        select.load('');
                    }

                    if (item.company_id && item.brand) {
                        select.addOption({ value: item.company_id, text: item.brand });
                        select.setValue(item.company_id, true);
                    }
                });
            },

            handleProductScan(select, item, query) {
                const qLower = query.trim().toLowerCase();
                if (!qLower) return;

                const existingRow = this.items.find(row => 
                    row.key !== item.key && row.product_id && (
                        (row.product_code && row.product_code.toLowerCase() === qLower) ||
                        (row.product_name && row.product_name.toLowerCase() === qLower)
                    )
                );

                if (existingRow) {
                    existingRow.quantity = (parseInt(existingRow.quantity) || 0) + 1;
                    this.calculateLine(this.items.indexOf(existingRow));
                    select.clear(true);
                    select.close();
                    if (select.dropdown_input) select.dropdown_input.value = '';
                    if (select.control_input) select.control_input.value = '';
                    this.$dispatch('toast', {
                        message: `Increased quantity for "${existingRow.product_name}" to ${existingRow.quantity}`,
                        type: 'info'
                    });
                    this.focusRowSelect(item);
                    return;
                }

                const exactMatch = Object.values(select.options).find(opt => 
                    (opt.sku && opt.sku.toLowerCase() === qLower) ||
                    (opt.text && opt.text.toLowerCase() === qLower) ||
                    (opt.name && opt.name.toLowerCase() === qLower)
                );

                if (exactMatch) {
                    select.addOption(exactMatch);
                    select.setValue(exactMatch.value);
                    this.setRowProduct(item, exactMatch);
                    select.close();
                    this.onProductScanSuccess(item);
                    return;
                }

                this.fetchOptions('{{ route("ajax.products.search") }}', {
                    q: query,
                    supplier_id: this.supplier_id || undefined,
                    company_id: item.company_id || undefined,
                    for_purchase: true
                }, (products) => {
                    let list = Array.isArray(products) ? products : [];

                    if (list.length === 0 && item.company_id) {
                        this.fetchOptions('{{ route("ajax.products.search") }}', {
                            q: query,
                            supplier_id: this.supplier_id || undefined,
                            for_purchase: true
                        }, (allProducts) => {
                            this.processScanResults(select, item, Array.isArray(allProducts) ? allProducts : [], query);
                        });
                    } else {
                        this.processScanResults(select, item, list, query);
                    }
                });
            },

            processScanResults(select, item, products, query) {
                const qLower = query.trim().toLowerCase();
                const available = this.filterAvailableProducts(products, item);

                if (available.length === 0) {
                    const existingProduct = products.find(p => 
                        (p.sku && p.sku.toLowerCase() === qLower) ||
                        (p.text && p.text.toLowerCase() === qLower) ||
                        (p.name && p.name.toLowerCase() === qLower)
                    );

                    if (existingProduct) {
                        const existingRow = this.items.find(row => 
                            String(row.product_id) === String(existingProduct.value || existingProduct.id)
                        );
                        if (existingRow) {
                            existingRow.quantity = (parseInt(existingRow.quantity) || 0) + 1;
                            this.calculateLine(this.items.indexOf(existingRow));
                            select.clear(true);
                            select.close();
                            if (select.dropdown_input) select.dropdown_input.value = '';
                            if (select.control_input) select.control_input.value = '';
                            this.$dispatch('toast', {
                                message: `Increased quantity for "${existingRow.product_name}" to ${existingRow.quantity}`,
                                type: 'info'
                            });
                            this.focusRowSelect(item);
                            return;
                        }
                    }

                    this.$dispatch('toast', {
                        message: `No available product found matching "${query}"`,
                        type: 'warning'
                    });
                    return;
                }

                const match = available.find(p => p.sku && p.sku.toLowerCase() === qLower) ||
                              available.find(p => p.sku && p.sku.toLowerCase().startsWith(qLower)) ||
                              available.find(p => (p.text || p.name || '').toLowerCase() === qLower) ||
                              available[0];

                select.addOption(match);
                select.setValue(match.value || match.id);
                this.setRowProduct(item, match);
                select.close();
                this.onProductScanSuccess(item);
            },

            onProductScanSuccess(item) {
                this.$dispatch('toast', { message: `Selected "${item.product_name}"`, type: 'success' });

                this.$nextTick(() => {
                    const emptyRow = this.items.find(row => !row.product_id);
                    if (emptyRow) {
                        this.focusRowSelect(emptyRow);
                    } else {
                        this.addRow();
                    }
                });
            },

            initRowProductSelect(el, item) {
                this.waitForTomSelect(() => {
                    const select = new TomSelect(el, {
                        placeholder: item.company_id
                            ? 'Search products for selected brand...'
                            : (this.supplier_id ? 'Search product or scan barcode...' : 'Select vendor first...'),
                        preload: 'focus',
                        maxOptions: 500,
                        valueField: 'value',
                        labelField: 'text',
                        searchField: ['text', 'name', 'sku'],
                        plugins: ['dropdown_input', 'clear_button'],
                        openOnFocus: true,
                        loadThrottle: 150,
                        load: (query, callback) => {
                            if (!this.supplier_id) return callback([]);
                            this.fetchOptions('{{ route("ajax.products.search") }}', {
                                q: query,
                                supplier_id: this.supplier_id || undefined,
                                company_id: item.company_id || undefined,
                                for_purchase: true
                            }, products => callback(this.filterAvailableProducts(products, item)));
                        },
                        closeAfterSelect: true,
                        onItemAdd: value => {
                            const forbiddenIds = this.selectedProductIds(item);
                            if (forbiddenIds.includes(String(value))) {
                                select.removeItem(value, true);
                                select.removeOption(value);
                                select.refreshOptions(false);
                                this.$dispatch('toast', { message: 'Product already added in another row.', type: 'warning' });
                                return;
                            }
                            const data = select.options[value];
                            if (data) {
                                this.setRowProduct(item, data);
                                this.onProductScanSuccess(item);
                            }
                        },
                        onChange: value => {
                            if (!value) {
                                this.clearRowProduct(item);
                            }
                        },
                        render: {
                            option: (data, escape) => {
                                const brandHtml = data.brand ? `<span class="shrink-0 text-xs text-indigo-600 bg-indigo-50 px-1.5 py-0.5 rounded border border-indigo-100">${escape(data.brand)}</span>` : '';
                                return `<div class="flex items-center justify-between gap-3 w-full py-1.5 px-1">
                                    <div class="min-w-0 pr-2">
                                        <div class="truncate font-medium text-gray-900">${escape(data.text || data.name || '')}</div>
                                        <div class="flex items-center gap-1.5 mt-0.5">
                                            <span class="text-xs font-mono text-gray-500 bg-gray-50 px-1.5 py-0.5 rounded border border-gray-200">${escape(data.sku || '')}</span>
                                            ${brandHtml}
                                        </div>
                                    </div>
                                    <div class="text-right shrink-0">
                                        <div class="text-sm font-semibold text-gray-700">${window.formatMoney(data.mrp || data.price || 0)}</div>
                                    </div>
                                </div>`;
                            },
                            item: (data, escape) => {
                                return `<div class="truncate font-medium text-gray-900">${escape(data.text || data.name || '')}</div>`;
                            },
                            no_results: (data, escape) => {
                                return `<div class="no-results px-3 py-2 text-sm text-gray-500">No available products found</div>`;
                            }
                        }
                    });

                    if (select.control_input) {
                        select.control_input.placeholder = 'Search product name or code...';
                    }

                    const handleScannerKeyDown = (e) => {
                        if (e.key === 'Enter' || e.keyCode === 13) {
                            const input = e.target;
                            const query = (input.value || '').trim();
                            if (!query) return;

                            const qLower = query.toLowerCase();

                            const activeVal = select.activeOption?.dataset?.value;
                            const activeData = activeVal ? select.options[activeVal] : null;
                            const activeMatches = activeData && (
                                (activeData.sku && activeData.sku.toLowerCase() === qLower) ||
                                (activeData.text && activeData.text.toLowerCase() === qLower) ||
                                (activeData.name && activeData.name.toLowerCase() === qLower)
                            );

                            if (!activeMatches) {
                                e.preventDefault();
                                e.stopPropagation();
                                e.stopImmediatePropagation();
                                this.handleProductScan(select, item, query);
                            }
                        }
                    };

                    select.control_input?.addEventListener('keydown', handleScannerKeyDown, true);
                    if (select.dropdown_input) {
                        select.dropdown_input.addEventListener('keydown', handleScannerKeyDown, true);
                    }
                    select.wrapper?.addEventListener('keydown', (e) => {
                        if ((e.key === 'Enter' || e.keyCode === 13) && (e.target?.classList.contains('dropdown-input') || e.target?.classList.contains('ts-input'))) {
                            handleScannerKeyDown(e);
                        }
                    }, true);

                    select.on('dropdown_open', () => {
                        const forbiddenIds = this.selectedProductIds(item);
                        let changed = false;
                        forbiddenIds.forEach(id => {
                            if (select.options[id] && String(select.getValue()) !== String(id)) {
                                select.removeOption(id);
                                changed = true;
                            }
                        });
                        if (changed) {
                            select.refreshOptions(false);
                        }
                        if (this.supplier_id && Object.keys(select.options).length <= (item.product_id ? 1 : 0)) {
                            this.reloadProductSelect(select, item, false);
                        } else {
                            select.refreshOptions(false);
                        }
                    });

                    if (!this.supplier_id) {
                        select.disable();
                    } else {
                        this.reloadProductSelect(select, item);
                    }

                    if (item.product_id && item.product_name) {
                        select.addOption({
                            value: item.product_id,
                            id: item.product_id,
                            text: item.product_name,
                            name: item.product_name,
                            sku: item.product_code,
                            mrp: item.mrp,
                            price: item.mrp,
                            brand: item.brand,
                            brand_id: item.company_id
                        });
                        select.setValue(item.product_id, true);
                    }
                });
            },

            initScannerListener() {
                let scanBuffer = '';
                let lastKeyTime = 0;

                window.addEventListener('keydown', (e) => {
                    const target = e.target;
                    const isFormInput = target && (
                        target.id === 'purchase_date' ||
                        target.id === 'due_date' ||
                        target.id === 'notes' ||
                        target.id === 'supplier_id' ||
                        target.name?.includes('quantity') ||
                        target.name?.includes('discount_percent')
                    );
                    if (isFormInput) return;

                    if (target && (target.classList?.contains('dropdown-input') || target.classList?.contains('ts-input') || target.closest?.('.ts-wrapper'))) {
                        return;
                    }

                    const now = Date.now();
                    const diff = now - lastKeyTime;
                    lastKeyTime = now;

                    if (e.key === 'Enter') {
                        if (scanBuffer.length >= 2) {
                            const barcode = scanBuffer.trim();
                            scanBuffer = '';
                            e.preventDefault();

                            if (!this.supplier_id) {
                                this.$dispatch('toast', { message: 'Please select a vendor first before scanning.', type: 'warning' });
                                return;
                            }

                            let targetItem = this.items.find(row => !row.product_id);
                            if (!targetItem) {
                                targetItem = this.blankItem();
                                this.items.push(targetItem);
                            }

                            this.$nextTick(() => {
                                const select = document.getElementById(`product_search_${targetItem.key}`)?.tomselect;
                                if (select) {
                                    this.handleProductScan(select, targetItem, barcode);
                                }
                            });
                        } else {
                            scanBuffer = '';
                        }
                        return;
                    }

                    if (e.key && e.key.length === 1 && !e.ctrlKey && !e.altKey && !e.metaKey) {
                        if (diff > 80) {
                            scanBuffer = '';
                        }
                        scanBuffer += e.key;
                    }
                });
            }
        }));
    });
</script>
@endpush
