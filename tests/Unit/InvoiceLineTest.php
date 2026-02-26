<?php

declare(strict_types=1);

namespace Peppol\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Peppol\Models\InvoiceLine;
use Peppol\Models\AllowanceCharge;

/**
 * Tests unitaires pour InvoiceLine.
 * Couvre : calcul lineAmount, BT-127, BT-132, BG-26, BT-155/156/157/158/159.
 */
class InvoiceLineTest extends TestCase
{
    private InvoiceLine $line;

    protected function setUp(): void
    {
        $this->line = new InvoiceLine();
        $this->line
            ->setId('1')
            ->setName('Article test')
            ->setQuantity(10.0)
            ->setUnitCode('C62')
            ->setUnitPrice(25.00)
            ->setVatCategory('S')
            ->setVatRate(21.0);
    }

    // -------------------------------------------------------------------------
    // Calcul lineAmount
    // -------------------------------------------------------------------------

    public function testLineAmountSansRemise(): void
    {
        // 10 × 25.00 = 250.00
        $this->assertEqualsWithDelta(250.00, $this->line->getLineAmount(), 0.01);
    }

    public function testLineAmountAvecRemise(): void
    {
        $this->line->addAllowanceCharge(AllowanceCharge::createAllowance(10.00, 'S', 21.0));
        $this->assertEqualsWithDelta(240.00, $this->line->getLineAmount(), 0.01);
    }

    public function testLineAmountAvecMajoration(): void
    {
        $this->line->addAllowanceCharge(AllowanceCharge::createCharge(5.00, 'S', 21.0));
        $this->assertEqualsWithDelta(255.00, $this->line->getLineAmount(), 0.01);
    }

    public function testLineAmountRemiseEtMajoration(): void
    {
        $this->line->addAllowanceCharge(AllowanceCharge::createAllowance(20.00, 'S', 21.0));
        $this->line->addAllowanceCharge(AllowanceCharge::createCharge(5.00, 'S', 21.0));
        $this->assertEqualsWithDelta(235.00, $this->line->getLineAmount(), 0.01);
    }

    // -------------------------------------------------------------------------
    // BT-127 — Note de ligne
    // -------------------------------------------------------------------------

    public function testNoteDeLineNullParDefaut(): void
    {
        $this->assertNull($this->line->getLineNote());
    }

    public function testNoteDeLineGetSet(): void
    {
        $this->line->setLineNote('Référence ancienne : ART-99');
        $this->assertSame('Référence ancienne : ART-99', $this->line->getLineNote());
    }

    public function testNoteDeLineNullable(): void
    {
        $this->line->setLineNote('Texte');
        $this->line->setLineNote(null);
        $this->assertNull($this->line->getLineNote());
    }

    // -------------------------------------------------------------------------
    // BT-132 — Référence ligne commande
    // -------------------------------------------------------------------------

    public function testOrderLineReferenceNullParDefaut(): void
    {
        $this->assertNull($this->line->getOrderLineReference());
    }

    public function testOrderLineReferenceGetSet(): void
    {
        $this->line->setOrderLineReference('PO-LINE-7');
        $this->assertSame('PO-LINE-7', $this->line->getOrderLineReference());
    }

    // -------------------------------------------------------------------------
    // BG-26 — Période de facturation ligne
    // -------------------------------------------------------------------------

    public function testLinePeriodValide(): void
    {
        $this->line->setLinePeriod('2025-01-01', '2025-01-31');
        $this->assertSame('2025-01-01', $this->line->getLinePeriodStartDate());
        $this->assertSame('2025-01-31', $this->line->getLinePeriodEndDate());
    }

    public function testLinePeriodMemeJour(): void
    {
        $this->line->setLinePeriod('2025-06-15', '2025-06-15');
        $this->assertSame('2025-06-15', $this->line->getLinePeriodStartDate());
    }

    public function testLinePeriodEndAvantStartLanceException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->line->setLinePeriod('2025-03-31', '2025-03-01');
    }

    public function testLinePeriodFormatInvalide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->line->setLinePeriod('01/01/2025', '31/01/2025');
    }

    // -------------------------------------------------------------------------
    // BT-155/156 — Identifiants article
    // -------------------------------------------------------------------------

    public function testSellerItemId(): void
    {
        $this->assertNull($this->line->getSellerItemId());
        $this->line->setSellerItemId('VERIN-V40');
        $this->assertSame('VERIN-V40', $this->line->getSellerItemId());
    }

    public function testBuyerItemId(): void
    {
        $this->assertNull($this->line->getBuyerItemId());
        $this->line->setBuyerItemId('ART-BUYER-42');
        $this->assertSame('ART-BUYER-42', $this->line->getBuyerItemId());
    }

    // -------------------------------------------------------------------------
    // BT-157 — Identifiant standard article (EAN/GTIN)
    // -------------------------------------------------------------------------

    public function testStandardItemIdNullParDefaut(): void
    {
        $this->assertNull($this->line->getStandardItemId());
    }

    public function testStandardItemIdAvecScheme(): void
    {
        $this->line->setStandardItemId('3700000040001', '0160');
        $this->assertSame('3700000040001', $this->line->getStandardItemId());
        $this->assertSame('0160', $this->line->getStandardItemSchemeId());
    }

    public function testStandardItemIdSchemeDefaut0160(): void
    {
        $this->line->setStandardItemId('3700000040001');
        $this->assertSame('0160', $this->line->getStandardItemSchemeId());
    }

    public function testStandardItemIdNullEffaceScheme(): void
    {
        $this->line->setStandardItemId('3700000040001');
        $this->line->setStandardItemId(null);
        $this->assertNull($this->line->getStandardItemId());
        $this->assertNull($this->line->getStandardItemSchemeId());
    }

    // -------------------------------------------------------------------------
    // BT-158 — Code classification
    // -------------------------------------------------------------------------

    public function testItemClassificationCode(): void
    {
        $this->line->setItemClassificationCode('49211500', 'STI');
        $this->assertSame('49211500', $this->line->getItemClassificationCode());
        $this->assertSame('STI', $this->line->getItemClassificationListId());
    }

    // -------------------------------------------------------------------------
    // BT-159 — Pays d'origine
    // -------------------------------------------------------------------------

    public function testOriginCountryCodeNullParDefaut(): void
    {
        $this->assertNull($this->line->getOriginCountryCode());
    }

    public function testOriginCountryCode(): void
    {
        $this->line->setOriginCountryCode('DE');
        $this->assertSame('DE', $this->line->getOriginCountryCode());
    }

    public function testOriginCountryCodeNormaliseEnMajuscule(): void
    {
        $this->line->setOriginCountryCode('de');
        $this->assertSame('DE', $this->line->getOriginCountryCode());
    }
}
