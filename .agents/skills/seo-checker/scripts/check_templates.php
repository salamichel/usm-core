<?php
declare(strict_types=1);

$workspaceRoot = dirname(__DIR__, 4);
$templatesDir = "$workspaceRoot/templates/front002";

if (!is_dir($templatesDir)) {
    // Fallback à templates/ si front002 n'existe pas
    $templatesDir = "$workspaceRoot/templates";
}

echo "Analyse SEO des templates dans : $templatesDir\n";
echo str_repeat("=", 60) . "\n";

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($templatesDir));
$warningsCount = 0;
$scannedCount = 0;

foreach ($iterator as $file) {
    if ($file->isDir() || $file->getExtension() !== 'twig') {
        continue;
    }

    $scannedCount++;
    $filePath = $file->getPathname();
    $relativeName = str_replace($workspaceRoot . DIRECTORY_SEPARATOR, "", $filePath);
    $content = file_get_contents($filePath);
    $fileWarnings = [];

    // 1. Recherche de balises img sans alt
    if (preg_match_all('/<img[^>]+>/i', $content, $matches)) {
        foreach ($matches[0] as $img) {
            if (stripos($img, 'alt=') === false) {
                $fileWarnings[] = "Balise <img> sans attribut alt : " . trim($img);
            }
        }
    }

    // 2. Recherche de liens vides
    if (preg_match_all('/<a[^>]+>/i', $content, $matches)) {
        foreach ($matches[0] as $link) {
            if (preg_match('/href=["\']\s*["\']/i', $link) || stripos($link, 'href=') === false) {
                $fileWarnings[] = "Lien <a> avec href vide ou absent : " . trim($link);
            }
        }
    }

    // 3. Vérification de la structure H1 (seulement sur les templates enfants, pas base.twig)
    if (basename($filePath) !== 'base.twig' && stripos($content, '{% extends') !== false) {
        $h1Count = substr_count(strtolower($content), '<h1');
        if ($h1Count === 0) {
            // Note: Certaines sous-pages peuvent hériter du H1 du layout parent
            $fileWarnings[] = "Aucune balise <h1> détectée dans ce template enfant.";
        } elseif ($h1Count > 1) {
            $fileWarnings[] = "Plusieurs balises <h1> détectées ($h1Count H1 trouvés).";
        }
    }

    // 4. Utilisation de balise de titre vide
    if (preg_match('/<h[1-6][^>]*>\s*<\/h[1-6]>/i', $content)) {
        $fileWarnings[] = "Balise de titre (H1-H6) vide détectée.";
    }

    if (!empty($fileWarnings)) {
        echo "\n[!] Fichier : $relativeName\n";
        foreach ($fileWarnings as $warn) {
            echo "    - $warn\n";
            $warningsCount++;
        }
    }
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "Analyse terminée :\n";
echo "- $scannedCount templates analysés.\n";
if ($warningsCount === 0) {
    echo "- Aucun problème SEO détecté ! (Félicitations !)\n";
} else {
    echo "- $warningsCount avertissement(s) SEO détecté(s).\n";
}
exit($warningsCount > 0 ? 1 : 0);
