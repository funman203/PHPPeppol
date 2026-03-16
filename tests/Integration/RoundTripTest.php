<?php

declare(strict_types=1);

namespace Peppol\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Peppol\Tests\InvoiceTestHelpers;
use Peppol\Models\InvoiceLine;
use Peppol\Models\AllowanceCharge;
use Peppol\Models\PaymentInfo;
use Peppol\Formats\XmlExporter;

/**
 * Tests d'intégration : round-trip PHP → XML → PHP.
 *
 * Flux : PeppolInvoice → XmlExporter::toUbl21() → PeppolInvoice::fromXml()
 * Vérifie que chaque champ exporté est identique après réimport.
 */
class RoundTripTest extends TestCase
{
    use InvoiceTestHelpers;

    // =========================================================================
    // Facture minimale
    // =========================================================================

    public function testRoundTripChampsRacine(): void
    {
        $original = $this->buildInvoiceBase();
        $imported = $this->roundTrip($original);

        $this->assertSame($original->getInvoiceNumber(), $imported->getInvoiceNumber(), 'BT-1');
        $this->assertSame($original->getIssueDate(), $imported->getIssueDate(), 'BT-2');
        $this->assertSame($original->getDocumentCurrencyCode(), $imported->getDocumentCurrencyCode(), 'BT-5');
        $this->assertSame($original->getDueDate(), $imported->getDueDate(), 'BT-9');
    }

    // =========================================================================
    // Références document
    // =========================================================================

    public function testRoundTripReferences(): void
    {
        $original = $this->buildInvoiceBase();
        $original->setPurchaseOrderReference('PO-2025-001');
        $original->setSalesOrderReference('SO-555');
        $original->setContractReference('CONTRAT-XY');
        $original->setProjectReference('PROJ-42');
        $original->setBuyerReference('REF-ACHETEUR');
        $original->setPrecedingInvoiceReference('INV-2024-099', '2024-11-15');

        $imported = $this->roundTrip($original);

        $this->assertSame('PO-2025-001', $imported->getPurchaseOrderReference(), 'BT-13');
        $this->assertSame('SO-555', $imported->getSalesOrderReference(), 'BT-14');
        $this->assertSame('CONTRAT-XY', $imported->getContractReference(), 'BT-12');
        $this->assertSame('PROJ-42', $imported->getProjectReference(), 'BT-11');
        $this->assertSame('REF-ACHETEUR', $imported->getBuyerReference(), 'BT-10');
        $this->assertSame('INV-2024-099', $imported->getPrecedingInvoiceNumber(), 'BT-25');
        $this->assertSame('2024-11-15', $imported->getPrecedingInvoiceDate(), 'BT-26');
    }

    // =========================================================================
    // BG-14 — Période de facturation header
    // =========================================================================

    public function testRoundTripInvoicePeriod(): void
    {
        $original = $this->buildInvoiceBase();
        $original->setInvoicePeriod('2025-02-01', '2025-02-28');

        $imported = $this->roundTrip($original);

        $this->assertSame('2025-02-01', $imported->getInvoicePeriodStartDate(), 'BT-73');
        $this->assertSame('2025-02-28', $imported->getInvoicePeriodEndDate(), 'BT-74');
    }

    // =========================================================================
    // Lignes de facture — champs de base
    // =========================================================================

    public function testRoundTripLignesBase(): void
    {
        $original = $this->buildInvoiceBase();
        $line = new InvoiceLine();
        $line->setId('1')
             ->setName('Pompe hydraulique HPX-200')
             ->setDescription('Pompe à engrenages haute pression 200 bar')
             ->setQuantity(3.0)
             ->setUnitCode('C62')
             ->setUnitPrice(450.00)
             ->setVatCategory('S')
             ->setVatRate(21.0);
        $original->addInvoiceLine($line);

        $imported = $this->roundTrip($original);
        $lines = $imported->getInvoiceLines();

        $this->assertCount(1, $lines);
        $il = $lines[0];
        $this->assertSame('1', $il->getId(), 'BT-126');
        $this->assertSame('Pompe hydraulique HPX-200', $il->getName(), 'BT-153');
        $this->assertEqualsWithDelta(3.0, $il->getQuantity(), 0.001, 'BT-129');
        $this->assertSame('C62', $il->getUnitCode(), 'BT-130');
        $this->assertEqualsWithDelta(450.00, $il->getUnitPrice(), 0.01, 'BT-146');
        $this->assertSame('S', $il->getVatCategory(), 'BT-151');
        $this->assertEqualsWithDelta(21.0, $il->getVatRate(), 0.01, 'BT-152');
    }

