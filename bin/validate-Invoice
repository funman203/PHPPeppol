#!/usr/bin/env php
<?php

/**
 * Outil CLI de validation de factures UBL.BE
 * 
 * Usage:
 *   ./bin/validate-invoice facture.xml
 *   ./bin/validate-invoice facture.xml --schematron
 *   ./bin/validate-invoice facture.xml --schematron --level=ublbe,en16931
 *   ./bin/validate-invoice facture.xml --json > report.json
 */

declare(strict_types=1);

// Autoload
$autoloadPaths = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../../autoload.php',
];

foreach ($autoloadPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        break;
    }
}

use Peppol\Formats\XmlImporter;
use Peppol\Validation\SchematronValidator;

// Couleurs ANSI
const COLOR_RESET = "\033[0m";
const COLOR_RED = "\033[31m";
const COLOR_GREEN = "\033[32m";
const COLOR_YELLOW = "\033[33m";
const COLOR_BLUE = "\033[34m";
const COLOR_CYAN = "\033[36m";

/**
 * Affiche l'aide
 */
function showHelp(): void
{
    echo <<<HELP
Outil de validation de factures UBL.BE

Usage:
  validate-invoice [options] <fichier.xml>

Options:
  --schematron           Active la validation Schematron
  --level=LEVELS         Niveaux Schematron (ublbe,en16931,peppol)
  --json                 Sort au format JSON
  --no-color             Désactive les couleurs
  --install-schematron   Installe les fichiers Schematron
  --clear-cache          Nettoie le cache Schematron
  -h, --help             Affiche cette aide

Exemples:
  validate-invoice facture.xml
  validate-invoice facture.xml --schematron
  validate-invoice facture.xml --schematron --level=ublbe,en16931
  validate-invoice facture.xml --json > report.json

HELP;
}

/**
 * Parse les arguments
 */
function parseArgs(array $argv): array
{
    $args = [
        'file' => null,
        'schematron' => false,
        'levels' => ['ublbe', 'en16931'],
        'json' => false,
        'color' => true,
        'install' => false,
        'clearCache' => false,
        'help' => false
    ];
    
    for ($i = 1; $i < count($argv); $i++) {
        $arg = $argv[$i];
        
        if ($arg === '--schematron') {
            $args['schematron'] = true;
        } elseif (strpos($arg, '--level=') === 0) {
            $levels = substr($arg, 8);
            $args['levels'] = explode(',', $levels);
        } elseif ($arg === '--json') {
            $args['json'] = true;
        } elseif ($arg === '--no-color') {
            $args['color'] = false;
        } elseif ($arg === '--install-schematron') {
            $args['install'] = true;
        } elseif ($arg === '--clear-cache') {
            $args['clearCache'] = true;
        } elseif ($arg === '-h' || $arg === '--help') {
            $args['help'] = true;
        } elseif (!$args['file'] && !str_starts_with($arg, '-')) {
            $args['file'] = $arg;
        }
    }
    
    return $args;
}

/**
 * Formate un message avec couleur
 */
function colorize(string $message, string $color, bool $useColor = true): string
{
    if (!$useColor) {
        return $message;
    }
    return $color . $message . COLOR_RESET;
}

/**
 * Affiche un en-tête
 */
function printHeader(string $title, bool $useColor = true): void
{
    echo "\n" . colorize("=== {$title} ===", COLOR_CYAN, $useColor) . "\n\n";
}

/**
 * Affiche un succès
 */
function printSuccess(string $message, bool $useColor = true): void
{
    echo colorize("✅ {$message}", COLOR_GREEN, $useColor) . "\n";
}

/**
 * Affiche une erreur
 */
function printError(string $message, bool $useColor = true): void
{
    echo colorize("❌ {$message}", COLOR_RED, $useColor) . "\n";
}

/**
 * Affiche un avertissement
 */
function printWarning(string $message, bool $useColor = true): void
{
    echo colorize("⚠️  {$message}", COLOR_YELLOW, $useColor) . "\n";
}

/**
 * Affiche une info
 */
function printInfo(string $message, bool $useColor = true): void
{
    echo colorize("ℹ️  {$message}", COLOR_BLUE, $useColor) . "\n";
}

// ========== MAIN ==========

$args = parseArgs($argv);

if ($args['help']) {
    showHelp();
    exit(0);
}

// Installation des fichiers Schematron
if ($args['install']) {
    printHeader('Installation des fichiers Schematron', $args['color']);
    
    try {
        $validator = new SchematronValidator();
        $results = $validator->installSchematronFiles(true);
        
        foreach ($results as $level => $success) {
            if ($success) {
                printSuccess("Schematron {$level} installé", $args['color']);
            } else {
                printError("Échec installation Schematron {$level}", $args['color']);
            }
        }
        
        exit(0);
    } catch (Exception $e) {
        printError("Erreur: " . $e->getMessage(), $args['color']);
        exit(1);
    }
}

