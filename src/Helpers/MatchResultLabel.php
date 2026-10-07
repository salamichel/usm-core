<?php

declare(strict_types=1);

namespace App\Helpers;

class MatchResultLabel
{
    /**
     * Formate le score en sets : ex. "3 - 1"
     */
    public static function formatScore(?int $setsFor, ?int $setsAgainst): ?string
    {
        if ($setsFor === null || $setsAgainst === null) {
            return null;
        }
        return "{$setsFor} - {$setsAgainst}";
    }

    /**
     * Détermine si le match est une victoire ('win'), défaite ('loss') ou nul ('draw').
     */
    public static function getOutcome(?int $setsFor, ?int $setsAgainst): ?string
    {
        if ($setsFor === null || $setsAgainst === null) {
            return null;
        }
        if ($setsFor > $setsAgainst) {
            return 'win';
        }
        if ($setsFor < $setsAgainst) {
            return 'loss';
        }
        return 'draw';
    }

    /**
     * Retourne le libellé court ('Victoire', 'Défaite')
     */
    public static function getOutcomeLabel(?int $setsFor, ?int $setsAgainst): ?string
    {
        $outcome = self::getOutcome($setsFor, $setsAgainst);
        return match ($outcome) {
            'win'  => 'Victoire',
            'loss' => 'Défaite',
            'draw' => 'Égalité',
            default => null,
        };
    }

    /**
     * Retourne les classes CSS Tailwind de badge selon l'issue.
     */
    public static function getBadgeClasses(?int $setsFor, ?int $setsAgainst): string
    {
        $outcome = self::getOutcome($setsFor, $setsAgainst);
        return match ($outcome) {
            'win'  => 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20',
            'loss' => 'bg-rose-500/10 text-rose-400 border border-rose-500/20',
            default => 'bg-slate-500/10 text-slate-400 border border-slate-500/20',
        };
    }
}
