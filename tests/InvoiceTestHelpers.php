<?php

declare(strict_types=1);

namespace Peppol\Tests;

use Peppol\Models\Party;
use Peppol\Models\Address;
use Peppol\Models\ElectronicAddress;
use Peppol\Models\InvoiceLine;
use Peppol\Models\AllowanceCharge;

/**
 * Trait de helpers partagés entre tous les tests PHPPeppol.
 */
trait InvoiceTestHelpers
{
    /**
     * Crée un objet Party minimal valide.
     */
    protected function makeParty(
        string $name,
        string $vatId,
        string $street = 'Rue de la Loi 1',
        string $city = 'Bruxelles',
        string $postal = '1000',
        string $country = 'BE'
    ): Party {
        $address = new Address($street, $city, $postal, $country);
        $endpoint = new ElectronicAddress('0208', $vatId);
        return new Party($name, $address, $vatId, null, null, $endpoint);
    }

    /**
     * Crée une InvoiceLine minimale valide.
     */
    protected function makeLine(
        string $id,
        float $qty,
        float $price,
        string $vatCat = 'S',
        float $vatRate = 21.0,
        string $name = ''
    ): InvoiceLine {
        return new InvoiceLine(
            $id,
            $name ?: "Article $id",
            $qty,
            'C62',
            $price,
            $vatCat,
            $vatRate
        );
    }
}
