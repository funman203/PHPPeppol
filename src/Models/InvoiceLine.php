<?php

declare(strict_types=1);

namespace Peppol\Models;

use Peppol\Core\InvoiceConstants;
use Peppol\Core\InvoiceValidatorTrait;

/**
 * Modèle de ligne de facture
 * 
 * Représente une ligne de facture conforme à la norme EN 16931 (BG-25)
 * 
 * @package Peppol\Models
 * @author Votre Nom
 * @version 1.0
 */
class InvoiceLine
{
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
        ?string $description = null
    ) {
        $this->setId($id);
        $this->setName($name);
        $this->setQuantity($quantity);
        $this->setUnitCode($unitCode);
        $this->setUnitPrice($unitPrice);
        $this->setVatCategory($vatCategory);
        $this->setVatRate($vatRate, $vatCategory);
        $this->description = $description;
        
        // Calcul automatique des montants
        $this->calculate();
    }
    
    private function setId(string $id): void
    {
        if (!$this->validateNotEmpty($id)) {
            throw new \InvalidArgumentException('L\'identifiant de ligne ne peut pas être vide');
        }
        $this->id = $id;
    }
    
    private function setName(string $name): void
    {
        if (!$this->validateNotEmpty($name)) {
            throw new \InvalidArgumentException('Le nom de l\'article ne peut pas être vide');
        }
        $this->name = $name;
    }
    
    private function setQuantity(float $quantity): void
    {
        if (!$this->validatePositiveAmount($quantity)) {
            throw new \InvalidArgumentException('La quantité doit être supérieure à 0');
        }
        $this->quantity = $quantity;
    }
    
    private function setUnitCode(string $unitCode): void
    {
        if (!$this->validateKeyExists($unitCode, InvoiceConstants::UNIT_CODES)) {
            throw new \InvalidArgumentException(
                "Code d'unité invalide: {$unitCode}. Codes valides: " . 
                implode(', ', array_keys(InvoiceConstants::UNIT_CODES))
            );
        }
        $this->unitCode = $unitCode;
    }
    
    private function setUnitPrice(float $unitPrice): void
    {
        if (!$this->validateNonNegativeAmount($unitPrice)) {
            throw new \InvalidArgumentException('Le prix unitaire ne peut pas être négatif');
        }
        $this->unitPrice = $unitPrice;
    }
    
    private function setVatCategory(string $vatCategory): void
    {
        if (!$this->validateKeyExists($vatCategory, InvoiceConstants::VAT_CATEGORIES)) {
            throw new \InvalidArgumentException(
                'Catégorie de TVA invalide. Catégories valides: ' . 
                implode(', ', array_keys(InvoiceConstants::VAT_CATEGORIES))
            );
        }
        $this->vatCategory = $vatCategory;
    }
    
    private function setVatRate(float $vatRate, string $vatCategory): void
    {
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
    private function calculate(): void
    {
        // Calcul du montant de la ligne HT (BT-131)
        $this->lineAmount = round($this->quantity * $this->unitPrice, 2);
        
        // Calcul du montant de TVA pour cette ligne
        $this->lineVatAmount = round($this->lineAmount * ($this->vatRate / 100), 2);
    }
    
    /**
     * Valide la ligne de facture
     * 
     * @param array<float>|null $allowedVatRates Taux de TVA autorisés (ex: pour validation BE)
     * @return array<string> Liste des erreurs (vide si valide)
     */
    public function validate(?array $allowedVatRates = null): array
    {
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
    public function toArray(): array
    {
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
            'lineVatAmount' => $this->lineVatAmount
        ];
    }
    
    // Getters
    public function getId(): string { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getDescription(): ?string { return $this->description; }
    public function getQuantity(): float { return $this->quantity; }
    public function getUnitCode(): string { return $this->unitCode; }
    public function getUnitPrice(): float { return $this->unitPrice; }
    public function getLineAmount(): float { return $this->lineAmount; }
    public function getVatCategory(): string { return $this->vatCategory; }
    public function getVatRate(): float { return $this->vatRate; }
    public function getLineVatAmount(): float { return $this->lineVatAmount; }
}
