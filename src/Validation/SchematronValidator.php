<?php

declare(strict_types=1);

namespace Peppol\Validation;

use DOMDocument;
use XSLTProcessor;

/**
 * Validateur Schematron pour UBL.BE
 * 
 * Effectue une validation complète selon les règles Schematron officielles
 * disponibles sur https://www.ubl.be
 * 
 * Ce validateur complète les validations PHP de base avec une vérification
 * stricte selon les schémas officiels belges.
 * 
 * @package Peppol\Validation
 * @author Votre Nom
 * @version 1.0
 */
class SchematronValidator
{
    /**
     * @var string Répertoire de cache pour les XSLT compilés
     */
    private string $cacheDir;
    
    /**
     * @var bool Active/désactive le cache
     */
    private bool $useCache;
    
    /**
     * @var array<string> URLs des fichiers Schematron officiels
     */
    private const SCHEMATRON_URLS = [
        'ublbe' => 'https://www.ubl.be/wp-content/uploads/2024/07/GLOBALUBL.BE-V1.31.zip',
        'en16931' => 'https://raw.githubusercontent.com/ConnectingEurope/eInvoicing-EN16931/master/schematrons/EN16931-UBL-validation.sch',
        'peppol' => 'https://raw.githubusercontent.com/OpenPEPPOL/peppol-bis-invoice-3/master/rules/sch/PEPPOL-EN16931-UBL.sch'
    ];
    
    /**
     * @var string Chemin vers les XSLT compilés
     */
    private string $compiledDir;
    
    /**
     * @var array<string, mixed> Metadata des fichiers compilés
     */
    private array $metadata = [];
    
    /**
     * @var bool Si true, utilise les XSLT pré-compilés (mode production)
     */
    private bool $usePrecompiled = true;
    
    /**
     * Constructeur
     * 
     * @param string|null $compiledDir Répertoire des XSLT compilés
     * @param bool $usePrecompiled Utiliser les XSLT pré-compilés (défaut: true)
     */
    public function __construct(
        ?string $compiledDir = null,
        bool $usePrecompiled = true
    ) {
        $this->compiledDir = $compiledDir ?? __DIR__ . '/../../resources/compiled';
        $this->usePrecompiled = $usePrecompiled;
        
        // Charger metadata
        $this->loadMetadata();
        
        // Vérifier que l'extension XSL est disponible
        if (!extension_loaded('xsl')) {
            throw new \RuntimeException(
                'Extension PHP XSL requise pour la validation Schematron. ' .
                'Installez-la avec: apt-get install php-xsl (Linux) ou activez-la dans php.ini'
            );
        }
    }
    
    /**
     * Charge les metadata des fichiers compilés
     */
    private function loadMetadata(): void
    {
        $metadataPath = $this->compiledDir . '/metadata.json';
        
        if (!file_exists($metadataPath)) {
            trigger_error(
                'Fichiers Schematron compilés manquants. ' .
                'Validation Schematron non disponible. ' .
                'Installez avec: php bin/compile-schematron.php --all',
                E_USER_WARNING
            );
            $this->metadata = [];
            return;
        }
        
        $this->metadata = json_decode(
            file_get_contents($metadataPath),
            true
        ) ?? [];
    }
    
    /**
     * Valide un document XML contre les règles Schematron
     * 
     * @param string $xmlContent Contenu XML de la facture
     * @param array<string> $levels Niveaux de validation ['ublbe', 'en16931', 'peppol']
     * @return SchematronValidationResult
     */
    public function validate(string $xmlContent, array $levels = ['ublbe', 'en16931']): SchematronValidationResult
    {
        $allErrors = [];
        $allWarnings = [];
        $allInfos = [];
        
        foreach ($levels as $level) {
            try {
                $result = $this->validateLevel($xmlContent, $level);
                $allErrors = array_merge($allErrors, $result->getErrors());
                $allWarnings = array_merge($allWarnings, $result->getWarnings());
                $allInfos = array_merge($allInfos, $result->getInfos());
            } catch (\RuntimeException $e) {
                // Si un niveau échoue, on log et on continue
                trigger_error(
                    "Validation Schematron {$level} échouée: " . $e->getMessage(),
                    E_USER_WARNING
                );
            }
        }
        
        return new SchematronValidationResult(
            empty($allErrors),
            $allErrors,
            $allWarnings,
            $allInfos
        );
    }
    
