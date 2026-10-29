<x-modal name="service-form-modal" :title="''" maxWidth="xl">
    <div class="p-6">
        <!-- Custom Header -->
        <div class="mb-6 space-y-1.5 text-center sm:text-left border-b border-gray-200 pb-4">
            <h3 class="text-lg font-semibold leading-none tracking-tight text-foreground">
                {{ $isEditing ? 'Edit Service' : 'Add New Service' }}
            </h3>
            <p class="text-sm text-muted-foreground">
                {{ $isEditing ? 'Update the service details and cost.' : 'Add a salon/parlour service with its standard rate/cost for POS checkout.' }}
            </p>
        </div>

        <form wire:submit="save" class="space-y-4">
            <!-- Service Name -->
            <x-form-input
                name="name"
                label="Service Name"
                type="text"
                wire:model="name"
                placeholder="e.g. Facial, Hair Brow, Pedicure, Hair Cut"
                required
                hint="Descriptive name of the service offered to customers."
            />

            <!-- Service Code & Cost / Rate -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-form-input
                    name="code"
                    label="Service Code"
                    type="text"
                    wire:model="code"
                    placeholder="e.g. SRV-0001 (auto if blank)"
                    hint="Optional identifier code."
                />

                <x-form-input
                    name="cost"
                    label="Service Cost / Rate"
                    type="number"
                    step="0.01"
                    min="0"
                    wire:model="cost"
                    placeholder="0.00"
                    required
                    hint="Standard charge/cost for this service in POS."
                />
            </div>

            <!-- Description -->
            <div class="space-y-2">
                <x-input-label for="service_description" :value="__('Description')" hint="Optional details or instructions." />
                <textarea
                    id="service_description"
                    wire:model="description"
                    rows="3"
                    class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                    placeholder="Optional notes about duration, steps, or supplies used..."
                ></textarea>
                <x-input-error :messages="$errors->get('description')" />
            </div>

            <!-- Active Status -->
            <div class="flex items-center pt-1">
                <label class="inline-flex items-center cursor-pointer">
                    <input
                        type="checkbox"
                        wire:model="is_active"
                        class="w-5 h-5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                    >
                    <span class="ml-2.5 text-sm font-medium text-gray-700">
                        {{ __('Active (Available in POS checkout)') }}
                    </span>
                </label>
            </div>

            <!-- Actions -->
            <div class="mt-6 flex justify-end gap-3 border-t border-gray-200 pt-4">
                <x-secondary-button type="button" x-on:click="$dispatch('close-modal', { name: 'service-form-modal' })">
                    {{ __('Cancel') }}
                </x-secondary-button>

                <x-primary-button type="submit" wire:loading.attr="disabled">
                    <svg wire:loading wire:target="save" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <x-heroicon-o-check wire:loading.remove wire:target="save" class="w-4 h-4 mr-2" />
                    {{ $isEditing ? __('Save Changes') : __('Add Service') }}
                </x-primary-button>
            </div>
        </form>
    </div>
</x-modal>
