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
 * @version 1.1
 */
class XmlExporter {

    private InvoiceBase $invoice;
    private string $customizationId;
    private string $profileId;

    public function __construct(
            InvoiceBase $invoice,
            ?string $customizationId = null,
            ?string $profileId = null
    ) {
        $this->invoice = $invoice;
        $this->customizationId = $customizationId ?? $this->detectCustomizationId();
        $this->profileId = $profileId ?? InvoiceConstants::PROFILE_PEPPOL;
    }

    private function detectCustomizationId(): string {
        if ($this->invoice instanceof UblBeInvoice) {
            return InvoiceConstants::CUSTOMIZATION_UBL_BE;
        }
        return InvoiceConstants::CUSTOMIZATION_PEPPOL;
    }

    // =========================================================================
    // Export principal
    // =========================================================================

    public function toUbl21(): string {
        $errors = $this->invoice->validate();
        if (!empty($errors)) {
            throw new \InvalidArgumentException(
                            'Impossible d\'exporter une facture invalide. Erreurs: ' . implode(', ', $errors)
                    );
        }

        $xml = new \DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;

        $invoice = $xml->createElementNS(
                'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2',
                'Invoice'
        );
        $invoice->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cac',
                'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $invoice->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cbc',
                'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $xml->appendChild($invoice);

        $this->addDocumentHeader($xml, $invoice);
        $this->addInvoicePeriod($xml, $invoice);
        $this->addPrecedingInvoiceReference($xml, $invoice);
        $this->addReferences($xml, $invoice);
        $this->addAttachedDocuments($xml, $invoice);
        $this->addSellerParty($xml, $invoice);
        $this->addBuyerParty($xml, $invoice);
        $this->addDelivery($xml, $invoice);
        $this->addPaymentMeans($xml, $invoice);
        $this->addPaymentTerms($xml, $invoice);
        $this->addAllowanceCharges($xml, $invoice);
        $this->addTaxTotals($xml, $invoice);
        $this->addMonetaryTotals($xml, $invoice);
        $this->addInvoiceLines($xml, $invoice);

        return $xml->saveXML();
    }

    // =========================================================================
    // En-tête
    // =========================================================================

    private function addDocumentHeader(\DOMDocument $xml, \DOMElement $invoice): void {
        $this->addElement($xml, $invoice, 'cbc:UBLVersionID', InvoiceConstants::UBL_VERSION);
        $this->addElement($xml, $invoice, 'cbc:CustomizationID', $this->customizationId);
        $this->addElement($xml, $invoice, 'cbc:ProfileID', $this->profileId);
        $this->addElement($xml, $invoice, 'cbc:ID', $this->invoice->getInvoiceNumber());
        $this->addElement($xml, $invoice, 'cbc:IssueDate', $this->invoice->getIssueDate());

        if ($this->invoice->getDueDate()) {
            $this->addElement($xml, $invoice, 'cbc:DueDate', $this->invoice->getDueDate());
        }

        $this->addElement($xml, $invoice, 'cbc:InvoiceTypeCode', $this->invoice->getInvoiceTypeCode());

        // BT-22 — Note de facture
        if ($this->invoice->getInvoiceNote()) {
            $this->addElement($xml, $invoice, 'cbc:Note', $this->invoice->getInvoiceNote());
        }

        $this->addElement($xml, $invoice, 'cbc:DocumentCurrencyCode', $this->invoice->getDocumentCurrencyCode());

        // BT-19 — Référence comptable acheteur
        if ($this->invoice->getBuyerAccountingReference()) {
            $this->addElement($xml, $invoice, 'cbc:AccountingCost', $this->invoice->getBuyerAccountingReference());
        }

        // BT-10 — Référence acheteur
        if ($this->invoice->getBuyerReference()) {
            $this->addElement($xml, $invoice, 'cbc:BuyerReference', $this->invoice->getBuyerReference());
        }
    }

// =========================================================================
// BG-14 — Période de facturation en-tête
// =========================================================================

