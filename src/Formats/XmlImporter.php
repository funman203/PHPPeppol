<?php

declare(strict_types=1);

namespace Peppol\Formats;

use Peppol\Core\InvoiceBase;
use Peppol\Standards\EN16931Invoice;
use Peppol\Standards\UblBeInvoice;
use Peppol\Models\Address;
use Peppol\Models\ElectronicAddress;
use Peppol\Models\Party;
use Peppol\Models\InvoiceLine;
use Peppol\Models\PaymentInfo;
use Peppol\Models\AttachedDocument;
use Peppol\Models\AllowanceCharge;
use Peppol\Exceptions\ImportWarningException;

/**
 * Importateur XML UBL 2.1
 *
 * Parse un document XML UBL 2.1 et crée une instance de facture
 * (EN16931Invoice ou UblBeInvoice selon le CustomizationID détecté).
 *
 * Deux modes d'import :
 *
 *   Mode strict (défaut) :
 *     - Toute donnée invalide (BIC malformé, unitCode inconnu…) lève une exception
 *     - Les totaux sont recalculés depuis les lignes
 *     - La facture est validée avant d'être retournée
 *
 *   Mode lenient (strict=false) :
 *     - Les données invalides sont chargées telles quelles avec un avertissement
 *     - Les totaux déclarés dans LegalMonetaryTotal sont comparés aux totaux recalculés
 *     - Si des écarts ou anomalies sont détectés, une ImportWarningException est levée
 *       (la facture reste récupérable via $e->getInvoice())
 *     - Si tout est cohérent, la facture est retournée normalement
 *
 * @package Peppol\Formats
 * @version 1.1
 */
class XmlImporter {
    // =========================================================================
    // Point d'entrée principal
    // =========================================================================

    /**
     * Importe une facture depuis un contenu XML UBL 2.1 ou un chemin de fichier
     *
     * Auto-détecte le type de facture depuis cbc:CustomizationID :
     *   - Contient 'UBL.BE' → UblBeInvoice
     *   - Sinon → EN16931Invoice
     *
     * En mode strict (défaut) :
     *   Toute donnée invalide lève une \InvalidArgumentException.
     *   Les totaux sont recalculés depuis les lignes.
     *
     * En mode lenient :
     *   Les anomalies (BIC invalide, unitCode non standard…) sont collectées.
     *   Les totaux déclarés sont comparés aux totaux recalculés.
     *   Si des écarts ou anomalies existent, une ImportWarningException est levée ;
     *   sinon la facture est retournée normalement.
     *
     * @param string      $xmlContent  Contenu XML en chaîne, ou chemin vers un fichier XML
     * @param string|null $targetClass Classe cible à instancier (auto-détectée si null)
     * @param bool        $strict      true = mode strict (défaut), false = mode lenient
     *
     * @return InvoiceBase Facture importée
     *
     * @throws \InvalidArgumentException     En mode strict si le XML est invalide ou une donnée est incorrecte
     * @throws ImportWarningException        En mode lenient si des écarts ou anomalies sont détectés
     */
    public static function fromUbl(
            string $xmlContent,
            ?string $targetClass = null,
            bool $strict = true
    ): InvoiceBase {
        // Lecture du fichier si chemin fourni
        if (file_exists($xmlContent)) {
            $xmlContent = file_get_contents($xmlContent);
            if ($xmlContent === false) {
                throw new \InvalidArgumentException("Impossible de lire le fichier XML");
            }
        }

        // Chargement du XML avec gestion d'erreurs
        libxml_use_internal_errors(true);
        $xml = new \DOMDocument();
        if (!$xml->loadXML($xmlContent)) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            throw new \InvalidArgumentException("XML invalide: " . ($errors[0]->message ?? 'Erreur inconnue'));
        }

