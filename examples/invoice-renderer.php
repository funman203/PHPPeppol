<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Exemple : rendu HTML exhaustif d'une facture Peppol depuis un XML UBL 2.1
 *
 * Usage :
 *   php invoice-renderer.php facture.xml
 *   php invoice-renderer.php facture.xml > facture.html
 */

// ============================================================
// 1. Chargement de la facture
// ============================================================

$xmlPath = $argv[1] ?? null;

if ($xmlPath === null) {
    // Facture de démonstration si aucun fichier fourni
    $invoice = buildDemoInvoice();
} elseif (!file_exists($xmlPath)) {
    fwrite(STDERR, "Erreur : fichier « $xmlPath » introuvable.\n");
    exit(1);
} else {
    try {
        $invoice = PeppolInvoice::fromXml($xmlPath, strict: false);
    } catch (\Peppol\Exceptions\ImportWarningException $e) {
        $invoice = $e->getInvoice();
        fwrite(STDERR, "Avertissements d'import : " . implode(', ', $e->getWarnings()) . "\n");
    } catch (\Exception $e) {
        fwrite(STDERR, "Erreur d'import : " . $e->getMessage() . "\n");
        exit(1);
    }
}

// ============================================================
// 2. Extraction des données
// ============================================================

$seller  = $invoice->getSeller();
$buyer   = $invoice->getBuyer();
$lines   = $invoice->getInvoiceLines();
$payment = $invoice->getPaymentInfo();
$acs     = $invoice->getAllowanceCharges();
$vats    = $invoice->getVatBreakdown();
$cur     = $invoice->getDocumentCurrencyCode();

$typeLabels = [
    '380' => 'FACTURE',
    '381' => 'AVOIR',
    '386' => "FACTURE D'ACOMPTE",
    '384' => 'FACTURE RECTIFICATIVE',
    '383' => 'FACTURE DE DÉBIT',
    '389' => 'FACTURE AUTO-LIQUIDÉE',
];
$typeLabel = $typeLabels[$invoice->getInvoiceTypeCode()] ?? 'DOCUMENT';

function fmt(float $amount, string $currency = 'EUR'): string {
    return number_format($amount, 2, ',', ' ') . ' ' . $currency;
}

function fmtDate(?string $date): string {
    if (!$date) return '—';
    try {
        return (new \DateTime($date))->format('d/m/Y');
    } catch (\Exception $e) {
        return $date;
    }
}

