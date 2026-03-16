<?php

declare(strict_types=1);

namespace Peppol\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Peppol\Tests\InvoiceTestHelpers;
use Peppol\Models\InvoiceLine;
use Peppol\Models\Party;
use Peppol\Models\Address;
use Peppol\Models\ElectronicAddress;
use Peppol\Formats\XmlExporter;

/**
 * Tests de validation structurelle du XML produit par XmlExporter::toUbl21().
 */
class XmlValidationTest extends TestCase
{
    use InvoiceTestHelpers;

    private \DOMDocument $dom;
    private \DOMXPath $xpath;

    protected function setUp(): void
    {
        $invoice = $this->buildInvoiceComplete();
        $exporter = new XmlExporter($invoice);
        $xml = $exporter->toUbl21();

        $this->dom = new \DOMDocument();
        $this->dom->loadXML($xml);

        $this->xpath = new \DOMXPath($this->dom);
        $this->xpath->registerNamespace('ubl', 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2');
        $this->xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $this->xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
    }

    // -------------------------------------------------------------------------
    // Éléments racine obligatoires
    // -------------------------------------------------------------------------

    public function testCustomizationIdPresent(): void
    {
        $val = $this->xpathText('//ubl:Invoice/cbc:CustomizationID');
        $this->assertStringContainsString('urn:cen.eu:en16931', $val);
    }

    public function testProfileIdPresent(): void
    {
        $val = $this->xpathText('//ubl:Invoice/cbc:ProfileID');
        $this->assertStringContainsString('peppol.eu', $val);
    }

    public function testInvoiceNumberPresent(): void
    {
        $this->assertNotEmpty($this->xpathText('//ubl:Invoice/cbc:ID'));
    }

    public function testIssueDateFormatYYYYMMDD(): void
    {
        $val = $this->xpathText('//ubl:Invoice/cbc:IssueDate');
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $val);
    }

    // -------------------------------------------------------------------------
    // Vendeur (BG-4)
    // -------------------------------------------------------------------------

    public function testSellerRegistrationNamePresent(): void
    {
        $val = $this->xpathText(
            '//cac:AccountingSupplierParty/cac:Party/cac:PartyLegalEntity/cbc:RegistrationName'
        );
        $this->assertNotEmpty($val);
    }

    public function testSellerEndpointAvecSchemeId(): void
    {
        $nodes = $this->xpath->query('//cac:AccountingSupplierParty/cac:Party/cbc:EndpointID');
        $this->assertGreaterThan(0, $nodes->length);
        $this->assertNotEmpty($nodes->item(0)->getAttribute('schemeID'), 'schemeID obligatoire');
    }

    // -------------------------------------------------------------------------
    // BT-33 — CompanyLegalForm
    // -------------------------------------------------------------------------

    public function testBt33CompanyLegalFormExporte(): void
    {
        $val = $this->xpathText(
            '//cac:AccountingSupplierParty/cac:Party/cac:PartyLegalEntity/cbc:CompanyLegalForm'
        );
        $this->assertSame('SA au capital de 100 000 EUR', $val, 'BT-33');
    }

    public function testBt33AbsentCoteAcheteur(): void
    {
        $nodes = $this->xpath->query(
            '//cac:AccountingCustomerParty/cac:Party/cac:PartyLegalEntity/cbc:CompanyLegalForm'
        );
        $this->assertSame(0, $nodes->length, 'BT-33 ne doit pas apparaître côté acheteur');
    }

    // -------------------------------------------------------------------------
    // Lignes (BG-25)
    // -------------------------------------------------------------------------

    public function testAuMoinsUneLignePresente(): void
    {
        $nodes = $this->xpath->query('//cac:InvoiceLine');
        $this->assertGreaterThan(0, $nodes->length);
    }

    public function testLigneQuantiteAvecUnitCode(): void
    {
        $nodes = $this->xpath->query('//cac:InvoiceLine/cbc:InvoicedQuantity');
        $this->assertGreaterThan(0, $nodes->length);
        $this->assertNotEmpty($nodes->item(0)->getAttribute('unitCode'), 'unitCode obligatoire');
    }

