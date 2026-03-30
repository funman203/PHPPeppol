<?php

declare(strict_types=1);

namespace Peppol\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Peppol\Formats\InvoiceHtmlRenderer;
use Peppol\Models\Address;
use Peppol\Models\ElectronicAddress;
use Peppol\Models\Party;
use Peppol\Models\InvoiceLine;
use Peppol\Models\AllowanceCharge;
use Peppol\Models\PaymentInfo;

/**
 * Tests unitaires pour InvoiceHtmlRenderer.
 * Vérifie que la classe est trouvable et que le HTML produit est correct.
 */
class InvoiceHtmlRendererTest extends TestCase
{
    private InvoiceHtmlRenderer $renderer;
    private \PeppolInvoice $invoice;

    protected function setUp(): void
    {
        $this->renderer = new InvoiceHtmlRenderer();
        $this->invoice  = $this->buildInvoice();
    }

    // =========================================================================
    // Autoload / instantiation
    // =========================================================================

    public function testClasseExiste(): void
    {
        $this->assertTrue(
            class_exists(InvoiceHtmlRenderer::class),
            'La classe InvoiceHtmlRenderer doit être trouvable via autoload'
        );
    }

    public function testInstanciation(): void
    {
        $this->assertInstanceOf(InvoiceHtmlRenderer::class, $this->renderer);
    }

    // =========================================================================
    // render() — fragment
    // =========================================================================

    public function testRenderRetourneUneChaine(): void
    {
        $html = $this->renderer->render($this->invoice);
        $this->assertIsString($html);
        $this->assertNotEmpty($html);
    }

    public function testRenderContientLaClassePepDoc(): void
    {
        $html = $this->renderer->render($this->invoice);
        $this->assertStringContainsString('pep-doc', $html);
    }

    public function testRenderContientLeStyle(): void
    {
        $html = $this->renderer->render($this->invoice);
        $this->assertStringContainsString('<style>', $html);
    }

    public function testRenderNePasEtrePleinePage(): void
    {
        $html = $this->renderer->render($this->invoice);
        $this->assertStringNotContainsString('<!DOCTYPE html>', $html);
        $this->assertStringNotContainsString('<html', $html);
    }

    // =========================================================================
    // renderPage() — page complète
    // =========================================================================

