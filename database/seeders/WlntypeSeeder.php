<?php

namespace Database\Seeders;

use App\Models\Wlntype;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class WlntypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! Schema::hasTable('wlntype')) {
            return;
        }

        Wlntype::factory()->count(5)->create();
    }
}
