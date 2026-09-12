<?php

namespace App\Game;

use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LobbyPin
{
    public static function hash(#[\SensitiveParameter] ?string $pin): ?string
    {
        if ($pin === null || $pin === '') {
            return null;
        }
        if (! preg_match('/\A[0-9]{4,8}\z/', $pin)) {
            throw ValidationException::withMessages(['pin' => 'Use a PIN of 4–8 digits.']);
        }

        return Hash::make($pin);
    }

    /** @param array<string, mixed> $state */
    public static function verify(array $state, #[\SensitiveParameter] ?string $pin): void
    {
        if (isset($state['pin_hash']) && ($pin === null || ! Hash::check($pin, $state['pin_hash']))) {
            throw ValidationException::withMessages(['pin' => 'This lobby needs a valid PIN. Ask the host and try again.']);
        }
    }
}
