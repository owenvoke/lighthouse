<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\GlobalId;

/**
 * Encode and decode globally unique IDs.
 */
interface GlobalId
{
    /**
     * Glue together a type and an id to create a global id.
     */
    public function encode(string $type, int|string $id): string;

    /**
     * Split a global id into the type and the id it contains.
     *
     * @return array{0: string, 1: string} A tuple of [$type, $id], e.g. ['User', '123']
     */
    public function decode(string $globalID): array;

    /**
     * Decode the Global ID and get just the ID.
     */
    public function decodeID(string $globalID): string;

    /**
     * Decode the Global ID and get just the type.
     */
    public function decodeType(string $globalID): string;
}
