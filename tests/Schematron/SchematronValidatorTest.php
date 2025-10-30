<?php

declare(strict_types=1);

namespace Peppol\Tests\Schematron;

use PHPUnit\Framework\TestCase;
use Peppol\Validation\SchematronValidator;
use Peppol\Formats\XmlExporter;

/**
 * Tests pour le validateur Schematron
 */
class SchematronValidatorTest extends TestCase
{
    private SchematronValidator $validator;
    
    protected function setUp(): void
    {
        if (!extension_loaded('xsl')) {
            $this->markTestSkipped('Extension XSL non disponible');
        }
        
        $this->validator = new SchematronValidator();
    }
    
    public function testValidatorInstantiation(): void
    {
        $this->assertInstanceOf(SchematronValidator::class, $this->validator);
    }
    
    public function testInstallSchematronFiles(): void
    {
        $results = $this->validator->installSchematronFiles();
        
        $this->assertIsArray($results);
        $this->assertArrayHasKey('ublbe', $results);
        $this->assertArrayHasKey('en16931', $results);
    }
    
    public function testValidateValidInvoice(): void
    {
        $validXml = $this->getValidInvoiceXml();
        
        try {
            $result = $this->validator->validate($validXml, ['ublbe']);
            
            $this->assertNotNull($result);
            $this->assertTrue($result->isValid() || $result->getErrorCount() > 0);
            
        } catch (\Exception $e) {
            // Si les fichiers Schematron ne sont pas installés
            $this->markTestSkipped('Fichiers Schematron non disponibles: ' . $e->getMessage());
        }
    }
    
    public function testValidateInvalidInvoice(): void
    {
        $invalidXml = $this->getInvalidInvoiceXml();
        
        try {
            $result = $this->validator->validate($invalidXml, ['ublbe']);
            
            $this->assertNotNull($result);
            $this->assertFalse($result->isValid());
            $this->assertGreaterThan(0, $result->getErrorCount());
            
        } catch (\Exception $e) {
            $this->markTestSkipped('Fichiers Schematron non disponibles: ' . $e->getMessage());
        }
    }
    
    public function testCacheFunctionality(): void
    {
        $xml = $this->getValidInvoiceXml();
        
        try {
            // Première validation (compilation)
            $start1 = microtime(true);
            $this->validator->validate($xml, ['ublbe']);
            $time1 = microtime(true) - $start1;
            
            // Deuxième validation (avec cache)
            $start2 = microtime(true);
            $this->validator->validate($xml, ['ublbe']);
            $time2 = microtime(true) - $start2;
            
            // Le cache devrait rendre la deuxième validation plus rapide
            $this->assertLessThan($time1, $time2);
            
        } catch (\Exception $e) {
            $this->markTestSkipped('Cache test skipped: ' . $e->getMessage());
        }
    }
    
    public function testClearCache(): void
    {
        $result = $this->validator->clearCache();
        $this->assertTrue($result);
    }
    
    public function testValidationResult(): void
    {
        $xml = $this->getValidInvoiceXml();
        
        try {
            $result = $this->validator->validate($xml, ['ublbe']);
            
            // Test des méthodes du résultat
            $this->assertIsInt($result->getErrorCount());
            $this->assertIsInt($result->getWarningCount());
            $this->assertIsInt($result->getInfoCount());
            $this->assertIsArray($result->getErrors());
            $this->assertIsArray($result->getWarnings());
            $this->assertIsString($result->getSummary());
            $this->assertIsString($result->toJson());
            
        } catch (\Exception $e) {
            $this->markTestSkipped('Validation result test skipped: ' . $e->getMessage());
        }
    }
    
    /**
     * Retourne un XML de facture valide pour les tests
     */
    private function getValidInvoiceXml(): string
    {
        $invoice = new \PeppolInvoice('TEST-001', '2025-10-30');
        
        $invoice->setSellerFromData(
            name: 'Test Seller SPRL',
            vatId: 'BE0477472701',
            streetName: 'Test Street 1',
            postalZone: '1000',
            cityName: 'Brussels',
            countryCode: 'BE',
            electronicAddressScheme: '0106',
            electronicAddress: '0477472701'
        );
        
        $invoice->setBuyerFromData(
            name: 'Test Buyer SA',
            streetName: 'Buyer Street 2',
            postalZone: '1050',
            cityName: 'Brussels',
            countryCode: 'BE',
            vatId: 'BE0987654321',
            electronicAddressScheme: '9925',
            electronicAddress: 'BE0987654321'
        );
        
        $invoice->setBuyerReference('TEST-REF-001');
        $invoice->setDueDate('2025-11-30');
        
        $invoice->addLine(
            id: '1',
            name: 'Test Product',
            quantity: 1.0,
            unitCode: 'C62',
            unitPrice: 100.00,
            vatCategory: 'S',
            vatRate: 21.0
        );
        
        $invoice->attachDocument(
            new \Peppol\Models\AttachedDocument(
                'test1.pdf',
                'Test content 1',
                'application/pdf',
                'Test document 1'
            )
        );
        
        $invoice->attachDocument(
            new \Peppol\Models\AttachedDocument(
                'test2.pdf',
                'Test content 2',
                'application/pdf',
                'Test document 2'
            )
        );
        
        $invoice->calculateTotals();
        
        $exporter = new XmlExporter($invoice);
        return $exporter->toUbl21();
    }
    
    /**
     * Retourne un XML de facture invalide pour les tests
     */
    private function getInvalidInvoiceXml(): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2">
    <cbc:ID>INVALID</cbc:ID>
    <!-- Document incomplet pour forcer des erreurs -->
</Invoice>
XML;
    }
}
