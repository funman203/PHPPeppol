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
 * Flux : PeppolInvoice → XmlExporter::toUbl21() → PeppolInvoice::fromXml()
 */
class RoundTripTest extends TestCase
{
    use InvoiceTestHelpers;

    // =========================================================================
    // Champs racine
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
    // Lignes — champs de base
    // =========================================================================

    public function testRoundTripLignesBase(): void
    {
        $original = $this->buildInvoiceBase();
        $line = new InvoiceLine(
            '1', 'Pompe hydraulique HPX-200', 3.0, 'C62', 450.00, 'S', 21.0,
            'Pompe à engrenages haute pression 200 bar'
        );
        $original->addInvoiceLine($line);
        $original->calculateTotals();

        $imported = $this->roundTrip($original);
        $lines = $imported->getInvoiceLines();

        // La facture de base contient déjà 1 ligne — la nouvelle est en position 1
        $il = null;
        foreach ($lines as $l) {
            if ($l->getId() === '1' && $l->getName() === 'Pompe hydraulique HPX-200') {
                $il = $l;
                break;
            }
        }
        $this->assertNotNull($il, 'Ligne "Pompe hydraulique HPX-200" non trouvée');
        $this->assertSame('1', $il->getId(), 'BT-126');
        $this->assertEqualsWithDelta(3.0, $il->getQuantity(), 0.001, 'BT-129');
        $this->assertSame('C62', $il->getUnitCode(), 'BT-130');
        $this->assertEqualsWithDelta(450.00, $il->getUnitPrice(), 0.01, 'BT-146');
        $this->assertSame('S', $il->getVatCategory(), 'BT-151');
        $this->assertEqualsWithDelta(21.0, $il->getVatRate(), 0.01, 'BT-152');
    }

    public function testRoundTripLigneNoteEtReference(): void
    {
        $original = new \PeppolInvoice('INV-NOTE-001', '2025-03-15');
        $original->setDueDate('2025-04-15');
        $original->setSeller($this->makeParty('Vendeur SA', 'BE0123456789'));
        $original->setBuyer($this->makeParty('Acheteur SPRL', 'BE0987654321'));

        $line = new InvoiceLine('1', 'Service maintenance', 1.0, 'C62', 200.00, 'S', 21.0);
        $line->setLineNote('Intervention du 15/01/2025 - Ticket #4521');
        $line->setOrderLineReference('PO-LINE-3');
        $original->addInvoiceLine($line);
        $original->calculateTotals();

        $imported = $this->roundTrip($original);
        $il = $imported->getInvoiceLines()[0];

        $this->assertSame('Intervention du 15/01/2025 - Ticket #4521', $il->getLineNote(), 'BT-127');
        $this->assertSame('PO-LINE-3', $il->getOrderLineReference(), 'BT-132');
    }

    public function testRoundTripLignePeriode(): void
    {
        $original = new \PeppolInvoice('INV-PERIOD-001', '2025-03-15');
        $original->setDueDate('2025-04-15');
        $original->setSeller($this->makeParty('Vendeur SA', 'BE0123456789'));
        $original->setBuyer($this->makeParty('Acheteur SPRL', 'BE0987654321'));

        $line = new InvoiceLine('1', 'Abonnement mensuel', 1.0, 'C62', 99.00, 'S', 21.0);
        $line->setLinePeriod('2025-03-01', '2025-03-31');
        $original->addInvoiceLine($line);
        $original->calculateTotals();

        $imported = $this->roundTrip($original);
        $il = $imported->getInvoiceLines()[0];

        $this->assertSame('2025-03-01', $il->getLinePeriodStartDate(), 'BT-134');
        $this->assertSame('2025-03-31', $il->getLinePeriodEndDate(), 'BT-135');
    }

    // =========================================================================
    // BT-155/156/157/158/159
    // =========================================================================

