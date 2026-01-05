<?php

declare(strict_types=1);

namespace Peppol\Formats;

use Peppol\Core\InvoiceBase;
use Peppol\Core\InvoiceConstants;
use Peppol\Standards\UblBeInvoice;

/**
 * Exportateur XML UBL 2.1
 * 
 * Génère un document XML UBL 2.1 conforme à la norme EN 16931
 * et aux spécifications Peppol BIS ou UBL.BE
 * 
 * @package Peppol\Formats
 * @author Votre Nom
 * @version 1.0
 */
class XmlExporter
{
    /**
     * @var InvoiceBase Facture à exporter
     */
    private InvoiceBase $invoice;
    
    /**
     * @var string Identifiant de customisation
     */
    private string $customizationId;
    
    /**
     * @var string Identifiant de profil
     */
    private string $profileId;
    
    /**
     * Constructeur
     * 
     * @param InvoiceBase $invoice Facture à exporter
     * @param string|null $customizationId Identifiant de customisation (auto-détecté si null)
     * @param string|null $profileId Identifiant de profil
     */
    public function __construct(
        InvoiceBase $invoice,
        ?string $customizationId = null,
        ?string $profileId = null
    ) {
        $this->invoice = $invoice;
        $this->customizationId = $customizationId ?? $this->detectCustomizationId();
        $this->profileId = $profileId ?? InvoiceConstants::PROFILE_PEPPOL;
    }
    
    /**
     * Détecte automatiquement l'identifiant de customisation selon le type de facture
     * 
     * @return string
     */
    private function detectCustomizationId(): string
    {
        if ($this->invoice instanceof UblBeInvoice) {
            return InvoiceConstants::CUSTOMIZATION_UBL_BE;
        }
        
        return InvoiceConstants::CUSTOMIZATION_PEPPOL;
    }
    
    /**
     * Exporte la facture au format XML UBL 2.1
     * 
     * @return string XML UBL
     * @throws \InvalidArgumentException Si la facture n'est pas valide
     */
    public function toUbl21(): string
    {
        // Validation préalable
        $errors = $this->invoice->validate();
        if (!empty($errors)) {
            throw new \InvalidArgumentException(
                'Impossible d\'exporter une facture invalide. Erreurs: ' . implode(', ', $errors)
            );
        }
        
        $xml = new \DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;
        
        // Élément racine Invoice avec namespaces
        $invoice = $xml->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:Invoice-2', 'Invoice');
        $invoice->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $invoice->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $xml->appendChild($invoice);
        
        // En-tête du document
        $this->addDocumentHeader($xml, $invoice);
        
        // Références
        $this->addReferences($xml, $invoice);
        
        // Documents joints
        $this->addAttachedDocuments($xml, $invoice);
        
        // Parties (Vendeur et Acheteur)
        $this->addSellerParty($xml, $invoice);
        $this->addBuyerParty($xml, $invoice);
        
        // Informations de paiement
        $this->addPaymentMeans($xml, $invoice);
        
        // Totaux TVA
        $this->addTaxTotals($xml, $invoice);
        
        // Totaux monétaires
        $this->addMonetaryTotals($xml, $invoice);
        
        // Lignes de facture
        $this->addInvoiceLines($xml, $invoice);
        
        return $xml->saveXML();
    }
    
    /**
     * Ajoute l'en-tête du document
     */
    private function addDocumentHeader(\DOMDocument $xml, \DOMElement $invoice): void
    {
        $this->addElement($xml, $invoice, 'cbc:UBLVersionID', InvoiceConstants::UBL_VERSION);
        $this->addElement($xml, $invoice, 'cbc:CustomizationID', $this->customizationId);
        $this->addElement($xml, $invoice, 'cbc:ProfileID', $this->profileId);
        $this->addElement($xml, $invoice, 'cbc:ID', $this->invoice->getInvoiceNumber());
        $this->addElement($xml, $invoice, 'cbc:IssueDate', $this->invoice->getIssueDate());
        
        if ($this->invoice->getDueDate()) {
            $this->addElement($xml, $invoice, 'cbc:DueDate', $this->invoice->getDueDate());
        }
        
        $this->addElement($xml, $invoice, 'cbc:InvoiceTypeCode', $this->invoice->getInvoiceTypeCode());
        
        // BT-20: Conditions de paiement
        if ($this->invoice->getPaymentTerms()) {
            $this->addElement($xml, $invoice, 'cbc:Note', $this->invoice->getPaymentTerms());
        }
        
        $this->addElement($xml, $invoice, 'cbc:DocumentCurrencyCode', $this->invoice->getDocumentCurrencyCode());
        
        if ($this->invoice->getBuyerReference()) {
            $this->addElement($xml, $invoice, 'cbc:BuyerReference', $this->invoice->getBuyerReference());
        }
    }
    