        // Création du contexte XPath avec namespaces UBL
        $xpath = new \DOMXPath($xml);
        $xpath->registerNamespace('ubl', 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2');
        $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');

        // Auto-détection du type si non spécifié
        if ($targetClass === null) {
            $targetClass = self::detectInvoiceType($xpath);
        }

        // Extraction des données obligatoires
        $invoiceNumber = self::getXPathValue($xpath, '//cbc:ID');
        $issueDate = self::getXPathValue($xpath, '//cbc:IssueDate');
        $invoiceTypeCode = self::getXPathValue($xpath, '//cbc:InvoiceTypeCode', '380');
        $currencyCode = self::getXPathValue($xpath, '//cbc:DocumentCurrencyCode', 'EUR');

        if (empty($invoiceNumber) || empty($issueDate)) {
            throw new \InvalidArgumentException(
                            "Le XML doit contenir au minimum un numéro de facture (cbc:ID) et une date d'émission (cbc:IssueDate)"
                    );
        }

        // Instanciation de la classe cible
        /** @var InvoiceBase $invoice */
        $invoice = new $targetClass($invoiceNumber, $issueDate, $invoiceTypeCode, $currencyCode);

        // Collecteur d'anomalies (mode lenient uniquement)
        $anomalies = [];

        // Chargement des données dans l'ordre logique
        self::loadBasicData($invoice, $xpath, $strict, $anomalies);
        self::loadSeller($invoice, $xpath);
        self::loadBuyer($invoice, $xpath);
        self::loadPaymentInfo($invoice, $xpath, $strict, $anomalies);
        self::loadAttachedDocuments($invoice, $xpath);
        self::loadAllowanceCharges($invoice, $xpath, $strict, $anomalies);
        self::loadInvoiceLines($invoice, $xpath, $strict, $anomalies);

        if ($strict) {
            // Mode strict : recalcul et retour direct
            if (!empty($invoice->getInvoiceLines())) {
                $invoice->calculateTotals();
            }
        } else {
            // Mode lenient : charge les totaux déclarés pour comparaison
            self::loadDeclaredTotals($invoice, $xpath);

            if (!empty($invoice->getInvoiceLines())) {
                $invoice->calculateTotals();
            }

            // Comparaison totaux déclarés vs recalculés
            $warnings = $invoice->checkImportedTotals();

            // Lance l'exception si anomalies ou écarts détectés
            // La facture reste accessible via $e->getInvoice()
            if (!empty($warnings) || !empty($anomalies)) {
                throw new ImportWarningException($invoice, $warnings, $anomalies);
            }
        }

        // Chargement des données spécifiques UBL.BE (après calcul des totaux)
        if ($invoice instanceof UblBeInvoice) {
            self::loadUblBeSpecificData($invoice, $xpath);
        }

        return $invoice;
    }

    // =========================================================================
    // Détection du type de facture
    // =========================================================================

    /**
     * Détecte le type de facture depuis le cbc:CustomizationID
     *
     * @param \DOMXPath $xpath
     * @return string Nom complet de la classe à instancier
     */
    private static function detectInvoiceType(\DOMXPath $xpath): string {
        $customizationId = self::getXPathValue($xpath, '//cbc:CustomizationID', '');

        if (strpos($customizationId, 'UBL.BE') !== false) {
            return UblBeInvoice::class;
        }

        return EN16931Invoice::class;
    }

    // =========================================================================
    // Données de base
    // =========================================================================

