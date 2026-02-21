<?php

declare(strict_types=1);

namespace Peppol\Formats;

use Peppol\Core\InvoiceBase;
use Peppol\Standards\EN16931Invoice;
use Peppol\Standards\UblBeInvoice;
use Peppol\Models\Address;
use Peppol\Models\ElectronicAddress;
use Peppol\Models\Party;
use Peppol\Models\InvoiceLine;
use Peppol\Models\PaymentInfo;
use Peppol\Models\AttachedDocument;
use Peppol\Exceptions\ImportWarningException;

/**
 * Importateur XML UBL 2.1
 * 
 * Parse un document XML UBL 2.1 et crée une instance de facture
 * 
 * @package Peppol\Formats
 * @author Votre Nom
 * @version 1.0
 */
class XmlImporter
{
    /**
     * Importe une facture depuis XML UBL
     * 
     * @param string $xmlContent Contenu XML ou chemin vers un fichier
     * @param string|null $targetClass Classe cible (auto-détectée si null)
     * @return InvoiceBase
     * @throws \InvalidArgumentException
     */
    public static function fromUbl(string $xmlContent, ?string $targetClass = null, bool $strict = true): InvoiceBase
    {
        // Si c'est un chemin de fichier, on lit son contenu
        if (file_exists($xmlContent)) {
            $xmlContent = file_get_contents($xmlContent);
            if ($xmlContent === false) {
                throw new \InvalidArgumentException("Impossible de lire le fichier XML");
            }
        }
        
        // Désactivation des erreurs XML pour les gérer manuellement
        libxml_use_internal_errors(true);
        
        $xml = new \DOMDocument();
        if (!$xml->loadXML($xmlContent)) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            throw new \InvalidArgumentException("XML invalide: " . ($errors[0]->message ?? 'Erreur inconnue'));
        }
        
        // Création d'un XPath pour faciliter la navigation
        $xpath = new \DOMXPath($xml);
        $xpath->registerNamespace('ubl', 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2');
        $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        
        // Détection automatique du type de facture si non spécifié
        if ($targetClass === null) {
            $targetClass = self::detectInvoiceType($xpath);
        }
        
        // Extraction des données de base
        $invoiceNumber = self::getXPathValue($xpath, '//cbc:ID');
        $issueDate = self::getXPathValue($xpath, '//cbc:IssueDate');
        $invoiceTypeCode = self::getXPathValue($xpath, '//cbc:InvoiceTypeCode', '380');
        $currencyCode = self::getXPathValue($xpath, '//cbc:DocumentCurrencyCode', 'EUR');
        
        if (empty($invoiceNumber) || empty($issueDate)) {
            throw new \InvalidArgumentException("Le XML doit contenir au minimum un numéro de facture et une date d'émission");
        }
        
        // Création de l'instance
        $invoice = new $targetClass($invoiceNumber, $issueDate, $invoiceTypeCode, $currencyCode);
        
        // Chargement des données de base
        self::loadBasicData($invoice, $xpath);
        
        // Chargement des parties
        self::loadSeller($invoice, $xpath);
        self::loadBuyer($invoice, $xpath);
        
        // Collecteur d'anomalies (mode lenient)
        $anomalies = [];

        // Chargement des informations de paiement
        self::loadPaymentInfo($invoice, $xpath, $strict, $anomalies);

        // Chargement des documents joints
        self::loadAttachedDocuments($invoice, $xpath);

        // Chargement des lignes de facture
        self::loadInvoiceLines($invoice, $xpath, $strict, $anomalies);

        if ($strict) {
            // Comportement original : recalcul + validation
            if (!empty($invoice->getInvoiceLines())) {
                $invoice->calculateTotals();
            }
        } else {
            // Mode lenient : totaux depuis le XML
            self::loadDeclaredTotals($invoice, $xpath);

            // Recalcul pour comparaison (si des lignes ont été chargées)
            if (!empty($invoice->getInvoiceLines())) {
                $invoice->calculateTotals();
            }

            // Vérification de cohérence
            $warnings = $invoice->checkImportedTotals();

            if (!empty($warnings) || !empty($anomalies)) {
                throw new ImportWarningException($invoice, $warnings, $anomalies);
            }
        }

        // Chargement des données spécifiques UBL.BE
        if ($invoice instanceof UblBeInvoice) {
            self::loadUblBeSpecificData($invoice, $xpath);
        }
        
        return $invoice;
    }
    
    /**
     * Détecte le type de facture depuis le CustomizationID
     * 
     * @param \DOMXPath $xpath
     * @return string Classe à instancier
     */
    private static function detectInvoiceType(\DOMXPath $xpath): string
    {
        $customizationId = self::getXPathValue($xpath, '//cbc:CustomizationID', '');
        
        if (strpos($customizationId, 'UBL.BE') !== false) {
            return UblBeInvoice::class;
        }
        
        return EN16931Invoice::class;
    }
    
