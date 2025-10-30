# Validation PHP vs Schematron

Ce document explique les différences entre les deux méthodes de validation et quand les utiliser.

## 📊 Comparaison rapide

| Critère | Validation PHP | Validation Schematron |
|---------|---------------|----------------------|
| **Vitesse** | ⚡ Très rapide (~5ms) | 🐌 Plus lent (~50-500ms) |
| **Couverture** | 🟡 Règles principales (80%) | 🟢 Toutes les règles (100%) |
| **Setup** | ✅ Aucun | ⚙️ Installation fichiers .sch |
| **Dépendances** | ✅ PHP seul | 📦 Extension XSL |
| **Maintenance** | 🔧 Code à maintenir | 📥 Fichiers officiels |
| **Certitude** | 🟡 Très bon | 🟢 Conformité garantie |
| **Usage recommandé** | Développement, tests rapides | Production, validation finale |

## 🎯 Quand utiliser chaque méthode ?

### ✅ Validation PHP seule

**Idéal pour** :
- Développement rapide
- Tests unitaires
- Validation en temps réel (UI)
- API à haute performance
- Environnements sans XSL

**Exemple** :
```php
// Feedback instantané pendant le développement
$errors = $invoice->validate();
if (!empty($errors)) {
    foreach ($errors as $error) {
        echo "❌ {$error}\n";
    }
}
```

### ✅ Validation Schematron seule

**Idéal pour** :
- Validation avant transmission officielle
- Conformité réglementaire stricte
- Audit et certification
- Tests d'intégration avec plateformes Peppol

**Exemple** :
```php
// Validation officielle avant envoi
$validator = new SchematronValidator();
$result = $validator->validate($xmlContent);

if (!$result->isValid()) {
    throw new Exception("Facture non conforme");
}
```

### ⚡ Approche hybride (recommandée)

**Meilleur des deux mondes** :

```php
// Étape 1: Validation PHP rapide (filtre initial)
$phpErrors = $invoice->validate();
if (!empty($phpErrors)) {
    // Correction immédiate des erreurs évidentes
    return ['status' => 'invalid', 'errors' => $phpErrors];
}

// Étape 2: Génération XML
$xml = $exporter->toUbl21();

// Étape 3: Validation Schematron (certification finale)
$validator = new SchematronValidator();
$result = $validator->validate($xml);

if ($result->isValid()) {
    // ✅ Facture garantie 100% conforme
    return ['status' => 'valid', 'xml' => $xml];
} else {
    // ❌ Erreurs subtiles détectées
    return ['status' => 'invalid', 'errors' => $result->getErrors()];
}
```

## 🔍 Ce que chaque validation détecte

### Validation PHP

**✅ Détecte** :
- Champs obligatoires manquants
- Formats de données invalides (dates, emails, IBAN)
- Numéros de TVA belges invalides (modulo 97)
- Incohérences dans les totaux
- Taux de TVA incorrects pour la Belgique
- Codes normalisés invalides (devise, unités, etc.)
- Règles métier de base (BR-01 à BR-CO-26)
- Règles UBL.BE principales

**❌ Ne détecte pas toujours** :
- Cardinalités complexes (ex: "exactement N éléments")
- Dépendances contextuelles subtiles
- Règles de validation croisées complexes
- Certaines règles EN 16931 avancées
- Toutes les nuances des spécifications

**Exemple d'erreur détectée** :
```php
// ✅ PHP détecte ceci
$invoice->addLine(
    id: '1',
    quantity: -5.0,  // ❌ Quantité négative
    vatRate: -10.0   // ❌ Taux négatif
);
// Erreur: "Quantité doit être > 0"
```

### Validation Schematron

**✅ Détecte tout ce que PHP détecte, PLUS** :
- Toutes les règles EN 16931 (300+ règles)
- Toutes les règles UBL.BE (50+ règles)
- Cardinalités exactes
- Patterns XPath complexes
- Dépendances conditionnelles
- Règles métier avancées
- Cohérence multi-éléments

**Exemple d'erreur détectée** :
```xml
<!-- ❌ Schematron détecte ceci, PHP pourrait le manquer -->
<cac:TaxSubtotal>
  <cbc:TaxableAmount>1000.00</cbc:TaxableAmount>
  <cbc:TaxAmount>210.00</cbc:TaxAmount>
  <cac:TaxCategory>
    <cbc:ID>S</cbc:ID>
    <cbc:Percent>20.0</cbc:Percent> <!-- ❌ Devrait être 21.0 pour 210€ de TVA -->
  </cac:TaxCategory>
</cac:TaxSubtotal>
```

Erreur Schematron :
```
BR-CO-17: VAT category tax amount = VAT category taxable amount x (VAT category rate / 100)
```

## 📈 Stratégies par environnement

### 🔧 Développement

```php
// Validation PHP immédiate pour feedback rapide
if (!$invoice->isValid()) {
    // Correction...
}

// Schematron périodique (ex: toutes les 10 factures)
if (random_int(1, 10) === 1) {
    $validator->validate($xml);
}
```

**Avantages** :
- ⚡ Cycle de développement rapide
- 🎯 Détection précoce des erreurs
- 💰 Pas de ralentissement

### 🧪 Tests

```php
// Tests unitaires = PHP
public function testInvoiceValidation()
{
    $errors = $this->invoice->validate();
    $this->assertEmpty($errors);
}

// Tests d'intégration = Schematron
public function testSchematronCompliance()
{
    $result = $this->validator->validate($xml);
    $this->assertTrue($result->isValid());
}
```

