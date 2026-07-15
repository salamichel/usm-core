<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\ExternalDatabase;

class JoueurSnapshot
{
    public static function getExternalJoueurs(): array
    {
        return ExternalDatabase::get()
            ->query("SELECT * FROM Joueurs ORDER BY Nom ASC, `Prénom` ASC")
            ->fetchAll();
    }

    /**
     * Flash tous les joueurs de la base externe vers joueur_snapshots,
     * puis rebuild equipe_saison_joueur pour chaque équipe active.
     * Re-flasher réinitialise les ajustements manuels.
     * Retourne le nombre de joueurs snapshotés.
     */
    public static function flashForSaison(int $saisonId): int
    {
        $joueurs = self::getExternalJoueurs();
        $db      = Database::get();

        $db->beginTransaction();
        try {
            // 0. Récupérer les capitaines actuels (id_joueur et equipe_id) pour les préserver
            $stmtCaptains = $db->prepare(
                "SELECT js.id_joueur, es.equipe_id
                 FROM equipe_saison_joueur esj
                 JOIN joueur_snapshots js ON js.id = esj.snapshot_id
                 JOIN equipe_saison es ON es.id = esj.equipe_saison_id
                 WHERE es.saison_id = ? AND esj.is_captain = 1"
            );
            $stmtCaptains->execute([$saisonId]);
            $existingCaptains = $stmtCaptains->fetchAll(\PDO::FETCH_ASSOC);

            $hasExistingCaptains = !empty($existingCaptains);

            $captainKeys = [];
            foreach ($existingCaptains as $cap) {
                $key = $cap['id_joueur'] . '-' . $cap['equipe_id'];
                $captainKeys[$key] = true;
            }

            // 1. Remplacer les snapshots : supprimer les anciens puis réinsérer
            $db->prepare(
                "DELETE FROM joueur_snapshots WHERE saison_id = ?"
            )->execute([$saisonId]);

            $insert = $db->prepare(
                "INSERT INTO joueur_snapshots (saison_id, id_joueur, nom, prenom, nlicence, data)
                 VALUES (:saison_id, :id_joueur, :nom, :prenom, :nlicence, :data)"
            );
            foreach ($joueurs as $j) {
                $insert->execute([
                    ':saison_id' => $saisonId,
                    ':id_joueur' => $j['id_joueur'],
                    ':nom'       => $j['Nom'],
                    ':prenom'    => $j['Prénom'],
                    ':nlicence'  => $j['NLicence'] ?? null,
                    ':data'      => json_encode($j),
                ]);
            }

            // 2. Rebuild equipe_saison_joueur pour chaque équipe active
            $equipes = EquipeConfig::allActive();
            foreach ($equipes as $eq) {
                $es = EquipeSaison::findOrCreate($eq['id'], $saisonId);

                // Reset membres existants (annule ajustements manuels)
                $db->prepare(
                    "DELETE FROM equipe_saison_joueur WHERE equipe_saison_id = ?"
                )->execute([$es['id']]);

                // Récupérer les snapshots dont le flag de l'équipe est vrai
                $col  = $eq['slug_colonne'];
                $snaps = self::findBySaison($saisonId);
                $ins  = $db->prepare(
                    "INSERT IGNORE INTO equipe_saison_joueur (equipe_saison_id, snapshot_id, is_captain)
                     VALUES (?, ?, ?)"
                );
                foreach ($snaps as $snap) {
                    $key = $snap['id_joueur'] . '-' . $eq['id'];
                    $wasCaptain = isset($captainKeys[$key]);

                    // Le joueur doit être inséré s'il est dans la colonne de l'équipe de la base externe,
                    // OU s'il était déjà capitaine de cette équipe.
                    if (!empty($snap['data'][$col]) || $wasCaptain) {
                        if ($hasExistingCaptains) {
                            // Si des capitaines existent déjà localement pour cette saison,
                            // on préserve l'existant local sans importer de nouveau capitaine de la base externe.
                            $isCaptain = $wasCaptain ? 1 : 0;
                        } else {
                            // Sinon (premier flash de la saison), on importe le capitaine de la base externe.
                            $isCaptain = str_contains($snap['data']['Caracteristique'] ?? '', 'Capitaine') ? 1 : 0;
                        }
                        $ins->execute([$es['id'], $snap['id'], $isCaptain]);
                    }
                }
            }

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        return count($joueurs);
    }

    public static function findBySaison(int $saisonId): array
    {
        $stmt = Database::get()->prepare(
            "SELECT * FROM joueur_snapshots WHERE saison_id = ? ORDER BY nom ASC, prenom ASC"
        );
        $stmt->execute([$saisonId]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $row['data'] = json_decode($row['data'], true) ?? [];
        }
        return $rows;
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::get()->prepare(
            "SELECT * FROM joueur_snapshots WHERE id = ? LIMIT 1"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) return null;
        $row['data'] = json_decode($row['data'], true) ?? [];
        return $row;
    }
}
