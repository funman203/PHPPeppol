<?php

declare(strict_types=1);

namespace Peppol\Models;

use Peppol\Core\InvoiceConstants;
use Peppol\Core\InvoiceValidatorTrait;
use Peppol\Models\AllowanceCharge;

/**
 * Modèle de ligne de facture
 * 
 * Représente une ligne de facture conforme à la norme EN 16931 (BG-25)
 * 
 * @package Peppol\Models
 * @author Votre Nom
 * @version 1.0
 */
class InvoiceLine {

    use InvoiceValidatorTrait;

    /**
     * @var string Identifiant de ligne (BT-126)
     */
    private string $id;

    /**
     * @var string Nom de l'article/service (BT-153)
     */
    private string $name;

    /**
     * @var float Quantité (BT-129)
     */
    private float $quantity;

    /**
     * @var string Code d'unité UN/ECE (BT-130)
     */
    private string $unitCode;

    /**
     * @var float Prix unitaire HT (BT-146)
     */
    private float $unitPrice;

    /**
     * @var string Catégorie de TVA (BT-151)
     */
    private string $vatCategory;

    /**
     * @var float Taux de TVA en % (BT-152)
     */
    private float $vatRate;

    /**
     * @var string|null Description détaillée (BT-154)
     */
    private ?string $description;

    /**
     * @var float Montant de la ligne HT (BT-131) - Calculé
     */
    private float $lineAmount;

    /**
     * @var float Montant de TVA de la ligne - Calculé
     */
    private float $lineVatAmount;

    /**
     * @var array<AllowanceCharge> Remises et majorations au niveau ligne (BG-28)
     */
    private array $lineAllowanceCharges = [];

    /**
     * @var string|null Note de ligne libre (BT-127)
     */
    private ?string $lineNote = null;

    /**
     * @var string|null Référence de ligne de commande (BT-132)
     */
    private ?string $orderLineReference = null;

    /**
     * @var string|null Date de début de la période de facturation de ligne (BT-134)
     *                  Format YYYY-MM-DD
     */
    private ?string $linePeriodStartDate = null;

    /**
     * @var string|null Date de fin de la période de facturation de ligne (BT-135)
     *                  Format YYYY-MM-DD
     */
    private ?string $linePeriodEndDate = null;

    /**
     * @var string|null Référence article vendeur (BT-155)
     */
    private ?string $sellerItemId = null;

    /**
     * @var string|null Référence article acheteur (BT-156)
     */
    private ?string $buyerItemId = null;

    /**
     * @var string|null Code de classification article (BT-158)
     *                  Ex : code UNSPSC ou CPV
     */
    private ?string $itemClassificationCode = null;

    /**
     * @var string Schéma de classification (listID de BT-158)
     *             Ex : 'STI' pour UNSPSC, 'CV2' pour CPV
     */
    private string $itemClassificationListId = 'STI';

    /**
     * @var float Somme des remises de ligne (BT-136)
     */
    private float $sumOfLineAllowances = 0.0;

    /**
     * @var float Somme des majorations de ligne (BT-141)
     */
    private float $sumOfLineCharges = 0.0;

    /**
     * Constructeur
     * 
     * @param string $id Identifiant de ligne
     * @param string $name Nom de l'article/service
     * @param float $quantity Quantité
     * @param string $unitCode Code d'unité (ex: C62, HUR, DAY)
     * @param float $unitPrice Prix unitaire HT
     * @param string $vatCategory Catégorie de TVA (S, Z, E, etc.)
     * @param float $vatRate Taux de TVA en %
     * @param string|null $description Description détaillée
     * @throws \InvalidArgumentException
     */
    public function __construct(
            string $id,
            string $name,
            float $quantity,
            string $unitCode,
            float $unitPrice,
            string $vatCategory,
            float $vatRate,
            ?string $description = null,
            array $lineAllowanceCharges = []
    ) {
        $this->setId($id);
        $this->setName($name);
        $this->setQuantity($quantity);
        $this->setUnitCode($unitCode);
        $this->setUnitPrice($unitPrice);
        $this->setVatCategory($vatCategory);
        $this->setVatRate($vatRate, $vatCategory);
        $this->description = $description;
        $this->lineNote = null;
        $this->orderLineReference = null;
        $this->linePeriodStartDate = null;
        $this->linePeriodEndDate = null;
        $this->sellerItemId = null;
        $this->buyerItemId = null;
        $this->itemClassificationCode = null;
        $this->itemClassificationListId = 'STI';
        foreach ($lineAllowanceCharges as $ac) {
            $this->addAllowanceCharge($ac);
        }
        // Calcul automatique des montants
        $this->calculate();
    }

    private function setId(string $id): void {
        if (!$this->validateNotEmpty($id)) {
            throw new \InvalidArgumentException('L\'identifiant de ligne ne peut pas être vide');
        }
        $this->id = $id;
    }

    private function setName(string $name): void {
        if (!$this->validateNotEmpty($name)) {
            throw new \InvalidArgumentException('Le nom de l\'article ne peut pas être vide');
        }
        $this->name = $name;
    }

