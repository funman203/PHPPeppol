<?php

declare(strict_types=1);

namespace Peppol\Standards;

use Peppol\Core\InvoiceBase;
use Peppol\Core\InvoiceConstants;
use Peppol\Models\Party;
use Peppol\Models\Address;
use Peppol\Models\ElectronicAddress;
use Peppol\Models\InvoiceLine;
use Peppol\Models\AttachedDocument;

/**
 * Implémentation d'une facture conforme à la norme EN 16931
 * 
 * Cette classe implémente les Business Rules (BR) de la norme européenne EN 16931
 * pour la facturation électronique.
 * 
 * @package Peppol\Standards
 * @author Votre Nom
 * @version 1.0
 * @link https://docs.peppol.eu/poacc/billing/3.0/
 */
class EN16931Invoice extends InvoiceBase
{
 
    
    /**
     * Crée une facture EN 16931 avec des méthodes helper simplifiées
     * 
     * @param string $invoiceNumber
     * @param string $issueDate
     * @param string $invoiceTypeCode
     * @param string $currencyCode
     */
    public function __construct(
        string $invoiceNumber,
        string $issueDate,
        string $invoiceTypeCode = '380',
        string $currencyCode = 'EUR'
    ) {
        parent::__construct($invoiceNumber, $issueDate, $invoiceTypeCode, $currencyCode);
    }
    
    /**
     * Définit le fournisseur avec des paramètres simples
     * 
     * @param string $name Nom
     * @param string $vatId Numéro de TVA
     * @param string $streetName Rue
     * @param string $postalZone Code postal
     * @param string $cityName Ville
     * @param string $countryCode Code pays
     * @param string|null $companyId Numéro d'entreprise
     * @param string|null $email Email
     * @param string|null $electronicAddressScheme Schéma adresse électronique
     * @param string|null $electronicAddress Adresse électronique
     * @return static
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
    ): static {
        $address = new Address($streetName, $cityName, $postalZone, $countryCode);
        
        $electronicAddr = null;
        if ($electronicAddressScheme !== null && $electronicAddress !== null) {
            $electronicAddr = new ElectronicAddress($electronicAddressScheme, $electronicAddress);
        }
        
        $seller = new Party($name, $address, $vatId, $companyId, $email, $electronicAddr);
        
        return $this->setSeller($seller);
    }
    
    /**
     * Définit le client avec des paramètres simples
     * 
     * @param string $name Nom
     * @param string $streetName Rue
     * @param string $postalZone Code postal
     * @param string $cityName Ville
     * @param string $countryCode Code pays
     * @param string|null $vatId Numéro de TVA
     * @param string|null $email Email
     * @param string|null $electronicAddressScheme Schéma adresse électronique
     * @param string|null $electronicAddress Adresse électronique
     * @return static
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
    ): static {
        $address = new Address($streetName, $cityName, $postalZone, $countryCode);
        
        $electronicAddr = null;
        if ($electronicAddressScheme !== null && $electronicAddress !== null) {
            $electronicAddr = new ElectronicAddress($electronicAddressScheme, $electronicAddress);
        }
        
        $buyer = new Party($name, $address, $vatId, null, $email, $electronicAddr);
        
        return $this->setBuyer($buyer);
    }
    
    /**
     * Ajoute une ligne de facture avec des paramètres simples
     * 
     * @param string $id Identifiant
     * @param string $name Nom
     * @param float $quantity Quantité
     * @param string $unitCode Code unité
     * @param float $unitPrice Prix unitaire
     * @param string $vatCategory Catégorie TVA
     * @param float $vatRate Taux TVA
     * @param string|null $description Description
     * @return static
     */
    public function addLine(
        string $id,
        string $name,
        float $quantity,
        string $unitCode,
        float $unitPrice,
        string $vatCategory,
        float $vatRate,
        ?string $description = null
    ): static {
        $line = new InvoiceLine(
            $id,
            $name,
            $quantity,
            $unitCode,
            $unitPrice,
            $vatCategory,
            $vatRate,
            $description
        );
        
        return $this->addInvoiceLine($line);
    }
    
    /**
     * Joint un document depuis un fichier
     * 
     * @param string $filePath Chemin du fichier
     * @param string|null $description Description
     * @return static
     */
    public function attachFile(
        string $filePath,
        ?string $description = null,
    ): static {
        $document = AttachedDocument::fromFile($filePath, $description);
        return $this->attachDocument($document);
    }
    
    /**
     * Validation EN 16931 spécifique
     * 
     * @return array<string>
     */
    public function validate(): array
    {
        $errors = parent::validate();
        
        // Validation spécifique EN 16931 (au-delà des BR de base)
        
        // Vérification cohérence devise
        foreach ($this->invoiceLines as $line) {
            // Toutes les lignes doivent utiliser la même devise (implicite)
        }
        
        return $errors;
    }
    
    /**
     * Retourne l'identifiant de customisation pour EN 16931
     * 
     * @return string
     */
    public function getCustomizationId(): string
    {
        return InvoiceConstants::CUSTOMIZATION_PEPPOL;
    }
    
    /**
     * Retourne l'identifiant de profil
     * 
     * @return string
     */
    public function getProfileId(): string
    {
        return InvoiceConstants::PROFILE_PEPPOL;
    }
    

    
    
    
}
