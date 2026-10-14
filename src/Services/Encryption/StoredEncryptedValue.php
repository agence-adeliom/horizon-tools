<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Services\Encryption;

/**
 * Une valeur chiffrée telle que stockée en base, repérée par une clé naturelle
 * (table, objet, clé) qui reste valable d’une base à l’autre après un db:pull.
 */
final readonly class StoredEncryptedValue
{
    /**
     * @param string      $table     une de EncryptedValueRepository::TABLES
     * @param int         $objectId  0 pour les options
     * @param string|null $reference valeur de la meta de référence ACF « _<clé> » (clé du champ)
     */
    public function __construct(
        public string $table,
        public int $objectId,
        public string $key,
        public string $value,
        public ?string $reference = null
    ) {
    }

    public function location(): string
    {
        return sprintf('%s:%d:%s', $this->table, $this->objectId, $this->key);
    }

    /**
     * @return array{table: string, objectId: int, key: string, value: string, reference: string|null}
     */
    public function toArray(): array
    {
        return [
            'table' => $this->table,
            'objectId' => $this->objectId,
            'key' => $this->key,
            'value' => $this->value,
            'reference' => $this->reference,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (string) $data['table'],
            (int) $data['objectId'],
            (string) $data['key'],
            (string) $data['value'],
            isset($data['reference']) ? (string) $data['reference'] : null
        );
    }
}
