<?php

declare(strict_types=1);

namespace Peppol\Formats;

use Peppol\Core\InvoiceBase;
use Peppol\Models\Party;

/**
 * Rendu HTML exhaustif d'une facture Peppol BIS 3.0 / UBL.BE
 *
 * Utilisation :
 *
 *   $renderer = new InvoiceHtmlRenderer();
 *   echo $renderer->render($invoice);
 *
 *   // Page HTML complète (avec <html>, <head>…)
 *   echo $renderer->renderPage($invoice);
 *
 *   // Juste le CSS (pour l'injecter dans votre propre layout)
 *   echo $renderer->getStyles();
 *
 * Options :
 *   $renderer->setLocale('fr');          // 'fr' (défaut) ou 'nl' ou 'en'
 *   $renderer->setShowAttachments(false); // masquer la section pièces jointes
 *
 * @package Peppol\Formats
 */
class InvoiceHtmlRenderer
{
    // =========================================================================
    // Configuration
    // =========================================================================

    private string $locale = 'fr';
    private bool $showAttachments = true;

    /** @var callable|null */
    private $qrCodeUrlCallback = null;

    public function setQrCodeUrlCallback(callable $callback): static
    {
        $this->qrCodeUrlCallback = $callback;
        return $this;
    }
    private static array $labels = [
        'fr' => [
            'invoice' => 'FACTURE',
            'credit_note' => 'AVOIR',
            'prepayment' => "FACTURE D'ACOMPTE",
            'corrective' => 'FACTURE RECTIFICATIVE',
            'debit' => 'FACTURE DE DÉBIT',
            'self_billed' => 'FACTURE AUTO-LIQUIDÉE',
            'emitter' => 'Émetteur · Vendeur',
            'recipient' => 'Destinataire · Acheteur',
            'issue_date' => "Date d'émission",
            'due_date' => "Date d'échéance",
            'delivery_date' => 'Date de livraison',
            'currency' => 'Devise',
            'note' => 'Note',
            'preceding' => 'Référence facture précédente',
            'references' => 'Références',
            'buyer_ref' => 'Réf. acheteur',
            'po_ref' => 'Commande acheteur',
            'so_ref' => 'Commande vendeur',
            'contract' => 'Contrat',
            'project' => 'Projet',
            'despatch' => "Avis d'expéd.",
            'receipt' => 'Avis réception',
            'accounting' => 'Réf. compta',
            'period_start' => 'Période — début',
            'period_end' => 'Période — fin',
            'lines' => 'Détail des prestations',
            'col_num' => '#',
            'col_desc' => 'Désignation',
            'col_qty' => 'Qté',
            'col_unit' => 'U.',
            'col_up' => 'P.U. HT',
            'col_vat' => 'TVA',
            'col_total' => 'Total HT',
            'doc_acs' => 'Remises & majorations document',
            'col_type' => 'Type',
            'col_reason' => 'Motif',
            'col_code' => 'Code',
            'col_vatcat' => 'Cat. TVA',
            'col_amount' => 'Montant',
            'allowance' => '▼ Remise',
            'charge' => '▲ Majoration',
            'vat_breakdown' => 'Ventilation TVA',
            'col_category' => 'Catégorie',
            'col_rate' => 'Taux',
            'col_base' => 'Base HT',
            'col_taxamt' => 'TVA',
            'subtotal_lines' => 'Total brut lignes',
            'total_allow' => 'Total remises',
            'total_charges' => 'Total majorations',
            'total_ht' => 'Total HT',
            'total_vat' => 'Total TVA',
            'total_ttc' => 'Total TTC',
            'prepaid' => 'Acompte versé',
            'payable' => 'NET À PAYER',
            'banking' => 'Coordonnées bancaires',
            'iban' => 'IBAN',
            'bic' => 'BIC / SWIFT',
            'pay_ref' => 'Référence de paiement',
            'pay_code' => 'Mode de paiement',
            'pay_terms' => 'Conditions de paiement',
            'attachments' => 'Documents joints',
            'generated' => 'Document généré le',
            'at' => 'à',
            'seller_ref' => 'Réf. vendeur',
            'buyer_item_ref' => 'Réf. acheteur',
            'gtin' => 'EAN/GTIN',
            'origin' => 'Origine',
            'order_line' => 'Ligne cmd',
            'period' => 'Période',
            'vat_label' => 'TVA',
            'company_id' => 'BCE',
            'peppol' => 'Peppol',
        ],
        'nl' => [
            'invoice' => 'FACTUUR',
            'credit_note' => 'CREDITNOTA',
            'emitter' => 'Afzender · Verkoper',
            'recipient' => 'Ontvanger · Koper',
            'issue_date' => 'Factuurdatum',
            'due_date' => 'Vervaldatum',
            'total_ht' => 'Totaal excl. BTW',
            'total_vat' => 'Totaal BTW',
            'total_ttc' => 'Totaal incl. BTW',
            'payable' => 'TE BETALEN',
            'lines' => 'Factuurlijnen',
            'banking' => 'Bankgegevens',
            'vat_breakdown' => 'BTW-overzicht',
        ],
        'en' => [
            'invoice' => 'INVOICE',
            'credit_note' => 'CREDIT NOTE',
            'emitter' => 'Sender · Seller',
            'recipient' => 'Recipient · Buyer',
            'issue_date' => 'Issue date',
            'due_date' => 'Due date',
            'total_ht' => 'Net amount',
            'total_vat' => 'VAT total',
            'total_ttc' => 'Gross amount',
            'payable' => 'AMOUNT DUE',
            'lines' => 'Line items',
            'banking' => 'Bank details',
            'vat_breakdown' => 'VAT breakdown',
        ],
    ];

