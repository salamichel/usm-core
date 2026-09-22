<?php

declare(strict_types=1);

define('ROOT', dirname(__DIR__));
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

use App\Models\Joueur;
use App\Models\Saison;
use App\Services\Agenda\EventTargetingService;
use App\Models\Participation;

echo "=== TEST EVENT TARGETING SERVICE ===" . PHP_EOL;

$saison = Saison::getActive();
echo "Active Saison: " . ($saison ? $saison['libelle'] : 'None') . PHP_EOL;

$players = Joueur::getAll();
echo "Total Players: " . count($players) . PHP_EOL;

if (!empty($players)) {
    $tested = 0;
    foreach ($players as $p) {
        $id = (int)$p['id_joueur'];
        $categories = Joueur::getCategories($id);
        if (empty($categories)) {
            continue;
        }

        $upcomingNew = EventTargetingService::getUpcomingForPlayer($id);
        $upcomingLegacy = Participation::getUpcomingForMember($id, $categories);

        assert(count($upcomingNew) === count($upcomingLegacy), "Counts must match between EventTargetingService and Participation::getUpcomingForMember for player $id");

        echo "Player ID {$id} (" . ($p['Prénom'] ?? '') . " " . ($p['Nom'] ?? '') . "): " . count($upcomingNew) . " upcoming event(s) found. Categories: " . implode(', ', $categories) . PHP_EOL;
        
        $tested++;
    }
}

// Test 2: Unit tests for isPlayerConcernedByEvent
echo "Testing isPlayerConcernedByEvent..." . PHP_EOL;

// Generic event (Club / Tournoi)
$isConcernedTournoi = EventTargetingService::isPlayerConcernedByEvent(539, 'Manifestation - Tournoi - Tournoi de rentrée');
assert($isConcernedTournoi === true, "Tournoi must concern all players");

$isConcernedClub = EventTargetingService::isPlayerConcernedByEvent(539, 'Manifestation - Vie du club - AG annuelle');
assert($isConcernedClub === true, "Club life must concern all players");

$cats539 = Joueur::getCategories(539);
echo "Player 539 categories: " . json_encode($cats539) . PHP_EOL;

$equipes539 = EventTargetingService::getPlayerEquipesConfig(539, $cats539);
echo "Player 539 equipes config: " . json_encode($equipes539) . PHP_EOL;

$allEquipes = \App\Core\Database::get()->query("SELECT id, slug_colonne, libelle, manifestation_filter, training_filter, is_active FROM equipes_config")->fetchAll(PDO::FETCH_ASSOC);
echo "All equipes in DB: " . json_encode($allEquipes) . PHP_EOL;

// Plateau UFOLEP 3
$isConcernedUfolep = EventTargetingService::isPlayerConcernedByEvent(539, 'Matchs - Match - Plateau UFOLEP 3');
assert($isConcernedUfolep === true, "Plateau UFOLEP 3 match must concern player with UFOLEP_3");

// L1 match should not concern 539
$isConcernedL1 = EventTargetingService::isPlayerConcernedByEvent(539, 'Matchs - Match - Match L1');
assert($isConcernedL1 === false, "Match L1 must NOT concern player 539 who only has UFOLEP_3");

// Player 465 (Ruben PENIN) has L2 which has training_filter "Présences - Entrainement - CompetLib (jeu libre)"
$isConcernedTraining465 = EventTargetingService::isPlayerConcernedByEvent(465, 'Présences - Entrainement - CompetLib (jeu libre)');
assert($isConcernedTraining465 === true, "Player with L2 must be concerned by CompetLib (jeu libre) training");

echo "All isPlayerConcernedByEvent unit assertions passed!" . PHP_EOL;

echo "=== ALL CHECKS PASSED SUCCESSFULLY ===" . PHP_EOL;



