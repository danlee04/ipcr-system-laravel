<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * The period / division / section the dashboard is currently looking at.
 *
 * Passed to every DashboardStats method so the same code answers "the whole
 * hospital" and "Nursing this semester" without a second query path.
 */
readonly class DashboardScope
{
    public function __construct(
        public ?int $periodId = null,
        public ?int $divisionId = null,
        public ?int $sectionId = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            periodId: self::id($request, 'filter_period_id'),
            divisionId: self::id($request, 'filter_division_id'),
            sectionId: self::id($request, 'filter_section_id'),
        );
    }

    public function isFiltered(): bool
    {
        return $this->periodId !== null
            || $this->divisionId !== null
            || $this->sectionId !== null;
    }

    /** The current filters as query parameters, empty ones dropped. */
    public function toQuery(array $overrides = []): array
    {
        return array_filter(array_merge([
            'filter_period_id'   => $this->periodId,
            'filter_division_id' => $this->divisionId,
            'filter_section_id'  => $this->sectionId,
        ], $overrides), fn ($value): bool => $value !== null && $value !== '');
    }

    private static function id(Request $request, string $key): ?int
    {
        $value = $request->query($key);

        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }
}
