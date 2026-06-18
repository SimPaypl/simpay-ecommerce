<?php

declare(strict_types=1);

namespace SimPay\SDK;

/**
 * DTO representing a BLIK alias for OneClick payments.
 *
 * An alias links a customer's identity in your system with their BLIK app,
 * enabling future payments without a 6-digit code.
 *
 * Usage (registration during first payment with T6 code):
 *   $alias = BlikAlias::register('Shop Label', 'user_123');
 *
 * Usage (OneClick via SimPay UUID):
 *   $alias = BlikAlias::fromUuid('019972b1-e4c0-714f-a10b-f88a158bee50', 'Shop Label');
 *
 * Usage (OneClick via value + type):
 *   $alias = BlikAlias::fromValue('user_123', 'Shop Label');
 *
 * Usage (resolving ALIAS_NOT_UNIQUE with blik_id):
 *   $alias = BlikAlias::fromValue('user_123', 'Shop Label')->withBlikId(953824);
 */
final class BlikAlias
{
    private ?string $uuid;
    private ?string $value;
    private string $type;
    private string $label;
    private ?int $blikId;

    private function __construct(
        string $label,
        ?string $value = null,
        string $type = 'UID',
        ?string $uuid = null,
        ?int $blikId = null
    ) {
        $this->label = $label;
        $this->value = $value;
        $this->type = $type;
        $this->uuid = $uuid;
        $this->blikId = $blikId;
    }

    /**
     * Create an alias for registration during the first BLIK Level 0 payment.
     *
     * @param string $label  Label shown in the customer's banking app (must comply with BLIK certification)
     * @param string $value  Your unique customer identifier (e.g. user ID from your database)
     * @param string $type   Alias type — always "UID" for merchant integrations
     */
    public static function register(string $label, string $value, string $type = 'UID'): self
    {
        return new self(label: $label, value: $value, type: $type);
    }

    /**
     * Create an alias for OneClick payment using the SimPay UUID
     * received from the blik:alias_status_changed IPN.
     *
     * This method always targets one specific banking app — no ALIAS_NOT_UNIQUE risk.
     *
     * @param string $uuid  SimPay alias UUID from IPN (data.id)
     * @param string $label Label shown in the customer's banking app
     */
    public static function fromUuid(string $uuid, string $label): self
    {
        return new self(label: $label, uuid: $uuid);
    }

    /**
     * Create an alias for OneClick payment using your own customer ID.
     *
     * If the customer has multiple banking apps registered, the API may return
     * ALIAS_NOT_UNIQUE — resolve it with withBlikId().
     *
     * @param string $value Your unique customer identifier
     * @param string $label Label shown in the customer's banking app
     * @param string $type  Alias type — always "UID" for merchant integrations
     */
    public static function fromValue(string $value, string $label, string $type = 'UID'): self
    {
        return new self(label: $label, value: $value, type: $type);
    }

    /**
     * Add a blik_id to resolve an ALIAS_NOT_UNIQUE conflict.
     *
     * Call this when the customer selects a specific banking app from the
     * alternatives list returned in AliasNotUniqueException.
     */
    public function withBlikId(int $blikId): self
    {
        return new self(
            label: $this->label,
            value: $this->value,
            type: $this->type,
            uuid: $this->uuid,
            blikId: $blikId
        );
    }

    /**
     * Build the alias array for the API request payload.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = ['label' => $this->label];

        if ($this->uuid !== null) {
            $data['uuid'] = $this->uuid;
        }

        if ($this->value !== null) {
            $data['value'] = $this->value;
            $data['type'] = $this->type;
        }

        if ($this->blikId !== null) {
            $data['blik_id'] = $this->blikId;
        }

        return $data;
    }

    public function getUuid(): ?string
    {
        return $this->uuid;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getBlikId(): ?int
    {
        return $this->blikId;
    }
}