    /**
     * Charge les données de base
     */
    private static function loadBasicData(InvoiceBase $invoice, \DOMXPath $xpath): void
    {
        $dueDate = self::getXPathValue($xpath, '//cbc:DueDate');
        if ($dueDate) {
            $invoice->setDueDate($dueDate);
        }
        
        $deliveryDate = self::getXPathValue($xpath, '//cbc:ActualDeliveryDate');
        if ($deliveryDate) {
            $invoice->setDeliveryDate($deliveryDate);
        }
        
        $orderRef = self::getXPathValue($xpath, '//cac:OrderReference/cbc:ID');
        if ($orderRef) {
            $invoice->setPurchaseOrderReference($orderRef);
        }
        
        $contractRef = self::getXPathValue($xpath, '//cac:ContractDocumentReference/cbc:ID');
        if ($contractRef) {
            $invoice->setContractReference($contractRef);
        }
    }
    
    /**
     * Charge le vendeur
     */
    private static function loadSeller(InvoiceBase $invoice, \DOMXPath $xpath): void
    {
        $basePath = '//cac:AccountingSupplierParty/cac:Party';
        
        $name = self::getXPathValue($xpath, "{$basePath}/cac:PartyLegalEntity/cbc:RegistrationName");
        $vatId = self::getXPathValue($xpath, "{$basePath}/cac:PartyTaxScheme/cbc:CompanyID");
        
        if (!$name || !$vatId) {
            return; // Données incomplètes
        }
        
        // Adresse
        $addressPath = "{$basePath}/cac:PostalAddress";
        $streetName = self::getXPathValue($xpath, "{$addressPath}/cbc:StreetName", '');
        $cityName = self::getXPathValue($xpath, "{$addressPath}/cbc:CityName", '');
        $postalZone = self::getXPathValue($xpath, "{$addressPath}/cbc:PostalZone", '');
        $countryCode = self::getXPathValue($xpath, "{$addressPath}/cac:Country/cbc:IdentificationCode", '');
        
        if (!$streetName || !$cityName || !$postalZone || !$countryCode) {
            return;
        }
        
        $address = new Address($streetName, $cityName, $postalZone, $countryCode);
        
        // Autres informations
        $companyId = self::getXPathValue($xpath, "{$basePath}/cac:PartyLegalEntity/cbc:CompanyID");
        $email = self::getXPathValue($xpath, "{$basePath}/cac:Contact/cbc:ElectronicMail");
        
        // Adresse électronique
        $electronicAddress = null;
        $endpointId = self::getXPathValue($xpath, "{$basePath}/cbc:EndpointID");
        if ($endpointId) {
            $endpointNode = $xpath->query("{$basePath}/cbc:EndpointID")->item(0);
            $schemeId = $endpointNode?->getAttribute('schemeID') ?? '9925';
            
            try {
                $electronicAddress = new ElectronicAddress($schemeId, $endpointId);
            } catch (\Exception $e) {
                // Ignore si invalide
            }
        }
        
        $seller = new Party($name, $address, $vatId, $companyId, $email, $electronicAddress);
        $invoice->setSeller($seller);
    }
    
    /**
     * Charge l'acheteur
     */
    private static function loadBuyer(InvoiceBase $invoice, \DOMXPath $xpath): void
    {
        $basePath = '//cac:AccountingCustomerParty/cac:Party';
        
        $name = self::getXPathValue($xpath, "{$basePath}/cac:PartyLegalEntity/cbc:RegistrationName");
        if (!$name) {
            return;
        }
        
        // Adresse
        $addressPath = "{$basePath}/cac:PostalAddress";
        $streetName = self::getXPathValue($xpath, "{$addressPath}/cbc:StreetName", '');
        $cityName = self::getXPathValue($xpath, "{$addressPath}/cbc:CityName", '');
        $postalZone = self::getXPathValue($xpath, "{$addressPath}/cbc:PostalZone", '');
        $countryCode = self::getXPathValue($xpath, "{$addressPath}/cac:Country/cbc:IdentificationCode", '');
        
        if (!$streetName || !$cityName || !$postalZone || !$countryCode) {
            return;
        }
        
        $address = new Address($streetName, $cityName, $postalZone, $countryCode);
        
        $vatId = self::getXPathValue($xpath, "{$basePath}/cac:PartyTaxScheme/cbc:CompanyID");
        $email = self::getXPathValue($xpath, "{$basePath}/cac:Contact/cbc:ElectronicMail");
        
        // Adresse électronique
        $electronicAddress = null;
        $endpointId = self::getXPathValue($xpath, "{$basePath}/cbc:EndpointID");
        if ($endpointId) {
            $endpointNode = $xpath->query("{$basePath}/cbc:EndpointID")->item(0);
            $schemeId = $endpointNode?->getAttribute('schemeID') ?? '9925';
            
            try {
                $electronicAddress = new ElectronicAddress($schemeId, $endpointId);
            } catch (\Exception $e) {
                // Ignore si invalide
            }
        }
        
        $buyer = new Party($name, $address, $vatId, null, $email, $electronicAddress);
        $invoice->setBuyer($buyer);
    }
    
