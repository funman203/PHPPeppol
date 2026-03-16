<?php

declare(strict_types=1);

namespace Peppol\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Peppol\Models\ApplicationResponse;
use Peppol\Models\DocumentResponse;
use Peppol\Formats\ApplicationResponseImporter;
use Peppol\Formats\ApplicationResponseExporter;

/**
 * Tests unitaires pour ApplicationResponse (MLR + IMR).
 */
class ApplicationResponseTest extends TestCase
{
    // =========================================================================
    // DocumentResponse — statuts
    // =========================================================================

    public function testDocumentResponseAccepte(): void
    {
        $dr = new DocumentResponse('AP', 'INV-2025-001');
        $this->assertTrue($dr->isAccepted());
        $this->assertFalse($dr->isRejected());
        $this->assertFalse($dr->isPending());
        $this->assertSame('Accepté', $dr->getStatusLabel());
    }

    public function testDocumentResponseRejete(): void
    {
        $dr = new DocumentResponse('RE', 'INV-2025-001', 'Facture invalide');
        $this->assertTrue($dr->isRejected());
        $this->assertFalse($dr->isAccepted());
        $reasons = $dr->getRejectionReasons();
        $this->assertCount(1, $reasons);
        $this->assertSame('Facture invalide', $reasons[0]);
    }

    public function testDocumentResponsePending(): void
    {
        foreach (['AB', 'IP', 'UQ'] as $code) {
            $dr = new DocumentResponse($code, 'INV-001');
            $this->assertTrue($dr->isPending(), "Code $code devrait être pending");
        }
    }

