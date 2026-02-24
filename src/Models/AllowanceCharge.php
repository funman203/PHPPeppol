<?php

declare(strict_types=1);

namespace Peppol\Models;

use Peppol\Core\InvoiceConstants;
use Peppol\Core\InvoiceValidatorTrait;

/**
 * Modèle de remise ou majoration au niveau document
 *
 * Représente un AllowanceCharge conforme à la norme EN 16931 :
 *   - BG-20 : Remise au niveau document  (ChargeIndicator = false)
 *   - BG-21 : Majoration au niveau document (ChargeIndicator = true)
 *
 * Les remises réduisent le montant net HT (taxExclusiveAmount),
 * les majorations l'augmentent. Les deux impactent également la base TVA.
 *
 * Codes de raison :
 *   - Remises      : UNCL5189 (ex : 95 = Discount, 100 = Special rebate)
 *   - Majorations  : UNCL7161 (ex : FC = Freight charges, PC = Packing)
 *
 * @package Peppol\Models
 * @version 1.0
 * @link https://docs.peppol.eu/poacc/billing/3.0/syntax/ubl-invoice/bg-20/
 * @link https://docs.peppol.eu/poacc/billing/3.0/syntax/ubl-invoice/bg-21/
 */
class AllowanceCharge
{
    use InvoiceValidatorTrait;

    /**
     * Codes de raison de remise selon UNCL5189 (liste non exhaustive)
     *
     * @var array<string, string>
     */
    public const ALLOWANCE_REASON_CODES = [
        '41'  => 'Bonus for works ahead of schedule',
        '42'  => 'Other bonus',
        '60'  => 'Manufacturer\'s consumer discount',
        '62'  => 'Due to military status',
        '63'  => 'Due to work accident',
        '64'  => 'Special agreement',
        '65'  => 'Production error discount',
        '66'  => 'New outlet discount',
        '67'  => 'Sample discount',
        '68'  => 'End-of-range discount',
        '70'  => 'Incoterm discount',
        '71'  => 'Point of sales threshold allowance',
        '88'  => 'Material surcharge/deduction',
        '95'  => 'Discount',
        '100' => 'Special rebate',
        '102' => 'Fixed long term',
        '103' => 'Temporary',
        '104' => 'Standard',
        '105' => 'Yearly turnover',
    ];

    /**
     * Codes de raison de majoration selon UNCL7161 (liste non exhaustive)
     *
     * @var array<string, string>
     */
    public const CHARGE_REASON_CODES = [
        'AA'  => 'Advertising',
        'AAA' => 'Telecommunication',
        'ABL' => 'Picking',
        'ABN' => 'Royalties',
        'ABR' => 'Testing',
        'ABS' => 'Transformation',
        'ABT' => 'Transportation',
        'ABU' => 'Packing',
        'ACF' => 'Acceptance',
        'ADC' => 'Freight charges',
        'ADE' => 'Freight insurance',
        'ADJ' => 'Insurance',
        'ADK' => 'Installation',
        'ADL' => 'Labelling',
        'ADM' => 'Maintenance',
        'ADN' => 'Overtime',
        'ADO' => 'Packaging',
        'ADP' => 'Palletizing',
        'ADQ' => 'Postal charges',
        'ADR' => 'Printing',
        'ADT' => 'Shipment',
        'ADW' => 'Warehousing',
        'ADX' => 'Wrapping',
        'FC'  => 'Freight charges',
        'FI'  => 'Financing',
        'LA'  => 'Labelling and tagging',
        'PC'  => 'Packing',
        'SH'  => 'Shipping and handling',
        'SM'  => 'Shipment consolidation',
        'TAE' => 'Testing and certification',
        'TX'  => 'Tax',
        'ZZZ' => 'Mutually defined',
    ];

    // =========================================================================
    // Propriétés
    // =========================================================================

    /**
     * @var bool Indicateur de type : true = majoration (charge), false = remise (allowance)
     *           BT-99 pour une majoration, BT-92 pour une remise
     */
    private bool $chargeIndicator;

    /**
     * @var float Montant de la remise ou majoration, arrondi à 2 décimales
     *            BT-92 (remise) / BT-99 (majoration)
     */
    private float $amount;

    /**
     * @var float|null Montant de base sur lequel s'applique le pourcentage
     *                 Obligatoire si MultiplierFactorNumeric est fourni
     *                 BT-93 (remise) / BT-100 (majoration)
     */
    private ?float $baseAmount;

    /**
     * @var float|null Pourcentage appliqué au montant de base (0–100)
     *                 Relation attendue : amount = baseAmount × multiplierFactorNumeric / 100
     *                 BT-94 (remise) / BT-101 (majoration)
     */
    private ?float $multiplierFactorNumeric;

    /**
     * @var string|null Code de raison selon UNCL5189 (remise) ou UNCL7161 (majoration)
     *                  BT-98 (remise) / BT-105 (majoration)
     *
     * @see self::ALLOWANCE_REASON_CODES
     * @see self::CHARGE_REASON_CODES
     */
    private ?string $reasonCode;

