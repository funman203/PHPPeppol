<?php

declare(strict_types=1);

use Peppol\Standards\EN16931Invoice;
use Peppol\Formats\XmlExporter;
use Peppol\Formats\XmlImporter;

/**
 * Classe façade PeppolInvoice
 *
 * Simplifie l'utilisation de la bibliothèque de facturation électronique
 * en fournissant une API conviviale pour créer, exporter et importer
 * des factures conformes à Peppol BIS 3.0.
 *
 * Cette classe étend EN16931Invoice et ajoute :
 *   - Des méthodes d'export (XML, JSON, fichier)
 *   - Des méthodes d'import statiques (fromXml, fromArray)
 *   - Des méthodes utilitaires (isValid, getSummary, display)
 *
 * Note : buyerReference (BT-10) est défini dans InvoiceBase et hérité ici.
 *
 * Exemple d'utilisation webservice :
 * <code>
 * try {
 *     $invoice = PeppolInvoice::fromXml($xml, strict: false);
 *     return json_encode(['result' => $invoice]); // JsonSerializable auto
 *
 * } catch (\Peppol\Exceptions\ImportWarningException $e) {
 *     $invoice = $e->getInvoice();
 *     return json_encode([
 *         'result'    => $invoice,
 *         'warnings'  => $e->getWarnings(),
 *         'anomalies' => $e->getAnomalies(),
 *     ]);
 *
 * } catch (\InvalidArgumentException $e) {
 *     return json_encode(['error' => $e->getMessage()]);
 * }
 * </code>
 *
 * @package Peppol
 * @version 1.1
 */
class PeppolInvoice extends EN16931Invoice
{
    // buyerReference (BT-10) est hérité de InvoiceBase — pas de redéclaration ici

    /**
     * Constructeur
     *
     * @param string $invoiceNumber   Numéro unique de facture (BT-1)
     * @param string $issueDate       Date d'émission YYYY-MM-DD (BT-2)
     * @param string $invoiceTypeCode Code type de facture UNCL1001 (BT-3) — défaut : 380
     * @param string $currencyCode    Code devise ISO 4217 (BT-5) — défaut : EUR
     */
    public function __construct(
        string $invoiceNumber,
        string $issueDate,
        string $invoiceTypeCode = '380',
        string $currencyCode = 'EUR'
    ) {
        parent::__construct($invoiceNumber, $issueDate, $invoiceTypeCode, $currencyCode);
    }

    // =========================================================================
    // Export
    // =========================================================================

    /**
     * Exporte la facture au format XML UBL 2.1 (Peppol BIS 3.0)
     *
     * Valide la facture avant export. Lance une exception si des erreurs
     * de validation sont détectées.
     *
     * @return string Contenu XML UBL 2.1
     * @throws \InvalidArgumentException Si la facture n'est pas valide
     */
    public function toXml(): string
    {
        $exporter = new XmlExporter($this);
        return $exporter->toUbl21();
    }

    /**
     * Sauvegarde la facture dans un fichier XML
     *
     * @param string $filepath Chemin complet du fichier de destination
     * @return bool True si la sauvegarde a réussi, false en cas d'erreur
     */
    public function saveXml(string $filepath): bool
    {
        $exporter = new XmlExporter($this);
        return $exporter->saveToFile($filepath);
    }

    /**
     * Exporte la facture au format JSON
     *
     * Utilise l'implémentation JsonSerializable héritée de InvoiceBase.
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
     * Sauvegarde la facture dans un fichier JSON
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
     * Importe une facture depuis un contenu XML UBL 2.1 ou un chemin de fichier
     *
     * En mode strict (défaut) : toute donnée invalide lève une exception.
     * En mode lenient : les anomalies sont collectées dans une ImportWarningException
     *   dont la facture reste récupérable via $e->getInvoice().
     *
     * @param string $xmlContent Contenu XML en chaîne, ou chemin vers un fichier XML
     * @param bool   $strict     true = mode strict (défaut), false = mode lenient
     * @return static
     * @throws \InvalidArgumentException             En mode strict si le XML ou une donnée est invalide
     * @throws \Peppol\Exceptions\ImportWarningException En mode lenient si des anomalies sont détectées
     */
    public static function fromXml(string $xmlContent, bool $strict = true): static
    {
        /** @var static */
        return XmlImporter::fromUbl($xmlContent, self::class, $strict);
    }

    /**
     * Crée une facture depuis un tableau de données
     *
     * Méthode de base — à compléter selon les besoins du projet.
     * Seuls invoiceNumber et issueDate sont requis.
     *
     * @param array<string, mixed> $data Données de la facture
     * @return self
     * @throws \InvalidArgumentException Si invoiceNumber ou issueDate est manquant
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['invoiceNumber'], $data['issueDate'])) {
            throw new \InvalidArgumentException('invoiceNumber et issueDate sont obligatoires');
        }

        return new self(
            $data['invoiceNumber'],
            $data['issueDate'],
            $data['invoiceTypeCode'] ?? '380',
            $data['documentCurrencyCode'] ?? 'EUR'
        );
    }

    // =========================================================================
    // Validation
    // =========================================================================

    /**
     * Retourne la liste des erreurs de validation
     *
     * Raccourci pour validate() avec un nom plus explicite.
     *
     * @return array<string> Liste des erreurs (vide si valide)
     */
    public function getValidationErrors(): array
    {
        return $this->validate();
    }

    /**
     * Vérifie si la facture est valide
     *
     * @return bool True si la facture ne contient aucune erreur de validation
     */
    public function isValid(): bool
    {
        return empty($this->validate());
    }

    // =========================================================================
    // Utilitaires d'affichage
    // =========================================================================

    /**
     * Retourne un résumé textuel de la facture
     *
     * Inclut : numéro, date, vendeur, acheteur, montants HT/TTC,
     * nombre de lignes, remises/majorations si présentes, et date d'échéance.
     *
     * @return string Résumé multi-lignes
     */
    public function getSummary(): string
    {
        $seller = $this->getSeller();
        $buyer  = $this->getBuyer();

        $summary  = sprintf("Facture N°%s du %s\n", $this->getInvoiceNumber(), $this->getIssueDate());
        $summary .= sprintf("De: %s (%s)\n", $seller->getName(), $seller->getVatId());
        $summary .= sprintf("À: %s\n", $buyer->getName());
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

        // Remises et majorations si présentes
        if (!empty($this->getAllowanceCharges())) {
            $allowances = array_filter($this->getAllowanceCharges(), fn($ac) => $ac->isAllowance());
            $charges    = array_filter($this->getAllowanceCharges(), fn($ac) => $ac->isCharge());

            if (!empty($allowances)) {
                $summary .= sprintf(
                    "Remises: -%.2f %s\n",
                    $this->getSumOfAllowances(),
                    $this->getDocumentCurrencyCode()
                );
            }
            if (!empty($charges)) {
                $summary .= sprintf(
                    "Majorations: +%.2f %s\n",
                    $this->getSumOfCharges(),
                    $this->getDocumentCurrencyCode()
                );
            }
        }

        if ($this->getDueDate()) {
            $summary .= sprintf("Échéance: %s\n", $this->getDueDate());
        }

        return $summary;
    }

    /**
     * Affiche le résumé de la facture sur la sortie standard
     *
     * @return void
     */
    public function display(): void
    {
        echo $this->getSummary();
    }
}