    /**
     * Ajoute les références
     */
    private function addReferences(\DOMDocument $xml, \DOMElement $invoice): void
    {
        if ($this->invoice->getPurchaseOrderReference()) {
            $orderRef = $xml->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2', 'cac:OrderReference');
            $this->addElement($xml, $orderRef, 'cbc:ID', $this->invoice->getPurchaseOrderReference());
            $invoice->appendChild($orderRef);
        }
        
        if ($this->invoice->getContractReference()) {
            $contractRef = $xml->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2', 'cac:ContractDocumentReference');
            $this->addElement($xml, $contractRef, 'cbc:ID', $this->invoice->getContractReference());
            $invoice->appendChild($contractRef);
        }
    }
    
    /**
     * Ajoute les documents joints
     */
    private function addAttachedDocuments(\DOMDocument $xml, \DOMElement $invoice): void
    {
        $documents = $this->invoice->getAttachedDocuments();
        $docsCount = count($documents);
        
        
        foreach ($documents as $doc) {
            $additionalDoc = $xml->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2', 'cac:AdditionalDocumentReference');
            
            $this->addElement($xml, $additionalDoc, 'cbc:ID', 'Attachment');
                        
            if ($doc->getDescription()) {
                $this->addElement($xml, $additionalDoc, 'cbc:DocumentDescription', $doc->getDescription());
            }
            
            $attachment = $xml->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2', 'cac:Attachment');
            $embeddedDoc = $xml->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2', 'cbc:EmbeddedDocumentBinaryObject', $doc->getContent());
            $embeddedDoc->setAttribute('mimeCode', $doc->getMimeType());
            $embeddedDoc->setAttribute('filename', $doc->getFilename());
            $attachment->appendChild($embeddedDoc);
            $additionalDoc->appendChild($attachment);
            $invoice->appendChild($additionalDoc);
        }
    }
    
    /**
     * Ajoute un document de référence fictif (pour UBL.BE)
     */
    private function addPlaceholderDocument(\DOMDocument $xml, \DOMElement $invoice, string $id, string $type, string $description): void
    {
        $additionalDoc = $xml->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2', 'cac:AdditionalDocumentReference');
        $this->addElement($xml, $additionalDoc, 'cbc:ID', $id);
        $this->addElement($xml, $additionalDoc, 'cbc:DocumentTypeCode', $type);
        $this->addElement($xml, $additionalDoc, 'cbc:DocumentDescription', $description);
        $invoice->appendChild($additionalDoc);
    }
    
