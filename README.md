# Bibliothèque de Facturation Électronique Peppol/UBL.BE

Une bibliothèque PHP 8+ complète pour créer, valider et manipuler des factures électroniques conformes aux normes :
- **EN 16931** (norme européenne)
- **Peppol BIS Billing 3.0**
- **UBL.BE 1.0** (spécification belge)

## 🎯 Fonctionnalités

✅ **Création de factures** conformes UBL.BE et EN 16931  
✅ **Validation complète** selon les Business Rules  
✅ **Export XML UBL 2.1** prêt pour transmission  
✅ **Import XML UBL** pour traiter des factures reçues  
✅ **Export JSON** pour intégration applicative  
✅ **Gestion des pièces jointes** (PDF, images, etc.)  
✅ **Validations spécifiques belges** (TVA, références structurées)  
✅ **Architecture modulaire** et extensible  

---

## 📦 Installation

```bash
composer require votre-namespace/peppol-invoice
```

---

## 🏗️ Architecture

### Structure des fichiers

```
src/
├── Core/
│   ├── InvoiceBase.php              # Classe abstraite de base
│   ├── InvoiceValidatorTrait.php    # Validations réutilisables
│   └── InvoiceConstants.php         # Constantes normalisées
│
├── Models/
│   ├── Address.php                  # Modèle Adresse
│   ├── ElectronicAddress.php        # Adresse électronique Peppol
│   ├── Party.php                    # Vendeur/Acheteur
│   ├── InvoiceLine.php              # Ligne de facture
│   ├── VatBreakdown.php             # Ventilation TVA
│   ├── PaymentInfo.php              # Informations de paiement
│   └── AttachedDocument.php         # Document joint
│
├── Standards/
│   ├── EN16931Invoice.php           # Implémentation EN 16931
│   └── UblBeInvoice.php             # Extension UBL.BE (Belgique)
│
├── Formats/
│   ├── XmlExporter.php              # Export XML UBL 2.1
│   └── XmlImporter.php              # Import XML UBL 2.1
│
└── PeppolInvoice.php                # Classe façade (point d'entrée)

examples/
├── 01-basic-invoice.php             # Exemple de base
├── 02-import-xml.php                # Import depuis XML
├── 03-intracommunity-invoice.php   # Facture intracommunautaire
└── 04-advanced-features.php         # Fonctionnalités avancées
```

### Hiérarchie d'héritage

```
InvoiceBase (abstract)
    ↓
EN16931Invoice (norme européenne)
    ↓
UblBeInvoice (spécification belge)
    ↓
PeppolInvoice (façade utilisateur)
```

---

## 🚀 Utilisation rapide

### Créer une facture

```php
<?php
require_once 'vendor/autoload.php';

// Création de la facture
$invoice = new PeppolInvoice(
    invoiceNumber: 'FAC-2025-001',
    issueDate: '2025-10-30'
);

// Fournisseur
$invoice->setSellerFromData(
    name: 'ACME SPRL',
    vatId: 'BE0123456789',
    streetName: 'Rue de la Loi 123',
    postalZone: '1000',
    cityName: 'Bruxelles',
    countryCode: 'BE',
    electronicAddressScheme: '0106', // Obligatoire UBL.BE
    electronicAddress: '0123456789'
);

// Client
$invoice->setBuyerFromData(
    name: 'Client SA',
    streetName: 'Avenue Louise 456',
    postalZone: '1050',
    cityName: 'Bruxelles',
    countryCode: 'BE',
    vatId: 'BE0987654321',
    electronicAddressScheme: '9925', // Obligatoire UBL.BE
    electronicAddress: 'BE0987654321'
);

// Référence obligatoire
$invoice->setBuyerReference('REF-CLIENT-2025-001');

// Date d'échéance
$invoice->setDueDate('2025-11-29');

// Ajouter des lignes
$invoice->addLine(
    id: '1',
    name: 'Prestation de conseil',
    quantity: 8.0,
    unitCode: 'HUR',
    unitPrice: 85.00,
    vatCategory: 'S',
    vatRate: 21.0
);

// Calculer les totaux
$invoice->calculateTotals();

// Valider et exporter
if ($invoice->isValid()) {
    $invoice->saveXml('facture.xml');
    echo "✅ Facture créée avec succès !";
}
```

### Importer une facture XML

```php
<?php
// Depuis un fichier
$invoice = PeppolInvoice::fromXml('facture_reçue.xml');

// Depuis une chaîne XML
$xmlContent = file_get_contents('https://api.example.com/invoice.xml');
$invoice = PeppolInvoice::fromXml($xmlContent);

// Accéder aux données
echo "Facture N°" . $invoice->getInvoiceNumber();
echo "Montant TTC: " . $invoice->getTaxInclusiveAmount() . " EUR";

// Extraire les documents joints
foreach ($invoice->getAttachedDocuments() as $doc) {
    $doc->saveToFile('extracted_' . $doc->getFilename());
}
```

