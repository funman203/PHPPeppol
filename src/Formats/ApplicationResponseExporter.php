<?php

declare(strict_types=1);

namespace Peppol\Formats;

use Peppol\Models\ApplicationResponse;
use Peppol\Models\DocumentResponse;

/**
 * Exportateur XML ApplicationResponse UBL 2.1
 *
 * Génère un document XML ApplicationResponse conforme Peppol
 * (MLR ou IMR selon le CustomizationID de l'objet fourni).
 *
 * @package Peppol\Formats
 */
class ApplicationResponseExporter
{
    private const NS_AR  = 'urn:oasis:names:specification:ubl:schema:xsd:ApplicationResponse-2';
    private const NS_CAC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';
    private const NS_CBC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';

    // =========================================================================
    // Point d'entrée
    // =========================================================================

    public function toXml(ApplicationResponse $ar): string
    {
        $this->validate($ar);

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $root = $dom->createElementNS(self::NS_AR, 'ApplicationResponse');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cac', self::NS_CAC);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cbc', self::NS_CBC);
        $dom->appendChild($root);

        // En-tête
        $this->addCbc($dom, $root, 'UBLVersionID', '2.1');
        $this->addCbc($dom, $root, 'CustomizationID', $ar->getCustomizationId());
        $this->addCbc($dom, $root, 'ProfileID', $ar->getProfileId());
        $this->addCbc($dom, $root, 'ID', $ar->getId());
        $this->addCbc($dom, $root, 'IssueDate', $ar->getIssueDate());

        if ($ar->getIssueTime()) {
            $this->addCbc($dom, $root, 'IssueTime', $ar->getIssueTime());
        }

        // Parties
        $this->addParty($dom, $root, 'SenderParty', $ar->getSenderParty());
        $this->addParty($dom, $root, 'ReceiverParty', $ar->getReceiverParty());

        // DocumentResponse(s)
        foreach ($ar->getDocumentResponses() as $dr) {
            $this->addDocumentResponse($dom, $root, $dr);
        }

        $xml = $dom->saveXML();
        if ($xml === false) {
            throw new \RuntimeException('Échec de la sérialisation XML');
        }
        return $xml;
    }

    public function saveToFile(ApplicationResponse $ar, string $filepath): bool
    {
        try {
            return file_put_contents($filepath, $this->toXml($ar)) !== false;
        } catch (\Exception $e) {
            return false;
        }
    }

    // =========================================================================
    // Validation minimale
    // =========================================================================

    private function validate(ApplicationResponse $ar): void
    {
        $errors = [];

        if (empty($ar->getDocumentResponses())) {
            $errors[] = 'Au moins un DocumentResponse est requis';
        }

        foreach ($ar->getDocumentResponses() as $i => $dr) {
            if (empty($dr->getReferenceId())) {
                $errors[] = "DocumentResponse[$i] : referenceId manquant";
            }
        }

        if (!empty($errors)) {
            throw new \InvalidArgumentException(
                'ApplicationResponse invalide : ' . implode(', ', $errors)
            );
        }
    }

    // =========================================================================
    // Builders DOM
    // =========================================================================

    private function addDocumentResponse(
        \DOMDocument $dom,
        \DOMElement $parent,
        DocumentResponse $dr
    ): void {
        $drElem = $dom->createElementNS(self::NS_CAC, 'cac:DocumentResponse');

        // cac:Response
        $response = $dom->createElementNS(self::NS_CAC, 'cac:Response');
        $this->addCbc($dom, $response, 'ResponseCode', $dr->getResponseCode());
        if ($dr->getDescription()) {
            $this->addCbc($dom, $response, 'Description', $dr->getDescription());
        }
        $drElem->appendChild($response);

        // cac:DocumentReference
        $docRef = $dom->createElementNS(self::NS_CAC, 'cac:DocumentReference');
        $this->addCbc($dom, $docRef, 'ID', $dr->getReferenceId());
        if ($dr->getReferenceUuid()) {
            $this->addCbc($dom, $docRef, 'UUID', $dr->getReferenceUuid());
        }
        if ($dr->getReferenceTypeCode()) {
            $this->addCbc($dom, $docRef, 'DocumentTypeCode', $dr->getReferenceTypeCode());
        }
        $drElem->appendChild($docRef);

        // cac:LineResponse(s)
        foreach ($dr->getLineResponses() as $lr) {
            $lineResp = $dom->createElementNS(self::NS_CAC, 'cac:LineResponse');
            $lineResponse = $dom->createElementNS(self::NS_CAC, 'cac:Response');
            if ($lr['code']) {
                $this->addCbc($dom, $lineResponse, 'ResponseCode', $lr['code']);
            }
            $this->addCbc($dom, $lineResponse, 'Description', $lr['description']);
            $lineResp->appendChild($lineResponse);

            // cac:LineReference obligatoire en UBL même si vide
            $lineRef = $dom->createElementNS(self::NS_CAC, 'cac:LineReference');
            $this->addCbc($dom, $lineRef, 'LineID', 'N/A');
            $lineResp->appendChild($lineRef);

            $drElem->appendChild($lineResp);
        }

        $parent->appendChild($drElem);
    }

    private function addParty(
        \DOMDocument $dom,
        \DOMElement $parent,
        string $elementName,
        array $partyData
    ): void {
        if (empty($partyData['name']) && empty($partyData['endpointId'])) {
            return;
        }

        $partyElem = $dom->createElementNS(self::NS_CAC, "cac:{$elementName}");

        if (!empty($partyData['endpointId'])) {
            $endpoint = $dom->createElementNS(
                self::NS_CBC,
                'cbc:EndpointID',
                htmlspecialchars($partyData['endpointId'], ENT_XML1, 'UTF-8')
            );
            $endpoint->setAttribute('schemeID', $partyData['endpointScheme'] ?? '0208');
            $partyElem->appendChild($endpoint);
        }

        if (!empty($partyData['name'])) {
            $partyName = $dom->createElementNS(self::NS_CAC, 'cac:PartyName');
            $this->addCbc($dom, $partyName, 'Name', $partyData['name']);
            $partyElem->appendChild($partyName);
        }

        $parent->appendChild($partyElem);
    }

    private function addCbc(\DOMDocument $dom, \DOMElement $parent, string $name, string $value): void
    {
        $element = $dom->createElementNS(
            self::NS_CBC,
            "cbc:{$name}",
            htmlspecialchars($value, ENT_XML1, 'UTF-8')
        );
        $parent->appendChild($element);
    }
}