    /**
     * Ajoute la partie vendeur
     */
    private function addSellerParty(\DOMDocument $xml, \DOMElement $invoice): void
    {
        $seller = $this->invoice->getSeller();
        
        $supplierParty = $xml->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2', 'cac:AccountingSupplierParty');
        $party = $xml->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2', 'cac:Party');
        
        // Adresse électronique
        if ($seller->getElectronicAddress()) {
            $endpoint = $xml->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2', 'cbc:EndpointID', $seller->getElectronicAddress()->getIdentifier());
            $endpoint->setAttribute('schemeID', $seller->getElectronicAddress()->getSchemeId());
            $party->appendChild($endpoint);
        }
        
        // Identifiant entreprise
        if ($seller->getCompanyId()) {
            $partyId = $xml->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2', 'cac:PartyIdentification');
            $this->addElement($xml, $partyId, 'cbc:ID', $seller->getCompanyId());
            $party->appendChild($partyId);
        }
        
        // Nom
        $partyName = $xml->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2', 'cac:PartyName');
        $this->addElement($xml, $partyName, 'cbc:Name', $seller->getName());
        $party->appendChild($partyName);
        
        // Adresse postale
        $this->addPostalAddress($xml, $party, $seller->getAddress());
        
        // TVA
        $partyTaxScheme = $xml->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2', 'cac:PartyTaxScheme');
        $this->addElement($xml, $partyTaxScheme, 'cbc:CompanyID', $seller->getVatId());
        $taxScheme = $xml->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2', 'cac:TaxScheme');
        $this->addElement($xml, $taxScheme, 'cbc:ID', 'VAT');
        $partyTaxScheme->appendChild($taxScheme);
        $party->appendChild($partyTaxScheme);
        
        // Entité légale
        $partyLegalEntity = $xml->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2', 'cac:PartyLegalEntity');
        $this->addElement($xml, $partyLegalEntity, 'cbc:RegistrationName', $seller->getName());
        if ($seller->getCompanyId()) {
            $this->addElement($xml, $partyLegalEntity, 'cbc:CompanyID', $seller->getCompanyId());
        }
        $party->appendChild($partyLegalEntity);
        
        // Contact
        if ($seller->getEmail()) {
            $contact = $xml->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2', 'cac:Contact');
            $this->addElement($xml, $contact, 'cbc:ElectronicMail', $seller->getEmail());
            $party->appendChild($contact);
        }
        
        $supplierParty->appendChild($party);
        $invoice->appendChild($supplierParty);
    }
    
    /**
     * Ajoute la partie acheteur
     */
    private function addBuyerParty(\DOMDocument $xml, \DOMElement $invoice): void
    {
        $buyer = $this->invoice->getBuyer();
        
        $customerParty = $xml->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2', 'cac:AccountingCustomerParty');
        $party = $xml->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2', 'cac:Party');
        
        // Adresse électronique
        if ($buyer->getElectronicAddress()) {
            $endpoint = $xml->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2', 'cbc:EndpointID', $buyer->getElectronicAddress()->getIdentifier());
            $endpoint->setAttribute('schemeID', $buyer->getElectronicAddress()->getSchemeId());
            $party->appendChild($endpoint);
        }
        
        // Nom
        $partyName = $xml->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2', 'cac:PartyName');
        $this->addElement($xml, $partyName, 'cbc:Name', $buyer->getName());
        $party->appendChild($partyName);
        
        // Adresse postale
        $this->addPostalAddress($xml, $party, $buyer->getAddress());
        
        // TVA si présent
        if ($buyer->getVatId()) {
            $partyTaxScheme = $xml->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2', 'cac:PartyTaxScheme');
            $this->addElement($xml, $partyTaxScheme, 'cbc:CompanyID', $buyer->getVatId());
            $taxScheme = $xml->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2', 'cac:TaxScheme');
            $this->addElement($xml, $taxScheme, 'cbc:ID', 'VAT');
            $partyTaxScheme->appendChild($taxScheme);
            $party->appendChild($partyTaxScheme);
        }
        
        // Entité légale
        $partyLegalEntity = $xml->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2', 'cac:PartyLegalEntity');
        $this->addElement($xml, $partyLegalEntity, 'cbc:RegistrationName', $buyer->getName());
        $party->appendChild($partyLegalEntity);
        
        $customerParty->appendChild($party);
        $invoice->appendChild($customerParty);
    }
    
    /**
     * Ajoute une adresse postale
     */
    private function addPostalAddress(\DOMDocument $xml, \DOMElement $parent, $address): void
    {
        $postalAddress = $xml->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2', 'cac:PostalAddress');
        $this->addElement($xml, $postalAddress, 'cbc:StreetName', $address->getStreetName());
        
        if ($address->getAdditionalStreetName()) {
            $this->addElement($xml, $postalAddress, 'cbc:AdditionalStreetName', $address->getAdditionalStreetName());
        }
        
        $this->addElement($xml, $postalAddress, 'cbc:CityName', $address->getCityName());
        $this->addElement($xml, $postalAddress, 'cbc:PostalZone', $address->getPostalZone());
        
        $country = $xml->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2', 'cac:Country');
        $this->addElement($xml, $country, 'cbc:IdentificationCode', $address->getCountryCode());
        $postalAddress->appendChild($country);
        
        $parent->appendChild($postalAddress);
    }
    