    private function addInvoicePeriod(\DOMDocument $xml, \DOMElement $invoice): void {
        if ($this->invoice->getInvoicePeriodStartDate() === null && $this->invoice->getInvoicePeriodEndDate() === null) {
            return;
        }

        $period = $xml->createElementNS(
                'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                'cac:InvoicePeriod'
        );
        if ($this->invoice->getInvoicePeriodStartDate()) {
            $this->addElement($xml, $period, 'cbc:StartDate', $this->invoice->getInvoicePeriodStartDate());
        }
        if ($this->invoice->getInvoicePeriodEndDate()) {
            $this->addElement($xml, $period, 'cbc:EndDate', $this->invoice->getInvoicePeriodEndDate());
        }
        $invoice->appendChild($period);
    }

    // =========================================================================
    // BG-3 — Référence facture précédente
    // =========================================================================

    private function addPrecedingInvoiceReference(\DOMDocument $xml, \DOMElement $invoice): void {
        if ($this->invoice->getPrecedingInvoiceNumber() === null) {
            return;
        }

        $billingRef = $xml->createElementNS(
                'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                'cac:BillingReference'
        );
        $invoiceDocRef = $xml->createElementNS(
                'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                'cac:InvoiceDocumentReference'
        );
        $this->addElement($xml, $invoiceDocRef, 'cbc:ID', $this->invoice->getPrecedingInvoiceNumber());
        if ($this->invoice->getPrecedingInvoiceDate()) {
            $this->addElement($xml, $invoiceDocRef, 'cbc:IssueDate', $this->invoice->getPrecedingInvoiceDate());
        }
        $billingRef->appendChild($invoiceDocRef);
        $invoice->appendChild($billingRef);
    }

    // =========================================================================
    // Références (BT-11 … BT-16)
    // =========================================================================

    private function addReferences(\DOMDocument $xml, \DOMElement $invoice): void {
        // BT-13 / BT-14 — OrderReference
        if ($this->invoice->getPurchaseOrderReference() !== null || $this->invoice->getSalesOrderReference() !== null) {
            $orderRef = $xml->createElementNS(
                    'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                    'cac:OrderReference'
            );
            // cbc:ID est obligatoire dans cac:OrderReference (BR-42)
            // Si BT-13 absent mais BT-14 présent, on utilise "NA" comme sentinelle
            $this->addElement(
                    $xml, $orderRef, 'cbc:ID',
                    $this->invoice->getPurchaseOrderReference() ?? 'NA'
            );
            if ($this->invoice->getSalesOrderReference()) {
                $this->addElement($xml, $orderRef, 'cbc:SalesOrderID', $this->invoice->getSalesOrderReference());
            }
            $invoice->appendChild($orderRef);
        }

        // BT-16 — DespatchDocumentReference
        if ($this->invoice->getDespatchAdviceReference()) {
            $ref = $xml->createElementNS(
                    'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                    'cac:DespatchDocumentReference'
            );
            $this->addElement($xml, $ref, 'cbc:ID', $this->invoice->getDespatchAdviceReference());
            $invoice->appendChild($ref);
        }

        // BT-15 — ReceiptDocumentReference
        if ($this->invoice->getReceivingAdviceReference()) {
            $ref = $xml->createElementNS(
                    'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                    'cac:ReceiptDocumentReference'
            );
            $this->addElement($xml, $ref, 'cbc:ID', $this->invoice->getReceivingAdviceReference());
            $invoice->appendChild($ref);
        }

        // BT-12 — ContractDocumentReference
        if ($this->invoice->getContractReference()) {
            $contractRef = $xml->createElementNS(
                    'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                    'cac:ContractDocumentReference'
            );
            $this->addElement($xml, $contractRef, 'cbc:ID', $this->invoice->getContractReference());
            $invoice->appendChild($contractRef);
        }

        // BT-11 — ProjectReference
        if ($this->invoice->getProjectReference()) {
            $projRef = $xml->createElementNS(
                    'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                    'cac:ProjectReference'
            );
            $this->addElement($xml, $projRef, 'cbc:ID', $this->invoice->getProjectReference());
            $invoice->appendChild($projRef);
        }
    }

