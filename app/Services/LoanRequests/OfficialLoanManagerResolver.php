<?php

namespace App\Services\LoanRequests;

final class OfficialLoanManagerResolver
{
    private const NAME = 'Annabelle M. Amora';

    private const POSITION = 'Loan Manager';

    public function name(): string
    {
        return self::NAME;
    }

    public function position(): string
    {
        return self::POSITION;
    }

    /**
     * @return array{name: string, position: string}
     */
    public function documentData(): array
    {
        return [
            'name' => $this->name(),
            'position' => $this->position(),
        ];
    }
}