    /**
     * Ajoute les moyens de paiement
     */
    private function addPaymentMeans(\DOMDocument $xml, \DOMElement $invoice): void
    {
        $paymentInfo = $this->invoice->getPaymentInfo();
        
        if ($paymentInfo && $paymentInfo->getIban()) {
            $paymentMeans = $xml->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2', 'cac:PaymentMeans');
            $this->addElement($xml, $paymentMeans, 'cbc:PaymentMeansCode', $paymentInfo->getPaymentMeansCode());
            
            if ($paymentInfo->getPaymentReference()) {
                $this->addElement($xml, $paymentMeans, 'cbc:PaymentID', $paymentInfo->getPaymentReference());
            }
            
            $payeeFinancialAccount = $xml->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2', 'cac:PayeeFinancialAccount');
            $this->addElement($xml, $payeeFinancialAccount, 'cbc:ID', $paymentInfo->getIban());
            
            if ($paymentInfo->getBic()) {
                $financialInstitution = $xml->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2', 'cac:FinancialInstitutionBranch');
                $this->addElement($xml, $financialInstitution, 'cbc:ID', $paymentInfo->getBic());
                $payeeFinancialAccount->appendChild($financialInstitution);
            }
            
            $paymentMeans->appendChild($payeeFinancialAccount);
            $invoice->appendChild($paymentMeans);
        }
    }
    
    /**
     * Ajoute les totaux TVA
     */
    private function addTaxTotals(\DOMDocument $xml, \DOMElement $invoice): void
    {
        $vatBreakdown = $this->invoice->getVatBreakdown();
        
        foreach ($vatBreakdown as $vat) {
            $taxTotal = $xml->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2', 'cac:TaxTotal');
            
            $taxAmount = $xml->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2', 'cbc:TaxAmount', number_format($vat->getTaxAmount(), 2, '.', ''));
            $taxAmount->setAttribute('currencyID', $this->invoice->getDocumentCurrencyCode());
            $taxTotal->appendChild($taxAmount);
            
            $taxSubtotal = $xml->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2', 'cac:TaxSubtotal');
            
            $taxableAmount = $xml->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2', 'cbc:TaxableAmount', number_format($vat->getTaxableAmount(), 2, '.', ''));
            $taxableAmount->setAttribute('currencyID', $this->invoice->getDocumentCurrencyCode());
            $taxSubtotal->appendChild($taxableAmount);
            
            $taxAmountSub = $xml->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2', 'cbc:TaxAmount', number_format($vat->getTaxAmount(), 2, '.', ''));
            $taxAmountSub->setAttribute('currencyID', $this->invoice->getDocumentCurrencyCode());
            $taxSubtotal->appendChild($taxAmountSub);
            
            $taxCategory = $xml->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2', 'cac:TaxCategory');
            $this->addElement($xml, $taxCategory, 'cbc:ID', $vat->getCategory());
            
            
            $this->addElement($xml, $taxCategory, 'cbc:Percent', number_format($vat->getRate(), 2, '.', ''));
            
            if ($vat->getExemptionReason()) {
                $this->addElement($xml, $taxCategory, 'cbc:TaxExemptionReasonCode', $vat->getExemptionReason());
                $this->addElement($xml, $taxCategory, 'cbc:TaxExemptionReason', InvoiceConstants::VAT_EXEMPTION_REASONS[$vat->getExemptionReason()] ?? '');
            }
            
            $taxSchemeVat = $xml->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2', 'cac:TaxScheme');
            $this->addElement($xml, $taxSchemeVat, 'cbc:ID', 'VAT');
            $taxCategory->appendChild($taxSchemeVat);
            
            $taxSubtotal->appendChild($taxCategory);
            $taxTotal->appendChild($taxSubtotal);
            $invoice->appendChild($taxTotal);
        }
    }
    
    /**
     * Ajoute les totaux monétaires
     */
    private function addMonetaryTotals(\DOMDocument $xml, \DOMElement $invoice): void
    {
        $legalMonetaryTotal = $xml->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2', 'cac:LegalMonetaryTotal');
        
        $this->addAmountElement($xml, $legalMonetaryTotal, 'cbc:LineExtensionAmount', $this->invoice->getTaxExclusiveAmount());
        $this->addAmountElement($xml, $legalMonetaryTotal, 'cbc:TaxExclusiveAmount', $this->invoice->getTaxExclusiveAmount());
        $this->addAmountElement($xml, $legalMonetaryTotal, 'cbc:TaxInclusiveAmount', $this->invoice->getTaxInclusiveAmount());
        $this->addAmountElement($xml, $legalMonetaryTotal, 'cbc:PayableAmount', $this->invoice->getPayableAmount());
        
        $invoice->appendChild($legalMonetaryTotal);
    }
    
