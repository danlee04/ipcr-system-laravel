<?php

namespace App\Support;

/**
 * What is standing in the way of deleting one organizational record.
 *
 * `blockers` maps a human label to a count, e.g. ['sections' => 3]. An empty
 * map means nothing references the record and it can be deleted.
 */
final readonly class DeletionReport
{
    /** @param array<string,int> $blockers */
    public function __construct(
        public bool $deletable,
        public array $blockers = [],
    ) {}

    /** The sentence shown to the administrator when deletion is refused. */
    public function message(): string
    {
        if ($this->deletable) {
            return 'Nothing references this record.';
        }

        $parts = [];

        foreach ($this->blockers as $label => $count) {
            $parts[] = $count . ' ' . ($count === 1 ? rtrim($label, 's') : $label);
        }

        $last = array_pop($parts);
        $list = $parts === [] ? $last : implode(', ', $parts) . ' and ' . $last;

        return "Cannot delete — {$list} reference this. Deactivate it instead.";
    }
}
