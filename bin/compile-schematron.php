#!/usr/bin/env php
<?php

/**
 * Compile les fichiers Schematron en XSLT
 * 
 * Stratégie :
 * - UBL.BE : Télécharger ZIP → Extraire .sch → Compiler
 * - EN 16931 : Télécharger .xsl pré-compilé officiel (pas de compilation)
 * - Peppol : Télécharger .sch → Compiler
 * 
 * Usage:
 *   php bin/compile-schematron.php --all
 *   php bin/compile-schematron.php --only=ublbe
 *   php bin/compile-schematron.php --check
 */

declare(strict_types=1);

// Couleurs
$isTty = function_exists('posix_isatty') && @posix_isatty(STDOUT);
define('GREEN', $isTty ? "\033[32m" : '');
define('RED', $isTty ? "\033[31m" : '');
define('YELLOW', $isTty ? "\033[33m" : '');
define('BLUE', $isTty ? "\033[34m" : '');
define('RESET', $isTty ? "\033[0m" : '');

function printSuccess(string $msg): void {
    echo GREEN . "✅ {$msg}" . RESET . "\n";
}

function printError(string $msg): void {
    echo RED . "❌ {$msg}" . RESET . "\n";
}

function printWarning(string $msg): void {
    echo YELLOW . "⚠️  {$msg}" . RESET . "\n";
}

function printInfo(string $msg): void {
    echo BLUE . "ℹ️  {$msg}" . RESET . "\n";
}

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

echo "\n=== Compilation Schematron ===\n\n";

// Configuration des sources
$sources = [
    'ublbe' => [
        'name' => 'UBL.BE',
        'type' => 'compile',
        'source_url' => 'https://www.ubl.be/wp-content/uploads/2024/07/GLOBALUBL.BE-V1.31.zip',
        'sch_pattern' => '/UBLBE.*Invoice.*\.sch$/i',
        'output_name' => 'UBLBE_Invoice-1.0.xsl'
    ],
    'en16931' => [
        'name' => 'EN 16931',
        'type' => 'download_precompiled',
        'xsl_url' => 'https://github.com/ConnectingEurope/eInvoicing-EN16931/releases/download/validation-1.3.11/EN16931-UBL-validation.xsl',
        'sch_url' => 'https://raw.githubusercontent.com/ConnectingEurope/eInvoicing-EN16931/validation-1.3.11/schematrons/EN16931-UBL-validation.sch',
        'output_name' => 'EN16931_UBL-1.3.xsl'
    ],
    'peppol' => [
        'name' => 'Peppol BIS',
        'type' => 'compile',
        'source_url' => 'https://raw.githubusercontent.com/OpenPEPPOL/peppol-bis-invoice-3/master/rules/sch/PEPPOL-EN16931-UBL.sch',
        'output_name' => 'PEPPOL_CIUS-UBL-1.0.xsl'
    ]
];

// Parse arguments
$options = getopt('', ['all', 'only:', 'check', 'force']);
$checkOnly = isset($options['check']);
$force = isset($options['force']);
$selectedSources = [];

if (isset($options['all']) || (empty($options['only']) && !$checkOnly)) {
    $selectedSources = array_keys($sources);
} elseif (isset($options['only'])) {
    $only = is_array($options['only']) ? $options['only'] : [$options['only']];
    $selectedSources = array_intersect($only, array_keys($sources));
}

// Créer répertoires
$compiledDir = __DIR__ . '/../resources/compiled';
$tempDir = sys_get_temp_dir() . '/schematron_compile_' . uniqid();

if (!is_dir($compiledDir)) {
    mkdir($compiledDir, 0755, true);
    printSuccess("Répertoire créé: resources/compiled/");
}

mkdir($tempDir, 0755, true);

$metadata = [
    'version' => '1.0.0',
    'updated_at' => date('c'),
    'files' => []
];

// Mode vérification uniquement
if ($checkOnly) {
    printInfo("Mode vérification uniquement");
    
    $metadataPath = $compiledDir . '/metadata.json';
    if (!file_exists($metadataPath)) {
        printError("Metadata manquant: metadata.json");
        exit(1);
    }
    
    $existing = json_decode(file_get_contents($metadataPath), true);
    
    foreach ($sources as $key => $config) {
        $xslPath = $compiledDir . '/' . $config['output_name'];
        $exists = file_exists($xslPath);
        $hasMetadata = isset($existing['files'][$config['output_name']]);
        
        echo "{$config['name']}: ";
        if ($exists && $hasMetadata) {
            $size = filesize($xslPath);
            printSuccess("OK (" . number_format($size / 1024, 1) . " KB)");
        } else {
            printError("Manquant ou incomplet");
        }
    }
    
    exit(0);
}