    /**
     * @var string|null Libellé de raison en texte libre
     *                  BT-97 (remise) / BT-104 (majoration)
     */
    private ?string $reason;

    /**
     * @var string Catégorie de TVA associée à cette remise/majoration
     *             Nécessaire pour imputer correctement sur la ventilation TVA (BG-23)
     *             BT-95 (remise) / BT-102 (majoration)
     */
    private string $vatCategory;

    /**
     * @var float Taux de TVA associé en pourcentage
     *            BT-96 (remise) / BT-103 (majoration)
     */
    private float $vatRate;

    // =========================================================================
    // Constructeur
    // =========================================================================

    /**
     * Constructeur
     *
     * @param bool        $chargeIndicator         true = majoration (BG-21), false = remise (BG-20)
     * @param float       $amount                  Montant positif ou nul
     * @param string      $vatCategory             Catégorie TVA selon UNCL5305 (S, Z, E, AE, K, G, O…)
     * @param float       $vatRate                 Taux TVA en %
     * @param float|null  $baseAmount              Montant de base (requis si $multiplierFactorNumeric fourni)
     * @param float|null  $multiplierFactorNumeric Pourcentage entre 0 et 100
     * @param string|null $reasonCode              Code UNCL5189 (remise) ou UNCL7161 (majoration)
     * @param string|null $reason                  Libellé raison libre
     *
     * @throws \InvalidArgumentException Si le montant est négatif, la catégorie TVA invalide,
     *                                   le montant de base négatif, ou le pourcentage hors [0;100]
     */
    public function __construct(
        bool $chargeIndicator,
        float $amount,
        string $vatCategory = 'S',
        float $vatRate = 0.0,
        ?float $baseAmount = null,
        ?float $multiplierFactorNumeric = null,
        ?string $reasonCode = null,
        ?string $reason = null
    ) {
        if ($amount < 0) {
            throw new \InvalidArgumentException(
                'Le montant de la remise/majoration ne peut pas être négatif'
            );
        }

        if (!$this->validateKeyExists($vatCategory, InvoiceConstants::VAT_CATEGORIES)) {
            throw new \InvalidArgumentException(
                "Catégorie de TVA invalide : {$vatCategory}. Catégories valides : " .
                implode(', ', array_keys(InvoiceConstants::VAT_CATEGORIES))
            );
        }

        if ($baseAmount !== null && $baseAmount < 0) {
            throw new \InvalidArgumentException('Le montant de base ne peut pas être négatif');
        }

        if ($multiplierFactorNumeric !== null &&
            ($multiplierFactorNumeric < 0 || $multiplierFactorNumeric > 100)) {
            throw new \InvalidArgumentException('Le pourcentage doit être compris entre 0 et 100');
        }

        $this->chargeIndicator         = $chargeIndicator;
        $this->amount                  = round($amount, 2);
        $this->vatCategory             = $vatCategory;
        $this->vatRate                 = $vatRate;
        $this->baseAmount              = $baseAmount !== null ? round($baseAmount, 2) : null;
        $this->multiplierFactorNumeric = $multiplierFactorNumeric;
        $this->reasonCode              = $reasonCode;
        $this->reason                  = $reason;
    }

    // =========================================================================
    // Méthodes factory
    // =========================================================================

    /**
     * Crée une remise (allowance) au niveau document — BG-20
     *
     * Raccourci pour `new AllowanceCharge(false, ...)`.
     *
     * @param float       $amount                  Montant de la remise
     * @param string      $vatCategory             Catégorie TVA (défaut : S = standard)
     * @param float       $vatRate                 Taux TVA en % (défaut : 21.0)
     * @param float|null  $baseAmount              Montant de base pour calcul par pourcentage
     * @param float|null  $multiplierFactorNumeric Pourcentage (0–100)
     * @param string|null $reasonCode              Code raison UNCL5189
     * @param string|null $reason                  Libellé raison libre
     * @return self
     * @throws \InvalidArgumentException
     */
    public static function createAllowance(
        float $amount,
        string $vatCategory = 'S',
        float $vatRate = 21.0,
        ?float $baseAmount = null,
        ?float $multiplierFactorNumeric = null,
        ?string $reasonCode = null,
        ?string $reason = null
    ): self {
        return new self(
            false,
            $amount,
            $vatCategory,
            $vatRate,
            $baseAmount,
            $multiplierFactorNumeric,
            $reasonCode,
            $reason
        );
    }

    /**
     * Crée une majoration (charge) au niveau document — BG-21
     *
     * Raccourci pour `new AllowanceCharge(true, ...)`.
     *
     * @param float       $amount                  Montant de la majoration
     * @param string      $vatCategory             Catégorie TVA (défaut : S = standard)
     * @param float       $vatRate                 Taux TVA en % (défaut : 21.0)
     * @param float|null  $baseAmount              Montant de base pour calcul par pourcentage
     * @param float|null  $multiplierFactorNumeric Pourcentage (0–100)
     * @param string|null $reasonCode              Code raison UNCL7161
     * @param string|null $reason                  Libellé raison libre
     * @return self
     * @throws \InvalidArgumentException
     */
    public static function createCharge(
        float $amount,
        string $vatCategory = 'S',
        float $vatRate = 21.0,
        ?float $baseAmount = null,
        ?float $multiplierFactorNumeric = null,
        ?string $reasonCode = null,
        ?string $reason = null
    ): self {
        return new self(
            true,
            $amount,
            $vatCategory,
            $vatRate,
            $baseAmount,
            $multiplierFactorNumeric,
            $reasonCode,
            $reason
        );
    }