    /**
     * Charge les données de base de la facture depuis le XML
     *
     * Éléments chargés :
     *   - DueDate (BT-9)
     *   - ActualDeliveryDate (BT-72, dans cac:Delivery)
     *   - BuyerReference (BT-10)
     *   - OrderReference/ID (BT-13) et SalesOrderID (BT-14)
     *   - ContractDocumentReference/ID (BT-12)
     *   - DespatchDocumentReference/ID (BT-16)
     *   - ReceiptDocumentReference/ID (BT-15)
     *   - ProjectReference/ID (BT-11)
     *   - AccountingCost (BT-19)
     *   - Note directe de l'Invoice (BT-22, distinct de PaymentTerms)
     *   - BillingReference / InvoiceDocumentReference (BG-3 : BT-25 + BT-26)
     *
     * @param InvoiceBase $invoice
     * @param \DOMXPath   $xpath
     */
    private static function loadBasicData(InvoiceBase $invoice, \DOMXPath $xpath, bool $strict, array &$anomalies): void {
        // BT-9 — Date d'échéance
        $dueDate = self::getXPathValue($xpath, '//cbc:DueDate');
        if ($dueDate) {
            try {
                $invoice->setDueDate($dueDate);
            } catch (\Exception $e) {
                if ($strict) {
                    throw $e;
                }
                $anomalies[] = sprintf('BT-9 : date d\'échéance invalide « %s » ignorée — %s', $dueDate, $e->getMessage());
            }
        }

        // BT-72 — Date de livraison (dans cac:Delivery)
        $deliveryDate = self::getXPathValue($xpath, '//cac:Delivery/cbc:ActualDeliveryDate');
        if ($deliveryDate) {
            try {
                $invoice->setDeliveryDate($deliveryDate);
            } catch (\Exception $e) {
                if ($strict) {
                    throw $e;
                }
                $anomalies[] = sprintf('BT-72 : date de livraison invalide « %s » ignorée — %s', $deliveryDate, $e->getMessage());
            }
        }

        // BT-10 — Référence acheteur
        $buyerRef = self::getXPathValue($xpath, '//cbc:BuyerReference');
        if ($buyerRef) {
            $invoice->setBuyerReference($buyerRef);
        }

        // BT-13 — Référence commande acheteur
        $orderRef = self::getXPathValue($xpath, '//cac:OrderReference/cbc:ID');
        if ($orderRef) {
            $invoice->setPurchaseOrderReference($orderRef);
        }

        // BT-14 — Référence ordre de vente vendeur
        $salesOrderRef = self::getXPathValue($xpath, '//cac:OrderReference/cbc:SalesOrderID');
        if ($salesOrderRef) {
            $invoice->setSalesOrderReference($salesOrderRef);
        }

        // BT-12 — Référence contrat
        $contractRef = self::getXPathValue($xpath, '//cac:ContractDocumentReference/cbc:ID');
        if ($contractRef) {
            $invoice->setContractReference($contractRef);
        }

        // BT-16 — Référence avis d'expédition
        $despatch = self::getXPathValue($xpath, '//cac:DespatchDocumentReference/cbc:ID');
        if ($despatch) {
            $invoice->setDespatchAdviceReference($despatch);
        }

        // BT-15 — Référence avis de réception
        $receiving = self::getXPathValue($xpath, '//cac:ReceiptDocumentReference/cbc:ID');
        if ($receiving) {
            $invoice->setReceivingAdviceReference($receiving);
        }

        // BT-11 — Référence projet
        $project = self::getXPathValue($xpath, '//cac:ProjectReference/cbc:ID');
        if ($project) {
            $invoice->setProjectReference($project);
        }

        // BT-19 — Référence comptable acheteur
        $accountingCost = self::getXPathValue($xpath, '//cbc:AccountingCost');
        if ($accountingCost) {
            $invoice->setBuyerAccountingReference($accountingCost);
        }

        // BT-22 — Note facture (enfant direct de Invoice, pas dans cac:PaymentTerms)
        $note = self::getXPathValue($xpath, '/Invoice/cbc:Note');
        if ($note) {
            $invoice->setInvoiceNote($note);
        }

        // BG-3 — Référence facture précédente (BT-25 + BT-26)
        $precedingNumber = self::getXPathValue(
                $xpath,
                '//cac:BillingReference/cac:InvoiceDocumentReference/cbc:ID'
        );
        if ($precedingNumber) {
            $precedingDate = self::getXPathValue(
                    $xpath,
                    '//cac:BillingReference/cac:InvoiceDocumentReference/cbc:IssueDate'
            );
            try {
                $invoice->setPrecedingInvoiceReference($precedingNumber, $precedingDate ?: null);
            } catch (\Exception $e) {
                if ($strict) {
                    throw $e;
                }
                $anomalies[] = sprintf('BG-3 : référence facture précédente invalide ignorée — %s', $e->getMessage());
            }
        }
// BG-14 — Période de facturation en-tête (BT-73 + BT-74)
        $periodStart = self::getXPathValue($xpath, '//ubl:Invoice/cac:InvoicePeriod/cbc:StartDate');
        $periodEnd = self::getXPathValue($xpath, '//ubl:Invoice/cac:InvoicePeriod/cbc:EndDate');
        if ($periodStart !== null || $periodEnd !== null) {
            try {
                $invoice->setInvoicePeriod($periodStart, $periodEnd);
            } catch (\Exception $e) {
                if ($strict) {
                    throw $e;
                }
                $anomalies[] = sprintf('BG-14 : période de facturation en-tête invalide ignorée — %s', $e->getMessage());
            }
        }
    }

    // =========================================================================
    // Vendeur (BG-4)
    // =========================================================================

    /**
     * Charge les données du vendeur / fournisseur (BG-4)
     *
     * Données chargées : nom (RegistrationName), TVA (CompanyID), adresse postale,
     * identifiant entreprise (CompanyID dans PartyLegalEntity), email de contact,
     * et adresse électronique (EndpointID avec schemeID).
     *
     * La méthode retourne silencieusement si les données minimales
     * (nom, TVA, adresse) sont manquantes.
     *
     * @param InvoiceBase $invoice
     * @param \DOMXPath   $xpath
     */
    private static function loadSeller(InvoiceBase $invoice, \DOMXPath $xpath): void {
        $basePath = '//cac:AccountingSupplierParty/cac:Party';

        $name = self::getXPathValue($xpath, "{$basePath}/cac:PartyLegalEntity/cbc:RegistrationName");
        $vatId = self::getXPathValue($xpath, "{$basePath}/cac:PartyTaxScheme/cbc:CompanyID");

        if (!$name || !$vatId) {
            return; // Données minimales manquantes
        }

        // Adresse postale
        $addressPath = "{$basePath}/cac:PostalAddress";
        $streetName = self::getXPathValue($xpath, "{$addressPath}/cbc:StreetName", '');
        $cityName = self::getXPathValue($xpath, "{$addressPath}/cbc:CityName", '');
        $postalZone = self::getXPathValue($xpath, "{$addressPath}/cbc:PostalZone", '');
        $countryCode = self::getXPathValue($xpath, "{$addressPath}/cac:Country/cbc:IdentificationCode", '');

        if (!$streetName || !$cityName || !$postalZone || !$countryCode) {
            return; // Adresse minimale manquante
        }

        $address = new Address($streetName, $cityName, $postalZone, $countryCode);

        // Autres données vendeur
        $companyId = self::getXPathValue($xpath, "{$basePath}/cac:PartyLegalEntity/cbc:CompanyID");
        $email = self::getXPathValue($xpath, "{$basePath}/cac:Contact/cbc:ElectronicMail");

        // Adresse électronique (BT-34)
        $electronicAddress = null;
        $endpointId = self::getXPathValue($xpath, "{$basePath}/cbc:EndpointID");
        if ($endpointId) {
            $endpointNode = $xpath->query("{$basePath}/cbc:EndpointID")->item(0);
            $schemeId = ($endpointNode instanceof \DOMElement) ? ($endpointNode->getAttribute('schemeID') ?: '9925') : '9925';
            try {
                $electronicAddress = new ElectronicAddress($schemeId, $endpointId);
            } catch (\Exception $e) {
                // Schéma non reconnu — on ignore l'adresse électronique
            }
        }

        $seller = new Party($name, $address, $vatId, $companyId, $email, $electronicAddress);
        $invoice->setSeller($seller);
    }

