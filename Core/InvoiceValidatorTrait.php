<?php

declare(strict_types=1);

namespace Peppol\Core;

/**
 * Trait de validation pour les factures électroniques
 * 
 * Fournit des méthodes de validation réutilisables pour différents
 * formats de données selon les normes européennes et belges.
 * 
 * @package Peppol\Core
 * @author Votre Nom
 * @version 1.0
 */
trait InvoiceValidatorTrait
{
    /**
     * Valide un format de date ISO 8601 (YYYY-MM-DD)
     * 
     * @param string $date Date à valider
     * @return bool True si valide
     */
    protected function validateDate(string $date): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }

    /**
     * Valide un numéro de TVA belge
     * 
     * Format: BE0123456789 (BE suivi de 10 chiffres)
     * Validation du modulo 97 selon les règles belges
     * 
     * @param string $vat Numéro de TVA à valider
     * @return bool True si valide
     */
    protected function validateBelgianVat(string $vat): bool
    {
        // Nettoyage du numéro
        $vat = strtoupper(str_replace([' ', '.'], '', $vat));
        
        // Vérification du format
        if (!preg_match('/^BE[0-9]{10}$/', $vat)) {
            return false;
        }
        
        // Extraction des chiffres
        $digits = substr($vat, 2);
        $check = (int)substr($digits, -2);
        $base = (int)substr($digits, 0, 8);
        
        // Validation du modulo 97
        return (97 - ($base % 97)) === $check;
    }

    /**
     * Valide un numéro de TVA européen (format basique)
     * 
     * Vérifie uniquement le format général (2 lettres + alphanumériques)
     * Ne valide pas les checksums spécifiques à chaque pays
     * 
     * @param string $vat Numéro de TVA à valider
     * @return bool True si format valide
     */
    protected function validateEuropeanVat(string $vat): bool
    {
        $vat = strtoupper(str_replace([' ', '.'], '', $vat));
        return preg_match('/^[A-Z]{2}[A-Z0-9]{2,12}$/', $vat) === 1;
    }

    /**
     * Valide un code pays ISO 3166-1 alpha-2
     * 
     * @param string $code Code pays à valider (ex: BE, FR, DE)
     * @return bool True si format valide
     */
    protected function validateCountryCode(string $code): bool
    {
        return preg_match('/^[A-Z]{2}$/', strtoupper($code)) === 1;
    }

    /**
     * Valide un numéro IBAN
     * 
     * Validation basique du format (2 lettres + 2 chiffres + alphanumériques)
     * Ne valide pas les checksums spécifiques à chaque pays
     * 
     * @param string $iban IBAN à valider
     * @return bool True si format valide
     */
    protected function validateIban(string $iban): bool
    {
        $ibanClean = str_replace(' ', '', strtoupper($iban));
        return preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]+$/', $ibanClean) === 1;
    }

    /**
     * Valide un code BIC/SWIFT
     * 
     * Format: 6 lettres + 2 alphanumériques + optionnellement 3 alphanumériques
     * (8 ou 11 caractères au total)
     * 
     * @param string $bic Code BIC à valider
     * @return bool True si format valide
     */
    protected function validateBic(string $bic): bool
    {
        return preg_match('/^[A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?$/', strtoupper($bic)) === 1;
    }

    /**
     * Valide une référence structurée belge selon le modulo 97
     * 
     * Format: 12 chiffres où les 2 derniers sont le checksum
     * Exemple: 123456789012 où 12 est le checksum de 1234567890
     * 
     * @param string $reference Référence de 12 chiffres (sans formatage)
     * @return bool True si valide selon modulo 97
     */
    protected function validateBelgianStructuredReference(string $reference): bool
    {
        if (strlen($reference) !== 12) {
            return false;
        }
        
        $base = (int)substr($reference, 0, 10);
        $check = (int)substr($reference, 10, 2);
        
        $calculated = $base % 97;
        if ($calculated === 0) {
            $calculated = 97;
        }
        
        return $calculated === $check;
    }

    /**
     * Valide un email
     * 
     * @param string $email Email à valider
     * @return bool True si format valide
     */
    protected function validateEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Valide qu'une valeur existe dans un tableau de constantes
     * 
     * @param mixed $value Valeur à valider
     * @param array $allowedValues Valeurs autorisées
     * @param string $fieldName Nom du champ (pour message d'erreur)
     * @return bool True si valide
     */
    protected function validateInList($value, array $allowedValues, string $fieldName = 'Valeur'): bool
    {
        return in_array($value, $allowedValues, true);
    }

    /**
     * Valide qu'une clé existe dans un tableau associatif
     * 
     * @param string $key Clé à valider
     * @param array $allowedKeys Clés autorisées
     * @param string $fieldName Nom du champ (pour message d'erreur)
     * @return bool True si valide
     */
    protected function validateKeyExists(string $key, array $allowedKeys, string $fieldName = 'Clé'): bool
    {
        return array_key_exists($key, $allowedKeys);
    }

    /**
     * Valide qu'une chaîne n'est pas vide après trim
     * 
     * @param string $value Valeur à valider
     * @return bool True si non vide
     */
    protected function validateNotEmpty(string $value): bool
    {
        return trim($value) !== '';
    }

    /**
     * Valide qu'un montant est positif
     * 
     * @param float $amount Montant à valider
     * @return bool True si > 0
     */
    protected function validatePositiveAmount(float $amount): bool
    {
        return $amount > 0;
    }

    /**
     * Valide qu'un montant est positif ou nul
     * 
     * @param float $amount Montant à valider
     * @return bool True si >= 0
     */
    protected function validateNonNegativeAmount(float $amount): bool
    {
        return $amount >= 0;
    }
}
