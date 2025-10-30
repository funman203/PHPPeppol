<?php

declare(strict_types=1);

namespace Peppol\Validation;

/**
 * Résultat de validation Schematron
 * 
 * Encapsule les erreurs, warnings et informations
 * retournées par la validation Schematron.
 * 
 * @package Peppol\Validation
 * @author Votre Nom
 * @version 1.0
 */
class SchematronValidationResult
{
    /**
     * @var bool Indique si la validation a réussi
     */
    private bool $valid;
    
    /**
     * @var array<SchematronViolation> Erreurs bloquantes
     */
    private array $errors;
    
    /**
     * @var array<SchematronViolation> Avertissements (non bloquants)
     */
    private array $warnings;
    
    /**
     * @var array<SchematronViolation> Informations
     */
    private array $infos;
    
    /**
     * Constructeur
     * 
     * @param bool $valid
     * @param array<SchematronViolation> $errors
     * @param array<SchematronViolation> $warnings
     * @param array<SchematronViolation> $infos
     */
    public function __construct(
        bool $valid,
        array $errors = [],
        array $warnings = [],
        array $infos = []
    ) {
        $this->valid = $valid;
        $this->errors = $errors;
        $this->warnings = $warnings;
        $this->infos = $infos;
    }
    
    /**
     * Retourne true si la validation a réussi (aucune erreur)
     * 
     * @return bool
     */
    public function isValid(): bool
    {
        return $this->valid;
    }
    
    /**
     * Retourne toutes les erreurs
     * 
     * @return array<SchematronViolation>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
    
    /**
     * Retourne tous les avertissements
     * 
     * @return array<SchematronViolation>
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }
    
    /**
     * Retourne toutes les informations
     * 
     * @return array<SchematronViolation>
     */
    public function getInfos(): array
    {
        return $this->infos;
    }
    
    /**
     * Retourne le nombre total d'erreurs
     * 
     * @return int
     */
    public function getErrorCount(): int
    {
        return count($this->errors);
    }
    
    /**
     * Retourne le nombre total d'avertissements
     * 
     * @return int
     */
    public function getWarningCount(): int
    {
        return count($this->warnings);
    }
    
    /**
     * Retourne le nombre total d'informations
     * 
     * @return int
     */
    public function getInfoCount(): int
    {
        return count($this->infos);
    }
    
    /**
     * Retourne true si des avertissements existent
     * 
     * @return bool
     */
    public function hasWarnings(): bool
    {
        return !empty($this->warnings);
    }
    
    /**
     * Retourne toutes les violations (erreurs + warnings + infos)
     * 
     * @return array<SchematronViolation>
     */
    public function getAllViolations(): array
    {
        return array_merge($this->errors, $this->warnings, $this->infos);
    }
    
    /**
     * Retourne les violations groupées par niveau de validation
     * 
     * @return array<string, array<SchematronViolation>>
     */
    public function getViolationsByLevel(): array
    {
        $grouped = [];
        
        foreach ($this->getAllViolations() as $violation) {
            $level = $violation->getLevel();
            if (!isset($grouped[$level])) {
                $grouped[$level] = [];
            }
            $grouped[$level][] = $violation;
        }
        
        return $grouped;
    }
    
    /**
     * Retourne les violations groupées par rôle (error, warning, info)
     * 
     * @return array<string, array<SchematronViolation>>
     */
    public function getViolationsByRole(): array
    {
        return [
            'error' => $this->errors,
            'warning' => $this->warnings,
            'info' => $this->infos
        ];
    }
    
    /**
     * Retourne un résumé textuel de la validation
     * 
     * @return string
     */
    public function getSummary(): string
    {
        $status = $this->isValid() ? '✅ VALIDE' : '❌ INVALIDE';
        
        $summary = "{$status}\n";
        $summary .= sprintf("Erreurs: %d\n", $this->getErrorCount());
        $summary .= sprintf("Avertissements: %d\n", $this->getWarningCount());
        $summary .= sprintf("Informations: %d\n", $this->getInfoCount());
        
        return $summary;
    }
    
    /**
     * Retourne un rapport détaillé de la validation
     * 
     * @param bool $includeInfos Inclure les informations (défaut: false)
     * @return string
     */
    public function getDetailedReport(bool $includeInfos = false): string
    {
        $report = $this->getSummary() . "\n";
        
        if (!empty($this->errors)) {
            $report .= "\n=== ERREURS ===\n";
            foreach ($this->errors as $i => $error) {
                $report .= sprintf(
                    "%d. [%s] %s\n   Location: %s\n   Test: %s\n\n",
                    $i + 1,
                    strtoupper($error->getLevel()),
                    $error->getMessage(),
                    $error->getLocation(),
                    $error->getTest()
                );
            }
        }
        
        if (!empty($this->warnings)) {
            $report .= "\n=== AVERTISSEMENTS ===\n";
            foreach ($this->warnings as $i => $warning) {
                $report .= sprintf(
                    "%d. [%s] %s\n   Location: %s\n\n",
                    $i + 1,
                    strtoupper($warning->getLevel()),
                    $warning->getMessage(),
                    $warning->getLocation()
                );
            }
        }
        
        if ($includeInfos && !empty($this->infos)) {
            $report .= "\n=== INFORMATIONS ===\n";
            foreach ($this->infos as $i => $info) {
                $report .= sprintf(
                    "%d. [%s] %s\n\n",
                    $i + 1,
                    strtoupper($info->getLevel()),
                    $info->getMessage()
                );
            }
        }
        
        return $report;
    }
    
    /**
     * Retourne le résultat sous forme de tableau
     * 
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'valid' => $this->valid,
            'errorCount' => $this->getErrorCount(),
            'warningCount' => $this->getWarningCount(),
            'infoCount' => $this->getInfoCount(),
            'errors' => array_map(fn($e) => $e->toArray(), $this->errors),
            'warnings' => array_map(fn($w) => $w->toArray(), $this->warnings),
            'infos' => array_map(fn($i) => $i->toArray(), $this->infos)
        ];
    }
    
    /**
     * Retourne le résultat en JSON
     * 
     * @param bool $prettyPrint
     * @return string
     */
    public function toJson(bool $prettyPrint = true): string
    {
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        if ($prettyPrint) {
            $flags |= JSON_PRETTY_PRINT;
        }
        
        return json_encode($this->toArray(), $flags);
    }
    
    /**
     * Affiche le rapport détaillé
     * 
     * @param bool $includeInfos
     * @return void
     */
    public function display(bool $includeInfos = false): void
    {
        echo $this->getDetailedReport($includeInfos);
    }
}
