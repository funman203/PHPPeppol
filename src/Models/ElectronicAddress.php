<?php

declare(strict_types=1);

namespace Peppol\Models;

use Peppol\Core\InvoiceConstants;

/**
 * Modèle d'adresse électronique
 * 
 * Représente une adresse électronique Peppol ou autre système de routage
 * selon la norme ISO 6523 (BT-34, BT-49)
 * 
 * @package Peppol\Models
 * @author Votre Nom
 * @version 1.0
 */
class ElectronicAddress
{
    /**
     * @var string Identifiant du schéma (ex: 0106 pour KBO-BCE belge)
     */
    private string $schemeId;
    
    /**
     * @var string Identifiant électronique
     */
    private string $identifier;
    
    /**
     * Constructeur
     * 
     * @param string $schemeId Schéma d'identification selon ISO 6523
     * @param string $identifier Identifiant dans ce schéma
     * @throws \InvalidArgumentException
     */
    public function __construct(string $schemeId, string $identifier)
    {
        $this->setSchemeId($schemeId);
        $this->setIdentifier($identifier);
    }
    
    /**
     * Crée une adresse électronique KBO-BCE belge
     * 
     * @param string $kboNumber Numéro KBO-BCE (ex: 0123456789)
     * @return self
     */
    public static function createBelgianKBO(string $kboNumber): self
    {
        return new self('0106', $kboNumber);
    }
    
    /**
     * Crée une adresse électronique basée sur un numéro de TVA
     * 
     * @param string $vatNumber Numéro de TVA avec préfixe pays (ex: BE0123456789)
     * @return self
     */
    public static function createFromVAT(string $vatNumber): self
    {
        return new self('9925', $vatNumber);
    }
    
    /**
     * Crée une adresse électronique GLN (Global Location Number)
     * 
     * @param string $glnNumber Numéro GLN (13 chiffres)
     * @return self
     */
    public static function createGLN(string $glnNumber): self
    {
        return new self('0088', $glnNumber);
    }
    
    private function setSchemeId(string $schemeId): void
    {
        if (!array_key_exists($schemeId, InvoiceConstants::ELECTRONIC_ADDRESS_SCHEMES)) {
            throw new \InvalidArgumentException(
                'Schéma d\'identification électronique invalide. Schémas valides: ' . 
                implode(', ', array_keys(InvoiceConstants::ELECTRONIC_ADDRESS_SCHEMES))
            );
        }
        $this->schemeId = $schemeId;
    }
    
    private function setIdentifier(string $identifier): void
    {
        if (empty(trim($identifier))) {
            throw new \InvalidArgumentException('L\'identifiant électronique ne peut pas être vide');
        }
        $this->identifier = $identifier;
    }
    
    /**
     * Valide l'adresse électronique
     * 
     * @return array<string> Liste des erreurs (vide si valide)
     */
    public function validate(): array
    {
        $errors = [];
        
        if (!array_key_exists($this->schemeId, InvoiceConstants::ELECTRONIC_ADDRESS_SCHEMES)) {
            $errors[] = 'Schéma d\'identification invalide';
        }
        
        if (empty(trim($this->identifier))) {
            $errors[] = 'Identifiant électronique vide';
        }
        
        return $errors;
    }
    
    /**
     * Retourne l'adresse électronique sous forme de tableau
     * 
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'schemeId' => $this->schemeId,
            'identifier' => $this->identifier,
            'schemeName' => InvoiceConstants::ELECTRONIC_ADDRESS_SCHEMES[$this->schemeId] ?? 'Unknown'
        ];
    }
    
    /**
     * Retourne une représentation textuelle
     * 
     * @return string
     */
    public function __toString(): string
    {
        return sprintf(
            '%s (%s)',
            $this->identifier,
            InvoiceConstants::ELECTRONIC_ADDRESS_SCHEMES[$this->schemeId] ?? $this->schemeId
        );
    }
    
    // Getters
    public function getSchemeId(): string { return $this->schemeId; }
    public function getIdentifier(): string { return $this->identifier; }
    public function getSchemeName(): string 
    { 
        return InvoiceConstants::ELECTRONIC_ADDRESS_SCHEMES[$this->schemeId] ?? 'Unknown'; 
    }
}