function esc(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function addr($party): string {
    if (!$party) return '';
    $a = $party->getAddress();
    if (!$a) return '';
    $parts = array_filter([
        $a->getStreetName(),
        $a->getAdditionalStreetName(),
        trim($a->getPostalZone() . ' ' . $a->getCityName()),
        $a->getCountryCode(),
    ]);
    return implode('<br>', array_map('esc', $parts));
}

// ============================================================
// 3. Rendu HTML
// ============================================================
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= esc($typeLabel) ?> <?= esc($invoice->getInvoiceNumber()) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
  :root {
    --ink:       #1a1814;
    --ink-light: #6b6560;
    --rule:      #d4cec8;
    --bg:        #faf9f7;
    --bg-alt:    #f2efe9;
    --accent:    #b5451b;
    --accent-2:  #2d6a4f;
    --white:     #ffffff;
    --shadow:    0 2px 20px rgba(26,24,20,.08);
  }

  * { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    font-family: 'DM Sans', sans-serif;
    font-weight: 300;
    font-size: 13.5px;
    line-height: 1.6;
    background: var(--bg);
    color: var(--ink);
    padding: 40px 20px;
  }

  /* ── Page ── */
  .page {
    max-width: 860px;
    margin: 0 auto;
    background: var(--white);
    box-shadow: var(--shadow);
    border-top: 4px solid var(--accent);
  }

  .page-inner { padding: 56px 64px; }

  /* ── Header ── */
  .doc-header {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 32px;
    align-items: start;
    margin-bottom: 48px;
    padding-bottom: 32px;
    border-bottom: 1px solid var(--rule);
  }

  .doc-type {
    font-family: 'Playfair Display', serif;
    font-size: 36px;
    font-weight: 700;
    letter-spacing: -.5px;
    color: var(--accent);
    line-height: 1;
    margin-bottom: 6px;
  }

  .doc-number {
    font-size: 13px;
    color: var(--ink-light);
    letter-spacing: .5px;
    text-transform: uppercase;
  }

  .doc-meta-block {
    text-align: right;
  }

  .meta-row {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    margin-bottom: 3px;
  }

  .meta-label {
    color: var(--ink-light);
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: .6px;
    padding-top: 1px;
  }

  .meta-value {
    font-weight: 500;
    font-size: 13.5px;
  }

  .badge {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 2px;
    font-size: 11px;
    font-weight: 500;
    letter-spacing: .5px;
    text-transform: uppercase;
  }

  .badge-380 { background: #e8f4fd; color: #1565c0; }
  .badge-381 { background: #fff3e0; color: #e65100; }
  .badge-other { background: var(--bg-alt); color: var(--ink-light); }

  /* ── Parties ── */
  .parties {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 32px;
    margin-bottom: 40px;
  }

  .party-block {
    padding: 24px;
    background: var(--bg-alt);
    position: relative;
  }

  .party-block::before {
    content: '';
    position: absolute;
    top: 0; left: 0;
    width: 3px;
    height: 100%;
  }

  .party-seller::before { background: var(--accent); }
  .party-buyer::before  { background: var(--accent-2); }

  .party-role {
    font-size: 10px;
    font-weight: 500;
    letter-spacing: 1.2px;
    text-transform: uppercase;
    color: var(--ink-light);
    margin-bottom: 8px;
  }

  .party-name {
    font-family: 'Playfair Display', serif;
    font-size: 17px;
    font-weight: 600;
    margin-bottom: 8px;
    line-height: 1.2;
  }

  .party-detail {
    font-size: 12.5px;
    color: var(--ink);
    line-height: 1.7;
  }

  .party-tag {
    display: inline-block;
    margin-top: 8px;
    font-size: 11px;
    color: var(--ink-light);
    background: rgba(0,0,0,.05);
    padding: 2px 8px;
    border-radius: 2px;
  }

  /* ── Références ── */
  .refs-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 1px;
    background: var(--rule);
    border: 1px solid var(--rule);
    margin-bottom: 40px;
  }

  .ref-cell {
    background: var(--white);
    padding: 14px 18px;
  }

  .ref-label {
    font-size: 10px;
    font-weight: 500;
    letter-spacing: .8px;
    text-transform: uppercase;
    color: var(--ink-light);
    margin-bottom: 3px;
  }

  .ref-value {
    font-size: 13px;
    font-weight: 500;
    color: var(--ink);
    word-break: break-all;
  }

  .ref-value.empty { color: var(--rule); font-style: italic; font-weight: 300; }

  /* ── Note ── */
  .note-block {
    background: #fffbf0;
    border-left: 3px solid #f0c040;
    padding: 14px 18px;
    margin-bottom: 32px;
    font-size: 13px;
    color: #7a6000;
  }

  /* ── Section title ── */
  .section-title {
    font-family: 'Playfair Display', serif;
    font-size: 13px;
    font-weight: 600;
    letter-spacing: .8px;
    text-transform: uppercase;
    color: var(--ink-light);
    margin-bottom: 12px;
    padding-bottom: 6px;
    border-bottom: 1px solid var(--rule);
  }

  /* ── Lines table ── */
  .lines-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 0;
    font-size: 13px;
  }

  .lines-table thead tr {
    background: var(--ink);
    color: var(--white);
  }

  .lines-table th {
    padding: 10px 14px;
    font-weight: 500;
    font-size: 11px;
    letter-spacing: .6px;
    text-transform: uppercase;
    text-align: left;
  }

  .lines-table th.right,
  .lines-table td.right { text-align: right; }

  .lines-table tbody tr { border-bottom: 1px solid var(--rule); }
  .lines-table tbody tr:last-child { border-bottom: none; }
  .lines-table tbody tr:nth-child(even) { background: var(--bg-alt); }

  .lines-table td { padding: 13px 14px; vertical-align: top; }

  .line-name { font-weight: 500; margin-bottom: 2px; }
  .line-desc { font-size: 12px; color: var(--ink-light); }
  .line-meta { font-size: 11px; color: var(--ink-light); margin-top: 4px; }
  .line-meta span { margin-right: 10px; }

  .line-allowance {
    font-size: 11.5px;
    color: var(--accent);
    margin-top: 3px;
  }

  /* ── Totals ── */
  .totals-section {
    display: grid;
    grid-template-columns: 1fr 320px;
    gap: 40px;
    margin-top: 32px;
    padding-top: 24px;
    border-top: 1px solid var(--rule);
  }

  .vat-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12.5px;
  }

  .vat-table th {
    text-align: left;
    font-size: 10px;
    font-weight: 500;
    letter-spacing: .7px;
    text-transform: uppercase;
    color: var(--ink-light);
    padding: 0 10px 8px 0;
    border-bottom: 1px solid var(--rule);
  }

  .vat-table td {
    padding: 7px 10px 7px 0;
    border-bottom: 1px solid var(--rule);
    font-variant-numeric: tabular-nums;
  }

  .vat-table tr:last-child td { border-bottom: none; }

  .amounts-block { }

  .amount-row {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    padding: 7px 0;
    border-bottom: 1px solid var(--rule);
    font-size: 13px;
  }

  .amount-row:last-child { border-bottom: none; }

  .amount-label { color: var(--ink-light); }

  .amount-value {
    font-variant-numeric: tabular-nums;
    font-weight: 400;
  }

  .amount-row.allowance .amount-value { color: var(--accent); }
  .amount-row.charge    .amount-value { color: var(--accent-2); }
  .amount-row.prepaid   .amount-value { color: var(--ink-light); font-style: italic; }

  .amount-row.total-ht {
    font-weight: 500;
    background: var(--bg-alt);
    padding: 8px 10px;
    margin: 4px 0;
  }

  .amount-row.total-ttc {
    font-family: 'Playfair Display', serif;
    font-size: 18px;
    font-weight: 700;
    padding: 12px 0 4px;
    border-bottom: none;
  }

  .amount-row.payable {
    background: var(--ink);
    color: var(--white);
    padding: 14px 16px;
    font-size: 15px;
    font-weight: 500;
    margin-top: 8px;
  }

  .amount-row.payable .amount-label,
  .amount-row.payable .amount-value { color: var(--white); }

  /* ── Payment ── */
  .payment-section {
    margin-top: 40px;
    padding-top: 24px;
    border-top: 1px solid var(--rule);
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 32px;
  }

  .payment-block {
    background: var(--bg-alt);
    padding: 20px 24px;
  }

  .payment-label {
    font-size: 10px;
    font-weight: 500;
    letter-spacing: 1px;
    text-transform: uppercase;
    color: var(--ink-light);
    margin-bottom: 6px;
  }

  .payment-value {
    font-family: 'DM Mono', 'Courier New', monospace;
    font-size: 13px;
    font-weight: 500;
    letter-spacing: .3px;
    word-break: break-all;
  }

  .terms-text {
    font-size: 12.5px;
    color: var(--ink);
    line-height: 1.6;
  }

  /* ── Footer ── */
  .doc-footer {
    margin-top: 48px;
    padding-top: 20px;
    border-top: 1px solid var(--rule);
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 11px;
    color: var(--ink-light);
  }

  .footer-legal {
    font-family: 'Playfair Display', serif;
    font-size: 11px;
    color: var(--ink-light);
  }

  /* ── Preceding invoice ── */
  .preceding-block {
    background: #f0f4ff;
    border-left: 3px solid #3b5bdb;
    padding: 12px 18px;
    margin-bottom: 32px;
    font-size: 12.5px;
    color: #1e3a8a;
  }

  @media print {
    body { background: white; padding: 0; }
    .page { box-shadow: none; border-top: 3px solid var(--accent); }
    .page-inner { padding: 32px 40px; }
  }