    // =========================================================================
    // Acheteur (BG-7)
    // =========================================================================

    /**
     * Charge les données de l'acheteur / client (BG-7)
     *
     * Données chargées : nom (RegistrationName), adresse postale,
     * TVA (CompanyID dans PartyTaxScheme), identifiant légal (CompanyID BT-47),
     * email de contact, et adresse électronique (EndpointID avec schemeID).
     *
     * @param InvoiceBase $invoice
     * @param \DOMXPath   $xpath
     */
    private static function loadBuyer(InvoiceBase $invoice, \DOMXPath $xpath): void {
        $basePath = '//cac:AccountingCustomerParty/cac:Party';

        $name = self::getXPathValue($xpath, "{$basePath}/cac:PartyLegalEntity/cbc:RegistrationName");
        if (!$name) {
            return;
        }

        // Adresse postale
        $addressPath = "{$basePath}/cac:PostalAddress";
        $streetName = self::getXPathValue($xpath, "{$addressPath}/cbc:StreetName", '');
        $cityName = self::getXPathValue($xpath, "{$addressPath}/cbc:CityName", '');
        $postalZone = self::getXPathValue($xpath, "{$addressPath}/cbc:PostalZone", '');
        $countryCode = self::getXPathValue($xpath, "{$addressPath}/cac:Country/cbc:IdentificationCode", '');

        if (!$streetName || !$cityName || !$postalZone || !$countryCode) {
            return;
        }

        $address = new Address($streetName, $cityName, $postalZone, $countryCode);

        $vatId = self::getXPathValue($xpath, "{$basePath}/cac:PartyTaxScheme/cbc:CompanyID");
        $companyId = self::getXPathValue($xpath, "{$basePath}/cac:PartyLegalEntity/cbc:CompanyID"); // BT-47
        $email = self::getXPathValue($xpath, "{$basePath}/cac:Contact/cbc:ElectronicMail");

        // Adresse électronique (BT-49)
        $electronicAddress = null;
        $endpointId = self::getXPathValue($xpath, "{$basePath}/cbc:EndpointID");
        if ($endpointId) {
            $endpointNode = $xpath->query("{$basePath}/cbc:EndpointID")->item(0);
            $schemeId = ($endpointNode instanceof \DOMElement) ? $endpointNode?->getAttribute('schemeID') ?? '9925' : '9925';
            try {
                $electronicAddress = new ElectronicAddress($schemeId, $endpointId);
            } catch (\Exception $e) {
                // Schéma non reconnu — on ignore l'adresse électronique
            }
        }

        $buyer = new Party($name, $address, $vatId, $companyId, $email, $electronicAddress);
        $invoice->setBuyer($buyer);
    }

    // =========================================================================
    // Documents joints (BG-24)
    // =========================================================================

    /**
     * Charge les documents joints (BG-24)
     *
     * Critère de sélection : tout cac:AdditionalDocumentReference contenant
     * un cac:Attachment / cbc:EmbeddedDocumentBinaryObject avec contenu,
     * mimeCode et filename est chargé. Les autres sont ignorés silencieusement.
     *
     * @param InvoiceBase $invoice
     * @param \DOMXPath   $xpath
     */
    private static function loadAttachedDocuments(InvoiceBase $invoice, \DOMXPath $xpath): void {
        $attachedDocs = $xpath->query('//cac:AdditionalDocumentReference');

        foreach ($attachedDocs as $docNode) {
            $embeddedDocNode = $xpath->query(
                            'cac:Attachment/cbc:EmbeddedDocumentBinaryObject',
                            $docNode
                    )->item(0);

            if (!$embeddedDocNode) {
                continue; // Pas de contenu binaire — on ignore
            }

            $base64Content = $embeddedDocNode->nodeValue;
            if (!($embeddedDocNode instanceof \DOMElement)) {
                continue;
            }
            $mimeType = $embeddedDocNode->getAttribute('mimeCode');
            $filename = $embeddedDocNode->getAttribute('filename');

            if (!$base64Content || !$mimeType || !$filename) {
                continue;
            }

            $fileContent = base64_decode($base64Content);
            $description = self::getXPathValue($xpath, 'cbc:DocumentDescription', null, $docNode);
            $documentType = self::getXPathValue($xpath, 'cbc:DocumentTypeCode', null, $docNode);

            try {
                $document = new AttachedDocument($filename, $fileContent, $mimeType, $description, $documentType);
                $invoice->attachDocument($document);
            } catch (\Exception $e) {
                // Document invalide (type MIME non supporté, taille…) — on ignore
            }
        }
    }

