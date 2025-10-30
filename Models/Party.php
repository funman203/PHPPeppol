<?php

declare(strict_types=1);

namespace Peppol\Models;

use Peppol\Core\InvoiceValidatorTrait;

/**
 * Modèle de partie (Vendeur ou Acheteur)
 * 
 * Représente une entité commerciale (entreprise ou personne)
 * conforme à la norme EN 16931 (BG-4 pour vendeur, BG-7 pour acheteur)
 * 
 * @package Peppol\Models
 * @author Votre Nom
 * @version 1.0
 */
class Party
{
    use InvoiceValidatorTrait;
    
    /**
     * @var string Nom de la partie (BT-27, BT-44)
     */
    private string $name;
    
    /**
     * @var string|null Numéro de TVA (BT-31, BT-48)
     */
    private ?string $vatId;
    
    /**
     * @var Address Adresse postale
     */
    private Address $address;
    
    /**
     * @var string|null Numéro d'identification légale (BT-30, BT-47)
     */
    private ?string $companyId;
    
    /**
     * @var string|null Email de contact (BT-43, BT-58)
     */
    private ?string $email;
    
    /**
     * @var ElectronicAddress|null Adresse électronique (BT-34, BT-49)
     */
    private ?ElectronicAddress $electronicAddress;
    
    /**
     * @var string|null Numéro de téléphone (BT-42, BT-57)
     */
    private ?string $telephone;
    
    /**
     * Constructeur
     * 
     * @param string $name Nom ou raison sociale
     * @param Address $address Adresse postale
     * @param string|null $vatId Numéro de TVA
     * @param string|null $companyId Numéro d'entreprise
     * @param string|null $email Email de contact
     * @param ElectronicAddress|null $electronicAddress Adresse électronique
     * @param string|null $telephone Téléphone
     * @throws \InvalidArgumentException
     */
    public function __construct(
        string $name,
        Address $address,
        ?string $vatId = null,
        ?string $companyId = null,
        ?string $email = null,
        ?ElectronicAddress $electronicAddress = null,
        ?string $telephone = null
    ) {
        $this->setName($name);
        $this->address = $address;
        $this->setVatId($vatId);
        $this->companyId = $companyId;
        $this->setEmail($email);
        $this->electronicAddress = $electronicAddress;
        $this->telephone = $telephone;
    }
    
    private function setName(string $name): void
    {
        if (!$this->validateNotEmpty($name)) {
            throw new \InvalidArgumentException('Le nom ne peut pas être vide');
        }
        $this->name = $name;
    }
    
    private function setVatId(?string $vatId): void
    {
        if ($vatId !== null && !$this->validateEuropeanVat($vatId)) {
            throw new \InvalidArgumentException('Format de numéro de TVA invalide');
        }
        $this->vatId = $vatId;
    }
    
    private function setEmail(?string $email): void
    {
        if ($email !== null && !$this->validateEmail($email)) {
            throw new \InvalidArgumentException('Format d\'email invalide');
        }
        $this->email = $email;
    }
    
    /**
     * Définit l'adresse électronique
     * 
     * @param ElectronicAddress $electronicAddress
     * @return self
     */
    public function setElectronicAddress(ElectronicAddress $electronicAddress): self
    {
        $this->electronicAddress = $electronicAddress;
        return $this;
    }
    
    /**
     * Vérifie si cette partie est un vendeur belge
     * 
     * @return bool
     */
    public function isBelgianSeller(): bool
    {
        return $this->address->getCountryCode() === 'BE' && 
               $this->vatId !== null && 
               $this->validateBelgianVat($this->vatId);
    }
    
    /**
     * Valide la partie
     * 
     * @param bool $requireVat Si true, le numéro de TVA est obligatoire
     * @param bool $requireElectronicAddress Si true, l'adresse électronique est obligatoire
     * @return array<string> Liste des erreurs (vide si valide)
     */
    public function validate(bool $requireVat = false, bool $requireElectronicAddress = false): array
    {
        $errors = [];
        
        if (!$this->validateNotEmpty($this->name)) {
            $errors[] = 'Nom obligatoire';
        }
        
        $addressErrors = $this->address->validate();
        if (!empty($addressErrors)) {
            $errors = array_merge($errors, array_map(fn($e) => "Adresse: $e", $addressErrors));
        }
        
        if ($requireVat && empty($this->vatId)) {
            $errors[] = 'Numéro de TVA obligatoire';
        }
        
        if ($this->vatId !== null && !$this->validateEuropeanVat($this->vatId)) {
            $errors[] = 'Format de numéro de TVA invalide';
        }
        
        if ($this->email !== null && !$this->validateEmail($this->email)) {
            $errors[] = 'Format d\'email invalide';
        }
        
        if ($requireElectronicAddress && $this->electronicAddress === null) {
            $errors[] = 'Adresse électronique obligatoire';
        }
        
        if ($this->electronicAddress !== null) {
            $electronicErrors = $this->electronicAddress->validate();
            if (!empty($electronicErrors)) {
                $errors = array_merge($errors, array_map(fn($e) => "Adresse électronique: $e", $electronicErrors));
            }
        }
        
        return $errors;
    }
    
    /**
     * Retourne la partie sous forme de tableau
     * 
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'vatId' => $this->vatId,
            'companyId' => $this->companyId,
            'address' => $this->address->toArray(),
            'email' => $this->email,
            'telephone' => $this->telephone,
            'electronicAddress' => $this->electronicAddress?->toArray()
        ];
    }
    
    // Getters
    public function getName(): string { return $this->name; }
    public function getVatId(): ?string { return $this->vatId; }
    public function getAddress(): Address { return $this->address; }
    public function getCompanyId(): ?string { return $this->companyId; }
    public function getEmail(): ?string { return $this->email; }
    public function getElectronicAddress(): ?ElectronicAddress { return $this->electronicAddress; }
    public function getTelephone(): ?string { return $this->telephone; }
}
