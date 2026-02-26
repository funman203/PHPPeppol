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
        $address = new Address();
        $address->setStreetName($street)
                ->setCityName($city)
                ->setPostalZone($postal)
                ->setCountryCode($country);

        $endpoint = new ElectronicAddress();
        $endpoint->setIdentifier($vatId)
                 ->setSchemeId('0208');

        $party = new Party();
        $party->setName($name)
              ->setVatId($vatId)
              ->setAddress($address)
              ->setElectronicAddress($endpoint);

        return $party;
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
        $line = new InvoiceLine();
        $line->setId($id)
             ->setName($name ?: "Article $id")
             ->setQuantity($qty)
             ->setUnitCode('C62')
             ->setUnitPrice($price)
             ->setVatCategory($vatCat)
             ->setVatRate($vatRate);
        return $line;
    }
}
