<?php

declare(strict_types=1);

namespace Peppol\Core;

use Peppol\Exceptions\ImportWarningException;
use Peppol\Models\Party;
use Peppol\Models\InvoiceLine;
use Peppol\Models\VatBreakdown;
use Peppol\Models\PaymentInfo;
use Peppol\Models\AttachedDocument;
use Peppol\Models\AllowanceCharge;

/**
 * Classe de base abstraite pour les factures électroniques
 *
 * Fournit la structure commune à toutes les implémentations de factures
 * (EN 16931, UBL.BE, Peppol, etc.)
 *
 * Implémente \JsonSerializable pour permettre la sérialisation directe
 * via json_encode() dans les webservices.
 *
 * @package Peppol\Core
 * @version 1.1
 * @link https://docs.peppol.eu/poacc/billing/3.0/
 * @link https://www.nen.nl/en/standard/nen-en-16931-1-2017/
 */
abstract class InvoiceBase implements \JsonSerializable {

    use InvoiceValidatorTrait;

// =========================================================================
    // Informations de base de la facture (BT-1 … BT-22)
    // =========================================================================

    /**
     * @var string Numéro unique de facture (BT-1)
     *             Ne peut pas être vide ni contenir de caractères XML interdits (< > & " ')
     */
    protected string $invoiceNumber;

    /**
     * @var string Date d'émission au format YYYY-MM-DD (BT-2)
     */
    protected string $issueDate;

    /**
     * @var string Code de type de facture selon UNCL1001 (BT-3)
     *             Ex : 380 = Facture commerciale, 381 = Avoir
     *
     * @see InvoiceConstants::INVOICE_TYPE_CODES
     */
    protected string $invoiceTypeCode;

    /**
     * @var string Code devise ISO 4217 (BT-5)
     *             Ex : EUR, USD, GBP
     *
     * @see InvoiceConstants::CURRENCY_CODES
     */
    protected string $documentCurrencyCode;

    /**
     * @var string|null Date d'échéance au format YYYY-MM-DD (BT-9)
     *                  Doit être >= date d'émission (BR-CO-10)
     */
    protected ?string $dueDate = null;

    /**
     * @var string|null Référence acheteur (BT-10)
     *                  Obligatoire pour UBL.BE si pas de référence commande (BT-13)
     */
    protected ?string $buyerReference = null;

    /**
     * @var string|null Référence du contrat (BT-12)
     */
    protected ?string $contractReference = null;

    /**
     * @var string|null Référence du bon de commande acheteur (BT-13)
     */
    protected ?string $purchaseOrderReference = null;

    /**
     * @var string|null Référence de l'ordre de vente vendeur (BT-14)
     */
    protected ?string $salesOrderReference = null;

    /**
     * @var string|null Référence de l'avis de réception (BT-15)
     */
    protected ?string $receivingAdviceReference = null;

    /**
     * @var string|null Référence de l'avis d'expédition (BT-16)
     */
    protected ?string $despatchAdviceReference = null;

    /**
     * @var string|null Référence de projet (BT-11)
     */
    protected ?string $projectReference = null;

    /**
     * @var string|null Référence comptable acheteur au niveau en-tête (BT-19)
     *                  Exporté dans cbc:AccountingCost
     */
    protected ?string $buyerAccountingReference = null;

    /**
     * @var string|null Note de facture libre (BT-22)
     *                  Distinct des conditions de paiement (BT-20)
     *                  Exporté dans cbc:Note enfant direct de Invoice
     */
    protected ?string $invoiceNote = null;

    /**
     * @var string|null Date de début de la période de facturation (BT-73)
     *                  Format YYYY-MM-DD
     */
    protected ?string $invoicePeriodStartDate = null;

    /**
     * @var string|null Date de fin de la période de facturation (BT-74)
     *                  Format YYYY-MM-DD
     */
    protected ?string $invoicePeriodEndDate = null;

    /**
     * @var string|null Date de livraison au format YYYY-MM-DD (BT-72)
     */
    protected ?string $deliveryDate = null;

    // =========================================================================
    // BG-3 — Référence à la facture précédente (pour avoirs)
    // =========================================================================

    /**
     * @var string|null Numéro de la facture originale référencée (BT-25)
     *                  Utilisé dans les avoirs (invoiceTypeCode = 381)
     *                  pour faire référence à la facture d'origine
     */
    protected ?string $precedingInvoiceNumber = null;

    /**
     * @var string|null Date de la facture originale au format YYYY-MM-DD (BT-26)
     */
    protected ?string $precedingInvoiceDate = null;

    // =========================================================================
    // Parties
    // =========================================================================

    /**
     * @var Party Fournisseur / Vendeur (BG-4)
     */
    protected Party $seller;

    /**
     * @var Party Client / Acheteur (BG-7)
     */
    protected Party $buyer;

    // =========================================================================
    // Lignes de facture
    // =========================================================================

    /**
     * @var array<InvoiceLine> Lignes de facture (BG-25)
     *                         Au moins une ligne est requise (BR-16)
     */
    protected array $invoiceLines = [];

    // =========================================================================
    // Remises et majorations au niveau document (BG-20 / BG-21)
    // =========================================================================

    /**
     * @var array<AllowanceCharge> Remises et majorations au niveau document
     *                             BG-20 : remises (ChargeIndicator = false)
     *                             BG-21 : majorations (ChargeIndicator = true)
     */
    protected array $allowanceCharges = [];

    // =========================================================================
    // Informations de paiement
    // =========================================================================

    /**
     * @var PaymentInfo|null Informations de paiement (BG-16, BG-17)
     */
    protected ?PaymentInfo $paymentInfo = null;

    /**
     * @var string|null Conditions de paiement (BT-20)
     *                  Obligatoire si date d'échéance non fournie et montant > 0 (BR-CO-25)
     */
    protected ?string $paymentTerms = null;

    // =========================================================================
    // Documents joints
    // =========================================================================

