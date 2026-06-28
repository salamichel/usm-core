<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\View;
use App\Models\Location;

class LocationController extends BaseAdminController
{
    public function index(array $params): void
    {
        Auth::require();
        View::render('admin/locations/list.twig', [
            'locations' => Location::all(),
        ]);
    }

    public function create(array $params): void
    {
        Auth::require();
        View::render('admin/locations/form.twig', [
            'location' => null,
            'action'   => BASE_URL . '/admin/locations/create',
        ]);
    }

    public function store(array $params): void
    {
        Auth::require();
        $data = $this->formData();

        if (empty($data['name'])) {
            View::render('admin/locations/form.twig', [
                'location' => $data,
                'action'   => BASE_URL . '/admin/locations/create',
                'error'    => 'Le nom est obligatoire.',
            ]);
            return;
        }

        if (empty($data['address'])) {
            View::render('admin/locations/form.twig', [
                'location' => $data,
                'action'   => BASE_URL . '/admin/locations/create',
                'error'    => 'L\'adresse est obligatoire.',
            ]);
            return;
        }

        // Utiliser les coordonnées manuelles si fournies, sinon tenter la géolocalisation
        if (!$data['latitude'] || !$data['longitude']) {
            $coords = $this->geocode($data['address']);
            if ($coords) {
                $data['latitude']  = $coords['latitude'];
                $data['longitude'] = $coords['longitude'];
                $method = 'geoloc';
            } else {
                $method = 'none';
            }
        } else {
            $method = 'manual';
        }

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

    public function edit(array $params): void
    {
        Auth::require();
        $location = Location::find((int)$params['id']);
        if (!$location) {
            $this->notFound();
            return;
        }

        View::render('admin/locations/form.twig', [
            'location' => $location,
            'action'   => BASE_URL . '/admin/locations/' . $location['id'] . '/edit',
        ]);
    }

    public function update(array $params): void
    {
        Auth::require();
        $id       = (int)$params['id'];
        $location = Location::find($id);

        if (!$location) {
            $this->notFound();
            return;
        }

        $data = $this->formData();

        if (empty($data['name'])) {
            View::render('admin/locations/form.twig', [
                'location' => array_merge($location, $data),
                'action'   => BASE_URL . '/admin/locations/' . $id . '/edit',
                'error'    => 'Le nom est obligatoire.',
            ]);
            return;
        }

        if (empty($data['address'])) {
            View::render('admin/locations/form.twig', [
                'location' => array_merge($location, $data),
                'action'   => BASE_URL . '/admin/locations/' . $id . '/edit',
                'error'    => 'L\'adresse est obligatoire.',
            ]);
            return;
        }

        // Utiliser les coordonnées manuelles si fournies, sinon tenter la géolocalisation
        if (!$data['latitude'] || !$data['longitude']) {
            $coords = $this->geocode($data['address']);
            if ($coords) {
                $data['latitude']  = $coords['latitude'];
                $data['longitude'] = $coords['longitude'];
                $method = 'geoloc';
            } else {
                $method = 'none';
            }
        } else {
            $method = 'manual';
        }

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

    public function delete(array $params): void
    {
        Auth::require();
        Location::delete((int)$params['id']);
        View::flash('success', 'Emplacement supprimé avec succès.');
        $this->redirect('/admin/locations');
    }

    private function formData(): array
    {
        return [
            'name'      => trim($_POST['name'] ?? ''),
            'address'   => trim($_POST['address'] ?? ''),
            'latitude'  => $_POST['latitude'] !== '' ? (float)($_POST['latitude'] ?? 0) : null,
            'longitude' => $_POST['longitude'] !== '' ? (float)($_POST['longitude'] ?? 0) : null,
        ];
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
