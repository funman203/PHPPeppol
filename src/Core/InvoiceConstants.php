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
        '54' => 'Carte de crédit',
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
 * Liste complète des codes utilisés dans la facturation électronique
 * Source : https://unece.org/trade/uncefact/cl-recommendations (Rev. 16, 2021)
 *
 * @var array<string, string>
 */
public const UNIT_CODES = [
    // -------------------------------------------------------------------------
    // Unités génériques / commerce
    // -------------------------------------------------------------------------
    'C62' => 'Unité (pièce immatérielle)',
    'H87' => 'Unité (pièce physique)',
    'EA'  => 'Élément',
    'ZZ'  => 'Unité définie mutuellement',
    'SET' => 'Ensemble',
    'PR'  => 'Paire',
    'DZN' => 'Douzaine',
    'GRO' => 'Grosse (144 unités)',
    'HGR' => 'Centaine de grammes',
    'KT'  => 'Kit',
    'NMP' => 'Nombre de paquets',
    'OP'  => 'Paquet de deux',
    'ST'  => 'Feuille',
    'TU'  => 'Tube',
    'VI'  => 'Flacon',
    'VQ'  => 'Cartouche',
    'WR'  => 'Bobine',
 
    // -------------------------------------------------------------------------
    // Emballages / conditionnement
    // -------------------------------------------------------------------------
    'BX'  => 'Boîte',
    'PK'  => 'Paquet',
    'BG'  => 'Sac',
    'CT'  => 'Carton',
    'CS'  => 'Caisse',
    'DR'  => 'Fût / Tonneau',
    'JR'  => 'Bocal',
    'BO'  => 'Bouteille',
    'CL'  => 'Bobine (fil)',
    'CR'  => 'Caisse (crisse)',
    'EN'  => 'Enveloppe',
    'GZ'  => 'Cage',
    'PX'  => 'Palette',
    'RO'  => 'Rouleau',
    'SL'  => 'Bobineau',
    'TN'  => 'Bidon',
    'TR'  => 'Bande',
    'PD'  => 'Coussinet',
 
    // -------------------------------------------------------------------------
    // Temps
    // -------------------------------------------------------------------------
    'SEC' => 'Seconde',
    'MIN' => 'Minute',
    'HUR' => 'Heure',
    'DAY' => 'Jour',
    'WEE' => 'Semaine',
    'MON' => 'Mois',
    'ANN' => 'Année',
    'QAN' => 'Trimestre',
    'SAN' => 'Semestre',
    'DEC' => 'Décennie',
 
    // -------------------------------------------------------------------------
    // Longueur / distance
    // -------------------------------------------------------------------------
    'MMT' => 'Millimètre',
    'CMT' => 'Centimètre',
    'DMT' => 'Décimètre',
    'MTR' => 'Mètre',
    'KMT' => 'Kilomètre',
    'INH' => 'Pouce',
    'FOT' => 'Pied',
    'YRD' => 'Yard',
    'SMI' => 'Mile terrestre',
    'NMI' => 'Mille nautique',
    'ANG' => 'Angström',
    'HMT' => 'Hectomètre',
 
    // -------------------------------------------------------------------------
    // Surface
    // -------------------------------------------------------------------------
    'MMK' => 'Millimètre carré',
    'CMK' => 'Centimètre carré',
    'DMK' => 'Décimètre carré',
    'MTK' => 'Mètre carré',
    'KMK' => 'Kilomètre carré',
    'HAR' => 'Hectare',
    'ARE' => 'Are',
    'INK' => 'Pouce carré',
    'FTK' => 'Pied carré',
    'YDK' => 'Yard carré',
 
    // -------------------------------------------------------------------------
    // Volume / capacité
    // -------------------------------------------------------------------------
    'MMQ' => 'Millimètre cube',
    'CMQ' => 'Centimètre cube',
    'DMQ' => 'Décimètre cube',
    'MTQ' => 'Mètre cube',
    'MLT' => 'Millilitre',
    'CLT' => 'Centilitre',
    'DLT' => 'Décilitre',
    'LTR' => 'Litre',
    'HLT' => 'Hectolitre',
    'GLL' => 'Gallon (US)',
    'QT'  => 'Quart (US)',
    'PT'  => 'Pinte (US)',
    'BLL' => 'Baril (pétrole)',
    'FTQ' => 'Pied cube',
    'INQ' => 'Pouce cube',
    'YDQ' => 'Yard cube',
 
    // -------------------------------------------------------------------------
    // Masse / poids
    // -------------------------------------------------------------------------
    'MC'  => 'Microgramme',
    'MGM' => 'Milligramme',
    'CGM' => 'Centigramme',
    'DGM' => 'Décigramme',
    'GRM' => 'Gramme',
    'HGM' => 'Hectogramme',
    'KGM' => 'Kilogramme',
    'TNE' => 'Tonne métrique',
    'DTN' => 'Décitonne',
    'ONZ' => 'Once',
    'LBR' => 'Livre (avoirdupois)',
    'STN' => 'Tonne courte (US)',
    'LTN' => 'Tonne longue (UK)',
    'CWI' => 'Quintal (UK)',
    'CWA' => 'Quintal (US)',
 
    // -------------------------------------------------------------------------
    // Énergie / puissance / électricité
    // -------------------------------------------------------------------------
    'WTT' => 'Watt',
    'KWT' => 'Kilowatt',
    'MWT' => 'Mégawatt',
    'GWT' => 'Gigawatt',
    'WHR' => 'Watt-heure',
    'KWH' => 'Kilowatt-heure',
    'MWH' => 'Mégawatt-heure',
    'GWH' => 'Gigawatt-heure',
    'JOU' => 'Joule',
    'KJO' => 'Kilojoule',
    'MJO' => 'Mégajoule',
    'GJO' => 'Gigajoule',
    'BTU' => 'British Thermal Unit',
    'kcl' => 'Kilocalorie',
    'AMP' => 'Ampère',
    'VLT' => 'Volt',
    'OHM' => 'Ohm',
    'FAR' => 'Farad',
    'HTZ' => 'Hertz',
    'KHZ' => 'Kilohertz',
    'MHZ' => 'Mégahertz',
    'GHZ' => 'Gigahertz',
    'VA'  => 'Volt-ampère',
    'KVA' => 'Kilovolt-ampère',
    'MVA' => 'Mégavolt-ampère',
 
    // -------------------------------------------------------------------------
    // Informatique / données
    // -------------------------------------------------------------------------
    'AD'  => 'Octet (byte)',
    'E34' => 'Kilooctet',
    'E35' => 'Mégaoctet',
    'E36' => 'Gigaoctet',
    'E37' => 'Téraoctet',
 
    // -------------------------------------------------------------------------
    // Pression / force
    // -------------------------------------------------------------------------
    'PAL' => 'Pascal',
    'KPA' => 'Kilopascal',
    'MPA' => 'Mégapascal',
    'BAR' => 'Bar',
    'MBR' => 'Millibar',
    'ATM' => 'Atmosphère',
    'NEW' => 'Newton',
    'KNS' => 'Kilonewton',
    'DYN' => 'Dyne',
 
    // -------------------------------------------------------------------------
    // Température
    // -------------------------------------------------------------------------
    'CEL' => 'Degré Celsius',
    'FAH' => 'Degré Fahrenheit',
    'KEL' => 'Kelvin',
 
    // -------------------------------------------------------------------------
    // Vitesse / débit
    // -------------------------------------------------------------------------
    'MTS' => 'Mètre par seconde',
    'KMH' => 'Kilomètre par heure',
    'KNT' => 'Nœud',
    'MQS' => 'Mètre cube par seconde',
    'MQH' => 'Mètre cube par heure',
    'LTS' => 'Litre par seconde',
    'LTH' => 'Litre par heure',
 
    // -------------------------------------------------------------------------
    // Finance / monétaire
    // -------------------------------------------------------------------------
    'MON' => 'Mois',   // déjà dans Temps — conservé pour compatibilité
    'PCT' => 'Pourcentage',
    'M4'  => 'Point de base (0,01%)',
 
    // -------------------------------------------------------------------------
    // Divers industriels
    // -------------------------------------------------------------------------
    'MTR' => 'Mètre (linéaire)',  // alias
    'RM'  => 'Rame (papier)',
    'ROL' => 'Rouleau',
    'STK' => 'Stick / Bâton',
    'AV'  => 'Ampoule',
    'CA'  => 'Boîte de conserve',
    'NRL' => 'Nombre de rouleaux',
    'PFL' => 'Paire de gants',
    'PTN' => 'Portion',
    'SYR' => 'Seringue',
 
    // -------------------------------------------------------------------------
    // Services / prestation / facturation numérique
    // -------------------------------------------------------------------------
    'E48' => 'Unité de service',
    'E51' => 'Appel',
    'E52' => 'Transaction',
    'E54' => 'Point',
    'NAR' => 'Nombre d\'articles',
    'NF'  => 'Message',
    'NMP' => 'Nombre de paquets',
    'TP'  => 'Mille feuilles (1 000 feuilles)',
];