    // =========================================================================
    // Validation
    // =========================================================================

    /**
     * Valide l'AllowanceCharge
     *
     * Vérifie :
     *   - Le montant est positif ou nul
     *   - La catégorie TVA est valide selon UNCL5305
     *   - Si un pourcentage est fourni, le montant de base doit l'être aussi
     *   - Cohérence : baseAmount × percent / 100 ≈ amount (tolérance 1 centime)
     *
     * @return array<string> Liste des erreurs (vide si valide)
     */
    public function validate(): array
    {
        $errors = [];

        if ($this->amount < 0) {
            $errors[] = 'Le montant ne peut pas être négatif';
        }

        if (!$this->validateKeyExists($this->vatCategory, InvoiceConstants::VAT_CATEGORIES)) {
            $errors[] = 'Catégorie de TVA invalide';
        }

        if ($this->multiplierFactorNumeric !== null && $this->baseAmount === null) {
            $errors[] = 'Le montant de base est requis si un pourcentage est fourni';
        }

        // Vérification cohérence pourcentage × base ≈ amount
        if ($this->multiplierFactorNumeric !== null && $this->baseAmount !== null) {
            $expected = round($this->baseAmount * $this->multiplierFactorNumeric / 100, 2);
            if (abs($expected - $this->amount) > 0.01) {
                $errors[] = sprintf(
                    'Montant incohérent avec base × pourcentage : %.2f × %.2f%% = %.2f ≠ %.2f',
                    $this->baseAmount,
                    $this->multiplierFactorNumeric,
                    $expected,
                    $this->amount
                );
            }
        }

        return $errors;
    }

    // =========================================================================
    // Calculs
    // =========================================================================

    /**
     * Retourne le montant de TVA calculé pour cet AllowanceCharge
     *
     * Utilisé par calculateTotals() pour impacter la ventilation TVA (BG-23).
     * Pour une remise, ce montant est négatif dans le calcul global.
     *
     * @return float Montant de TVA arrondi à 2 décimales (amount × vatRate / 100)
     */
    public function getVatAmount(): float
    {
        return round($this->amount * ($this->vatRate / 100), 2);
    }

    // =========================================================================
    // Sérialisation
    // =========================================================================

    /**
     * Retourne l'AllowanceCharge sous forme de tableau associatif
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'chargeIndicator'         => $this->chargeIndicator,
            'amount'                  => $this->amount,
            'baseAmount'              => $this->baseAmount,
            'multiplierFactorNumeric' => $this->multiplierFactorNumeric,
            'reasonCode'              => $this->reasonCode,
            'reason'                  => $this->reason,
            'vatCategory'             => $this->vatCategory,
            'vatRate'                 => $this->vatRate,
            'vatAmount'               => $this->getVatAmount(),
        ];
    }

    // =========================================================================
    // Getters
    // =========================================================================

    /**
     * Retourne l'indicateur de type (true = majoration, false = remise)
     *
     * @return bool
     */
    public function getChargeIndicator(): bool { return $this->chargeIndicator; }

    /**
     * Raccourci : retourne true si cet objet est une majoration (BG-21)
     *
     * @return bool
     */
    public function isCharge(): bool { return $this->chargeIndicator; }

    /**
     * Raccourci : retourne true si cet objet est une remise (BG-20)
     *
     * @return bool
     */
    public function isAllowance(): bool { return !$this->chargeIndicator; }

    /**
     * Retourne le montant de la remise ou majoration (BT-92 / BT-99)
     *
     * @return float
     */
    public function getAmount(): float { return $this->amount; }

    /**
     * Retourne le montant de base (BT-93 / BT-100), null si non défini
     *
     * @return float|null
     */
    public function getBaseAmount(): ?float { return $this->baseAmount; }

    /**
     * Retourne le pourcentage (BT-94 / BT-101), null si non défini
     *
     * @return float|null
     */
    public function getMultiplierFactorNumeric(): ?float { return $this->multiplierFactorNumeric; }

    /**
     * Retourne le code raison UNCL5189/UNCL7161 (BT-98 / BT-105), null si non défini
     *
     * @return string|null
     */
    public function getReasonCode(): ?string { return $this->reasonCode; }

    /**
     * Retourne le libellé de raison libre (BT-97 / BT-104), null si non défini
     *
     * @return string|null
     */
    public function getReason(): ?string { return $this->reason; }

    /**
     * Retourne la catégorie de TVA associée (BT-95 / BT-102)
     *
     * @return string
     */
    public function getVatCategory(): string { return $this->vatCategory; }

    /**
     * Retourne le taux de TVA associé en % (BT-96 / BT-103)
     *
     * @return float
     */
    public function getVatRate(): float { return $this->vatRate; }
}