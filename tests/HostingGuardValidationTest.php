<?php

declare(strict_types=1);
include_once __DIR__ . '/stubs/Validator.php';
class HostingGuardValidationTest extends TestCaseSymconValidation
{
    public function testValidateHostingGuard(): void
    {
        $this->validateLibrary(__DIR__ . '/..');
    }

    public function testValidateCertificatesModule(): void
    {
        $this->validateModule(__DIR__ . '/../Certificates');
    }

    public function testValidateDatabasesModule(): void
    {
        $this->validateModule(__DIR__ . '/../Databases');
    }

    public function testValidateWebSpacesModule(): void
    {
        $this->validateModule(__DIR__ . '/../Webspaces');
    }
}