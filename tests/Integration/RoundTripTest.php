<?php

declare(strict_types=1);

namespace PHPPeppol\Tests\Integration;

use PHPUnit\Framework\TestCase;
use PHPPeppol\Models\PeppolInvoice;
use PHPPeppol\Models\InvoiceLine;
use PHPPeppol\Models\Party;
use PHPPeppol\Models\Address;
use PHPPeppol\Models\ElectronicAddress;
use PHPPeppol\Models\AllowanceCharge;
use PHPPeppol\Models\PaymentInfo;
use PHPPeppol\Formats\XmlExporter;
use PHPPeppol\Formats\XmlImporter;

/**
 * Tests d'intégration : round-trip PHP → XML → PHP.
 * Vérifie que chaque champ exporté est correctement réimporté.
 */
class RoundTripTest extends TestCase
{
    // =========================================================================
    // Facture minimale conforme BIS 3.0
    // =========================================================================

    public function testRoundTripFactureMinimale(): void
    {
        $original = $this->buildInvoiceMinimale();
        $imported = $this->roundTrip($original);

        $this->assertSame(
            $original->getInvoiceNumber(),
            $imported->getInvoiceNumber(),
            'BT-1 Invoice number'
        );
        $this->assertSame(
            $original->getIssueDate(),
            $imported->getIssueDate(),
            'BT-2 Issue date'
        );
        $this->assertSame(
            $original->getDocumentCurrencyCode(),
            $imported->getDocumentCurrencyCode(),
            'BT-5 Currency'
        );
    }

    // =========================================================================
    // Références document
    // =========================================================================