### 🚀 Production

**Option A : Synchrone (petits volumes)**
```php
// 1. PHP (pré-filtre)
$phpErrors = $invoice->validate();
if (!empty($phpErrors)) {
    return ['error' => 'Invalid invoice'];
}

// 2. Schematron (certification)
$result = $validator->validate($xml);
if (!$result->isValid()) {
    return ['error' => 'Non-compliant invoice'];
}

// 3. Envoi
return sendToP eppol($xml);
```

**Option B : Asynchrone (gros volumes)**
```php
// 1. PHP immédiat
$phpErrors = $invoice->validate();
if (!empty($phpErrors)) {
    return ['error' => 'Invalid invoice'];
}

// 2. Génération et sauvegarde
$xml = $exporter->toUbl21();
saveInvoice($xml);

// 3. Schematron en background (queue)
Queue::push(new ValidateSchematronJob($xml));

return ['status' => 'pending_validation'];
```

**Option C : Échantillonnage (très gros volumes)**
```php
// PHP toujours
$phpErrors = $invoice->validate();
if (!empty($phpErrors)) {
    return ['error' => 'Invalid invoice'];
}

// Schematron sur échantillon (ex: 5%)
if (random_int(1, 100) <= 5) {
    $result = $validator->validate($xml);
    if (!$result->isValid()) {
        logWarning('Schematron failed', $result->getErrors());
    }
}
```

## 🎓 Exemples pratiques

### Cas 1 : API REST haute performance

```php
// POST /api/invoices
public function create(Request $request)
{
    $invoice = $this->buildInvoice($request->all());
    
    // Validation PHP uniquement (< 10ms)
    $errors = $invoice->validate();
    if (!empty($errors)) {
        return response()->json(['errors' => $errors], 400);
    }
    
    $xml = $exporter->toUbl21();
    
    // Schematron asynchrone
    dispatch(new ValidateInvoiceJob($xml));
    
    return response()->json([
        'id' => $invoice->getInvoiceNumber(),
        'status' => 'accepted'
    ], 202);
}
```

### Cas 2 : Interface utilisateur temps réel

```javascript
// Frontend - validation au fil de la saisie
onFieldChange(field, value) {
    // Appel API avec validation PHP
    fetch('/api/validate-field', {
        method: 'POST',
        body: JSON.stringify({ field, value })
    }).then(response => {
        // Feedback immédiat (< 100ms)
        if (!response.ok) {
            showError(response.error);
        }
    });
}
```

```php
// Backend - validation de champ
public function validateField(Request $request)
{
    // PHP uniquement pour la vitesse
    try {
        $invoice->setField($request->field, $request->value);
        return ['valid' => true];
    } catch (InvalidArgumentException $e) {
        return ['valid' => false, 'error' => $e->getMessage()];
    }
}
```

### Cas 3 : Batch de facturation mensuelle

```php
// Génération de 10 000 factures
foreach ($invoicesToGenerate as $data) {
    $invoice = $this->buildInvoice($data);
    
    // 1. PHP rapide
    if (!$invoice->isValid()) {
        continue; // Skip
    }
    
    // 2. Génération XML
    $xml = $exporter->toUbl21();
    saveToFile($xml);
}

// 3. Validation Schematron en batch (une fois)
$validator = new SchematronValidator();
$sampleFiles = array_rand($generatedFiles, 100); // 1% sample

foreach ($sampleFiles as $file) {
    $result = $validator->validate(file_get_contents($file));
    if (!$result->isValid()) {
        logError("Sample validation failed: {$file}");
        // Décision: arrêter le batch ou continuer ?
    }
}
```

## 💡 Recommandations finales

### ✅ À faire

1. **Toujours** utiliser la validation PHP en premier
2. Utiliser Schematron pour la **validation finale** avant envoi officiel
3. **Cacher** les résultats Schematron compilés
4. **Logger** les divergences entre PHP et Schematron
5. **Mettre à jour** régulièrement les fichiers Schematron

### ❌ À éviter

1. ~~Utiliser uniquement PHP en production~~
2. ~~Valider Schematron à chaque requête API~~
3. ~~Ignorer les warnings Schematron~~
4. ~~Oublier de mettre à jour les schémas~~
5. ~~Désactiver la validation en production~~

## 🔧 Configuration recommandée

```php
<?php
// config/invoice.php

return [
    'validation' => [
        // Validation PHP (toujours activée)
        'php' => [
            'enabled' => true,
            'strict_mode' => env('APP_ENV') !== 'production'
        ],
        
        // Validation Schematron
        'schematron' => [
            'enabled' => true,
            'mode' => env('SCHEMATRON_MODE', 'async'), // sync, async, sample
            'sample_rate' => env('SCHEMATRON_SAMPLE_RATE', 5), // %
            'levels' => ['ublbe', 'en16931'],
            'cache' => true,
            'fail_on_warning' => env('APP_ENV') !== 'production'
        ]
    ]
];
```

---

## 📚 Ressources complémentaires

- [Guide installation Schematron](SCHEMATRON_SETUP.md)
- [Architecture de validation](../README.md#validation)
- [Exemples de code](../examples/)

---

**En résumé** : Utilisez PHP pour la vitesse et le développement, Schematron pour la conformité et la certification. L'approche hybride offre le meilleur compromis entre performance et fiabilité.