    // =========================================================================
    // Informations de paiement (BG-16 / BG-17)
    // =========================================================================

    /**
     * Charge les informations de paiement (BG-16 / BG-17)
     *
     * En mode strict : tout BIC invalide lève une InvalidArgumentException.
     * En mode lenient : le BIC invalide est chargé tel quel via PaymentInfo::withRawBic()
     *   et une anomalie est enregistrée dans $anomalies.
     *
     * Les conditions de paiement (BT-20) sont lues depuis cac:PaymentTerms/cbc:Note
     * et synchronisées dans PaymentInfo et dans la facture via setPaymentTerms().
     *
     * @param InvoiceBase   $invoice
     * @param \DOMXPath     $xpath
     * @param bool          $strict   true = strict, false = lenient
     * @param array<string> $anomalies Collecteur d'anomalies (passé par référence)
     *
     * @throws \InvalidArgumentException En mode strict si les données de paiement sont invalides
     */
    private static function loadPaymentInfo(
            InvoiceBase $invoice,
            \DOMXPath $xpath,
            bool $strict,
            array &$anomalies
    ): void {
        $iban = self::getXPathValue($xpath, '//cac:PaymentMeans/cac:PayeeFinancialAccount/cbc:ID');
        if (!$iban) {
            return; // Pas d'IBAN — on ignore le bloc paiement
        }

        $paymentMeansCode = self::getXPathValue($xpath, '//cac:PaymentMeans/cbc:PaymentMeansCode', '30');
        $bic = self::getXPathValue($xpath,
                '//cac:PaymentMeans/cac:PayeeFinancialAccount/cac:FinancialInstitutionBranch/cbc:ID');
        $paymentRef = self::getXPathValue($xpath, '//cac:PaymentMeans/cbc:PaymentID');
        // BT-20 — Conditions de paiement dans cac:PaymentTerms/cbc:Note
        $paymentTerms = self::getXPathValue($xpath, '//cac:PaymentTerms/cbc:Note');

        try {
            $paymentInfo = new PaymentInfo($paymentMeansCode, $iban, $bic, $paymentRef, $paymentTerms);
            $invoice->setPaymentInfo($paymentInfo);
            if ($paymentTerms) {
                $invoice->setPaymentTerms($paymentTerms);
            }
        } catch (\InvalidArgumentException $e) {
            if ($strict) {
                throw $e;
            }

            // Mode lenient : charge le BIC brut sans validation
            try {
                $paymentInfo = PaymentInfo::withRawBic(
                        $paymentMeansCode, $iban, $bic, $paymentRef, $paymentTerms
                );
                $invoice->setPaymentInfo($paymentInfo);
                if ($paymentTerms) {
                    $invoice->setPaymentTerms($paymentTerms);
                }
                $anomalies[] = sprintf(
                        'Paiement : BIC invalide « %s » chargé tel quel — %s',
                        $bic ?? '',
                        $e->getMessage()
                );
            } catch (\Exception $e2) {
                $anomalies[] = 'Bloc paiement ignoré : ' . $e2->getMessage();
            }
        }
    }

    // =========================================================================
    // AllowanceCharge au niveau document (BG-20 / BG-21)
    // =========================================================================

