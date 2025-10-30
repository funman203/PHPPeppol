<?php

/**
 * Exemple 4: Fonctionnalités avancées
 * 
 * Cet exemple montre :
 * - Facture avec remises
 * - Gestion de plusieurs taux de TVA
 * - Références structurées belges
 * - Notes et conditions de paiement
 * - Exportation hors UE
 * - Facture d'acompte
 * - Gestion d'erreurs avancée
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Peppol\Models\PaymentInfo;
use Peppol\Models\AttachedDocument;

echo "=== FONCTIONNALITÉS AVANCÉES ===\n\n";

// ========== CAS 1: FACTURE AVEC PLUSIEURS TAUX DE TVA ==========

echo "=== CAS 1: Plusieurs taux de TVA ===\n\n";

$invoice1 = new PeppolInvoice('FAC-MULTI-001', '2025-10-30');

$invoice1->setSellerFromData(
    name: 'Restaurant Gastronomique SPRL',
    vatId: 'BE0477472701',
    streetName: 'Place des Saveurs 8',
    postalZone: '1000',
    cityName: 'Bruxelles',
    countryCode: 'BE',
    electronicAddressScheme: '0106',
    electronicAddress: '0477472701'
);

$invoice1->setBuyerFromData(
    name: 'Entreprise Client SA',
    streetName: 'Rue du Commerce 10',
    postalZone: '1050',
    cityName: 'Bruxelles',
    countryCode: 'BE',
    vatId: 'BE0477472701',
    electronicAddressScheme: '9925',
    electronicAddress: '0477472701'
);

$invoice1->setBuyerReference('REF-RESTO-2025');
$invoice1->setDueDate('2025-11-15');

// Repas sur place - TVA 12%
$invoice1->addLine(
    id: '1',
    name: 'Menu affaires',
    quantity: 15.0,
    unitCode: 'C62',
    unitPrice: 45.00,
    vatCategory: 'S',
    vatRate: 12.0, // TVA réduite pour restauration
    description: 'Menu 3 services + boisson'
);

// Boissons alcoolisées - TVA 21%
$invoice1->addLine(
    id: '2',
    name: 'Vins et spiritueux',
    quantity: 8.0,
    unitCode: 'C62',
    unitPrice: 25.00,
    vatCategory: 'S',
    vatRate: 21.0, // TVA standard
    description: 'Sélection de vins'
);

// Produits alimentaires à emporter - TVA 6%
$invoice1->addLine(
    id: '3',
    name: 'Plateaux traiteur',
    quantity: 3.0,
    unitCode: 'C62',
    unitPrice: 120.00,
    vatCategory: 'S',
    vatRate: 6.0, // TVA réduite pour alimentation
    description: 'Plateaux traiteur pour événement'
);

$invoice1->attachDocument(
    new AttachedDocument('doc1.pdf', 'Contenu doc 1', 'application/pdf', 'Document 1')
);
$invoice1->attachDocument(
    new AttachedDocument('doc2.pdf', 'Contenu doc 2', 'application/pdf', 'Document 2')
);

$invoice1->calculateTotals();

echo "Facture: " . $invoice1->getInvoiceNumber() . "\n";
echo "Ventilation TVA:\n";
foreach ($invoice1->getVatBreakdown() as $vat) {
    echo sprintf(
        "  - TVA %.0f%%: Base %.2f EUR → TVA %.2f EUR\n",
        $vat->getRate(),
        $vat->getTaxableAmount(),
        $vat->getTaxAmount()
    );
}
echo sprintf("Total TTC: %.2f EUR\n\n", $invoice1->getTaxInclusiveAmount());

// ========== CAS 2: RÉFÉRENCE STRUCTURÉE BELGE ==========

echo "=== CAS 2: Référence structurée belge ===\n\n";

$invoice2 = new PeppolInvoice('FAC-REF-002', '2025-10-30');

$invoice2->setSellerFromData(
    name: 'Services Pro SPRL',
    vatId: 'BE0477472701',
    streetName: 'Avenue Pro 20',
    postalZone: '1060',
    cityName: 'Bruxelles',
    countryCode: 'BE',
    electronicAddressScheme: '0106',
    electronicAddress: '0477472701'
);

$invoice2->setBuyerFromData(
    name: 'Client Particulier',
    streetName: 'Rue Privée 5',
    postalZone: '1070',
    cityName: 'Bruxelles',
    countryCode: 'BE',
    electronicAddressScheme: '9925',
    electronicAddress: 'BE0000000000'
);

$invoice2->setBuyerReference('CLIENT-2025-789');
$invoice2->setDueDate('2025-11-30');

$invoice2->addLine(
    id: '1',
    name: 'Prestation de service',
    quantity: 1.0,
    unitCode: 'C62',
    unitPrice: 850.00,
    vatCategory: 'S',
    vatRate: 21.0
);

// Référence structurée belge avec validation modulo 97
$paymentInfo = new PaymentInfo(
    paymentMeansCode: '30', // Virement
    iban: 'BE68539007547034',
    bic: 'GKCCBEBB'
);

// Générer une référence structurée valide
$baseNumber = '1234567890'; // 10 chiffres
$checksum = 97 - ((int)$baseNumber % 97);
if ($checksum === 0) {
    $checksum = 97;
}
$structuredRef = $baseNumber . str_pad((string)$checksum, 2, '0', STR_PAD_LEFT);

try {
    $paymentInfo->setBelgianStructuredReference($structuredRef);
    echo "✅ Référence structurée générée: " . $paymentInfo->getFormattedBelgianReference() . "\n";
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
}

$invoice2->setPaymentInfo($paymentInfo);
$invoice2->attachDocument(
    new AttachedDocument('doc1.pdf', 'Contenu', 'application/pdf', 'Doc 1')
);
$invoice2->attachDocument(
    new AttachedDocument('doc2.pdf', 'Contenu', 'application/pdf', 'Doc 2')
);

$invoice2->calculateTotals();
echo "\n";

// ========== CAS 3: EXPORTATION HORS UE ==========

echo "=== CAS 3: Exportation hors UE (TVA 0%) ===\n\n";

$invoice3 = new PeppolInvoice('FAC-EXPORT-003', '2025-10-30');

$invoice3->setSellerFromData(
    name: 'Global Export SPRL',
    vatId: 'BE0477472701',
    streetName: 'Port de Bruxelles 1',
    postalZone: '1000',
    cityName: 'Bruxelles',
    countryCode: 'BE',
    electronicAddressScheme: '0106',
    electronicAddress: '0477472701'
);

$invoice3->setBuyerFromData(
    name: 'US Company Inc.',
    streetName: '123 Main Street',
    postalZone: '10001',
    cityName: 'New York',
    countryCode: 'US', // États-Unis (hors UE)
    electronicAddressScheme: '9925',
    electronicAddress: 'US123456789'
);

$invoice3->setBuyerReference('US-ORDER-2025');
$invoice3->setDueDate('2025-11-30');

$invoice3->addLine(
    id: '1',
    name: 'Produits manufacturés',
    quantity: 100.0,
    unitCode: 'C62',
    unitPrice: 150.00,
    vatCategory: 'G', // G = Exportation hors UE
    vatRate: 0.0
);

// Raison d'exonération obligatoire
$invoice3->setVatExemptionReason('VATEX-EU-G');

$invoice3->attachDocument(
    new AttachedDocument('customs_doc.pdf', 'Contenu', 'application/pdf', 'Document douanier')
);
$invoice3->attachDocument(
    new AttachedDocument('export_license.pdf', 'Contenu', 'application/pdf', 'Licence export')
);

$invoice3->calculateTotals();

echo "Facture: " . $invoice3->getInvoiceNumber() . "\n";
echo "Destination: États-Unis (hors UE)\n";
echo sprintf("Montant HT: %.2f EUR (TVA 0%% - exportation)\n", $invoice3->getTaxExclusiveAmount());
echo "Raison exonération: VATEX-EU-G\n\n";

// ========== CAS 4: FACTURE D'ACOMPTE ==========

echo "=== CAS 4: Facture d'acompte ===\n\n";

$invoice4 = new PeppolInvoice(
    invoiceNumber: 'FAC-ACOMPTE-004',
    issueDate: '2025-10-30',
    invoiceTypeCode: '386', // 386 = Facture d'acompte
    currencyCode: 'EUR'
);

$invoice4->setSellerFromData(
    name: 'Construction SPRL',
    vatId: 'BE0477472701',
    streetName: 'Rue du Chantier 30',
    postalZone: '1080',
    cityName: 'Bruxelles',
    countryCode: 'BE',
    electronicAddressScheme: '0106',
    electronicAddress: '0477472701'
);

$invoice4->setBuyerFromData(
    name: 'Promoteur Immobilier SA',
    streetName: 'Avenue Construction 15',
    postalZone: '1090',
    cityName: 'Bruxelles',
    countryCode: 'BE',
    vatId: 'BE0477472701',
    electronicAddressScheme: '9925',
    electronicAddress: '0477472701'
);

$invoice4->setBuyerReference('PROJET-2025-XYZ');
$invoice4->setContractReference('CONTRAT-2025-001');
$invoice4->setDueDate('2025-11-15');

// Acompte de 30% sur un projet de 100.000 EUR
$invoice4->addLine(
    id: '1',
    name: 'Acompte sur travaux de construction',
    quantity: 1.0,
    unitCode: 'C62',
    unitPrice: 30000.00, // 30% de 100.000 EUR
    vatCategory: 'S',
    vatRate: 21.0,
    description: 'Acompte 30% sur projet immobilier - Contrat 2025-001'
);

$invoice4->setPaymentTerms('Acompte de 30% à la signature du contrat. Facture intermédiaire.');

$invoice4->attachDocument(
    new AttachedDocument('contrat.pdf', 'Contenu', 'application/pdf', 'Contrat signé')
);
$invoice4->attachDocument(
    new AttachedDocument('planning.pdf', 'Contenu', 'application/pdf', 'Planning des travaux')
);

$invoice4->calculateTotals();

echo "Facture: " . $invoice4->getInvoiceNumber() . "\n";
echo "Type: 386 (Facture d'acompte)\n";
echo sprintf("Acompte: %.2f EUR TTC\n", $invoice4->getTaxInclusiveAmount());
echo "Projet: Construction immobilière\n\n";

// ========== CAS 5: GESTION D'ERREURS AVANCÉE ==========

echo "=== CAS 5: Gestion d'erreurs avancée ===\n\n";

function createAndValidateInvoice(): array
{
    $results = [];
    
    try {
        $invoice = new PeppolInvoice('FAC-TEST-005', '2025-10-30');
        
        // Configuration du vendeur
        try {
            $invoice->setSellerFromData(
                name: 'Test SPRL',
                vatId: 'BE0477472701',
                streetName: 'Rue Test 1',
                postalZone: '1000',
                cityName: 'Bruxelles',
                countryCode: 'BE',
                electronicAddressScheme: '0106',
                electronicAddress: '0477472701'
            );
            $results[] = "✅ Vendeur configuré";
        } catch (Exception $e) {
            $results[] = "❌ Erreur vendeur: " . $e->getMessage();
            return $results;
        }
        
        // Configuration de l'acheteur
        try {
            $invoice->setBuyerFromData(
                name: 'Client Test',
                streetName: 'Rue Client 2',
                postalZone: '1050',
                cityName: 'Bruxelles',
                countryCode: 'BE',
                vatId: 'BE0477472701',
                electronicAddressScheme: '9925',
                electronicAddress: '0477472701'
            );
            $results[] = "✅ Acheteur configuré";
        } catch (Exception $e) {
            $results[] = "❌ Erreur acheteur: " . $e->getMessage();
            return $results;
        }
        
        // Ajout de lignes
        try {
            $invoice->addLine(
                id: '1',
                name: 'Article test',
                quantity: 5.0,
                unitCode: 'C62',
                unitPrice: 100.00,
                vatCategory: 'S',
                vatRate: 21.0
            );
            $results[] = "✅ Ligne ajoutée";
        } catch (Exception $e) {
            $results[] = "❌ Erreur ligne: " . $e->getMessage();
        }
        
        // Documents joints
        try {
            $invoice->attachDocument(
                new AttachedDocument('doc1.pdf', 'Content', 'application/pdf', 'Doc 1')
            );
            $invoice->attachDocument(
                new AttachedDocument('doc2.pdf', 'Content', 'application/pdf', 'Doc 2')
            );
            $results[] = "✅ Documents joints";
        } catch (Exception $e) {
            $results[] = "❌ Erreur documents: " . $e->getMessage();
        }
        
        // Références obligatoires
        $invoice->setBuyerReference('REF-TEST-2025');
        $invoice->setDueDate('2025-11-30');
        $results[] = "✅ Références définies";
        
        // Calcul des totaux
        $invoice->calculateTotals();
        $results[] = "✅ Totaux calculés";
        
        // Validation complète
        $errors = $invoice->validate();
        if (empty($errors)) {
            $results[] = "✅ Validation réussie";
        } else {
            $results[] = "❌ Erreurs de validation:";
            foreach ($errors as $error) {
                $results[] = "   - {$error}";
            }
        }
        
        // Export
        try {
            $xml = $invoice->toXml();
            $results[] = "✅ Export XML réussi (" . strlen($xml) . " octets)";
        } catch (Exception $e) {
            $results[] = "❌ Erreur export: " . $e->getMessage();
        }
        
    } catch (Exception $e) {
        $results[] = "❌ Erreur fatale: " . $e->getMessage();
    }
    
    return $results;
}

$testResults = createAndValidateInvoice();
foreach ($testResults as $result) {
    echo $result . "\n";
}

echo "\n";

// ========== EXPORT DE TOUTES LES FACTURES ==========

echo "=== EXPORT DES EXEMPLES ===\n\n";

$invoices = [
    $invoice1 => 'multi_tva',
    $invoice2 => 'reference_structuree',
    $invoice3 => 'export_hors_ue',
    $invoice4 => 'acompte'
];

foreach ($invoices as $invoice => $name) {
    $xmlPath = __DIR__ . "/output/{$name}.xml";
    $jsonPath = __DIR__ . "/output/{$name}.json";
    
    if ($invoice->saveXml($xmlPath)) {
        echo "✅ {$name}.xml\n";
    }
    
    if ($invoice->saveJson($jsonPath)) {
        echo "✅ {$name}.json\n";
    }
}

echo "\n✨ Exemples avancés terminés avec succès !\n";