    // =========================================================================
    // Documents joints
    // =========================================================================

    private function addAttachedDocuments(\DOMDocument $xml, \DOMElement $invoice): void {
        foreach ($this->invoice->getAttachedDocuments() as $doc) {
            $additionalDoc = $xml->createElementNS(
                    'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                    'cac:AdditionalDocumentReference'
            );

            $this->addElement($xml, $additionalDoc, 'cbc:ID', 'Attachment');

            if ($doc->getDescription()) {
                $this->addElement($xml, $additionalDoc, 'cbc:DocumentDescription', $doc->getDescription());
            }

            $attachment = $xml->createElementNS(
                    'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                    'cac:Attachment'
            );
            $embeddedDoc = $xml->createElementNS(
                    'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                    'cbc:EmbeddedDocumentBinaryObject',
                    $doc->getContent()
            );
            $embeddedDoc->setAttribute('mimeCode', $doc->getMimeType());
            $embeddedDoc->setAttribute('filename', $doc->getFilename());
            $attachment->appendChild($embeddedDoc);
            $additionalDoc->appendChild($attachment);
            $invoice->appendChild($additionalDoc);
        }
    }

    // =========================================================================
    // Parties
    // =========================================================================

    private function addSellerParty(\DOMDocument $xml, \DOMElement $invoice): void {
        $seller = $this->invoice->getSeller();
        $supplierParty = $xml->createElementNS(
                'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                'cac:AccountingSupplierParty'
        );
        $party = $xml->createElementNS(
                'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                'cac:Party'
        );

        if ($seller->getElectronicAddress()) {
            $endpoint = $xml->createElementNS(
                    'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                    'cbc:EndpointID',
                    $seller->getElectronicAddress()->getIdentifier()
            );
            $endpoint->setAttribute('schemeID', $seller->getElectronicAddress()->getSchemeId());
            $party->appendChild($endpoint);
        }

        if ($seller->getCompanyId()) {
            $partyId = $xml->createElementNS(
                    'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                    'cac:PartyIdentification'
            );
            $this->addElement($xml, $partyId, 'cbc:ID', $seller->getCompanyId());
            $party->appendChild($partyId);
        }

        $partyName = $xml->createElementNS(
                'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                'cac:PartyName'
        );
        $this->addElement($xml, $partyName, 'cbc:Name', $seller->getName());
        $party->appendChild($partyName);

        $this->addPostalAddress($xml, $party, $seller->getAddress());

        $partyTaxScheme = $xml->createElementNS(
                'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                'cac:PartyTaxScheme'
        );
        $this->addElement($xml, $partyTaxScheme, 'cbc:CompanyID', $seller->getVatId() ?? '');
        $taxScheme = $xml->createElementNS(
                'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                'cac:TaxScheme'
        );
        $this->addElement($xml, $taxScheme, 'cbc:ID', 'VAT');
        $partyTaxScheme->appendChild($taxScheme);
        $party->appendChild($partyTaxScheme);

        $partyLegalEntity = $xml->createElementNS(
                'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                'cac:PartyLegalEntity'
        );
        $this->addElement($xml, $partyLegalEntity, 'cbc:RegistrationName', $seller->getName());
        if ($seller->getCompanyId()) {
            $this->addElement($xml, $partyLegalEntity, 'cbc:CompanyID', $seller->getCompanyId());
        }
        $party->appendChild($partyLegalEntity);

        if ($seller->getEmail()) {
            $contact = $xml->createElementNS(
                    'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                    'cac:Contact'
            );
            $this->addElement($xml, $contact, 'cbc:ElectronicMail', $seller->getEmail());
            $party->appendChild($contact);
        }

        $supplierParty->appendChild($party);
        $invoice->appendChild($supplierParty);
    }