    /**
     * @var array<AttachedDocument> Documents joints (BG-24)
     */
    protected array $attachedDocuments = [];

    // =========================================================================
    // Totaux
    // =========================================================================

    /**
     * @var float Somme des montants nets de toutes les lignes (BT-106)
     *            = Σ InvoiceLine.lineAmount
     */
    protected float $sumOfLineNetAmounts = 0.0;

    /**
     * @var float Somme de toutes les remises au niveau document (BT-107)
     *            = Σ AllowanceCharge[ChargeIndicator=false].Amount
     */
    protected float $sumOfAllowances = 0.0;

    /**
     * @var float Somme de toutes les majorations au niveau document (BT-108)
     *            = Σ AllowanceCharge[ChargeIndicator=true].Amount
     */
    protected float $sumOfCharges = 0.0;

    /**
     * @var float Montant net total hors taxes (BT-109)
     *            = sumOfLineNetAmounts - sumOfAllowances + sumOfCharges
     *            Anciennement nommé taxExclusiveAmount dans BT-110
     */
    protected float $taxExclusiveAmount = 0.0;

    /**
     * @var float Montant total TVA comprise (BT-112)
     *            = taxExclusiveAmount + Σ TVA
     */
    protected float $taxInclusiveAmount = 0.0;

    /**
     * @var float Montant à payer (BT-115)
     *            = taxInclusiveAmount - prepaidAmount
     */
    protected float $payableAmount = 0.0;

    /**
     * @var float Montant prépayé / acompte déjà versé (BT-113)
     */
    protected float $prepaidAmount = 0.0;

    /**
     * @var array<string, VatBreakdown> Ventilation par taux de TVA (BG-23)
     *                                  Clé : "{catégorie}_{taux}" ex: "S_21"
     */
    protected array $vatBreakdown = [];

    /**
     * @var float Total TVA (BT-110)
     *            = Σ VatBreakdown.taxAmount
     */
    protected float $totalVatAmount = 0.0;

    // =========================================================================
    // Import lenient — gestion des totaux déclarés
    // =========================================================================

    /**
     * Totaux tels que déclarés dans LegalMonetaryTotal du XML source.
     * Null si non chargé (import strict ou facture créée programmatiquement).
     *
     * Utilisé par checkImportedTotals() pour détecter des écarts entre
     * les totaux déclarés dans le XML et les totaux recalculés depuis les lignes.
     *
     * @var array{lineExtension:float, taxExclusive:float, taxInclusive:float, prepaid:float, payable:float, taxAmount:float}|null
     */
    private ?array $importedTotals = null;

    // =========================================================================
    // Constructeur
    // =========================================================================

    /**
     * Constructeur
     *
     * @param string $invoiceNumber  Numéro unique de facture (BT-1)
     * @param string $issueDate      Date d'émission au format YYYY-MM-DD (BT-2)
     * @param string $invoiceTypeCode Code type facture UNCL1001 (BT-3) — défaut : 380
     * @param string $currencyCode   Code devise ISO 4217 (BT-5) — défaut : EUR
     *
     * @throws \InvalidArgumentException Si l'un des paramètres est invalide
     */
    public function __construct(
            string $invoiceNumber,
            string $issueDate,
            string $invoiceTypeCode = '380',
            string $currencyCode = 'EUR'
    ) {
        $this->setInvoiceNumber($invoiceNumber);
        $this->setIssueDate($issueDate);
        $this->setInvoiceTypeCode($invoiceTypeCode);
        $this->setDocumentCurrencyCode($currencyCode);
    }

    // =========================================================================
    // Setters internes (avec validation)
    // =========================================================================

    /**
     * Définit et valide le numéro de facture
     *
     * Bloque les caractères de contrôle XML interdits (< > & " ') et les
     * caractères nuls, mais autorise tout le reste (tirets, slashes, accents…).
     *
     * @param string $invoiceNumber
     * @throws \InvalidArgumentException
     */
    protected function setInvoiceNumber(string $invoiceNumber): void {
        if (!$this->validateNotEmpty($invoiceNumber)) {
            throw new \InvalidArgumentException('Le numéro de facture ne peut pas être vide');
        }

        if (!preg_match('/^[^\x00-\x08\x0B\x0C\x0E-\x1F<>&"\']+$/', $invoiceNumber)) {
            throw new \InvalidArgumentException('Le numéro de facture contient des caractères invalides');
        }

        $this->invoiceNumber = $invoiceNumber;
    }

    /**
     * Définit et valide la date d'émission
     *
     * @param string $issueDate Format YYYY-MM-DD
     * @throws \InvalidArgumentException
     */
    protected function setIssueDate(string $issueDate): void {
        if (!$this->validateDate($issueDate)) {
            throw new \InvalidArgumentException('Format de date invalide (YYYY-MM-DD)');
        }

        $this->issueDate = $issueDate;
    }

    /**
     * Définit et valide le code type de facture
     *
     * @param string $invoiceTypeCode Code UNCL1001 (ex : 380, 381…)
     * @throws \InvalidArgumentException
     * @see InvoiceConstants::INVOICE_TYPE_CODES
     */
    protected function setInvoiceTypeCode(string $invoiceTypeCode): void {
        if (!$this->validateKeyExists($invoiceTypeCode, InvoiceConstants::INVOICE_TYPE_CODES)) {
            throw new \InvalidArgumentException(
                            'Code de type de facture invalide. Codes valides: ' .
                            implode(', ', array_keys(InvoiceConstants::INVOICE_TYPE_CODES))
                    );
        }

        $this->invoiceTypeCode = $invoiceTypeCode;
    }

    /**
     * Définit et valide le code devise
     *
     * @param string $currencyCode Code ISO 4217 (ex : EUR, USD…)
     * @throws \InvalidArgumentException
     * @see InvoiceConstants::CURRENCY_CODES
     */
    protected function setDocumentCurrencyCode(string $currencyCode): void {
        if (!$this->validateInList($currencyCode, InvoiceConstants::CURRENCY_CODES)) {
            throw new \InvalidArgumentException(
                            'Code devise invalide. Codes valides: ' . implode(', ', InvoiceConstants::CURRENCY_CODES)
                    );
        }

        $this->documentCurrencyCode = $currencyCode;
    }

