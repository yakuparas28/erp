<?php

namespace Modules\Inventory\Services;

/**
 * Delta sync için opak, sunucu üretimli cursor (PRD 3.3): cihazın saati
 * ASLA referans alınmaz. Cursor, en son görülen (updated_at, id) çiftini
 * base64 JSON olarak taşır; aynı saniye içindeki kayıtlar id ile ayrışır.
 */
class SyncCursorService
{
    /**
     * @return array{updated_at: string, id: int}|null
     */
    public function decode(?string $cursor): ?array
    {
        if ($cursor === null || $cursor === '') {
            return null;
        }

        $decoded = json_decode(base64_decode($cursor, true) ?: '', true);

        if (! is_array($decoded) || ! isset($decoded['updated_at'], $decoded['id'])) {
            return null;
        }

        return ['updated_at' => (string) $decoded['updated_at'], 'id' => (int) $decoded['id']];
    }

    public function encode(string $updatedAt, int $id): string
    {
        return base64_encode(json_encode(['updated_at' => $updatedAt, 'id' => $id]));
    }
}
