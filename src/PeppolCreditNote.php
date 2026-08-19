<?php

declare(strict_types=1);

use Peppol\Standards\EN16931Invoice;
use Peppol\Formats\CreditNoteXmlExporter;
use Peppol\Formats\XmlImporter;

/**
 * Classe façade PeppolCreditNote
 *
 * Équivalent de PeppolInvoice pour les notes de crédit (avoirs) UBL 2.1
 * conformes à Peppol BIS Billing 3.0.
 *
 * S'utilise exactement comme PeppolInvoice — mêmes setters hérités
 * (setSellerFromData, setBuyerFromData, addLine, etc.), avec deux
 * différences :
 *   - invoiceTypeCode vaut '381' (Avoir) par défaut
 *   - toXml() génère un document UBL cac:CreditNote (CreditNoteLine /
 *     CreditedQuantity / CreditNoteTypeCode) au lieu de cac:Invoice
 *
 * Exemple d'utilisation :
 * <code>
 * $avoir = new PeppolCreditNote('AV-2026-001', '2026-08-19');
 * $avoir->setPrecedingInvoiceReference('FA-2026-042', '2026-07-01');
 * $avoir->setSellerFromData(...);
 * $avoir->setBuyerFromData(...);
 * $avoir->addLine('1', 'Remboursement produit X', 1, 'C62', -100.00, 'S', 21.0);
 * echo $avoir->toXml();
 * </code>
 *
 * @package Peppol
 * @version 1.0
 */
class PeppolCreditNote extends EN16931Invoice
{
    /**
     * Constructeur
     *
     * @param string $creditNoteNumber Numéro unique de la note de crédit (BT-1)
     * @param string $issueDate        Date d'émission YYYY-MM-DD (BT-2)
     * @param string $invoiceTypeCode  Code type UNCL1001 (BT-3) — défaut : 381 (Avoir)
     * @param string $currencyCode     Code devise ISO 4217 (BT-5) — défaut : EUR
     */
    public function __construct(
        string $creditNoteNumber,
        string $issueDate,
        string $invoiceTypeCode = '381',
        string $currencyCode = 'EUR'
    ) {
        parent::__construct($creditNoteNumber, $issueDate, $invoiceTypeCode, $currencyCode);
    }

    // =========================================================================
    // Export
    // =========================================================================

    /**
     * Exporte la note de crédit au format XML UBL 2.1 (Peppol BIS 3.0)
     *
     * Valide le document avant export. Lance une exception si des erreurs
     * de validation sont détectées.
     *
     * @return string Contenu XML UBL 2.1 (cac:CreditNote)
     * @throws \InvalidArgumentException Si le document n'est pas valide
     */
    public function toXml(): string
    {
        $exporter = new CreditNoteXmlExporter($this);
        return $exporter->toUbl21();
    }

    /**
     * Sauvegarde la note de crédit dans un fichier XML
     *
     * @param string $filepath Chemin complet du fichier de destination
     * @return bool True si la sauvegarde a réussi, false en cas d'erreur
     */
    public function saveXml(string $filepath): bool
    {
        $exporter = new CreditNoteXmlExporter($this);
        return $exporter->saveToFile($filepath);
    }

    /**
     * Exporte la note de crédit au format JSON
     *
     * @param bool $prettyPrint Formater le JSON avec indentation (défaut : true)
     * @return string Contenu JSON
     */
    public function toJson(bool $prettyPrint = true): string
    {
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        if ($prettyPrint) {
            $flags |= JSON_PRETTY_PRINT;
        }

        return json_encode($this->toArray(), $flags);
    }

    /**
     * Sauvegarde la note de crédit dans un fichier JSON
     *
     * @param string $filepath    Chemin complet du fichier de destination
     * @param bool   $prettyPrint Formater le JSON avec indentation (défaut : true)
     * @return bool True si la sauvegarde a réussi, false en cas d'erreur
     */
    public function saveJson(string $filepath, bool $prettyPrint = true): bool
    {
        return file_put_contents($filepath, $this->toJson($prettyPrint)) !== false;
    }