    /**
     * Charge les documents joints
     */
    private static function loadAttachedDocuments(InvoiceBase $invoice, \DOMXPath $xpath): void
    {
        $attachedDocs = $xpath->query('//cac:AdditionalDocumentReference');
        
        foreach ($attachedDocs as $docNode) {
            $docId = self::getXPathValue($xpath, 'cbc:ID', null, $docNode);
            
            // Ignore les références qui ne sont pas des pièces jointes
            if ($docId !== 'Attachment') {
                continue;
            }
            
            $embeddedDocNode = $xpath->query('cac:Attachment/cbc:EmbeddedDocumentBinaryObject', $docNode)->item(0);
            if (!$embeddedDocNode) {
                continue;
            }
            
            $base64Content = $embeddedDocNode->nodeValue;
            $mimeType = $embeddedDocNode->getAttribute('mimeCode');
            $filename = $embeddedDocNode->getAttribute('filename');
            
            if (!$base64Content || !$mimeType || !$filename) {
                continue;
            }
            
            $fileContent = base64_decode($base64Content);
            $description = self::getXPathValue($xpath, 'cbc:DocumentDescription', null, $docNode);
            $documentType = self::getXPathValue($xpath, 'cbc:DocumentTypeCode', null, $docNode);
            
            try {
                $document = new AttachedDocument($filename, $fileContent, $mimeType, $description, $documentType);
                $invoice->attachDocument($document);
            } catch (\Exception $e) {
                // Ignore si invalide
            }
        }
    }
    
/**
     * Charge les informations de paiement.
     * En mode lenient : BIC invalide ne rejette plus tout le bloc.
     */
    private static function loadPaymentInfo(InvoiceBase $invoice, \DOMXPath $xpath, bool $strict, array &$anomalies): void
    {
        $iban = self::getXPathValue($xpath, '//cac:PaymentMeans/cac:PayeeFinancialAccount/cbc:ID');
        if (!$iban) {
            return;
        }

        $paymentMeansCode = self::getXPathValue($xpath, '//cac:PaymentMeans/cbc:PaymentMeansCode', '30');
        $bic = self::getXPathValue($xpath, '//cac:PaymentMeans/cac:PayeeFinancialAccount/cac:FinancialInstitutionBranch/cbc:ID');
        $paymentRef = self::getXPathValue($xpath, '//cac:PaymentMeans/cbc:PaymentID');
        $paymentTerms = self::getXPathValue($xpath, '//cbc:Note');

        try {
            $paymentInfo = new PaymentInfo($paymentMeansCode, $iban, $bic, $paymentRef, $paymentTerms);
            $invoice->setPaymentInfo($paymentInfo);
        } catch (\InvalidArgumentException $e) {
            if ($strict) {
                throw $e;
            }
            // Mode lenient : on charge avec BIC brut
            try {
                $paymentInfo = PaymentInfo::withRawBic($paymentMeansCode, $iban, $bic, $paymentRef, $paymentTerms);
                $invoice->setPaymentInfo($paymentInfo);
                $anomalies[] = sprintf(
                    'Paiement : BIC invalide « %s » chargé tel quel — %s',
                    $bic ?? '',
                    $e->getMessage()
                );
            } catch (\Exception $e2) {
                $anomalies[] = 'Bloc paiement ignoré : ' . $e2->getMessage();
            }
        }
    }

    /**
     * Lit LegalMonetaryTotal depuis le XML et stocke via setImportedTotals().
     * Appelé uniquement en mode lenient.
     */
    private static function loadDeclaredTotals(InvoiceBase $invoice, \DOMXPath $xpath): void
    {
        $lineExtension = (float) self::getXPathValue($xpath, '//cac:LegalMonetaryTotal/cbc:LineExtensionAmount', '0');
        $taxExclusive  = (float) self::getXPathValue($xpath, '//cac:LegalMonetaryTotal/cbc:TaxExclusiveAmount',  '0');
        $taxInclusive  = (float) self::getXPathValue($xpath, '//cac:LegalMonetaryTotal/cbc:TaxInclusiveAmount',  '0');
        $prepaid       = (float) self::getXPathValue($xpath, '//cac:LegalMonetaryTotal/cbc:PrepaidAmount',       '0');
        $payable       = (float) self::getXPathValue($xpath, '//cac:LegalMonetaryTotal/cbc:PayableAmount',       '0');
        $taxAmount     = (float) self::getXPathValue($xpath, '//cac:TaxTotal/cbc:TaxAmount',                    '0');

        $invoice->setImportedTotals($lineExtension, $taxExclusive, $taxInclusive, $prepaid, $payable, $taxAmount);
    }

