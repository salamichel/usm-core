<?php

declare(strict_types=1);

namespace App\Helpers;

class ParticipationStatus
{
    private string $status;

    private const STATUS_MAPPING = [
        "Sélectionné(e)"           => "selected",
        "Disponible si nécessaire" => "available_if_needed",
        "Disponible"               => "available",
        "Joker"                    => "available",
        "Indisponible"             => "unavailable",
        "Absent(e)"                => "absent",
        "Présent(e)"               => "present",
        "Présent(e) à 2"           => "present",
        "Présent(e) à 3"           => "present",
        "Présent(e) à 4"           => "present",
        "Présent(e) à 5"           => "present",
        "Ne sait pas"              => "unknown",
        "?"                        => "unknown",
    ];

    /**
     * Reconstruit le statut de sélection canonique avec la disponibilité d origine
     */
    public static function buildSelectedStatus(string $originalStatus): string
    {
        if (empty($originalStatus) || $originalStatus === "Sans réponse") {
            return "Sélectionné(e)";
        }
        return "Sélectionné(e) ($originalStatus)";
    }

    public function __construct(string $status)
    {
        $this->status = trim((string) $status);
    }

    public function getCategory(): string
    {
        if ($this->isEmpty()) {
            return "no_response";
        }

        $base = $this->getBaseStatus();

        // Exact match
        if (isset(self::STATUS_MAPPING[$base])) {
            return self::STATUS_MAPPING[$base];
        }

        // Fuzzy match
        foreach (self::STATUS_MAPPING as $keyword => $cat) {
            if (stripos($base, $keyword) !== false) {
                return $cat;
            }
        }

        return "no_response";
    }

    public function isSelected(): bool
    {
        return $this->getCategory() === "selected";
    }

    public function isAvailable(): bool
    {
        $cat = $this->getCategory();
        return $cat === "available" || $cat === "available_if_needed";
    }

    public function isUnavailable(): bool
    {
        return $this->getCategory() === "unavailable";
    }

    public function isAbsent(): bool
    {
        return $this->getCategory() === "absent";
    }

    public function isPresent(): bool
    {
        return $this->getCategory() === "present";
    }

    public function isUnknown(): bool
    {
        return $this->getCategory() === "unknown";
    }

    public function isEmpty(): bool
    {
        return $this->status === "";
    }

    private function getBaseStatus(): string
    {
        // Strip trailing "(xxx)" for selections
        if (preg_match("/^(.*?)\s*\((.*?)\)$/", $this->status, $matches)) {
            return trim($matches[1]);
        }
        return $this->status;
    }

    public function getOriginalStatus(): string
    {
        if (preg_match("/^.*?\s*\((.*?)\)$/", $this->status, $matches)) {
            return trim($matches[1]);
        }
        return "";
    }

    public function isNonAbsence(): bool
    {
        return !$this->isAbsent() && !$this->isUnavailable() && !$this->isEmpty();
    }

    public function getCompanionCount(): int
    {
        if ($this->isPresent()) {
            if (preg_match("/(\d+)/", $this->status, $matches)) {
                $num = (int)$matches[1];
                return max(0, $num - 1);
            }
        }
        return 0;
    }

    public function getIcon(): string
    {
        return match ($this->getCategory()) {
            "present" => "✓",
            "available" => "✓",
            "available_if_needed" => "◐",
            "unavailable" => "✗",
            "absent" => "✗",
            "selected" => "★",
            "unknown" => "?",
            "no_response" => "-",
            default => "-",
        };
    }

    public function getLabel(): string
    {
        $cat = $this->getCategory();
        if ($cat === "selected") {
            $orig = $this->getOriginalStatus();
            if ($orig !== "" && $orig !== "Sans réponse") {
                return "Sélectionné (" . $orig . ")";
            }
            return "Sélectionné";
        }

        return match ($cat) {
            "present" => "Présent",
            "available" => "Disponible",
            "available_if_needed" => "Disponible si nécessaire",
            "unavailable" => "Indisponible",
            "absent" => "Absent",
            "unknown" => "Ne sait pas",
            "no_response" => "Sans réponse",
            default => "-",
        };
    }

    public function getBackgroundColor(): string
    {
        return match ($this->getCategory()) {
            "present", "selected" => "bg-green-100",
            "available" => "bg-amber-100",
            "available_if_needed" => "bg-cyan-100",
            "unavailable", "absent" => "bg-red-100",
            "unknown" => "bg-gray-100",
            "no_response" => "bg-white",
            default => "bg-white",
        };
    }

    public function getTextColor(): string
    {
        return match ($this->getCategory()) {
            "present", "selected" => "text-green-700",
            "available" => "text-amber-700",
            "available_if_needed" => "text-cyan-700",
            "unavailable", "absent" => "text-red-700",
            "unknown" => "text-gray-700",
            "no_response" => "text-gray-500",
            default => "text-gray-500",
        };
    }

    public static function categorize(string $status): string
    {
        return (new self($status))->getCategory();
    }
}