    // =========================================================================
    // Configuration publique
    // =========================================================================

    public function setLocale(string $locale): static
    {
        $this->locale = $locale;
        return $this;
    }

    public function setShowAttachments(bool $show): static
    {
        $this->showAttachments = $show;
        return $this;
    }

    // =========================================================================
    // Points d'entrée publics
    // =========================================================================

    /**
     * Retourne uniquement le fragment HTML du document (sans <html>/<head>).
     * À injecter dans votre propre layout.
     */
    public function render(InvoiceBase $invoice): string
    {
        return $this->buildHtml($invoice, fullPage: false);
    }

    /**
     * Retourne une page HTML autonome complète.
     */
    public function renderPage(InvoiceBase $invoice): string
    {
        return $this->buildHtml($invoice, fullPage: true);
    }

    /**
     * Retourne uniquement le CSS (pour l'injecter dans votre <head>).
     */
    public function getStyles(): string
    {
        return '<style>' . $this->css() . '</style>';
    }

    // =========================================================================
    // Construction HTML
    // =========================================================================

    private function buildHtml(InvoiceBase $invoice, bool $fullPage): string
    {
        $l = fn(string $key): string => $this->label($key);

        $seller = $invoice->getSeller();
        $buyer = $invoice->getBuyer();
        $lines = $invoice->getInvoiceLines();
        $payment = $invoice->getPaymentInfo();
        $acs = $invoice->getAllowanceCharges();
        $vats = $invoice->getVatBreakdown();
        $qrUrl = $this->qrCodeUrlCallback !== null
            ? ($this->qrCodeUrlCallback)($invoice)
            : null;
        $cur = $invoice->getDocumentCurrencyCode();

        $typeLabels = [
            '380' => $l('invoice'),
            '381' => $l('credit_note'),
            '386' => $l('prepayment'),
            '384' => $l('corrective'),
            '383' => $l('debit'),
            '389' => $l('self_billed'),
        ];
        $typeLabel = $typeLabels[$invoice->getInvoiceTypeCode()] ?? $l('invoice');

        $badgeClass = match ($invoice->getInvoiceTypeCode()) {
            '380' => 'badge-380',
            '381' => 'badge-381',
            default => 'badge-other',
        };

        $html = '';

        // ── En-tête ──────────────────────────────────────────────
        $html .= '<div class="pep-doc">';
        $html .= '<div class="pep-inner">';

        $html .= '<div class="pep-header">';
        $html .= '<div>';
        $html .= '<div class="pep-type">' . $this->e($typeLabel) . '</div>';
        $html .= '<div class="pep-number">N° ' . $this->e($invoice->getInvoiceNumber()) . '</div>';
        $html .= '<span class="pep-badge ' . $badgeClass . '">Code ' . $this->e($invoice->getInvoiceTypeCode()) . '</span>';
        $html .= '</div>';

        $html .= '<div class="pep-meta-block">';
        $html .= $this->metaRow($l('issue_date'), $this->fmtDate($invoice->getIssueDate()));
        if ($invoice->getDueDate()) {
            $html .= $this->metaRow($l('due_date'), $this->fmtDate($invoice->getDueDate()), 'style="color:var(--pep-accent)"');
        }
        if ($invoice->getDeliveryDate()) {
            $html .= $this->metaRow($l('delivery_date'), $this->fmtDate($invoice->getDeliveryDate()));
        }
        $html .= $this->metaRow($l('currency'), $this->e($cur));
        $html .= '</div>';
        $html .= '</div>'; // .pep-header

        // ── Note facture ─────────────────────────────────────────
        if ($invoice->getInvoiceNote()) {
            $html .= '<div class="pep-note"><strong>' . $l('note') . ' :</strong> ' . $this->e($invoice->getInvoiceNote()) . '</div>';
        }

        // ── Facture précédente ───────────────────────────────────
        if ($invoice->getPrecedingInvoiceNumber()) {
            $html .= '<div class="pep-preceding"><strong>' . $l('preceding') . ' :</strong> N° '
                . $this->e($invoice->getPrecedingInvoiceNumber());
            if ($invoice->getPrecedingInvoiceDate()) {
                $html .= ' du ' . $this->fmtDate($invoice->getPrecedingInvoiceDate());
            }
            $html .= '</div>';
        }

        // ── Parties ──────────────────────────────────────────────
        $html .= '<div class="pep-parties">';
        $html .= $this->renderParty($seller, $l('emitter'), 'seller');
        $html .= $this->renderParty($buyer, $l('recipient'), 'buyer');
        $html .= '</div>';

        // ── Références ───────────────────────────────────────────
        $refs = array_filter([
            $l('buyer_ref') => $invoice->getBuyerReference(),
            $l('po_ref') => $invoice->getPurchaseOrderReference(),
            $l('so_ref') => $invoice->getSalesOrderReference(),
            $l('contract') => $invoice->getContractReference(),
            $l('project') => $invoice->getProjectReference(),
            $l('despatch') => $invoice->getDespatchAdviceReference(),
            $l('receipt') => $invoice->getReceivingAdviceReference(),
            $l('accounting') => $invoice->getBuyerAccountingReference(),
        ]);
        if (!empty($refs)) {
            $html .= '<div class="pep-section-title">' . $l('references') . '</div>';
            $html .= '<div class="pep-refs-grid">';
            foreach ($refs as $label => $value) {
                $html .= $this->refCell($label, $value);
            }
            $html .= '</div>';
        }

        // ── Période de facturation header ────────────────────────
        if ($invoice->getInvoicePeriodStartDate() || $invoice->getInvoicePeriodEndDate()) {
            $html .= '<div class="pep-refs-grid" style="margin-bottom:32px">';
            $html .= $this->refCell($l('period_start'), $this->fmtDate($invoice->getInvoicePeriodStartDate()));
            $html .= $this->refCell($l('period_end'), $this->fmtDate($invoice->getInvoicePeriodEndDate()));
            $html .= '</div>';
        }

        // ── Lignes ───────────────────────────────────────────────
        $html .= '<div class="pep-section-title">' . $l('lines') . '</div>';
        $html .= '<table class="pep-table">';
        $html .= '<thead><tr>'
            . '<th style="width:24px">' . $l('col_num') . '</th>'
            . '<th>' . $l('col_desc') . '</th>'
            . '<th class="r" style="width:55px">' . $l('col_qty') . '</th>'
            . '<th style="width:38px">' . $l('col_unit') . '</th>'
            . '<th class="r" style="width:95px">' . $l('col_up') . '</th>'
            . '<th style="width:52px">' . $l('col_vat') . '</th>'
            . '<th class="r" style="width:95px">' . $l('col_total') . '</th>'
            . '</tr></thead><tbody';


        foreach ($lines as $i => $line) {
            $html .= '<tr>';
            $html .= '<td class="pep-line-num">' . ($i + 1) . '</td>';
            $html .= '<td>';
            $html .= '<div class="pep-line-name">' . $this->e($line->getName()) . '</div>';
            if ($line->getDescription()) {
                $html .= '<div class="pep-line-desc">' . $this->e($line->getDescription()) . '</div>';
            }

            // Métadonnées article
            $metas = array_filter([
                $l('seller_ref') => $line->getSellerItemId(),
                $l('buyer_item_ref') => $line->getBuyerItemId(),
                $l('gtin') => $line->getStandardItemId(),
                ($line->getItemClassificationListId() ?? 'Classif.') => $line->getItemClassificationCode(),
                $l('origin') => $line->getOriginCountryCode(),
                $l('order_line') => $line->getOrderLineReference(),
            ]);
            if ($line->getLinePeriodStartDate()) {
                $metas[$l('period')] = $this->fmtDate($line->getLinePeriodStartDate())
                    . ' → ' . $this->fmtDate($line->getLinePeriodEndDate());
            }
            if (!empty($metas)) {
                $html .= '<div class="pep-line-meta">';
                foreach ($metas as $mk => $mv) {
                    $html .= '<span>' . $this->e((string) $mk) . ' : ' . $this->e((string) $mv) . '</span>';
                }
                $html .= '</div>';
            }

            if ($line->getLineNote()) {
                $html .= '<div class="pep-line-note">💬 ' . $this->e($line->getLineNote()) . '</div>';
            }

            foreach ($line->getLineAllowanceCharges() as $lac) {
                $html .= '<div class="pep-line-ac">'
                    . ($lac->isAllowance() ? $l('allowance') : $l('charge'))
                    . ($lac->getReason() ? ' (' . $this->e($lac->getReason()) . ')' : '')
                    . ' : −' . $this->fmt($lac->getAmount(), $cur)
                    . '</div>';
            }
            $html .= '</td>';

            $html .= '<td class="r">' . number_format($line->getQuantity(), 2, ',', ' ') . '</td>';
            $html .= '<td class="pep-muted">' . $this->e($line->getUnitCode()) . '</td>';
            $html .= '<td class="r">' . $this->fmt($line->getUnitPrice(), $cur) . '</td>';
            $html .= '<td class="pep-muted">'
                . $this->e($line->getVatCategory())
                . ($line->getVatRate() > 0 ? ' ' . number_format($line->getVatRate(), 0) . '%' : '')
                . '</td>';
            $html .= '<td class="r pep-strong">' . $this->fmt($line->getLineAmount(), $cur) . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';

        // ── Remises/majorations document ─────────────────────────
        if (!empty($acs)) {
            $html .= '<div style="margin-top:24px">';
            $html .= '<div class="pep-section-title">' . $l('doc_acs') . '</div>';
            $html .= '<table class="pep-table pep-table-sm"><thead><tr>'
                . '<th>' . $l('col_type') . '</th>'
                . '<th>' . $l('col_reason') . '</th>'
                . '<th>' . $l('col_code') . '</th>'
                . '<th>' . $l('col_vatcat') . '</th>'
                . '<th class="r">' . $l('col_amount') . '</th>'
                . '</tr></thead><tbody>';
            foreach ($acs as $ac) {
                $html .= '<tr>'
                    . '<td>' . ($ac->isAllowance() ? $l('allowance') : $l('charge')) . '</td>'
                    . '<td>' . $this->e($ac->getReason() ?? '—') . '</td>'
                    . '<td>' . $this->e($ac->getReasonCode() ?? '—') . '</td>'
                    . '<td>' . $this->e($ac->getVatCategory())
                    . ($ac->getVatRate() > 0 ? ' ' . number_format($ac->getVatRate(), 0) . '%' : '') . '</td>'
                    . '<td class="r pep-accent">' . $this->fmt($ac->getAmount(), $cur) . '</td>'
                    . '</tr>';
            }
            $html .= '</tbody></table></div>';
        }

        // ── Totaux ───────────────────────────────────────────────
        $html .= '<div class="pep-totals">';

        // Ventilation TVA
        $html .= '<div>';
        $html .= '<div class="pep-section-title">' . $l('vat_breakdown') . '</div>';
        $html .= '<table class="pep-vat-table"><thead><tr>'
            . '<th>' . $l('col_category') . '</th>'
            . '<th>' . $l('col_rate') . '</th>'
            . '<th class="r">' . $l('col_base') . '</th>'
            . '<th class="r">' . $l('col_taxamt') . '</th>'
            . '</tr></thead><tbody>';
        foreach ($vats as $vat) {
            $html .= '<tr>'
                . '<td>' . $this->e($vat->getCategory()) . '</td>'
                . '<td>' . number_format($vat->getRate(), 2, ',', '') . '%</td>'
                . '<td class="r pep-mono">' . $this->fmt($vat->getTaxableAmount(), $cur) . '</td>'
                . '<td class="r pep-mono pep-strong">' . $this->fmt($vat->getTaxAmount(), $cur) . '</td>'
                . '</tr>';
        }
        $html .= '</tbody></table></div>';

        // Récapitulatif montants
        $html .= '<div class="pep-amounts">';
        $html .= $this->amountRow($l('subtotal_lines'), $this->fmt($invoice->getSumOfLineNetAmounts(), $cur));
        if ($invoice->getSumOfAllowances() > 0) {
            $html .= $this->amountRow('− ' . $l('total_allow'), $this->fmt($invoice->getSumOfAllowances(), $cur), 'pep-row-allow');
        }
        if ($invoice->getSumOfCharges() > 0) {
            $html .= $this->amountRow('+ ' . $l('total_charges'), $this->fmt($invoice->getSumOfCharges(), $cur), 'pep-row-charge');
        }
        $html .= $this->amountRow($l('total_ht'), $this->fmt($invoice->getTaxExclusiveAmount(), $cur), 'pep-row-ht');
        $html .= $this->amountRow($l('total_vat'), $this->fmt($invoice->getTotalVatAmount(), $cur));
        $html .= $this->amountRow($l('total_ttc'), $this->fmt($invoice->getTaxInclusiveAmount(), $cur), 'pep-row-ttc');
        if ($invoice->getPrepaidAmount() > 0) {
            $html .= $this->amountRow('− ' . $l('prepaid'), $this->fmt($invoice->getPrepaidAmount(), $cur), 'pep-row-prepaid');
        }
        $html .= $this->amountRow($l('payable'), $this->fmt($invoice->getPayableAmount(), $cur), 'pep-row-payable');
        $html .= '</div>';
        $html .= '</div>'; // .pep-totals

        // ── Paiement ─────────────────────────────────────────────
        if ($payment?->getIban() || $invoice->getPaymentTerms()) {

            $html .= '<div class="pep-payment">';
            if ($invoice->getPaymentTerms()) {
                $html .= '<div class="pep-section-title" style="margin-top:20px">' . $l('pay_terms') . '</div>';
                $html .= '<div class="pep-pay-block"><div class="pep-terms">'
                    . nl2br($this->e($invoice->getPaymentTerms()))
                    . '</div></div>';
            }
            // Colonne gauche — IBAN et conditions empilés verticalement
            $html .= '<div class="pep-payment-left">';

            if ($payment?->getIban()) {
                $html .= '<div class="pep-section-title">' . $l('banking') . '</div>';
                $html .= '<div class="pep-pay-block">';
                // QR seul, float à droite
                if ($qrUrl !== null) {
                    $html .= '<div style="flex:0 0 170px;display:flex;flex-direction:column;align-items:center;justify-content:center;float:right;">';
                    $html .= '<img src="' . $this->e($qrUrl) . '" alt="QR code paiement" style="width:150px;height:150px;display:block">';
                    $html .= '<div style="font-size:10px;color:var(--pep-muted);letter-spacing:.5px;text-transform:uppercase;margin-top:8px;text-align:center">Scannez pour payer</div>';
                    $html .= '</div>';
                }
                $html .= $this->payRow($l('iban'), $payment->getIban());
                if ($payment->getBic()) {
                    $html .= $this->payRow($l('bic'), $payment->getBic());
                }
                if ($payment->getPaymentReference()) {
                    $html .= $this->payRow($l('pay_ref'), $payment->getPaymentReference());
                }
                $html .= $this->payRow($l('pay_code'), $payment->getPaymentMeansCode());
                $html .= '</div>';
            }

            $html .= '</div>'; // .pep-payment-left

            $html .= '</div>'; // .pep-payment
        }

        // ── Pièces jointes ───────────────────────────────────────
        if ($this->showAttachments) {
            $docs = $invoice->getAttachedDocuments();
            if (!empty($docs)) {
                $html .= '<div style="margin-top:32px">';
                $html .= '<div class="pep-section-title">' . $l('attachments') . '</div>';
                $html .= '<div class="pep-refs-grid">';
                foreach ($docs as $doc) {
                    $html .= '<div class="pep-ref-cell">';
                    $html .= '<div class="pep-ref-label">' . $this->e($doc->getMimeType()) . '</div>';
                    $html .= '<div class="pep-ref-value">📎 ' . $this->e($doc->getFilename()) . '</div>';
                    if ($doc->getDescription()) {
                        $html .= '<div class="pep-muted" style="font-size:12px;margin-top:3px">'
                            . $this->e($doc->getDescription()) . '</div>';
                    }
                    $html .= '</div>';
                }
                $html .= '</div></div>';
            }
        }

        // ── Footer ───────────────────────────────────────────────
        $html .= '<div class="pep-footer">';
        $footerLeft = array_filter([
            $seller?->getName(),
            $seller?->getVatId() ? $l('vat_label') . ' ' . $seller->getVatId() : null,
            $seller?->getCompanyLegalForm(),
        ]);
        $html .= '<div class="pep-footer-legal">' . $this->e(implode(' · ', $footerLeft)) . '</div>';
        $html .= '<div>' . $l('generated') . ' ' . date('d/m/Y') . ' ' . $l('at') . ' ' . date('H:i') . ' · Peppol UBL 2.1</div>';
        $html .= '</div>';

        $html .= '</div></div>'; // .pep-inner / .pep-doc

        if (!$fullPage) {
            return '<style>' . $this->css() . '</style>' . $html;
        }

        return '<!DOCTYPE html><html lang="' . $this->e($this->locale) . '">'
            . '<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>' . $this->e($invoice->getInvoiceNumber()) . '</title>'
            . '<link rel="preconnect" href="https://fonts.googleapis.com">'
            . '<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">'
            . '<style>' . $this->css(standalone: true) . '</style>'
            . '</head><body class="pep-body">'
            . $html
            . '</body></html>';
    }

    // =========================================================================
    // Helpers HTML
    // =========================================================================

    private function renderParty(?Party $party, string $role, string $side): string
    {
        $html = '<div class="pep-party pep-party-' . $side . '">';
        $html .= '<div class="pep-party-role">' . $this->e($role) . '</div>';
        $html .= '<div class="pep-party-name">' . $this->e($party?->getName() ?? '—') . '</div>';

        if ($party?->getAddress()) {
            $a = $party->getAddress();
            $parts = array_filter([
                $a->getStreetName(),
                $a->getAdditionalStreetName(),
                trim($a->getPostalZone() . ' ' . $a->getCityName()),
                $a->getCountryCode(),
            ]);
            $html .= '<div class="pep-party-addr">' . implode('<br>', array_map([$this, 'e'], $parts)) . '</div>';
        }

        foreach (array_filter([
            $this->label('vat_label') => $party?->getVatId(),
            $this->label('company_id') => $party?->getCompanyId(),
            $party?->getCompanyLegalForm() ? '' : null => $party?->getCompanyLegalForm(),
            '✉' => $party?->getEmail(),
        ]) as $tagLabel => $tagValue) {
            if ($tagValue) {
                $html .= '<span class="pep-tag">'
                    . ($tagLabel ? $this->e($tagLabel) . ' : ' : '')
                    . $this->e($tagValue) . '</span>';
            }
        }

        if ($party?->getElectronicAddress()) {
            $ea = $party->getElectronicAddress();
            $html .= '<span class="pep-tag">'
                . $this->label('peppol') . ' ' . $this->e($ea->getSchemeId())
                . ' : ' . $this->e($ea->getIdentifier()) . '</span>';
        }

        $html .= '</div>';
        return $html;
    }

    private function metaRow(string $label, string $value, string $valueAttr = ''): string
    {
        return '<div class="pep-meta-row">'
            . '<span class="pep-meta-label">' . $this->e($label) . '</span>'
            . '<span class="pep-meta-value" ' . $valueAttr . '>' . $value . '</span>'
            . '</div>';
    }

    private function refCell(string $label, ?string $value): string
    {
        return '<div class="pep-ref-cell">'
            . '<div class="pep-ref-label">' . $this->e($label) . '</div>'
            . '<div class="pep-ref-value">' . $this->e($value ?? '—') . '</div>'
            . '</div>';
    }

    private function amountRow(string $label, string $value, string $extraClass = ''): string
    {
        return '<div class="pep-amount-row ' . $extraClass . '">'
            . '<span class="pep-amount-label">' . $this->e($label) . '</span>'
            . '<span class="pep-amount-value pep-mono">' . $value . '</span>'
            . '</div>';
    }

    private function payRow(string $label, string $value): string
    {
        return '<div class="pep-pay-label">' . $this->e($label) . '</div>'
            . '<div class="pep-pay-value pep-mono">' . $this->e($value) . '</div>';
    }

    // =========================================================================
    // Helpers utilitaires
    // =========================================================================

    private function label(string $key): string
    {
        return $this->e(
            self::$labels[$this->locale][$key]
            ?? self::$labels['fr'][$key]
            ?? $key
        );
    }

    private function e(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function fmt(float $amount, string $currency): string
    {
        return number_format($amount, 2, ',', ' ') . '&nbsp;' . $currency;
    }

    private function fmtDate(?string $date): string
    {
        if (!$date)
            return '—';
        try {
            return (new \DateTime($date))->format('d/m/Y');
        } catch (\Exception) {
            return $this->e($date);
        }
    }

    // =========================================================================
    // CSS
    // =========================================================================

    private function css(bool $standalone = false): string
    {
        $bodyRules = $standalone
            ? 'body.pep-body{font-family:"DM Sans",sans-serif;font-weight:300;font-size:13.5px;line-height:1.6;background:#faf9f7;color:#1a1814;padding:40px 20px;max-width:1200px;margin:0 auto;}'
            : '';

        return $bodyRules . '
:root{
  --pep-ink:#1a1814;--pep-muted:#6b6560;--pep-rule:#d4cec8;
  --pep-bg:#faf9f7;--pep-bg-alt:#f2efe9;--pep-accent:#b5451b;
  --pep-green:#2d6a4f;--pep-white:#ffffff;
}
.pep-doc{width:100%;max-width:1100px;margin:0 auto;background:var(--pep-white);
  box-shadow:0 2px 20px rgba(26,24,20,.08);border-top:4px solid var(--pep-accent);
  font-family:"DM Sans",sans-serif;font-weight:300;font-size:13.5px;
  line-height:1.6;color:var(--pep-ink);}
.pep-inner{padding:56px 64px;}
/* Header */
.pep-header{display:grid;grid-template-columns:1fr auto;gap:32px;align-items:start;
  margin-bottom:48px;padding-bottom:32px;border-bottom:1px solid var(--pep-rule);}
.pep-type{font-family:"Playfair Display",serif;font-size:36px;font-weight:700;
  letter-spacing:-.5px;color:var(--pep-accent);line-height:1;margin-bottom:6px;}
.pep-number{font-size:13px;color:var(--pep-muted);letter-spacing:.5px;text-transform:uppercase;}
.pep-badge{display:inline-block;padding:2px 10px;border-radius:2px;font-size:11px;
  font-weight:500;letter-spacing:.5px;text-transform:uppercase;margin-top:10px;}
.badge-380{background:#e8f4fd;color:#1565c0;}
.badge-381{background:#fff3e0;color:#e65100;}
.badge-other{background:var(--pep-bg-alt);color:var(--pep-muted);}
.pep-meta-block{text-align:right;}
.pep-meta-row{display:flex;justify-content:flex-end;gap:12px;margin-bottom:3px;}
.pep-meta-label{color:var(--pep-muted);font-size:11px;text-transform:uppercase;letter-spacing:.6px;padding-top:1px;}
.pep-meta-value{font-weight:500;font-size:13.5px;}
/* Note & preceding */
.pep-note{background:#fffbf0;border-left:3px solid #f0c040;padding:14px 18px;
  margin-bottom:32px;font-size:13px;color:#7a6000;}
.pep-preceding{background:#f0f4ff;border-left:3px solid #3b5bdb;padding:12px 18px;
  margin-bottom:32px;font-size:12.5px;color:#1e3a8a;}
/* Parties */
.pep-parties{display:grid;grid-template-columns:1fr 1fr;gap:32px;margin-bottom:40px;}
.pep-party{padding:24px;background:var(--pep-bg-alt);position:relative;}
.pep-party::before{content:"";position:absolute;top:0;left:0;width:3px;height:100%;}
.pep-party-seller::before{background:var(--pep-accent);}
.pep-party-buyer::before{background:var(--pep-green);}
.pep-party-role{font-size:10px;font-weight:500;letter-spacing:1.2px;text-transform:uppercase;
  color:var(--pep-muted);margin-bottom:8px;}
.pep-party-name{font-family:"Playfair Display",serif;font-size:17px;font-weight:600;
  margin-bottom:8px;line-height:1.2;}
.pep-party-addr{font-size:12.5px;line-height:1.7;margin-bottom:4px;}
.pep-tag{display:inline-block;margin-top:4px;margin-right:4px;font-size:11px;
  color:var(--pep-muted);background:rgba(0,0,0,.05);padding:2px 8px;border-radius:2px;}
/* Références */
.pep-refs-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));
  gap:1px;background:var(--pep-rule);border:1px solid var(--pep-rule);margin-bottom:32px;}
.pep-ref-cell{background:var(--pep-white);padding:14px 18px;}
.pep-ref-label{font-size:10px;font-weight:500;letter-spacing:.8px;text-transform:uppercase;
  color:var(--pep-muted);margin-bottom:3px;}
.pep-ref-value{font-size:13px;font-weight:500;word-break:break-all;}
/* Section title */
.pep-section-title{font-family:"Playfair Display",serif;font-size:13px;font-weight:600;
  letter-spacing:.8px;text-transform:uppercase;color:var(--pep-muted);
  margin-bottom:12px;padding-bottom:6px;border-bottom:1px solid var(--pep-rule);}
/* Tables */
.pep-table{width:100%;border-collapse:collapse;font-size:13px;margin-bottom:0;}
.pep-table thead tr{background:var(--pep-ink);color:var(--pep-white);}
.pep-table th{padding:10px 14px;font-weight:500;font-size:11px;letter-spacing:.6px;
  text-transform:uppercase;text-align:left;}
.pep-table th.r,.pep-table td.r{text-align:right;}
.pep-table tbody tr{border-bottom:1px solid var(--pep-rule);}
.pep-table tbody tr:last-child{border-bottom:none;}
.pep-table tbody tr:nth-child(even){background:var(--pep-bg-alt);}
.pep-table td{padding:13px 14px;vertical-align:top;}
.pep-table-sm td,.pep-table-sm th{padding:8px 14px;}
.pep-line-num{color:var(--pep-muted);font-size:11px;}
.pep-line-name{font-weight:500;margin-bottom:2px;}
.pep-line-desc{font-size:12px;color:var(--pep-muted);}
.pep-line-meta{font-size:11px;color:var(--pep-muted);margin-top:4px;}
.pep-line-meta span{margin-right:10px;}
.pep-line-note{font-size:12px;color:var(--pep-ink);font-style:italic;margin-top:4px;}
.pep-line-ac{font-size:11.5px;color:var(--pep-accent);margin-top:3px;}
/* Totaux */
.pep-totals{display:grid;grid-template-columns:1fr 320px;gap:40px;
  margin-top:32px;padding-top:24px;border-top:1px solid var(--pep-rule);}
.pep-vat-table{width:100%;border-collapse:collapse;font-size:12.5px;}
.pep-vat-table th{text-align:left;font-size:10px;font-weight:500;letter-spacing:.7px;
  text-transform:uppercase;color:var(--pep-muted);padding:0 10px 8px 0;
  border-bottom:1px solid var(--pep-rule);}
.pep-vat-table td{padding:7px 10px 7px 0;border-bottom:1px solid var(--pep-rule);}
.pep-vat-table tr:last-child td{border-bottom:none;}
.pep-amounts{}
.pep-amount-row{display:flex;justify-content:space-between;align-items:baseline;
  padding:7px 0;border-bottom:1px solid var(--pep-rule);font-size:13px;}
.pep-amount-row:last-child{border-bottom:none;}
.pep-amount-label{color:var(--pep-muted);}
.pep-row-allow .pep-amount-value{color:var(--pep-accent);}
.pep-row-charge .pep-amount-value{color:var(--pep-green);}
.pep-row-prepaid .pep-amount-label,.pep-row-prepaid .pep-amount-value{color:var(--pep-muted);font-style:italic;}
.pep-row-ht{font-weight:500;background:var(--pep-bg-alt);padding:8px 10px !important;margin:4px 0;}
.pep-row-ttc{font-family:"Playfair Display",serif;font-size:18px;font-weight:700;
  padding:12px 0 4px !important;border-bottom:none !important;}
.pep-row-payable{background:var(--pep-ink);color:var(--pep-white) !important;
  padding:14px 16px !important;font-size:15px;font-weight:500;margin-top:8px;border-bottom:none !important;}
.pep-row-payable .pep-amount-label,.pep-row-payable .pep-amount-value{color:var(--pep-white);}
/* Paiement */
.pep-payment{margin-top:40px;padding-top:24px;border-top:1px solid var(--pep-rule);
  display:flex;flex-direction:row;gap:40px;align-items:flex-start;}
.pep-payment-left{flex:1 1 auto;min-width:0;}
.pep-pay-block{background:var(--pep-bg-alt);padding:20px 24px;}
.pep-pay-label{font-size:10px;font-weight:500;letter-spacing:1px;text-transform:uppercase;
  color:var(--pep-muted);margin-bottom:4px;margin-top:12px;}
.pep-pay-label:first-child{margin-top:0;}
.pep-pay-value{font-size:13px;font-weight:500;word-break:break-all;}
.pep-terms{font-size:12.5px;line-height:1.6;}
/* Footer */
.pep-footer{margin-top:48px;padding-top:20px;border-top:1px solid var(--pep-rule);
  display:flex;justify-content:space-between;align-items:center;
  font-size:11px;color:var(--pep-muted);}
.pep-footer-legal{font-family:"Playfair Display",serif;font-size:11px;}
/* Utilitaires */
.pep-muted{color:var(--pep-muted);}
.pep-strong{font-weight:500;}
.pep-accent{color:var(--pep-accent);}
.pep-mono{font-variant-numeric:tabular-nums;}
@media print{
  .pep-doc{box-shadow:none;border-top:3px solid var(--pep-accent);}
  .pep-inner{padding:32px 40px;}
}';
    }
}
