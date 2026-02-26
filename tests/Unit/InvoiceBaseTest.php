<?php

declare(strict_types=1);

namespace Peppol\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Peppol\Models\Party;
use Peppol\Models\Address;
use Peppol\Models\ElectronicAddress;
use Peppol\Models\InvoiceLine;
use Peppol\Models\AllowanceCharge;

/**
 * Tests unitaires pour InvoiceBase (via PeppolInvoice).
 * Couvre : références, périodes header, totaux, remises/majorations document.
 *
 * PeppolInvoice est une classe globale (sans namespace) — on l'utilise avec \PeppolInvoice
 */
class InvoiceBaseTest extends TestCase
{
    private \PeppolInvoice $invoice;

    protected function setUp(): void
    {
        $this->invoice = new \PeppolInvoice('INV-2025-001', '2025-03-01');

        $address = new Address('Rue de la Loi 1', 'Bruxelles', '1000', 'BE');
        $endpoint = new ElectronicAddress('0208', 'BE0123456789');
        $seller = new Party('Société Test SA', $address, 'BE0123456789', null, null, $endpoint);
        $this->invoice->setSeller($seller);

        $address2 = new Address('Avenue Louise 1', 'Bruxelles', '1050', 'BE');
        $endpoint2 = new ElectronicAddress('0208', 'BE0987654321');
        $buyer = new Party('Client SARL', $address2, 'BE0987654321', null, null, $endpoint2);
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
    // BG-14 — Période de facturation header
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
        $this->invoice->addInvoiceLine($this->makeLine('1', 5.0, 100.00, 'S', 21.0));
        $this->invoice->calculateTotals();

        $this->assertEqualsWithDelta(500.00, $this->invoice->getSumOfLineNetAmounts(), 0.01);
        $this->assertEqualsWithDelta(500.00, $this->invoice->getTaxExclusiveAmount(), 0.01);
        $this->assertEqualsWithDelta(105.00, $this->invoice->getTotalVatAmount(), 0.01);
        $this->assertEqualsWithDelta(605.00, $this->invoice->getTaxInclusiveAmount(), 0.01);
        $this->assertEqualsWithDelta(605.00, $this->invoice->getPayableAmount(), 0.01);
    }

    public function testCalculTotauxAvecRemiseDocument(): void
    {
        $this->invoice->addInvoiceLine($this->makeLine('1', 1.0, 1000.00, 'S', 21.0));
        $this->invoice->addAllowanceCharge(
            AllowanceCharge::createAllowance(50.00, 'S', 21.0, 'Remise commerciale')
        );
        $this->invoice->calculateTotals();

        $this->assertEqualsWithDelta(950.00, $this->invoice->getTaxExclusiveAmount(), 0.01);
        $this->assertEqualsWithDelta(199.50, $this->invoice->getTotalVatAmount(), 0.01);
        $this->assertEqualsWithDelta(1149.50, $this->invoice->getTaxInclusiveAmount(), 0.01);
    }

    public function testCalculTotauxAvecMajorationDocument(): void
    {
        $this->invoice->addInvoiceLine($this->makeLine('1', 1.0, 500.00, 'S', 21.0));
        $this->invoice->addAllowanceCharge(
            AllowanceCharge::createCharge(25.00, 'S', 21.0, 'Transport')
        );
        $this->invoice->calculateTotals();

        $this->assertEqualsWithDelta(525.00, $this->invoice->getTaxExclusiveAmount(), 0.01);
    }

    public function testCalculTotauxMultiTVA(): void
    {
        $this->invoice->addInvoiceLine($this->makeLine('1', 1.0, 100.00, 'S', 21.0));
        $this->invoice->addInvoiceLine($this->makeLine('2', 1.0, 200.00, 'S', 6.0));
        $this->invoice->addInvoiceLine($this->makeLine('3', 1.0, 50.00, 'Z', 0.0));
        $this->invoice->calculateTotals();

        // TVA = 100×21% + 200×6% + 50×0% = 21 + 12 + 0 = 33
        $this->assertEqualsWithDelta(33.00, $this->invoice->getTotalVatAmount(), 0.01);
    }

    public function testPrepaidAmount(): void
    {
        $this->invoice->addInvoiceLine($this->makeLine('1', 1.0, 1000.00, 'S', 21.0));
        $this->invoice->setPrepaidAmount(500.00);
        $this->invoice->calculateTotals();

        // TTC = 1210, acompte = 500, restant = 710
        $this->assertEqualsWithDelta(710.00, $this->invoice->getPayableAmount(), 0.01);
    }

    // -------------------------------------------------------------------------
    // BT-33 — Capital social (CompanyLegalForm)
    // -------------------------------------------------------------------------

    public function testCompanyLegalForm(): void
    {
        $this->assertNull($this->invoice->getSeller()->getCompanyLegalForm());
        $this->invoice->getSeller()->setCompanyLegalForm('SA au capital de 125 000 EUR');
        $this->assertSame('SA au capital de 125 000 EUR', $this->invoice->getSeller()->getCompanyLegalForm());
    }

    // -------------------------------------------------------------------------
    // Helper
    // -------------------------------------------------------------------------

    private function makeLine(string $id, float $qty, float $price, string $vatCat, float $vatRate): InvoiceLine
    {
        return new InvoiceLine($id, "Article $id", $qty, 'C62', $price, $vatCat, $vatRate);
    }
}