/**
 * Abréviations courtes des unités de mesure (pour affichage compact)
 * @var array<string, string>
 */
public const UNIT_CODES_SHORT = [
    'C62' => 'u.',
    'H87' => 'u.',
    'EA'  => 'u.',
    'ZZ'  => 'u.',
    'SET' => 'ens.',
    'PR'  => 'paire',
    'DZN' => 'dz.',
    'SEC' => 's',
    'MIN' => 'min',
    'HUR' => 'h',
    'DAY' => 'j',
    'WEE' => 'sem.',
    'MON' => 'mois',
    'ANN' => 'an',
    'QAN' => 'trim.',
    'SAN' => 'sem.',
    'MMT' => 'mm',
    'CMT' => 'cm',
    'DMT' => 'dm',
    'MTR' => 'm',
    'KMT' => 'km',
    'INH' => 'in',
    'FOT' => 'ft',
    'YRD' => 'yd',
    'SMI' => 'mi',
    'NMI' => 'nmi',
    'MMK' => 'mm²',
    'CMK' => 'cm²',
    'DMK' => 'dm²',
    'MTK' => 'm²',
    'KMK' => 'km²',
    'HAR' => 'ha',
    'ARE' => 'a',
    'MMQ' => 'mm³',
    'CMQ' => 'cm³',
    'DMQ' => 'dm³',
    'MTQ' => 'm³',
    'MLT' => 'ml',
    'CLT' => 'cl',
    'DLT' => 'dl',
    'LTR' => 'l',
    'HLT' => 'hl',
    'GLL' => 'gal',
    'BLL' => 'bbl',
    'MC'  => 'µg',
    'MGM' => 'mg',
    'GRM' => 'g',
    'HGM' => 'hg',
    'KGM' => 'kg',
    'TNE' => 't',
    'ONZ' => 'oz',
    'LBR' => 'lb',
    'STN' => 't.c.',
    'LTN' => 't.l.',
    'WTT' => 'W',
    'KWT' => 'kW',
    'MWT' => 'MW',
    'GWT' => 'GW',
    'WHR' => 'Wh',
    'KWH' => 'kWh',
    'MWH' => 'MWh',
    'GWH' => 'GWh',
    'JOU' => 'J',
    'KJO' => 'kJ',
    'MJO' => 'MJ',
    'GJO' => 'GJ',
    'AMP' => 'A',
    'VLT' => 'V',
    'OHM' => 'Ω',
    'FAR' => 'F',
    'HTZ' => 'Hz',
    'KHZ' => 'kHz',
    'MHZ' => 'MHz',
    'GHZ' => 'GHz',
    'VA'  => 'VA',
    'KVA' => 'kVA',
    'PAL' => 'Pa',
    'KPA' => 'kPa',
    'MPA' => 'MPa',
    'BAR' => 'bar',
    'MBR' => 'mbar',
    'ATM' => 'atm',
    'NEW' => 'N',
    'KNS' => 'kN',
    'CEL' => '°C',
    'FAH' => '°F',
    'KEL' => 'K',
    'MTS' => 'm/s',
    'KMH' => 'km/h',
    'KNT' => 'kn',
    'MQS' => 'm³/s',
    'MQH' => 'm³/h',
    'LTS' => 'l/s',
    'LTH' => 'l/h',
    'PCT' => '%',
    'M4'  => 'bp',
    'AD'  => 'B',
    'E34' => 'kB',
    'E35' => 'MB',
    'E36' => 'GB',
    'E37' => 'TB',
    'BX'  => 'boîte',
    'PK'  => 'pqt',
    'BG'  => 'sac',
    'CT'  => 'ctn',
    'CS'  => 'caisse',
    'DR'  => 'fût',
    'RO'  => 'roul.',
    'ROL' => 'roul.',
    'RM'  => 'rame',
    'NAR' => 'nb',
    'E48' => 'svc',
    'E51' => 'appel',
    'E52' => 'transac.',
    'E54' => 'pt',
    'NF'  => 'msg',
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