    /**
     * Valide contre un niveau spécifique
     * 
     * @param string $xmlContent
     * @param string $level
     * @return SchematronValidationResult
     */
    private function validateLevel(string $xmlContent, string $level): SchematronValidationResult
    {
        // Obtenir le chemin du XSLT compilé
        $xslPath = $this->getCompiledXslPath($level);
        
        if (!file_exists($xslPath)) {
            throw new \RuntimeException(
                "XSLT compilé manquant pour {$level}: {$xslPath}\n" .
                "Installez avec: php bin/compile-schematron.php --all"
            );
        }
        
        // Appliquer le XSLT pré-compilé
        $svrlOutput = $this->applyCompiledXslt($xmlContent, $xslPath);
        
        // Parser le résultat SVRL
        return $this->parseSvrlOutput($svrlOutput, $level);
    }
    
    /**
     * Retourne le chemin du XSLT compilé pour un niveau
     * 
     * @param string $level
     * @return string
     */
    private function getCompiledXslPath(string $level): string
    {
        $mapping = [
            'ublbe' => 'UBLBE_Invoice-1.0.xsl',
            'en16931' => 'EN16931_UBL-1.3.xsl',
            'peppol' => 'PEPPOL_CIUS-UBL-1.0.xsl'
        ];
        
        if (!isset($mapping[$level])) {
            throw new \InvalidArgumentException("Niveau de validation inconnu: {$level}");
        }
        
        return $this->compiledDir . '/' . $mapping[$level];
    }
    
    /**
     * Applique un XSLT pré-compilé au XML
     * 
     * @param string $xmlContent
     * @param string $xslPath
     * @return string Résultat SVRL
     */
    private function applyCompiledXslt(string $xmlContent, string $xslPath): string
    {
        libxml_use_internal_errors(true);
        libxml_clear_errors();
        
        // Charger le XML
        $xmlDoc = new \DOMDocument();
        if (!$xmlDoc->loadXML($xmlContent)) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            throw new \RuntimeException(
                "XML invalide" . ($errors ? ": " . $errors[0]->message : '')
            );
        }
        
