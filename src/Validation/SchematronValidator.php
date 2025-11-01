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
        
        if (!@$processor->importStylesheet($xslDoc)) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            throw new \RuntimeException(
                "Erreur import XSLT: " . basename($xslPath) .
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
            'iso_svrl_for_xslt2.xsl',
            'iso_schematron_skeleton_for_saxon.xsl'
        ];
        
        $missing = [];
        foreach ($requiredFiles as $file) {
            $path = $isoDir . '/' . $file;
            if (!file_exists($path)) {
                $missing[] = $file;
            }
        }
        
        if (!empty($missing)) {
            throw new \RuntimeException(
                "Fichiers XSLT ISO Schematron manquants pour la compilation:\n" .
                "  - " . implode("\n  - ", $missing) . "\n" .
                "Installez-les avec: php bin/install-schematron.php"
            );
        }
        
        // Charger le fichier Schematron
        libxml_use_internal_errors(true);
        libxml_clear_errors();
        
        $schematronDoc = new \DOMDocument();
        $schematronDoc->preserveWhiteSpace = false;
        $schematronDoc->formatOutput = false;
        
        if (!$schematronDoc->load($schematronPath)) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            throw new \RuntimeException(
                "Impossible de charger le fichier Schematron: {$schematronPath}" .
                ($errors ? "\n  " . $errors[0]->message : '')
            );
        }
        
        try {
            echo "  Étape 1/3: Inclusion des fichiers externes...\n";
            $step1 = $this->applyIsoXsltForCompilation(
                $schematronDoc, 
                $isoDir . '/iso_dsdl_include.xsl'
            );
            
            echo "  Étape 2/3: Expansion des patterns abstraits...\n";
            $step2 = $this->applyIsoXsltForCompilation(
                $step1, 
                $isoDir . '/iso_abstract_expand.xsl'
            );
            
            echo "  Étape 3/3: Compilation finale en XSLT...\n";
            $xsltDoc = $this->applyIsoXsltForCompilation(
                $step2, 
                $isoDir . '/iso_svrl_for_xslt2.xsl'
            );
            
            $result = $xsltDoc->saveXML();
            
            if (empty($result) || strlen($result) < 1000) {
                throw new \RuntimeException(
                    "XSLT compilé trop petit ou vide (" . strlen($result) . " octets)"
                );
            }
            
            return $result;
            
        } catch (\Exception $e) {
            // Si la compilation échoue, vérifier s'il y a une version pré-compilée disponible
            $filename = basename($schematronPath, '.sch');
            throw new \RuntimeException(
                "Erreur lors de la compilation Schematron: " . $e->getMessage() . "\n\n" .
                "💡 Astuce: Si un fichier XSLT pré-compilé officiel existe pour '{$filename}',\n" .
                "   téléchargez-le directement au lieu de compiler le .sch source."
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
        
        // Charger le XSLT
        $xslDoc = new \DOMDocument();
        if (!$xslDoc->load($xsltPath)) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            throw new \RuntimeException(
                "Impossible de charger XSLT: {$xsltPath}" .
                ($errors ? ": " . $errors[0]->message : '')
            );
        }
        
        // Créer le processeur
        $processor = new \XSLTProcessor();
        
        // IMPORTANT : Augmenter la limite de récursion pour éviter l'erreur "infinite template recursion"
        // Certains schématrons (comme UBL.BE) ont des templates récursifs profonds
        if (method_exists($processor, 'setSecurityPrefs')) {
            $processor->setSecurityPrefs(XSL_SECPREF_NONE);
        }
        
        // Paramètres Schematron standards
        $processor->setParameter('', 'generate-paths', 'true');
        $processor->setParameter('', 'diagnose', 'yes');
        $processor->setParameter('', 'phase', '#ALL');
        
        // Paramètre pour éviter la récursion infinie dans strip-strings
        // Si le fichier est iso_dsdl_include.xsl, on désactive certaines optimisations
        if (basename($xsltPath) === 'iso_dsdl_include.xsl') {
            // Ne pas essayer de strip les whitespaces de manière récursive
            $processor->setParameter('', 'strip-space', 'false');
        }
        
        // Importer le stylesheet
        if (!@$processor->importStylesheet($xslDoc)) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            
            $errorMsg = "Erreur import XSLT: {$xsltPath}";
            if (!empty($errors)) {
                $errorMsg .= "\n";
                foreach ($errors as $error) {
                    $errorMsg .= "  LibXML [{$error->level}] {$error->message} (ligne {$error->line})";
                }
            }
            
            throw new \RuntimeException($errorMsg);
        }
        
        // Augmenter la limite de récursion via ini_set si possible
        $oldMaxDepth = ini_get('xsl.max_depth');
        if ($oldMaxDepth !== false) {
            // Tenter d'augmenter à 10000 (valeur élevée pour UBL.BE complexe)
            @ini_set('xsl.max_depth', '10000');
        }
        
        // Alternative : Définir via setParameter si ini_set échoue
        if (ini_get('xsl.max_depth') < 5000) {
            // Si on ne peut pas modifier via ini_set, on tente via le processeur
            $processor->setParameter('', 'xsl.max_depth', '10000');
        }
        
        // Transformer avec gestion des erreurs
        libxml_clear_errors();
        $resultDoc = @$processor->transformToDoc($sourceDoc);
        
        // Restaurer l'ancienne valeur
        if ($oldMaxDepth !== false) {
            ini_set('xsl.max_depth', $oldMaxDepth);
        }
        
        if ($resultDoc === false) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            
            // Vérifier si c'est une erreur de récursion
            $isRecursionError = false;
            $errorMsg = "Erreur transformation XSLT: " . basename($xsltPath);
            
            if (!empty($errors)) {
                $errorMsg .= "\n";
                foreach ($errors as $error) {
                    $msg = trim($error->message);
                    if (strpos($msg, 'infinite template recursion') !== false) {
                        $isRecursionError = true;
                    }
                    $errorMsg .= "  LibXML [{$error->level}] {$msg}";
                    if ($error->line > 0) {
                        $errorMsg .= " (ligne {$error->line})";
                    }
                    $errorMsg .= "\n";
                }
            }
            
            // Si c'est une erreur de récursion, donner des instructions spécifiques
            if ($isRecursionError) {
                $errorMsg .= "\n⚠️  Le fichier Schematron contient des structures trop complexes.\n";
                $errorMsg .= "Solutions possibles:\n";
                $errorMsg .= "1. Utiliser le fichier XSLT pré-compilé officiel si disponible\n";
                $errorMsg .= "2. Simplifier le fichier .sch source\n";
                $errorMsg .= "3. Augmenter xsl.max_depth dans php.ini (actuellement: " . ini_get('xsl.max_depth') . ")\n";
            }
            
            throw new \RuntimeException($errorMsg);
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
    public function installIsoSchematronXslt(): array
    {
        $files = [
            'iso_dsdl_include.xsl',
            'iso_abstract_expand.xsl',
            'iso_svrl_for_xslt2.xsl',
            'iso_schematron_skeleton_for_saxon.xsl'
        ];
        
        $isoDir = __DIR__ . '/../../resources/iso-schematron';
        
        if (!is_dir($isoDir)) {
            @mkdir($isoDir, 0755, true);
        }
        
        $results = [];
        
        foreach ($files as $file) {
            $path = $isoDir . '/' . $file;
            
            // Si déjà présent et valide, skip
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
                        'timeout' => 30,
                        'user_agent' => 'PHPPeppol/1.0',
                        'follow_location' => true
                    ],
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false
                    ]
                ]));
                
                if ($content !== false && !empty($content) && strlen($content) > 1000) {
                    if (file_put_contents($path, $content) !== false) {
                        $success = true;
                        break;
                    }
                }
            }
            
            $results[$file] = $success;
        }
        
        return $results;
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
        if (!is_dir($this->compiledDir)) {
            return true;
        }
        
        $files = glob($this->compiledDir . '/*.xsl');
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
        $schematronDir = __DIR__ . '/../../resources/schematron';
        
        if (!is_dir($schematronDir)) {
            @mkdir($schematronDir, 0755, true);
        }
        
        // UBL.BE - Télécharger et extraire du ZIP
        $ublbePath = $schematronDir . '/UBLBE_Invoice-1.0.sch';
        if ($force || !file_exists($ublbePath)) {
            try {
                $zipContent = @file_get_contents(self::SCHEMATRON_URLS['ublbe'], false, stream_context_create([
                    'http' => ['timeout' => 30, 'user_agent' => 'PHPPeppol/1.0'],
                    'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
                ]));
                
                if ($zipContent !== false) {
                    $zipPath = sys_get_temp_dir() . '/ublbe_' . uniqid() . '.zip';
                    file_put_contents($zipPath, $zipContent);
                    
                    $zip = new \ZipArchive();
                    if ($zip->open($zipPath)) {
                        for ($i = 0; $i < $zip->numFiles; $i++) {
                            $filename = $zip->getNameIndex($i);
                            if (preg_match('/UBLBE.*Invoice.*\.sch$/i', $filename)) {
                                $content = $zip->getFromIndex($i);
                                file_put_contents($ublbePath, $content);
                                break;
                            }
                        }
                        $zip->close();
                    }
                    @unlink($zipPath);
                }
                
                $results['ublbe'] = file_exists($ublbePath);
            } catch (\Exception $e) {
                $results['ublbe'] = false;
            }
        } else {
            $results['ublbe'] = true;
        }
        
        // EN 16931 - Téléchargement direct
        $en16931Path = $schematronDir . '/EN16931_UBL-1.3.sch';
        if ($force || !file_exists($en16931Path)) {
            $content = @file_get_contents(self::SCHEMATRON_URLS['en16931'], false, stream_context_create([
                'http' => ['timeout' => 30, 'user_agent' => 'PHPPeppol/1.0'],
                'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
            ]));
            
            if ($content !== false) {
                file_put_contents($en16931Path, $content);
            }
            
            $results['en16931'] = file_exists($en16931Path);
        } else {
            $results['en16931'] = true;
        }
        
        // Peppol - Téléchargement direct
        $peppolPath = $schematronDir . '/PEPPOL_CIUS-UBL-1.0.sch';
        if ($force || !file_exists($peppolPath)) {
            $content = @file_get_contents(self::SCHEMATRON_URLS['peppol'], false, stream_context_create([
                'http' => ['timeout' => 30, 'user_agent' => 'PHPPeppol/1.0'],
                'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
            ]));
            
            if ($content !== false) {
                file_put_contents($peppolPath, $content);
            }
            
            $results['peppol'] = file_exists($peppolPath);
        } else {
            $results['peppol'] = true;
        }
        
        return $results;
    }
}
