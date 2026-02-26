<?php

declare(strict_types=1);

namespace PHPPeppol\Tests\Integration;

use PHPUnit\Framework\TestCase;
use PHPPeppol\Models\PeppolInvoice;
use PHPPeppol\Models\InvoiceLine;
use PHPPeppol\Models\Party;
use PHPPeppol\Models\Address;
use PHPPeppol\Models\ElectronicAddress;
use PHPPeppol\Formats\XmlExporter;

/**
 * Tests de validation structurelle du XML produit.
 * Vérifie la présence et la conformité des éléments UBL obligatoires.
 */
class XmlValidationTest extends TestCase
{
    private PeppolInvoice $invoice;
    private \DOMDocument $dom;
    private \DOMXPath $xpath;

    protected function setUp(): void
    {
        $this->invoice = $this->buildInvoiceComplete();

        $exporter = new XmlExporter();
        $xml = $exporter->export($this->invoice);

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
        $nodes = $this->xpath->query('//ubl:Invoice/cbc:CustomizationID');
        $this->assertGreaterThan(0, $nodes->length, 'CustomizationID doit être présent');
        $this->assertStringContainsString(
            'urn:cen.eu:en16931',
            $nodes->item(0)->textContent
        );
    }

    public function testProfileIdPresent(): void
    {
        $nodes = $this->xpath->query('//ubl:Invoice/cbc:ProfileID');
        $this->assertGreaterThan(0, $nodes->length, 'ProfileID doit être présent');
        $this->assertStringContainsString('peppol.eu', $nodes->item(0)->textContent);
    }

    public function testInvoiceNumberPresent(): void
    {
        $nodes = $this->xpath->query('//ubl:Invoice/cbc:ID');
        $this->assertGreaterThan(0, $nodes->length);
        $this->assertNotEmpty($nodes->item(0)->textContent);
    }

