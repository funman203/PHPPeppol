<?php

declare(strict_types=1);

use Peppol\Standards\EN16931Invoice;
use Peppol\Formats\XmlExporter;
use Peppol\Formats\XmlImporter;

/**
 * Classe façade PeppolInvoice
 * 
 * Simplifie l'utilisation de la bibliothèque de facturation électronique
 * en fournissant une API conviviale pour créer, exporter et importer
 * des factures conformes à Peppol BIS
 * 
 * Cette classe étend UblBeInvoice (conforme EN 16931)
 * 
 * @package Peppol
 * @author Votre Nom
 * @version 1.0
 */
class PeppolInvoice extends EN16931Invoice
{
    /**
     * @var string|null Référence d'acheteur ou PO - Obligatoire
     */
    protected ?string $buyerReference = null;
        
    /**
     * Définit la référence d'acheteur
     * Obligatoire si pas de référence de commande
     * 
     * @param string $buyerReference
     * @return self
     */
    public function setBuyerReference(string $buyerReference): self
    {
        $this->buyerReference = $buyerReference;
        return $this;
    }
    
    /**
     * Constructeur simplifié
     * 
     * @param string $invoiceNumber Numéro de facture
     * @param string $issueDate Date d'émission (YYYY-MM-DD)
     * @param string $invoiceTypeCode Type de facture (défaut: 380 = facture commerciale)
     * @param string $currencyCode Devise (défaut: EUR)
     */
    public function __construct(
        string $invoiceNumber,
        string $issueDate,
        string $invoiceTypeCode = '380',
        string $currencyCode = 'EUR'
    ) {
        parent::__construct($invoiceNumber, $issueDate, $invoiceTypeCode, $currencyCode);
    }
    
    /**
     * Exporte la facture au format XML UBL 2.1
     * 
     * @return string Contenu XML
     * @throws \InvalidArgumentException Si la facture n'est pas valide
     */
    public function toXml(): string
    {
        $exporter = new XmlExporter($this);
        return $exporter->toUbl21();
    }
    
    /**
     * Sauvegarde la facture dans un fichier XML
     * 
     * @param string $filepath Chemin du fichier de destination
     * @return bool True si succès
     */
    public function saveXml(string $filepath): bool
    {
        $exporter = new XmlExporter($this);
        return $exporter->saveToFile($filepath);
    }
    
    /**
     * Exporte la facture au format JSON
     * 
     * @param bool $prettyPrint Formater le JSON (défaut: true)
     * @return string Contenu JSON
     */
    public function toJson(bool $prettyPrint = true): string
    {
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        if ($prettyPrint) {
            $flags |= JSON_PRETTY_PRINT;
        }
        
        return json_encode($this->toArray(), $flags);
    }
    
    /**
     * Sauvegarde la facture dans un fichier JSON
     * 
     * @param string $filepath Chemin du fichier de destination
     * @param bool $prettyPrint Formater le JSON (défaut: true)
     * @return bool True si succès
     */
    public function saveJson(string $filepath, bool $prettyPrint = true): bool
    {
        return file_put_contents($filepath, $this->toJson($prettyPrint)) !== false;
    }
    
    /**
     * Importe une facture depuis un fichier ou contenu XML UBL
     * 
     * @param string $xmlContent Contenu XML ou chemin vers un fichier XML
     * @param bool $strict Validation stricte par défaut
     * @return self
     * @throws \InvalidArgumentException Si le XML est invalide
     */
    public static function fromXml(string $xmlContent, bool $strict = true): self
    {
         return XmlImporter::fromUbl($xmlContent, self::class, $strict);
    }
    
    /**
     * Crée une facture depuis un tableau de données
     * 
     * @param array $data Données de la facture
     * @return self
     * @throws \InvalidArgumentException Si les données sont invalides
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['invoiceNumber'], $data['issueDate'])) {
            throw new \InvalidArgumentException('invoiceNumber et issueDate sont obligatoires');
        }
        
        $invoice = new self(
            $data['invoiceNumber'],
            $data['issueDate'],
            $data['invoiceTypeCode'] ?? '380',
            $data['documentCurrencyCode'] ?? 'EUR'
        );
        
        // À compléter selon les besoins...
        
        return $invoice;
    }
    
    /**
     * Vérifie si la facture est valide et retourne les erreurs
     * 
     * @return array Liste des erreurs (vide si valide)
     */
    public function getValidationErrors(): array
    {
        return $this->validate();
    }
    
    /**
     * Vérifie si la facture est valide
     * 
     * @return bool True si valide
     */
    public function isValid(): bool
    {
        return empty($this->validate());
    }
    
    /**
     * Retourne un résumé textuel de la facture
     * 
     * @return string
     */
    public function getSummary(): string
    {
        $seller = $this->getSeller();
        $buyer = $this->getBuyer();
        
        $summary = sprintf(
            "Facture N°%s du %s\n",
            $this->getInvoiceNumber(),
            $this->getIssueDate()
        );
        
        $summary .= sprintf(
            "De: %s (%s)\n",
            $seller->getName(),
            $seller->getVatId()
        );
        
        $summary .= sprintf(
            "À: %s\n",
            $buyer->getName()
        );
        
        $summary .= sprintf(
            "Montant HT: %.2f %s\n",
            $this->getTaxExclusiveAmount(),
            $this->getDocumentCurrencyCode()
        );
        
        $summary .= sprintf(
            "Montant TTC: %.2f %s\n",
            $this->getTaxInclusiveAmount(),
            $this->getDocumentCurrencyCode()
        );
        
        $summary .= sprintf(
            "Lignes: %d\n",
            count($this->getInvoiceLines())
        );
        
        if ($this->getDueDate()) {
            $summary .= sprintf(
                "Échéance: %s\n",
                $this->getDueDate()
            );
        }
        
        return $summary;
    }
    
    /**
     * Affiche un résumé de la facture
     * 
     * @return void
     */
    public function display(): void
    {
        echo $this->getSummary();
    }
    
        public function getBuyerReference(): ?string { return $this->buyerReference; }
    
}
