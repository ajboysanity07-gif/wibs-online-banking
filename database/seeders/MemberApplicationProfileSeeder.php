<?php

namespace Database\Seeders;

use App\Models\MemberApplicationProfile;
use Illuminate\Database\Seeder;

class MemberApplicationProfileSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        MemberApplicationProfile::factory()->count(10)->create();
    }
}