    public function testRoundTripIdentifiantsArticle(): void
    {
        $original = new \PeppolInvoice('INV-IDS-001', '2025-03-15');
        $original->setDueDate('2025-04-15');
        $original->setSeller($this->makeParty('Vendeur SA', 'BE0123456789'));
        $original->setBuyer($this->makeParty('Acheteur SPRL', 'BE0987654321'));

        $line = new InvoiceLine('1', 'Vérin hydraulique V-40', 2.0, 'C62', 320.00, 'S', 21.0);
        $line->setSellerItemId('VERIN-V40')
             ->setBuyerItemId('ART-BUYER-999')
             ->setStandardItemId('3700000040001', '0160')
             ->setItemClassificationCode('23152000', 'STI')
             ->setOriginCountryCode('DE');
        $original->addInvoiceLine($line);
        $original->calculateTotals();

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
        $original->addAllowanceCharge(
            AllowanceCharge::createAllowance(
                amount: 50.00,
                vatCategory: 'S',
                vatRate: 21.0,
                reason: 'Remise fidélité'
            )
        );
        $original->calculateTotals();

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
        $original = new \PeppolInvoice('INV-DISC-001', '2025-03-15');
        $original->setDueDate('2025-04-15');
        $original->setSeller($this->makeParty('Vendeur SA', 'BE0123456789'));
        $original->setBuyer($this->makeParty('Acheteur SPRL', 'BE0987654321'));

        $line = new InvoiceLine('1', 'Article avec remise', 10.0, 'C62', 100.00, 'S', 21.0);
        $line->addAllowanceCharge(
            AllowanceCharge::createAllowance(amount: 50.00, vatCategory: 'S', vatRate: 21.0, reason: 'Remise volume')
        );
        $original->addInvoiceLine($line);
        $original->calculateTotals();

        $imported = $this->roundTrip($original);
        // 10×100 - 50 = 950
        $this->assertEqualsWithDelta(950.00, $imported->getInvoiceLines()[0]->getLineAmount(), 0.01);
    }

    // =========================================================================
    // Totaux
    // =========================================================================

    public function testRoundTripTotauxCoherents(): void
    {
        $original = new \PeppolInvoice('INV-TOT-001', '2025-03-15');
        $original->setDueDate('2025-04-15');
        $original->setSeller($this->makeParty('Vendeur SA', 'BE0123456789'));
        $original->setBuyer($this->makeParty('Acheteur SPRL', 'BE0987654321'));

        $original->addInvoiceLine(new InvoiceLine('1', 'Art 1', 5.0, 'C62', 100.00, 'S', 21.0));
        $original->addInvoiceLine(new InvoiceLine('2', 'Art 2', 2.0, 'C62', 250.00, 'S', 21.0));
        $original->calculateTotals();

        $imported = $this->roundTrip($original);
        $imported->calculateTotals();

        // 5×100 + 2×250 = 500 + 500 = 1000 HT
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
        $payment = new PaymentInfo('30', 'BE71096123456769', 'GKCCBEBB', 'INV-2025-001');
        $original->setPaymentInfo($payment);

        $imported = $this->roundTrip($original);
        $pi = $imported->getPaymentInfo();

        $this->assertNotNull($pi);
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
        $invoice->addInvoiceLine(new InvoiceLine('1', 'Prestation standard', 1.0, 'C62', 100.00, 'S', 21.0));
        $invoice->calculateTotals();
        return $invoice;
    }

    private function roundTrip(\PeppolInvoice $invoice): \PeppolInvoice
    {
        $exporter = new XmlExporter($invoice);
        $xml = $exporter->toUbl21();

        $this->assertNotEmpty($xml);
        $dom = new \DOMDocument();
        $this->assertTrue($dom->loadXML($xml), 'XML mal formé');

        $imported = \PeppolInvoice::fromXml($xml, strict: false);
        $this->assertInstanceOf(\PeppolInvoice::class, $imported);
        return $imported;
    }
}