    /**
     * Charge les remises et majorations au niveau document (BG-20 / BG-21)
     *
     * Seuls les cac:AllowanceCharge enfants directs de l'élément Invoice sont
     * traités (ceux dans cac:InvoiceLine appartiennent au niveau ligne — BG-28,
     * non encore implémenté).
     *
     * En mode strict : toute AllowanceCharge invalide lève une exception.
     * En mode lenient : les AllowanceCharge invalides sont ignorées avec une anomalie.
     *
     * @param InvoiceBase   $invoice
     * @param \DOMXPath     $xpath
     * @param bool          $strict
     * @param array<string> $anomalies Collecteur d'anomalies (passé par référence)
     *
     * @throws \InvalidArgumentException En mode strict si une AllowanceCharge est invalide
     */
    private static function loadAllowanceCharges(
            InvoiceBase $invoice,
            \DOMXPath $xpath,
            bool $strict,
            array &$anomalies
    ): void {
        // Sélection des AllowanceCharge enfants directs de Invoice (pas ceux des lignes)
        $nodes = $xpath->query('/ubl:Invoice/cac:AllowanceCharge');

        foreach ($nodes as $node) {
            $chargeIndicatorStr = self::getXPathValue($xpath, 'cbc:ChargeIndicator', 'false', $node);
            $chargeIndicator = strtolower(trim($chargeIndicatorStr)) === 'true';

            $amount = (float) self::getXPathValue($xpath, 'cbc:Amount', '0', $node);
            $baseAmount = self::getXPathValue($xpath, 'cbc:BaseAmount', null, $node);
            $percent = self::getXPathValue($xpath, 'cbc:MultiplierFactorNumeric', null, $node);
            $reasonCode = self::getXPathValue($xpath, 'cbc:AllowanceChargeReasonCode', null, $node);
            $reason = self::getXPathValue($xpath, 'cbc:AllowanceChargeReason', null, $node);
            $vatCat = self::getXPathValue($xpath, 'cac:TaxCategory/cbc:ID', 'S', $node);
            $vatRate = (float) self::getXPathValue($xpath, 'cac:TaxCategory/cbc:Percent', '0', $node);

            try {
                $ac = new AllowanceCharge(
                        $chargeIndicator,
                        $amount,
                        $vatCat,
                        $vatRate,
                        $baseAmount !== null ? (float) $baseAmount : null,
                        $percent !== null ? (float) $percent : null,
                        $reasonCode,
                        $reason
                );
                $invoice->addAllowanceCharge($ac);
            } catch (\InvalidArgumentException $e) {
                if ($strict) {
                    throw $e;
                }
                // Mode lenient : on ignore cette AllowanceCharge et on enregistre l'anomalie
                $anomalies[] = sprintf(
                        'AllowanceCharge (%s) ignorée : %s',
                        $chargeIndicator ? 'majoration' : 'remise',
                        $e->getMessage()
                );
            }
        }
    }

    // =========================================================================
    // Lignes de facture (BG-25)
    // =========================================================================

