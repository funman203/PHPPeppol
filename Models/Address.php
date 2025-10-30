<?php

declare(strict_types=1);

namespace Peppol\Models;

use Peppol\Core\InvoiceValidatorTrait;

/**
 * Modèle d'adresse postale
 * 
 * Représente une adresse conforme à la norme EN 16931
 * Utilisé pour les adresses de vendeur et d'acheteur
 * 
 * @package Peppol\Models
 * @author Votre Nom
 * @version 1.0
 */
class Address
{
    use InvoiceValidatorTrait;
    
    /**
     * @var string Ligne d'adresse (BT-35, BT-50)
     */
    private string $streetName;
    
    /**
     * @var string Ville (BT-37, BT-52)
     */
    private string $cityName;
    
    /**
     * @var string Code postal (BT-38, BT-53)
     */
    private string $postalZone;
    
    /**
     * @var string Code pays ISO 3166-1 alpha-2 (BT-40, BT-55)
     */
    private string $countryCode;
    
    /**
     * @var string|null Ligne d'adresse additionnelle (BT-36, BT-51)
     */
    private ?string $additionalStreetName = null;
    
    /**
     * Constructeur
     * 
     * @param string $streetName Rue/adresse
     * @param string $cityName Ville
     * @param string $postalZone Code postal
     * @param string $countryCode Code pays (ex: BE, FR, DE)
     * @param string|null $additionalStreetName Complément d'adresse
     * @throws \InvalidArgumentException
     */
    public function __construct(
        string $streetName,
        string $cityName,
        string $postalZone,
        string $countryCode,
        ?string $additionalStreetName = null
    ) {
        $this->setStreetName($streetName);
        $this->setCityName($cityName);
        $this->setPostalZone($postalZone);
        $this->setCountryCode($countryCode);
        $this->additionalStreetName = $additionalStreetName;
    }
    
    private function setStreetName(string $streetName): void
    {
        if (!$this->validateNotEmpty($streetName)) {
            throw new \InvalidArgumentException('L\'adresse ne peut pas être vide');
        }
        $this->streetName = $streetName;
    }
    
    private function setCityName(string $cityName): void
    {
        if (!$this->validateNotEmpty($cityName)) {
            throw new \InvalidArgumentException('La ville ne peut pas être vide');
        }
        $this->cityName = $cityName;
    }
    
    private function setPostalZone(string $postalZone): void
    {
        if (!$this->validateNotEmpty($postalZone)) {
            throw new \InvalidArgumentException('Le code postal ne peut pas être vide');
        }
        $this->postalZone = $postalZone;
    }
    
    private function setCountryCode(string $countryCode): void
    {
        if (!$this->validateCountryCode($countryCode)) {
            throw new \InvalidArgumentException('Code pays invalide (ISO 3166-1 alpha-2)');
        }
        $this->countryCode = strtoupper($countryCode);
    }
    
    /**
     * Valide l'adresse
     * 
     * @return array<string> Liste des erreurs (vide si valide)
     */
    public function validate(): array
    {
        $errors = [];
        
        if (!$this->validateNotEmpty($this->streetName)) {
            $errors[] = 'Adresse obligatoire';
        }
        
        if (!$this->validateNotEmpty($this->cityName)) {
            $errors[] = 'Ville obligatoire';
        }
        
        if (!$this->validateNotEmpty($this->postalZone)) {
            $errors[] = 'Code postal obligatoire';
        }
        
        if (!$this->validateCountryCode($this->countryCode)) {
            $errors[] = 'Code pays invalide';
        }
        
        return $errors;
    }
    
    /**
     * Retourne l'adresse sous forme de tableau
     * 
     * @return array<string, string|null>
     */
    public function toArray(): array
    {
        return [
            'streetName' => $this->streetName,
            'additionalStreetName' => $this->additionalStreetName,
            'cityName' => $this->cityName,
            'postalZone' => $this->postalZone,
            'countryCode' => $this->countryCode
        ];
    }
    
    // Getters
    public function getStreetName(): string { return $this->streetName; }
    public function getCityName(): string { return $this->cityName; }
    public function getPostalZone(): string { return $this->postalZone; }
    public function getCountryCode(): string { return $this->countryCode; }
    public function getAdditionalStreetName(): ?string { return $this->additionalStreetName; }
}
