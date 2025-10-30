<?php

declare(strict_types=1);

namespace Peppol\Models;

/**
 * Modèle de ventilation de TVA
 * 
 * Représente une ventilation par taux de TVA conforme à la norme EN 16931 (BG-23)
 * 
 * @package Peppol\Models
 * @author Votre Nom
 * @version 1.0
 */
class VatBreakdown
{
    /**
     * @var string Catégorie de TVA (BT-118)
     */
    private string $category;
    
    /**
     * @var float Taux de TVA en % (BT-119)
     */
    private float $rate;
    
    /**
     * @var float Montant taxable (BT-116)
     */
    private float $taxableAmount;
    
    /**
     * @var float Montant de TVA (BT-117)
     */
    private float $taxAmount;
    
    /**
     * @var string|null Raison d'exonération (BT-121)
     */
    private ?string $exemptionReason;
    
    /**
     * Constructeur
     * 
     * @param string $category Catégorie de TVA (S, Z, E, etc.)
     * @param float $rate Taux de TVA en %
     * @param float $taxableAmount Montant taxable
     * @param float $taxAmount Montant de TVA
     * @param string|null $exemptionReason Raison d'exonération si applicable
     */
    public function __construct(
        string $category,
        float $rate,
        float $taxableAmount,
        float $taxAmount,
        ?string $exemptionReason = null
    ) {
        $this->category = $category;
        $this->rate = $rate;
        $this->taxableAmount = round($taxableAmount, 2);
        $this->taxAmount = round($taxAmount, 2);
        $this->exemptionReason = $exemptionReason;
    }
    
    /**
     * Crée une clé unique pour cette ventilation
     * Utilisé pour regrouper les lignes par catégorie et taux
     * 
     * @return string
     */
    public function getKey(): string
    {
        return $this->category . '_' . $this->rate;
    }
    
    /**
     * Ajoute un montant à cette ventilation
     * 
     * @param float $taxableAmount Montant taxable à ajouter
     * @param float $taxAmount Montant de TVA à ajouter
     * @return void
     */
    public function addAmount(float $taxableAmount, float $taxAmount): void
    {
        $this->taxableAmount = round($this->taxableAmount + $taxableAmount, 2);
        $this->taxAmount = round($this->taxAmount + $taxAmount, 2);
    }
    
    /**
     * Vérifie si une raison d'exonération est requise
     * 
     * @return bool
     */
    public function requiresExemptionReason(): bool
    {
        return in_array($this->category, ['E', 'AE', 'K', 'G', 'O']);
    }
    
    /**
     * Valide la ventilation de TVA
     * 
     * @return array<string> Liste des erreurs (vide si valide)
     */
    public function validate(): array
    {
        $errors = [];
        
        if ($this->requiresExemptionReason() && empty($this->exemptionReason)) {
            $errors[] = "Raison d'exonération obligatoire pour la catégorie {$this->category}";
        }
        
        if ($this->taxableAmount < 0) {
            $errors[] = 'Le montant taxable ne peut pas être négatif';
        }
        
        if ($this->taxAmount < 0) {
            $errors[] = 'Le montant de TVA ne peut pas être négatif';
        }
        
        // Vérification cohérence : taxAmount doit correspondre à taxableAmount * rate
        $expectedTaxAmount = round($this->taxableAmount * ($this->rate / 100), 2);
        if (abs($this->taxAmount - $expectedTaxAmount) > 0.02) { // Tolérance de 2 centimes
            $errors[] = 'Montant de TVA incohérent avec le montant taxable et le taux';
        }
        
        return $errors;
    }
    
    /**
     * Retourne la ventilation sous forme de tableau
     * 
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'category' => $this->category,
            'rate' => $this->rate,
            'taxableAmount' => $this->taxableAmount,
            'taxAmount' => $this->taxAmount,
            'exemptionReason' => $this->exemptionReason
        ];
    }
    
    // Getters
    public function getCategory(): string { return $this->category; }
    public function getRate(): float { return $this->rate; }
    public function getTaxableAmount(): float { return $this->taxableAmount; }
    public function getTaxAmount(): float { return $this->taxAmount; }
    public function getExemptionReason(): ?string { return $this->exemptionReason; }
    
    // Setter pour la raison d'exonération
    public function setExemptionReason(?string $exemptionReason): void
    {
        $this->exemptionReason = $exemptionReason;
    }
}