    /**
     * Charge les lignes de facture (BG-25)
     *
     * En mode strict : un unitCode non reconnu dans InvoiceConstants::UNIT_CODES
     *   lève une InvalidArgumentException.
     * En mode lenient : la ligne est créée avec unitCode='C62' (valeur par défaut),
     *   puis le vrai unitCode est injecté via ReflectionProperty pour préserver
     *   la valeur originale. Une anomalie est enregistrée.
     *
     * Les lignes avec quantité <= 0 ou sans ID/nom sont silencieusement ignorées.
     *
     * @param InvoiceBase   $invoice
     * @param \DOMXPath     $xpath
     * @param bool          $strict
     * @param array<string> $anomalies Collecteur d'anomalies (passé par référence)
     *
     * @throws \InvalidArgumentException En mode strict si une ligne est invalide
     */
    private static function loadInvoiceLines(
            InvoiceBase $invoice,
            \DOMXPath $xpath,
            bool $strict,
            array &$anomalies
    ): void {
        $lines = $xpath->query('//cac:InvoiceLine');

        foreach ($lines as $lineNode) {
            $lineId = self::getXPathValue($xpath, 'cbc:ID', null, $lineNode);
            $lineName = self::getXPathValue($xpath, 'cac:Item/cbc:Name', null, $lineNode);

            if (!$lineId || !$lineName) {
                continue;
            }

            $quantityNode = $xpath->query('cbc:InvoicedQuantity', $lineNode)->item(0);
            $quantity = $quantityNode ? (float) $quantityNode->nodeValue : 0;
            $unitCode = ($quantityNode instanceof \DOMElement) ? ($quantityNode->getAttribute('unitCode') ?: 'C62') : 'C62';

            if ($quantity <= 0) {
                continue;
            }

            $unitPrice = (float) self::getXPathValue($xpath, 'cac:Price/cbc:PriceAmount', '0', $lineNode);
            $vatCategory = self::getXPathValue($xpath, 'cac:Item/cac:ClassifiedTaxCategory/cbc:ID', 'S', $lineNode);
            $vatRate = (float) self::getXPathValue($xpath, 'cac:Item/cac:ClassifiedTaxCategory/cbc:Percent', '0', $lineNode);
            $description = self::getXPathValue($xpath, 'cac:Item/cbc:Description', null, $lineNode);

            // Création de la ligne
            $line = null;
            try {
                $line = new InvoiceLine(
                        $lineId, $lineName, $quantity, $unitCode,
                        $unitPrice, $vatCategory, $vatRate, $description
                );
            } catch (\InvalidArgumentException $e) {
                if ($strict) {
                    throw $e;
                }
                // Mode lenient : unitCode non standard → injection via Reflection
                try {
                    $line = new InvoiceLine(
                            $lineId, $lineName, $quantity, 'C62',
                            $unitPrice, $vatCategory, $vatRate, $description
                    );
                    $ref = new \ReflectionProperty(InvoiceLine::class, 'unitCode');
                    $ref->setAccessible(true);
                    $ref->setValue($line, $unitCode);
                    $anomalies[] = sprintf(
                            'Ligne %s : unitCode non standard « %s » chargé tel quel — %s',
                            $lineId, $unitCode, $e->getMessage()
                    );
                } catch (\Exception $e2) {
                    $anomalies[] = sprintf('Ligne %s ignorée : %s', $lineId, $e2->getMessage());
                }
            }

            if ($line === null) {
                continue;
            }

            // BT-132 — Référence de ligne de commande
            $orderLineRef = self::getXPathValue($xpath, 'cac:OrderLineReference/cbc:LineID', null, $lineNode);
            if ($orderLineRef !== null) {
                $line->setOrderLineReference($orderLineRef);
            }

            // BT-127 — Note de ligne
            $lineNote = self::getXPathValue($xpath, 'cbc:Note', null, $lineNode);
            if ($lineNote !== null) {
                $line->setLineNote($lineNote);
            }

            // BG-26 — Période de facturation de ligne (BT-134 + BT-135)
            $linePeriodStart = self::getXPathValue($xpath, 'cac:InvoicePeriod/cbc:StartDate', null, $lineNode);
            $linePeriodEnd = self::getXPathValue($xpath, 'cac:InvoicePeriod/cbc:EndDate', null, $lineNode);
            if ($linePeriodStart !== null || $linePeriodEnd !== null) {
                try {
                    $line->setLinePeriod($linePeriodStart, $linePeriodEnd);
                } catch (\Exception $e) {
                    if ($strict) {
                        throw $e;
                    }
                    $anomalies[] = sprintf(
                            'Ligne %s — BG-26 : période de facturation invalide ignorée — %s',
                            $lineId, $e->getMessage()
                    );
                }
            }

            // BT-155 — Référence article vendeur
            $sellerItemId = self::getXPathValue($xpath, 'cac:Item/cac:SellersItemIdentification/cbc:ID', null, $lineNode);
            if ($sellerItemId !== null) {
                $line->setSellerItemId($sellerItemId);
            }

            // BT-156 — Référence article acheteur
            $buyerItemId = self::getXPathValue($xpath, 'cac:Item/cac:BuyersItemIdentification/cbc:ID', null, $lineNode);
            if ($buyerItemId !== null) {
                $line->setBuyerItemId($buyerItemId);
            }

            // BT-157 — Identifiant standard article (EAN/GTIN)
            $standardItemNodes = $xpath->query('cac:Item/cac:StandardItemIdentification/cbc:ID', $lineNode);
            if ($standardItemNodes && $standardItemNodes->length > 0) {
                $standardItemNode = $standardItemNodes->item(0);
                $standardItemId = trim($standardItemNode->nodeValue);
                $standardSchemeId = ($standardItemNode instanceof \DOMElement) ? ($standardItemNode->getAttribute('schemeID') ?: '0160') : '0160';
                if ($standardItemId !== '') {
                    $line->setStandardItemId($standardItemId, $standardSchemeId);
                }
            }

            // BT-158 — Code de classification article
            $classificationNodes = $xpath->query('cac:Item/cac:CommodityClassification/cbc:ItemClassificationCode', $lineNode);
            if ($classificationNodes && $classificationNodes->length > 0) {
                $classificationNode = $classificationNodes->item(0);
                $classificationCode = trim($classificationNode->nodeValue);
                $listId = ($$classificationNode instanceof \DOMElement) ($classificationNode->getAttribute('listID') ?: 'STI') : 'STI';
                if ($classificationCode !== '') {
                    $line->setItemClassificationCode($classificationCode, $listId);
                }
            }

            // BT-159 — Pays d'origine article
            $originCountry = self::getXPathValue($xpath, 'cac:Item/cac:OriginCountry/cbc:IdentificationCode', null, $lineNode);
            if ($originCountry !== null) {
                $line->setOriginCountryCode($originCountry);
            }

            // BG-28 — Remises et majorations au niveau ligne
            $lineAcs = $xpath->query('cac:AllowanceCharge', $lineNode);
            foreach ($lineAcs as $lacNode) {
                $lacChargeIndicator = strtolower(
                                self::getXPathValue($xpath, 'cbc:ChargeIndicator', 'false', $lacNode)
                        ) === 'true';
                $lacAmount = (float) self::getXPathValue($xpath, 'cbc:Amount', '0', $lacNode);
                $lacBase = self::getXPathValue($xpath, 'cbc:BaseAmount', null, $lacNode);
                $lacPercent = self::getXPathValue($xpath, 'cbc:MultiplierFactorNumeric', null, $lacNode);
                $lacReasonCode = self::getXPathValue($xpath, 'cbc:AllowanceChargeReasonCode', null, $lacNode);
                $lacReason = self::getXPathValue($xpath, 'cbc:AllowanceChargeReason', null, $lacNode);
                $lacVatCat = self::getXPathValue($xpath, 'cac:TaxCategory/cbc:ID', 'S', $lacNode);
                $lacVatRate = (float) self::getXPathValue($xpath, 'cac:TaxCategory/cbc:Percent', '0', $lacNode);

                try {
                    $lac = new AllowanceCharge(
                            $lacChargeIndicator, $lacAmount, $lacVatCat, $lacVatRate,
                            $lacBase !== null ? (float) $lacBase : null,
                            $lacPercent !== null ? (float) $lacPercent : null,
                            $lacReasonCode, $lacReason
                    );
                    $line->addAllowanceCharge($lac);
                } catch (\InvalidArgumentException $e) {
                    if ($strict) {
                        throw $e;
                    }
                    $anomalies[] = sprintf(
                            'Ligne %s — AllowanceCharge ignoré : %s',
                            $lineId, $e->getMessage()
                    );
                }
            }

            $invoice->addInvoiceLine($line);
        }
    }