    // =========================================================================
    // Setters publics — informations de base
    // =========================================================================

    /**
     * Définit la date d'échéance
     *
     * BR-CO-10 : La date d'échéance doit être >= date d'émission
     *
     * @param string $dueDate Format YYYY-MM-DD (BT-9)
     * @return self
     * @throws \InvalidArgumentException Si le format est invalide ou si la date est antérieure à l'émission
     */
    public function setDueDate(string $dueDate): self {
        if (!$this->validateDate($dueDate)) {
            throw new \InvalidArgumentException("Format de date d'échéance invalide (YYYY-MM-DD)");
        }

        if ($dueDate < $this->issueDate) {
            throw new \InvalidArgumentException(
                            "La date d'échéance ne peut pas être antérieure à la date d'émission"
                    );
        }

        $this->dueDate = $dueDate;
        return $this;
    }

    /**
     * Définit la date de livraison
     *
     * @param string $deliveryDate Format YYYY-MM-DD (BT-72)
     * @return self
     * @throws \InvalidArgumentException
     */
    public function setDeliveryDate(string $deliveryDate): self {
        if (!$this->validateDate($deliveryDate)) {
            throw new \InvalidArgumentException('Format de date de livraison invalide (YYYY-MM-DD)');
        }

        $this->deliveryDate = $deliveryDate;
        return $this;
    }

    /**
     * Définit la référence acheteur
     *
     * BT-10 — Obligatoire pour UBL.BE si pas de référence commande (BT-13)
     *
     * @param string $buyerReference Référence libre de l'acheteur
     * @return self
     */
    public function setBuyerReference(string $buyerReference): self {
        $this->buyerReference = $buyerReference;
        return $this;
    }

    /**
     * Définit la référence du bon de commande acheteur
     *
     * @param string $purchaseOrderReference Numéro de commande acheteur (BT-13)
     * @return self
     */
    public function setPurchaseOrderReference(string $purchaseOrderReference): self {
        $this->purchaseOrderReference = $purchaseOrderReference;
        return $this;
    }

    /**
     * Définit la référence de l'ordre de vente vendeur
     *
     * @param string $salesOrderReference Numéro d'ordre de vente (BT-14)
     * @return self
     */
    public function setSalesOrderReference(string $salesOrderReference): self {
        $this->salesOrderReference = $salesOrderReference;
        return $this;
    }

    /**
     * Définit la référence du contrat
     *
     * @param string $contractReference Numéro de contrat (BT-12)
     * @return self
     */
    public function setContractReference(string $contractReference): self {
        $this->contractReference = $contractReference;
        return $this;
    }

    /**
     * Définit la référence de l'avis de réception
     *
     * @param string $ref Référence avis de réception (BT-15)
     * @return self
     */
    public function setReceivingAdviceReference(string $ref): self {
        $this->receivingAdviceReference = $ref;
        return $this;
    }

    /**
     * Définit la référence de l'avis d'expédition
     *
     * @param string $ref Référence avis d'expédition (BT-16)
     * @return self
     */
    public function setDespatchAdviceReference(string $ref): self {
        $this->despatchAdviceReference = $ref;
        return $this;
    }

    /**
     * Définit la référence de projet
     *
     * @param string $projectReference Identifiant de projet (BT-11)
     * @return self
     */
    public function setProjectReference(string $projectReference): self {
        $this->projectReference = $projectReference;
        return $this;
    }

    /**
     * Définit la référence comptable acheteur au niveau en-tête
     *
     * Exporté dans cbc:AccountingCost.
     *
     * @param string $ref Référence comptable (BT-19)
     * @return self
     */
    public function setBuyerAccountingReference(string $ref): self {
        $this->buyerAccountingReference = $ref;
        return $this;
    }

    /**
     * Définit la note de facture libre
     *
     * BT-22 — Distinct des conditions de paiement (BT-20).
     * Exporté dans cbc:Note enfant direct de l'élément Invoice.
     *
     * @param string $note Note libre
     * @return self
     */
    public function setInvoiceNote(string $note): self {
        $this->invoiceNote = $note;
        return $this;
    }

    /**
     * Définit la période de facturation en-tête (BG-14)
     *
     * Au moins une des deux dates doit être fournie.
     * Si les deux sont fournies, endDate doit être >= startDate.
     *
     * @param string|null $startDate Date de début YYYY-MM-DD (BT-73)
     * @param string|null $endDate   Date de fin YYYY-MM-DD (BT-74)
     * @return self
     * @throws \InvalidArgumentException
     */
    public function setInvoicePeriod(?string $startDate, ?string $endDate): self {
        if ($startDate !== null && !$this->validateDate($startDate)) {
            throw new \InvalidArgumentException('Format de date de début de période invalide (YYYY-MM-DD)');
        }
        if ($endDate !== null && !$this->validateDate($endDate)) {
            throw new \InvalidArgumentException('Format de date de fin de période invalide (YYYY-MM-DD)');
        }
        if ($startDate !== null && $endDate !== null && $endDate < $startDate) {
            throw new \InvalidArgumentException(
                            'La date de fin de période ne peut pas être antérieure à la date de début'
                    );
        }

        $this->invoicePeriodStartDate = $startDate;
        $this->invoicePeriodEndDate = $endDate;
        return $this;
    }

    // =========================================================================
    // BG-3 — Référence à la facture précédente (avoirs)
    // =========================================================================

