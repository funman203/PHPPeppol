<?php

declare(strict_types=1);

namespace Peppol\Models;

/**
 * Réponse à un document spécifique dans une ApplicationResponse (BG-2)
 *
 * Représente le résultat du traitement d'une facture :
 *   - code de statut (AP, RE, AB, IP, UQ)
 *   - référence au document original
 *   - raisons de rejet / descriptions d'erreur
 *
 * @package Peppol\Models
 */
class DocumentResponse
{
    // =========================================================================
    // Codes de statut (UNCL4343 subset Peppol)
    // =========================================================================

    /** Accepté / approuvé */
    const STATUS_ACCEPTED  = 'AP';
    /** Rejeté */
    const STATUS_REJECTED  = 'RE';
    /** Accusé de réception (en cours de traitement) */
    const STATUS_ACKNOWLEDGED = 'AB';
    /** En cours de traitement */
    const STATUS_IN_PROCESS = 'IP';
    /** En litige / sous réserve */
    const STATUS_UNDER_QUERY = 'UQ';

    /** @var array<string, string> Labels lisibles par statut */
    const STATUS_LABELS = [
        self::STATUS_ACCEPTED     => 'Accepté',
        self::STATUS_REJECTED     => 'Rejeté',
        self::STATUS_ACKNOWLEDGED => 'Accusé de réception',
        self::STATUS_IN_PROCESS   => 'En cours de traitement',
        self::STATUS_UNDER_QUERY  => 'En litige',
    ];

    // =========================================================================
    // Propriétés
    // =========================================================================

    /** @var string Code de statut (AP, RE, AB, IP, UQ) */
    private string $responseCode;

    /** @var string|null Description globale de la réponse */
    private ?string $description = null;

    /** @var string ID du document référencé (numéro de facture) */
    private string $referenceId;

    /** @var string|null Type du document référencé (UNCL1001 : 380, 381…) */
    private ?string $referenceTypeCode = null;

    /** @var string|null UUID/instance identifier du document (BT-1 étendu) */
    private ?string $referenceUuid = null;

    /**
     * @var array<array{code: string|null, description: string}> 
     * Erreurs détaillées — chaque entrée est un LineResponse
     */
    private array $lineResponses = [];

    // =========================================================================
    // Constructeur
    // =========================================================================

    /**
     * @param string      $responseCode  Code de statut (AP, RE, AB, IP, UQ)
     * @param string      $referenceId   ID du document référencé
     * @param string|null $description   Description globale optionnelle
     * @throws \InvalidArgumentException
     */
    public function __construct(
        string $responseCode,
        string $referenceId,
        ?string $description = null
    ) {
        $this->setResponseCode($responseCode);
        if (empty(trim($referenceId))) {
            throw new \InvalidArgumentException('L\'ID du document référencé ne peut pas être vide');
        }
        $this->referenceId  = $referenceId;
        $this->description  = $description;
    }

    // =========================================================================
    // Setters
    // =========================================================================

    private function setResponseCode(string $code): void
    {
        $valid = array_keys(self::STATUS_LABELS);
        if (!in_array($code, $valid, true)) {
            throw new \InvalidArgumentException(
                "Code de statut invalide : « $code ». Codes valides : " . implode(', ', $valid)
            );
        }
        $this->responseCode = $code;
    }

    public function setReferenceTypeCode(?string $code): static
    {
        $this->referenceTypeCode = $code;
        return $this;
    }

    public function setReferenceUuid(?string $uuid): static
    {
        $this->referenceUuid = $uuid;
        return $this;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    /**
     * Ajoute une erreur / raison de rejet détaillée (LineResponse)
     *
     * @param string      $description Texte de l'erreur
     * @param string|null $code        Code d'erreur optionnel (ex: BV, SV, BT-1…)
     */
    public function addLineResponse(string $description, ?string $code = null): static
    {
        $this->lineResponses[] = [
            'code'        => $code,
            'description' => $description,
        ];
        return $this;
    }

    // =========================================================================
    // Getters
    // =========================================================================

    public function getResponseCode(): string   { return $this->responseCode; }
    public function getDescription(): ?string   { return $this->description; }
    public function getReferenceId(): string     { return $this->referenceId; }
    public function getReferenceTypeCode(): ?string { return $this->referenceTypeCode; }
    public function getReferenceUuid(): ?string  { return $this->referenceUuid; }

    /** @return array<array{code: string|null, description: string}> */
    public function getLineResponses(): array    { return $this->lineResponses; }

    // =========================================================================
    // Helpers de statut
    // =========================================================================

    public function isAccepted(): bool      { return $this->responseCode === self::STATUS_ACCEPTED; }
    public function isRejected(): bool      { return $this->responseCode === self::STATUS_REJECTED; }
    public function isAcknowledged(): bool  { return $this->responseCode === self::STATUS_ACKNOWLEDGED; }
    public function isInProcess(): bool     { return $this->responseCode === self::STATUS_IN_PROCESS; }
    public function isUnderQuery(): bool    { return $this->responseCode === self::STATUS_UNDER_QUERY; }
    public function isPending(): bool
    {
        return in_array($this->responseCode, [
            self::STATUS_ACKNOWLEDGED,
            self::STATUS_IN_PROCESS,
            self::STATUS_UNDER_QUERY,
        ], true);
    }

    public function getStatusLabel(): string
    {
        return self::STATUS_LABELS[$this->responseCode] ?? $this->responseCode;
    }

    /**
     * Retourne toutes les descriptions d'erreur (descriptions globale + line responses)
     *
     * @return array<string>
     */
    public function getRejectionReasons(): array
    {
        $reasons = [];
        if ($this->description !== null) {
            $reasons[] = $this->description;
        }
        foreach ($this->lineResponses as $lr) {
            $reasons[] = ($lr['code'] ? "[{$lr['code']}] " : '') . $lr['description'];
        }
        return $reasons;
    }
}
