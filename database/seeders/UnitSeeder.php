<?php

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    public function run(): void
    {
        $units = [
            ['name' => 'Piece', 'symbol' => 'pcs'],
            ['name' => 'Bottle', 'symbol' => 'btl'],
            ['name' => 'Tube', 'symbol' => 'tube'],
            ['name' => 'Jar', 'symbol' => 'jar'],
            ['name' => 'Palette', 'symbol' => 'plt'],
            ['name' => 'Box', 'symbol' => 'box'],
            ['name' => 'Set', 'symbol' => 'set'],
            ['name' => 'Sachet', 'symbol' => 'sct'],
        ];

        foreach ($units as $unit) {
            Unit::firstOrCreate(
                ['symbol' => $unit['symbol']],
                ['name' => $unit['name']]
            );
        }
    }
}