    private function addBuyerParty(\DOMDocument $xml, \DOMElement $invoice): void {
        $buyer = $this->invoice->getBuyer();
        $customerParty = $xml->createElementNS(
                'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                'cac:AccountingCustomerParty'
        );
        $party = $xml->createElementNS(
                'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                'cac:Party'
        );

        if ($buyer->getElectronicAddress()) {
            $endpoint = $xml->createElementNS(
                    'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                    'cbc:EndpointID',
                    $buyer->getElectronicAddress()->getIdentifier()
            );
            $endpoint->setAttribute('schemeID', $buyer->getElectronicAddress()->getSchemeId());
            $party->appendChild($endpoint);
        }

        $partyName = $xml->createElementNS(
                'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                'cac:PartyName'
        );
        $this->addElement($xml, $partyName, 'cbc:Name', $buyer->getName());
        $party->appendChild($partyName);

        $this->addPostalAddress($xml, $party, $buyer->getAddress());

        if ($buyer->getVatId()) {
            $partyTaxScheme = $xml->createElementNS(
                    'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                    'cac:PartyTaxScheme'
            );
            $this->addElement($xml, $partyTaxScheme, 'cbc:CompanyID', $buyer->getVatId());
            $taxScheme = $xml->createElementNS(
                    'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                    'cac:TaxScheme'
            );
            $this->addElement($xml, $taxScheme, 'cbc:ID', 'VAT');
            $partyTaxScheme->appendChild($taxScheme);
            $party->appendChild($partyTaxScheme);
        }

        $partyLegalEntity = $xml->createElementNS(
                'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                'cac:PartyLegalEntity'
        );
        $this->addElement($xml, $partyLegalEntity, 'cbc:RegistrationName', $buyer->getName());
        // BT-47 — Buyer legal registration ID
        if ($buyer->getCompanyId()) {
            $this->addElement($xml, $partyLegalEntity, 'cbc:CompanyID', $buyer->getCompanyId());
        }
        $party->appendChild($partyLegalEntity);

        if ($buyer->getEmail()) {
            $contact = $xml->createElementNS(
                    'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                    'cac:Contact'
            );
            $this->addElement($xml, $contact, 'cbc:ElectronicMail', $buyer->getEmail());
            $party->appendChild($contact);
        }

        $customerParty->appendChild($party);
        $invoice->appendChild($customerParty);
    }

    private function addPostalAddress(\DOMDocument $xml, \DOMElement $parent, $address): void {
        $postalAddress = $xml->createElementNS(
                'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                'cac:PostalAddress'
        );
        $this->addElement($xml, $postalAddress, 'cbc:StreetName', $address->getStreetName());
        if ($address->getAdditionalStreetName()) {
            $this->addElement($xml, $postalAddress, 'cbc:AdditionalStreetName', $address->getAdditionalStreetName());
        }
        $this->addElement($xml, $postalAddress, 'cbc:CityName', $address->getCityName());
        $this->addElement($xml, $postalAddress, 'cbc:PostalZone', $address->getPostalZone());

        $country = $xml->createElementNS(
                'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                'cac:Country'
        );
        $this->addElement($xml, $country, 'cbc:IdentificationCode', $address->getCountryCode());
        $postalAddress->appendChild($country);
        $parent->appendChild($postalAddress);
    }

    // =========================================================================
    // Livraison (BG-13 / BT-72)
    // =========================================================================

    private function addDelivery(\DOMDocument $xml, \DOMElement $invoice): void {
        if (!$this->invoice->getDeliveryDate()) {
            return;
        }

        $delivery = $xml->createElementNS(
                'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                'cac:Delivery'
        );
        $this->addElement($xml, $delivery, 'cbc:ActualDeliveryDate', $this->invoice->getDeliveryDate());
        $invoice->appendChild($delivery);
    }

    // =========================================================================
    // Paiement
    // =========================================================================

