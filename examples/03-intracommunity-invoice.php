<?php

/**
 * Exemple 3: Facture intracommunautaire
 * 
 * Cet exemple montre comment créer une facture intracommunautaire
 * (livraison B2B entre deux pays de l'UE) avec TVA à 0% et
 * autoliquidation de la TVA par le client.
 */

require_once __DIR__ . '/../vendor/autoload.php';

echo "=== FACTURE INTRACOMMUNAUTAIRE ===\n\n";

// ========== CRÉATION DE LA FACTURE ==========

echo "Création d'une facture intracommunautaire...\n\n";

$invoice = new PeppolInvoice(
    invoiceNumber: 'FAC-INTRA-2025-001',
    issueDate: '2025-10-30',
    invoiceTypeCode: '380', // Facture commerciale
    currencyCode: 'EUR'
);

// ========== VENDEUR BELGE ==========

echo "Configuration du vendeur (Belgique)...\n";

$invoice->setSellerFromData(
    name: 'Export International SPRL',
    vatId: 'BE0477472701',
    streetName: 'Quai du Commerce 15',
    postalZone: '2000',
    cityName: 'Anvers',
    countryCode: 'BE',
    companyId: '0477.472.701',
    email: 'export@international.be',
    electronicAddressScheme: '0106', // KBO-BCE
    electronicAddress: '0477472701'
);

// ========== ACHETEUR ALLEMAND ==========

echo "Configuration de l'acheteur (Allemagne)...\n";

$invoice->setBuyerFromData(
    name: 'Deutsche Handelsgesellschaft GmbH',
    streetName: 'Hauptstraße 42',
    postalZone: '10115',
    cityName: 'Berlin',
    countryCode: 'DE', // Allemagne
    vatId: 'DE123456789', // Numéro de TVA allemand
    email: 'einkauf@deutsche-handel.de',
    electronicAddressScheme: '9925', // Numéro de TVA
    electronicAddress: 'DE123456789'
);

// ========== RÉFÉRENCES ==========

echo "Ajout des références...\n";

$invoice->setBuyerReference('PO-DE-2025-456');
$invoice->setPurchaseOrderReference('ORDER-2025-456');
$invoice->setDueDate('2025-11-29'); // 30 jours
$invoice->setDeliveryDate('2025-10-28'); // Date de livraison

// ========== LIGNES DE FACTURE - TVA INTRACOMMUNAUTAIRE ==========

echo "Ajout des lignes de facture...\n\n";

$invoice->addLine(
    id: '1',
    name: 'Machines industrielles - Modèle A500',
    quantity: 5.0,
    unitCode: 'C62', // Unité (pièce)
    unitPrice: 2500.00,
    vatCategory: 'K', // K = Intracommunautaire
    vatRate: 0.0, // TVA à 0% (autoliquidation par le client)
    description: 'Machines industrielles haute performance avec garantie 2 ans'
);

$invoice->addLine(
    id: '2',
    name: 'Pièces de rechange',
    quantity: 50.0,
    unitCode: 'C62',
    unitPrice: 45.00,
    vatCategory: 'K', // K = Intracommunautaire
    vatRate: 0.0,
    description: 'Kit de pièces de rechange pour machines A500'
);

$invoice->addLine(
    id: '3',
    name: 'Formation technique',
    quantity: 2.0,
    unitCode: 'DAY', // Jours
    unitPrice: 800.00,
    vatCategory: 'K', // K = Intracommunautaire
    vatRate: 0.0,
    description: 'Formation sur site - 2 jours pour 5 techniciens'
);

// ========== RAISON D'EXONÉRATION TVA ==========

echo "Définition de la raison d'exonération de TVA...\n";

// Obligatoire pour les catégories E, AE, K, G, O
$invoice->setVatExemptionReason('VATEX-EU-IC'); // Livraison intracommunautaire

// ========== INFORMATIONS DE PAIEMENT ==========

echo "Configuration du paiement...\n";

use Peppol\Models\PaymentInfo;

$paymentInfo = new PaymentInfo(
    paymentMeansCode: '58', // Virement SEPA
    iban: 'BE68539007547034',
    bic: 'GKCCBEBB',
    paymentReference: 'FAC-INTRA-2025-001'
);

$invoice->setPaymentInfo($paymentInfo);

// Conditions de paiement
$invoice->setPaymentTerms('Paiement par virement SEPA à 30 jours. Autoliquidation de la TVA par le client conformément à la directive 2006/112/CE.');

// ========== DOCUMENTS JOINTS ==========

echo "Ajout des documents joints...\n";

// Document 1: Bon de livraison
$invoice->attachDocument(
    new \Peppol\Models\AttachedDocument(
        filename: 'delivery_note_456.pdf',
        fileContent: 'Contenu fictif du bon de livraison',
        mimeType: 'application/pdf',
        description: 'Bon de livraison n°456 - Livraison effectuée le 28/10/2025',
        documentType: 'DeliveryNote'
    )
);

// Document 2: Certificat intracommunautaire
$invoice->attachDocument(
    new \Peppol\Models\AttachedDocument(
        filename: 'intracom_certificate.pdf',
        fileContent: 'Contenu fictif du certificat',
        mimeType: 'application/pdf',
        description: 'Certificat de livraison intracommunautaire conforme',
        documentType: 'CommercialInvoice'
    )
);

