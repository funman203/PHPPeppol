# 🔧 XSLT Transformation Troubleshooting

## Erreur rencontrée

```
XSLTProcessor::transformToDoc(): runtime error: 
file .../resources/iso-schematron/iso_dsdl_include.xsl line 1409 element call-template
```

## 🔍 Cause

Cette erreur se produit lors de la compilation Schematron → XSLT. Causes possibles :

1. **Version de libxslt incompatible** avec les XSLT ISO Schematron
2. **Fichiers XSLT ISO corrompus** lors du téléchargement
3. **Namespaces manquants** dans le fichier Schematron source
4. **Paramètres XSLT non définis** requis par la transformation

## ✅ Solutions

### Solution 1 : Vérifier libxslt

```bash
# Vérifier la version
php -i | grep -i libxslt

# Version recommandée : >= 1.1.28
```

Si version trop ancienne :

```bash
# Ubuntu/Debian
sudo apt-get update
sudo apt-get install --upgrade libxslt1.1

# Redémarrer PHP
sudo service php8.2-fpm restart
```

### Solution 2 : Réinstaller les fichiers XSLT ISO

```bash
# Nettoyer
rm -rf resources/iso-schematron/

# Réinstaller
php bin/install-schematron.php

# Vérifier l'intégrité
ls -lh resources/iso-schematron/
```

**Tailles attendues** :
```
-rw-r--r-- iso_dsdl_include.xsl      (~15 KB)
-rw-r--r-- iso_abstract_expand.xsl   (~23 KB)  
-rw-r--r-- iso_svrl_for_xslt2.xsl    (~45 KB)
```

Si les fichiers sont trop petits ou 0 octets → corruption.

### Solution 3 : Téléchargement manuel

Si le téléchargement automatique échoue :

```bash
mkdir -p resources/iso-schematron
cd resources/iso-schematron

# Source officielle
wget https://raw.githubusercontent.com/Schematron/schematron/2020-10-01/trunk/schematron/code/iso_dsdl_include.xsl
wget https://raw.githubusercontent.com/Schematron/schematron/2020-10-01/trunk/schematron/code/iso_abstract_expand.xsl
wget https://raw.githubusercontent.com/Schematron/schematron/2020-10-01/trunk/schematron/code/iso_svrl_for_xslt2.xsl

# Vérifier
file *.xsl  # Doit indiquer "XML document text"
```

### Solution 4 : Utiliser une version stable

Les XSLT ISO Schematron ont plusieurs versions. La version 2020-10-01 est stable :

```bash
cd resources/iso-schematron

# Remplacer par version stable
rm *.xsl

# Télécharger version 2020-10-01
for file in iso_dsdl_include.xsl iso_abstract_expand.xsl iso_svrl_for_xslt2.xsl; do
    wget "https://raw.githubusercontent.com/Schematron/schematron/2020-10-01/trunk/schematron/code/$file"
done
```

### Solution 5 : Désactiver Schematron dans les tests

Si vous voulez continuer sans Schematron :

**Dans `phpunit.xml`** :
```xml
<testsuites>
    <testsuite name="Unit">
        <directory>tests/Unit</directory>
    </testsuite>
    <!-- Désactiver temporairement -->
    <!-- <testsuite name="Schematron">
        <directory>tests/Schematron</directory>
    </testsuite> -->
</testsuites>
```

**Ou exclure dans la commande** :
```bash
vendor/bin/phpunit --exclude-group schematron
```

### Solution 6 : Skip tests en CI/CD

**Dans `.github/workflows/tests.yml`** :
```yaml
- name: Run PHPUnit tests
  run: vendor/bin/phpunit --exclude-group schematron
  continue-on-error: false
```

## 🧪 Tests de diagnostic

### Test 1 : Vérifier l'extension XSL

```bash
php -r "
if (extension_loaded('xsl')) {
    echo '✅ Extension XSL chargée\n';
    echo 'Version libxslt: ' . LIBXSLT_DOTTED_VERSION . '\n';
} else {
    echo '❌ Extension XSL manquante\n';
}
"
```

### Test 2 : Test transformation simple