    public function testRoundTripReferences(): void
    {
        $original = $this->buildInvoiceMinimale();
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
    // BG-14 Période de facturation header
    // =========================================================================

    public function testRoundTripInvoicePeriod(): void
    {
        $original = $this->buildInvoiceMinimale();
        $original->setInvoicePeriod('2025-02-01', '2025-02-28');

        $imported = $this->roundTrip($original);

        $this->assertSame('2025-02-01', $imported->getInvoicePeriodStartDate(), 'BT-73');
        $this->assertSame('2025-02-28', $imported->getInvoicePeriodEndDate(), 'BT-74');
    }

    // =========================================================================
    // Lignes de facture
    // =========================================================================

    public function testRoundTripLignesBase(): void
    {
        $original = $this->buildInvoiceMinimale();

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

        $importedLine = $lines[0];
        $this->assertSame('1', $importedLine->getId(), 'BT-126');
        $this->assertSame('Pompe hydraulique HPX-200', $importedLine->getName(), 'BT-153');
        $this->assertEqualsWithDelta(3.0, $importedLine->getQuantity(), 0.001, 'BT-129');
        $this->assertSame('C62', $importedLine->getUnitCode(), 'BT-130');
        $this->assertEqualsWithDelta(450.00, $importedLine->getUnitPrice(), 0.01, 'BT-146');
        $this->assertSame('S', $importedLine->getVatCategory(), 'BT-151');
        $this->assertEqualsWithDelta(21.0, $importedLine->getVatRate(), 0.01, 'BT-152');
    }

    public function testRoundTripLigneNoteEtReference(): void
    {
        $original = $this->buildInvoiceMinimale();

        $line = new InvoiceLine();
        $line->setId('1')
             ->setName('Service maintenance')
             ->setQuantity(1.0)
             ->setUnitCode('C62')
             ->setUnitPrice(200.00)
             ->setVatCategory('S')
             ->setVatRate(21.0)
             ->setLineNote('Intervention du 15/01/2025 - Ticket #4521')
             ->setOrderLineReference('PO-LINE-3');

        $original->addInvoiceLine($line);
        $imported = $this->roundTrip($original);

        $importedLine = $imported->getInvoiceLines()[0];
        $this->assertSame('Intervention du 15/01/2025 - Ticket #4521', $importedLine->getLineNote(), 'BT-127');
        $this->assertSame('PO-LINE-3', $importedLine->getOrderLineReference(), 'BT-132');
    }

    public function testRoundTripLignePeriode(): void
    {
        $original = $this->buildInvoiceMinimale();

        $line = new InvoiceLine();
        $line->setId('1')
             ->setName('Abonnement mensuel')
             ->setQuantity(1.0)
             ->setUnitCode('C62')
             ->setUnitPrice(99.00)
             ->setVatCategory('S')
             ->setVatRate(21.0)
             ->setLinePeriod('2025-03-01', '2025-03-31');

        $original->addInvoiceLine($line);
        $imported = $this->roundTrip($original);

        $importedLine = $imported->getInvoiceLines()[0];
        $this->assertSame('2025-03-01', $importedLine->getLinePeriodStartDate(), 'BT-134');
        $this->assertSame('2025-03-31', $importedLine->getLinePeriodEndDate(), 'BT-135');
    }

    // =========================================================================
    // BT-155/156/157/158/159 — Identifiants et classification article
    // =========================================================================

    public function testRoundTripIdentifiantsArticle(): void
    {
        $original = $this->buildInvoiceMinimale();

        $line = new InvoiceLine();
        $line->setId('1')
             ->setName('Vérin hydraulique V-40')
             ->setQuantity(2.0)
             ->setUnitCode('C62')
             ->setUnitPrice(320.00)
             ->setVatCategory('S')
             ->setVatRate(21.0)
             ->setSellerItemId('VERIN-V40')
             ->setBuyerItemId('ART-BUYER-999')
             ->setStandardItemId('3700000040001', '0160')
             ->setItemClassificationCode('23152000', 'STI')
             ->setOriginCountryCode('DE');

        $original->addInvoiceLine($line);
        $imported = $this->roundTrip($original);

        $importedLine = $imported->getInvoiceLines()[0];
        $this->assertSame('VERIN-V40', $importedLine->getSellerItemId(), 'BT-155');
        $this->assertSame('ART-BUYER-999', $importedLine->getBuyerItemId(), 'BT-156');
        $this->assertSame('3700000040001', $importedLine->getStandardItemId(), 'BT-157');
        $this->assertSame('0160', $importedLine->getStandardItemSchemeId(), 'BT-157 schemeID');
        $this->assertSame('23152000', $importedLine->getItemClassificationCode(), 'BT-158');
        $this->assertSame('DE', $importedLine->getOriginCountryCode(), 'BT-159');
    }

    // =========================================================================
    // BT-33 Capital social
    // =========================================================================

    public function testRoundTripCompanyLegalForm(): void
    {
        $original = $this->buildInvoiceMinimale();
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
        $original = $this->buildInvoiceMinimale();
        $line = $this->makeLineSingle();
        $original->addInvoiceLine($line);

        $allowance = AllowanceCharge::createAllowance(50.00, 'S', 21.0, 'Remise fidélité', '95');
        $original->addAllowance($allowance);

        $imported = $this->roundTrip($original);

        $allowances = array_filter(
            $imported->getAllowanceCharges(),
            fn($ac) => !$ac->isCharge()
        );
        $this->assertCount(1, $allowances);

        $importedAllowance = array_values($allowances)[0];
        $this->assertEqualsWithDelta(50.00, $importedAllowance->getAmount(), 0.01);
        $this->assertSame('Remise fidélité', $importedAllowance->getReason());
    }

    public function testRoundTripRemiseLigne(): void
    {
        $original = $this->buildInvoiceMinimale();

        $line = new InvoiceLine();
        $line->setId('1')
             ->setName('Article avec remise')
             ->setQuantity(10.0)
             ->setUnitCode('C62')
             ->setUnitPrice(100.00)
             ->setVatCategory('S')
             ->setVatRate(21.0);

        $remiseLigne = AllowanceCharge::createAllowance(50.00, 'S', 21.0, 'Remise volume');
        $line->addAllowanceCharge($remiseLigne);

        $original->addInvoiceLine($line);
        $imported = $this->roundTrip($original);

        $importedLine = $imported->getInvoiceLines()[0];
        // lineAmount = 10×100 - 50 = 950
        $this->assertEqualsWithDelta(950.00, $importedLine->getLineAmount(), 0.01);
    }

    // =========================================================================
    // Totaux après round-trip
    // =========================================================================

    public function testRoundTripTotauxCoherents(): void
    {
        $original = $this->buildInvoiceMinimale();

        $line1 = new InvoiceLine();
        $line1->setId('1')->setName('Art 1')->setQuantity(5.0)->setUnitCode('C62')
              ->setUnitPrice(100.00)->setVatCategory('S')->setVatRate(21.0);

        $line2 = new InvoiceLine();
        $line2->setId('2')->setName('Art 2')->setQuantity(2.0)->setUnitCode('C62')
              ->setUnitPrice(250.00)->setVatCategory('S')->setVatRate(21.0);

        $original->addInvoiceLine($line1);
        $original->addInvoiceLine($line2);
        $original->calculateTotals();

        $imported = $this->roundTrip($original);
        $imported->calculateTotals();

        // HT = 500 + 500 = 1000
        $this->assertEqualsWithDelta(1000.00, $imported->getTaxExclusiveAmount(), 0.01);
        // TVA = 1000 × 21% = 210
        $this->assertEqualsWithDelta(210.00, $imported->getTotalVatAmount(), 0.01);
        // TTC = 1210
        $this->assertEqualsWithDelta(1210.00, $imported->getTaxInclusiveAmount(), 0.01);
    }

    // =========================================================================
    // Paiement
    // =========================================================================

    public function testRoundTripPaiementVirement(): void
    {
        $original = $this->buildInvoiceMinimale();

        $payment = new PaymentInfo();
        $payment->setPaymentMeansCode('30')
                ->setIban('BE71096123456769')
                ->setBic('GKCCBEBB')
                ->setPaymentReference('INV-2025-001');

        $original->setPaymentInfo($payment);
        $imported = $this->roundTrip($original);

        $this->assertSame('BE71096123456769', $imported->getPaymentInfo()->getIban());
        $this->assertSame('GKCCBEBB', $imported->getPaymentInfo()->getBic());
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function buildInvoiceMinimale(): PeppolInvoice
    {
        $invoice = new PeppolInvoice();
        $invoice->setInvoiceNumber('INV-TEST-001')
                ->setIssueDate('2025-03-15')
                ->setDocumentCurrencyCode('EUR')
                ->setDueDate('2025-04-15');

        $seller = $this->makeParty(
            'Hydraulique Industrielle SA',
            'BE0123456789',
            'Rue de l\'Industrie 42',
            'Liège',
            '4000',
            'BE'
        );
        $invoice->setSeller($seller);

        $buyer = $this->makeParty(
            'Garage Dupont SPRL',
            'BE0987654321',
            'Chaussée de Namur 15',
            'Namur',
            '5000',
            'BE'
        );
        $invoice->setBuyer($buyer);

        // Ligne par défaut pour que la facture soit valide
        $invoice->addInvoiceLine($this->makeLineSingle());
        $invoice->calculateTotals();

        return $invoice;
    }

    private function makeParty(
        string $name,
        string $vatId,
        string $street,
        string $city,
        string $postal,
        string $country
    ): Party {
        $address = new Address();
        $address->setStreetName($street)
                ->setCityName($city)
                ->setPostalZone($postal)
                ->setCountryCode($country);

        $endpoint = new ElectronicAddress();
        $endpoint->setIdentifier($vatId)->setSchemeId('0208');

        $party = new Party();
        $party->setName($name)
              ->setVatId($vatId)
              ->setAddress($address)
              ->setElectronicAddress($endpoint);

        return $party;
    }

    private function makeLineSingle(): InvoiceLine
    {
        $line = new InvoiceLine();
        $line->setId('1')
             ->setName('Prestation standard')
             ->setQuantity(1.0)
             ->setUnitCode('C62')
             ->setUnitPrice(100.00)
             ->setVatCategory('S')
             ->setVatRate(21.0);
        return $line;
    }

    /**
     * Exporte la facture en XML puis la réimporte, simule le round-trip réseau.
     */
    private function roundTrip(PeppolInvoice $invoice): PeppolInvoice
    {
        $exporter = new XmlExporter();
        $xml = $exporter->export($invoice);

        // Vérifier que le XML produit est du XML valide
        $this->assertNotEmpty($xml, 'XML exporté ne doit pas être vide');

        $dom = new \DOMDocument();
        $loadResult = $dom->loadXML($xml);
        $this->assertTrue($loadResult, 'XML exporté doit être du XML bien formé');

        $importer = new XmlImporter();
        $imported = $importer->import($xml);

        $this->assertInstanceOf(PeppolInvoice::class, $imported);
        return $imported;
    }
}
