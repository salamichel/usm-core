<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\View;
use App\Models\Location;

class LocationController extends AdminCrudController
{
    public function __construct()
    {
        parent::__construct();
        $this->entityType = 'location';
        $this->itemName = 'location';
        $this->itemsName = 'locations';
        $this->templates = [
            'list' => 'admin/locations/list.twig',
            'form' => 'admin/locations/form.twig',
        ];
    }

    protected function getModel(): string
    {
        return Location::class;
    }

    protected function getEntity(int $id): ?array
    {
        return Location::find($id);
    }

    protected function getAllEntities(): array
    {
        return Location::all();
    }

    protected function createEntity(array $data): int
    {
        return Location::create($data);
    }

    protected function updateEntity(int $id, array $data): void
    {
        Location::update($id, $data);
    }

    protected function deleteEntity(int $id): void
    {
        Location::delete($id);
    }

    protected function getFormData(): array
    {
        return [
            'name'      => trim($_POST['name'] ?? ''),
            'address'   => trim($_POST['address'] ?? ''),
            'latitude'  => $_POST['latitude'] !== '' ? (float)($_POST['latitude'] ?? 0) : null,
            'longitude' => $_POST['longitude'] !== '' ? (float)($_POST['longitude'] ?? 0) : null,
        ];
    }

    protected function validateData(array $data, ?array $existingEntity = null): ?string
    {
        if (empty($data['name'])) {
            return 'Le nom est obligatoire.';
        }
        if (empty($data['address'])) {
            return 'L\'adresse est obligatoire.';
        }
        return null;
    }

    public function store(array $params): void
    {
        $data = $this->getFormData();
        $error = $this->validateData($data);
        if ($error) {
            View::render($this->getFormTemplate(false), array_merge($this->getCreateData(), [
                'location' => $data,
                'error'    => $error,
            ]));
            return;
        }

        $method = $this->resolveCoordinates($data);
        $id = Location::create($data);

        if ($method === 'manual') {
            View::flash('success', 'Emplacement créé avec coordonnées manuelles.');
        } elseif ($method === 'geoloc') {
            View::flash('success', 'Emplacement créé et géolocalisé automatiquement.');
        } else {
            View::flash('success', 'Emplacement créé. Vous pouvez ajouter les coordonnées GPS manuellement en éditant.');
        }

        $this->redirect('/admin/locations/' . $id . '/edit');
    }

    public function update(array $params): void
    {
        $id = (int)$params['id'];
        $location = Location::find($id);

        if (!$location) {
            $this->notFound();
            return;
        }

        $data = $this->getFormData();
        $error = $this->validateData($data, $location);
        if ($error) {
            View::render($this->getFormTemplate(true), array_merge($this->getEditData($location), [
                'location' => array_merge($location, $data),
                'error'    => $error,
            ]));
            return;
        }

        $method = $this->resolveCoordinates($data);
        Location::update($id, $data);

        if ($method === 'manual') {
            View::flash('success', 'Emplacement mis à jour avec coordonnées manuelles.');
        } elseif ($method === 'geoloc') {
            View::flash('success', 'Emplacement mis à jour et géolocalisé automatiquement.');
        } else {
            View::flash('success', 'Emplacement mis à jour.');
        }

        $this->redirect('/admin/locations');
    }

    private function resolveCoordinates(array &$data): string
    {
        if (!$data['latitude'] || !$data['longitude']) {
            $coords = $this->geocode($data['address']);
            if ($coords) {
                $data['latitude']  = $coords['latitude'];
                $data['longitude'] = $coords['longitude'];
                return 'geoloc';
            }
            return 'none';
        }
        return 'manual';
    }

    private function geocode(string $address): ?array
    {
        if (empty(trim($address))) {
            return null;
        }

        $query = urlencode($address);
        $url   = "https://nominatim.openstreetmap.org/search?format=json&q={$query}&limit=1&timeout=10";

        $ctx = stream_context_create([
            'http' => [
                'timeout'      => 10,
                'user_agent'   => 'USMVolley/1.0 (+http://localhost)',
                'header'       => 'Accept: application/json',
            ],
        ]);

        try {
            $response = @file_get_contents($url, false, $ctx);
            if ($response === false) {
                error_log("Geocoding failed for address: $address (network error)");
                return null;
            }

            $data = json_decode($response, true);
            if (!is_array($data) || count($data) === 0) {
                error_log("Geocoding failed for address: $address (no results)");
                return null;
            }

            $lat = (float)($data[0]['lat'] ?? null);
            $lon = (float)($data[0]['lon'] ?? null);

            if ($lat === 0.0 || $lon === 0.0) {
                error_log("Geocoding failed for address: $address (invalid coordinates)");
                return null;
            }

            error_log("Successfully geocoded: $address -> $lat, $lon");

            return [
                'latitude'  => $lat,
                'longitude' => $lon,
            ];
        } catch (\Exception $e) {
            error_log("Geocoding exception for address: $address - " . $e->getMessage());
            return null;
        }
    }
}