    private function addPaymentMeans(\DOMDocument $xml, \DOMElement $invoice): void {
        $paymentInfo = $this->invoice->getPaymentInfo();
        if (!$paymentInfo || !$paymentInfo->getIban()) {
            return;
        }

        $paymentMeans = $xml->createElementNS(
                'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                'cac:PaymentMeans'
        );
        $this->addElement($xml, $paymentMeans, 'cbc:PaymentMeansCode', $paymentInfo->getPaymentMeansCode());

        if ($paymentInfo->getPaymentReference()) {
            $this->addElement($xml, $paymentMeans, 'cbc:PaymentID', $paymentInfo->getPaymentReference());
        }

        $payeeFinancialAccount = $xml->createElementNS(
                'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                'cac:PayeeFinancialAccount'
        );
        $this->addElement($xml, $payeeFinancialAccount, 'cbc:ID', $paymentInfo->getIban());

        if ($paymentInfo->getBic()) {
            $financialInstitution = $xml->createElementNS(
                    'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                    'cac:FinancialInstitutionBranch'
            );
            $this->addElement($xml, $financialInstitution, 'cbc:ID', $paymentInfo->getBic());
            $payeeFinancialAccount->appendChild($financialInstitution);
        }

        $paymentMeans->appendChild($payeeFinancialAccount);
        $invoice->appendChild($paymentMeans);
    }

    private function addPaymentTerms(\DOMDocument $xml, \DOMElement $invoice): void {
        $terms = $this->invoice->getPaymentTerms();
        if (!$terms) {
            return;
        }
        $paymentTerms = $xml->createElementNS(
                'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                'cac:PaymentTerms'
        );
        $this->addElement($xml, $paymentTerms, 'cbc:Note', $terms);
        $invoice->appendChild($paymentTerms);
    }

    // =========================================================================
    // AllowanceCharge (BG-20 / BG-21)
    // =========================================================================

    private function addAllowanceCharges(\DOMDocument $xml, \DOMElement $invoice): void {
        foreach ($this->invoice->getAllowanceCharges() as $ac) {
            $acElem = $xml->createElementNS(
                    'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                    'cac:AllowanceCharge'
            );

            $chargeIndicator = $xml->createElementNS(
                    'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                    'cbc:ChargeIndicator',
                    $ac->getChargeIndicator() ? 'true' : 'false'
            );
            $acElem->appendChild($chargeIndicator);

            if ($ac->getReasonCode()) {
                $this->addElement($xml, $acElem, 'cbc:AllowanceChargeReasonCode', $ac->getReasonCode());
            }
            if ($ac->getReason()) {
                $this->addElement($xml, $acElem, 'cbc:AllowanceChargeReason', $ac->getReason());
            }
            if ($ac->getMultiplierFactorNumeric() !== null) {
                $this->addElement(
                        $xml, $acElem,
                        'cbc:MultiplierFactorNumeric',
                        number_format($ac->getMultiplierFactorNumeric(), 2, '.', '')
                );
            }

            $amountElem = $xml->createElementNS(
                    'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                    'cbc:Amount',
                    number_format($ac->getAmount(), 2, '.', '')
            );
            $amountElem->setAttribute('currencyID', $this->invoice->getDocumentCurrencyCode());
            $acElem->appendChild($amountElem);

            if ($ac->getBaseAmount() !== null) {
                $baseAmountElem = $xml->createElementNS(
                        'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                        'cbc:BaseAmount',
                        number_format($ac->getBaseAmount(), 2, '.', '')
                );
                $baseAmountElem->setAttribute('currencyID', $this->invoice->getDocumentCurrencyCode());
                $acElem->appendChild($baseAmountElem);
            }

            // TaxCategory
            $taxCategory = $xml->createElementNS(
                    'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                    'cac:TaxCategory'
            );
            $this->addElement($xml, $taxCategory, 'cbc:ID', $ac->getVatCategory());
            $this->addElement($xml, $taxCategory, 'cbc:Percent', number_format($ac->getVatRate(), 2, '.', ''));
            $taxScheme = $xml->createElementNS(
                    'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                    'cac:TaxScheme'
            );
            $this->addElement($xml, $taxScheme, 'cbc:ID', 'VAT');
            $taxCategory->appendChild($taxScheme);
            $acElem->appendChild($taxCategory);

            $invoice->appendChild($acElem);
        }
    }

