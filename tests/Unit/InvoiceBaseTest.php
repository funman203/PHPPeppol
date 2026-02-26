<?php

declare(strict_types=1);

namespace Peppol\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PHPPeppol\Models\PeppolInvoice;
use PHPPeppol\Models\InvoiceLine;
use PHPPeppol\Models\Party;
use PHPPeppol\Models\Address;
use PHPPeppol\Models\ElectronicAddress;
use PHPPeppol\Models\AllowanceCharge;

/**
 * Tests unitaires pour InvoiceBase (via PeppolInvoice).
 * Couvre : références, périodes header, totaux, remises/majorations document.
 */
class InvoiceBaseTest extends TestCase
{
    private PeppolInvoice $invoice;

    protected function setUp(): void
    {
        $this->invoice = new PeppolInvoice();
        $this->invoice
            ->setInvoiceNumber('INV-2025-001')
            ->setIssueDate('2025-03-01')
            ->setDocumentCurrencyCode('EUR');

        // Vendeur minimal
        $seller = $this->makeParty('Société Test SA', 'BE0123456789');
        $this->invoice->setSeller($seller);

        // Acheteur minimal
        $buyer = $this->makeParty('Client SARL', 'BE0987654321');
        $this->invoice->setBuyer($buyer);
    }

    // -------------------------------------------------------------------------
    // Références document
    // -------------------------------------------------------------------------

    public function testPurchaseOrderReference(): void
    {
        $this->invoice->setPurchaseOrderReference('PO-2025-999');
        $this->assertSame('PO-2025-999', $this->invoice->getPurchaseOrderReference());
    }

    public function testSalesOrderReference(): void
    {
        $this->invoice->setSalesOrderReference('SO-77');
        $this->assertSame('SO-77', $this->invoice->getSalesOrderReference());
    }

    public function testContractReference(): void
    {
        $this->invoice->setContractReference('CONTRAT-ABC');
        $this->assertSame('CONTRAT-ABC', $this->invoice->getContractReference());
    }

    public function testPrecedingInvoiceReference(): void
    {
        $this->invoice->setPrecedingInvoiceReference('INV-2024-100', '2024-12-01');

        $this->assertSame('INV-2024-100', $this->invoice->getPrecedingInvoiceNumber());
        $this->assertSame('2024-12-01', $this->invoice->getPrecedingInvoiceDate());
    }

    // -------------------------------------------------------------------------
    // BG-14 Période de facturation header
    // -------------------------------------------------------------------------

    public function testInvoicePeriodValide(): void
    {
        $this->invoice->setInvoicePeriod('2025-01-01', '2025-01-31');

        $this->assertSame('2025-01-01', $this->invoice->getInvoicePeriodStartDate());
        $this->assertSame('2025-01-31', $this->invoice->getInvoicePeriodEndDate());
    }

