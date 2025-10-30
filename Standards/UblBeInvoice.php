<?php

declare(strict_types=1);

namespace Peppol\Standards;

use Peppol\Core\InvoiceConstants;

/**
 * Implémentation d'une facture conforme à la norme UBL.BE 1.0
 * 
 * Cette classe étend EN 16931 avec les règles spécifiques belges
 * pour la facturation électronique.
 * 
 * @package Peppol\Standards
 * @author Votre Nom
 * @version 1.0
 * @link https://www.nbb.be/fr/
 */
class UblBeInvoice extends EN16931Invoice
{
    /**
     * @var string|null Référence d'acheteur (BT-10) - Obligatoire pour UBL.BE
     */
    protected ?string $buyerReference = null;
    
    /**
     * @var string|null Conditions de paiement (BT-20)
     */
    protected ?string $paymentTerms = null;
    
    /**
     * @var string|null Raison d'exonération TVA (BT-121)
     */
    protected ?string $vatExemptionReason = null;
    
    /**
     * Définit la référence d'acheteur
     * BT-10 - Obligatoire pour UBL.BE si pas de référence de commande
     * 
     * @param string $buyerReference
     * @return self
     */
    public function setBuyerReference(string $buyerReference): self
    {
        $this->buyerReference = $buyerReference;
        return $this;
    }
    
    /**
     * Définit les conditions de paiement
     * BT-20 - Obligatoire si date d'échéance non fournie et montant > 0
     * 
     * @param string $paymentTerms
     * @return self
     */
    public function setPaymentTerms(string $paymentTerms): self
    {
        $this->paymentTerms = $paymentTerms;
        
        // Met à jour aussi dans PaymentInfo si elle existe
        if ($this->paymentInfo !== null) {
            $this->paymentInfo->setPaymentTerms($paymentTerms);
        }
        
        return $this;
    }
    
    /**
     * Définit une raison d'exonération de TVA
     * Obligatoire si catégorie de TVA = E (Exonéré), AE (Autoliquidation), K, G, O
     * 
     * @param string $exemptionReason Code raison selon UNCL5305
     * @return self
     * @throws \InvalidArgumentException
     */
    public function setVatExemptionReason(string $exemptionReason): self
    {
        if (!$this->validateKeyExists($exemptionReason, InvoiceConstants::VAT_EXEMPTION_REASONS)) {
            throw new \InvalidArgumentException(
                'Code raison d\'exonération invalide. Codes valides: ' . 
                implode(', ', array_keys(InvoiceConstants::VAT_EXEMPTION_REASONS))
            );
        }
        
        $this->vatExemptionReason = $exemptionReason;
        
        // Applique la raison à toutes les ventilations TVA concernées
        foreach ($this->vatBreakdown as $vat) {
            if ($vat->requiresExemptionReason()) {
                $vat->setExemptionReason($exemptionReason);
            }
        }
        
        return $this;
    }
    
    /**
     * Surcharge pour valider le fournisseur belge
     * 
     * @param string $name
     * @param string $vatId Numéro de TVA belge obligatoire
     * @param string $streetName
     * @param string $postalZone
     * @param string $cityName
     * @param string $countryCode
     * @param string|null $companyId
     * @param string|null $email
     * @param string|null $electronicAddressScheme
     * @param string|null $electronicAddress Obligatoire pour UBL.BE
     * @return self
     * @throws \InvalidArgumentException
     */
    public function setSellerFromData(
        string $name,
        string $vatId,
        string $streetName,
        string $postalZone,
        string $cityName,
        string $countryCode,
        ?string $companyId = null,
        ?string $email = null,
        ?string $electronicAddressScheme = null,
        ?string $electronicAddress = null
    ): self {
        // UBL.BE: Adresse électronique vendeur obligatoire
        if (empty($electronicAddress)) {
            throw new \InvalidArgumentException('Adresse électronique vendeur obligatoire pour UBL.BE');
        }
        
        // Validation spécifique du numéro de TVA belge si pays = BE
        if ($countryCode === 'BE' && !$this->validateBelgianVat($vatId)) {
            throw new \InvalidArgumentException('Numéro de TVA belge invalide (format: BE0123456789 avec modulo 97)');
        }
        
        return parent::setSellerFromData(
            $name,
            $vatId,
            $streetName,
            $postalZone,
            $cityName,
            $countryCode,
            $companyId,
            $email,
            $electronicAddressScheme,
            $electronicAddress
        );
    }
    