    public function testRenderPageRetournePaginaComplete(): void
    {
        $html = $this->renderer->renderPage($this->invoice);
        $this->assertStringContainsString('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('<html', $html);
        $this->assertStringContainsString('</html>', $html);
    }

    public function testRenderPageContientHead(): void
    {
        $html = $this->renderer->renderPage($this->invoice);
        $this->assertStringContainsString('<head>', $html);
        $this->assertStringContainsString('<meta charset="UTF-8">', $html);
    }

    public function testRenderPageContientGoogleFonts(): void
    {
        $html = $this->renderer->renderPage($this->invoice);
        $this->assertStringContainsString('fonts.googleapis.com', $html);
    }

    // =========================================================================
    // getStyles()
    // =========================================================================

    public function testGetStylesRetourneBaliseStyle(): void
    {
        $css = $this->renderer->getStyles();
        $this->assertStringStartsWith('<style>', $css);
        $this->assertStringEndsWith('</style>', $css);
    }

    public function testGetStylesContientVariablesCss(): void
    {
        $css = $this->renderer->getStyles();
        $this->assertStringContainsString('--pep-ink', $css);
        $this->assertStringContainsString('--pep-accent', $css);
    }

    // =========================================================================
    // Contenu — champs obligatoires
    // =========================================================================

    public function testNumeroFacturePresent(): void
    {
        $html = $this->renderer->render($this->invoice);
        $this->assertStringContainsString('FAC-TEST-001', $html);
    }

    public function testNomVendeurPresent(): void
    {
        $html = $this->renderer->render($this->invoice);
        $this->assertStringContainsString('Vendeur Test SA', $html);
    }

    public function testNomAcheteurPresent(): void
    {
        $html = $this->renderer->render($this->invoice);
        $this->assertStringContainsString('Acheteur SPRL', $html);
    }

    public function testNomArticlePresent(): void
    {
        $html = $this->renderer->render($this->invoice);
        $this->assertStringContainsString('Article de test', $html);
    }

    public function testMontantTtcPresent(): void
    {
        $html = $this->renderer->render($this->invoice);
        // 100 × 21% = 121 TTC
        $this->assertStringContainsString('121', $html);
    }

    public function testTypeFacturePresent(): void
    {
        $html = $this->renderer->render($this->invoice);
        $this->assertStringContainsString('FACTURE', $html);
    }

    // =========================================================================
    // Contenu — champs optionnels présents si renseignés
    // =========================================================================

    public function testNoteFactureAffichee(): void
    {
        $this->invoice->setInvoiceNote('Ceci est une note de test');
        $html = $this->renderer->render($this->invoice);
        $this->assertStringContainsString('Ceci est une note de test', $html);
    }

    public function testReferencesAffichees(): void
    {
        $this->invoice->setPurchaseOrderReference('PO-999');
        $this->invoice->setContractReference('CONTRAT-XY');
        $html = $this->renderer->render($this->invoice);
        $this->assertStringContainsString('PO-999', $html);
        $this->assertStringContainsString('CONTRAT-XY', $html);
    }

    public function testPeriodeFacturationAffichee(): void
    {
        $this->invoice->setInvoicePeriod('2025-01-01', '2025-01-31');
        $html = $this->renderer->render($this->invoice);
        $this->assertStringContainsString('01/01/2025', $html);
        $this->assertStringContainsString('31/01/2025', $html);
    }

    public function testCapitalSocialAffiche(): void
    {
        $this->invoice->getSeller()->setCompanyLegalForm('SA au capital de 100 000 EUR');
        $html = $this->renderer->render($this->invoice);
        $this->assertStringContainsString('SA au capital de 100 000 EUR', $html);
    }

    public function testIbanAffiche(): void
    {
        $payment = new PaymentInfo('30', 'BE71096123456769', 'GKCCBEBB', 'REF-001');
        $this->invoice->setPaymentInfo($payment);
        $html = $this->renderer->render($this->invoice);
        $this->assertStringContainsString('BE71096123456769', $html);
        $this->assertStringContainsString('GKCCBEBB', $html);
    }

    public function testConditionsPaiementAffichees(): void
    {
        $this->invoice->setPaymentTerms('Paiement à 30 jours.');
        $html = $this->renderer->render($this->invoice);
        $this->assertStringContainsString('Paiement à 30 jours.', $html);
    }

    public function testRemiseDocumentAffichee(): void
    {
        $this->invoice->addAllowanceCharge(
            AllowanceCharge::createAllowance(amount: 10.00, vatCategory: 'S', vatRate: 21.0, reason: 'Remise test')
        );
        $this->invoice->calculateTotals();
        $html = $this->renderer->render($this->invoice);
        $this->assertStringContainsString('Remise test', $html);
    }

    public function testNoteDeLineAffichee(): void
    {
        $this->invoice->addInvoiceLine(
            (new InvoiceLine('2', 'Art 2', 1.0, 'C62', 50.00, 'S', 21.0))
                ->setLineNote('Note sur la ligne 2')
        );
        $this->invoice->calculateTotals();
        $html = $this->renderer->render($this->invoice);
        $this->assertStringContainsString('Note sur la ligne 2', $html);
    }

    public function testIdentifiantsArticleAffiches(): void
    {
        $line = new InvoiceLine('2', 'Art GTIN', 1.0, 'C62', 50.00, 'S', 21.0);
        $line->setSellerItemId('REF-VEND-99')
             ->setStandardItemId('3700000000001', '0160')
             ->setOriginCountryCode('DE');
        $this->invoice->addInvoiceLine($line);
        $this->invoice->calculateTotals();

        $html = $this->renderer->render($this->invoice);
        $this->assertStringContainsString('REF-VEND-99', $html);
        $this->assertStringContainsString('3700000000001', $html);
        $this->assertStringContainsString('DE', $html);
    }

    // =========================================================================
    // Locale
    // =========================================================================

    public function testLocaleNl(): void
    {
        $this->renderer->setLocale('nl');
        $html = $this->renderer->render($this->invoice);
        $this->assertStringContainsString('FACTUUR', $html);
        $this->assertStringContainsString('TE BETALEN', $html);
    }

    public function testLocaleEn(): void
    {
        $this->renderer->setLocale('en');
        $html = $this->renderer->render($this->invoice);
        $this->assertStringContainsString('INVOICE', $html);
        $this->assertStringContainsString('AMOUNT DUE', $html);
    }

    // =========================================================================
    // XSS — échappement
    // =========================================================================

    public function testEchappementXss(): void
    {
        // Si un champ contient du HTML, il doit être échappé
        $this->invoice->setInvoiceNote('<script>alert("xss")</script>');
        $html = $this->renderer->render($this->invoice);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    // =========================================================================
    // HTML bien formé
    // =========================================================================

    public function testRenderPageHtmlBienForme(): void
    {
        $html = $this->renderer->renderPage($this->invoice);
        $dom = new \DOMDocument();
        $dom->encoding = 'UTF-8';
        // libxml_use_internal_errors pour éviter les warnings sur les entités HTML5
        libxml_use_internal_errors(true);
        $result = $dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        $this->assertTrue($result, 'Le HTML produit doit être parseable par DOMDocument');
    }

    // =========================================================================
    // setShowAttachments(false)
    // =========================================================================

    public function testMasquerAttachments(): void
    {
        $this->renderer->setShowAttachments(false);
        $html = $this->renderer->render($this->invoice);
        // La section attachments ne doit pas apparaître
        $this->assertStringNotContainsString('Documents joints', $html);
    }

    // =========================================================================
    // Helper
    // =========================================================================

    private function buildInvoice(): \PeppolInvoice
    {
        $invoice = new \PeppolInvoice('FAC-TEST-001', '2025-03-15', '380', 'EUR');
        $invoice->setDueDate('2025-04-15');

        $invoice->setSeller(new Party(
            'Vendeur Test SA',
            new Address("Rue de l'Industrie 1", 'Liège', '4000', 'BE'),
            'BE0123456789', null, null,
            new ElectronicAddress('0208', 'BE0123456789')
        ));
        $invoice->setBuyer(new Party(
            'Acheteur SPRL',
            new Address('Chaussée de Namur 1', 'Namur', '5000', 'BE'),
            'BE0987654321', null, null,
            new ElectronicAddress('0208', 'BE0987654321')
        ));

        $invoice->addInvoiceLine(
            new InvoiceLine('1', 'Article de test', 1.0, 'C62', 100.00, 'S', 21.0)
        );
        $invoice->calculateTotals();

        return $invoice;
    }
}