    /**
     * Définit la référence à la facture originale (BG-3)
     *
     * Utilisé principalement pour les avoirs (invoiceTypeCode = 381) afin
     * de faire référence à la facture commerciale d'origine.
     *
     * Exporté dans cac:BillingReference / cac:InvoiceDocumentReference.
     *
     * @param string      $invoiceNumber Numéro de la facture originale (BT-25)
     * @param string|null $issueDate     Date de la facture originale YYYY-MM-DD (BT-26), optionnelle
     * @return self
     * @throws \InvalidArgumentException Si le numéro est vide ou la date invalide
     */
    public function setPrecedingInvoiceReference(string $invoiceNumber, ?string $issueDate = null): self {
        if (!$this->validateNotEmpty($invoiceNumber)) {
            throw new \InvalidArgumentException(
                            'Le numéro de la facture précédente ne peut pas être vide'
                    );
        }

        if ($issueDate !== null && !$this->validateDate($issueDate)) {
            throw new \InvalidArgumentException(
                            'Format de date de la facture précédente invalide (YYYY-MM-DD)'
                    );
        }

        $this->precedingInvoiceNumber = $invoiceNumber;
        $this->precedingInvoiceDate = $issueDate;
        return $this;
    }

    // =========================================================================
    // Parties
    // =========================================================================

    /**
     * Définit le fournisseur / vendeur
     *
     * @param Party $seller Partie vendeur (BG-4)
     * @return self
     */
    public function setSeller(Party $seller): self {
        $this->seller = $seller;
        return $this;
    }

    /**
     * Définit le client / acheteur
     *
     * @param Party $buyer Partie acheteur (BG-7)
     * @return self
     */
    public function setBuyer(Party $buyer): self {
        $this->buyer = $buyer;
        return $this;
    }

    // =========================================================================
    // Lignes de facture
    // =========================================================================

    /**
     * Ajoute une ligne de facture
     *
     * @param InvoiceLine $line Ligne de facture (BG-25)
     * @return self
     */
    public function addInvoiceLine(InvoiceLine $line): self {
        $this->invoiceLines[] = $line;
        return $this;
    }

    // =========================================================================
    // Remises et majorations au niveau document (BG-20 / BG-21)
    // =========================================================================

    /**
     * Ajoute une remise ou majoration au niveau document
     *
     * @param AllowanceCharge $ac Remise (BG-20) ou majoration (BG-21)
     * @return self
     */
    public function addAllowanceCharge(AllowanceCharge $ac): self {
        $this->allowanceCharges[] = $ac;
        return $this;
    }

    /**
     * Helper — ajoute une remise simple au niveau document (BG-20)
     *
     * @param float       $amount      Montant de la remise (positif)
     * @param string      $vatCategory Catégorie TVA associée (défaut : S)
     * @param float       $vatRate     Taux TVA en % (défaut : 21.0)
     * @param string|null $reasonCode  Code raison UNCL5189 (ex : '95' = Discount)
     * @param string|null $reason      Libellé raison libre
     * @param float|null  $baseAmount  Montant de base pour calcul par pourcentage
     * @param float|null  $percent     Pourcentage (0–100)
     * @return self
     * @throws \InvalidArgumentException
     */
    public function addAllowance(
            float $amount,
            string $vatCategory = 'S',
            float $vatRate = 21.0,
            ?string $reasonCode = null,
            ?string $reason = null,
            ?float $baseAmount = null,
            ?float $percent = null
    ): self {
        return $this->addAllowanceCharge(
                        AllowanceCharge::createAllowance(
                                $amount, $vatCategory, $vatRate, $baseAmount, $percent, $reasonCode, $reason
                        )
                );
    }

    /**
     * Helper — ajoute une majoration simple au niveau document (BG-21)
     *
     * @param float       $amount      Montant de la majoration (positif)
     * @param string      $vatCategory Catégorie TVA associée (défaut : S)
     * @param float       $vatRate     Taux TVA en % (défaut : 21.0)
     * @param string|null $reasonCode  Code raison UNCL7161 (ex : 'FC' = Freight charges)
     * @param string|null $reason      Libellé raison libre
     * @param float|null  $baseAmount  Montant de base pour calcul par pourcentage
     * @param float|null  $percent     Pourcentage (0–100)
     * @return self
     * @throws \InvalidArgumentException
     */
    public function addCharge(
            float $amount,
            string $vatCategory = 'S',
            float $vatRate = 21.0,
            ?string $reasonCode = null,
            ?string $reason = null,
            ?float $baseAmount = null,
            ?float $percent = null
    ): self {
        return $this->addAllowanceCharge(
                        AllowanceCharge::createCharge(
                                $amount, $vatCategory, $vatRate, $baseAmount, $percent, $reasonCode, $reason
                        )
                );
    }

    // =========================================================================
    // Informations de paiement
    // =========================================================================

    /**
     * Définit les informations de paiement
     *
     * @param PaymentInfo $paymentInfo Informations de paiement (BG-16, BG-17)
     * @return self
     */
    public function setPaymentInfo(PaymentInfo $paymentInfo): self {
        $this->paymentInfo = $paymentInfo;
        return $this;
    }

    /**
     * Définit les conditions de paiement
     *
     * BT-20 — Obligatoire si date d'échéance non fournie et montant > 0 (BR-CO-25)
     * Met également à jour PaymentInfo si elle existe.
     *
     * @param string $paymentTerms Texte libre décrivant les conditions (BT-20)
     * @return self
     */
    public function setPaymentTerms(string $paymentTerms): self {
        $this->paymentTerms = $paymentTerms;

        // Propagation dans PaymentInfo si elle existe déjà
        if ($this->paymentInfo !== null) {
            $this->paymentInfo->setPaymentTerms($paymentTerms);
        }

        return $this;
    }

    // =========================================================================
    // Documents joints
    // =========================================================================

    /**
     * Joint un document à la facture
     *
     * @param AttachedDocument $document Document joint (BG-24)
     * @return self
     */
    public function attachDocument(AttachedDocument $document): self {
        $this->attachedDocuments[] = $document;
        return $this;
    }

    // =========================================================================
    // Montant prépayé
    // =========================================================================

