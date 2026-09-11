<?php

namespace App\Game;

use Illuminate\Validation\ValidationException;

class CharacterCreator
{
    /** @return array{default: array<string, int|string>, options: array<string, list<array{id: string, name: string, unlocked: bool, requirement: string}>>} */
    public function catalog(int $level): array
    {
        $options = [];
        foreach (config('character_creator.options') as $field => $items) {
            $options[$field] = array_map(fn (array $item): array => [
                'id' => $item['id'], 'name' => $item['name'], 'unlocked' => $level >= $item['level'],
                'requirement' => $item['level'] === 1 ? 'Available to every account' : 'Reach level '.$item['level'],
            ], array_values($items));
        }

        return ['default' => config('character_creator.default'), 'options' => $options];
    }

    /** Only known versioned part IDs reach storage or public room snapshots.
     * @return array<string, int|string>
     */
    public function validate(mixed $recipe, int $level): array
    {
        if (! is_array($recipe) || ($recipe['version'] ?? null) !== 1
            || array_diff(array_keys($recipe), array_keys(config('character_creator.default'))) !== []) {
            throw ValidationException::withMessages(['creator' => 'Choose a supported character design.']);
        }
        // Version 1 designs saved before body types and poses retain their
        // original stance. Explicit invalid values still fail validation.
        foreach (['body_type', 'pose'] as $field) {
            if (! array_key_exists($field, $recipe)) {
                $recipe[$field] = config('character_creator.default.'.$field);
            }
        }
        $clean = ['version' => 1];
        foreach ($this->catalog($level)['options'] as $field => $items) {
            $value = $recipe[$field] ?? null;
            $item = is_string($value) ? collect($items)->firstWhere('id', $value) : null;
            if ($item === null || ! $item['unlocked']) {
                throw ValidationException::withMessages(['creator.'.$field => 'Choose an unlocked '.str_replace('_', ' ', $field).'.']);
            }
            $clean[$field] = $value;
        }

        return $clean;
    }

    /** Legacy designs are normalized; malformed recipes fall back to the cast.
     * @return array<string, int|string>|null
     */
    public function saved(mixed $recipe, int $level): ?array
    {
        if ($recipe === null) {
            return null;
        }
        try {
            return $this->validate($recipe, $level);
        } catch (ValidationException) {
            return null;
        }
    }
}