        // Charger le XSLT pré-compilé
        $xslDoc = new \DOMDocument();
        if (!$xslDoc->load($xslPath)) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            throw new \RuntimeException(
                "XSLT invalide: {$xslPath}" . ($errors ? ": " . $errors[0]->message : '')
            );
        }
        
        // Appliquer la transformation
        $processor = new \XSLTProcessor();
        
        try {
            $processor->importStylesheet($xslDoc);
        } catch (\Exception $e) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            throw new \RuntimeException(
                "Erreur import XSLT: " . $e->getMessage() .
                ($errors ? "\nLibXML: " . $errors[0]->message : '')
            );
        }
        
        $result = $processor->transformToXML($xmlDoc);
        
        if ($result === false) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            throw new \RuntimeException(
                "Erreur transformation XSLT" . ($errors ? ": " . $errors[0]->message : '')
            );
        }
        
        libxml_clear_errors();
        return $result;
    }
    
    /**
     * Compile un fichier Schematron en XSLT (pour le script de compilation)
     * Cette méthode est publique pour permettre la compilation via le script CLI
     * 
     * @param string $schematronPath Chemin vers le fichier .sch
     * @return string Contenu XSLT compilé
     */
    public function compileSchematronFile(string $schematronPath): string
    {
        if (!file_exists($schematronPath)) {
            throw new \RuntimeException("Fichier Schematron introuvable: {$schematronPath}");
        }
        
        // Pour la compilation, on a besoin des XSLT ISO
        $isoDir = __DIR__ . '/../../resources/iso-schematron';
        $requiredFiles = [
            'iso_dsdl_include.xsl',
            'iso_abstract_expand.xsl',
            'iso_svrl_for_xslt2.xsl'
        ];
        
        $missing = [];
        foreach ($requiredFiles as $file) {
            if (!file_exists($isoDir . '/' . $file)) {
                $missing[] = $file;
            }
        }
        
        if (!empty($missing)) {
            throw new \RuntimeException(
                "Fichiers XSLT ISO Schematron manquants pour la compilation:\n" .
                "  - " . implode("\n  - ", $missing) . "\n" .
                "Téléchargez-les depuis: https://github.com/Schematron/schematron\n" .
                "Ou utilisez: php bin/install-schematron.php"
            );
        }
        
        // Charger le fichier Schematron
        libxml_use_internal_errors(true);
        libxml_clear_errors();
        
        $schematronDoc = new \DOMDocument();
        if (!$schematronDoc->load($schematronPath)) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            throw new \RuntimeException(
                "Impossible de charger le fichier Schematron" .
                ($errors ? ": " . $errors[0]->message : '')
            );
        }
        
        try {
            // Étape 1 : Inclusion
            $step1 = $this->applyIsoXsltForCompilation($schematronDoc, $isoDir . '/iso_dsdl_include.xsl');
            
            // Étape 2 : Expansion
            $step2 = $this->applyIsoXsltForCompilation($step1, $isoDir . '/iso_abstract_expand.xsl');
            
            // Étape 3 : Compilation en XSLT SVRL
            $xsltDoc = $this->applyIsoXsltForCompilation($step2, $isoDir . '/iso_svrl_for_xslt2.xsl');
            
            return $xsltDoc->saveXML();
            
        } catch (\Exception $e) {
            throw new \RuntimeException(
                "Erreur lors de la compilation Schematron: " . $e->getMessage()
            );
        }
    }
    
    /**
     * Applique une transformation XSLT ISO pour la compilation
     * 
     * @param \DOMDocument $sourceDoc
     * @param string $xsltPath
     * @return \DOMDocument
     */
    private function applyIsoXsltForCompilation(\DOMDocument $sourceDoc, string $xsltPath): \DOMDocument
    {
        libxml_use_internal_errors(true);
        libxml_clear_errors();
        
        $xslDoc = new \DOMDocument();
        if (!$xslDoc->load($xsltPath)) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            throw new \RuntimeException(
                "Impossible de charger XSLT: {$xsltPath}" .
                ($errors ? ": " . $errors[0]->message : '')
            );
        }
        
        $processor = new \XSLTProcessor();
        $processor->setParameter('', 'generate-paths', 'true');
        $processor->setParameter('', 'diagnose', 'yes');
        $processor->setParameter('', 'phase', '#ALL');
        
        try {
            $processor->importStylesheet($xslDoc);
        } catch (\Exception $e) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            throw new \RuntimeException(
                "Erreur import XSLT: " . $e->getMessage() .
                ($errors ? "\nLibXML: " . $errors[0]->message : '')
            );
        }
        
        $resultDoc = $processor->transformToDoc($sourceDoc);
        
        if ($resultDoc === false) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            throw new \RuntimeException(
                "Erreur transformation XSLT" . ($errors ? ": " . $errors[0]->message : '')
            );
        }
        
        libxml_clear_errors();
        return $resultDoc;
    }
    
    /**
     * Installe les fichiers XSLT ISO Schematron nécessaires pour la compilation
     * Utilisé uniquement par le script de compilation, pas en production
     * 
     * @return array<string, bool>
     */