### Modifier et ré-exporter

```php
<?php
// Charger une facture existante
$invoice = PeppolInvoice::fromXml('facture.xml');

// Ajouter une ligne
$invoice->addLine(
    id: '10',
    name: 'Frais de traitement',
    quantity: 1.0,
    unitCode: 'C62',
    unitPrice: 25.00,
    vatCategory: 'S',
    vatRate: 21.0
);

// Recalculer et sauvegarder
$invoice->calculateTotals();
$invoice->saveXml('facture_modifiée.xml');
```

---

## 📋 Validations UBL.BE obligatoires

### ✅ Checklist de conformité

- [ ] **Adresse électronique vendeur** (BT-34) - Obligatoire
- [ ] **Adresse électronique acheteur** (BT-49) - Obligatoire
- [ ] **Référence acheteur OU référence commande** (BT-10 ou BT-13) - Au moins une
- [ ] **Date d'échéance OU conditions de paiement** (BT-9 ou BT-20) - Au moins une si montant > 0
- [ ] **Au moins 2 documents joints** (BG-24) - Minimum requis
- [ ] **Numéro de TVA belge valide** pour vendeur BE (avec modulo 97)
- [ ] **Taux de TVA belges** (21%, 12%, 6%, 0%) pour vendeur BE
- [ ] **CustomizationID** = `urn:cen.eu:en16931:2017#conformant#urn:UBL.BE:1.0.0.20180214`

---

## 🔧 Codes normalisés

### Types de facture (UNCL1001)

| Code | Description |
|------|-------------|
| `380` | Facture commerciale |
| `381` | Avoir |
| `386` | Facture d'acompte |
| `384` | Facture rectificative |

### Catégories de TVA (UNCL5305)

| Code | Description |
|------|-------------|
| `S` | Taux standard |
| `Z` | Taux zéro |
| `E` | Exonéré |
| `AE` | Autoliquidation |
| `K` | Intra-communautaire |
| `G` | Exportation hors UE |

### Codes d'unité (UN/ECE Rec. 20)

| Code | Description |
|------|-------------|
| `C62` | Unité (pièce) |
| `HUR` | Heure |
| `DAY` | Jour |
| `MON` | Mois |
| `KGM` | Kilogramme |
| `LTR` | Litre |

### Moyens de paiement (UNCL4461)

| Code | Description |
|------|-------------|
| `30` | Virement bancaire |
| `48` | Carte de paiement |
| `49` | Prélèvement |
| `58` | Virement SEPA |

---

## 🇧🇪 Spécificités belges

### Numéro de TVA

Format : `BE0123456789` (BE + 10 chiffres avec validation modulo 97)

```php
// Validation automatique
$invoice->setSellerFromData(
    vatId: 'BE0123456789', // ✅ Valide
    // ...
);
```

### Référence structurée

Format : `+++123/4567/89012+++` (avec validation modulo 97)

```php
use Peppol\Models\PaymentInfo;

$payment = new PaymentInfo(
    paymentMeansCode: '30',
    iban: 'BE68539007547034',
    bic: 'GKCCBEBB',
    paymentReference: '+++123/4567/89012+++' // ✅ Validé
);

$invoice->setPaymentInfo($payment);
```

### Adresses électroniques

