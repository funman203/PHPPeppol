<?php

/**
 * Exemple 5: Validation Schematron avancée
 * 
 * Cet exemple montre comment utiliser la validation Schematron
 * pour assurer une conformité 100% avec UBL.BE
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Peppol\Validation\SchematronValidator;
use Peppol\Formats\XmlExporter;

echo "=== VALIDATION SCHEMATRON UBL.BE ===\n\n";

// ========== INSTALLATION DES FICHIERS SCHEMATRON ==========

echo "Étape 1: Installation des fichiers Schematron officiels...\n";

$validator = new SchematronValidator();

// Télécharger les fichiers Schematron depuis ubl.be
$installResults = $validator->installSchematronFiles();

foreach ($installResults as $level => $success) {
    $status = $success ? '✅' : '❌';
    echo "  {$status} Schematron {$level}: " . ($success ? 'installé' : 'échec') . "\n";
}

echo "\n";

// ========== CRÉATION D'UNE FACTURE DE TEST ==========

echo "Étape 2: Création d'une facture de test...\n";

$invoice = new PeppolInvoice('FAC-SCH-001', '2025-10-30');

// Configuration complète pour UBL.BE
$invoice->setSellerFromData(
    name: 'Test Company SPRL',
    vatId: 'BE0123456789',
    streetName: 'Rue du Test 1',
    postalZone: '1000',
    cityName: 'Bruxelles',
    countryCode: 'BE',
    electronicAddressScheme: '0106',
    electronicAddress: '0123456789'
);

$invoice->setBuyerFromData(
    name: 'Client Test SA',
    streetName: 'Avenue Test 2',
    postalZone: '1050',
    cityName: 'Bruxelles',
    countryCode: 'BE',
    vatId: 'BE0987654321',
    electronicAddressScheme: '9925',
    electronicAddress: 'BE0987654321'
);

$invoice->setBuyerReference('REF-TEST-001');
$invoice->setDueDate('2025-11-29');

$invoice->addLine(
    id: '1',
    name: 'Article de test',
    quantity: 10.0,
    unitCode: 'C62',
    unitPrice: 100.00,
    vatCategory: 'S',
    vatRate: 21.0
);

// Ajouter 2 documents joints (obligatoire UBL.BE)
$invoice->attachFile(
    filePath: __DIR__ . '/documents/test_doc1.pdf',
    description: 'Document de test 1',
    documentType: 'CommercialInvoice'
);

$invoice->attachFile(
    filePath: __DIR__ . '/documents/test_doc2.pdf',
    description: 'Document de test 2',
    documentType: 'GeneralTermsAndConditions'
);

$invoice->calculateTotals();

echo "✅ Facture créée\n\n";

// ========== VALIDATION PHP DE BASE ==========

echo "Étape 3: Validation PHP de base...\n";

$phpErrors = $invoice->validate();

if (empty($phpErrors)) {
    echo "✅ Validation PHP réussie\n\n";
} else {
    echo "❌ Erreurs PHP:\n";
    foreach ($phpErrors as $error) {
        echo "  - {$error}\n";
    }
    echo "\n";
}

// ========== EXPORT XML ==========

echo "Étape 4: Export XML UBL...\n";

$exporter = new XmlExporter($invoice);
$xmlContent = $exporter->toUbl21();

echo "✅ XML généré (" . strlen($xmlContent) . " octets)\n\n";

// ========== VALIDATION SCHEMATRON ==========

echo "Étape 5: Validation Schematron...\n\n";

// Validation UBL.BE uniquement
echo "--- Validation UBL.BE ---\n";
$resultUblBe = $validator->validate($xmlContent, ['ublbe']);
echo $resultUblBe->getSummary();

if (!$resultUblBe->isValid()) {
    echo "\n" . $resultUblBe->getDetailedReport(false) . "\n";
}

// Validation EN 16931
echo "\n--- Validation EN 16931 ---\n";
$resultEn16931 = $validator->validate($xmlContent, ['en16931']);
echo $resultEn16931->getSummary();

if (!$resultEn16931->isValid()) {
    echo "\n" . $resultEn16931->getDetailedReport(false) . "\n";
}

// Validation complète (tous les niveaux)
echo "\n--- Validation complète (UBL.BE + EN 16931) ---\n";
$resultFull = $validator->validate($xmlContent, ['ublbe', 'en16931']);

echo $resultFull->getSummary();

if ($resultFull->hasWarnings()) {
    echo "\nAvertissements:\n";
    foreach ($resultFull->getWarnings() as $warning) {
        echo $warning->getFormattedMessage() . "\n";
    }
}

// ========== EXPORT DU RAPPORT ==========

if (!$resultFull->isValid()) {
    echo "\n--- Rapport détaillé ---\n";
    $resultFull->display(false);
    
    // Sauvegarder le rapport en JSON
    $reportPath = __DIR__ . '/output/validation_report.json';
    file_put_contents($reportPath, $resultFull->toJson());
    echo "\n📄 Rapport JSON sauvegardé: {$reportPath}\n";
}

// ========== VALIDATION AVEC EXPORT INTÉGRÉ ==========

echo "\n=== OPTION 2: VALIDATION LORS DE L'EXPORT ===\n\n";

echo "Export avec validation Schematron automatique...\n";

try {
    $exporter->enableSchematronValidation(true, ['ublbe', 'en16931']);
    $xmlValidated = $exporter->toUbl21();
    
    echo "✅ Export réussi avec validation Schematron\n";
    
    $outputPath = __DIR__ . '/output/facture_validee.xml';
    file_put_contents($outputPath, $xmlValidated);
    echo "📄 XML sauvegardé: {$outputPath}\n";
    
} catch (\RuntimeException $e) {
    echo "❌ Échec de la validation:\n";
    echo $e->getMessage() . "\n";
}

// ========== ANALYSE DES VIOLATIONS PAR NIVEAU ==========

echo "\n=== ANALYSE DES VIOLATIONS ===\n\n";

$violationsByLevel = $resultFull->getViolationsByLevel();

foreach ($violationsByLevel as $level => $violations) {
    echo "Niveau: " . strtoupper($level) . "\n";
    echo "  Total: " . count($violations) . " violation(s)\n";
    
    $byRole = [];
    foreach ($violations as $v) {
        $role = $v->getRole();
        $byRole[$role] = ($byRole[$role] ?? 0) + 1;
    }
    
    foreach ($byRole as $role => $count) {
        echo "  - {$role}: {$count}\n";
    }
    
    echo "\n";
}

// ========== CONSEILS D'UTILISATION ==========

echo "\n=== CONSEILS D'UTILISATION ===\n\n";

echo "1. En développement:\n";
echo "   - Utilisez la validation Schematron systématiquement\n";
echo "   - Corrigez toutes les erreurs ET warnings\n\n";

echo "2. En production:\n";
echo "   - Validation PHP rapide pour les vérifications de base\n";
echo "   - Validation Schematron en asynchrone ou périodique\n";
echo "   - Conservez les rapports de validation\n\n";

echo "3. Performance:\n";
echo "   - Le cache XSLT améliore grandement les performances\n";
echo "   - Première validation: ~500ms, suivantes: ~50ms\n\n";

echo "4. Mise à jour des schémas:\n";
echo "   \$validator->clearCache();\n";
echo "   \$validator->installSchematronFiles(true); // force=true\n\n";

// ========== NETTOYAGE ==========

echo "=== NETTOYAGE ===\n\n";

echo "Voulez-vous nettoyer le cache Schematron ? (y/n): ";
$input = trim(fgets(STDIN));

if (strtolower($input) === 'y') {
    $validator->clearCache();
    echo "✅ Cache nettoyé\n";
} else {
    echo "ℹ️ Cache conservé pour de meilleures performances\n";
}

echo "\n✨ Validation Schematron terminée !\n";
