<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\BoostPackage;

class BoostPackageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $packages = [
            [
                'name' => 'Small Boost',
                'description' => 'Get 3 profile boosts to increase your visibility',
                'boost_count' => 3,
                'boost_duration' => 30,
                'platform' => 'both',
                'product_id' => 'dating_app_boost_3',
                'status' => 1,
                'position' => 1
            ],
            [
                'name' => 'Medium Boost',
                'description' => 'Get 5 profile boosts - Most Popular!',
                'boost_count' => 5,
                'boost_duration' => 60,
                'platform' => 'both',
                'product_id' => 'dating_app_boost_5',
                'status' => 1,
                'position' => 2
            ],
            [
                'name' => 'Large Boost',
                'description' => 'Get 10 profile boosts - Best Value!',
                'boost_count' => 10,
                'boost_duration' => 120,
                'platform' => 'both',
                'product_id' => 'dating_app_boost_10',
                'status' => 1,
                'position' => 3
            ]
        ];

        foreach ($packages as $package) {
            BoostPackage::create($package);
        }
    }
}
