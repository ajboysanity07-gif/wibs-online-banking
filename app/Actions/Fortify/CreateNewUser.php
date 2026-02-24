<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\AppUser;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): AppUser
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        return AppUser::create([
            'username' => $input['username'],
            'email' => $input['email'],
            'acctno' => str_pad((string) (AppUser::max('user_id') + 1), 6, '0', STR_PAD_LEFT),
            'password' => $input['password'],
            'role' => 'client',
            'status' => 'pending',
        ]);
    }
}
