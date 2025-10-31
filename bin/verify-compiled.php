#!/usr/bin/env php
<?php

/**
 * Vérifie l'intégrité des fichiers Schematron compilés
 */

declare(strict_types=1);

echo "\n=== Vérification des fichiers compilés ===\n\n";

$compiledDir = __DIR__ . '/../resources/compiled';
$metadataPath = $compiledDir . '/metadata.json';

$errors = [];
$warnings = [];

// 1. Vérifier metadata.json
if (!file_exists($metadataPath)) {
    $errors[] = "Metadata manquant: metadata.json";
} else {
    $metadata = json_decode(file_get_contents($metadataPath), true);
    
    if (!$metadata) {
        $errors[] = "Metadata invalide (JSON corrompu)";
    } else {
        echo "✅ Metadata présent\n";
        echo "   Version: {$metadata['version']}\n";
        echo "   Mis à jour: {$metadata['updated_at']}\n\n";
        
        // 2. Vérifier chaque fichier
        $expected = [
            'UBLBE_Invoice-1.0.xsl' => 'UBL.BE',
            'EN16931_UBL-1.3.xsl' => 'EN 16931',
            'PEPPOL_CIUS-UBL-1.0.xsl' => 'Peppol BIS'
        ];
        
        foreach ($expected as $filename => $name) {
            $path = $compiledDir . '/' . $filename;
            
            echo "{$name}: ";
            
            if (!file_exists($path)) {
                echo "❌ MANQUANT\n";
                $errors[] = "{$name}: Fichier manquant ({$filename})";
            } else {
                $size = filesize($path);
                
                if ($size < 1000) {
                    echo "❌ TROP PETIT ({$size} octets)\n";
                    $errors[] = "{$name}: Fichier trop petit (probablement corrompu)";
                } else {
                    // Vérifier que c'est du XML valide
                    $content = file_get_contents($path);
                    
                    libxml_use_internal_errors(true);
                    $xml = simplexml_load_string($content);
                    
                    if ($xml === false) {
                        echo "❌ XML INVALIDE\n";
                        $errors[] = "{$name}: XML invalide";
                    } else {
                        echo "✅ OK (" . number_format($size / 1024, 1) . " KB)\n";
                        
                        // Vérifier metadata
                        if (!isset($metadata['files'][$filename])) {
                            $warnings[] = "{$name}: Absent du metadata";
                        }
                    }
                    
                    libxml_clear_errors();
                }
            }
        }
    }
}

echo "\n=== Résultat ===\n\n";

if (empty($errors) && empty($warnings)) {
    echo "✅ Tous les fichiers sont valides\n\n";
    exit(0);
}

if (!empty($warnings)) {
    echo "⚠️  Avertissements:\n";
    foreach ($warnings as $warning) {
        echo "   - {$warning}\n";
    }
    echo "\n";
}

if (!empty($errors)) {
    echo "❌ Erreurs:\n";
    foreach ($errors as $error) {
        echo "   - {$error}\n";
    }
    echo "\n";
    echo "Pour corriger: php bin/compile-schematron.php --all --force\n\n";
    exit(1);
}

exit(0);
