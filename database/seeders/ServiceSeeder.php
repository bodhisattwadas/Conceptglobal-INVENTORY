<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $services = [
            // Facials & Skin Care
            [
                'name' => 'Hydra Glow Facial',
                'code' => 'SRV-FAC-01',
                'cost' => 1500.00,
                'description' => 'Deep pore cleansing, ultrasonic hydration, and instant brightening mask.',
                'is_active' => true,
            ],
            [
                'name' => 'Gold Radiance Facial',
                'code' => 'SRV-FAC-02',
                'cost' => 1800.00,
                'description' => '24K gold dust massage cream with collagen boost for radiant skin.',
                'is_active' => true,
            ],
            [
                'name' => 'Fruit Refresh Facial',
                'code' => 'SRV-FAC-03',
                'cost' => 850.00,
                'description' => 'Organic seasonal fruit extract facial suitable for all sensitive skin types.',
                'is_active' => true,
            ],
            [
                'name' => 'O3+ Bridal Glow Facial',
                'code' => 'SRV-FAC-04',
                'cost' => 2500.00,
                'description' => 'Premium dermatological facial for bridal pre-glow and deep hyperpigmentation care.',
                'is_active' => true,
            ],
            [
                'name' => 'O3+ D-Tan Pack & Clean Up',
                'code' => 'SRV-DTAN-01',
                'cost' => 600.00,
                'description' => 'Intensive anti-tan exfoliation and soothing eucalyptus mask.',
                'is_active' => true,
            ],

            // Eyebrows & Threading
            [
                'name' => 'Eyebrow Shaping & Threading',
                'code' => 'SRV-THR-01',
                'cost' => 80.00,
                'description' => 'Precision eyebrow hair removal and gentle shape contouring.',
                'is_active' => true,
            ],
            [
                'name' => 'Upper Lip Threading',
                'code' => 'SRV-THR-02',
                'cost' => 50.00,
                'description' => 'Quick and clean threading for upper lip area.',
                'is_active' => true,
            ],
            [
                'name' => 'Full Face Threading',
                'code' => 'SRV-THR-03',
                'cost' => 250.00,
                'description' => 'Includes eyebrows, upper lip, chin, sideburns, and forehead.',
                'is_active' => true,
            ],

            // Hair Care & Styling
            [
                'name' => 'Classic Haircut & Blow Dry',
                'code' => 'SRV-HAIR-01',
                'cost' => 450.00,
                'description' => 'Consultation, wash, customized haircut, and professional blow dry styling.',
                'is_active' => true,
            ],
            [
                'name' => 'L\'Oreal Intensive Hair Spa',
                'code' => 'SRV-HAIR-02',
                'cost' => 1200.00,
                'description' => 'Deep nourishing massage cream with scalp detox and ozone steam.',
                'is_active' => true,
            ],
            [
                'name' => 'Keratin Smooth Treatment',
                'code' => 'SRV-HAIR-03',
                'cost' => 4000.00,
                'description' => 'Frizz-free protein infusion smoothing therapy lasting up to 4 months.',
                'is_active' => true,
            ],
            [
                'name' => 'Global Hair Coloring',
                'code' => 'SRV-HAIR-04',
                'cost' => 2800.00,
                'description' => 'Full root-to-tip professional ammonia-free hair color application.',
                'is_active' => true,
            ],
            [
                'name' => 'Root Touch Up (Color)',
                'code' => 'SRV-HAIR-05',
                'cost' => 950.00,
                'description' => 'Grey coverage root touch-up with gloss wash.',
                'is_active' => true,
            ],

            // Hands, Feet & Nails
            [
                'name' => 'Classic Spa Pedicure',
                'code' => 'SRV-NAIL-01',
                'cost' => 650.00,
                'description' => 'Foot soak, exfoliating scrub, cuticle care, heel buffing, and polish.',
                'is_active' => true,
            ],
            [
                'name' => 'Classic Spa Manicure',
                'code' => 'SRV-NAIL-02',
                'cost' => 500.00,
                'description' => 'Hand massage, nail shaping, cuticle nourishment, and glossy polish.',
                'is_active' => true,
            ],
            [
                'name' => 'Gel Nail Polish Application',
                'code' => 'SRV-NAIL-03',
                'cost' => 700.00,
                'description' => 'Long-lasting chip-resistant UV gel polish for hands.',
                'is_active' => true,
            ],

            // Waxing
            [
                'name' => 'Rica Waxing - Full Arms',
                'code' => 'SRV-WAX-01',
                'cost' => 450.00,
                'description' => 'Colophony-free Italian liposoluble wax with soothing lotion.',
                'is_active' => true,
            ],
            [
                'name' => 'Rica Waxing - Full Legs',
                'code' => 'SRV-WAX-02',
                'cost' => 750.00,
                'description' => 'Gentle painless liposoluble wax for smooth silky legs.',
                'is_active' => true,
            ],
            [
                'name' => 'Underarms Waxing',
                'code' => 'SRV-WAX-03',
                'cost' => 150.00,
                'description' => 'Quick and hygienic underarm waxing.',
                'is_active' => true,
            ],

            // Makeup & Bridal
            [
                'name' => 'HD Party Makeup',
                'code' => 'SRV-MKP-01',
                'cost' => 2200.00,
                'description' => 'High-definition photo-ready makeup including basic hairstyling and lashes.',
                'is_active' => true,
            ],
        ];

        foreach ($services as $service) {
            Service::updateOrCreate(
                ['code' => $service['code']],
                $service
            );
        }
    }
}