    /**
     * Définit le montant prépayé / acompte (BT-113)
     *
     * Recalcule automatiquement le payableAmount si taxInclusiveAmount est déjà connu :
     *   payableAmount = taxInclusiveAmount - prepaidAmount
     *
     * @param float $prepaidAmount Montant déjà versé
     * @return self
     */
    public function setPrepaidAmount(float $prepaidAmount): self {
        $this->prepaidAmount = $prepaidAmount;
        if ($this->taxInclusiveAmount > 0.0) {
            $this->payableAmount = round($this->taxInclusiveAmount - $this->prepaidAmount, 2);
        }
        return $this;
    }

    /**
     * Retourne le montant prépayé (BT-113)
     *
     * @return float
     */
    public function getPrepaidAmount(): float {
        return $this->prepaidAmount;
    }

    // =========================================================================
    // Calcul des totaux
    // =========================================================================

    /**
     * Calcule tous les totaux de la facture selon EN 16931
     *
     * Algorithme :
     *   1. sumOfLineNetAmounts  = Σ InvoiceLine.lineAmount
     *   2. sumOfAllowances      = Σ AllowanceCharge[isAllowance].amount
     *   3. sumOfCharges         = Σ AllowanceCharge[isCharge].amount
     *   4. taxExclusiveAmount   = sumOfLineNetAmounts - sumOfAllowances + sumOfCharges  (BT-109)
     *   5. Ventilation TVA (BG-23) : lignes + impact des AllowanceCharge sur base et TVA
     *   6. taxInclusiveAmount   = taxExclusiveAmount + Σ TVA  (BT-112)
     *   7. payableAmount        = taxInclusiveAmount - prepaidAmount  (BT-115)
     *
     * Les AllowanceCharge réduisent (remises) ou augmentent (majorations)
     * à la fois la base taxable et le montant de TVA dans la ventilation BG-23.
     *
     * @return self
     * @throws \InvalidArgumentException Si la facture ne contient aucune ligne
     */
    public function calculateTotals(): self {
        if (empty($this->invoiceLines)) {
            throw new \InvalidArgumentException(
                            'Impossible de calculer les totaux sans lignes de facture'
                    );
        }

        // ---- Réinitialisation ------------------------------------------------
        $this->sumOfLineNetAmounts = 0.0;
        $this->sumOfAllowances = 0.0;
        $this->sumOfCharges = 0.0;
        $this->vatBreakdown = [];

        // ---- Lignes ----------------------------------------------------------
        foreach ($this->invoiceLines as $line) {
            $this->sumOfLineNetAmounts += $line->getLineAmount();

            $vatKey = $line->getVatCategory() . '_' . $line->getVatRate();

            if (!isset($this->vatBreakdown[$vatKey])) {
                $this->vatBreakdown[$vatKey] = new VatBreakdown(
                        $line->getVatCategory(),
                        $line->getVatRate(),
                        0.0,
                        0.0
                );
            }

            $this->vatBreakdown[$vatKey]->addAmount(
                    $line->getLineAmount(),
                    $line->getLineVatAmount()
            );
        }

        // ---- AllowanceCharge au niveau document ------------------------------
        foreach ($this->allowanceCharges as $ac) {
            $vatKey = $ac->getVatCategory() . '_' . $ac->getVatRate();

            if (!isset($this->vatBreakdown[$vatKey])) {
                $this->vatBreakdown[$vatKey] = new VatBreakdown(
                        $ac->getVatCategory(),
                        $ac->getVatRate(),
                        0.0,
                        0.0
                );
            }

            if ($ac->isAllowance()) {
                // Une remise réduit la base taxable et la TVA correspondante
                $this->sumOfAllowances += $ac->getAmount();
                $this->vatBreakdown[$vatKey]->addAmount(-$ac->getAmount(), -$ac->getVatAmount());
            } else {
                // Une majoration augmente la base taxable et la TVA correspondante
                $this->sumOfCharges += $ac->getAmount();
                $this->vatBreakdown[$vatKey]->addAmount($ac->getAmount(), $ac->getVatAmount());
            }
        }

        // ---- Arrondis --------------------------------------------------------
        $this->sumOfLineNetAmounts = round($this->sumOfLineNetAmounts, 2);
        $this->sumOfAllowances = round($this->sumOfAllowances, 2);
        $this->sumOfCharges = round($this->sumOfCharges, 2);

        // BT-109 : montant net total HT
        $this->taxExclusiveAmount = round(
                $this->sumOfLineNetAmounts - $this->sumOfAllowances + $this->sumOfCharges,
                2
        );

        // BT-110 : total TVA
        $this->totalVatAmount = 0.0;
        foreach ($this->vatBreakdown as $vat) {
            $this->totalVatAmount += $vat->getTaxAmount();
        }
        $this->totalVatAmount = round($this->totalVatAmount, 2);

        $this->taxInclusiveAmount = round($this->taxExclusiveAmount + $this->totalVatAmount, 2);
        $this->payableAmount = round($this->taxInclusiveAmount - $this->prepaidAmount, 2);

        return $this;
    }

    // =========================================================================
    // Import lenient — gestion des totaux déclarés
    // =========================================================================

    /**
     * Stocke les totaux tels que déclarés dans LegalMonetaryTotal du XML source
     *
     * Appelé uniquement par XmlImporter en mode lenient (strict=false).
     * Ces valeurs sont ensuite comparées aux totaux recalculés par checkImportedTotals().
     *
     * @param float $lineExtension Somme des montants de lignes (cbc:LineExtensionAmount)
     * @param float $taxExclusive  Montant HT (cbc:TaxExclusiveAmount)
     * @param float $taxInclusive  Montant TTC (cbc:TaxInclusiveAmount)
     * @param float $prepaid       Montant prépayé (cbc:PrepaidAmount)
     * @param float $payable       Montant à payer (cbc:PayableAmount)
     * @param float $taxAmount     Total TVA (cbc:TaxAmount de TaxTotal)
     * @return void
     */
    public function setImportedTotals(
            float $lineExtension,
            float $taxExclusive,
            float $taxInclusive,
            float $prepaid,
            float $payable,
            float $taxAmount
    ): void {
        $this->importedTotals = [
            'lineExtension' => $lineExtension,
            'taxExclusive' => $taxExclusive,
            'taxInclusive' => $taxInclusive,
            'prepaid' => $prepaid,
            'payable' => $payable,
            'taxAmount' => $taxAmount,
        ];
    }