</style>
</head>
<body>
<div class="page">
<div class="page-inner">

<!-- ══════════════════════════════════════════════
     EN-TÊTE
══════════════════════════════════════════════ -->
<div class="doc-header">
  <div>
    <div class="doc-type"><?= esc($typeLabel) ?></div>
    <div class="doc-number">N° <?= esc($invoice->getInvoiceNumber()) ?></div>
    <?php
      $badgeClass = match($invoice->getInvoiceTypeCode()) {
        '380' => 'badge-380',
        '381' => 'badge-381',
        default => 'badge-other'
      };
    ?>
    <span class="badge <?= $badgeClass ?>" style="margin-top:10px;">
      Code <?= esc($invoice->getInvoiceTypeCode()) ?>
    </span>
  </div>

  <div class="doc-meta-block">
    <div class="meta-row">
      <span class="meta-label">Date d'émission</span>
      <span class="meta-value"><?= fmtDate($invoice->getIssueDate()) ?></span>
    </div>
    <?php if ($invoice->getDueDate()): ?>
    <div class="meta-row">
      <span class="meta-label">Date d'échéance</span>
      <span class="meta-value" style="color:var(--accent);"><?= fmtDate($invoice->getDueDate()) ?></span>
    </div>
    <?php endif; ?>
    <?php if ($invoice->getDeliveryDate()): ?>
    <div class="meta-row">
      <span class="meta-label">Date de livraison</span>
      <span class="meta-value"><?= fmtDate($invoice->getDeliveryDate()) ?></span>
    </div>
    <?php endif; ?>
    <div class="meta-row" style="margin-top:6px;">
      <span class="meta-label">Devise</span>
      <span class="meta-value"><?= esc($cur) ?></span>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════
     NOTE DE FACTURE