    private function setQuantity(float $quantity): void {
        if (!$this->validatePositiveAmount($quantity)) {
            throw new \InvalidArgumentException('La quantité doit être supérieure à 0');
        }
        $this->quantity = $quantity;
    }

    private function setUnitCode(string $unitCode): void {
        if (!$this->validateKeyExists($unitCode, InvoiceConstants::UNIT_CODES)) {
            throw new \InvalidArgumentException(
                            "Code d'unité invalide: {$unitCode}. Codes valides: " .
                            implode(', ', array_keys(InvoiceConstants::UNIT_CODES))
                    );
        }
        $this->unitCode = $unitCode;
    }

    private function setUnitPrice(float $unitPrice): void {
        if (!$this->validateNonNegativeAmount($unitPrice)) {
            throw new \InvalidArgumentException('Le prix unitaire ne peut pas être négatif');
        }
        $this->unitPrice = $unitPrice;
    }

    private function setVatCategory(string $vatCategory): void {
        if (!$this->validateKeyExists($vatCategory, InvoiceConstants::VAT_CATEGORIES)) {
            throw new \InvalidArgumentException(
                            'Catégorie de TVA invalide. Catégories valides: ' .
                            implode(', ', array_keys(InvoiceConstants::VAT_CATEGORIES))
                    );
        }
        $this->vatCategory = $vatCategory;
    }

    private function setVatRate(float $vatRate, string $vatCategory): void {
        // BR-CO-14: Si catégorie S, le taux doit être > 0
        if ($vatCategory === 'S' && $vatRate <= 0) {
            throw new \InvalidArgumentException(
                            'Le taux de TVA doit être supérieur à 0 pour la catégorie Standard (S)'
                    );
        }

        // BR-CO-15: Si catégorie Z, E, G, O, le taux doit être 0
        if (in_array($vatCategory, ['Z', 'E', 'G', 'O']) && $vatRate != 0) {
            throw new \InvalidArgumentException(
                            "Le taux de TVA doit être 0 pour la catégorie {$vatCategory}"
                    );
        }

        $this->vatRate = $vatRate;
    }

    /**
     * Calcule les montants de la ligne
     * 
     * @return void
     */
    private function calculate(): void {
        // Montant brut avant remises/majorations de ligne
        $grossAmount = round($this->quantity * $this->unitPrice, 2);

        // Somme des remises et majorations (BG-28)
        $this->sumOfLineAllowances = 0.0;
        $this->sumOfLineCharges = 0.0;
        foreach ($this->lineAllowanceCharges as $ac) {
            if ($ac->isAllowance()) {
                $this->sumOfLineAllowances += $ac->getAmount();
            } else {
                $this->sumOfLineCharges += $ac->getAmount();
            }
        }
        $this->sumOfLineAllowances = round($this->sumOfLineAllowances, 2);
        $this->sumOfLineCharges = round($this->sumOfLineCharges, 2);

        // BT-131 — Montant net de la ligne
        $this->lineAmount = round($grossAmount - $this->sumOfLineAllowances + $this->sumOfLineCharges, 2);

        // Montant de TVA de la ligne
        $this->lineVatAmount = round($this->lineAmount * ($this->vatRate / 100), 2);
    }

    /**
     * Ajoute une remise ou majoration au niveau ligne (BG-28)
     *
     * Recalcule automatiquement lineAmount et lineVatAmount.
     *
     * @param AllowanceCharge $ac
     * @return self
     */
    public function addAllowanceCharge(AllowanceCharge $ac): self {
        $this->lineAllowanceCharges[] = $ac;
        $this->calculate();
        return $this;
    }

    /**
     * Définit la note de ligne (BT-127)
     *
     * @param string $note Note libre
     * @return self
     */
    public function setLineNote(string $note): self {
        $this->lineNote = $note;
        return $this;
    }

    /**
     * Définit la référence de ligne de commande (BT-132)
     *
     * @param string $ref Référence (ex : numéro de ligne du bon de commande)
     * @return self
     */
    public function setOrderLineReference(string $ref): self {
        $this->orderLineReference = $ref;
        return $this;
    }

    /**
     * Définit la période de facturation de la ligne (BG-26)
     *
     * @param string|null $startDate Date de début YYYY-MM-DD (BT-134)
     * @param string|null $endDate   Date de fin YYYY-MM-DD (BT-135)
     * @return self
     * @throws \InvalidArgumentException
     */
    public function setLinePeriod(?string $startDate, ?string $endDate): self {
        if ($startDate !== null && !\DateTime::createFromFormat('Y-m-d', $startDate)) {
            throw new \InvalidArgumentException('Format de date de début de période de ligne invalide (YYYY-MM-DD)');
        }
        if ($endDate !== null && !\DateTime::createFromFormat('Y-m-d', $endDate)) {
            throw new \InvalidArgumentException('Format de date de fin de période de ligne invalide (YYYY-MM-DD)');
        }
        if ($startDate !== null && $endDate !== null && $endDate < $startDate) {
            throw new \InvalidArgumentException(
                            'La date de fin de période de ligne ne peut pas être antérieure à la date de début'
                    );
        }

        $this->linePeriodStartDate = $startDate;
        $this->linePeriodEndDate = $endDate;
        return $this;
    }