    /**
     * Retourne les totaux importés depuis le XML source, ou null si non chargés
     *
     * @return array{lineExtension:float, taxExclusive:float, taxInclusive:float, prepaid:float, payable:float, taxAmount:float}|null
     */
    public function getImportedTotals(): ?array {
        return $this->importedTotals;
    }

    /**
     * Compare les totaux déclarés (LegalMonetaryTotal) aux totaux recalculés depuis les lignes
     *
     * Retourne un tableau d'écarts pour les champs dont la différence dépasse le seuil.
     * Retourne un tableau vide si aucun importedTotals n'a été chargé.
     *
     * Les AllowanceCharge sont intégrés dans le recalcul.
     *
     * @param float $threshold Seuil de tolérance en unités monétaires (défaut : 0.02 = 2 centimes)
     * @return array<string, array{declared:float, calculated:float, diff:float}>
     *         Clés possibles : 'taxExclusive', 'taxInclusive', 'taxAmount'
     */
    public function checkImportedTotals(float $threshold = 0.02): array {
        if ($this->importedTotals === null) {
            return [];
        }

        $recalcTaxExclusive = 0.0;
        $recalcTaxAmount = 0.0;

        foreach ($this->invoiceLines as $line) {
            $recalcTaxExclusive += $line->getLineAmount();
            $recalcTaxAmount += $line->getLineVatAmount();
        }

        // Intégrer les AllowanceCharge dans le recalcul
        foreach ($this->allowanceCharges as $ac) {
            if ($ac->isAllowance()) {
                $recalcTaxExclusive -= $ac->getAmount();
                $recalcTaxAmount -= $ac->getVatAmount();
            } else {
                $recalcTaxExclusive += $ac->getAmount();
                $recalcTaxAmount += $ac->getVatAmount();
            }
        }

        $recalcTaxExclusive = round($recalcTaxExclusive, 2);
        $recalcTaxAmount = round($recalcTaxAmount, 2);
        $recalcTaxInclusive = round($recalcTaxExclusive + $recalcTaxAmount, 2);

        $candidates = [
            'taxExclusive' => $recalcTaxExclusive,
            'taxInclusive' => $recalcTaxInclusive,
            'taxAmount' => $recalcTaxAmount,
        ];

        $warnings = [];
        foreach ($candidates as $key => $calcValue) {
            $declared = $this->importedTotals[$key];
            $diff = abs($declared - $calcValue);
            if ($diff > $threshold) {
                $warnings[$key] = [
                    'declared' => $declared,
                    'calculated' => $calcValue,
                    'diff' => $diff,
                ];
            }
        }

        return $warnings;
    }

    // =========================================================================
    // Validation
    // =========================================================================

    /**
     * Valide la facture selon les règles de base EN 16931
     *
     * Les sous-classes peuvent surcharger cette méthode pour ajouter
     * des validations spécifiques (UBL.BE, Peppol…).
     *
     * Règles vérifiées :
     *   - BR-01 : Numéro de facture obligatoire
     *   - BR-02 : Date d'émission valide
     *   - BR-03 : Code type de facture valide
     *   - BR-04 : Code devise valide
     *   - BR-06 : Vendeur obligatoire
     *   - BR-08 : Acheteur obligatoire
     *   - BR-16 : Au moins une ligne requise
     *   - BR-CO-13 : Totaux calculés
     *   - Validation des lignes, AllowanceCharge, paiement, documents joints, TVA
     *
     * @return array<string> Liste des erreurs (tableau vide si valide)
     */
    public function validate(): array {
        $errors = [];

        // BR-01: Une facture doit avoir un numéro
        if (!$this->validateNotEmpty($this->invoiceNumber)) {
            $errors[] = 'BR-01: Numéro de facture obligatoire';
        }

        // BR-02: Date d'émission obligatoire et valide
        if (!$this->validateDate($this->issueDate)) {
            $errors[] = "BR-02: Date d'émission invalide";
        }

        // BR-03: Code type obligatoire
        if (!$this->validateKeyExists($this->invoiceTypeCode, InvoiceConstants::INVOICE_TYPE_CODES)) {
            $errors[] = 'BR-03: Code type de facture invalide';
        }

        // BR-04: Devise obligatoire
        if (!$this->validateInList($this->documentCurrencyCode, InvoiceConstants::CURRENCY_CODES)) {
            $errors[] = 'BR-04: Code devise invalide';
        }

        // BR-06: Vendeur obligatoire
        if (!isset($this->seller)) {
            $errors[] = 'BR-06: Fournisseur obligatoire';
        } else {
            $sellerErrors = $this->seller->validate();
            if (!empty($sellerErrors)) {
                $errors = array_merge($errors, array_map(fn($e) => "Fournisseur: $e", $sellerErrors));
            }
        }

        // BR-08: Acheteur obligatoire
        if (!isset($this->buyer)) {
            $errors[] = 'BR-08: Client obligatoire';
        } else {
            $buyerErrors = $this->buyer->validate();
            if (!empty($buyerErrors)) {
                $errors = array_merge($errors, array_map(fn($e) => "Client: $e", $buyerErrors));
            }
        }

        // BR-16: Au moins une ligne
        if (empty($this->invoiceLines)) {
            $errors[] = 'BR-16: Au moins une ligne de facture requise';
        }

        // Validation de chaque ligne
        foreach ($this->invoiceLines as $index => $line) {
            $lineErrors = $line->validate();
            if (!empty($lineErrors)) {
                $errors = array_merge(
                        $errors,
                        array_map(fn($e) => 'Ligne ' . ($index + 1) . ": $e", $lineErrors)
                );
            }
        }

        // Validation de chaque AllowanceCharge
        foreach ($this->allowanceCharges as $index => $ac) {
            $acErrors = $ac->validate();
            if (!empty($acErrors)) {
                $label = $ac->isCharge() ? 'Majoration' : 'Remise';
                $errors = array_merge(
                        $errors,
                        array_map(fn($e) => "{$label} " . ($index + 1) . ": $e", $acErrors)
                );
            }
        }

        // BR-CO-13: Les totaux doivent être calculés
        if ($this->taxExclusiveAmount === 0.0 && !empty($this->invoiceLines)) {
            $errors[] = "BR-CO-13: Les totaux de la facture n'ont pas été calculés";
        }

        // Validation des informations de paiement
        if ($this->paymentInfo !== null) {
            $paymentErrors = $this->paymentInfo->validate();
            if (!empty($paymentErrors)) {
                $errors = array_merge(
                        $errors,
                        array_map(fn($e) => "Paiement: $e", $paymentErrors)
                );
            }
        }

        // Validation des documents joints
        foreach ($this->attachedDocuments as $index => $doc) {
            $docErrors = $doc->validate();
            if (!empty($docErrors)) {
                $errors = array_merge(
                        $errors,
                        array_map(fn($e) => 'Document ' . ($index + 1) . ": $e", $docErrors)
                );
            }
        }

        // Validation des ventilations TVA
        foreach ($this->vatBreakdown as $vat) {
            $vatErrors = $vat->validate();
            if (!empty($vatErrors)) {
                $errors = array_merge($errors, array_map(fn($e) => "TVA: $e", $vatErrors));
            }
        }

        // BG-3 : date de la facture précédente doit être valide si fournie
        if ($this->precedingInvoiceDate !== null && !$this->validateDate($this->precedingInvoiceDate)) {
            $errors[] = 'BG-3: Date de la facture précédente invalide';
        }

        return $errors;
    }