    /**
     * Charge les lignes de facture.
     * En mode lenient : unitCode inconnu accepté via Reflection.
     */
    private static function loadInvoiceLines(InvoiceBase $invoice, \DOMXPath $xpath, bool $strict, array &$anomalies): void
    {
        $lines = $xpath->query('//cac:InvoiceLine');

        foreach ($lines as $lineNode) {
            $lineId = self::getXPathValue($xpath, 'cbc:ID', null, $lineNode);
            $lineName = self::getXPathValue($xpath, 'cac:Item/cbc:Name', null, $lineNode);

            if (!$lineId || !$lineName) {
                continue;
            }

            $quantityNode = $xpath->query('cbc:InvoicedQuantity', $lineNode)->item(0);
            $quantity = $quantityNode ? (float)$quantityNode->nodeValue : 0;
            $unitCode = $quantityNode?->getAttribute('unitCode') ?? 'C62';

            $unitPrice = (float)self::getXPathValue($xpath, 'cac:Price/cbc:PriceAmount', '0', $lineNode);
            $vatCategory = self::getXPathValue($xpath, 'cac:Item/cac:ClassifiedTaxCategory/cbc:ID', 'S', $lineNode);
            $vatRate = (float)self::getXPathValue($xpath, 'cac:Item/cac:ClassifiedTaxCategory/cbc:Percent', '0', $lineNode);
            $description = self::getXPathValue($xpath, 'cac:Item/cbc:Description', null, $lineNode);

            if ($quantity <= 0) {
                continue;
            }

            try {
                $line = new InvoiceLine($lineId, $lineName, $quantity, $unitCode, $unitPrice, $vatCategory, $vatRate, $description);
                $invoice->addInvoiceLine($line);
            } catch (\InvalidArgumentException $e) {
                if ($strict) {
                    throw $e;
                }
                // Mode lenient : on crée la ligne avec C62 puis on injecte le vrai unitCode
                try {
                    $line = new InvoiceLine($lineId, $lineName, $quantity, 'C62', $unitPrice, $vatCategory, $vatRate, $description);
                    $ref = new \ReflectionProperty(InvoiceLine::class, 'unitCode');
                    $ref->setAccessible(true);
                    $ref->setValue($line, $unitCode);
                    $invoice->addInvoiceLine($line);
                    $anomalies[] = sprintf(
                        'Ligne %s : unitCode non standard « %s » chargé tel quel — %s',
                        $lineId,
                        $unitCode,
                        $e->getMessage()
                    );
                } catch (\Exception $e2) {
                    $anomalies[] = sprintf('Ligne %s ignorée : %s', $lineId, $e2->getMessage());
                }
            }
        }
    }
    
    /**
     * Charge les données spécifiques UBL.BE
     */
    private static function loadUblBeSpecificData(UblBeInvoice $invoice, \DOMXPath $xpath): void
    {
        $buyerRef = self::getXPathValue($xpath, '//cbc:BuyerReference');
        if ($buyerRef) {
            $invoice->setBuyerReference($buyerRef);
        }
        
        $paymentTerms = self::getXPathValue($xpath, '//cbc:Note');
        if ($paymentTerms) {
            $invoice->setPaymentTerms($paymentTerms);
        }
        
        $exemptionReason = self::getXPathValue($xpath, '//cac:TaxSubtotal/cac:TaxCategory/cbc:TaxExemptionReasonCode');
        if ($exemptionReason) {
            try {
                $invoice->setVatExemptionReason($exemptionReason);
            } catch (\Exception $e) {
                // Ignore si code non reconnu
            }
        }
    }
    
    /**
     * Extrait une valeur via XPath
     * 
     * @param \DOMXPath $xpath
     * @param string $query
     * @param string|null $default
     * @param \DOMNode|null $contextNode
     * @return string|null
     */
    private static function getXPathValue(\DOMXPath $xpath, string $query, ?string $default = null, ?\DOMNode $contextNode = null): ?string
    {
        $nodes = $contextNode 
            ? $xpath->query($query, $contextNode)
            : $xpath->query($query);
        
        if ($nodes && $nodes->length > 0) {
            $value = trim($nodes->item(0)->nodeValue);
            return $value !== '' ? $value : $default;
        }
        
        return $default;
    }
}