    /**
     * Définit la référence article vendeur (BT-155)
     *
     * @param string $id Identifiant article dans le système vendeur
     * @return self
     */
    public function setSellerItemId(string $id): self {
        $this->sellerItemId = $id;
        return $this;
    }

    /**
     * Définit la référence article acheteur (BT-156)
     *
     * @param string $id Identifiant article dans le système acheteur
     * @return self
     */
    public function setBuyerItemId(string $id): self {
        $this->buyerItemId = $id;
        return $this;
    }

    /**
     * Définit le code de classification article (BT-158)
     *
     * @param string $code     Code de classification (ex : code UNSPSC ou CPV)
     * @param string $listId   Schéma de classification — 'STI' = UNSPSC, 'CV2' = CPV (défaut : 'STI')
     * @return self
     */
    public function setItemClassificationCode(string $code, string $listId = 'STI'): self {
        $this->itemClassificationCode = $code;
        $this->itemClassificationListId = $listId;
        return $this;
    }

    /**
     * Valide la ligne de facture
     * 
     * @param array<float>|null $allowedVatRates Taux de TVA autorisés (ex: pour validation BE)
     * @return array<string> Liste des erreurs (vide si valide)
     */
    public function validate(?array $allowedVatRates = null): array {
        $errors = [];

        if (!$this->validateNotEmpty($this->id)) {
            $errors[] = 'Identifiant de ligne obligatoire';
        }

        if (!$this->validateNotEmpty($this->name)) {
            $errors[] = 'Nom de l\'article obligatoire';
        }

        if (!$this->validatePositiveAmount($this->quantity)) {
            $errors[] = 'Quantité doit être > 0';
        }

        if (!$this->validateKeyExists($this->unitCode, InvoiceConstants::UNIT_CODES)) {
            $errors[] = 'Code d\'unité invalide';
        }

        if (!$this->validateNonNegativeAmount($this->unitPrice)) {
            $errors[] = 'Prix unitaire invalide';
        }

        if (!$this->validateKeyExists($this->vatCategory, InvoiceConstants::VAT_CATEGORIES)) {
            $errors[] = 'Catégorie de TVA invalide';
        }

        // Validation du taux de TVA selon le pays si fourni
        if ($allowedVatRates !== null && $this->vatCategory === 'S') {
            if (!in_array($this->vatRate, $allowedVatRates)) {
                $errors[] = 'Taux de TVA non autorisé pour ce pays';
            }
        }

        return $errors;
    }

    /**
     * Retourne la ligne sous forme de tableau
     * 
     * @return array<string, mixed>
     */
    public function toArray(): array {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unitCode' => $this->unitCode,
            'unitPrice' => $this->unitPrice,
            'lineAmount' => $this->lineAmount,
            'vatCategory' => $this->vatCategory,
            'vatRate' => $this->vatRate,
            'lineVatAmount' => $this->lineVatAmount,
            'sumOfLineAllowances' => $this->sumOfLineAllowances,
            'sumOfLineCharges' => $this->sumOfLineCharges,
            'lineAllowanceCharges' => array_map(fn($ac) => $ac->toArray(), $this->lineAllowanceCharges),
            'lineNote' => $this->lineNote,
            'orderLineReference' => $this->orderLineReference,
            'linePeriod' => ($this->linePeriodStartDate !== null || $this->linePeriodEndDate !== null) ? [
                'startDate' => $this->linePeriodStartDate,
                'endDate' => $this->linePeriodEndDate,
            ] : null,
            'sellerItemId' => $this->sellerItemId,
            'buyerItemId' => $this->buyerItemId,
            'itemClassificationCode' => $this->itemClassificationCode,
            'itemClassificationListId' => $this->itemClassificationListId,
        ];
    }

    // Getters
    public function getId(): string {
        return $this->id;
    }

    public function getName(): string {
        return $this->name;
    }

    public function getDescription(): ?string {
        return $this->description;
    }

    public function getQuantity(): float {
        return $this->quantity;
    }

    public function getUnitCode(): string {
        return $this->unitCode;
    }

    public function getUnitPrice(): float {
        return $this->unitPrice;
    }

    public function getLineAmount(): float {
        return $this->lineAmount;
    }

    public function getVatCategory(): string {
        return $this->vatCategory;
    }

    public function getVatRate(): float {
        return $this->vatRate;
    }

    public function getLineVatAmount(): float {
        return $this->lineVatAmount;
    }

    public function getLineAllowanceCharges(): array {
        return $this->lineAllowanceCharges;
    }

    public function getSumOfLineAllowances(): float {
        return $this->sumOfLineAllowances;
    }

    public function getSumOfLineCharges(): float {
        return $this->sumOfLineCharges;
    }

    public function getLineNote(): ?string {
        return $this->lineNote;
    }

    public function getOrderLineReference(): ?string {
        return $this->orderLineReference;
    }

    public function getLinePeriodStartDate(): ?string {
        return $this->linePeriodStartDate;
    }

    public function getLinePeriodEndDate(): ?string {
        return $this->linePeriodEndDate;
    }
}
