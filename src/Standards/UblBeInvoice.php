<?php

declare(strict_types=1);

namespace Peppol\Standards;

use Peppol\Core\InvoiceConstants;

/**
 * Implémentation d'une facture conforme à la norme UBL.BE 1.0
 *
 * Cette classe étend EN16931Invoice avec les règles spécifiques belges
 * pour la facturation électronique.
 *
 * Règles UBL.BE supplémentaires par rapport à EN 16931 :
 *   - BT-10 (buyerReference) OU BT-13 (purchaseOrderReference) obligatoire
 *   - Adresse électronique vendeur obligatoire (BT-34)
 *   - Adresse électronique acheteur obligatoire (BT-49)
 *   - BR-CO-25 : date d'échéance OU conditions de paiement obligatoires si montant > 0
 *   - UBL-BE-01 : au moins 2 documents joints requis
 *   - Taux de TVA belges uniquement : 21%, 12%, 6%, 0%
 *   - Validation du numéro de TVA belge (modulo 97) pour les vendeurs BE
 *
 * Note : buyerReference (BT-10) et paymentTerms (BT-20) sont définis
 * dans InvoiceBase et hérités ici — ils ne sont pas redéclarés.
 *
 * @package Peppol\Standards
 * @version 1.1
 * @link https://www.nbb.be/fr/paiements-et-titres/standards-techniques/belgische-uitwisselingsnorm-voor-elektronische
 */
class UblBeInvoice extends EN16931Invoice
{
    /**
     * @var string|null Raison d'exonération de TVA (BT-121)
     *                  Obligatoire si catégorie TVA = E, AE, K, G ou O
     *
     * @see InvoiceConstants::VAT_EXEMPTION_REASONS
     */
    protected ?string $vatExemptionReason = null;

    // buyerReference  (BT-10) → hérité de InvoiceBase
    // paymentTerms    (BT-20) → hérité de InvoiceBase

    // =========================================================================
    // Setter paymentTerms — surcharge pour propagation dans PaymentInfo
    // =========================================================================

    /**
     * Définit les conditions de paiement
     *
     * BT-20 — Obligatoire si date d'échéance non fournie et montant > 0 (BR-CO-25)
     * Met également à jour PaymentInfo si elle existe.
     *
     * @param string $paymentTerms Texte libre décrivant les conditions
     * @return self
     */
    public function setPaymentTerms(string $paymentTerms): self
    {
        $this->paymentTerms = $paymentTerms;

        // Propagation dans PaymentInfo si elle existe déjà
        if ($this->paymentInfo !== null) {
            $this->paymentInfo->setPaymentTerms($paymentTerms);
        }

        return $this;
    }

    // =========================================================================
    // Raison d'exonération TVA (BT-121)
    // =========================================================================

    /**
     * Définit la raison d'exonération de TVA
     *
     * Obligatoire si une catégorie de TVA requérant une exonération est présente :
     * E (exonéré), AE (autoliquidation), K (intracommunautaire), G (exportation), O (hors champ)
     *
     * Applique automatiquement la raison à toutes les ventilations TVA qui en ont besoin.
     *
     * @param string $exemptionReason Code selon InvoiceConstants::VAT_EXEMPTION_REASONS
     * @return self
     * @throws \InvalidArgumentException Si le code n'est pas dans la liste des codes reconnus
     *
     * @see InvoiceConstants::VAT_EXEMPTION_REASONS
     */
    public function setVatExemptionReason(string $exemptionReason): self
    {
        if (!$this->validateKeyExists($exemptionReason, InvoiceConstants::VAT_EXEMPTION_REASONS)) {
            throw new \InvalidArgumentException(
                "Code raison d'exonération invalide. Codes valides: " .
                implode(', ', array_keys(InvoiceConstants::VAT_EXEMPTION_REASONS))
            );
        }

        $this->vatExemptionReason = $exemptionReason;

        // Propagation vers les ventilations TVA qui requiresExemptionReason()
        foreach ($this->vatBreakdown as $vat) {
            if ($vat->requiresExemptionReason()) {
                $vat->setExemptionReason($exemptionReason);
            }
        }

        return $this;
    }

