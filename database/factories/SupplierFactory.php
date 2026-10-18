<?php

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Crypt;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $companyName = fake()->company();
        $accountNumber = fake()->numerify('###########');
        $city = fake()->city();
        $state = fake()->state();
        $country = 'India';

        return [
            'name' => $companyName,
            'legal_name' => $companyName.' Pvt Ltd',
            'trade_name' => fake()->optional(0.7)->company(),
            'supplier_type' => fake()->randomElement(['Distributor', 'Manufacturer', 'Wholesaler', 'Importer']),
            'registration_number' => strtoupper(fake()->bothify('REG-####-???')),
            'tax_id' => strtoupper(fake()->bothify('##?????####?1Z?')),
            'website' => fake()->url(),
            'industry' => fake()->randomElement(['Cosmetics', 'Personal Care', 'Skincare', 'Beauty Products', 'Wellness']),
            'contact_person' => fake()->name(),
            'email' => fake()->unique()->companyEmail(),
            'accounts_email' => fake()->unique()->safeEmail(),
            'purchase_email' => fake()->unique()->safeEmail(),
            'phone' => '+91 '.fake()->numberBetween(60000, 99999).' '.fake()->numberBetween(10000, 99999),
            'alternate_phone' => fake()->optional(0.6)->randomElement(['+91 '.fake()->numberBetween(60000, 99999).' '.fake()->numberBetween(10000, 99999)]),
            'address' => fake()->address(),
            'address_line_1' => fake()->streetAddress(),
            'address_line_2' => fake()->optional(0.5)->secondaryAddress(),
            'city' => $city,
            'state' => $state,
            'postal_code' => fake()->postcode(),
            'country' => $country,
            'bank_name' => fake()->randomElement(['HDFC Bank', 'ICICI Bank', 'State Bank of India', 'Axis Bank', 'Kotak Mahindra Bank']),
            'bank_branch' => $city.' Main Branch',
            'account_name' => $companyName,
            'account_number_encrypted' => Crypt::encryptString($accountNumber),
            'account_number_last4' => substr($accountNumber, -4),
            'account_type' => fake()->randomElement(['Current', 'Savings']),
            'ifsc_code' => strtoupper(fake()->bothify('????0######')),
            'swift_bic' => strtoupper(fake()->bothify('???????????')),
            'beneficiary_name' => $companyName,
            'bank_country' => $country,
            'status' => fake()->randomElement(['active', 'active', 'active', 'inactive']),
            'notes' => fake()->optional(0.5)->sentence(),
        ];
    }
}
