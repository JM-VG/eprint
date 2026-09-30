<?php

namespace App\Services;

use App\Models\Service;

class PriceListService
{
    /**
     * Create or update a price list entry. The unit follows the type:
     * documents are priced per page, photos per set.
     *
     * @param  array<string, mixed>  $data  validated ServiceRequest data
     */
    public function save(Service $service, array $data): Service
    {
        $isPhoto = $data['type'] === 'photo';

        $service->fill([
            'type' => $data['type'],
            'name' => $data['name'],
            'paper_size' => $isPhoto ? null : $data['paper_size'],
            'color_mode' => $data['color_mode'],
            'unit' => $isPhoto ? 'set' : 'page',
            'pieces_per_set' => $isPhoto ? (int) $data['pieces_per_set'] : null,
            'price' => $data['price'],
            'is_active' => (bool) ($data['is_active'] ?? false),
        ])->save();

        return $service;
    }
}
