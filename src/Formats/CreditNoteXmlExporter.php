<?php

declare(strict_types=1);

namespace Peppol\Formats;

/**
 * Exportateur XML UBL 2.1 — Note de crédit (Avoir)
 *
 * Réutilise toute la logique de XmlExporter (parties, TVA, totaux,
 * remises/majorations, documents joints, etc.) qui est strictement
 * identique entre Invoice et CreditNote dans le schéma UBL 2.1.
 *
 * Seuls diffèrent :
 *   - L'élément racine : cac:CreditNote (namespace ...CreditNote-2)
 *   - cbc:InvoiceTypeCode  → cbc:CreditNoteTypeCode
 *   - cac:InvoiceLine      → cac:CreditNoteLine
 *   - cbc:InvoicedQuantity → cbc:CreditedQuantity
 *
 * @package Peppol\Formats
 * @version 1.0
 * @link https://docs.peppol.eu/poacc/billing/3.0/bis/#_credit_note
 */
class CreditNoteXmlExporter extends XmlExporter
{
    protected string $rootElementName = 'CreditNote';
    protected string $rootNamespaceUri = 'urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2';
    protected string $documentTypeCodeElementName = 'cbc:CreditNoteTypeCode';
    protected string $lineElementName = 'cac:CreditNoteLine';
    protected string $quantityElementName = 'cbc:CreditedQuantity';
}