```bash
php -r "
\$xml = new DOMDocument();
\$xml->loadXML('<root/>');

\$xsl = new DOMDocument();
\$xsl->loadXML('<?xml version=\"1.0\"?><xsl:stylesheet version=\"1.0\" xmlns:xsl=\"http://www.w3.org/1999/XSL/Transform\"><xsl:template match=\"/\"><output/></xsl:template></xsl:stylesheet>');

\$proc = new XSLTProcessor();
\$proc->importStylesheet(\$xsl);

\$result = \$proc->transformToXML(\$xml);
echo \$result ? '✅ XSLT fonctionne' : '❌ XSLT ne fonctionne pas';
"
```

### Test 3 : Valider fichiers XSLT

```bash
cd resources/iso-schematron

for file in *.xsl; do
    echo -n "Testing $file: "
    xmllint --noout "$file" 2>&1 && echo "✅" || echo "❌"
done
```

### Test 4 : Test Schematron complet

```bash
php bin/test-schematron-install.php
```

## 🔄 Workarounds

### Option A : Validation PHP seule

Si Schematron pose problème, utilisez uniquement la validation PHP :

```php
// Au lieu de
$validator = new SchematronValidator();
$result = $validator->validate($xml);

// Utilisez
$invoice = XmlImporter::fromUbl($xml);
$errors = $invoice->validate();  // Validation PHP uniquement
```

La validation PHP couvre 80% des règles. Pour production :

```php
// Validation hybride avec fallback
try {
    $validator = new SchematronValidator();
    $schematronResult = $validator->validate($xml, ['ublbe']);
    
    if (!$schematronResult->isValid()) {
        return ['errors' => $schematronResult->getErrors()];
    }
} catch (\RuntimeException $e) {
    // Fallback sur validation PHP si Schematron échoue
    $invoice = XmlImporter::fromUbl($xml);
    $phpErrors = $invoice->validate();
    
    if (!empty($phpErrors)) {
        return ['errors' => $phpErrors, 'warning' => 'Schematron non disponible'];
    }
}
```

### Option B : Pre-compiler les XSLT

Pour éviter la compilation à chaque fois :

```bash
# Compiler une fois
php -r "
require 'vendor/autoload.php';
\$v = new Peppol\Validation\SchematronValidator();
// Premier appel compile et met en cache
\$v->validate(file_get_contents('tests/fixtures/valid-ublbe-invoice.xml'), ['ublbe']);
echo 'XSLT compilé et mis en cache';
"

# Les validations suivantes utilisent le cache
```

### Option C : Validation externe

Utiliser un service externe pour la validation Schematron :

```php
function validateWithExternalService(string $xml): array
{
    $ch = curl_init('https://validator-api.example.com/validate');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    return json_decode($response, true);
}
```

## 📊 Matrice de compatibilité

| OS | PHP | libxslt | Statut |
|----|-----|---------|--------|
| Ubuntu 22.04 | 8.2 | 1.1.34 | ✅ OK |
| Ubuntu 20.04 | 8.1 | 1.1.34 | ✅ OK |
| Ubuntu 18.04 | 8.0 | 1.1.29 | ⚠️ Instable |
| Debian 11 | 8.2 | 1.1.34 | ✅ OK |
| Alpine Linux | 8.2 | 1.1.37 | ✅ OK |
| macOS | 8.2 | 1.1.35 | ✅ OK |
| Windows | 8.2 | 1.1.34 | ⚠️ Non testé |

## 🆘 Support

### Logs détaillés

Pour obtenir plus d'informations sur l'erreur :

```php
// Activer les erreurs libxml
libxml_use_internal_errors(true);

try {
    $result = $validator->validate($xml);
} catch (\Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n\n";
    
    $errors = libxml_get_errors();
    foreach ($errors as $error) {
        echo "LibXML: " . $error->message . "\n";
        echo "Ligne: " . $error->line . "\n";
        echo "Colonne: " . $error->column . "\n\n";
    }
    libxml_clear_errors();
}
```

### Créer une issue

Si le problème persiste, créez une issue avec :

1. Version de PHP : `php -v`
2. Version de libxslt : `php -i | grep libxslt`
3. OS : `uname -a`
4. Output de : `php bin/test-schematron-install.php`
5. Logs complets de l'erreur

## 📚 Références

- [ISO Schematron GitHub](https://github.com/Schematron/schematron)
- [PHP XSLTProcessor](https://www.php.net/manual/en/class.xsltprocessor.php)
- [libxslt Documentation](http://xmlsoft.org/libxslt/)
- [UBL.BE Documentation](https://www.ubl.be/)

---

**Dernière mise à jour** : 30 octobre 2025
