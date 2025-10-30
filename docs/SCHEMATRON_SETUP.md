# Guide de Configuration Schematron

Ce guide explique comment configurer et utiliser la validation Schematron pour assurer une conformité 100% avec UBL.BE.

## 📋 Prérequis

### Extensions PHP requises

```bash
# Debian/Ubuntu
sudo apt-get install php-xsl php-xml

# CentOS/RHEL
sudo yum install php-xml

# macOS (Homebrew)
brew install php
# XSL est inclus par défaut

# Windows
# Activez extension=xsl dans php.ini
```

Vérifiez l'installation :

```php
<?php
if (extension_loaded('xsl')) {
    echo "✅ Extension XSL disponible";
} else {
    echo "❌ Extension XSL manquante";
}
```

## 🔧 Installation

### Option 1: Installation automatique

```php
<?php
use Peppol\Validation\SchematronValidator;

$validator = new SchematronValidator();

// Télécharge automatiquement les fichiers Schematron officiels
$results = $validator->installSchematronFiles();

foreach ($results as $level => $success) {
    echo ($success ? '✅' : '❌') . " {$level}\n";
}
```

### Option 2: Installation manuelle

1. Téléchargez les fichiers depuis [ubl.be](https://www.nbb.be/fr/peppol) :
   - `UBLBE_Invoice-1.0.sch`
   - `EN16931_UBL-1.3.sch`
   - `PEPPOL_CIUS-UBL-1.0.sch`

2. Placez-les dans `resources/schematron/` :

```
project/
├── resources/
│   └── schematron/
│       ├── UBLBE_Invoice-1.0.sch
│       ├── EN16931_UBL-1.3.sch
│       └── PEPPOL_CIUS-UBL-1.0.sch
```

3. Téléchargez les XSLT ISO Schematron :

```bash
mkdir -p resources/iso-schematron
cd resources/iso-schematron

wget https://raw.githubusercontent.com/Schematron/schematron/master/trunk/schematron/code/iso_dsdl_include.xsl
wget https://raw.githubusercontent.com/Schematron/schematron/master/trunk/schematron/code/iso_abstract_expand.xsl
wget https://raw.githubusercontent.com/Schematron/schematron/master/trunk/schematron/code/iso_svrl_for_xslt2.xsl
```

## 🚀 Utilisation

### Validation basique

```php
<?php
use Peppol\Validation\SchematronValidator;

$validator = new SchematronValidator();

// Valider un XML
$xmlContent = file_get_contents('facture.xml');
$result = $validator->validate($xmlContent);

if ($result->isValid()) {
    echo "✅ Facture conforme UBL.BE";
} else {
    echo "❌ Erreurs trouvées:\n";
    foreach ($result->getErrors() as $error) {
        echo "  - " . $error->getMessage() . "\n";
    }
}
```

### Validation multi-niveaux

```php
<?php
// Valider selon plusieurs niveaux
$result = $validator->validate($xmlContent, [
    'ublbe',    // Règles belges spécifiques
    'en16931',  // Norme européenne
    'peppol'    // Règles Peppol (optionnel)
]);

// Analyser par niveau
foreach ($result->getViolationsByLevel() as $level => $violations) {
    echo "Niveau {$level}: " . count($violations) . " problème(s)\n";
}
```

### Validation lors de l'export

```php
<?php
use Peppol\Formats\XmlExporter;

$exporter = new XmlExporter($invoice);

// Activer la validation Schematron automatique
$exporter->enableSchematronValidation(true, ['ublbe', 'en16931']);

try {
    $xml = $exporter->toUbl21();
    echo "✅ Export réussi avec validation Schematron";
} catch (\RuntimeException $e) {
    echo "❌ Validation échouée:\n" . $e->getMessage();
}
```

## ⚙️ Configuration avancée

### Personnaliser les répertoires

```php
<?php
$validator = new SchematronValidator(
    schematronDir: '/custom/path/schematron',
    cacheDir: '/custom/path/cache',
    useCache: true
);
```

### Gestion du cache

```php
<?php
// Nettoyer le cache (après mise à jour des schémas)
$validator->clearCache();

// Désactiver le cache (développement)
$validator = new SchematronValidator(useCache: false);
```

### Rapport détaillé

```php
<?php
$result = $validator->validate($xmlContent);

// Rapport complet avec infos
echo $result->getDetailedReport(includeInfos: true);

// Export JSON
file_put_contents('report.json', $result->toJson());

// Export en tableau
$data = $result->toArray();
```

## 📊 Niveaux de validation

### UBL.BE (ublbe)

Règles spécifiques belges :
- Au moins 2 documents joints
- Adresses électroniques obligatoires
- Référence acheteur OU commande
- Taux de TVA belges (21%, 12%, 6%, 0%)
- Validation modulo 97 pour TVA belge

### EN 16931 (en16931)

Norme européenne :
- Business Rules (BR-*)
- Cardinality Rules (BR-CO-*)
- Cohérence des montants
- Formats de dates
- Codes normalisés

### Peppol (peppol)

Règles Peppol BIS :
- Identifiants Peppol
- Routage électronique
- Contraintes réseau Peppol

## 🎯 Stratégies de validation

### Développement

```php
<?php
// Validation stricte systématique
$exporter->enableSchematronValidation(true, ['ublbe', 'en16931', 'peppol']);

// Afficher tous les warnings
error_reporting(E_ALL);
```

### Production

```php
<?php
// Validation PHP rapide
$phpErrors = $invoice->validate();
if (!empty($phpErrors)) {
    throw new Exception("Validation PHP échouée");
}

// Schematron en asynchrone (job queue)
$queue->push(new ValidateInvoiceJob($xmlContent));

// OU validation périodique
if (random_int(1, 100) <= 10) { // 10% des factures
    $validator->validate($xmlContent);
}
```

## 🔍 Interprétation des résultats

### Types de violations

| Type | Bloquant | Description |
|------|----------|-------------|
| **error** | ✅ Oui | Règle obligatoire non respectée |
| **fatal** | ✅ Oui | Erreur critique |
| **warning** | ❌ Non | Recommandation non suivie |
| **info** | ❌ Non | Information contextuelle |

### Exemples d'erreurs courantes

#### UBL-BE-01: Documents joints manquants

```
[UBLBE/ERROR] At least 2 AdditionalDocumentReference elements required
Location: /Invoice
```

**Solution** : Ajouter au moins 2 documents joints

```php
$invoice->attachFile('doc1.pdf', 'Document 1');
$invoice->attachFile('doc2.pdf', 'Document 2');
```

#### BR-CO-25: Date d'échéance ou conditions manquantes

```
[EN16931/ERROR] DueDate or PaymentTerms required when amount > 0
Location: /Invoice
```

**Solution** :

```php
$invoice->setDueDate('2025-11-29');
// OU
$invoice->setPaymentTerms('30 jours fin de mois');
```

## 🐛 Dépannage

### Extension XSL manquante

```
RuntimeException: Extension PHP XSL requise
```

**Solution** :
```bash
sudo apt-get install php-xsl
sudo service apache2 restart  # ou php-fpm
```

### Fichiers Schematron introuvables

```
RuntimeException: Fichier Schematron introuvable
```

**Solution** :
```php
$validator->installSchematronFiles(force: true);
```

### Erreur de transformation XSLT

```
RuntimeException: Erreur lors de la transformation XSLT
```

**Causes possibles** :
1. Fichier Schematron corrompu → Retélécharger
2. XSLT ISO manquants → Installer manuellement
3. XML mal formé → Valider la syntaxe XML

### Performance lente

**Première validation lente (~500ms)** : Normal, compilation XSLT

**Validations suivantes lentes** : Vérifier que le cache est actif

```php
// Vérifier le cache
$validator = new SchematronValidator(useCache: true);

// Forcer la recompilation
$validator->clearCache();
```

## 📈 Optimisation des performances

### Cache activé (recommandé)

```php
// ~500ms première fois, ~50ms ensuite
$validator = new SchematronValidator(useCache: true);
```

### Validation sélective

```php
// UBL.BE uniquement (plus rapide)
$result = $validator->validate($xmlContent, ['ublbe']);
```

### Validation en lot

```php
// Réutiliser l'instance
$validator = new SchematronValidator();

foreach ($invoices as $xml) {
    $result = $validator->validate($xml, ['ublbe']);
    // Le cache XSLT est réutilisé
}
```

## 📚 Ressources

- [UBL.BE Documentation](https://www.nbb.be/fr/peppol)
- [EN 16931 Standard](https://ec.europa.eu/digital-building-blocks/wikis/display/DIGITAL/Compliance+with+eInvoicing+standard)
- [Peppol BIS Billing](https://docs.peppol.eu/poacc/billing/3.0/)
- [ISO Schematron](http://schematron.com/)

## 🆘 Support

Pour les problèmes spécifiques à la validation Schematron :

1. Vérifiez les logs : `$result->getDetailedReport()`
2. Testez avec les exemples officiels UBL.BE
3. Consultez la documentation Schematron
4. Ouvrez une issue GitHub avec le rapport JSON

---

**Note** : La validation Schematron est **optionnelle** mais **fortement recommandée** pour garantir la conformité 100% avec UBL.BE et éviter les rejets lors de la transmission.