══════════════════════════════════════════════ -->
<?php if ($invoice->getInvoiceNote()): ?>
<div class="note-block">
  <strong>Note :</strong> <?= esc($invoice->getInvoiceNote()) ?>
</div>
<?php endif; ?>

<!-- ══════════════════════════════════════════════
     RÉFÉRENCE FACTURE PRÉCÉDENTE
══════════════════════════════════════════════ -->
<?php if ($invoice->getPrecedingInvoiceNumber()): ?>
<div class="preceding-block">
  <strong>Référence facture précédente :</strong>
  N° <?= esc($invoice->getPrecedingInvoiceNumber()) ?>
  <?php if ($invoice->getPrecedingInvoiceDate()): ?>
    du <?= fmtDate($invoice->getPrecedingInvoiceDate()) ?>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- ══════════════════════════════════════════════
     PARTIES
══════════════════════════════════════════════ -->
<div class="parties">
  <!-- Vendeur -->
  <div class="party-block party-seller">
    <div class="party-role">Émetteur · Vendeur</div>
    <div class="party-name"><?= esc($seller?->getName() ?? '—') ?></div>
    <div class="party-detail"><?= addr($seller) ?></div>
    <?php if ($seller?->getVatId()): ?>
      <span class="party-tag">TVA : <?= esc($seller->getVatId()) ?></span>
    <?php endif; ?>
    <?php if ($seller?->getCompanyId()): ?>
      <span class="party-tag">BCE : <?= esc($seller->getCompanyId()) ?></span>
    <?php endif; ?>
    <?php if ($seller?->getCompanyLegalForm()): ?>
      <span class="party-tag"><?= esc($seller->getCompanyLegalForm()) ?></span>
    <?php endif; ?>
    <?php if ($seller?->getEmail()): ?>
      <span class="party-tag">✉ <?= esc($seller->getEmail()) ?></span>
    <?php endif; ?>
    <?php if ($seller?->getElectronicAddress()): ?>
      <span class="party-tag">
        Peppol <?= esc($seller->getElectronicAddress()->getSchemeId()) ?> :
        <?= esc($seller->getElectronicAddress()->getIdentifier()) ?>
      </span>
    <?php endif; ?>
  </div>

  <!-- Acheteur -->
  <div class="party-block party-buyer">
    <div class="party-role">Destinataire · Acheteur</div>
    <div class="party-name"><?= esc($buyer?->getName() ?? '—') ?></div>
    <div class="party-detail"><?= addr($buyer) ?></div>
    <?php if ($buyer?->getVatId()): ?>
      <span class="party-tag">TVA : <?= esc($buyer->getVatId()) ?></span>
    <?php endif; ?>
    <?php if ($buyer?->getCompanyId()): ?>
      <span class="party-tag">BCE : <?= esc($buyer->getCompanyId()) ?></span>
    <?php endif; ?>
    <?php if ($buyer?->getEmail()): ?>
      <span class="party-tag">✉ <?= esc($buyer->getEmail()) ?></span>
    <?php endif; ?>
    <?php if ($buyer?->getElectronicAddress()): ?>
      <span class="party-tag">
        Peppol <?= esc($buyer->getElectronicAddress()->getSchemeId()) ?> :
        <?= esc($buyer->getElectronicAddress()->getIdentifier()) ?>
      </span>
    <?php endif; ?>
  </div>
