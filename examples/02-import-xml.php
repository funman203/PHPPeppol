<?php

/**
 * Exemple 2: Import et manipulation de factures XML UBL
 * 
 * Cet exemple montre comment :
 * - Importer une facture depuis XML
 * - Accéder aux données
 * - Modifier la facture
 * - Ré-exporter en XML et JSON
 * - Extraire les documents joints
 */

require_once __DIR__ . '/../vendor/autoload.php';

echo "=== IMPORT ET MANIPULATION DE FACTURES XML ===\n\n";

// ========== IMPORT DEPUIS FICHIER XML ==========

echo "Étape 1: Import d'une facture XML existante...\n";

$xmlPath = __DIR__ . '/data/facture_exemple.xml';

// Vérifier si le fichier existe, sinon créer un exemple
if (!file_exists($xmlPath)) {
    echo "⚠️  Fichier exemple non trouvé, création d'un exemple...\n";
    createExampleInvoice($xmlPath);
}

try {
    // Import du XML
    $invoice = PeppolInvoice::fromXml($xmlPath);
    echo "✅ Facture importée avec succès\n\n";
    
} catch (Exception $e) {
    echo "❌ Erreur lors de l'import: " . $e->getMessage() . "\n";
    exit(1);
}

// ========== AFFICHAGE DES INFORMATIONS ==========

echo "Étape 2: Lecture des données...\n\n";

// Informations de base
echo "--- Informations de base ---\n";
echo "Numéro: " . $invoice->getInvoiceNumber() . "\n";
echo "Date d'émission: " . $invoice->getIssueDate() . "\n";
echo "Date d'échéance: " . ($invoice->getDueDate() ?? 'Non définie') . "\n";
echo "Type: " . $invoice->getInvoiceTypeCode() . "\n";
echo "Devise: " . $invoice->getDocumentCurrencyCode() . "\n\n";

// Fournisseur
echo "--- Fournisseur ---\n";
$seller = $invoice->getSeller();
echo "Nom: " . $seller->getName() . "\n";
echo "TVA: " . $seller->getVatId() . "\n";
echo "Adresse: " . $seller->getAddress()->getStreetName() . ", ";
echo $seller->getAddress()->getPostalZone() . " " . $seller->getAddress()->getCityName() . "\n";
if ($seller->getEmail()) {
    echo "Email: " . $seller->getEmail() . "\n";
}
echo "\n";

// Client
echo "--- Client ---\n";
$buyer = $invoice->getBuyer();
echo "Nom: " . $buyer->getName() . "\n";
if ($buyer->getVatId()) {
    echo "TVA: " . $buyer->getVatId() . "\n";
}
echo "Adresse: " . $buyer->getAddress()->getStreetName() . ", ";
echo $buyer->getAddress()->getPostalZone() . " " . $buyer->getAddress()->getCityName() . "\n\n";

// Lignes de facture
echo "--- Lignes de facture ---\n";
foreach ($invoice->getInvoiceLines() as $i => $line) {
    echo sprintf(
        "%d. %s\n   Qté: %.2f %s × %.2f EUR = %.2f EUR HT (TVA %.0f%%)\n",
        $i + 1,
        $line->getName(),
        $line->getQuantity(),
        $line->getUnitCode(),
        $line->getUnitPrice(),
        $line->getLineAmount(),
        $line->getVatRate()
    );
}
echo "\n";

// Totaux
echo "--- Totaux ---\n";
echo sprintf("Total HT: %.2f EUR\n", $invoice->getTaxExclusiveAmount());
echo sprintf("Total TTC: %.2f EUR\n", $invoice->getTaxInclusiveAmount());
echo sprintf("À payer: %.2f EUR\n\n", $invoice->getPayableAmount());

// Ventilation TVA
echo "--- Ventilation TVA ---\n";
foreach ($invoice->getVatBreakdown() as $vat) {
    echo sprintf(
        "- Catégorie %s à %.0f%%: Base %.2f EUR, TVA %.2f EUR\n",
        $vat->getCategory(),
        $vat->getRate(),
        $vat->getTaxableAmount(),
        $vat->getTaxAmount()
    );
}
echo "\n";

// Informations de paiement
if ($invoice->getPaymentInfo()) {
    echo "--- Informations de paiement ---\n";
    $payment = $invoice->getPaymentInfo();
    echo "Moyen: " . $payment->getPaymentMeansCode() . "\n";
    if ($payment->getIban()) {
        echo "IBAN: " . $payment->getIban() . "\n";
    }
    if ($payment->getBic()) {
        echo "BIC: " . $payment->getBic() . "\n";
    }
    if ($payment->getPaymentReference()) {
        echo "Référence: " . $payment->getFormattedBelgianReference() . "\n";
    }
    echo "\n";
}

// Documents joints
$attachedDocs = $invoice->getAttachedDocuments();
if (!empty($attachedDocs)) {
    echo "--- Documents joints ---\n";
    foreach ($attachedDocs as $i => $doc) {
        echo sprintf(
            "%d. %s (%s) - %s\n",
            $i + 1,
            $doc->getFilename(),
            $doc->getFormattedSize(),
            $doc->getDescription() ?? 'Pas de description'
        );
    }
    echo "\n";
}

// ========== MODIFICATION DE LA FACTURE ==========

echo "Étape 3: Modification de la facture...\n\n";

