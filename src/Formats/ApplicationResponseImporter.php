<?php

declare(strict_types=1);

namespace Peppol\Formats;

use Peppol\Models\ApplicationResponse;
use Peppol\Models\DocumentResponse;

/**
 * Importateur XML ApplicationResponse UBL 2.1
 *
 * Parse un document ApplicationResponse (MLR ou IMR) et retourne
 * un objet ApplicationResponse prêt à l'emploi.
 *
 * @package Peppol\Formats
 */
class ApplicationResponseImporter
{
    // =========================================================================
    // Point d'entrée
    // =========================================================================

    /**
     * Parse un XML ApplicationResponse (MLR ou IMR)
     *
     * @param string $xmlContent Contenu XML ou chemin de fichier
     * @return ApplicationResponse
     * @throws \InvalidArgumentException Si le XML est invalide ou incomplet
     */
    public static function fromXml(string $xmlContent): ApplicationResponse
    {
        // Lecture fichier si chemin fourni
        if (file_exists($xmlContent)) {
            $xmlContent = file_get_contents($xmlContent);
            if ($xmlContent === false) {
                throw new \InvalidArgumentException('Impossible de lire le fichier XML');
            }
        }

        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        if (!$dom->loadXML($xmlContent)) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            throw new \InvalidArgumentException(
                'XML invalide : ' . ($errors[0]->message ?? 'Erreur inconnue')
            );
        }

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('ar',  'urn:oasis:names:specification:ubl:schema:xsd:ApplicationResponse-2');
        $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');

        // Vérification élément racine
        $root = $dom->documentElement;
        if ($root === null || $root->localName !== 'ApplicationResponse') {
            throw new \InvalidArgumentException(
                'Le document XML doit avoir un élément racine <ApplicationResponse>'
            );
        }

        // Champs obligatoires
        $id          = self::xval($xpath, '//cbc:ID');
        $issueDate   = self::xval($xpath, '//cbc:IssueDate');
        $customizationId = self::xval($xpath, '//cbc:CustomizationID', ApplicationResponse::CUSTOMIZATION_IMR);
        $profileId   = self::xval($xpath, '//cbc:ProfileID', ApplicationResponse::PROFILE_IMR);

        if (!$id || !$issueDate) {
            throw new \InvalidArgumentException(
                'ApplicationResponse invalide : cbc:ID et cbc:IssueDate sont obligatoires'
            );
        }

        $ar = new ApplicationResponse($id, $issueDate, $customizationId, $profileId);

        // Heure d'émission
        $issueTime = self::xval($xpath, '//cbc:IssueTime');
        if ($issueTime) {
            $ar->setIssueTime($issueTime);
        }

        // Parties
        self::loadParty($xpath, $ar, 'SenderParty', 'setSenderParty');
        self::loadParty($xpath, $ar, 'ReceiverParty', 'setReceiverParty');

        // DocumentResponse(s)
        $docResponseNodes = $xpath->query('//cac:DocumentResponse');
        if (!$docResponseNodes || $docResponseNodes->length === 0) {
            throw new \InvalidArgumentException(
                'ApplicationResponse invalide : au moins un cac:DocumentResponse est requis'
            );
        }

        foreach ($docResponseNodes as $drNode) {
            $dr = self::parseDocumentResponse($xpath, $drNode);
            if ($dr !== null) {
                $ar->addDocumentResponse($dr);
            }
        }

        return $ar;
    }

    // =========================================================================
    // Parsing DocumentResponse
    // =========================================================================

    private static function parseDocumentResponse(\DOMXPath $xpath, \DOMNode $node): ?DocumentResponse
    {
        $responseCode = self::xval($xpath, 'cac:Response/cbc:ResponseCode', null, $node);
        $referenceId  = self::xval($xpath, 'cac:DocumentReference/cbc:ID', null, $node);

        if (!$responseCode || !$referenceId) {
            return null; // DocumentResponse incomplet — ignoré
        }

        try {
            $description = self::xval($xpath, 'cac:Response/cbc:Description', null, $node);
            $dr = new DocumentResponse($responseCode, $referenceId, $description);
        } catch (\InvalidArgumentException $e) {
            return null;
        }

        // Type de document référencé
        $typeCode = self::xval($xpath, 'cac:DocumentReference/cbc:DocumentTypeCode', null, $node);
        if ($typeCode) {
            $dr->setReferenceTypeCode($typeCode);
        }

        // UUID du document référencé
        $uuid = self::xval($xpath, 'cac:DocumentReference/cbc:UUID', null, $node);
        if ($uuid) {
            $dr->setReferenceUuid($uuid);
        }

        // LineResponse(s) — erreurs détaillées
        $lineNodes = $xpath->query('cac:LineResponse', $node);
        if ($lineNodes) {
            foreach ($lineNodes as $lineNode) {
                $lineDesc = self::xval($xpath, 'cac:Response/cbc:Description', null, $lineNode);
                $lineCode = self::xval($xpath, 'cac:Response/cbc:ResponseCode', null, $lineNode);
                if ($lineDesc) {
                    $dr->addLineResponse($lineDesc, $lineCode);
                }
            }
        }

        return $dr;
    }

    // =========================================================================
    // Parsing parties
    // =========================================================================

    private static function loadParty(
        \DOMXPath $xpath,
        ApplicationResponse $ar,
        string $elementName,
        string $setterMethod
    ): void {
        $basePath = "//cac:{$elementName}";

        $name = self::xval($xpath, "{$basePath}/cac:PartyName/cbc:Name")
             ?? self::xval($xpath, "{$basePath}/cac:PartyLegalEntity/cbc:RegistrationName")
             ?? '';

        $endpointNode = $xpath->query("{$basePath}/cbc:EndpointID")->item(0);
        $endpointId     = null;
        $endpointScheme = null;

        if ($endpointNode !== null) {
            $endpointId = trim($endpointNode->nodeValue);
            $endpointScheme = ($endpointNode instanceof \DOMElement)
                ? ($endpointNode->getAttribute('schemeID') ?: null)
                : null;
        }

        if ($name !== '' || $endpointId !== null) {
            $ar->$setterMethod($name, $endpointId, $endpointScheme);
        }
    }

    // =========================================================================
    // Helper XPath
    // =========================================================================

    private static function xval(
        \DOMXPath $xpath,
        string $query,
        ?string $default = null,
        ?\DOMNode $context = null
    ): ?string {
        $nodes = $context ? $xpath->query($query, $context) : $xpath->query($query);
        if ($nodes && $nodes->length > 0) {
            $val = trim($nodes->item(0)->nodeValue);
            return $val !== '' ? $val : $default;
        }
        return $default;
    }
}
