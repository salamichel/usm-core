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
                    "INSERT IGNORE INTO equipe_saison_joueur (equipe_saison_id, snapshot_id)
                     VALUES (?, ?)"
                );
                foreach ($snaps as $snap) {
                    if (!empty($snap['data'][$col])) {
                        $ins->execute([$es['id'], $snap['id']]);
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