// Nettoyage du cache
if ($args['clearCache']) {
    printHeader('Nettoyage du cache Schematron', $args['color']);
    
    try {
        $validator = new SchematronValidator();
        $validator->clearCache();
        printSuccess("Cache nettoyé", $args['color']);
        exit(0);
    } catch (Exception $e) {
        printError("Erreur: " . $e->getMessage(), $args['color']);
        exit(1);
    }
}

// Vérifier le fichier
if (!$args['file']) {
    printError("Erreur: Fichier XML requis", $args['color']);
    echo "\nUtilisez --help pour l'aide\n";
    exit(1);
}

if (!file_exists($args['file'])) {
    printError("Erreur: Fichier introuvable: {$args['file']}", $args['color']);
    exit(1);
}

// Charger le XML
$xmlContent = file_get_contents($args['file']);
if ($xmlContent === false) {
    printError("Erreur: Impossible de lire le fichier", $args['color']);
    exit(1);
}

$fileSize = strlen($xmlContent);
$fileName = basename($args['file']);

// ========== VALIDATION PHP ==========

if (!$args['json']) {
    printHeader("Validation de: {$fileName} ({$fileSize} octets)", $args['color']);
    
    echo "Étape 1: Validation PHP de base...\n";
}

try {
    $invoice = XmlImporter::fromUbl($xmlContent);
    $phpErrors = $invoice->validate();
    
    if (!$args['json']) {
        if (empty($phpErrors)) {
            printSuccess("Validation PHP réussie", $args['color']);
        } else {
            printError("Validation PHP échouée", $args['color']);
            echo "\nErreurs:\n";
            foreach ($phpErrors as $error) {
                echo "  - {$error}\n";
            }
        }
    }
    
    $validationResult = [
        'file' => $fileName,
        'size' => $fileSize,
        'php' => [
            'valid' => empty($phpErrors),
            'errors' => $phpErrors
        ]
    ];
    
} catch (Exception $e) {
    if ($args['json']) {
        echo json_encode([
            'file' => $fileName,
            'error' => $e->getMessage()
        ], JSON_PRETTY_PRINT);
    } else {
        printError("Erreur lors de l'import: " . $e->getMessage(), $args['color']);
    }
    exit(1);
}

// ========== VALIDATION SCHEMATRON ==========

if ($args['schematron']) {
    if (!$args['json']) {
        echo "\nÉtape 2: Validation Schematron...\n";
    }
    
    try {
        $validator = new SchematronValidator();
        $schematronResult = $validator->validate($xmlContent, $args['levels']);
        
        $validationResult['schematron'] = [
            'levels' => $args['levels'],
            'valid' => $schematronResult->isValid(),
            'errorCount' => $schematronResult->getErrorCount(),
            'warningCount' => $schematronResult->getWarningCount(),
            'infoCount' => $schematronResult->getInfoCount(),
            'errors' => array_map(fn($e) => $e->toArray(), $schematronResult->getErrors()),
            'warnings' => array_map(fn($w) => $w->toArray(), $schematronResult->getWarnings()),
            'infos' => array_map(fn($i) => $i->toArray(), $schematronResult->getInfos())
        ];
        
        if (!$args['json']) {
            echo "\n" . $schematronResult->getSummary() . "\n";
            
            if (!$schematronResult->isValid()) {
                echo "\nErreurs Schematron:\n";
                foreach ($schematronResult->getErrors() as $error) {
                    echo $error->getFormattedMessage($args['color']) . "\n";
                }
            }
            
            if ($schematronResult->hasWarnings()) {
                echo "\nAvertissements Schematron:\n";
                foreach ($schematronResult->getWarnings() as $warning) {
                    echo $warning->getFormattedMessage($args['color']) . "\n";
                }
            }
        }
        
    } catch (Exception $e) {
        if ($args['json']) {
            $validationResult['schematron'] = [
                'error' => $e->getMessage()
            ];
        } else {
            printWarning("Validation Schematron non disponible: " . $e->getMessage(), $args['color']);
        }
    }
}

// ========== RÉSULTAT FINAL ==========

if ($args['json']) {
    echo json_encode($validationResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} else {
    printHeader('Résultat final', $args['color']);
    
    $overallValid = empty($phpErrors) && 
                    (!$args['schematron'] || ($validationResult['schematron']['valid'] ?? false));
    
    if ($overallValid) {
        printSuccess("Facture VALIDE ✨", $args['color']);
        exit(0);
    } else {
        printError("Facture INVALIDE", $args['color']);
        exit(1);
    }
}
