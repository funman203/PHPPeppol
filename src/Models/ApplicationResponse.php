<?php

declare(strict_types=1);

namespace Peppol\Models;


/**
 * Modèle ApplicationResponse UBL 2.1
 *
 * Représente une réponse à une facture Peppol — soit un MLR (Message Level Response,
 * émis par l'Access Point), soit un IMR (Invoice Message Response, émis par l'acheteur).
 *
 * Structure UBL :
 *   ApplicationResponse
 *     ├── cbc:CustomizationID
 *     ├── cbc:ProfileID
 *     ├── cbc:ID
 *     ├── cbc:IssueDate
 *     ├── cbc:IssueTime
 *     ├── cac:SenderParty
 *     ├── cac:ReceiverParty
 *     └── cac:DocumentResponse (1..n)
 *           ├── cac:Response / cbc:ResponseCode
 *           ├── cac:Response / cbc:Description
 *           ├── cac:DocumentReference / cbc:ID
 *           └── cac:LineResponse (0..n)
 *
 * @package Peppol\Models
 */
class ApplicationResponse
{
    // =========================================================================
    // CustomizationID connus
    // =========================================================================

    const CUSTOMIZATION_MLR = 'urn:fdc:peppol.eu:poacc:trns:mlr:3';
    const CUSTOMIZATION_IMR = 'urn:fdc:peppol.eu:poacc:trns:invoice_message_response:3';
    const CUSTOMIZATION_INVOICE_RESPONSE = 'urn:fdc:peppol.eu:poacc:trns:invoice_response:3';
    const PROFILE_INVOICE_RESPONSE = 'urn:fdc:peppol.eu:poacc:bis:invoice_response:3';
    const PROFILE_MLR = 'urn:fdc:peppol.eu:poacc:bis:mlr:3';
    const PROFILE_IMR = 'urn:fdc:peppol.eu:poacc:bis:invoice_message_response:3';

    // =========================================================================
    // Propriétés
    // =========================================================================

    private string $id;
    private string $issueDate;
    private ?string $issueTime = null;
    private string $customizationId;
    private string $profileId;

    /** @var array{name: string, endpointId: string|null, endpointScheme: string|null} */
    private array $senderParty = ['name' => '', 'endpointId' => null, 'endpointScheme' => null];

    /** @var array{name: string, endpointId: string|null, endpointScheme: string|null} */
    private array $receiverParty = ['name' => '', 'endpointId' => null, 'endpointScheme' => null];

    /** @var array<DocumentResponse> */
    private array $documentResponses = [];

    // =========================================================================
    // Constructeur
    // =========================================================================

    public function __construct(
        string $id,
        string $issueDate,
        string $customizationId = self::CUSTOMIZATION_IMR,
        string $profileId = self::PROFILE_IMR
    ) {
        if (empty(trim($id))) {
            throw new \InvalidArgumentException('L\'ID de l\'ApplicationResponse ne peut pas être vide');
        }
        if (!\DateTime::createFromFormat('Y-m-d', $issueDate)) {
            throw new \InvalidArgumentException('Format de date invalide (YYYY-MM-DD attendu)');
        }
        $this->id = $id;
        $this->issueDate = $issueDate;
        $this->customizationId = $customizationId;
        $this->profileId = $profileId;
    }

    // =========================================================================
    // Factories
    // =========================================================================

    /**
     * Crée une IMR en réponse à une facture reçue
     *
     * @param InvoiceBase $invoice    Facture à laquelle on répond
     * @param string      $responseCode AP, RE, AB, IP ou UQ
     * @param string|null $description  Raison optionnelle
     */
    public static function createImrForInvoice(
        InvoiceBase $invoice,
        string $responseCode,
        ?string $description = null
    ): static {
        $ar = new static(
            'AR-' . $invoice->getInvoiceNumber(),
            date('Y-m-d'),
            self::CUSTOMIZATION_IMR,
            self::PROFILE_IMR
        );
        $ar->setIssueTime(date('H:i:s'));

        $docResponse = new DocumentResponse($responseCode, $invoice->getInvoiceNumber(), $description);
        $docResponse->setReferenceTypeCode($invoice->getInvoiceTypeCode());
        $ar->addDocumentResponse($docResponse);

        return $ar;
    }

    // =========================================================================
    // Setters
    // =========================================================================

    public function setIssueTime(?string $time): static
    {
        $this->issueTime = $time;
        return $this;
    }

    public function setSenderParty(
        string $name,
        ?string $endpointId = null,
        ?string $endpointScheme = null
    ): static {
        $this->senderParty = compact('name', 'endpointId', 'endpointScheme');
        return $this;
    }

    public function setReceiverParty(
        string $name,
        ?string $endpointId = null,
        ?string $endpointScheme = null
    ): static {
        $this->receiverParty = compact('name', 'endpointId', 'endpointScheme');
        return $this;
    }

    public function addDocumentResponse(DocumentResponse $response): static
    {
        $this->documentResponses[] = $response;
        return $this;
    }

    // =========================================================================
    // Getters
    // =========================================================================