    // =========================================================================
    // TVA
    // =========================================================================

    private function addTaxTotals(\DOMDocument $xml, \DOMElement $invoice): void {
        foreach ($this->invoice->getVatBreakdown() as $vat) {
            $taxTotal = $xml->createElementNS(
                    'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                    'cac:TaxTotal'
            );

            $taxAmount = $xml->createElementNS(
                    'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                    'cbc:TaxAmount',
                    number_format($vat->getTaxAmount(), 2, '.', '')
            );
            $taxAmount->setAttribute('currencyID', $this->invoice->getDocumentCurrencyCode());
            $taxTotal->appendChild($taxAmount);

            $taxSubtotal = $xml->createElementNS(
                    'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                    'cac:TaxSubtotal'
            );

            $taxableAmount = $xml->createElementNS(
                    'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                    'cbc:TaxableAmount',
                    number_format($vat->getTaxableAmount(), 2, '.', '')
            );
            $taxableAmount->setAttribute('currencyID', $this->invoice->getDocumentCurrencyCode());
            $taxSubtotal->appendChild($taxableAmount);

            $taxAmountSub = $xml->createElementNS(
                    'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                    'cbc:TaxAmount',
                    number_format($vat->getTaxAmount(), 2, '.', '')
            );
            $taxAmountSub->setAttribute('currencyID', $this->invoice->getDocumentCurrencyCode());
            $taxSubtotal->appendChild($taxAmountSub);

            $taxCategory = $xml->createElementNS(
                    'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                    'cac:TaxCategory'
            );
            $this->addElement($xml, $taxCategory, 'cbc:ID', $vat->getCategory());
            $this->addElement($xml, $taxCategory, 'cbc:Percent', number_format($vat->getRate(), 2, '.', ''));

            if ($vat->getExemptionReason()) {
                $this->addElement($xml, $taxCategory, 'cbc:TaxExemptionReasonCode', $vat->getExemptionReason());
                $this->addElement(
                        $xml, $taxCategory,
                        'cbc:TaxExemptionReason',
                        InvoiceConstants::VAT_EXEMPTION_REASONS[$vat->getExemptionReason()] ?? ''
                );
            }

            $taxSchemeVat = $xml->createElementNS(
                    'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                    'cac:TaxScheme'
            );
            $this->addElement($xml, $taxSchemeVat, 'cbc:ID', 'VAT');
            $taxCategory->appendChild($taxSchemeVat);
            $taxSubtotal->appendChild($taxCategory);
            $taxTotal->appendChild($taxSubtotal);
            $invoice->appendChild($taxTotal);
        }
    }

    // =========================================================================
    // Totaux monétaires
    // =========================================================================

    private function addMonetaryTotals(\DOMDocument $xml, \DOMElement $invoice): void {
        $lmt = $xml->createElementNS(
                'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                'cac:LegalMonetaryTotal'
        );

        $this->addAmountElement($xml, $lmt, 'cbc:LineExtensionAmount', $this->invoice->getSumOfLineNetAmounts());
        $this->addAmountElement($xml, $lmt, 'cbc:TaxExclusiveAmount', $this->invoice->getTaxExclusiveAmount());
        $this->addAmountElement($xml, $lmt, 'cbc:TaxInclusiveAmount', $this->invoice->getTaxInclusiveAmount());

        if ($this->invoice->getSumOfAllowances() > 0.0) {
            $this->addAmountElement($xml, $lmt, 'cbc:AllowanceTotalAmount', $this->invoice->getSumOfAllowances());
        }
        if ($this->invoice->getSumOfCharges() > 0.0) {
            $this->addAmountElement($xml, $lmt, 'cbc:ChargeTotalAmount', $this->invoice->getSumOfCharges());
        }
        if ($this->invoice->getPrepaidAmount() > 0.0) {
            $this->addAmountElement($xml, $lmt, 'cbc:PrepaidAmount', $this->invoice->getPrepaidAmount());
        }

        $this->addAmountElement($xml, $lmt, 'cbc:PayableAmount', $this->invoice->getPayableAmount());

        $invoice->appendChild($lmt);
    }