    /**
     * Ajoute les lignes de facture
     */
    private function addInvoiceLines(\DOMDocument $xml, \DOMElement $invoice): void
    {
        foreach ($this->invoice->getInvoiceLines() as $line) {
            $invoiceLine = $xml->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2', 'cac:InvoiceLine');
            
            $this->addElement($xml, $invoiceLine, 'cbc:ID', $line->getId());
            
            // cbc:Note ?
            
            $quantity = $xml->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2', 'cbc:InvoicedQuantity', (string)$line->getQuantity());
            $quantity->setAttribute('unitCode', $line->getUnitCode());
            $invoiceLine->appendChild($quantity);
            
            $this->addAmountElement($xml, $invoiceLine, 'cbc:LineExtensionAmount', $line->getLineAmount());
                      
            // Item
            $item = $xml->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2', 'cac:Item');
            // Description toujours en premier
            if ($line->getDescription()) {
                $this->addElement($xml, $item, 'cbc:Description', $line->getDescription());
            }
            $this->addElement($xml, $item, 'cbc:Name', $line->getName());

            $classifiedTaxCategory = $xml->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2', 'cac:ClassifiedTaxCategory');
            $this->addElement($xml, $classifiedTaxCategory, 'cbc:ID', $line->getVatCategory());
            
            
            $this->addElement($xml, $classifiedTaxCategory, 'cbc:Percent', number_format($line->getVatRate(), 2, '.', ''));
            
            $lineTaxScheme = $xml->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2', 'cac:TaxScheme');
            $this->addElement($xml, $lineTaxScheme, 'cbc:ID', 'VAT');
            $classifiedTaxCategory->appendChild($lineTaxScheme);
            $item->appendChild($classifiedTaxCategory);
            $invoiceLine->appendChild($item);
            
            // Price
            $price = $xml->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2', 'cac:Price');
            $this->addAmountElement($xml, $price, 'cbc:PriceAmount', $line->getUnitPrice());
            $invoiceLine->appendChild($price);     
            $invoice->appendChild($invoiceLine);
        }
    }
    
    /**
     * Ajoute un élément avec texte
     */
    private function addElement(\DOMDocument $xml, \DOMElement $parent, string $name, string $value): void
    {
        $namespace = strpos($name, 'cbc:') === 0 
            ? 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2'
            : 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';
        
        $element = $xml->createElementNS($namespace, $name, htmlspecialchars($value, ENT_XML1, 'UTF-8'));
        $parent->appendChild($element);
    }
    
    /**
     * Ajoute un élément montant avec devise
     */
    private function addAmountElement(\DOMDocument $xml, \DOMElement $parent, string $name, float $amount): void
    {
        $element = $xml->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2', $name, number_format($amount, 2, '.', ''));
        $element->setAttribute('currencyID', $this->invoice->getDocumentCurrencyCode());
        $parent->appendChild($element);
    }
    
    /**
     * Sauvegarde le XML dans un fichier
     * 
     * @param string $filepath Chemin du fichier
     * @return bool True si succès
     */
    public function saveToFile(string $filepath): bool
    {
        try {
            $xml = $this->toUbl21();
            return file_put_contents($filepath, $xml) !== false;
        } catch (\Exception $e) {
            return false;
        }
    }


    /**
     * @var bool Active/désactive la validation Schematron
     */
    private bool $enableSchematronValidation = false;
    
    /**
     * @var array<string> Niveaux de validation Schematron à appliquer
     */
    private array $schematronLevels = ['ublbe', 'en16931'];
    
    /**
     * Active la validation Schematron lors de l'export
     * 
     * @param bool $enable
     * @param array<string> $levels Niveaux de validation ['ublbe', 'en16931', 'peppol']
     * @return self
     */
    public function enableSchematronValidation(bool $enable = true, array $levels = ['ublbe', 'en16931']): self
    {
        $this->enableSchematronValidation = $enable;
        $this->schematronLevels = $levels;
        return $this;
    }

}
