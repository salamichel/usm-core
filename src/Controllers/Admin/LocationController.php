<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\View;
use App\Models\Location;

class LocationController
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

        $coords = $this->geocode($data['address']);
        if ($coords) {
            $data['latitude']  = $coords['latitude'];
            $data['longitude'] = $coords['longitude'];
        }

        $id = Location::create($data);
        View::flash('success', 'Emplacement créé avec succès.');
        header('Location: ' . BASE_URL . '/admin/locations/' . $id . '/edit');
        exit;
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

        $coords = $this->geocode($data['address']);
        if ($coords) {
            $data['latitude']  = $coords['latitude'];
            $data['longitude'] = $coords['longitude'];
        }

        Location::update($id, $data);
        View::flash('success', 'Emplacement mis à jour avec succès.');
        header('Location: ' . BASE_URL . '/admin/locations');
        exit;
    }

    public function delete(array $params): void
    {
        Auth::require();
        Location::delete((int)$params['id']);
        View::flash('success', 'Emplacement supprimé avec succès.');
        header('Location: ' . BASE_URL . '/admin/locations');
        exit;
    }

    private function formData(): array
    {
        return [
            'name'    => trim($_POST['name'] ?? ''),
            'address' => trim($_POST['address'] ?? ''),
        ];
    }

    private function geocode(string $address): ?array
    {
        $query = urlencode($address);
        $url   = "https://nominatim.openstreetmap.org/search?format=json&q={$query}&limit=1";

        $ctx = stream_context_create([
            'http' => [
                'timeout'      => 5,
                'user_agent'   => 'USMVolley (contact@example.com)',
            ],
        ]);

        $response = @file_get_contents($url, false, $ctx);
        if (!$response) {
            return null;
        }

        $data = json_decode($response, true);
        if (!$data || count($data) === 0) {
            return null;
        }

        return [
            'latitude'  => (float)$data[0]['lat'],
            'longitude' => (float)$data[0]['lon'],
        ];
    }

    private function notFound(): void
    {
        http_response_code(404);
        View::render('404.twig');
    }
}