// ========== CALCUL DES TOTAUX ==========

echo "Calcul des totaux...\n\n";

$invoice->calculateTotals();

// ========== AFFICHAGE DU RÉSUMÉ ==========

echo "=== RÉSUMÉ DE LA FACTURE INTRACOMMUNAUTAIRE ===\n\n";

echo "Facture: " . $invoice->getInvoiceNumber() . "\n";
echo "Date: " . $invoice->getIssueDate() . "\n";
echo "Échéance: " . $invoice->getDueDate() . "\n";
echo "Livraison: " . $invoice->getDeliveryDate() . "\n\n";

echo "--- Transaction intracommunautaire ---\n";
echo "De: Belgique (BE) → Vers: Allemagne (DE)\n";
echo "Régime TVA: Autoliquidation (article 196 Directive TVA)\n";
echo "Raison exonération: VATEX-EU-IC\n\n";

echo "--- Lignes ---\n";
foreach ($invoice->getInvoiceLines() as $line) {
    echo sprintf(
        "- %s: %.2f × %.2f EUR = %.2f EUR (TVA %s à %.0f%%)\n",
        $line->getName(),
        $line->getQuantity(),
        $line->getUnitPrice(),
        $line->getLineAmount(),
        $line->getVatCategory(),
        $line->getVatRate()
    );
}
echo "\n";

echo "--- Totaux ---\n";
echo sprintf("Total HT: %.2f EUR\n", $invoice->getTaxExclusiveAmount());
echo sprintf("TVA (0%% - autoliquidation): %.2f EUR\n", 0.0);
echo sprintf("Total TTC: %.2f EUR\n", $invoice->getTaxInclusiveAmount());
echo sprintf("À payer: %.2f EUR\n\n", $invoice->getPayableAmount());

echo "--- Ventilation TVA ---\n";
foreach ($invoice->getVatBreakdown() as $vat) {
    echo sprintf(
        "Catégorie %s (Intracommunautaire) à %.0f%%: Base %.2f EUR, TVA %.2f EUR\n",
        $vat->getCategory(),
        $vat->getRate(),
        $vat->getTaxableAmount(),
        $vat->getTaxAmount()
    );
    if ($vat->getExemptionReason()) {
        echo "Raison d'exonération: " . $vat->getExemptionReason() . "\n";
    }
}
echo "\n";

// ========== VALIDATION ==========

echo "=== VALIDATION ===\n\n";

$errors = $invoice->validate();

if (empty($errors)) {
    echo "✅ Facture intracommunautaire VALIDE\n\n";
} else {
    echo "❌ Erreurs de validation:\n";
    foreach ($errors as $error) {
        echo "  - {$error}\n";
    }
    echo "\n";
    exit(1);
}

// ========== EXPORT ==========

echo "=== EXPORT ===\n\n";

// Export XML
$xmlPath = __DIR__ . '/output/facture_intracommunautaire.xml';
if ($invoice->saveXml($xmlPath)) {
    echo "✅ XML exporté: {$xmlPath}\n";
}

// Export JSON
$jsonPath = __DIR__ . '/output/facture_intracommunautaire.json';
if ($invoice->saveJson($jsonPath)) {
    echo "✅ JSON exporté: {$jsonPath}\n";
}

echo "\n";

// ========== INFORMATIONS IMPORTANTES ==========

echo "=== INFORMATIONS IMPORTANTES ===\n\n";

echo "📋 Points clés pour une facture intracommunautaire:\n\n";

echo "1. TVA à 0% (catégorie K)\n";
echo "   ✅ Le vendeur ne facture pas de TVA\n";
echo "   ✅ Le client autoliquide la TVA dans son pays\n\n";

echo "2. Numéros de TVA obligatoires\n";
echo "   ✅ Vendeur: Numéro de TVA valide du pays d'origine\n";
echo "   ✅ Acheteur: Numéro de TVA valide du pays de destination\n\n";

echo "3. Raison d'exonération\n";
echo "   ✅ Code VATEX-EU-IC obligatoire\n";
echo "   ✅ Référence à l'article 138 de la directive TVA\n\n";

echo "4. Justificatifs requis\n";
echo "   ✅ Bon de livraison\n";
echo "   ✅ Preuve de transport\n";
echo "   ✅ Accusé de réception\n\n";

echo "5. Obligations déclaratives\n";
echo "   ⚠️  Déclaration Intrastat (si seuils dépassés)\n";
echo "   ⚠️  Listing des clients intracommunautaires\n";
echo "   ⚠️  Mention sur la déclaration de TVA\n\n";

echo "6. Mentions obligatoires sur la facture\n";
echo "   ✅ \"Autoliquidation de la TVA\"\n";
echo "   ✅ Numéros de TVA des deux parties\n";
echo "   ✅ Référence à l'article 196 de la directive TVA\n\n";

echo "7. Validation des numéros de TVA\n";
echo "   💡 Vérifiez toujours la validité du numéro de TVA du client\n";
echo "   💡 Utilisez le système VIES: https://ec.europa.eu/taxation_customs/vies/\n\n";

echo "✨ Facture intracommunautaire créée avec succès !\n";
