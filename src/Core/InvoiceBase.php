<?php

declare(strict_types=1);

namespace Peppol\Core;

use Peppol\Models\Party;
use Peppol\Models\InvoiceLine;
use Peppol\Models\VatBreakdown;
use Peppol\Models\PaymentInfo;
use Peppol\Models\AttachedDocument;

/**
 * Classe de base abstraite pour les factures électroniques
 * 
 * Fournit la structure commune à toutes les implémentations de factures
 * (EN 16931, UBL.BE, Peppol, etc.)
 * 
 * @package Peppol\Core
 * @author Votre Nom
 * @version 1.0
 */
abstract class InvoiceBase
{
    use InvoiceValidatorTrait;
    
    // === Informations de base de la facture ===
    /**
     * @var string Numéro unique de facture (BT-1)
     */
    protected string $invoiceNumber;
    
    /**
     * @var string Date d'émission au format YYYY-MM-DD (BT-2)
     */
    protected string $issueDate;
    
    /**
     * @var string Code de type de facture (BT-3)
     */
    protected string $invoiceTypeCode;
    
    /**
     * @var string Code devise ISO 4217 (BT-5)
     */
    protected string $documentCurrencyCode;
    
    /**
     * @var string|null Date d'échéance (BT-9)
     */
    protected ?string $dueDate = null;
    
    /**
     * @var string|null Date de livraison (BT-72)
     */
    protected ?string $deliveryDate = null;
    
    /**
     * @var string|null Référence du bon de commande (BT-13)
     */
    protected ?string $purchaseOrderReference = null;
    
    /**
     * @var string|null Référence du contrat (BT-12)
     */
    protected ?string $contractReference = null;
    
    // === Parties ===
    
    /**
     * @var Party Fournisseur/Vendeur (BG-4)
     */
    protected Party $seller;
    
    /**
     * @var Party Client/Acheteur (BG-7)
     */
    protected Party $buyer;
    
    // === Lignes de facture ===
    
    /**
     * @var array<InvoiceLine> Lignes de facture (BG-25)
     */
    protected array $invoiceLines = [];
    
    // === Informations de paiement ===
    
    /**
     * @var PaymentInfo|null Informations de paiement (BG-16, BG-17)
     */
    protected ?PaymentInfo $paymentInfo = null;

    /**
     * @var string|null Conditions de paiement (BT-20)
     */
    protected ?string $paymentTerms = null;
    

    
    // === Documents joints ===
    
    /**
     * @var array<AttachedDocument> Documents joints (BG-24)
     */
    protected array $attachedDocuments = [];
    
    // === Totaux ===
    
    /**
     * @var float Montant net total HT (BT-106)
     */
    protected float $taxExclusiveAmount = 0.0;
    
    /**
     * @var float Montant total TTC (BT-112)
     */
    protected float $taxInclusiveAmount = 0.0;
    
    /**
     * @var float Montant à payer (BT-115)
     */
    protected float $payableAmount = 0.0;
    
    /**
     * @var array<string, VatBreakdown> Ventilation par taux de TVA (BG-23)
     */
    protected array $vatBreakdown = [];
    
    /**
     * Constructeur
     * 
     * @param string $invoiceNumber Numéro unique de facture
     * @param string $issueDate Date d'émission (YYYY-MM-DD)
     * @param string $invoiceTypeCode Code type facture (380, 381, etc.)
     * @param string $currencyCode Code devise ISO 4217
     * @throws \InvalidArgumentException
     */
    public function __construct(
        string $invoiceNumber,
        string $issueDate,
        string $invoiceTypeCode = '380',
        string $currencyCode = 'EUR'
    ) {
        $this->setInvoiceNumber($invoiceNumber);
        $this->setIssueDate($issueDate);
        $this->setInvoiceTypeCode($invoiceTypeCode);
        $this->setDocumentCurrencyCode($currencyCode);
    }
    
    // === Setters de base ===
    
    protected function setInvoiceNumber(string $invoiceNumber): void
    {
        if (!$this->validateNotEmpty($invoiceNumber)) {
            throw new \InvalidArgumentException('Le numéro de facture ne peut pas être vide');
        }
        
        if (!preg_match('/^[A-Za-z0-9\/_-]+$/', $invoiceNumber)) {
            throw new \InvalidArgumentException('Le numéro de facture contient des caractères invalides');
        }
        
        $this->invoiceNumber = $invoiceNumber;
    }
    
