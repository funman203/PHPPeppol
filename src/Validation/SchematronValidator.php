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
     * @var string Chemin du répertoire des schémas Schematron
     */
    private string $schematronDir;
    
    /**
     * @var array<string, string> Cache des XSLT compilés en mémoire
     */
    private array $xsltCache = [];
    
    /**
     * Constructeur
     * 
     * @param string|null $schematronDir Répertoire contenant les fichiers .sch
     * @param string|null $cacheDir Répertoire de cache (null = pas de cache disque)
     * @param bool $useCache Active le cache (défaut: true)
     */
    public function __construct(
        ?string $schematronDir = null,
        ?string $cacheDir = null,
        bool $useCache = true
    ) {
        $this->schematronDir = $schematronDir ?? __DIR__ . '/../../resources/schematron';
        $this->cacheDir = $cacheDir ?? sys_get_temp_dir() . '/peppol_schematron_cache';
        $this->useCache = $useCache;
        
        // Vérifier que l'extension XSL est disponible
        if (!extension_loaded('xsl')) {
            throw new \RuntimeException(
                'Extension PHP XSL requise pour la validation Schematron. ' .
                'Installez-la avec: apt-get install php-xsl (Linux) ou activez-la dans php.ini'
            );
        }
        
        // Créer le répertoire de cache si nécessaire
        if ($this->useCache && !is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0755, true);
        }
    }
    
    /**
     * Valide un document XML contre les règles Schematron UBL.BE
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
            $result = $this->validateLevel($xmlContent, $level);
            $allErrors = array_merge($allErrors, $result->getErrors());
            $allWarnings = array_merge($allWarnings, $result->getWarnings());
            $allInfos = array_merge($allInfos, $result->getInfos());
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
        // Charger le fichier Schematron
        $schematronPath = $this->getSchematronPath($level);
        
        if (!file_exists($schematronPath)) {
            // Essayer de télécharger le fichier si l'URL est connue
            if (isset(self::SCHEMATRON_URLS[$level])) {
                $this->downloadSchematron($level);
            }
            
            if (!file_exists($schematronPath)) {
                throw new \RuntimeException(
                    "Fichier Schematron introuvable: {$schematronPath}. " .
                    "Téléchargez-le depuis https://www.ubl.be"
                );
            }
        }
        
        // Compiler le Schematron en XSLT
        $xsltContent = $this->compileSchematron($schematronPath);
        
        // Appliquer la transformation XSLT au XML
        $svrlOutput = $this->applyXslt($xmlContent, $xsltContent);
        
        // Parser le résultat SVRL (Schematron Validation Report Language)
        return $this->parseSvrlOutput($svrlOutput, $level);
    }
    
    /**
     * Retourne le chemin du fichier Schematron
     * 
     * @param string $level
     * @return string
     */
    private function getSchematronPath(string $level): string
    {
        $filenames = [
            'ublbe' => 'UBLBE_Invoice-1.0.sch',
            'en16931' => 'EN16931_UBL-1.3.sch',
            'peppol' => 'PEPPOL_CIUS-UBL-1.0.sch'
        ];
        
        $filename = $filenames[$level] ?? "{$level}.sch";
        return $this->schematronDir . '/' . $filename;
    }
    
    /**
     * Télécharge un fichier Schematron officiel
     * 
     * @param string $level
     * @return bool
     */
    private function downloadSchematron(string $level): bool
    {
        if (!isset(self::SCHEMATRON_URLS[$level])) {
            return false;
        }
        
        $url = self::SCHEMATRON_URLS[$level];
        $destination = $this->getSchematronPath($level);
        
        // Créer le répertoire si nécessaire
        $dir = dirname($destination);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        
        // Cas spécial pour UBL.BE (fichier ZIP)
        if ($level === 'ublbe') {
            return $this->downloadAndExtractUblBe($url, $destination);
        }
        
        // Télécharger le fichier
        $content = @file_get_contents($url);
        if ($content === false) {
            return false;
        }
        
        return file_put_contents($destination, $content) !== false;
    }
    
    /**
     * Télécharge et extrait le ZIP UBL.BE
     * 
     * @param string $url
     * @param string $destination
     * @return bool
     */
    private function downloadAndExtractUblBe(string $url, string $destination): bool
    {
        // Télécharger le ZIP
        $zipContent = @file_get_contents($url);
        if ($zipContent === false) {
            return false;
        }
        
        // Sauvegarder temporairement le ZIP
        $tempZip = sys_get_temp_dir() . '/ublbe_' . uniqid() . '.zip';
        file_put_contents($tempZip, $zipContent);
        
        // Extraire le fichier Schematron
        $zip = new \ZipArchive();
        if ($zip->open($tempZip) !== true) {
            @unlink($tempZip);
            return false;
        }
        
        // Chercher le fichier .sch dans le ZIP
        $schematronFile = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            if (preg_match('/UBLBE.*\.sch$/i', $filename)) {
                $schematronFile = $filename;
                break;
            }
        }
        
        if ($schematronFile === null) {
            $zip->close();
            @unlink($tempZip);
            return false;
        }
        
        // Extraire le fichier
        $content = $zip->getFromName($schematronFile);
        $zip->close();
        @unlink($tempZip);
        
        if ($content === false) {
            return false;
        }
        
        return file_put_contents($destination, $content) !== false;
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
        // Charger le fichier Schematron
        $schematronDoc = new DOMDocument();
        $schematronDoc->load($schematronPath);
        
        // Étape 1 : Inclusion des fichiers externes (iso_dsdl_include.xsl)
        $step1 = $this->applyIsoXslt($schematronDoc, 'iso_dsdl_include.xsl');
        
        // Étape 2 : Expansion des patterns abstraits (iso_abstract_expand.xsl)
        $step2 = $this->applyIsoXslt($step1, 'iso_abstract_expand.xsl');
        
        // Étape 3 : Compilation en XSLT (iso_svrl_for_xslt2.xsl)
        $xsltDoc = $this->applyIsoXslt($step2, 'iso_svrl_for_xslt2.xsl');
        
        return $xsltDoc->saveXML();
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
        // Chemin des XSLT ISO Schematron (inclus dans les ressources)
        $isoXsltPath = __DIR__ . '/../../resources/iso-schematron/' . $xsltFilename;
        
        if (!file_exists($isoXsltPath)) {
            // Fallback : utiliser les XSLT ISO depuis une source en ligne
            $this->downloadIsoSchematronXslt($xsltFilename);
        }
        
        if (!file_exists($isoXsltPath)) {
            throw new \RuntimeException(
                "XSLT ISO Schematron introuvable: {$isoXsltPath}. " .
                "Vous devez installer les feuilles de style ISO Schematron."
            );
        }
        
        $xsltDoc = new DOMDocument();
        $xsltDoc->load($isoXsltPath);
        
        $processor = new XSLTProcessor();
        $processor->importStylesheet($xsltDoc);
        
        $resultDoc = $processor->transformToDoc($sourceDoc);
        
        if ($resultDoc === false) {
            throw new \RuntimeException("Erreur lors de la transformation XSLT");
        }
        
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
        $baseUrl = 'https://raw.githubusercontent.com/Schematron/schematron/master/trunk/schematron/code/';
        $url = $baseUrl . $filename;
        
        $destination = __DIR__ . '/../../resources/iso-schematron/' . $filename;
        $dir = dirname($destination);
        
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        
        $content = @file_get_contents($url);
        if ($content === false) {
            return false;
        }
        
        return file_put_contents($destination, $content) !== false;
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
