<?php
declare(strict_types=1);

namespace App\Helpers;

/**
 * Utilitaires de manipulation HTML.
 */
class HtmlHelper
{
    /**
     * Valeurs considérées comme "vides" par l'éditeur Quill.
     */
    private const QUILL_EMPTY_VALUES = ['<p><br></p>', '<p></p>', '<p> </p>'];

    /**
     * Retourne null si la chaîne HTML est vide ou correspond à une valeur
     * vide générée par Quill (ex: "<p><br></p>").
     */
    public static function nullIfEmptyHtml(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }
        return in_array(trim($html), self::QUILL_EMPTY_VALUES, true) ? null : $html;
    }
}
