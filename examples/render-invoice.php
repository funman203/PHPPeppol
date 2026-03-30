<?php
/**
 * Exemple d'utilisation de InvoiceHtmlRenderer
 * À placer dans examples/render-invoice.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Peppol\Formats\InvoiceHtmlRenderer;

// ── Chargement ────────────────────────────────────────────────
$xmlPath = $argv[1] ?? null;

if ($xmlPath === null || !file_exists($xmlPath)) {
    fwrite(STDERR, "Usage : php examples/render-invoice.php chemin/vers/facture.xml\n");
    exit(1);
}

try {
    $invoice = PeppolInvoice::fromXml($xmlPath, strict: false);
} catch (\Peppol\Exceptions\ImportWarningException $e) {
    $invoice = $e->getInvoice();
} catch (\Exception $e) {
    fwrite(STDERR, "Erreur : " . $e->getMessage() . "\n");
    exit(1);
}

// ── Rendu ─────────────────────────────────────────────────────
$renderer = new InvoiceHtmlRenderer();
// $renderer->setLocale('nl');          // optionnel
// $renderer->setShowAttachments(false); // optionnel

// Page HTML autonome
echo $renderer->renderPage($invoice);

// ── Ou dans votre propre layout ───────────────────────────────
// echo '<div class="my-layout">';
// echo $renderer->render($invoice);   // fragment + CSS inline
// echo '</div>';