    public function testRoundTripLigneNoteEtReference(): void
    {
        $original = $this->buildInvoiceBase();
        $line = $this->makeLine('1', 1.0, 200.00);
        $line->setLineNote('Ticket #4521 — intervention du 15/01');
        $line->setOrderLineReference('PO-LINE-3');
        $original->addInvoiceLine($line);

        $imported = $this->roundTrip($original);
        $il = $imported->getInvoiceLines()[0];

        $this->assertSame('Ticket #4521 — intervention du 15/01', $il->getLineNote(), 'BT-127');
        $this->assertSame('PO-LINE-3', $il->getOrderLineReference(), 'BT-132');
    }

    public function testRoundTripLignePeriode(): void
    {
        $original = $this->buildInvoiceBase();
        $line = $this->makeLine('1', 1.0, 99.00);
        $line->setLinePeriod('2025-03-01', '2025-03-31');
        $original->addInvoiceLine($line);

        $imported = $this->roundTrip($original);
        $il = $imported->getInvoiceLines()[0];

        $this->assertSame('2025-03-01', $il->getLinePeriodStartDate(), 'BT-134');
        $this->assertSame('2025-03-31', $il->getLinePeriodEndDate(), 'BT-135');
    }

    // =========================================================================
    // BT-155/156/157/158/159 — Identifiants et classification article
    // =========================================================================

    public function testRoundTripIdentifiantsArticle(): void
    {
        $original = $this->buildInvoiceBase();
        $line = $this->makeLine('1', 2.0, 320.00);
        $line->setSellerItemId('VERIN-V40')
             ->setBuyerItemId('ART-BUYER-999')
             ->setStandardItemId('3700000040001', '0160')
             ->setItemClassificationCode('23152000', 'STI')
             ->setOriginCountryCode('DE');
        $original->addInvoiceLine($line);

        $imported = $this->roundTrip($original);
        $il = $imported->getInvoiceLines()[0];

        $this->assertSame('VERIN-V40', $il->getSellerItemId(), 'BT-155');
        $this->assertSame('ART-BUYER-999', $il->getBuyerItemId(), 'BT-156');
        $this->assertSame('3700000040001', $il->getStandardItemId(), 'BT-157');
        $this->assertSame('0160', $il->getStandardItemSchemeId(), 'BT-157 schemeID');
        $this->assertSame('23152000', $il->getItemClassificationCode(), 'BT-158');
        $this->assertSame('DE', $il->getOriginCountryCode(), 'BT-159');
    }

    // =========================================================================
    // BT-33 — Capital social
    // =========================================================================

    public function testRoundTripCompanyLegalForm(): void
    {
        $original = $this->buildInvoiceBase();
        $original->getSeller()->setCompanyLegalForm('SA au capital de 125 000 EUR');

        $imported = $this->roundTrip($original);

        $this->assertSame(
            'SA au capital de 125 000 EUR',
            $imported->getSeller()->getCompanyLegalForm(),
            'BT-33'
        );
    }

    // =========================================================================
    // Remises et majorations
    // =========================================================================