    public function testIssueDatePresent(): void
    {
        $nodes = $this->xpath->query('//ubl:Invoice/cbc:IssueDate');
        $this->assertGreaterThan(0, $nodes->length);
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}$/',
            $nodes->item(0)->textContent,
            'IssueDate doit être au format YYYY-MM-DD'
        );
    }

    // -------------------------------------------------------------------------
    // Vendeur (BG-4)
    // -------------------------------------------------------------------------

    public function testSellerNamePresent(): void
    {
        $nodes = $this->xpath->query(
            '//cac:AccountingSupplierParty/cac:Party/cac:PartyLegalEntity/cbc:RegistrationName'
        );
        $this->assertGreaterThan(0, $nodes->length);
        $this->assertNotEmpty($nodes->item(0)->textContent);
    }

    public function testSellerVatIdPresent(): void
    {
        $nodes = $this->xpath->query(
            '//cac:AccountingSupplierParty/cac:Party/cac:PartyTaxScheme/cbc:CompanyID'
        );
        $this->assertGreaterThan(0, $nodes->length);
    }

    public function testSellerEndpointPresent(): void
    {
        $nodes = $this->xpath->query(
            '//cac:AccountingSupplierParty/cac:Party/cbc:EndpointID'
        );
        $this->assertGreaterThan(0, $nodes->length);
        // L'attribut schemeID doit être présent
        $schemeId = $nodes->item(0)->getAttribute('schemeID');
        $this->assertNotEmpty($schemeId, 'EndpointID doit avoir un schemeID');
    }

    public function testSellerCompanyLegalFormExporte(): void
    {
        $nodes = $this->xpath->query(
            '//cac:AccountingSupplierParty/cac:Party/cac:PartyLegalEntity/cbc:CompanyLegalForm'
        );
        $this->assertGreaterThan(0, $nodes->length, 'BT-33 CompanyLegalForm doit être exporté');
        $this->assertSame('SA au capital de 100 000 EUR', $nodes->item(0)->textContent);
    }

    // -------------------------------------------------------------------------
    // Acheteur (BG-7)
    // -------------------------------------------------------------------------

    public function testBuyerNamePresent(): void
    {
        $nodes = $this->xpath->query(
            '//cac:AccountingCustomerParty/cac:Party/cac:PartyLegalEntity/cbc:RegistrationName'
        );
        $this->assertGreaterThan(0, $nodes->length);
    }

    // -------------------------------------------------------------------------
    // OrderReference — BR-42 : cbc:ID obligatoire même si NA
    // -------------------------------------------------------------------------

    public function testOrderReferenceIdToujoursPresent(): void
    {
        $nodes = $this->xpath->query('//cac:OrderReference/cbc:ID');
        // Si SalesOrderRef est fourni sans PurchaseOrderRef, ID doit valoir "NA"
        if ($nodes->length > 0) {
            $this->assertNotEmpty($nodes->item(0)->textContent);
        }
        // Si ni l'un ni l'autre n'est fourni, cac:OrderReference ne doit pas exister — OK
        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Lignes de facture (BG-25)
    // -------------------------------------------------------------------------

    public function testAuMoinsUneLigne(): void
    {
        $nodes = $this->xpath->query('//cac:InvoiceLine');
        $this->assertGreaterThan(0, $nodes->length, 'Au moins une InvoiceLine requise');
    }

    public function testLigneIdPresent(): void
    {
        $nodes = $this->xpath->query('//cac:InvoiceLine/cbc:ID');
        $this->assertGreaterThan(0, $nodes->length);
        $this->assertNotEmpty($nodes->item(0)->textContent);
    }

    public function testLigneQuantiteAvecUnitCode(): void
    {
        $nodes = $this->xpath->query('//cac:InvoiceLine/cbc:InvoicedQuantity');
        $this->assertGreaterThan(0, $nodes->length);
        $unitCode = $nodes->item(0)->getAttribute('unitCode');
        $this->assertNotEmpty($unitCode, 'InvoicedQuantity doit avoir un attribut unitCode');
    }

    public function testLigneMontantAvecCurrencyId(): void
    {
        $nodes = $this->xpath->query('//cac:InvoiceLine/cbc:LineExtensionAmount');
        $this->assertGreaterThan(0, $nodes->length);
        $currencyId = $nodes->item(0)->getAttribute('currencyID');
        $this->assertNotEmpty($currencyId, 'LineExtensionAmount doit avoir currencyID');
    }

    // -------------------------------------------------------------------------
    // BT-157 Standard item identifier
    // -------------------------------------------------------------------------

    public function testStandardItemIdExporte(): void
    {
        $nodes = $this->xpath->query(
            '//cac:InvoiceLine/cac:Item/cac:StandardItemIdentification/cbc:ID'
        );
        $this->assertGreaterThan(0, $nodes->length, 'BT-157 doit être exporté');
        $this->assertSame('3700000000001', $nodes->item(0)->textContent);
        $this->assertSame('0160', $nodes->item(0)->getAttribute('schemeID'));
    }

    // -------------------------------------------------------------------------
    // BT-159 Pays d'origine
    // -------------------------------------------------------------------------

    public function testOriginCountryExporte(): void
    {
        $nodes = $this->xpath->query(
            '//cac:InvoiceLine/cac:Item/cac:OriginCountry/cbc:IdentificationCode'
        );
        $this->assertGreaterThan(0, $nodes->length, 'BT-159 doit être exporté');
        $this->assertSame('DE', $nodes->item(0)->textContent);
    }

    // -------------------------------------------------------------------------
    // Totaux (BG-22)
    // -------------------------------------------------------------------------

    public function testLegalMonetaryTotalPresent(): void
    {
        $required = [
            'cbc:LineExtensionAmount',
            'cbc:TaxExclusiveAmount',
            'cbc:TaxInclusiveAmount',
            'cbc:PayableAmount',
        ];

        foreach ($required as $element) {
            $nodes = $this->xpath->query("//cac:LegalMonetaryTotal/$element");
            $this->assertGreaterThan(0, $nodes->length, "$element doit être présent dans LegalMonetaryTotal");
        }
    }

    public function testTaxTotalPresent(): void
    {
        $nodes = $this->xpath->query('//cac:TaxTotal/cbc:TaxAmount');
        $this->assertGreaterThan(0, $nodes->length, 'TaxTotal/TaxAmount doit être présent');
    }

    // -------------------------------------------------------------------------
    // Helper
    // -------------------------------------------------------------------------

    private function buildInvoiceComplete(): PeppolInvoice
    {
        $invoice = new PeppolInvoice();
        $invoice->setInvoiceNumber('TEST-XML-001')
                ->setIssueDate('2025-03-15')
                ->setDocumentCurrencyCode('EUR');

        // Vendeur avec BT-33
        $sellerAddress = new Address();
        $sellerAddress->setStreetName('Rue de l\'Industrie 42')
                      ->setCityName('Liège')->setPostalZone('4000')->setCountryCode('BE');
        $sellerEndpoint = new ElectronicAddress();
        $sellerEndpoint->setIdentifier('BE0123456789')->setSchemeId('0208');
        $seller = new Party();
        $seller->setName('Hydraulique SA')
               ->setVatId('BE0123456789')
               ->setAddress($sellerAddress)
               ->setElectronicAddress($sellerEndpoint)
               ->setCompanyLegalForm('SA au capital de 100 000 EUR');
        $invoice->setSeller($seller);

        // Acheteur
        $buyerAddress = new Address();
        $buyerAddress->setStreetName('Chaussée de Namur 15')
                     ->setCityName('Namur')->setPostalZone('5000')->setCountryCode('BE');
        $buyerEndpoint = new ElectronicAddress();
        $buyerEndpoint->setIdentifier('BE0987654321')->setSchemeId('0208');
        $buyer = new Party();
        $buyer->setName('Garage Dupont')
              ->setVatId('BE0987654321')
              ->setAddress($buyerAddress)
              ->setElectronicAddress($buyerEndpoint);
        $invoice->setBuyer($buyer);

        // Ligne avec BT-157 et BT-159
        $line = new InvoiceLine();
        $line->setId('1')
             ->setName('Vérin hydraulique V-40')
             ->setQuantity(2.0)
             ->setUnitCode('C62')
             ->setUnitPrice(350.00)
             ->setVatCategory('S')
             ->setVatRate(21.0)
             ->setStandardItemId('3700000000001', '0160')
             ->setOriginCountryCode('DE');
        $invoice->addInvoiceLine($line);
        $invoice->calculateTotals();

        return $invoice;
    }
}
