<?php

declare(strict_types=1);

namespace Peppol\Models;

use Peppol\Core\InvoiceConstants;
use Peppol\Core\InvoiceValidatorTrait;

/**
 * Modèle d'informations de paiement
 * 
 * Représente les informations de paiement conforme à la norme EN 16931 (BG-16, BG-17)
 * 
 * @package Peppol\Models
 * @author Votre Nom
 * @version 1.0
 */
class PaymentInfo {

    use InvoiceValidatorTrait;

    /**
     * @var string Code moyen de paiement UNCL4461 (BT-81)
     */
    private string $paymentMeansCode;

    /**
     * @var string|null IBAN du compte bancaire (BT-84)
     */
    private ?string $iban;

    /**
     * @var string|null Code BIC/SWIFT (BT-86)
     */
    private ?string $bic;

    /**
     * @var string|null Référence de paiement (BT-82)
     */
    private ?string $paymentReference;

    /**
     * @var string|null Conditions de paiement (BT-20)
     */
    private ?string $paymentTerms;

    /**
     * Constructeur
     * 
     * @param string $paymentMeansCode Code moyen de paiement (défaut: 30 = virement)
     * @param string|null $iban IBAN
     * @param string|null $bic BIC/SWIFT
     * @param string|null $paymentReference Référence de paiement
     * @param string|null $paymentTerms Conditions de paiement
     * @throws \InvalidArgumentException
     */
    public function __construct(
            string $paymentMeansCode = '30',
            ?string $iban = null,
            ?string $bic = null,
            ?string $paymentReference = null,
            ?string $paymentTerms = null
    ) {
        $this->setPaymentMeansCode($paymentMeansCode);
        $this->setIban($iban);
        $this->setBic($bic);
        $this->setPaymentReference($paymentReference);
        $this->paymentTerms = $paymentTerms;
    }

    /**
     * Constructeur alternatif pour le mode lenient : charge le BIC tel quel
     * sans validation. Utilisé par XmlImporter quand le BIC est malformé.
     */
    public static function withRawBic(
            string $paymentMeansCode = '30',
            ?string $iban = null,
            ?string $rawBic = null,
            ?string $paymentReference = null,
            ?string $paymentTerms = null
    ): self {
        $instance = new self($paymentMeansCode, $iban, null, $paymentReference, $paymentTerms);
        // Injecte le BIC brut en contournant le setter validant
        $instance->bic = $rawBic;
        return $instance;
    }

    private function setPaymentMeansCode(string $paymentMeansCode): void {
        if (!$this->validateKeyExists($paymentMeansCode, InvoiceConstants::PAYMENT_MEANS_CODES)) {
            throw new \InvalidArgumentException(
                            'Code moyen de paiement invalide. Codes valides: ' .
                            implode(', ', array_keys(InvoiceConstants::PAYMENT_MEANS_CODES))
                    );
        }
        $this->paymentMeansCode = $paymentMeansCode;
    }

    private function setIban(?string $iban): void {
        if ($iban !== null) {
            $ibanClean = str_replace(' ', '', strtoupper($iban));
            if (!$this->validateIban($ibanClean)) {
                throw new \InvalidArgumentException('Format IBAN invalide');
            }
            $this->iban = $ibanClean;
        } else {
            $this->iban = null;
        }
    }

    private function setBic(?string $bic): void {
        if ($bic !== null && !$this->validateBic($bic)) {
            throw new \InvalidArgumentException('Format BIC invalide');
        }
        $this->bic = $bic;
    }

    private function setPaymentReference(?string $paymentReference): void {
        $this->paymentReference = $paymentReference;
    }

    /**
     * Définit une référence structurée belge
     * Valide le format et le modulo 97
     * 
     * @param string $reference Référence au format +++123/4567/89012+++
     * @throws \InvalidArgumentException
     */
    public function setBelgianStructuredReference(string $reference): void {
        $refClean = str_replace(['+', '/', ' '], '', $reference);

        if (!preg_match('/^[0-9]{12}$/', $refClean)) {
            throw new \InvalidArgumentException(
                            'Format de référence structurée invalide. Format attendu: +++123/4567/89012+++'
                    );
        }

        if (!$this->validateBelgianStructuredReference($refClean)) {
            throw new \InvalidArgumentException(
                            'La référence structurée belge n\'est pas valide (erreur de modulo 97)'
                    );
        }

        $this->paymentReference = $reference;
    }

    /**
     * Retourne la référence de paiement formatée (pour affichage belge)
     * 
     * @return string|null
     */
    public function getFormattedBelgianReference(): ?string {
        if ($this->paymentReference === null) {
            return null;
        }

        $ref = str_replace(['+', '/', ' '], '', $this->paymentReference);

        if (strlen($ref) !== 12) {
            return $this->paymentReference; // Retourne tel quel si pas format belge
        }

        return sprintf(
                '+++%s/%s/%s+++',
                substr($ref, 0, 3),
                substr($ref, 3, 4),
                substr($ref, 7, 5)
        );
    }

    /**
     * Valide les informations de paiement
     * 
     * @return array<string> Liste des erreurs (vide si valide)
     */
    public function validate(): array {
        $errors = [];

        if (!$this->validateKeyExists($this->paymentMeansCode, InvoiceConstants::PAYMENT_MEANS_CODES)) {
            $errors[] = 'Code moyen de paiement invalide';
        }

        // Si virement bancaire, IBAN recommandé
        if (in_array($this->paymentMeansCode, ['30', '31', '58']) && $this->iban === null) {
            $errors[] = 'IBAN recommandé pour un virement bancaire';
        }

        if ($this->iban !== null && !$this->validateIban($this->iban)) {
            $errors[] = 'Format IBAN invalide';
        }

        if ($this->bic !== null && !$this->validateBic($this->bic)) {
            $errors[] = 'Format BIC invalide';
        }

        return $errors;
    }

    /**
     * Retourne les informations de paiement sous forme de tableau
     * 
     * @return array<string, mixed>
     */
    public function toArray(): array {
        return [
            'paymentMeansCode' => $this->paymentMeansCode,
            'paymentMeansName' => InvoiceConstants::PAYMENT_MEANS_CODES[$this->paymentMeansCode] ?? '',
            'iban' => $this->iban,
            'bic' => $this->bic,
            'paymentReference' => $this->paymentReference,
            'paymentTerms' => $this->paymentTerms
        ];
    }

    // Getters
    public function getPaymentMeansCode(): string {
        return $this->paymentMeansCode;
    }

    public function getIban(): ?string {
        return $this->iban;
    }

    public function getBic(): ?string {
        return $this->bic;
    }

    public function getPaymentReference(): ?string {
        return $this->paymentReference;
    }

    public function getPaymentTerms(): ?string {
        return $this->paymentTerms;
    }

    // Setters
    public function setPaymentTerms(?string $paymentTerms): void {
        $this->paymentTerms = $paymentTerms;
    }
}