    public function testDocumentResponseCodeInvalide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new DocumentResponse('XX', 'INV-001');
    }

    public function testDocumentResponseReferenceVide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new DocumentResponse('AP', '');
    }

    public function testLineResponsesAggregees(): void
    {
        $dr = new DocumentResponse('RE', 'INV-001');
        $dr->addLineResponse('Numéro de TVA invalide', 'BT-31');
        $dr->addLineResponse('Montant incohérent', 'BR-CO-13');

        $reasons = $dr->getRejectionReasons();
        $this->assertCount(2, $reasons);
        $this->assertStringContainsString('[BT-31]', $reasons[0]);
        $this->assertStringContainsString('[BR-CO-13]', $reasons[1]);
    }

    // =========================================================================
    // ApplicationResponse — construction
    // =========================================================================

    public function testApplicationResponseConstruction(): void
    {
        $ar = new ApplicationResponse('AR-001', '2025-03-15');
        $this->assertSame('AR-001', $ar->getId());
        $this->assertSame('2025-03-15', $ar->getIssueDate());
        $this->assertTrue($ar->isImr());
        $this->assertFalse($ar->isMlr());
    }

    public function testApplicationResponseMlr(): void
    {
        $ar = new ApplicationResponse(
            'MLR-001', '2025-03-15',
            ApplicationResponse::CUSTOMIZATION_MLR,
            ApplicationResponse::PROFILE_MLR
        );
        $this->assertTrue($ar->isMlr());
        $this->assertFalse($ar->isImr());
    }

    public function testApplicationResponseDateInvalide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ApplicationResponse('AR-001', '15-03-2025');
    }

    public function testApplicationResponseHelperStatuts(): void
    {
        $ar = new ApplicationResponse('AR-001', '2025-03-15');
        $ar->addDocumentResponse(new DocumentResponse('AP', 'INV-001'));

        $this->assertTrue($ar->isAccepted());
        $this->assertFalse($ar->isRejected());
        $this->assertSame('Accepté', $ar->getStatusLabel());
    }

    public function testGetRejectionReasonsMultiDocuments(): void
    {
        $ar = new ApplicationResponse('AR-001', '2025-03-15');

        $dr1 = new DocumentResponse('RE', 'INV-001', 'Erreur globale');
        $dr1->addLineResponse('Détail ligne 1', 'BT-1');
        $ar->addDocumentResponse($dr1);

        $reasons = $ar->getRejectionReasons();
        $this->assertCount(2, $reasons);
    }

    // =========================================================================
    // Factory createImrForInvoice
    // =========================================================================

    public function testCreateImrForInvoice(): void
    {
        $invoice = new \PeppolInvoice('FAC-2025-042', '2025-03-15');
        $ar = ApplicationResponse::createImrForInvoice($invoice, 'AP');

        $this->assertTrue($ar->isImr());
        $this->assertTrue($ar->isAccepted());
        $this->assertSame('FAC-2025-042', $ar->getFirstDocumentResponse()?->getReferenceId());
        $this->assertSame('380', $ar->getFirstDocumentResponse()?->getReferenceTypeCode());
    }

    public function testCreateImrRejectedAvecRaison(): void
    {
        $invoice = new \PeppolInvoice('FAC-2025-043', '2025-03-15');
        $ar = ApplicationResponse::createImrForInvoice($invoice, 'RE', 'Montant TVA incorrect');

        $this->assertTrue($ar->isRejected());
        $this->assertSame('Montant TVA incorrect', $ar->getFirstDocumentResponse()?->getDescription());
    }

    // =========================================================================
    // matchesInvoice
    // =========================================================================

    public function testMatchesInvoiceOk(): void
    {
        $ar = new ApplicationResponse('AR-001', '2025-03-15');
        $ar->addDocumentResponse(new DocumentResponse('AP', 'FAC-99'));
    
        $issues = $ar->matchesInvoice('FAC-99', 'BE0123456789', 'BE0987654321');
        $this->assertEmpty($issues);
    }
    
    public function testMatchesInvoiceMauvaisId(): void
    {
        $ar = new ApplicationResponse('AR-001', '2025-03-15');
        $ar->addDocumentResponse(new DocumentResponse('AP', 'FAC-DIFFERENT'));
    
        $issues = $ar->matchesInvoice('FAC-99');
        $this->assertCount(1, $issues);
        $this->assertStringContainsString('FAC-DIFFERENT', $issues[0]);
    }
    
    public function testMatchesInvoiceMauvaisVendeur(): void
{
    $ar = new ApplicationResponse('AR-001', '2025-03-15');
    $ar->addDocumentResponse(new DocumentResponse('AP', 'FAC-99'));
    $ar->setReceiverParty('Quelqu\'un', 'BE9999999999', '0208');

    $issues = $ar->matchesInvoice('FAC-99', 'BE0123456789');
    $this->assertCount(1, $issues);
    $this->assertStringContainsString('ReceiverParty', $issues[0]);
}

    // =========================================================================
    // Round-trip XML export → import
    // =========================================================================

    public function testRoundTripImr(): void
    {
        $ar = new ApplicationResponse('AR-RT-001', '2025-03-15');
        $ar->setIssueTime('14:30:00');
        $ar->setSenderParty('Acheteur SA', 'BE0987654321', '0208');
        $ar->setReceiverParty('Vendeur SPRL', 'BE0123456789', '0208');

        $dr = new DocumentResponse('RE', 'INV-2025-001', 'Facture non conforme');
        $dr->setReferenceTypeCode('380');
        $dr->addLineResponse('Numéro de TVA manquant', 'BT-31');
        $ar->addDocumentResponse($dr);

        $exporter = new ApplicationResponseExporter();
        $xml = $exporter->toXml($ar);

        // Le XML doit être bien formé
        $dom = new \DOMDocument();
        $this->assertTrue($dom->loadXML($xml), 'XML mal formé');

        // Réimport
        $imported = ApplicationResponseImporter::fromXml($xml);

        $this->assertSame('AR-RT-001', $imported->getId());
        $this->assertSame('2025-03-15', $imported->getIssueDate());
        $this->assertSame('14:30:00', $imported->getIssueTime());
        $this->assertTrue($imported->isRejected());

        $idr = $imported->getFirstDocumentResponse();
        $this->assertNotNull($idr);
        $this->assertSame('INV-2025-001', $idr->getReferenceId());
        $this->assertSame('380', $idr->getReferenceTypeCode());
        $this->assertSame('Facture non conforme', $idr->getDescription());
        $this->assertCount(1, $idr->getLineResponses());
    }

    public function testRoundTripMlr(): void
    {
        $ar = new ApplicationResponse(
            'MLR-001', '2025-03-15',
            ApplicationResponse::CUSTOMIZATION_MLR,
            ApplicationResponse::PROFILE_MLR
        );
        $ar->addDocumentResponse(new DocumentResponse('AP', 'INV-2025-001'));

        $exporter = new ApplicationResponseExporter();
        $xml = $exporter->toXml($ar);
        $imported = ApplicationResponseImporter::fromXml($xml);

        $this->assertTrue($imported->isMlr());
        $this->assertTrue($imported->isAccepted());
    }

    public function testImportXmlManquantDocumentResponse(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<ApplicationResponse xmlns="urn:oasis:names:specification:ubl:schema:xsd:ApplicationResponse-2"
    xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2"
    xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2">
    <cbc:ID>AR-EMPTY</cbc:ID>
    <cbc:IssueDate>2025-03-15</cbc:IssueDate>
</ApplicationResponse>
XML;
        $this->expectException(\InvalidArgumentException::class);
        ApplicationResponseImporter::fromXml($xml);
    }
}