    // =========================================================================
    // Lignes
    // =========================================================================

    private function addInvoiceLines(\DOMDocument $xml, \DOMElement $invoice): void {
        foreach ($this->invoice->getInvoiceLines() as $line) {
            $invoiceLine = $xml->createElementNS(
                    'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                    'cac:InvoiceLine'
            );

            $this->addElement($xml, $invoiceLine, 'cbc:ID', $line->getId());

            // BT-132 — Référence de ligne de commande
            if ($line->getOrderLineReference()) {
                $orderLineRef = $xml->createElementNS(
                        'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                        'cac:OrderLineReference'
                );
                $this->addElement($xml, $orderLineRef, 'cbc:LineID', $line->getOrderLineReference());
                $invoiceLine->appendChild($orderLineRef);
            }

            // BT-127 — Note de ligne
            if ($line->getLineNote()) {
                $this->addElement($xml, $invoiceLine, 'cbc:Note', $line->getLineNote());
            }

            // BG-26 — Période de facturation de ligne
            if ($line->getLinePeriodStartDate() !== null || $line->getLinePeriodEndDate() !== null) {
                $linePeriod = $xml->createElementNS(
                        'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                        'cac:InvoicePeriod'
                );
                if ($line->getLinePeriodStartDate()) {
                    $this->addElement($xml, $linePeriod, 'cbc:StartDate', $line->getLinePeriodStartDate());
                }
                if ($line->getLinePeriodEndDate()) {
                    $this->addElement($xml, $linePeriod, 'cbc:EndDate', $line->getLinePeriodEndDate());
                }
                $invoiceLine->appendChild($linePeriod);
            }

            $quantity = $xml->createElementNS(
                    'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                    'cbc:InvoicedQuantity',
                    (string) $line->getQuantity()
            );
            $quantity->setAttribute('unitCode', $line->getUnitCode());
            $invoiceLine->appendChild($quantity);

            $this->addAmountElement($xml, $invoiceLine, 'cbc:LineExtensionAmount', $line->getLineAmount());

            // BG-28 — Remises et majorations au niveau ligne
            foreach ($line->getLineAllowanceCharges() as $lac) {
                $lacElem = $xml->createElementNS(
                        'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                        'cac:AllowanceCharge'
                );

                $ci = $xml->createElementNS(
                        'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                        'cbc:ChargeIndicator',
                        $lac->getChargeIndicator() ? 'true' : 'false'
                );
                $lacElem->appendChild($ci);

                if ($lac->getReasonCode()) {
                    $this->addElement($xml, $lacElem, 'cbc:AllowanceChargeReasonCode', $lac->getReasonCode());
                }
                if ($lac->getReason()) {
                    $this->addElement($xml, $lacElem, 'cbc:AllowanceChargeReason', $lac->getReason());
                }
                if ($lac->getMultiplierFactorNumeric() !== null) {
                    $this->addElement($xml, $lacElem, 'cbc:MultiplierFactorNumeric',
                            number_format($lac->getMultiplierFactorNumeric(), 2, '.', ''));
                }

                $amtElem = $xml->createElementNS(
                        'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                        'cbc:Amount',
                        number_format($lac->getAmount(), 2, '.', '')
                );
                $amtElem->setAttribute('currencyID', $this->invoice->getDocumentCurrencyCode());
                $lacElem->appendChild($amtElem);

                if ($lac->getBaseAmount() !== null) {
                    $baseElem = $xml->createElementNS(
                            'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                            'cbc:BaseAmount',
                            number_format($lac->getBaseAmount(), 2, '.', '')
                    );
                    $baseElem->setAttribute('currencyID', $this->invoice->getDocumentCurrencyCode());
                    $lacElem->appendChild($baseElem);
                }

                // TaxCategory de la remise/majoration de ligne
                $lacTaxCat = $xml->createElementNS(
                        'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                        'cac:TaxCategory'
                );
                $this->addElement($xml, $lacTaxCat, 'cbc:ID', $lac->getVatCategory());
                $this->addElement($xml, $lacTaxCat, 'cbc:Percent',
                        number_format($lac->getVatRate(), 2, '.', ''));
                $lacTaxScheme = $xml->createElementNS(
                        'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                        'cac:TaxScheme'
                );
                $this->addElement($xml, $lacTaxScheme, 'cbc:ID', 'VAT');
                $lacTaxCat->appendChild($lacTaxScheme);
                $lacElem->appendChild($lacTaxCat);

                $invoiceLine->appendChild($lacElem);
            }

            $item = $xml->createElementNS(
                    'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                    'cac:Item'
            );
            if ($line->getDescription()) {
                $this->addElement($xml, $item, 'cbc:Description', $line->getDescription());
            }
            $this->addElement($xml, $item, 'cbc:Name', $line->getName());

            $classifiedTaxCategory = $xml->createElementNS(
                    'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                    'cac:ClassifiedTaxCategory'
            );
            $this->addElement($xml, $classifiedTaxCategory, 'cbc:ID', $line->getVatCategory());
            $this->addElement($xml, $classifiedTaxCategory, 'cbc:Percent',
                    number_format($line->getVatRate(), 2, '.', ''));
            $lineTaxScheme = $xml->createElementNS(
                    'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                    'cac:TaxScheme'
            );
            $this->addElement($xml, $lineTaxScheme, 'cbc:ID', 'VAT');
            $classifiedTaxCategory->appendChild($lineTaxScheme);
            $item->appendChild($classifiedTaxCategory);
            $invoiceLine->appendChild($item);

            $price = $xml->createElementNS(
                    'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                    'cac:Price'
            );
            $this->addAmountElement($xml, $price, 'cbc:PriceAmount', $line->getUnitPrice());
            $invoiceLine->appendChild($price);

            $invoice->appendChild($invoiceLine);
        }
    }