// Ajouter une ligne
echo "Ajout d'une nouvelle ligne de facture...\n";
$invoice->addLine(
    id: (string)(count($invoice->getInvoiceLines()) + 1),
    name: 'Frais de traitement',
    quantity: 1.0,
    unitCode: 'C62',
    unitPrice: 25.00,
    vatCategory: 'S',
    vatRate: 21.0,
    description: 'Frais de traitement administratif'
);

// Modifier la date d'échéance
echo "Modification de la date d'échéance...\n";
$newDueDate = date('Y-m-d', strtotime($invoice->getIssueDate() . ' +45 days'));
$invoice->setDueDate($newDueDate);

// Recalculer les totaux
echo "Recalcul des totaux...\n";
$invoice->calculateTotals();

echo "✅ Modifications effectuées\n\n";

// Afficher les nouveaux totaux
echo "Nouveaux totaux:\n";
echo sprintf("Total HT: %.2f EUR\n", $invoice->getTaxExclusiveAmount());
echo sprintf("Total TTC: %.2f EUR\n", $invoice->getTaxInclusiveAmount());
echo sprintf("Nouvelle échéance: %s\n\n", $invoice->getDueDate());

// ========== VALIDATION ==========

echo "Étape 4: Validation de la facture modifiée...\n";

$errors = $invoice->validate();

if (empty($errors)) {
    echo "✅ Facture modifiée valide\n\n";
} else {
    echo "❌ Erreurs de validation:\n";
    foreach ($errors as $error) {
        echo "  - {$error}\n";
    }
    echo "\n";
}

// ========== EXTRACTION DES DOCUMENTS JOINTS ==========

if (!empty($attachedDocs)) {
    echo "Étape 5: Extraction des documents joints...\n";
    
    $extractDir = __DIR__ . '/output/extracted_documents';
    if (!is_dir($extractDir)) {
        @mkdir($extractDir, 0755, true);
    }
    
    foreach ($attachedDocs as $i => $doc) {
        $outputPath = $extractDir . '/' . $doc->getFilename();
        if ($doc->saveToFile($outputPath)) {
            echo "  ✅ Extrait: {$doc->getFilename()}\n";
        } else {
            echo "  ❌ Échec: {$doc->getFilename()}\n";
        }
    }
    echo "\n";
}

// ========== EXPORT ==========

echo "Étape 6: Export de la facture modifiée...\n";

// Export XML
$xmlOutputPath = __DIR__ . '/output/facture_modifiee.xml';
if ($invoice->saveXml($xmlOutputPath)) {
    echo "✅ XML exporté: {$xmlOutputPath}\n";
}

// Export JSON
$jsonOutputPath = __DIR__ . '/output/facture_modifiee.json';
if ($invoice->saveJson($jsonOutputPath)) {
    echo "✅ JSON exporté: {$jsonOutputPath}\n";
}

echo "\n";

// ========== COMPARAISON AVANT/APRÈS ==========

echo "=== RÉSUMÉ DES MODIFICATIONS ===\n\n";

echo "Nombre de lignes: " . count($invoice->getInvoiceLines()) . "\n";
echo "Date d'échéance: {$newDueDate}\n";
echo sprintf("Montant total TTC: %.2f EUR\n", $invoice->getTaxInclusiveAmount());

echo "\n✨ Import et modification terminés avec succès !\n";

// ========== FONCTION HELPER ==========

/**
 * Crée une facture d'exemple pour la démo
 */
function createExampleInvoice(string $path): void
{
    $invoice = new PeppolInvoice('FAC-IMPORT-001', '2025-10-15');
    
    $invoice->setSellerFromData(
        name: 'Exemple SPRL',
        vatId: 'BE0477472701',
        streetName: 'Rue Exemple 1',
        postalZone: '1000',
        cityName: 'Bruxelles',
        countryCode: 'BE',
        electronicAddressScheme: '0106',
        electronicAddress: '0477472701'
    );
    
    $invoice->setBuyerFromData(
        name: 'Client Exemple SA',
        streetName: 'Avenue Test 2',
        postalZone: '1050',
        cityName: 'Bruxelles',
        countryCode: 'BE',
        vatId: 'BE0477472701',
        electronicAddressScheme: '9925',
        electronicAddress: 'BE0477472701'
    );
    
    $invoice->setBuyerReference('REF-EXEMPLE-001');
    $invoice->setDueDate('2025-11-15');
    
    $invoice->addLine(
        id: '1',
        name: 'Service de consultation',
        quantity: 5.0,
        unitCode: 'HUR',
        unitPrice: 95.00,
        vatCategory: 'S',
        vatRate: 21.0
    );
    
    $invoice->addLine(
        id: '2',
        name: 'Matériel informatique',
        quantity: 2.0,
        unitCode: 'C62',
        unitPrice: 250.00,
        vatCategory: 'S',
        vatRate: 21.0
    );
    
    // Documents joints fictifs
    $invoice->attachDocument(
        new \Peppol\Models\AttachedDocument(
            'document1.pdf',
            'Contenu fictif du document 1',
            'application/pdf',
            'Document de référence 1',
            'CommercialInvoice'
        )
    );
    
    $invoice->attachDocument(
        new \Peppol\Models\AttachedDocument(
            'document2.pdf',
            'Contenu fictif du document 2',
            'application/pdf',
            'Conditions générales',
            'GeneralTermsAndConditions'
        )
    );
    
    $invoice->calculateTotals();
    
    // Créer le répertoire si nécessaire
    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    
    $invoice->saveXml($path);
}