</div>

<!-- ══════════════════════════════════════════════
     RÉFÉRENCES
══════════════════════════════════════════════ -->
<?php
$refs = array_filter([
    'Réf. acheteur'     => $invoice->getBuyerReference(),
    'Commande acheteur' => $invoice->getPurchaseOrderReference(),
    'Commande vendeur'  => $invoice->getSalesOrderReference(),
    'Contrat'           => $invoice->getContractReference(),
    'Projet'            => $invoice->getProjectReference(),
    'Avis d\'expéd.'    => $invoice->getDespatchAdviceReference(),
    'Avis réception'    => $invoice->getReceivingAdviceReference(),
    'Réf. compta'       => $invoice->getBuyerAccountingReference(),
]);
if (!empty($refs)):
?>
<div class="section-title">Références</div>
<div class="refs-grid" style="margin-bottom:32px;">
  <?php foreach ($refs as $label => $value): ?>
  <div class="ref-cell">
    <div class="ref-label"><?= esc($label) ?></div>
    <div class="ref-value"><?= esc($value) ?></div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ══════════════════════════════════════════════
     PÉRIODE DE FACTURATION HEADER
══════════════════════════════════════════════ -->
<?php if ($invoice->getInvoicePeriodStartDate() || $invoice->getInvoicePeriodEndDate()): ?>
<div class="refs-grid" style="margin-bottom:32px;">
  <div class="ref-cell">
    <div class="ref-label">Période de facturation — début</div>
    <div class="ref-value"><?= fmtDate($invoice->getInvoicePeriodStartDate()) ?></div>
  </div>
  <div class="ref-cell">
    <div class="ref-label">Période de facturation — fin</div>
    <div class="ref-value"><?= fmtDate($invoice->getInvoicePeriodEndDate()) ?></div>
  </div>
</div>
<?php endif; ?>

<!-- ══════════════════════════════════════════════
     LIGNES DE FACTURE