    protected function setIssueDate(string $issueDate): void
    {
        if (!$this->validateDate($issueDate)) {
            throw new \InvalidArgumentException('Format de date invalide (YYYY-MM-DD)');
        }
        
        $this->issueDate = $issueDate;
    }
    
    protected function setInvoiceTypeCode(string $invoiceTypeCode): void
    {
        if (!$this->validateKeyExists($invoiceTypeCode, InvoiceConstants::INVOICE_TYPE_CODES)) {
            throw new \InvalidArgumentException(
                'Code de type de facture invalide. Codes valides: ' . 
                implode(', ', array_keys(InvoiceConstants::INVOICE_TYPE_CODES))
            );
        }
        
        $this->invoiceTypeCode = $invoiceTypeCode;
    }
    
    protected function setDocumentCurrencyCode(string $currencyCode): void
    {
        if (!$this->validateInList($currencyCode, InvoiceConstants::CURRENCY_CODES)) {
            throw new \InvalidArgumentException(
                'Code devise invalide. Codes valides: ' . implode(', ', InvoiceConstants::CURRENCY_CODES)
            );
        }
        
        $this->documentCurrencyCode = $currencyCode;
    }
    
    /**
     * Définit la date d'échéance
     * BR-CO-10: La date d'échéance doit être >= date d'émission
     * 
     * @param string $dueDate Format YYYY-MM-DD
     * @return self
     * @throws \InvalidArgumentException
     */
    public function setDueDate(string $dueDate): self
    {
        if (!$this->validateDate($dueDate)) {
            throw new \InvalidArgumentException('Format de date d\'échéance invalide (YYYY-MM-DD)');
        }
        
        if ($dueDate < $this->issueDate) {
            throw new \InvalidArgumentException(
                'La date d\'échéance ne peut pas être antérieure à la date d\'émission'
            );
        }
        
        $this->dueDate = $dueDate;
        return $this;
    }
    
    /**
     * Définit la date de livraison
     * 
     * @param string $deliveryDate Format YYYY-MM-DD
     * @return self
     * @throws \InvalidArgumentException
     */
    public function setDeliveryDate(string $deliveryDate): self
    {
        if (!$this->validateDate($deliveryDate)) {
            throw new \InvalidArgumentException('Format de date de livraison invalide');
        }
        
        $this->deliveryDate = $deliveryDate;
        return $this;
    }
    
    /**
     * Définit la référence de commande
     * 
     * @param string $purchaseOrderReference
     * @return self
     */
    public function setPurchaseOrderReference(string $purchaseOrderReference): self
    {
        $this->purchaseOrderReference = $purchaseOrderReference;
        return $this;
    }
    
    /**
     * Définit la référence de contrat
     * 
     * @param string $contractReference
     * @return self
     */
    public function setContractReference(string $contractReference): self
    {
        $this->contractReference = $contractReference;
        return $this;
    }
    
    /**
     * Définit le fournisseur
     * 
     * @param Party $seller
     * @return self
     */
    public function setSeller(Party $seller): self
    {
        $this->seller = $seller;
        return $this;
    }
    
    /**
     * Définit le client
     * 
     * @param Party $buyer
     * @return self
     */
    public function setBuyer(Party $buyer): self
    {
        $this->buyer = $buyer;
        return $this;
    }
    
    /**
     * Ajoute une ligne de facture
     * 
     * @param InvoiceLine $line
     * @return self
     */
    public function addInvoiceLine(InvoiceLine $line): self
    {
        $this->invoiceLines[] = $line;
        return $this;
    }
    
    /**
     * Définit les informations de paiement
     * 
     * @param PaymentInfo $paymentInfo
     * @return self
     */
    public function setPaymentInfo(PaymentInfo $paymentInfo): self
    {
        $this->paymentInfo = $paymentInfo;
        return $this;
    }
    
