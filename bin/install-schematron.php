#!/usr/bin/env php
<?php

/**
 * Script d'installation des fichiers Schematron
 * 
 * Ce script télécharge et installe :
 * - Les fichiers Schematron officiels UBL.BE, EN 16931, Peppol
 * - Les feuilles de style XSLT ISO Schematron
 * 
 * Usage: php bin/install-schematron.php
 */

declare(strict_types=1);

// Couleurs (désactivées si pas de TTY)
$isTty = function_exists('posix_isatty') && posix_isatty(STDOUT);
const GREEN = "\033[32m";
const RED = "\033[31m";
const YELLOW = "\033[33m";
const BLUE = "\033[34m";
const RESET = "\033[0m";

function printSuccess(string $msg): void {
    global $isTty;
    echo ($isTty ? GREEN : '') . "✅ {$msg}" . ($isTty ? RESET : '') . "\n";
}

function printError(string $msg): void {
    global $isTty;
    echo ($isTty ? RED : '') . "❌ {$msg}" . ($isTty ? RESET : '') . "\n";
}

function printWarning(string $msg): void {
    global $isTty;
    echo ($isTty ? YELLOW : '') . "⚠️  {$msg}" . ($isTty ? RESET : '') . "\n";
}

function printInfo(string $msg): void {
    global $isTty;
    echo ($isTty ? BLUE : '') . "ℹ️  {$msg}" . ($isTty ? RESET : '') . "\n";
}

echo "\n=== Installation des fichiers Schematron ===\n\n";

// Vérifier l'autoload
$autoloadPaths = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../../autoload.php',
];

$autoloadFound = false;
foreach ($autoloadPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $autoloadFound = true;
        break;
    }
}

if (!$autoloadFound) {
    printError("Autoload non trouvé. Lancez 'composer install' d'abord.");
    exit(1);
}

// Vérifier l'extension XSL
if (!extension_loaded('xsl')) {
    printError("Extension PHP XSL requise mais non disponible");
    echo "\nInstallez-la avec:\n";
    echo "  Ubuntu/Debian: sudo apt-get install php-xsl\n";
    echo "  CentOS/RHEL: sudo yum install php-xml\n";
    echo "  macOS: Extension incluse par défaut\n";
    echo "  Windows: Activez extension=xsl dans php.ini\n\n";
    exit(1);
}

printSuccess("Extension XSL disponible");

// Créer les répertoires
$dirs = [
    __DIR__ . '/../resources/schematron',
    __DIR__ . '/../resources/iso-schematron'
];

foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        if (@mkdir($dir, 0755, true)) {
            printSuccess("Répertoire créé: " . basename($dir));
        } else {
            printError("Impossible de créer: {$dir}");
        }
    }
}

echo "\n--- Installation XSLT ISO Schematron ---\n";

try {
    $validator = new Peppol\Validation\SchematronValidator();
    $isoResults = $validator->installIsoSchematronXslt();
    
    foreach ($isoResults as $file => $success) {
        if ($success) {
            printSuccess("XSLT ISO: {$file}");
        } else {
            printError("Échec XSLT ISO: {$file}");
        }
    }
} catch (Exception $e) {
    printError("Erreur installation XSLT ISO: " . $e->getMessage());
}

echo "\n--- Installation fichiers Schematron officiels ---\n";

try {
    $validator = new Peppol\Validation\SchematronValidator();
    $schResults = $validator->installSchematronFiles(force: true);
    
    foreach ($schResults as $level => $success) {
        if ($success) {
            printSuccess("Schematron {$level}");
        } else {
            printWarning("Échec Schematron {$level} (peut nécessiter téléchargement manuel)");
        }
    }
} catch (Exception $e) {
    printError("Erreur installation Schematron: " . $e->getMessage());
}

echo "\n--- Vérification de l'installation ---\n";

$requiredFiles = [
    'resources/iso-schematron/iso_dsdl_include.xsl',
    'resources/iso-schematron/iso_abstract_expand.xsl',
    'resources/iso-schematron/iso_svrl_for_xslt2.xsl',
];

$allPresent = true;
foreach ($requiredFiles as $file) {
    $path = __DIR__ . '/../' . $file;
    if (file_exists($path)) {
        printSuccess(basename($file));
    } else {
        printError(basename($file) . " manquant");
        $allPresent = false;
    }
}

echo "\n--- Fichiers Schematron ---\n";

$schematronFiles = [
    'resources/schematron/UBLBE_Invoice-1.0.sch' => 'UBL.BE',
    'resources/schematron/EN16931_UBL-1.3.sch' => 'EN 16931',
    'resources/schematron/PEPPOL_CIUS-UBL-1.0.sch' => 'Peppol'
];

foreach ($schematronFiles as $file => $name) {
    $path = __DIR__ . '/../' . $file;
    if (file_exists($path)) {
        $size = filesize($path);
        printSuccess("{$name} (" . number_format($size / 1024, 1) . " KB)");
    } else {
        printWarning("{$name} non installé");
    }
}

echo "\n=== Résultat ===\n\n";

if ($allPresent) {
    printSuccess("Installation réussie ! ✨");
    echo "\nVous pouvez maintenant utiliser la validation Schematron:\n";
    echo "  php bin/validate-invoice facture.xml --schematron\n\n";
    exit(0);
} else {
    printWarning("Installation partielle");
    echo "\nCertains fichiers n'ont pas pu être téléchargés automatiquement.\n";
    echo "Vous pouvez les télécharger manuellement depuis:\n";
    echo "  - UBL.BE: https://www.ubl.be/\n";
    echo "  - EN 16931: https://github.com/ConnectingEurope/eInvoicing-EN16931\n";
    echo "  - Peppol: https://github.com/OpenPEPPOL/peppol-bis-invoice-3\n";
    echo "  - ISO XSLT: https://github.com/Schematron/schematron\n\n";
    exit(1);
}