    // =========================================================================
    // Helpers DOM
    // =========================================================================

    private function addElement(\DOMDocument $xml, \DOMElement $parent, string $name, string $value): void {
        $namespace = str_starts_with($name, 'cbc:') ? 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2' : 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';

        $element = $xml->createElementNS($namespace, $name, htmlspecialchars($value, ENT_XML1, 'UTF-8'));
        $parent->appendChild($element);
    }

    private function addAmountElement(\DOMDocument $xml, \DOMElement $parent, string $name, float $amount): void {
        $element = $xml->createElementNS(
                'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                $name,
                number_format($amount, 2, '.', '')
        );
        $element->setAttribute('currencyID', $this->invoice->getDocumentCurrencyCode());
        $parent->appendChild($element);
    }

    // =========================================================================
    // Sauvegarde fichier
    // =========================================================================

    public function saveToFile(string $filepath): bool {
        try {
            return file_put_contents($filepath, $this->toUbl21()) !== false;
        } catch (\Exception $e) {
            return false;
        }
    }

    // =========================================================================
    // Schematron (inchangé)
    // =========================================================================

    private bool $enableSchematronValidation = false;
    private array $schematronLevels = ['ublbe', 'en16931'];

    public function enableSchematronValidation(bool $enable = true, array $levels = ['ublbe', 'en16931']): self {
        $this->enableSchematronValidation = $enable;
        $this->schematronLevels = $levels;
        return $this;
    }
}