    // =========================================================================
    // Surcharges setSellerFromData / setBuyerFromData
    // =========================================================================

    /**
     * Définit le fournisseur avec validation spécifique UBL.BE
     *
     * Surcharge setSellerFromData() de EN16931Invoice pour imposer :
     *   - L'adresse électronique vendeur (BT-34) : obligatoire pour UBL.BE
     *   - La validation du numéro de TVA belge (modulo 97) si pays = BE
     *
     * @param string      $name                   Nom ou raison sociale
     * @param string      $vatId                  Numéro de TVA (belge obligatoire si pays = BE)
     * @param string      $streetName             Rue
     * @param string      $postalZone             Code postal
     * @param string      $cityName               Ville
     * @param string      $countryCode            Code pays ISO 3166-1 alpha-2
     * @param string|null $companyId              Numéro d'entreprise
     * @param string|null $email                  Email de contact
     * @param string|null $electronicAddressScheme Schéma d'adresse électronique ISO 6523
     * @param string|null $electronicAddress       Identifiant électronique (obligatoire pour UBL.BE)
     * @return self
     * @throws \InvalidArgumentException Si l'adresse électronique est manquante
     *                                   ou si le numéro de TVA belge est invalide
     */
    public function setSellerFromData(
        string $name,
        string $vatId,
        string $streetName,
        string $postalZone,
        string $cityName,
        string $countryCode,
        ?string $companyId = null,
        ?string $email = null,
        ?string $electronicAddressScheme = null,
        ?string $electronicAddress = null
    ): self {
        // UBL.BE : adresse électronique vendeur obligatoire
        if (empty($electronicAddress)) {
            throw new \InvalidArgumentException(
                'Adresse électronique vendeur obligatoire pour UBL.BE'
            );
        }

        // UBL.BE : validation spécifique TVA belge si pays = BE
        if ($countryCode === 'BE' && !$this->validateBelgianVat($vatId)) {
            throw new \InvalidArgumentException(
                'Numéro de TVA belge invalide (format: BE0123456789 avec modulo 97)'
            );
        }

        return parent::setSellerFromData(
            $name, $vatId, $streetName, $postalZone, $cityName, $countryCode,
            $companyId, $email, $electronicAddressScheme, $electronicAddress
        );
    }

    /**
     * Définit le client avec validation spécifique UBL.BE
     *
     * Surcharge setBuyerFromData() de EN16931Invoice pour imposer :
     *   - L'adresse électronique acheteur (BT-49) : obligatoire pour UBL.BE
     *
     * @param string      $name                   Nom ou raison sociale
     * @param string      $streetName             Rue
     * @param string      $postalZone             Code postal
     * @param string      $cityName               Ville
     * @param string      $countryCode            Code pays ISO 3166-1 alpha-2
     * @param string|null $vatId                  Numéro de TVA
     * @param string|null $email                  Email de contact
     * @param string|null $electronicAddressScheme Schéma d'adresse électronique ISO 6523
     * @param string|null $electronicAddress       Identifiant électronique (obligatoire pour UBL.BE)
     * @return self
     * @throws \InvalidArgumentException Si l'adresse électronique acheteur est manquante
     */
    public function setBuyerFromData(
        string $name,
        string $streetName,
        string $postalZone,
        string $cityName,
        string $countryCode,
        ?string $vatId = null,
        ?string $email = null,
        ?string $electronicAddressScheme = null,
        ?string $electronicAddress = null
    ): self {
        // UBL.BE : adresse électronique acheteur obligatoire
        if (empty($electronicAddress)) {
            throw new \InvalidArgumentException(
                'Adresse électronique acheteur obligatoire pour UBL.BE'
            );
        }

        return parent::setBuyerFromData(
            $name, $streetName, $postalZone, $cityName, $countryCode,
            $vatId, $email, $electronicAddressScheme, $electronicAddress
        );
    }

