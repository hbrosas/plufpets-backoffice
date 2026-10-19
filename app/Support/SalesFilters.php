<?php

namespace App\Support;

use Carbon\CarbonInterface;

class SalesFilters
{
    public function __construct(
        public string $preset,
        public CarbonInterface $from,
        public CarbonInterface $to,
        public ?int $branchId,
        public string $search,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'preset' => $this->preset,
            'from' => $this->from->toDateString(),
            'to' => $this->to->toDateString(),
            'branch_id' => $this->branchId,
            'search' => $this->search,
        ];
    }

    public function label(): string
    {
        if ($this->from->toDateString() === $this->to->toDateString()) {
            return $this->from->timezone(config('app.timezone'))->format('M j, Y');
        }

        return $this->from->timezone(config('app.timezone'))->format('M j, Y')
            .' – '
            .$this->to->timezone(config('app.timezone'))->format('M j, Y');
    }
}
