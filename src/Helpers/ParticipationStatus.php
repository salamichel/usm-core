<?php
declare(strict_types=1);

namespace App\Helpers;

class ParticipationStatus
{
    private string $status;

    private const SELECTED_KEYWORDS = ['Sélectionné(e)'];
    private const AVAILABLE_IF_NEEDED = ['Disponible si n'];
    private const AVAILABLE_KEYWORDS = ['Disponible', 'Joker'];
    private const UNAVAILABLE_KEYWORDS = ['Indisponible'];
    private const ABSENT_KEYWORDS = ['Absent', 'Non'];
    private const PRESENT_KEYWORDS = ['Oui', 'Présent'];
    private const UNKNOWN_KEYWORDS = ['Ne sait pas', '?'];

    public function __construct(string $status)
    {
        $this->status = trim((string) $status);
    }

    public function getCategory(): string
    {
        if ($this->isEmpty()) {
            return 'no_response';
        }

        if ($this->matchesAny(self::SELECTED_KEYWORDS)) {
            return 'selected';
        }

        if ($this->matchesAny(self::AVAILABLE_IF_NEEDED)) {
            return 'available';
        }

        if ($this->matchesAny(self::AVAILABLE_KEYWORDS)) {
            return 'available';
        }

        if ($this->matchesAny(self::UNAVAILABLE_KEYWORDS)) {
            return 'unavailable';
        }

        if ($this->matchesAny(self::ABSENT_KEYWORDS)) {
            return 'absent';
        }

        if ($this->matchesAny(self::PRESENT_KEYWORDS)) {
            return 'present';
        }

        if ($this->matchesAny(self::UNKNOWN_KEYWORDS)) {
            return 'unknown';
        }

        return 'no_response';
    }

    public function isSelected(): bool
    {
        return $this->matchesAny(self::SELECTED_KEYWORDS);
    }

    public function isAvailable(): bool
    {
        return $this->matchesAny(self::AVAILABLE_IF_NEEDED) || $this->matchesAny(self::AVAILABLE_KEYWORDS);
    }

    public function isUnavailable(): bool
    {
        return $this->matchesAny(self::UNAVAILABLE_KEYWORDS);
    }

    public function isAbsent(): bool
    {
        return $this->matchesAny(self::ABSENT_KEYWORDS);
    }

    public function isPresent(): bool
    {
        return $this->matchesAny(self::PRESENT_KEYWORDS);
    }

    public function isUnknown(): bool
    {
        return $this->matchesAny(self::UNKNOWN_KEYWORDS);
    }

    public function isEmpty(): bool
    {
        return $this->status === '';
    }

    public function isNonAbsence(): bool
    {
        return !$this->isAbsent() && !$this->isUnavailable() && !$this->isEmpty();
    }

    public function getCompanionCount(): int
    {
        if ($this->matchesAny(self::PRESENT_KEYWORDS)) {
            if (strpos($this->status, '5') !== false) {
                return 4;
            } elseif (strpos($this->status, '4') !== false) {
                return 3;
            } elseif (strpos($this->status, '3') !== false) {
                return 2;
            } elseif (strpos($this->status, '2') !== false) {
                return 1;
            }
        }
        return 0;
    }

    public function getIcon(): string
    {
        return match ($this->getCategory()) {
            'present' => '✓',
            'available' => '◐',
            'unavailable' => '✗',
            'absent' => '✗',
            'selected' => '★',
            'unknown' => '?',
            'no_response' => '—',
            default => '—',
        };
    }

    public function getLabel(): string
    {
        $cat = $this->getCategory();
        if ($cat === 'selected') {
            $orig = $this->getOriginalStatus();
            if ($orig !== '' && $orig !== 'Sans réponse') {
                return 'Sélectionné (' . $orig . ')';
            }
            return 'Sélectionné';
        }
        return match ($cat) {
            'present' => 'Présent',
            'available' => 'Disponible',
            'unavailable' => 'Indisponible',
            'absent' => 'Absent',
            'unknown' => 'Ne sait pas',
            'no_response' => 'Sans réponse',
            default => '—',
        };
    }

    public function getBackgroundColor(): string
    {
        return match ($this->getCategory()) {
            'present', 'selected' => 'bg-green-100',
            'available' => 'bg-amber-100',
            'unavailable', 'absent' => 'bg-red-100',
            'unknown' => 'bg-gray-100',
            'no_response' => 'bg-white',
            default => 'bg-white',
        };
    }

    public function getTextColor(): string
    {
        return match ($this->getCategory()) {
            'present', 'selected' => 'text-green-700',
            'available' => 'text-amber-700',
            'unavailable', 'absent' => 'text-red-700',
            'unknown' => 'text-gray-700',
            'no_response' => 'text-gray-500',
            default => 'text-gray-500',
        };
    }

    private function matchesAny(array $keywords): bool
    {
        foreach ($keywords as $keyword) {
            if (strpos($this->status, $keyword) !== false) {
                return true;
            }
        }
        return false;
    }

    public static function categorize(string $status): string
    {
        return (new self($status))->getCategory();
    }
}