    /**
     * Définit les conditions de paiement
     * BT-20 - Obligatoire si date d'échéance non fournie et montant > 0
     * 
     * @param string $paymentTerms
     * @return self
     */
    public function setPaymentTerms(string $paymentTerms): self
    {
        $this->paymentTerms = $paymentTerms;
        
        // Met à jour aussi dans PaymentInfo si elle existe
        if ($this->paymentInfo !== null) {
            $this->paymentInfo->setPaymentTerms($paymentTerms);
        }
        
        return $this;
    }    
    
    /**
     * Joint un document
     * 
     * @param AttachedDocument $document
     * @return self
     */
    public function attachDocument(AttachedDocument $document): self
    {
        $this->attachedDocuments[] = $document;
        return $this;
    }
    
    /**
     * Calcule les totaux de la facture
     * BR-CO-13: Les montants doivent être cohérents
     * 
     * @return self
     * @throws \InvalidArgumentException
     */
    public function calculateTotals(): self
    {
        if (empty($this->invoiceLines)) {
            throw new \InvalidArgumentException('Impossible de calculer les totaux sans lignes de facture');
        }
        
        // Réinitialisation
        $this->taxExclusiveAmount = 0.0;
        $this->vatBreakdown = [];
        
        // Calcul par ligne et regroupement par taux de TVA
        foreach ($this->invoiceLines as $line) {
            $this->taxExclusiveAmount += $line->getLineAmount();
            
            $vatKey = $line->getVatCategory() . '_' . $line->getVatRate();
            
            if (!isset($this->vatBreakdown[$vatKey])) {
                $this->vatBreakdown[$vatKey] = new VatBreakdown(
                    $line->getVatCategory(),
                    $line->getVatRate(),
                    0.0,
                    0.0
                );
            }
            
            $this->vatBreakdown[$vatKey]->addAmount(
                $line->getLineAmount(),
                $line->getLineVatAmount()
            );
        }
        
        // Calcul du total TTC
        $totalVat = 0.0;
        foreach ($this->vatBreakdown as $vat) {
            $totalVat += $vat->getTaxAmount();
        }
        
        $this->taxExclusiveAmount = round($this->taxExclusiveAmount, 2);
        $this->taxInclusiveAmount = round($this->taxExclusiveAmount + $totalVat, 2);
        $this->payableAmount = $this->taxInclusiveAmount;
        
        return $this;
    }
    
    /**
     * Valide la facture selon les règles de base
     * Les sous-classes peuvent surcharger cette méthode pour ajouter des validations spécifiques
     * 
     * @return array<string> Liste des erreurs (vide si valide)
     */
    public function validate(): array
    {
        $errors = [];
        
        // BR-01: Une facture doit avoir un numéro
        if (!$this->validateNotEmpty($this->invoiceNumber)) {
            $errors[] = 'BR-01: Numéro de facture obligatoire';
        }
        
        // BR-02: Date d'émission obligatoire
        if (!$this->validateDate($this->issueDate)) {
            $errors[] = 'BR-02: Date d\'émission invalide';
        }
        
        // BR-03: Code type obligatoire
        if (!$this->validateKeyExists($this->invoiceTypeCode, InvoiceConstants::INVOICE_TYPE_CODES)) {
            $errors[] = 'BR-03: Code type de facture invalide';
        }
        
        // BR-04: Devise obligatoire
        if (!$this->validateInList($this->documentCurrencyCode, InvoiceConstants::CURRENCY_CODES)) {
            $errors[] = 'BR-04: Code devise invalide';
        }
        
        // BR-06: Vendeur obligatoire
        if (!isset($this->seller)) {
            $errors[] = 'BR-06: Fournisseur obligatoire';
        } else {
            $sellerErrors = $this->seller->validate();
            if (!empty($sellerErrors)) {
                $errors = array_merge($errors, array_map(fn($e) => "Fournisseur: $e", $sellerErrors));
            }
        }
        
        // BR-08: Acheteur obligatoire
        if (!isset($this->buyer)) {
            $errors[] = 'BR-08: Client obligatoire';
        } else {
            $buyerErrors = $this->buyer->validate();
            if (!empty($buyerErrors)) {
                $errors = array_merge($errors, array_map(fn($e) => "Client: $e", $buyerErrors));
            }
        }
        
        // BR-16: Au moins une ligne
        if (empty($this->invoiceLines)) {
            $errors[] = 'BR-16: Au moins une ligne de facture requise';
        }
        
        // Validation des lignes
        foreach ($this->invoiceLines as $index => $line) {
            $lineErrors = $line->validate();
            if (!empty($lineErrors)) {
                $errors = array_merge($errors, array_map(fn($e) => "Ligne " . ($index + 1) . ": $e", $lineErrors));
            }
        }
        
        // BR-CO-13: Les totaux doivent être calculés
        if ($this->taxExclusiveAmount === 0.0 && !empty($this->invoiceLines)) {
            $errors[] = 'BR-CO-13: Les totaux de la facture n\'ont pas été calculés';
        }
        
        // Validation des informations de paiement
        if ($this->paymentInfo !== null) {
            $paymentErrors = $this->paymentInfo->validate();
            if (!empty($paymentErrors)) {
                $errors = array_merge($errors, array_map(fn($e) => "Paiement: $e", $paymentErrors));
            }
        }
        
        // Validation des documents joints
        foreach ($this->attachedDocuments as $index => $doc) {
            $docErrors = $doc->validate();
            if (!empty($docErrors)) {
                $errors = array_merge($errors, array_map(fn($e) => "Document " . ($index + 1) . ": $e", $docErrors));
            }
        }
        
        // Validation des ventilations TVA
        foreach ($this->vatBreakdown as $vat) {
            $vatErrors = $vat->validate();
            if (!empty($vatErrors)) {
                $errors = array_merge($errors, array_map(fn($e) => "TVA: $e", $vatErrors));
            }
        }
        
        return $errors;
    }
    