    // =========================================================================
    // Sérialisation
    // =========================================================================

    /**
     * Implémentation de \JsonSerializable
     *
     * Permet d'utiliser directement json_encode($invoice) dans les webservices.
     * Délègue à toArray().
     *
     * @return mixed
     */
    public function jsonSerialize(): mixed {
        return $this->toArray();
    }

    /**
     * Retourne la facture sous forme de tableau associatif
     *
     * Le contenu binaire des documents joints n'est pas inclus (uniquement
     * les métadonnées : filename, mimeType, description) pour éviter des
     * réponses JSON trop volumineuses.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array {
        return [
            'invoiceNumber' => $this->invoiceNumber,
            'issueDate' => $this->issueDate,
            'dueDate' => $this->dueDate,
            'deliveryDate' => $this->deliveryDate,
            'invoiceTypeCode' => $this->invoiceTypeCode,
            'documentCurrencyCode' => $this->documentCurrencyCode,
            'buyerReference' => $this->buyerReference,
            'purchaseOrderReference' => $this->purchaseOrderReference,
            'salesOrderReference' => $this->salesOrderReference,
            'contractReference' => $this->contractReference,
            'receivingAdviceReference' => $this->receivingAdviceReference,
            'despatchAdviceReference' => $this->despatchAdviceReference,
            'projectReference' => $this->projectReference,
            'buyerAccountingReference' => $this->buyerAccountingReference,
            'invoiceNote' => $this->invoiceNote,
            'invoicePeriod' => ($this->invoicePeriodStartDate !== null || $this->invoicePeriodEndDate !== null) ? [
        'startDate' => $this->invoicePeriodStartDate,
        'endDate' => $this->invoicePeriodEndDate,
            ] : null,
            'precedingInvoice' => $this->precedingInvoiceNumber !== null ? [
        'number' => $this->precedingInvoiceNumber,
        'issueDate' => $this->precedingInvoiceDate,
            ] : null,
            'seller' => isset($this->seller) ? $this->seller->toArray() : null,
            'buyer' => isset($this->buyer) ? $this->buyer->toArray() : null,
            'lines' => array_map(fn($line) => $line->toArray(), $this->invoiceLines),
            'allowanceCharges' => array_map(fn($ac) => $ac->toArray(), $this->allowanceCharges),
            'totals' => [
                'sumOfLineNetAmounts' => $this->sumOfLineNetAmounts,
                'sumOfAllowances' => $this->sumOfAllowances,
                'sumOfCharges' => $this->sumOfCharges,
                'taxExclusiveAmount' => $this->taxExclusiveAmount,
                'taxInclusiveAmount' => $this->taxInclusiveAmount,
                'taxAmount' => round($this->taxInclusiveAmount - $this->taxExclusiveAmount, 2),
                'prepaidAmount' => $this->prepaidAmount,
                'payableAmount' => $this->payableAmount,
            ],
            'vatBreakdown' => array_map(fn($vat) => $vat->toArray(), array_values($this->vatBreakdown)),
            'payment' => $this->paymentInfo?->toArray(),
            'paymentTerms' => $this->paymentTerms,
            // Métadonnées uniquement — pas le contenu binaire
            'attachedDocuments' => array_map(fn($doc) => [
                'filename' => $doc->getFilename(),
                'mimeType' => $doc->getMimeType(),
                'description' => $doc->getDescription(),
                    ], $this->attachedDocuments),
        ];
    }

    // =========================================================================
    // Getters
    // =========================================================================

    /**
     * Retourne le numéro de facture (BT-1)
     * @return string
     */
    public function getInvoiceNumber(): string {
        return $this->invoiceNumber;
    }

    /**
     * Retourne la date d'émission YYYY-MM-DD (BT-2)
     * @return string
     */
    public function getIssueDate(): string {
        return $this->issueDate;
    }

    /**
     * Retourne la date d'échéance YYYY-MM-DD (BT-9), ou null si non définie
     * @return string|null
     */
    public function getDueDate(): ?string {
        return $this->dueDate;
    }

