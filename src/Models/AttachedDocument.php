<?php

declare(strict_types=1);

namespace Peppol\Models;

use Peppol\Core\InvoiceConstants;

/**
 * Modèle de document joint
 * 
 * Représente un document joint à la facture conforme à la norme EN 16931 (BG-24)
 * 
 * @package Peppol\Models
 * @author Votre Nom
 * @version 1.0
 */
class AttachedDocument
{
    /**
     * @var string Nom du fichier (BT-125)
     */
    private string $filename;
    
    /**
     * @var string Type MIME (BT-125-1)
     */
    private string $mimeType;
    
    /**
     * @var string Contenu encodé en Base64
     */
    private string $content;
    
    /**
     * @var string|null Description du document (BT-123)
     */
    private ?string $description;
    
    
    /**
     * @var int Taille du fichier en octets
     */
    private int $size;
    
    
        private ?string $documentType = null;
    
    /**
     * Constructeur
     * 
     * @param string $filename Nom du fichier
     * @param string $fileContent Contenu binaire du fichier
     * @param string $mimeType Type MIME
     * @param string|null $description Description
     * @param string|null $documentType Type de document
     * @throws \InvalidArgumentException
     */
    public function __construct(
        string $filename,
        string $fileContent,
        string $mimeType = 'application/pdf',
        ?string $description = null,
        ?string $documentType = null
    ) {
        $this->setFilename($filename);
        $this->setMimeType($mimeType);
        $this->setContent($fileContent);
        $this->description = $description;
        $this->documentType = $documentType;
    }
    
    /**
     * Crée un document joint depuis un fichier
     * 
     * @param string $filePath Chemin du fichier
     * @param string|null $description Description
     * @param string|null $documentType Type de document
     * @return self
     * @throws \InvalidArgumentException
     */
    public static function fromFile(
        string $filePath,
        ?string $description = null,
    ): self {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("Le fichier n'existe pas: {$filePath}");
        }
        
        if (!is_readable($filePath)) {
            throw new \InvalidArgumentException("Le fichier n'est pas lisible: {$filePath}");
        }
        
        $fileContent = file_get_contents($filePath);
        if ($fileContent === false) {
            throw new \InvalidArgumentException("Impossible de lire le fichier: {$filePath}");
        }
        
        $filename = basename($filePath);
        $mimeType = self::detectMimeType($filePath);
        
        return new self($filename, $fileContent, $mimeType, $description);
    }
    
    /**
     * Détecte le type MIME d'un fichier
     * 
     * @param string $filePath
     * @return string
     */
    private static function detectMimeType(string $filePath): string
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        
        $mimeTypes = [
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'xml' => 'application/xml',
            'csv' => 'text/csv',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'xls' => 'application/vnd.ms-excel',
            'zip' => 'application/zip',
            'txt' => 'text/plain'
        ];
        
        return $mimeTypes[$extension] ?? 'application/octet-stream';
    }
    
    private function setFilename(string $filename): void
    {
        if (empty(trim($filename))) {
            throw new \InvalidArgumentException('Le nom du fichier ne peut pas être vide');
        }
        $this->filename = $filename;
    }
    
    private function setMimeType(string $mimeType): void
    {
        if (!in_array(strtolower($mimeType), InvoiceConstants::SUPPORTED_MIME_TYPES)) {
            throw new \InvalidArgumentException(
                'Type MIME non supporté. Types valides: ' . 
                implode(', ', InvoiceConstants::SUPPORTED_MIME_TYPES)
            );
        }
        $this->mimeType = $mimeType;
    }
    
    private function setContent(string $fileContent): void
    {
        if (empty($fileContent)) {
            throw new \InvalidArgumentException('Le contenu du fichier ne peut pas être vide');
        }
        
        $this->size = strlen($fileContent);
        
        if ($this->size > InvoiceConstants::MAX_ATTACHMENT_SIZE) {
            throw new \InvalidArgumentException(
                sprintf(
                    'La taille du fichier (%d octets) dépasse la limite de %d octets (%.2f MB)',
                    $this->size,
                    InvoiceConstants::MAX_ATTACHMENT_SIZE,
                    InvoiceConstants::MAX_ATTACHMENT_SIZE / 1048576
                )
            );
        }
        
        $this->content = base64_encode($fileContent);
    }
    
    /**
     * Retourne le contenu décodé (binaire)
     * 
     * @return string
     */
    public function getDecodedContent(): string
    {
        return base64_decode($this->content);
    }
    
    /**
     * Sauvegarde le document dans un fichier
     * 
     * @param string $destinationPath Chemin de destination
     * @return bool True si succès
     */
    public function saveToFile(string $destinationPath): bool
    {
        return file_put_contents($destinationPath, $this->getDecodedContent()) !== false;
    }
    
    /**
     * Retourne la taille du fichier en format lisible
     * 
     * @return string
     */
    public function getFormattedSize(): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $this->size;
        $unit = 0;
        
        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }
        
        return round($size, 2) . ' ' . $units[$unit];
    }
    
    /**
     * Valide le document joint
     * 
     * @return array<string> Liste des erreurs (vide si valide)
     */
    public function validate(): array
    {
        $errors = [];
        
        if (empty(trim($this->filename))) {
            $errors[] = 'Nom de fichier obligatoire';
        }
        
        if (!in_array($this->mimeType, InvoiceConstants::SUPPORTED_MIME_TYPES)) {
            $errors[] = 'Type MIME non supporté';
        }
        
        if (empty($this->content)) {
            $errors[] = 'Contenu du fichier vide';
        }
        
        if ($this->size > InvoiceConstants::MAX_ATTACHMENT_SIZE) {
            $errors[] = 'Fichier trop volumineux';
        }
        
        return $errors;
    }
    
    /**
     * Retourne le document sous forme de tableau
     * 
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'filename' => $this->filename,
            'mimeType' => $this->mimeType,
            'content' => $this->content,
            'description' => $this->description,
            'documentType' => $this->documentType,
            'size' => $this->size,
            'formattedSize' => $this->getFormattedSize()
        ];
    }
    
    // Getters
    public function getFilename(): string { return $this->filename; }
    public function getMimeType(): string { return $this->mimeType; }
    public function getContent(): string { return $this->content; }
    public function getDescription(): ?string { return $this->description; }
    public function getDocumentType(): ?string { return $this->documentType; }
    public function getSize(): int { return $this->size; }
}
