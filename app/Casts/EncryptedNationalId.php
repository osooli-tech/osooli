<?php

declare(strict_types=1);

namespace App\Casts;

use App\Support\NationalId;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Reads owners.national_id decrypted and writes it encrypted, keeping the
 * fingerprint column in step on every write so the two can never drift.
 *
 * @implements CastsAttributes<string|null, string|null>
 */
class EncryptedNationalId implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return NationalId::decrypt($value === null ? null : (string) $value);
    }

    /** @return array<string, string|null> */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        $columns = NationalId::columns($value === null ? null : (string) $value);

        return [$key => $columns['national_id'], $key.'_hash' => $columns['national_id_hash']];
    }
}