    public function testInvoicePeriodEndAvantStartLanceException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->invoice->setInvoicePeriod('2025-06-30', '2025-06-01');
    }

    public function testInvoicePeriodFormatInvalide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->invoice->setInvoicePeriod('2025/01/01', '2025/01/31');
    }

    // -------------------------------------------------------------------------
    // Calcul des totaux
    // -------------------------------------------------------------------------

    public function testCalculTotauxSimple(): void
    {
        $line = $this->makeLine('1', 5.0, 100.00, 'S', 21.0);
        $this->invoice->addInvoiceLine($line);
        $this->invoice->calculateTotals();

        // 5 × 100 = 500.00 HT
        $this->assertEqualsWithDelta(500.00, $this->invoice->getSumOfLineNetAmounts(), 0.01);
        $this->assertEqualsWithDelta(500.00, $this->invoice->getTaxExclusiveAmount(), 0.01);

        // 500 × 21% = 105.00 TVA
        $this->assertEqualsWithDelta(105.00, $this->invoice->getTotalVatAmount(), 0.01);

        // 500 + 105 = 605.00 TTC
        $this->assertEqualsWithDelta(605.00, $this->invoice->getTaxInclusiveAmount(), 0.01);
        $this->assertEqualsWithDelta(605.00, $this->invoice->getPayableAmount(), 0.01);
    }

    public function testCalculTotauxAvecRemiseDocument(): void
    {
        $line = $this->makeLine('1', 1.0, 1000.00, 'S', 21.0);
        $this->invoice->addInvoiceLine($line);

        // Remise document : 50€
        $allowance = AllowanceCharge::createAllowance(50.00, 'S', 21.0, 'Remise commerciale');
        $this->invoice->addAllowance($allowance);

        $this->invoice->calculateTotals();

        // HT = 1000 - 50 = 950
        $this->assertEqualsWithDelta(950.00, $this->invoice->getTaxExclusiveAmount(), 0.01);

        // TVA = 950 × 21% = 199.50
        $this->assertEqualsWithDelta(199.50, $this->invoice->getTotalVatAmount(), 0.01);

        // TTC = 950 + 199.50 = 1149.50
        $this->assertEqualsWithDelta(1149.50, $this->invoice->getTaxInclusiveAmount(), 0.01);
    }

    public function testCalculTotauxAvecMajorationDocument(): void
    {
        $line = $this->makeLine('1', 1.0, 500.00, 'S', 21.0);
        $this->invoice->addInvoiceLine($line);

        // Frais de transport : 25€
        $charge = AllowanceCharge::createCharge(25.00, 'S', 21.0, 'Transport');
        $this->invoice->addCharge($charge);

        $this->invoice->calculateTotals();

        // HT = 500 + 25 = 525
        $this->assertEqualsWithDelta(525.00, $this->invoice->getTaxExclusiveAmount(), 0.01);
    }

    public function testCalculTotauxMultiTVA(): void
    {
        // Ligne 1 : TVA 21%
        $line1 = $this->makeLine('1', 1.0, 100.00, 'S', 21.0);
        // Ligne 2 : TVA 6%
        $line2 = $this->makeLine('2', 1.0, 200.00, 'S', 6.0);
        // Ligne 3 : exonéré
        $line3 = $this->makeLine('3', 1.0, 50.00, 'Z', 0.0);

        $this->invoice->addInvoiceLine($line1);
        $this->invoice->addInvoiceLine($line2);
        $this->invoice->addInvoiceLine($line3);
        $this->invoice->calculateTotals();

        // TVA totale = 100×21% + 200×6% + 50×0% = 21 + 12 + 0 = 33
        $this->assertEqualsWithDelta(33.00, $this->invoice->getTotalVatAmount(), 0.01);

        // Vérifier le breakdown TVA
        $vatBreakdowns = $this->invoice->getVatBreakdowns();
        $this->assertCount(2, $vatBreakdowns); // Z à 0% avec montant TVA = 0 peut être exclu selon l'implémentation
    }

    public function testPrepaidAmount(): void
    {
        $line = $this->makeLine('1', 1.0, 1000.00, 'S', 21.0);
        $this->invoice->addInvoiceLine($line);
        $this->invoice->setPrepaidAmount(500.00);
        $this->invoice->calculateTotals();

        // TTC = 1210, acompte = 500, restant = 710
        $this->assertEqualsWithDelta(710.00, $this->invoice->getPayableAmount(), 0.01);
    }

    // -------------------------------------------------------------------------
    // BT-33 Capital social (CompanyLegalForm)
    // -------------------------------------------------------------------------

    public function testCompanyLegalForm(): void
    {
        $seller = $this->invoice->getSeller();
        $this->assertNull($seller->getCompanyLegalForm());

        $seller->setCompanyLegalForm('SA au capital de 125 000 EUR');
        $this->assertSame('SA au capital de 125 000 EUR', $seller->getCompanyLegalForm());
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeParty(string $name, string $vatId): Party
    {
        $address = new Address();
        $address->setStreetName('Rue de la Loi 1')
                ->setCityName('Bruxelles')
                ->setPostalZone('1000')
                ->setCountryCode('BE');

        $endpoint = new ElectronicAddress();
        $endpoint->setIdentifier('0088:' . ltrim($vatId, 'BE'))
                 ->setSchemeId('0088');

        $party = new Party();
        $party->setName($name)
              ->setVatId($vatId)
              ->setAddress($address)
              ->setElectronicAddress($endpoint);

        return $party;
    }

    private function makeLine(
        string $id,
        float $qty,
        float $price,
        string $vatCat,
        float $vatRate
    ): InvoiceLine {
        $line = new InvoiceLine();
        $line->setId($id)
             ->setName("Article $id")
             ->setQuantity($qty)
             ->setUnitCode('C62')
             ->setUnitPrice($price)
             ->setVatCategory($vatCat)
             ->setVatRate($vatRate);
        return $line;
    }
}