    // =========================================================================
    // Totaux déclarés (mode lenient uniquement)
    // =========================================================================

    /**
     * Lit et stocke les totaux déclarés dans LegalMonetaryTotal du XML source
     *
     * Ces valeurs sont ensuite comparées aux totaux recalculés depuis les lignes
     * par InvoiceBase::checkImportedTotals().
     *
     * Appelé uniquement en mode lenient, avant calculateTotals().
     * Synchronise également le prépaiement (BT-113) via setPrepaidAmount().
     *
     * @param InvoiceBase $invoice
     * @param \DOMXPath   $xpath
     */
    private static function loadDeclaredTotals(InvoiceBase $invoice, \DOMXPath $xpath): void {
        $lineExtension = (float) self::getXPathValue($xpath, '//cac:LegalMonetaryTotal/cbc:LineExtensionAmount', '0');
        $taxExclusive = (float) self::getXPathValue($xpath, '//cac:LegalMonetaryTotal/cbc:TaxExclusiveAmount', '0');
        $taxInclusive = (float) self::getXPathValue($xpath, '//cac:LegalMonetaryTotal/cbc:TaxInclusiveAmount', '0');
        $prepaid = (float) self::getXPathValue($xpath, '//cac:LegalMonetaryTotal/cbc:PrepaidAmount', '0');
        $payable = (float) self::getXPathValue($xpath, '//cac:LegalMonetaryTotal/cbc:PayableAmount', '0');
        $taxAmount = (float) self::getXPathValue($xpath, '//cac:TaxTotal/cbc:TaxAmount', '0');

        $invoice->setImportedTotals($lineExtension, $taxExclusive, $taxInclusive, $prepaid, $payable, $taxAmount);
        // Synchronisation du prépaiement pour le calcul correct de payableAmount
        $invoice->setPrepaidAmount($prepaid);
    }

    // =========================================================================
    // Données spécifiques UBL.BE
    // =========================================================================

    /**
     * Charge les données spécifiques à la norme UBL.BE
     *
     * Éléments chargés :
     *   - BuyerReference (BT-10) : déjà chargé dans loadBasicData, synchronisé ici si besoin
     *   - PaymentTerms (BT-20) : lus depuis cac:PaymentTerms/cbc:Note dans loadPaymentInfo
     *   - VatExemptionReason (BT-121) : premier code d'exonération trouvé dans un TaxSubtotal
     *
     * @param UblBeInvoice $invoice
     * @param \DOMXPath    $xpath
     */
    private static function loadUblBeSpecificData(UblBeInvoice $invoice, \DOMXPath $xpath): void {
        // BT-121 — Raison d'exonération TVA
        $exemptionReason = self::getXPathValue(
                $xpath,
                '//cac:TaxSubtotal/cac:TaxCategory/cbc:TaxExemptionReasonCode'
        );
        if ($exemptionReason) {
            try {
                $invoice->setVatExemptionReason($exemptionReason);
            } catch (\Exception $e) {
                // Code non reconnu — on ignore
            }
        }
    }

    // =========================================================================
    // Helper XPath
    // =========================================================================

    /**
     * Extrait la valeur textuelle du premier nœud correspondant à une requête XPath
     *
     * Retourne la valeur après trim, ou $default si le nœud n'existe pas
     * ou si la valeur est vide après trim.
     *
     * @param \DOMXPath       $xpath       Contexte XPath
     * @param string          $query       Expression XPath
     * @param string|null     $default     Valeur par défaut (null si non spécifié)
     * @param \DOMNode|null   $contextNode Nœud de contexte pour les requêtes relatives
     * @return string|null
     */
    private static function getXPathValue(
            \DOMXPath $xpath,
            string $query,
            ?string $default = null,
            ?\DOMNode $contextNode = null
    ): ?string {
        $nodes = $contextNode ? $xpath->query($query, $contextNode) : $xpath->query($query);

        if ($nodes && $nodes->length > 0) {
            $value = trim($nodes->item(0)->nodeValue);
            return $value !== '' ? $value : $default;
        }

        return $default;
    }
}