    public function testLigneMontantAvecCurrencyId(): void
    {
        $nodes = $this->xpath->query('//cac:InvoiceLine/cbc:LineExtensionAmount');
        $this->assertGreaterThan(0, $nodes->length);
        $this->assertNotEmpty($nodes->item(0)->getAttribute('currencyID'), 'currencyID obligatoire');
    }

    // -------------------------------------------------------------------------
    // BT-157 — Identifiant standard article
    // -------------------------------------------------------------------------

    public function testBt157StandardItemIdExporte(): void
    {
        $nodes = $this->xpath->query(
            '//cac:InvoiceLine/cac:Item/cac:StandardItemIdentification/cbc:ID'
        );
        $this->assertGreaterThan(0, $nodes->length, 'BT-157 doit être présent');
        $this->assertSame('3700000000001', $nodes->item(0)->textContent);
        $this->assertSame('0160', $nodes->item(0)->getAttribute('schemeID'));
    }

    // -------------------------------------------------------------------------
    // BT-159 — Pays d'origine
    // -------------------------------------------------------------------------

    public function testBt159OriginCountryExporte(): void
    {
        $val = $this->xpathText(
            '//cac:InvoiceLine/cac:Item/cac:OriginCountry/cbc:IdentificationCode'
        );
        $this->assertSame('DE', $val, 'BT-159');
    }

    // -------------------------------------------------------------------------
    // Totaux (BG-22)
    // -------------------------------------------------------------------------

    public function testLegalMonetaryTotalObligatoires(): void
    {
        foreach ([
            'cbc:LineExtensionAmount',
            'cbc:TaxExclusiveAmount',
            'cbc:TaxInclusiveAmount',
            'cbc:PayableAmount',
        ] as $elem) {
            $nodes = $this->xpath->query("//cac:LegalMonetaryTotal/$elem");
            $this->assertGreaterThan(0, $nodes->length, "$elem manquant");
        }
    }

    public function testMontantsAvecCurrencyId(): void
    {
        $nodes = $this->xpath->query('//cac:LegalMonetaryTotal/cbc:TaxInclusiveAmount');
        $this->assertNotEmpty($nodes->item(0)->getAttribute('currencyID'));
    }

    public function testTaxTotalPresent(): void
    {
        $nodes = $this->xpath->query('//cac:TaxTotal/cbc:TaxAmount');
        $this->assertGreaterThan(0, $nodes->length);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function xpathText(string $query): string
    {
        $nodes = $this->xpath->query($query);
        if (!$nodes || $nodes->length === 0) {
            return '';
        }
        return $nodes->item(0)->textContent;
    }

    private function buildInvoiceComplete(): \PeppolInvoice
    {
        $invoice = new \PeppolInvoice('TEST-XML-001', '2025-03-15', '380', 'EUR');
        $invoice->setDueDate('2025-04-15');

        $seller = new Party(
            'Hydraulique SA',
            new Address("Rue de l'Industrie 42", 'Liège', '4000', 'BE'),
            'BE0123456789',
            null, null,
            new ElectronicAddress('0208', 'BE0123456789')
        );
        $seller->setCompanyLegalForm('SA au capital de 100 000 EUR');
        $invoice->setSeller($seller);

        $invoice->setBuyer(new Party(
            'Garage Dupont',
            new Address('Chaussée de Namur 15', 'Namur', '5000', 'BE'),
            'BE0987654321',
            null, null,
            new ElectronicAddress('0208', 'BE0987654321')
        ));

        $line = new InvoiceLine('1', 'Vérin hydraulique V-40', 2.0, 'C62', 350.00, 'S', 21.0);
        $line->setStandardItemId('3700000000001', '0160')
             ->setOriginCountryCode('DE');
        $invoice->addInvoiceLine($line);
        $invoice->calculateTotals();

        return $invoice;
    }
}