    public function testRoundTripRemiseDocument(): void
    {
        $original = $this->buildInvoiceBase();
        $original->addAllowance(
            AllowanceCharge::createAllowance(50.00, 'S', 21.0, 'Remise fidélité', '95')
        );

        $imported = $this->roundTrip($original);
        $allowances = array_values(array_filter(
            $imported->getAllowanceCharges(),
            fn($ac) => $ac->isAllowance()
        ));

        $this->assertCount(1, $allowances);
        $this->assertEqualsWithDelta(50.00, $allowances[0]->getAmount(), 0.01);
        $this->assertSame('Remise fidélité', $allowances[0]->getReason());
    }

    public function testRoundTripRemiseLigne(): void
    {
        $original = $this->buildInvoiceBase();
        $line = $this->makeLine('1', 10.0, 100.00);
        $line->addAllowanceCharge(AllowanceCharge::createAllowance(50.00, 'S', 21.0, 'Remise volume'));
        $original->addInvoiceLine($line);

        $imported = $this->roundTrip($original);
        // 10×100 - 50 = 950
        $this->assertEqualsWithDelta(950.00, $imported->getInvoiceLines()[0]->getLineAmount(), 0.01);
    }

    // =========================================================================
    // Totaux après round-trip
    // =========================================================================

    public function testRoundTripTotauxCoherents(): void
    {
        $original = $this->buildInvoiceBase();
        $original->addInvoiceLine($this->makeLine('1', 5.0, 100.00)); // 500
        $original->addInvoiceLine($this->makeLine('2', 2.0, 250.00)); // 500
        $original->calculateTotals();

        $imported = $this->roundTrip($original);
        $imported->calculateTotals();

        $this->assertEqualsWithDelta(1000.00, $imported->getTaxExclusiveAmount(), 0.01, 'HT');
        $this->assertEqualsWithDelta(210.00, $imported->getTotalVatAmount(), 0.01, 'TVA');
        $this->assertEqualsWithDelta(1210.00, $imported->getTaxInclusiveAmount(), 0.01, 'TTC');
    }

    // =========================================================================
    // Paiement
    // =========================================================================

    public function testRoundTripPaiementVirement(): void
    {
        $original = $this->buildInvoiceBase();
        $payment = new PaymentInfo();
        $payment->setPaymentMeansCode('30')
                ->setIban('BE71096123456769')
                ->setBic('GKCCBEBB')
                ->setPaymentReference('INV-2025-001');
        $original->setPaymentInfo($payment);

        $imported = $this->roundTrip($original);
        $pi = $imported->getPaymentInfo();

        $this->assertSame('BE71096123456769', $pi->getIban());
        $this->assertSame('GKCCBEBB', $pi->getBic());
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function buildInvoiceBase(): \PeppolInvoice
    {
        $invoice = new \PeppolInvoice('INV-TEST-001', '2025-03-15', '380', 'EUR');
        $invoice->setDueDate('2025-04-15');

        $invoice->setSeller($this->makeParty(
            'Hydraulique Industrielle SA', 'BE0123456789',
            "Rue de l'Industrie 42", 'Liège', '4000', 'BE'
        ));
        $invoice->setBuyer($this->makeParty(
            'Garage Dupont SPRL', 'BE0987654321',
            'Chaussée de Namur 15', 'Namur', '5000', 'BE'
        ));

        // Une ligne par défaut pour que la facture passe la validation
        $invoice->addInvoiceLine($this->makeLine('1', 1.0, 100.00));
        $invoice->calculateTotals();

        return $invoice;
    }

    /**
     * Exporte la facture en XML via XmlExporter::toUbl21(),
     * puis la réimporte via PeppolInvoice::fromXml().
     */
    private function roundTrip(\PeppolInvoice $invoice): \PeppolInvoice
    {
        $exporter = new XmlExporter($invoice);
        $xml = $exporter->toUbl21();

        $this->assertNotEmpty($xml, 'XML exporté ne doit pas être vide');

        $dom = new \DOMDocument();
        $this->assertTrue($dom->loadXML($xml), 'Le XML exporté doit être bien formé');

        $imported = \PeppolInvoice::fromXml($xml, strict: false);
        $this->assertInstanceOf(\PeppolInvoice::class, $imported);

        return $imported;
    }
}