══════════════════════════════════════════════ -->
<div class="section-title">Détail des prestations</div>
<table class="lines-table">
  <thead>
    <tr>
      <th style="width:28px">#</th>
      <th>Désignation</th>
      <th class="right" style="width:80px">Qté</th>
      <th style="width:50px">U.</th>
      <th class="right" style="width:100px">P.U. HT</th>
      <th style="width:50px">TVA</th>
      <th class="right" style="width:110px">Total HT</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($lines as $i => $line): ?>
    <tr>
      <td style="color:var(--ink-light);font-size:11px;"><?= $i + 1 ?></td>
      <td>
        <div class="line-name"><?= esc($line->getName()) ?></div>
        <?php if ($line->getDescription()): ?>
          <div class="line-desc"><?= esc($line->getDescription()) ?></div>
        <?php endif; ?>
        <div class="line-meta">
          <?php if ($line->getSellerItemId()): ?>
            <span>Réf. vendeur : <?= esc($line->getSellerItemId()) ?></span>
          <?php endif; ?>
          <?php if ($line->getBuyerItemId()): ?>
            <span>Réf. acheteur : <?= esc($line->getBuyerItemId()) ?></span>
          <?php endif; ?>
          <?php if ($line->getStandardItemId()): ?>
            <span>EAN/GTIN : <?= esc($line->getStandardItemId()) ?></span>
          <?php endif; ?>
          <?php if ($line->getItemClassificationCode()): ?>
            <span><?= esc($line->getItemClassificationListId()) ?> : <?= esc($line->getItemClassificationCode()) ?></span>
          <?php endif; ?>
          <?php if ($line->getOriginCountryCode()): ?>
            <span>Origine : <?= esc($line->getOriginCountryCode()) ?></span>
          <?php endif; ?>
          <?php if ($line->getOrderLineReference()): ?>
            <span>Ligne cmd : <?= esc($line->getOrderLineReference()) ?></span>
          <?php endif; ?>
          <?php if ($line->getLinePeriodStartDate()): ?>
            <span>Période : <?= fmtDate($line->getLinePeriodStartDate()) ?> → <?= fmtDate($line->getLinePeriodEndDate()) ?></span>
          <?php endif; ?>
        </div>
        <?php if ($line->getLineNote()): ?>
          <div class="line-meta" style="color:var(--ink);font-style:italic;">
            💬 <?= esc($line->getLineNote()) ?>
          </div>
        <?php endif; ?>
        <?php foreach ($line->getLineAllowanceCharges() as $lac): ?>
          <div class="line-allowance">
            <?= $lac->isAllowance() ? '▼ Remise' : '▲ Majoration' ?>
            <?= $lac->getReason() ? '(' . esc($lac->getReason()) . ')' : '' ?>
            : −<?= fmt($lac->getAmount(), $cur) ?>
          </div>
        <?php endforeach; ?>
      </td>
      <td class="right"><?= number_format($line->getQuantity(), 2, ',', ' ') ?></td>
      <td style="color:var(--ink-light)"><?= esc($line->getUnitCode()) ?></td>
      <td class="right"><?= fmt($line->getUnitPrice(), $cur) ?></td>
      <td style="color:var(--ink-light)">
        <?= esc($line->getVatCategory()) ?>
        <?= $line->getVatRate() > 0 ? number_format($line->getVatRate(), 0) . '%' : '' ?>
      </td>
      <td class="right" style="font-weight:500;"><?= fmt($line->getLineAmount(), $cur) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<!-- ══════════════════════════════════════════════
     REMISES / MAJORATIONS DOCUMENT
══════════════════════════════════════════════ -->
<?php if (!empty($acs)): ?>
<div style="margin-top:24px;">
  <div class="section-title">Remises & majorations document</div>
  <table class="lines-table" style="font-size:12.5px;">
    <thead>
      <tr>
        <th>Type</th>
        <th>Motif</th>
        <th>Code raison</th>
        <th>Cat. TVA</th>
        <th class="right">Montant</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($acs as $ac): ?>
      <tr>
        <td><?= $ac->isAllowance() ? '▼ Remise' : '▲ Majoration' ?></td>
        <td><?= esc($ac->getReason() ?? '—') ?></td>
        <td><?= esc($ac->getReasonCode() ?? '—') ?></td>
        <td><?= esc($ac->getVatCategory()) ?> <?= $ac->getVatRate() > 0 ? number_format($ac->getVatRate(), 0).'%' : '' ?></td>
        <td class="right" style="color:var(--accent);"><?= fmt($ac->getAmount(), $cur) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<!-- ══════════════════════════════════════════════
     TOTAUX
