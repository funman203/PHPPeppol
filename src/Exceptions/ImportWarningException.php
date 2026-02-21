<?php

declare(strict_types=1);

namespace Peppol\Exceptions;

use Peppol\Core\InvoiceBase;

/**
 * Levée en mode lenient (strict=false) quand l'import réussit mais présente
 * des anomalies : champs invalides chargés tels quels, et/ou écart entre les
 * totaux déclarés dans LegalMonetaryTotal et les totaux recalculés depuis les
 * lignes.
 *
 * La facture est toujours récupérable via getInvoice().
 *
 * @package Peppol\Exceptions
 */
class ImportWarningException extends \RuntimeException
{
    private InvoiceBase $invoice;

    /** @var array<string, array{declared: float, calculated: float, diff: float}> */
    private array $warnings;

    /** @var array<string> */
    private array $anomalies;

    /**
     * @param InvoiceBase                                                           $invoice
     * @param array<string, array{declared: float, calculated: float, diff: float}> $warnings
     * @param array<string>                                                         $anomalies
     */
    public function __construct(InvoiceBase $invoice, array $warnings = [], array $anomalies = [])
    {
        $this->invoice   = $invoice;
        $this->warnings  = $warnings;
        $this->anomalies = $anomalies;

        $parts = [];
        if (!empty($warnings)) {
            $parts[] = count($warnings) . ' écart(s) de totaux';
        }
        if (!empty($anomalies)) {
            $parts[] = count($anomalies) . ' anomalie(s) de champs';
        }

        parent::__construct('Import lenient avec ' . implode(', ', $parts) . '.');
    }

    /** Facture importée — toujours utilisable malgré les avertissements. */
    public function getInvoice(): InvoiceBase
    {
        return $this->invoice;
    }

    /** Écarts entre totaux déclarés (LegalMonetaryTotal) et totaux recalculés. */
    public function getWarnings(): array
   {
        return $this->warnings;
    }

    /** Champs invalides qui ont été chargés tels quels (BIC malformé, unitCode inconnu, etc.). */
    public function getAnomalies(): array
    {
        return $this->anomalies;
    }
}