    // =========================================================================
    // Import
    // =========================================================================

    /**
     * Importe une note de crédit depuis un contenu XML UBL 2.1 (CreditNote)
     * ou un chemin de fichier.
     *
     * Note : XmlImporter lit les données génériques du modèle (parties, lignes,
     * totaux, TVA...) indépendamment du nom de la racine XML (Invoice/CreditNote),
     * puisque le modèle interne (InvoiceBase) est partagé entre les deux types
     * de documents.
     *
     * @param string $xmlContent Contenu XML en chaîne, ou chemin vers un fichier XML
     * @param bool   $strict     true = mode strict (défaut), false = mode lenient
     * @return static
     * @throws \InvalidArgumentException                  En mode strict si le XML ou une donnée est invalide
     * @throws \Peppol\Exceptions\ImportWarningException En mode lenient si des anomalies sont détectées
     */
    public static function fromXml(string $xmlContent, bool $strict = true): static
    {
        /** @var static */
        return XmlImporter::fromUbl($xmlContent, self::class, $strict);
    }

    /**
     * Crée une note de crédit depuis un tableau de données
     *
     * @param array<string, mixed> $data Données de la note de crédit
     * @return self
     * @throws \InvalidArgumentException Si creditNoteNumber/invoiceNumber ou issueDate est manquant
     */
    public static function fromArray(array $data): self
    {
        $number = $data['creditNoteNumber'] ?? $data['invoiceNumber'] ?? null;

        if (!isset($number, $data['issueDate'])) {
            throw new \InvalidArgumentException('creditNoteNumber et issueDate sont obligatoires');
        }

        return new self(
            $number,
            $data['issueDate'],
            $data['invoiceTypeCode'] ?? '381',
            $data['documentCurrencyCode'] ?? 'EUR'
        );
    }

    // =========================================================================
    // Validation
    // =========================================================================

    /**
     * Retourne la liste des erreurs de validation
     *
     * @return array<string> Liste des erreurs (vide si valide)
     */
    public function getValidationErrors(): array
    {
        return $this->validate();
    }

    /**
     * Vérifie si la note de crédit est valide
     *
     * @return bool True si le document ne contient aucune erreur de validation
     */
    public function isValid(): bool
    {
        return empty($this->validate());
    }

    // =========================================================================
    // Utilitaires d'affichage
    // =========================================================================

    /**
     * Retourne un résumé textuel de la note de crédit
     *
     * @return string Résumé multi-lignes
     */
    public function getSummary(): string
    {
        $seller = $this->getSeller();
        $buyer  = $this->getBuyer();

        $summary  = sprintf("Note de crédit N°%s du %s\n", $this->getInvoiceNumber(), $this->getIssueDate());
        $summary .= sprintf("De: %s (%s)\n", $seller->getName(), $seller->getVatId());
        $summary .= sprintf("À: %s\n", $buyer->getName());

        if ($this->getPrecedingInvoiceNumber()) {
            $summary .= sprintf(
                "Référence facture d'origine: %s%s\n",
                $this->getPrecedingInvoiceNumber(),
                $this->getPrecedingInvoiceDate() ? ' du ' . $this->getPrecedingInvoiceDate() : ''
            );
        }

        $summary .= sprintf(
            "Montant HT: %.2f %s\n",
            $this->getTaxExclusiveAmount(),
            $this->getDocumentCurrencyCode()
        );
        $summary .= sprintf(
            "Montant TTC: %.2f %s\n",
            $this->getTaxInclusiveAmount(),
            $this->getDocumentCurrencyCode()
        );
        $summary .= sprintf("Lignes: %d\n", count($this->getInvoiceLines()));

        return $summary;
    }

    /**
     * Affiche le résumé de la note de crédit sur la sortie standard
     *
     * @return void
     */
    public function display(): void
    {
        echo $this->getSummary();
    }
}
