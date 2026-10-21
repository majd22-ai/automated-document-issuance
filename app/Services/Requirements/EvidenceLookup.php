<?php

namespace App\Services\Requirements;

final readonly class EvidenceLookup
{
    /** Records are supplied only by a trusted server adapter, never directly from browser input. */
    public function __construct(public bool $available = false, public array $records = []) {}
}
