<?php

/**
 * Exemple 1: Création d'une facture basique UBL.BE
 * 
 * Cet exemple montre comment créer une facture électronique complète
 * conforme à la norme UBL.BE 1.0 (Belgique)
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Peppol\Models\ElectronicAddress;

// ========== CRÉATION DE LA FACTURE ==========

$invoice = new PeppolInvoice(
    invoiceNumber: 'FAC-2025-001',
    issueDate: '2025-10-30',
    invoiceTypeCode: '380', // Facture commerciale
    currencyCode: 'EUR'
);

// ========== DÉFINITION DU FOURNISSEUR ==========

$invoice->setSellerFromData(
    name: 'ACME SPRL',
    vatId: 'BE0477472701',
    streetName: 'Rue de la Loi 123',
    postalZone: '1000',
    cityName: 'Bruxelles',
    countryCode: 'BE',
    companyId: '0477.472.701',
    email: 'facturation@acme.be',
    electronicAddressScheme: '0106', // KBO-BCE Belgique (obligatoire UBL.BE)
    electronicAddress: '0477472701'
);

// ========== DÉFINITION DU CLIENT ==========

$invoice->setBuyerFromData(
    name: 'Client SA',
    streetName: 'Avenue Louise 456',
    postalZone: '1050',
    cityName: 'Bruxelles',
    countryCode: 'BE',
    vatId: 'BE0987654321',
    electronicAddressScheme: '9925', // Numéro TVA (obligatoire UBL.BE)
    electronicAddress: 'BE0987654321'
);

// ========== RÉFÉRENCES OBLIGATOIRES UBL.BE ==========

// Au moins l'une de ces deux références est obligatoire pour UBL.BE
$invoice->setBuyerReference('REF-CLIENT-2025-001');
// OU
// $invoice->setPurchaseOrderReference('PO-2025-001');

// ========== DATE D'ÉCHÉANCE ET CONDITIONS ==========

// BR-CO-25: Date d'échéance OU conditions de paiement obligatoires
$invoice->setDueDate('2025-11-29'); // 30 jours
// OU
// $invoice->setPaymentTerms('Paiement à 30 jours fin de mois');

// ========== AJOUT DES LIGNES DE FACTURE ==========

$invoice->addLine(
    id: '1',
    name: 'Prestation de conseil',
    quantity: 8.0,
    unitCode: 'HUR', // Heures
    unitPrice: 85.00,
    vatCategory: 'S', // Taux standard
    vatRate: 21.0,
    description: 'Conseil en développement logiciel - 8 heures @ 85€/h'
);

$invoice->addLine(
    id: '2',
    name: 'Hébergement serveur',
    quantity: 1.0,
    unitCode: 'MON', // Mois
    unitPrice: 150.00,
    vatCategory: 'S',
    vatRate: 21.0,
    description: 'Hébergement mensuel - Octobre 2025'
);

$invoice->addLine(
    id: '3',
    name: 'Nom de domaine',
    quantity: 1.0,
    unitCode: 'ANN', // Année
    unitPrice: 25.00,
    vatCategory: 'S',
    vatRate: 21.0,
    description: 'Renouvellement nom de domaine'
);

// ========== INFORMATIONS DE PAIEMENT ==========

use Peppol\Models\PaymentInfo;

$paymentInfo = new PaymentInfo(
    paymentMeansCode: '30', // Virement bancaire
    iban: 'BE68539007547034',
    bic: 'GKCCBEBB',
    paymentReference: '+++123/4567/89012+++' // Référence structurée belge
);

$invoice->setPaymentInfo($paymentInfo);

// ========== DOCUMENTS JOINTS (minimum 2 pour UBL.BE) ==========

$invoice->attachFile(
    filePath: __DIR__ . '/documents/contrat.pdf',
    description: 'Contrat cadre n°2025-001',
    documentType: 'Contract'
);

$invoice->attachFile(
    filePath: __DIR__ . '/documents/conditions_generales.pdf',
    description: 'Conditions générales de vente',
    documentType: 'GeneralTermsAndConditions'
);

// ========== CALCUL DES TOTAUX ==========

$invoice->calculateTotals();

// ========== VALIDATION ==========

$errors = $invoice->getValidationErrors();

if ($invoice->isValid()) {
    echo "✅ FACTURE VALIDE\n\n";
    
    // Affichage du résumé
    $invoice->display();
    
    echo "\n--- DÉTAIL DES LIGNES ---\n";
    foreach ($invoice->getInvoiceLines() as $line) {
        echo sprintf(
            "- %s: %.2f %s × %.2f EUR = %.2f EUR HT (TVA %.0f%%)\n",
            $line->getName(),
            $line->getQuantity(),
            $line->getUnitCode(),
            $line->getUnitPrice(),
            $line->getLineAmount(),
            $line->getVatRate()
        );
    }
    
    echo "\n--- VENTILATION TVA ---\n";
    foreach ($invoice->getVatBreakdown() as $vat) {
        echo sprintf(
            "- Catégorie %s à %.0f%%: Base %.2f EUR, TVA %.2f EUR\n",
            $vat->getCategory(),
            $vat->getRate(),
            $vat->getTaxableAmount(),
            $vat->getTaxAmount()
        );
    }
    
    // ========== EXPORT XML UBL ==========
    
    $xmlPath = __DIR__ . '/output/facture_FAC-2025-001.xml';
    if ($invoice->saveXml($xmlPath)) {
        echo "\n📄 Facture XML UBL exportée: {$xmlPath}\n";
    }
    
    // ========== EXPORT JSON ==========
    
    $jsonPath = __DIR__ . '/output/facture_FAC-2025-001.json';
    if ($invoice->saveJson($jsonPath)) {
        echo "📄 Facture JSON exportée: {$jsonPath}\n";
    }
    
    // ========== AFFICHAGE INFORMATIONS DE PAIEMENT ==========
    
    echo "\n--- INFORMATIONS DE PAIEMENT ---\n";
    $payment = $invoice->getPaymentInfo();
    if ($payment) {
        echo "IBAN: " . $payment->getIban() . "\n";
        echo "BIC: " . $payment->getBic() . "\n";
        echo "Référence: " . $payment->getFormattedBelgianReference() . "\n";
    }
    
    echo "\n✨ Traitement terminé avec succès !\n";
    
} else {
    echo "❌ FACTURE INVALIDE\n\n";
    echo "Erreurs de validation:\n";
    foreach ($errors as $error) {
        echo "  - $error\n";
    }
    exit(1);
}
