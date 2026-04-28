<?php
declare(strict_types=1);

namespace App\Services;

class Pagination
{
    public int $currentPage;
    public int $perPage;
    public int $total;
    public int $totalPages;
    public int $offset;
    public int $limit;

    public function __construct(int $total, int $perPage = 10, ?int $currentPage = null)
    {
        $this->perPage = max(1, $perPage);
        $this->total = max(0, $total);
        $this->currentPage = max(1, $currentPage ?? 1);
        $this->totalPages = $this->total > 0 ? (int)ceil($this->total / $this->perPage) : 1;

        // Clamp current page to valid range
        if ($this->currentPage > $this->totalPages) {
            $this->currentPage = $this->totalPages;
        }

        $this->offset = ($this->currentPage - 1) * $this->perPage;
        $this->limit = $this->perPage;
    }

    public function hasPrevious(): bool
    {
        return $this->currentPage > 1;
    }

    public function hasNext(): bool
    {
        return $this->currentPage < $this->totalPages;
    }

    public function previousPage(): int
    {
        return max(1, $this->currentPage - 1);
    }

    public function nextPage(): int
    {
        return min($this->totalPages, $this->currentPage + 1);
    }

    /** Return range of page numbers to display (e.g. [1,2,3,4,5] for window around current) */
    public function getPageRange(int $window = 5): array
    {
        $half = (int)floor($window / 2);
        $start = max(1, $this->currentPage - $half);
        $end = min($this->totalPages, $start + $window - 1);

        // Expand start if we're near the end
        if ($end - $start + 1 < $window) {
            $start = max(1, $end - $window + 1);
        }

        return range($start, $end);
    }
}