    /**
     * Surcharge pour valider l'acheteur pour UBL.BE
     * 
     * @param string $name
     * @param string $streetName
     * @param string $postalZone
     * @param string $cityName
     * @param string $countryCode
     * @param string|null $vatId
     * @param string|null $email
     * @param string|null $electronicAddressScheme
     * @param string|null $electronicAddress Obligatoire pour UBL.BE
     * @return self
     * @throws \InvalidArgumentException
     */
    public function setBuyerFromData(
        string $name,
        string $streetName,
        string $postalZone,
        string $cityName,
        string $countryCode,
        ?string $vatId = null,
        ?string $email = null,
        ?string $electronicAddressScheme = null,
        ?string $electronicAddress = null
    ): self {
        // UBL.BE: Adresse électronique acheteur obligatoire
        if (empty($electronicAddress)) {
            throw new \InvalidArgumentException('Adresse électronique acheteur obligatoire pour UBL.BE');
        }
        
        return parent::setBuyerFromData(
            $name,
            $streetName,
            $postalZone,
            $cityName,
            $countryCode,
            $vatId,
            $email,
            $electronicAddressScheme,
            $electronicAddress
        );
    }
    
    /**
     * Validation UBL.BE spécifique
     * 
     * @return array<string>
     */
    public function validate(): array
    {
        $errors = parent::validate();
        
        // BR-CO-25: Si montant > 0, date d'échéance OU conditions de paiement obligatoires
        if ($this->payableAmount > 0 && empty($this->dueDate) && empty($this->paymentTerms)) {
            $errors[] = 'BR-CO-25: Date d\'échéance ou conditions de paiement obligatoires si montant > 0';
        }
        
        // UBL.BE: Référence acheteur OU référence commande obligatoire
        if (empty($this->buyerReference) && empty($this->purchaseOrderReference)) {
            $errors[] = 'UBL-BE: Référence acheteur (BT-10) ou référence commande (BT-13) obligatoire';
        }
        
        // UBL.BE: Adresse électronique vendeur obligatoire
        if ($this->seller->getElectronicAddress() === null) {
            $errors[] = 'UBL-BE: Adresse électronique vendeur obligatoire';
        }
        
        // UBL.BE: Adresse électronique acheteur obligatoire
        if ($this->buyer->getElectronicAddress() === null) {
            $errors[] = 'UBL-BE: Adresse électronique acheteur obligatoire';
        }
        
        // UBL-BE-01: Au moins 2 documents joints obligatoires
        if (count($this->attachedDocuments) < 2) {
            $errors[] = 'UBL-BE-01: Au moins 2 documents joints requis';
        }
        
        // Validation des taux de TVA belges pour les vendeurs belges
        if ($this->seller->getAddress()->getCountryCode() === 'BE') {
            foreach ($this->invoiceLines as $index => $line) {
                if ($line->getVatCategory() === 'S' && 
                    !in_array($line->getVatRate(), InvoiceConstants::BE_VAT_RATES)) {
                    $errors[] = "Ligne " . ($index + 1) . ": Taux de TVA belge invalide. Taux valides: " . 
                                implode('%, ', InvoiceConstants::BE_VAT_RATES) . '%';
                }
            }
        }
        
        // Validation raison d'exonération si nécessaire
        foreach ($this->vatBreakdown as $vat) {
            if ($vat->requiresExemptionReason() && empty($this->vatExemptionReason)) {
                $errors[] = "Raison d'exonération TVA obligatoire pour catégorie " . $vat->getCategory();
            }
        }
        
        return $errors;
    }
    
    /**
     * Retourne l'identifiant de customisation pour UBL.BE
     * 
     * @return string
     */
    public function getCustomizationId(): string
    {
        return InvoiceConstants::CUSTOMIZATION_UBL_BE;
    }
    
    /**
     * Retourne les données supplémentaires UBL.BE
     * 
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = parent::toArray();
        
        $data['buyerReference'] = $this->buyerReference;
        $data['paymentTerms'] = $this->paymentTerms;
        $data['vatExemptionReason'] = $this->vatExemptionReason;
        
        return $data;
    }
    
    // Getters
    public function getBuyerReference(): ?string { return $this->buyerReference; }
    public function getPaymentTerms(): ?string { return $this->paymentTerms; }
    public function getVatExemptionReason(): ?string { return $this->vatExemptionReason; }
}
