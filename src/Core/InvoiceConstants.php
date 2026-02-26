<?php

declare(strict_types=1);

namespace Peppol\Core;

/**
 * Constantes normalisées pour la facturation électronique
 * 
 * Cette classe centralise tous les codes et énumérations utilisés
 * dans les factures électroniques conformes aux normes internationales.
 * 
 * @package Peppol\Core
 * @author Votre Nom
 * @version 1.0
 */
final class InvoiceConstants
{
    /**
     * Codes de type de facture selon UNCL1001
     * @var array<int|string, string>
     */
    public const INVOICE_TYPE_CODES = [
        '380' => 'Facture commerciale',
        '381' => 'Avoir',
        '386' => 'Facture d\'acompte',
        '384' => 'Facture rectificative',
        '383' => 'Facture de débit',
        '389' => 'Facture d\'autofacturation'
    ];
    
    /**
     * Codes devise selon ISO 4217
     * @var array<string>
     */
    public const CURRENCY_CODES = [
        'EUR', 'USD', 'GBP', 'CHF', 'CAD', 'JPY', 'AUD', 'NZD', 
        'SEK', 'NOK', 'DKK', 'PLN', 'CZK', 'HUF', 'RON', 'BGN', 'HRK'
    ];
    
    /**
     * Catégories de TVA selon UNCL5305
     * @var array<string, string>
     */
    public const VAT_CATEGORIES = [
        'S' => 'Taux standard',
        'Z' => 'Taux zéro',
        'E' => 'Exonéré',
        'AE' => 'Autoliquidation',
        'K' => 'Intra-communautaire',
        'G' => 'Exportation hors UE',
        'O' => 'Hors champ TVA',
        'L' => 'Services Canariens',
        'M' => 'Taxes régionales'
    ];
    
    /**
     * Codes de moyens de paiement selon UNCL4461
     * @var array<int|string, string>
     */
    public const PAYMENT_MEANS_CODES = [
        '1' => 'Instrument non défini',
        '10' => 'Espèces',
        '20' => 'Chèque',
        '30' => 'Virement bancaire',
        '31' => 'Virement SEPA',
        '42' => 'Paiement à un compte bancaire',
        '48' => 'Carte de paiement',
        '49' => 'Prélèvement automatique',
        '57' => 'Domiciliation européenne (SEPA Direct Debit)',
        '58' => 'Virement SEPA',
        '59' => 'Prélèvement SEPA B2B',
        '97' => 'Compensation'
    ];
    
    /**
     * Raisons d'exonération de TVA selon UNCL5305
     * @var array<string, string>
     */
    public const VAT_EXEMPTION_REASONS = [
        'VATEX-EU-79-C' => 'Exonéré - Article 79 Directive TVA UE',
        'VATEX-EU-132' => 'Exonéré - Article 132 Directive TVA UE',
        'VATEX-EU-143' => 'Exonéré - Article 143 Directive TVA UE',
        'VATEX-EU-148' => 'Exonéré - Article 148 Directive TVA UE',
        'VATEX-EU-151' => 'Exonéré - Article 151 Directive TVA UE',
        'VATEX-EU-309' => 'Exonéré - Article 309 Directive TVA UE',
        'VATEX-EU-AE' => 'Autoliquidation',
        'VATEX-EU-IC' => 'Livraison intracommunautaire',
        'VATEX-EU-G' => 'Exportation hors UE',
        'VATEX-EU-O' => 'Hors champ d\'application de la TVA',
        'VATEX-BE-SMALL' => 'Petite entreprise exemptée (Belgique)'
    ];
    