══════════════════════════════════════════════ -->
<div class="totals-section">

  <!-- Ventilation TVA -->
  <div>
    <div class="section-title">Ventilation TVA</div>
    <table class="vat-table">
      <thead>
        <tr>
          <th>Catégorie</th>
          <th>Taux</th>
          <th style="text-align:right">Base HT</th>
          <th style="text-align:right">TVA</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($vats as $vat): ?>
        <tr>
          <td><?= esc($vat->getCategory()) ?></td>
          <td><?= number_format($vat->getRate(), 2, ',', '') ?>%</td>
          <td style="text-align:right;font-variant-numeric:tabular-nums"><?= fmt($vat->getTaxableAmount(), $cur) ?></td>
          <td style="text-align:right;font-variant-numeric:tabular-nums;font-weight:500"><?= fmt($vat->getTaxAmount(), $cur) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- Récapitulatif montants -->
  <div class="amounts-block">
    <div class="amount-row">
      <span class="amount-label">Total brut lignes</span>
      <span class="amount-value"><?= fmt($invoice->getSumOfLineNetAmounts(), $cur) ?></span>
    </div>
    <?php if ($invoice->getSumOfAllowances() > 0): ?>
    <div class="amount-row allowance">
      <span class="amount-label">Total remises</span>
      <span class="amount-value">− <?= fmt($invoice->getSumOfAllowances(), $cur) ?></span>
    </div>
    <?php endif; ?>
    <?php if ($invoice->getSumOfCharges() > 0): ?>
    <div class="amount-row charge">
      <span class="amount-label">Total majorations</span>
      <span class="amount-value">+ <?= fmt($invoice->getSumOfCharges(), $cur) ?></span>
    </div>
    <?php endif; ?>
    <div class="amount-row total-ht">
      <span class="amount-label">Total HT</span>
      <span class="amount-value"><?= fmt($invoice->getTaxExclusiveAmount(), $cur) ?></span>
    </div>
    <div class="amount-row">
      <span class="amount-label">Total TVA</span>
      <span class="amount-value"><?= fmt($invoice->getTotalVatAmount(), $cur) ?></span>
    </div>
    <div class="amount-row total-ttc">
      <span class="amount-label">Total TTC</span>
      <span class="amount-value"><?= fmt($invoice->getTaxInclusiveAmount(), $cur) ?></span>
    </div>
    <?php if ($invoice->getPrepaidAmount() > 0): ?>
    <div class="amount-row prepaid">
      <span class="amount-label">Acompte versé</span>
      <span class="amount-value">− <?= fmt($invoice->getPrepaidAmount(), $cur) ?></span>
    </div>
    <?php endif; ?>
    <div class="amount-row payable">
      <span class="amount-label">NET À PAYER</span>
      <span class="amount-value"><?= fmt($invoice->getPayableAmount(), $cur) ?></span>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════
     PAIEMENT
══════════════════════════════════════════════ -->
<?php if ($payment || $invoice->getPaymentTerms()): ?>
<div class="payment-section">
  <?php if ($payment && $payment->getIban()): ?>
  <div>
    <div class="section-title">Coordonnées bancaires</div>
    <div class="payment-block">
      <div class="payment-label">IBAN</div>
      <div class="payment-value"><?= esc($payment->getIban()) ?></div>
      <?php if ($payment->getBic()): ?>
      <div class="payment-label" style="margin-top:12px;">BIC / SWIFT</div>
      <div class="payment-value"><?= esc($payment->getBic()) ?></div>
      <?php endif; ?>
      <?php if ($payment->getPaymentReference()): ?>
      <div class="payment-label" style="margin-top:12px;">Référence de paiement</div>
      <div class="payment-value"><?= esc($payment->getPaymentReference()) ?></div>
      <?php endif; ?>
      <div class="payment-label" style="margin-top:12px;">Mode de paiement</div>
      <div class="payment-value"><?= esc($payment->getPaymentMeansCode()) ?></div>
    </div>
  </div>
  <?php endif; ?>
  <?php if ($invoice->getPaymentTerms()): ?>
  <div>
    <div class="section-title">Conditions de paiement</div>
    <div class="payment-block">
      <div class="terms-text"><?= nl2br(esc($invoice->getPaymentTerms())) ?></div>
    </div>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- ══════════════════════════════════════════════
     DOCUMENTS JOINTS
══════════════════════════════════════════════ -->
<?php $docs = $invoice->getAttachedDocuments(); ?>
<?php if (!empty($docs)): ?>
<div style="margin-top:32px;">
  <div class="section-title">Documents joints</div>
  <div class="refs-grid">
    <?php foreach ($docs as $doc): ?>
    <div class="ref-cell">
      <div class="ref-label"><?= esc($doc->getMimeType()) ?></div>
      <div class="ref-value">📎 <?= esc($doc->getFilename()) ?></div>
      <?php if ($doc->getDescription()): ?>
        <div style="font-size:12px;color:var(--ink-light);margin-top:3px;"><?= esc($doc->getDescription()) ?></div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- ══════════════════════════════════════════════
     FOOTER
