<?php

namespace Database\Seeders;

use App\LoanRequestPersonRole;
use App\Models\LoanRequest;
use App\Models\LoanRequestPerson;
use Illuminate\Database\Seeder;

class LoanRequestPersonSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $loanRequest = LoanRequest::factory()->create();

        LoanRequestPerson::factory()
            ->forLoanRequest($loanRequest)
            ->role(LoanRequestPersonRole::Applicant)
            ->create();
        LoanRequestPerson::factory()
            ->forLoanRequest($loanRequest)
            ->role(LoanRequestPersonRole::CoMakerOne)
            ->create();
        LoanRequestPerson::factory()
            ->forLoanRequest($loanRequest)
            ->role(LoanRequestPersonRole::CoMakerTwo)
            ->create();
    }
}
