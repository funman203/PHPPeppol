# Scripts CLI

Ce répertoire contient les outils en ligne de commande pour la bibliothèque Peppol Invoice.

## 📋 Scripts disponibles

### validate-invoice

**Validation de factures UBL/Peppol**

```bash
# Validation PHP seule
./bin/validate-invoice facture.xml

# Validation PHP + Schematron
./bin/validate-invoice facture.xml --schematron

# Validation avec niveaux spécifiques
./bin/validate-invoice facture.xml --schematron --level=ublbe,en16931

# Export JSON du rapport
./bin/validate-invoice facture.xml --schematron --json > rapport.json

# Aide
./bin/validate-invoice --help
```

**Options** :
- `--schematron` : Active la validation Schematron
- `--level=LEVELS` : Niveaux Schematron (ublbe,en16931,peppol)
- `--json` : Format de sortie JSON
- `--no-color` : Désactive les couleurs
- `--install-schematron` : Installe les fichiers Schematron
- `--clear-cache` : Nettoie le cache Schematron
- `-h, --help` : Affiche l'aide

---

### install-schematron.php

**Installation des fichiers Schematron**

Télécharge et installe :
- Fichiers XSLT ISO Schematron (3 fichiers)
- Fichiers Schematron officiels (UBL.BE, EN 16931, Peppol)

```bash
php bin/install-schematron.php
```

**Ce qui est installé** :
```
resources/
├── iso-schematron/
│   ├── iso_dsdl_include.xsl
│   ├── iso_abstract_expand.xsl
│   └── iso_svrl_for_xslt2.xsl
└── schematron/
    ├── UBLBE_Invoice-1.0.sch
    ├── EN16931_UBL-1.3.sch
    └── PEPPOL_CIUS-UBL-1.0.sch
```

**Sortie** :
```
=== Installation des fichiers Schematron ===

✅ Extension XSL disponible
✅ Répertoire créé: schematron
✅ Répertoire créé: iso-schematron

--- Installation XSLT ISO Schematron ---
✅ XSLT ISO: iso_dsdl_include.xsl
✅ XSLT ISO: iso_abstract_expand.xsl
✅ XSLT ISO: iso_svrl_for_xslt2.xsl

--- Installation fichiers Schematron officiels ---
✅ Schematron ublbe
✅ Schematron en16931
⚠️  Schematron peppol (optionnel)

=== Résultat ===

✅ Installation complète réussie ! ✨
```

---

### test-schematron-install.php

**Test de l'installation Schematron**

Vérifie que tout est correctement installé :
- Extensions PHP requises
- Fichiers XSLT ISO
- Fichiers Schematron
- Création du validateur
- Test de validation basique

```bash
php bin/test-schematron-install.php
```

**Sortie** :
```
=== Test Installation Schematron ===

1. Extension XSL... ✅
2. Extension ZIP... ✅
3. XSLT ISO Schematron:
   - iso_dsdl_include.xsl: ✅ (15.2 KB)
   - iso_abstract_expand.xsl: ✅ (23.4 KB)
   - iso_svrl_for_xslt2.xsl: ✅ (45.1 KB)
4. Fichiers Schematron:
   - UBL.BE: ✅ (78.3 KB)
   - EN 16931: ✅ (156.7 KB)
   - Peppol: ⚠️  (optionnel)
5. Autoload Composer... ✅
6. Création validateur... ✅
7. Test validation basique... ✅

=== Résumé ===

✅ Installation complète et fonctionnelle !
```

**Codes de sortie** :
- `0` : Installation complète et fonctionnelle
- `1` : Problèmes détectés (détails affichés)

---

## 🚀 Workflow recommandé

### Installation initiale

```bash
# 1. Installer les dépendances
composer install

# 2. Installer Schematron
php bin/install-schematron.php

# 3. Tester l'installation
php bin/test-schematron-install.php
```

### Utilisation quotidienne

```bash
# Valider une facture rapidement
./bin/validate-invoice facture.xml

# Validation complète avant envoi
./bin/validate-invoice facture.xml --schematron --level=ublbe,en16931
```

### Debugging

```bash
# Test complet de l'installation
php bin/test-schematron-install.php

# Nettoyer le cache si problème
./bin/validate-invoice --clear-cache

# Réinstaller Schematron
php bin/install-schematron.php
```

---

## 🔧 Développement

### Ajouter un nouveau script

1. **Créer le fichier**
   ```bash
   touch bin/mon-script.php
   chmod +x bin/mon-script.php
   ```

2. **Ajouter le shebang**
   ```php
   #!/usr/bin/env php
   <?php
   declare(strict_types=1);
   
   // Autoload
   require __DIR__ . '/../vendor/autoload.php';
   
   // Votre code...
   ```

3. **Tester**
   ```bash
   php bin/mon-script.php
   # OU
   ./bin/mon-script.php
   ```

### Bonnes pratiques

- ✅ Toujours utiliser `declare(strict_types=1);`
- ✅ Gérer les arguments avec `$argv`
- ✅ Retourner des codes de sortie appropriés (`exit(0)` ou `exit(1)`)
- ✅ Afficher des messages clairs avec emojis/couleurs
- ✅ Supporter `--help` pour l'aide
- ✅ Gérer les erreurs avec try/catch
- ✅ Vérifier les prérequis (extensions, fichiers)

### Codes de sortie standards

| Code | Signification |
|------|---------------|
| `0` | Succès |
| `1` | Erreur générale |
| `2` | Usage incorrect |
| `255` | Erreur fatale PHP |

---

## 📚 Exemples d'utilisation

### Validation batch

```bash
# Valider tous les XML d'un répertoire
for file in invoices/*.xml; do
    ./bin/validate-invoice "$file" --schematron --json >> results.json
done
```

### Intégration CI/CD

```yaml
# .github/workflows/validate.yml
- name: Validate invoices
  run: |
    for file in tests/fixtures/*.xml; do
      php bin/validate-invoice "$file" --schematron
    done
```

### Script personnalisé

```bash
#!/bin/bash
# validate-all.sh

echo "=== Validation des factures ==="

for invoice in data/*.xml; do
    echo -n "$(basename $invoice): "
    
    if php bin/validate-invoice "$invoice" --schematron --json > /dev/null 2>&1; then
        echo "✅"
    else
        echo "❌"
        php bin/validate-invoice "$invoice" --schematron
    fi
done
```

---

## 🐛 Troubleshooting

### Script non exécutable

```bash
chmod +x bin/validate-invoice
```

### Extension manquante

```bash
# Vérifier
php -m | grep xsl

# Installer
sudo apt-get install php-xsl
sudo service php8.2-fpm restart
```

### Autoload non trouvé

```bash
# Réinstaller les dépendances
composer install
```

### Schematron non installé

```bash
# Installer
php bin/install-schematron.php

# Vérifier
php bin/test-schematron-install.php
```

---

## 📖 Documentation

- [Guide d'installation Schematron](../docs/SCHEMATRON_SETUP.md)
- [Comparaison PHP vs Schematron](../docs/PHP_VS_SCHEMATRON.md)
- [Quick Fix Composer](../QUICK_FIX.md)
- [Fix erreur Composer](../FIX_COMPOSER_ERROR.md)

---

## 🆘 Support

Pour les problèmes avec les scripts CLI :

1. Tester l'installation : `php bin/test-schematron-install.php`
2. Consulter les docs : `docs/SCHEMATRON_SETUP.md`
3. Vérifier les issues GitHub
4. Créer une nouvelle issue avec la sortie des commandes

---

**Dernière mise à jour** : 30 octobre 2025