    /**
     * Retourne la facture sous forme de tableau
     * 
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'invoiceNumber' => $this->invoiceNumber,
            'issueDate' => $this->issueDate,
            'dueDate' => $this->dueDate,
            'deliveryDate' => $this->deliveryDate,
            'invoiceTypeCode' => $this->invoiceTypeCode,
            'documentCurrencyCode' => $this->documentCurrencyCode,
            'purchaseOrderReference' => $this->purchaseOrderReference,
            'contractReference' => $this->contractReference,
            'seller' => $this->seller->toArray(),
            'buyer' => $this->buyer->toArray(),
            'lines' => array_map(fn($line) => $line->toArray(), $this->invoiceLines),
            'totals' => [
                'taxExclusiveAmount' => $this->taxExclusiveAmount,
                'taxInclusiveAmount' => $this->taxInclusiveAmount,
                'payableAmount' => $this->payableAmount
            ],
            'vatBreakdown' => array_map(fn($vat) => $vat->toArray(), array_values($this->vatBreakdown)),
            'payment' => $this->paymentInfo?->toArray(),
            'attachedDocuments' => array_map(fn($doc) => $doc->toArray(), $this->attachedDocuments)
        ];
    }
    
    // === Getters ===
    
    public function getInvoiceNumber(): string { return $this->invoiceNumber; }
    public function getIssueDate(): string { return $this->issueDate; }
    public function getDueDate(): ?string { return $this->dueDate; }
    public function getDeliveryDate(): ?string { return $this->deliveryDate; }
    public function getInvoiceTypeCode(): string { return $this->invoiceTypeCode; }
    public function getDocumentCurrencyCode(): string { return $this->documentCurrencyCode; }
    public function getPurchaseOrderReference(): ?string { return $this->purchaseOrderReference; }
    public function getContractReference(): ?string { return $this->contractReference; }
    public function getSeller(): Party { return $this->seller; }
    public function getBuyer(): Party { return $this->buyer; }
    public function getInvoiceLines(): array { return $this->invoiceLines; }
    public function getPaymentInfo(): ?PaymentInfo { return $this->paymentInfo; }
    public function getAttachedDocuments(): array { return $this->attachedDocuments; }
    public function getTaxExclusiveAmount(): float { return $this->taxExclusiveAmount; }
    public function getTaxInclusiveAmount(): float { return $this->taxInclusiveAmount; }
    public function getPayableAmount(): float { return $this->payableAmount; }
    public function getVatBreakdown(): array { return $this->vatBreakdown; }
    public function getPaymentTerms(): ?string { return $this->paymentTerms; }
}