══════════════════════════════════════════════ -->
<div class="doc-footer">
  <div class="footer-legal">
    <?= esc($seller?->getName() ?? '') ?>
    <?php if ($seller?->getVatId()): ?> · TVA <?= esc($seller->getVatId()) ?><?php endif; ?>
    <?php if ($seller?->getCompanyLegalForm()): ?> · <?= esc($seller->getCompanyLegalForm()) ?><?php endif; ?>
  </div>
  <div>
    Document généré le <?= date('d/m/Y à H:i') ?>
    · Peppol UBL 2.1
  </div>
</div>

</div><!-- /.page-inner -->
</div><!-- /.page -->
</body>
</html>
<?php

// ============================================================
// Facture de démonstration
// ============================================================
function buildDemoInvoice(): PeppolInvoice
{
    $invoice = new PeppolInvoice('FAC-2025-0042', '2025-03-15', '380', 'EUR');
    $invoice->setDueDate('2025-04-15');
    $invoice->setPurchaseOrderReference('PO-2025-1234');
    $invoice->setBuyerReference('REF-CLIENT-99');
    $invoice->setPaymentTerms('Paiement à 30 jours date de facture. Escompte 2% si paiement sous 10 jours.');

    $sellerAddress = new Peppol\Models\Address("Rue de l'Industrie 42", 'Liège', '4000', 'BE');
    $sellerEndpoint = new Peppol\Models\ElectronicAddress('0208', 'BE0123456789');
    $seller = new Peppol\Models\Party(
        'Hydraulique Industrielle SA', $sellerAddress, 'BE0123456789',
        '0123456789', 'contact@hydraulique-sa.be', $sellerEndpoint
    );
    $seller->setCompanyLegalForm('SA au capital de 250 000 EUR');
    $invoice->setSeller($seller);

    $buyerAddress = new Peppol\Models\Address('Chaussée de Namur 15', 'Namur', '5000', 'BE');
    $buyerEndpoint = new Peppol\Models\ElectronicAddress('0208', 'BE0987654321');
    $buyer = new Peppol\Models\Party(
        'Garage Dupont SPRL', $buyerAddress, 'BE0987654321',
        '0987654321', 'comptabilite@dupont.be', $buyerEndpoint
    );
    $invoice->setBuyer($buyer);

    $line1 = new Peppol\Models\InvoiceLine('1', 'Vérin hydraulique double effet V-40', 3.0, 'C62', 420.00, 'S', 21.0, 'Course 200mm, pression max 250 bar');
    $line1->setSellerItemId('VERIN-V40-DE')
          ->setStandardItemId('3700000040001', '0160')
          ->setOriginCountryCode('DE');
    $invoice->addInvoiceLine($line1);

    $line2 = new Peppol\Models\InvoiceLine('2', 'Pompe hydraulique HPX-200', 1.0, 'C62', 1850.00, 'S', 21.0, 'Débit 45 L/min, moteur 15kW');
    $line2->setLinePeriod('2025-03-01', '2025-03-15');
    $invoice->addInvoiceLine($line2);

    $line3 = new Peppol\Models\InvoiceLine('3', 'Prestation montage & mise en service', 4.0, 'HUR', 95.00, 'S', 21.0);
    $line3->setLineNote('Intervention site client 15/03/2025 — Technicien : J. Martin');
    $invoice->addInvoiceLine($line3);

    $line4 = new Peppol\Models\InvoiceLine('4', 'Joint torique NBR (lot 50 pièces)', 2.0, 'C62', 28.50, 'Z', 0.0);
    $invoice->addInvoiceLine($line4);

    $invoice->addAllowanceCharge(
        Peppol\Models\AllowanceCharge::createAllowance(amount: 150.00, vatCategory: 'S', vatRate: 21.0, reason: 'Remise client fidèle')
    );

    $payment = new Peppol\Models\PaymentInfo('30', 'BE71096123456769', 'GKCCBEBB', '+++042/2025/00042+++');
    $invoice->setPaymentInfo($payment);

    $invoice->calculateTotals();
    return $invoice;
}