    // =========================================================================
    // Validation UBL.BE
    // =========================================================================

    /**
     * Validation spécifique UBL.BE
     *
     * Étend la validation EN 16931 avec les règles supplémentaires belges :
     *
     *   - BR-CO-25 : si montant à payer > 0, date d'échéance OU conditions de paiement requis
     *   - BT-10/BT-13 : référence acheteur OU référence commande obligatoire
     *   - BT-34 : adresse électronique vendeur obligatoire
     *   - BT-49 : adresse électronique acheteur obligatoire
     *   - UBL-BE-01 : au moins 2 documents joints requis
     *   - Taux de TVA belges (21%, 12%, 6%, 0%) pour les vendeurs belges
     *   - Raison d'exonération TVA si catégorie requérant une exonération
     *
     * @return array<string> Liste des erreurs (vide si valide)
     */
    public function validate(): array
    {
        $errors = parent::validate();

        // BR-CO-25 : date d'échéance OU conditions de paiement obligatoires si montant > 0
        if ($this->payableAmount > 0 && empty($this->dueDate) && empty($this->paymentTerms)) {
            $errors[] = "BR-CO-25: Date d'échéance ou conditions de paiement obligatoires si montant > 0";
        }

        // BT-10/BT-13 : référence acheteur OU référence commande
        if (empty($this->buyerReference) && empty($this->purchaseOrderReference)) {
            $errors[] = 'UBL-BE: Référence acheteur (BT-10) ou référence commande (BT-13) obligatoire';
        }

        // BT-34 : adresse électronique vendeur
        if (isset($this->seller) && $this->seller->getElectronicAddress() === null) {
            $errors[] = 'UBL-BE: Adresse électronique vendeur obligatoire';
        }

        // BT-49 : adresse électronique acheteur
        if (isset($this->buyer) && $this->buyer->getElectronicAddress() === null) {
            $errors[] = 'UBL-BE: Adresse électronique acheteur obligatoire';
        }

        // UBL-BE-01 : au moins 2 documents joints
        if (count($this->attachedDocuments) < 2) {
            $errors[] = 'UBL-BE-01: Au moins 2 documents joints requis';
        }

        // Validation des taux de TVA belges pour les vendeurs belges
        if (isset($this->seller) && $this->seller->getAddress()->getCountryCode() === 'BE') {
            foreach ($this->invoiceLines as $index => $line) {
                if ($line->getVatCategory() === 'S' &&
                    !in_array($line->getVatRate(), InvoiceConstants::BE_VAT_RATES)) {
                    $errors[] = 'Ligne ' . ($index + 1) .
                                ': Taux de TVA belge invalide. Taux valides: ' .
                                implode('%, ', InvoiceConstants::BE_VAT_RATES) . '%';
                }
            }
        }

        // Raison d'exonération TVA obligatoire pour les catégories concernées
        foreach ($this->vatBreakdown as $vat) {
            if ($vat->requiresExemptionReason() && empty($this->vatExemptionReason)) {
                $errors[] = "Raison d'exonération TVA obligatoire pour catégorie " . $vat->getCategory();
            }
        }

        return $errors;
    }

    // =========================================================================
    // Getters / Sérialisation
    // =========================================================================

    /**
     * Retourne l'identifiant de customisation pour UBL.BE
     *
     * @return string
     */
    public function getCustomizationId(): string
    {
        return InvoiceConstants::CUSTOMIZATION_UBL_BE;
    }

    /**
     * Retourne la raison d'exonération de TVA (BT-121), ou null si non définie
     *
     * @return string|null
     */
    public function getVatExemptionReason(): ?string
    {
        return $this->vatExemptionReason;
    }

    /**
     * Retourne la facture sous forme de tableau, avec les champs UBL.BE supplémentaires
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = parent::toArray();

        // Champs spécifiques UBL.BE (buyerReference et paymentTerms
        // sont déjà dans InvoiceBase::toArray())
        $data['vatExemptionReason'] = $this->vatExemptionReason;

        return $data;
    }
}