Pour UBL.BE, utilisez les schémas :
- `0106` : KBO-BCE (numéro d'entreprise belge)
- `9925` : Numéro de TVA

```php
use Peppol\Models\ElectronicAddress;

// KBO-BCE
$address = ElectronicAddress::createBelgianKBO('0123456789');

// Numéro de TVA
$address = ElectronicAddress::createFromVAT('BE0123456789');
```

---

## 📊 Exemples avancés

### Facture intracommunautaire

```php
<?php
$invoice = new PeppolInvoice('FAC-2025-002', '2025-10-30');

// Vendeur belge
$invoice->setSellerFromData(
    name: 'Export SA',
    vatId: 'BE0999999999',
    streetName: 'Quai du Commerce 1',
    postalZone: '2000',
    cityName: 'Anvers',
    countryCode: 'BE',
    electronicAddressScheme: '0106',
    electronicAddress: '0999999999'
);

// Client allemand
$invoice->setBuyerFromData(
    name: 'German Company GmbH',
    streetName: 'Hauptstrasse 10',
    postalZone: '10115',
    cityName: 'Berlin',
    countryCode: 'DE',
    vatId: 'DE123456789',
    electronicAddressScheme: '9925',
    electronicAddress: 'DE123456789'
);

// Ligne avec TVA intracommunautaire
$invoice->addLine(
    id: '1',
    name: 'Marchandises diverses',
    quantity: 100.0,
    unitCode: 'C62',
    unitPrice: 50.00,
    vatCategory: 'K', // Intra-communautaire
    vatRate: 0.0
);

// Raison d'exonération obligatoire
$invoice->setVatExemptionReason('VATEX-EU-IC');

$invoice->calculateTotals();
$invoice->saveXml('facture_intracommunautaire.xml');
```

### Documents joints

```php
<?php
use Peppol\Models\AttachedDocument;

// Depuis un fichier
$invoice->attachFile(
    filePath: '/path/to/contrat.pdf',
    description: 'Contrat cadre',
    documentType: 'Contract'
);

// Depuis un contenu binaire
$pdfContent = file_get_contents('document.pdf');
$document = new AttachedDocument(
    filename: 'conditions.pdf',
    fileContent: $pdfContent,
    mimeType: 'application/pdf',
    description: 'Conditions générales',
    documentType: 'GeneralTermsAndConditions'
);

$invoice->attachDocument($document);
```

---

## 🧪 Tests et validation

### Valider une facture

```php
<?php
$errors = $invoice->validate();

if (empty($errors)) {
    echo "✅ Facture valide";
} else {
    echo "❌ Erreurs:\n";
    foreach ($errors as $error) {
        echo "  - $error\n";
    }
}

// Ou plus simple
if ($invoice->isValid()) {
    // Traitement...
}
```

### Business Rules vérifiées

- **BR-01** : Numéro de facture obligatoire
- **BR-02** : Date d'émission obligatoire
- **BR-03** : Type de facture obligatoire
- **BR-04** : Devise obligatoire
- **BR-06** : Fournisseur obligatoire
- **BR-08** : Client obligatoire
- **BR-16** : Au moins une ligne requise
- **BR-CO-10** : Date d'échéance ≥ date d'émission
- **BR-CO-13** : Cohérence des totaux
- **BR-CO-14** : Taux > 0 pour catégorie S
- **BR-CO-15** : Taux = 0 pour catégories Z, E, G, O
- **BR-CO-25** : Date d'échéance OU conditions de paiement si montant > 0
- **BR-CO-26** : N° TVA fournisseur obligatoire

Plus les règles spécifiques **UBL-BE** :
- **UBL-BE-01** : Au moins 2 documents joints
- **UBL-BE-10** : cbc:Name dans TaxCategory
- **UBL-BE-14** : TaxTotal dans chaque ligne
- **UBL-BE-15** : cbc:Name dans ClassifiedTaxCategory

---

## 🛠️ Extension et personnalisation

### Créer une validation personnalisée

```php
<?php
use Peppol\Standards\UblBeInvoice;

class MyCustomInvoice extends UblBeInvoice
{
    public function validate(): array
    {
        $errors = parent::validate();
        
        // Ajout de règles personnalisées
        if ($this->getTaxInclusiveAmount() > 10000) {
            if ($this->getAttachedDocuments()->count() < 3) {
                $errors[] = 'CUSTOM: 3 documents requis si montant > 10000€';
            }
        }
        
        return $errors;
    }
}
```

### Ajouter un format d'export

```php
<?php
namespace Peppol\Formats;

use Peppol\Core\InvoiceBase;

class CsvExporter
{
    public function __construct(private InvoiceBase $invoice) {}
    
    public function toCsv(): string
    {
        // Votre logique d'export CSV
    }
}
```

---

## 📚 Ressources

### Documentation officielle

- [EN 16931](https://ec.europa.eu/digital-building-blocks/wikis/display/DIGITAL/Compliance+with+eInvoicing+standard) - Norme européenne
- [Peppol BIS](https://docs.peppol.eu/poacc/billing/3.0/) - Spécification Peppol
- [UBL.BE](https://www.nbb.be/fr/peppol) - Spécification belge
- [UN/ECE Codes](https://unece.org/trade/uncefact/cl-recommendations) - Codes normalisés

### Support

- 📧 Email : support@example.com
- 🐛 Issues : https://github.com/votre-repo/issues
- 📖 Wiki : https://github.com/votre-repo/wiki

---

## 📄 Licence

MIT License - Voir le fichier LICENSE pour plus de détails

---

## 👥 Contribution

Les contributions sont les bienvenues ! Consultez CONTRIBUTING.md pour les guidelines.

---

## ✨ Changelog

### v1.0.0 (2025-10-30)
- ✅ Support complet UBL.BE 1.0
- ✅ Support EN 16931
- ✅ Export/Import XML UBL 2.1
- ✅ Validations Business Rules
- ✅ Gestion documents joints
- ✅ Architecture modulaire

---

**Made with ❤️ for Belgian e-invoicing**