    /**
     * Schémas d'identification électronique selon ISO 6523 ICD
     * @var array<int|string, string>
     */
    public const ELECTRONIC_ADDRESS_SCHEMES = [
        '0002' => 'SIRENE (France)',
        '0007' => 'LIEF (Suède)',
        '0009' => 'SIRET (France)', 
        '0037' => 'LY-tunnus (Finlande)',
        '0060' => 'DUNS',
        '0088' => 'EAN/GLN',
        '0096' => 'DANISH CVR',
        '0208' => 'Numero d\'entreprise',
        '0135' => 'SIA (Italie)',
        '0142' => 'SECETI',
        '0184' => 'DIGSTORG (Danemark)',
        '0190' => 'Dutch Originator\'s Identification Number',
        '0191' => 'Centre of Registers and Information Systems (Estonie)',
        '0192' => 'Enhetsregisteret ved Bronnoysundregisterne (Norvège)',
        '0195' => 'Registre de Commerce et des Sociétés (Luxembourg)',
        '0196' => 'Icelandic VAT number',
        '9925' => 'Numéro TVA (prefixé par code pays)',
        '9956' => 'Belgian Crossroad Bank of Enterprises'
    ];
    
    /**
     * Taux de TVA belges standards
     * @var array<float>
     */
    public const BE_VAT_RATES = [21.0, 12.0, 6.0, 0.0];
    
    /**
     * Codes d'unité de mesure selon UN/ECE Recommendation 20
     * Liste non exhaustive des codes les plus utilisés
     * @var array<string, string>
     */
    public const UNIT_CODES = [
        'C62' => 'Unité (pièce immatériel)',
        'H87' => 'Unité (pièce physique)',
        'HUR' => 'Heure',
        'DAY' => 'Jour',
        'MON' => 'Mois',
        'ANN' => 'Année',
        'MTR' => 'Mètre',
        'KMT' => 'Kilomètre',
        'MTK' => 'Mètre carré',
        'MTQ' => 'Mètre cube',
        'LTR' => 'Litre',
        'KGM' => 'Kilogramme',
        'TNE' => 'Tonne métrique',
        'GRM' => 'Gramme',
        'MGM' => 'Milligramme',
        'KWH' => 'Kilowatt-heure',
        'MWH' => 'Mégawatt-heure',
        'SET' => 'Ensemble',
        'MIN' => 'Minute',
        'SEC' => 'Seconde',
        'WEE' => 'Semaine',
        'BX' => 'Boîte',
        'PK' => 'Paquet',
        'EA' => 'Élément',
        'PR' => 'Paire',
        'DZN' => 'Douzaine',
        'GLL' => 'Gallon',
        'ONZ' => 'Once',
        'LBR' => 'Livre (masse)',
        'FOT' => 'Pied',
        'INH' => 'Pouce',
        'YRD' => 'Yard',
        'SMI' => 'Mile',
        'ZZ' => 'Unité définie mutuellement (mutually defined)'
    ];
    
    /**
     * Identifiant de personnalisation pour Peppol BIS Billing 3.0
     */
    public const CUSTOMIZATION_PEPPOL = 'urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0';
    
    /**
     * Identifiant de personnalisation pour UBL.BE 1.0
     */
    public const CUSTOMIZATION_UBL_BE = 'urn:cen.eu:en16931:2017#conformant#urn:UBL.BE:1.0.0.20180214';
    
    /**
     * Identifiant de profil Peppol
     */
    public const PROFILE_PEPPOL = 'urn:fdc:peppol.eu:2017:poacc:billing:01:1.0';
    
    /**
     * Version UBL
     */
    public const UBL_VERSION = '2.1';
    
    /**
     * Types MIME supportés pour les documents joints
     * @var array<string>
     */
    public const SUPPORTED_MIME_TYPES = [
        'application/pdf',
        'image/png',
        'image/jpeg',
        'image/jpg',
        'image/gif',
        'application/xml',
        'text/xml',
        'text/csv',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-excel',
        'application/zip',
        'text/plain'
    ];
    
    /**
     * Taille maximale par fichier joint (10 MB)
     */
    public const MAX_ATTACHMENT_SIZE = 10485760;
    
    // Empêche l'instanciation de cette classe
    private function __construct() {}
}