    /**
     * Retourne la date de livraison YYYY-MM-DD (BT-72), ou null si non définie
     * @return string|null
     */
    public function getDeliveryDate(): ?string {
        return $this->deliveryDate;
    }

    /**
     * Retourne le code type de facture UNCL1001 (BT-3)
     * @return string
     */
    public function getInvoiceTypeCode(): string {
        return $this->invoiceTypeCode;
    }

    /**
     * Retourne le code devise ISO 4217 (BT-5)
     * @return string
     */
    public function getDocumentCurrencyCode(): string {
        return $this->documentCurrencyCode;
    }

    /**
     * Retourne la référence acheteur (BT-10), ou null si non définie
     * @return string|null
     */
    public function getBuyerReference(): ?string {
        return $this->buyerReference;
    }

    /**
     * Retourne la référence du bon de commande acheteur (BT-13), ou null
     * @return string|null
     */
    public function getPurchaseOrderReference(): ?string {
        return $this->purchaseOrderReference;
    }

    /**
     * Retourne la référence de l'ordre de vente vendeur (BT-14), ou null
     * @return string|null
     */
    public function getSalesOrderReference(): ?string {
        return $this->salesOrderReference;
    }

    /**
     * Retourne la référence du contrat (BT-12), ou null
     * @return string|null
     */
    public function getContractReference(): ?string {
        return $this->contractReference;
    }

    /**
     * Retourne la référence de l'avis de réception (BT-15), ou null
     * @return string|null
     */
    public function getReceivingAdviceReference(): ?string {
        return $this->receivingAdviceReference;
    }

    /**
     * Retourne la référence de l'avis d'expédition (BT-16), ou null
     * @return string|null
     */
    public function getDespatchAdviceReference(): ?string {
        return $this->despatchAdviceReference;
    }

    /**
     * Retourne la référence de projet (BT-11), ou null
     * @return string|null
     */
    public function getProjectReference(): ?string {
        return $this->projectReference;
    }

    /**
     * Retourne la référence comptable acheteur en-tête (BT-19), ou null
     * @return string|null
     */
    public function getBuyerAccountingReference(): ?string {
        return $this->buyerAccountingReference;
    }

    /**
     * Retourne la note de facture libre (BT-22), ou null
     * @return string|null
     */
    public function getInvoiceNote(): ?string {
        return $this->invoiceNote;
    }

    /**
     * Retourne le numéro de la facture précédente référencée (BT-25), ou null
     * @return string|null
     */
    public function getPrecedingInvoiceNumber(): ?string {
        return $this->precedingInvoiceNumber;
    }

    /**
     * Retourne la date de la facture précédente référencée (BT-26), ou null
     * @return string|null
     */
    public function getPrecedingInvoiceDate(): ?string {
        return $this->precedingInvoiceDate;
    }

    /**
     * Retourne le fournisseur / vendeur (BG-4)
     * @return Party
     */
    public function getSeller(): Party {
        return $this->seller;
    }

    /**
     * Retourne le client / acheteur (BG-7)
     * @return Party
     */
    public function getBuyer(): Party {
        return $this->buyer;
    }

    /**
     * Retourne la liste des lignes de facture (BG-25)
     * @return array<InvoiceLine>
     */
    public function getInvoiceLines(): array {
        return $this->invoiceLines;
    }

    /**
     * Retourne la liste des remises et majorations au niveau document (BG-20/BG-21)
     * @return array<AllowanceCharge>
     */
    public function getAllowanceCharges(): array {
        return $this->allowanceCharges;
    }

    /**
     * Retourne les informations de paiement (BG-16, BG-17), ou null
     * @return PaymentInfo|null
     */
    public function getPaymentInfo(): ?PaymentInfo {
        return $this->paymentInfo;
    }

    /**
     * Retourne les conditions de paiement (BT-20), ou null
     * @return string|null
     */
    public function getPaymentTerms(): ?string {
        return $this->paymentTerms;
    }

    /**
     * Retourne la liste des documents joints (BG-24)
     * @return array<AttachedDocument>
     */
    public function getAttachedDocuments(): array {
        return $this->attachedDocuments;
    }

    /**
     * Retourne la somme des montants nets de lignes (BT-106)
     * @return float
     */
    public function getSumOfLineNetAmounts(): float {
        return $this->sumOfLineNetAmounts;
    }

    /**
     * Retourne la somme des remises au niveau document (BT-107)
     * @return float
     */
    public function getSumOfAllowances(): float {
        return $this->sumOfAllowances;
    }

    /**
     * Retourne la somme des majorations au niveau document (BT-108)
     * @return float
     */
    public function getSumOfCharges(): float {
        return $this->sumOfCharges;
    }

    /**
     * Retourne le montant net total hors taxes (BT-109)
     * @return float
     */
    public function getTaxExclusiveAmount(): float {
        return $this->taxExclusiveAmount;
    }

    /**
     * Retourne le montant total TVA comprise (BT-112)
     * @return float
     */
    public function getTaxInclusiveAmount(): float {
        return $this->taxInclusiveAmount;
    }

    /**
     * Retourne le montant à payer (BT-115)
     * @return float
     */
    public function getPayableAmount(): float {
        return $this->payableAmount;
    }

    /**
     * Retourne la ventilation par taux de TVA (BG-23)
     *
     * @return array<string, VatBreakdown> Clé : "{catégorie}_{taux}" ex : "S_21"
     */
    public function getVatBreakdown(): array {
        return $this->vatBreakdown;
    }

    public function getTotalVatAmount(): float {
        return $this->totalVatAmount;
    }

    /**
     * Retourne la date de début de la période de facturation (BT-73), ou null
     * @return string|null
     */
    public function getInvoicePeriodStartDate(): ?string {
        return $this->invoicePeriodStartDate;
    }

    /**
     * Retourne la date de fin de la période de facturation (BT-74), ou null
     * @return string|null
     */
    public function getInvoicePeriodEndDate(): ?string {
        return $this->invoicePeriodEndDate;
    }
}