    public function getId(): string
    {
        return $this->id;
    }
    public function getIssueDate(): string
    {
        return $this->issueDate;
    }
    public function getIssueTime(): ?string
    {
        return $this->issueTime;
    }
    public function getCustomizationId(): string
    {
        return $this->customizationId;
    }
    public function getProfileId(): string
    {
        return $this->profileId;
    }

    /** @return array{name: string, endpointId: string|null, endpointScheme: string|null} */
    public function getSenderParty(): array
    {
        return $this->senderParty;
    }

    /** @return array{name: string, endpointId: string|null, endpointScheme: string|null} */
    public function getReceiverParty(): array
    {
        return $this->receiverParty;
    }

    /** @return array<DocumentResponse> */
    public function getDocumentResponses(): array
    {
        return $this->documentResponses;
    }

    /**
     * Retourne le premier DocumentResponse (cas le plus fréquent : une seule facture)
     */
    public function getFirstDocumentResponse(): ?DocumentResponse
    {
        return $this->documentResponses[0] ?? null;
    }

    // =========================================================================
    // Helpers de statut (délèguent au premier DocumentResponse)
    // =========================================================================

    public function isAccepted(): bool
    {
        return $this->getFirstDocumentResponse()?->isAccepted() ?? false;
    }

    public function isRejected(): bool
    {
        return $this->getFirstDocumentResponse()?->isRejected() ?? false;
    }

    public function isPending(): bool
    {
        return $this->getFirstDocumentResponse()?->isPending() ?? false;
    }

    public function getStatusLabel(): string
    {
        return $this->getFirstDocumentResponse()?->getStatusLabel() ?? 'Inconnu';
    }

    /**
     * Toutes les raisons de rejet de tous les DocumentResponse
     *
     * @return array<string>
     */
    public function getRejectionReasons(): array
    {
        $reasons = [];
        foreach ($this->documentResponses as $dr) {
            array_push($reasons, ...$dr->getRejectionReasons());
        }
        return $reasons;
    }

    // =========================================================================
    // Validation de cohérence avec la facture originale
    // =========================================================================

    /**
     * Vérifie que cette AR correspond bien à la facture fournie
     *
     * Contrôles effectués :
     *   1. L'ID de facture référencé dans le DocumentResponse correspond à $invoice->getInvoiceNumber()
     *   2. Si les parties sont renseignées dans l'AR, le VAT ID du receiver correspond au vendeur
     *      et le VAT ID du sender correspond à l'acheteur (logique IMR : l'acheteur répond)
     *
     * @return array<string> Liste des incohérences détectées (vide = cohérent)
     */
    public function matchesInvoice(
        string $invoiceNumber,
        ?string $sellerVatId = null,
        ?string $buyerVatId = null
    ): array {
        $issues = [];

        // Vérification ID facture
        foreach ($this->documentResponses as $dr) {
            if ($dr->getReferenceId() !== $invoiceNumber) {
                $issues[] = sprintf(
                    'DocumentResponse référence « %s » mais la facture attendue est « %s »',
                    $dr->getReferenceId(),
                    $invoiceNumber
                );
            }
        }

        // Vérification vendeur — le receiver de l'AR doit être le vendeur
        // (c'est l'acheteur qui envoie l'AR, donc le vendeur est le destinataire)
        if ($sellerVatId !== null) {
            $receiverEndpoint = $this->receiverParty['endpointId'];
            if ($receiverEndpoint !== null) {
                $receiverClean = preg_replace('/[^0-9]/', '', $receiverEndpoint);
                $sellerClean = preg_replace('/[^0-9]/', '', $sellerVatId);
                if (
                    $sellerClean !== '' && $receiverClean !== ''
                    && !str_contains($receiverClean, $sellerClean)
                    && !str_contains($sellerClean, $receiverClean)
                ) {
                    $issues[] = sprintf(
                        'ReceiverParty (« %s ») ne correspond pas au vendeur attendu (« %s »)',
                        $receiverEndpoint,
                        $sellerVatId
                    );
                }
            }
        }

        // Vérification acheteur — le sender de l'AR doit être l'acheteur
        if ($buyerVatId !== null) {
            $senderEndpoint = $this->senderParty['endpointId'];
            if ($senderEndpoint !== null) {
                $senderClean = preg_replace('/[^0-9]/', '', $senderEndpoint);
                $buyerClean = preg_replace('/[^0-9]/', '', $buyerVatId);
                if (
                    $buyerClean !== '' && $senderClean !== ''
                    && !str_contains($senderClean, $buyerClean)
                    && !str_contains($buyerClean, $senderClean)
                ) {
                    $issues[] = sprintf(
                        'SenderParty (« %s ») ne correspond pas à l\'acheteur attendu (« %s »)',
                        $senderEndpoint,
                        $buyerVatId
                    );
                }
            }
        }

        return $issues;
    }



    // =========================================================================
    // Type
    // =========================================================================

    public function isMlr(): bool
    {
        return str_contains($this->customizationId, 'mlr');
    }

    public function isImr(): bool
    {
        return str_contains($this->customizationId, 'invoice_message_response')
            || str_contains($this->customizationId, 'invoice_response');
    }
}
