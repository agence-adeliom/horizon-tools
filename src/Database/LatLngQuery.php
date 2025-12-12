<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Database;

class LatLngQuery
{
    private string $relation = 'AND';

    private array $query = [];

    public function __construct()
    {
    }

    public function add(
        string $latitudeKey,
        string $longitudeKey,
        float $latitude,
        float $longitude,
        float $radiusInKm,
        bool $orderByDistance = true,
        string $order = 'ASC'
    ): self {
        if (!empty($this->query)) {
            throw new \Exception('At the moment only one LatLngQuery is supported.');
        }

        $order = strtoupper($order);

        if (!in_array($order, ['ASC', 'DESC'])) {
            throw new \Exception('Order must be either ASC or DESC.');
        }

        $data = [
            'latitude' => [
                'key' => $latitudeKey,
                'value' => $latitude,
            ],
            'longitude' => [
                'key' => $longitudeKey,
                'value' => $longitude,
            ],
            'radius_in_km' => $radiusInKm,
            'order_by_distance' => $orderByDistance,
            'order' => $order,
        ];

        $this->query[] = $data;

        return $this;
    }

    public function getQuery(): array
    {
        return $this->query;
    }

    public function setRelation(string $relation): self
    {
        if (in_array($relation, ['AND', 'OR'])) {
            $this->relation = $relation;
        }

        return $this;
    }

    public function getRelation(): string
    {
        return $this->relation;
    }

    public function generateLatLngQueryArray(): array
    {
        $elements = array_map(function ($query) {
            if (is_array($query)) {
                return $query;
            } elseif ($query instanceof self) {
                return $query->generateLatLngQueryArray();
            }
        }, $this->getQuery());

        return [
            'relation' => $this->getRelation(),
            ...$elements,
        ];
    }
}