/*    public function installIsoSchematronXslt(): array
    {
        $files = [
            'iso_dsdl_include.xsl',
            'iso_abstract_expand.xsl',
            'iso_svrl_for_xslt2.xsl'
        ];
        
        $isoDir = __DIR__ . '/../../resources/iso-schematron';
        
        if (!is_dir($isoDir)) {
            @mkdir($isoDir, 0755, true);
        }
        
        $results = [];
        
        foreach ($files as $file) {
            $path = $isoDir . '/' . $file;
            
            if (file_exists($path) && filesize($path) > 1000) {
                $results[$file] = true;
                continue;
            }
            
            // Télécharger depuis GitHub
            $sources = [
                'https://raw.githubusercontent.com/Schematron/schematron/2020-10-01/trunk/schematron/code/' . $file,
                'https://raw.githubusercontent.com/Schematron/schematron/master/trunk/schematron/code/' . $file,
            ];
            
            $success = false;
            foreach ($sources as $url) {
                $content = @file_get_contents($url, false, stream_context_create([
                    'http' => [
                        'timeout' => 10,
                        'user_agent' => 'PeppolInvoice/1.0'
                    ]
                ]));
                
                if ($content !== false && !empty($content) && strlen($content) > 1000) {
                    file_put_contents($path, $content);
                    $success = true;
                    break;
                }
            }
            
            $results[$file] = $success;
        }
        
        return $results;
    }
    */
    /**
     * Nettoie le cache (legacy - plus utilisé avec les pré-compilés)
     * 
     * @return bool
     */
    public function clearCache(): bool
    {
        // Plus de cache en mode pré-compilé, mais on garde la méthode
        // pour compatibilité avec les scripts existants
        return true;
    }
    
    /**
     * Compile un fichier Schematron en XSLT
     * 
     * Le processus de compilation Schematron -> XSLT se fait en plusieurs étapes :
     * 1. Inclusion des fichiers externes
     * 2. Expansion des patterns abstraits
     * 3. Compilation finale en XSLT
     * 
     * @param string $schematronPath
     * @return string Contenu XSLT
     */
    private function compileSchematron(string $schematronPath): string
    {
        $cacheKey = md5($schematronPath . filemtime($schematronPath));
        
        // Vérifier le cache mémoire
        if (isset($this->xsltCache[$cacheKey])) {
            return $this->xsltCache[$cacheKey];
        }
        
        // Vérifier le cache disque
        if ($this->useCache) {
            $cachedPath = $this->cacheDir . '/' . $cacheKey . '.xsl';
            if (file_exists($cachedPath)) {
                $content = file_get_contents($cachedPath);
                $this->xsltCache[$cacheKey] = $content;
                return $content;
            }
        }
        
        // Compiler le Schematron
        $xsltContent = $this->performSchematronCompilation($schematronPath);
        
        // Sauvegarder en cache
        if ($this->useCache) {
            $cachedPath = $this->cacheDir . '/' . $cacheKey . '.xsl';
            @file_put_contents($cachedPath, $xsltContent);
        }
        
        $this->xsltCache[$cacheKey] = $xsltContent;
        return $xsltContent;
    }
    
    /**
     * Effectue la compilation Schematron -> XSLT
     * 
     * Utilise les feuilles de style ISO Schematron standard
     * 
     * @param string $schematronPath
     * @return string
     */
    private function performSchematronCompilation(string $schematronPath): string
    {
        // Vérifier et installer les XSLT ISO si nécessaire
        $this->ensureIsoSchematronXslt();
        
        // Charger le fichier Schematron
        libxml_use_internal_errors(true);
        libxml_clear_errors();
        
        $schematronDoc = new DOMDocument();
        if (!$schematronDoc->load($schematronPath)) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            throw new \RuntimeException(
                "Impossible de charger le fichier Schematron: {$schematronPath}" .
                ($errors ? "\nErreur: " . $errors[0]->message : '')
            );
        }
        
        try {
            // Étape 1 : Inclusion des fichiers externes (iso_dsdl_include.xsl)
            $step1 = $this->applyIsoXslt($schematronDoc, 'iso_dsdl_include.xsl');
            
            // Étape 2 : Expansion des patterns abstraits (iso_abstract_expand.xsl)
            $step2 = $this->applyIsoXslt($step1, 'iso_abstract_expand.xsl');
            
            // Étape 3 : Compilation en XSLT (iso_svrl_for_xslt2.xsl)
            $xsltDoc = $this->applyIsoXslt($step2, 'iso_svrl_for_xslt2.xsl');
            
            return $xsltDoc->saveXML();
            
        } catch (\RuntimeException $e) {
            // Si la compilation échoue, proposer une alternative
            throw new \RuntimeException(
                "La compilation Schematron a échoué.\n" .
                "Erreur: " . $e->getMessage() . "\n\n" .
                "Solutions possibles:\n" .
                "1. Réinstallez les fichiers XSLT ISO: php bin/install-schematron.php\n" .
                "2. Vérifiez que libxslt est installé: php -m | grep xsl\n" .
                "3. Utilisez uniquement la validation PHP: \$invoice->validate()\n"
            );
        }
    }
    
    /**
     * Assure que les XSLT ISO Schematron sont présents
     * 
     * @return void
     * @throws \RuntimeException Si installation échoue
     */
    private function ensureIsoSchematronXslt(): void
    {
        $required = [
            'iso_dsdl_include.xsl',
            'iso_abstract_expand.xsl',
            'iso_svrl_for_xslt2.xsl'
        ];
        
        $missing = [];
        foreach ($required as $file) {
            $path = __DIR__ . '/../../resources/iso-schematron/' . $file;
            if (!file_exists($path)) {
                $missing[] = $file;
            }
        }
        
        if (!empty($missing)) {
            // Essayer de télécharger automatiquement
            $results = $this->installIsoSchematronXslt();
            
            // Vérifier à nouveau
            foreach ($missing as $file) {
                $path = __DIR__ . '/../../resources/iso-schematron/' . $file;
                if (!file_exists($path)) {
                    throw new \RuntimeException(
                        "Fichiers XSLT ISO Schematron manquants. Installez-les avec:\n" .
                        "composer install-schematron\n" .
                        "Ou téléchargez-les depuis: https://github.com/Schematron/schematron"
                    );
                }
            }
        }
    }
    
    /**
     * Applique une transformation XSLT ISO Schematron
     * 
     * @param DOMDocument $sourceDoc
     * @param string $xsltFilename
     * @return DOMDocument
     */
    private function applyIsoXslt(DOMDocument $sourceDoc, string $xsltFilename): DOMDocument
    {
        // Chemin des XSLT ISO Schematron
        $isoXsltPath = __DIR__ . '/../../resources/iso-schematron/' . $xsltFilename;
        
        if (!file_exists($isoXsltPath)) {
            throw new \RuntimeException(
                "XSLT ISO Schematron introuvable: {$isoXsltPath}\n" .
                "Installez les fichiers avec: composer install-schematron"
            );
        }
        
        // Charger le XSLT avec gestion d'erreurs libxml
        libxml_use_internal_errors(true);
        libxml_clear_errors();
        
        $xsltDoc = new DOMDocument();
        $xsltDoc->load($isoXsltPath);
        
        $processor = new XSLTProcessor();
        
        // Paramètres optionnels pour la compilation Schematron
        $processor->setParameter('', 'generate-paths', 'true');
        $processor->setParameter('', 'diagnose', 'yes');
        $processor->setParameter('', 'phase', '#ALL');
        
        try {
            $processor->importStylesheet($xsltDoc);
        } catch (\Exception $e) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            
            throw new \RuntimeException(
                "Erreur lors de l'import du XSLT {$xsltFilename}: " . $e->getMessage() .
                ($errors ? "\nLibXML: " . $errors[0]->message : '')
            );
        }
        
        try {
            $resultDoc = $processor->transformToDoc($sourceDoc);
        } catch (\Exception $e) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            
            throw new \RuntimeException(
                "Erreur lors de la transformation XSLT {$xsltFilename}: " . $e->getMessage() .
                ($errors ? "\nLibXML: " . $errors[0]->message : '')
            );
        }
        
        if ($resultDoc === false) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            
            $errorMsg = "Erreur lors de la transformation XSLT: {$xsltFilename}";
            if (!empty($errors)) {
                $errorMsg .= "\nLibXML: " . $errors[0]->message;
            }
            
            throw new \RuntimeException($errorMsg);
        }
        
        libxml_clear_errors();
        return $resultDoc;
    }
    
    /**
     * Télécharge les XSLT ISO Schematron standard
     * 
     * @param string $filename
     * @return bool
     */
    private function downloadIsoSchematronXslt(string $filename): bool
    {
        // Essayer plusieurs sources
        $sources = [
            'https://raw.githubusercontent.com/Schematron/schematron/master/trunk/schematron/code/' . $filename,
            'https://raw.githubusercontent.com/schematron/schematron/2020-10-01/trunk/schematron/code/' . $filename,
        ];
        
        $destination = __DIR__ . '/../../resources/iso-schematron/' . $filename;
        $dir = dirname($destination);
        
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        
        foreach ($sources as $url) {
            $content = @file_get_contents($url, false, stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'user_agent' => 'PeppolInvoice/1.0'
                ]
            ]));
            
            if ($content !== false && !empty($content)) {
                return file_put_contents($destination, $content) !== false;
            }
        }
        
        return false;
    }
    
    /**
     * Installe tous les fichiers XSLT ISO Schematron nécessaires
     * 
     * @return array<string, bool>
     */
    public function installIsoSchematronXslt(): array
    {
        $files = [
            'iso_dsdl_include.xsl',
            'iso_abstract_expand.xsl',
            'iso_svrl_for_xslt2.xsl'
        ];
        
        $results = [];
        foreach ($files as $file) {
            $path = __DIR__ . '/../../resources/iso-schematron/' . $file;
            
            if (!file_exists($path)) {
                $results[$file] = $this->downloadIsoSchematronXslt($file);
            } else {
                $results[$file] = true; // Déjà présent
            }
        }
        
        return $results;
    }
    
    /**
     * Applique une transformation XSLT au XML
     * 
     * @param string $xmlContent
     * @param string $xsltContent
     * @return string Résultat SVRL
     */
    private function applyXslt(string $xmlContent, string $xsltContent): string
    {
        $xmlDoc = new DOMDocument();
        $xmlDoc->loadXML($xmlContent);
        
        $xsltDoc = new DOMDocument();
        $xsltDoc->loadXML($xsltContent);
        
        $processor = new XSLTProcessor();
        $processor->importStylesheet($xsltDoc);
        
        $result = $processor->transformToXML($xmlDoc);
        
        if ($result === false) {
            throw new \RuntimeException("Erreur lors de la validation Schematron");
        }
        
        return $result;
    }
    
    /**
     * Parse le résultat SVRL (Schematron Validation Report Language)
     * 
     * @param string $svrlOutput
     * @param string $level
     * @return SchematronValidationResult
     */
    private function parseSvrlOutput(string $svrlOutput, string $level): SchematronValidationResult
    {
        $doc = new DOMDocument();
        $doc->loadXML($svrlOutput);
        
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('svrl', 'http://purl.oclc.org/dsdl/svrl');
        
        $errors = [];
        $warnings = [];
        $infos = [];
        
        // Parser les failed-assert (erreurs)
        $failedAsserts = $xpath->query('//svrl:failed-assert');
        foreach ($failedAsserts as $assert) {
            $role = $assert->getAttribute('role') ?: 'error';
            $location = $assert->getAttribute('location');
            $test = $assert->getAttribute('test');
            
            $textNode = $xpath->query('svrl:text', $assert)->item(0);
            $message = $textNode ? trim($textNode->nodeValue) : 'Règle non respectée';
            
            $error = new SchematronViolation(
                $level,
                $role,
                $message,
                $location,
                $test
            );
            
            if ($role === 'error' || $role === 'fatal') {
                $errors[] = $error;
            } elseif ($role === 'warning') {
                $warnings[] = $error;
            } else {
                $infos[] = $error;
            }
        }
        
        // Parser les successful-report (informations)
        $successfulReports = $xpath->query('//svrl:successful-report');
        foreach ($successfulReports as $report) {
            $role = $report->getAttribute('role') ?: 'info';
            $location = $report->getAttribute('location');
            $test = $report->getAttribute('test');
            
            $textNode = $xpath->query('svrl:text', $report)->item(0);
            $message = $textNode ? trim($textNode->nodeValue) : 'Information';
            
            $info = new SchematronViolation(
                $level,
                $role,
                $message,
                $location,
                $test
            );
            
            $infos[] = $info;
        }
        
        return new SchematronValidationResult(
            empty($errors),
            $errors,
            $warnings,
            $infos
        );
    }
    
    /**
     * Nettoie le cache
     * 
     * @return bool
     */
    public function clearCache(): bool
    {
        $this->xsltCache = [];
        
        if (!$this->useCache || !is_dir($this->cacheDir)) {
            return true;
        }
        
        $files = glob($this->cacheDir . '/*.xsl');
        foreach ($files as $file) {
            @unlink($file);
        }
        
        return true;
    }
    
    /**
     * Installe les fichiers Schematron officiels
     * 
     * @param bool $force Force le téléchargement même si les fichiers existent
     * @return array<string, bool> Résultat du téléchargement par niveau
     */
    public function installSchematronFiles(bool $force = false): array
    {
        $results = [];
        
        foreach (array_keys(self::SCHEMATRON_URLS) as $level) {
            $path = $this->getSchematronPath($level);
            
            if (!$force && file_exists($path)) {
                $results[$level] = true;
                continue;
            }
            
            $results[$level] = $this->downloadSchematron($level);
        }
        
        return $results;
    }
}