// Compilation
foreach ($selectedSources as $key) {
    $config = $sources[$key];
    echo "\n--- {$config['name']} ---\n";
    
    $outputPath = $compiledDir . '/' . $config['output_name'];
    
    // Vérifier si déjà présent et valide
    if (!$force && file_exists($outputPath) && filesize($outputPath) > 1000) {
        printInfo("Déjà présent, utiliser --force pour recompiler");
        
        // Charger metadata existant
        $metadata['files'][$config['output_name']] = [
            'type' => $config['type'],
            'name' => $config['name'],
            'skipped' => true
        ];
        continue;
    }
    
    try {
        if ($config['type'] === 'download_precompiled') {
            // EN 16931 : Télécharger le .xsl pré-compilé officiel
            printInfo("Téléchargement du XSLT pré-compilé officiel...");
            
            $xslContent = @file_get_contents($config['xsl_url']);
            if ($xslContent === false) {
                throw new Exception("Impossible de télécharger: " . $config['xsl_url']);
            }
            
            file_put_contents($outputPath, $xslContent);
            
            // Télécharger aussi le .sch pour le checksum
            $schContent = @file_get_contents($config['sch_url']);
            $checksum = $schContent ? hash('sha256', $schContent) : 'unknown';
            
            $metadata['files'][$config['output_name']] = [
                'type' => 'precompiled_official',
                'name' => $config['name'],
                'source_url' => $config['sch_url'],
                'precompiled_url' => $config['xsl_url'],
                'source_checksum' => $checksum,
                'downloaded_at' => date('c'),
                'size' => filesize($outputPath)
            ];
            
            printSuccess("XSLT pré-compilé téléchargé (" . number_format(filesize($outputPath) / 1024, 1) . " KB)");
            
        } else {
            // UBL.BE et Peppol : Compiler
            printInfo("Compilation depuis le source .sch...");
            
            // Télécharger source
            $schContent = null;
            $schPath = null;
            
            if (isset($config['sch_pattern'])) {
                // UBL.BE : Extraire du ZIP
                printInfo("Téléchargement du ZIP...");
                $zipContent = @file_get_contents($config['source_url']);
                if ($zipContent === false) {
                    throw new Exception("Impossible de télécharger le ZIP");
                }
                
                $zipPath = $tempDir . '/ublbe.zip';
                file_put_contents($zipPath, $zipContent);
                
                $zip = new ZipArchive();
                if ($zip->open($zipPath) !== true) {
                    throw new Exception("Impossible d'ouvrir le ZIP");
                }
                
                // Trouver le fichier .sch
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $filename = $zip->getNameIndex($i);
                    if (preg_match($config['sch_pattern'], $filename)) {
                        $schContent = $zip->getFromIndex($i);
                        $schPath = $tempDir . '/' . basename($filename);
                        file_put_contents($schPath, $schContent);
                        break;
                    }
                }
                
                $zip->close();
                
                if (!$schContent) {
                    throw new Exception("Fichier .sch non trouvé dans le ZIP");
                }
                
            } else {
                // Peppol : Téléchargement direct
                printInfo("Téléchargement du .sch...");
                $schContent = @file_get_contents($config['source_url']);
                if ($schContent === false) {
                    throw new Exception("Impossible de télécharger: " . $config['source_url']);
                }
                
                $schPath = $tempDir . '/source.sch';
                file_put_contents($schPath, $schContent);
            }
            
            // Compiler avec SchematronValidator
            printInfo("Compilation en XSLT...");
            
            $validator = new Peppol\Validation\SchematronValidator();
            $xslContent = $validator->compileSchematronFile($schPath);
            
            file_put_contents($outputPath, $xslContent);
            
            $metadata['files'][$config['output_name']] = [
                'type' => 'compiled',
                'name' => $config['name'],
                'source_url' => $config['source_url'],
                'source_checksum' => hash('sha256', $schContent),
                'compiled_at' => date('c'),
                'size' => filesize($outputPath)
            ];
            
            printSuccess("Compilé avec succès (" . number_format(filesize($outputPath) / 1024, 1) . " KB)");
        }
        
    } catch (Exception $e) {
        printError("Échec: " . $e->getMessage());
        $metadata['files'][$config['output_name']] = [
            'type' => $config['type'],
            'name' => $config['name'],
            'error' => $e->getMessage(),
            'failed_at' => date('c')
        ];
    }
}

// Sauvegarder metadata
$metadataPath = $compiledDir . '/metadata.json';
file_put_contents(
    $metadataPath,
    json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
);

printSuccess("Metadata sauvegardé: metadata.json");

// Nettoyer
array_map('unlink', glob($tempDir . '/*'));
rmdir($tempDir);

echo "\n=== Résumé ===\n\n";

$successful = 0;
$failed = 0;
$skipped = 0;

foreach ($metadata['files'] as $filename => $info) {
    if (isset($info['error'])) {
        $failed++;
    } elseif (isset($info['skipped'])) {
        $skipped++;
    } else {
        $successful++;
    }
}

printInfo("Succès: {$successful}");
if ($skipped > 0) {
    printInfo("Ignorés: {$skipped}");
}
if ($failed > 0) {
    printWarning("Échecs: {$failed}");
}

echo "\nFichiers générés dans: resources/compiled/\n";
echo "Les fichiers .xsl sont prêts à être versionnés dans Git.\n\n";

exit($failed > 0 ? 1 : 0);
