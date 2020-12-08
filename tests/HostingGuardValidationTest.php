<?php

declare(strict_types=1);
include_once __DIR__ . '/stubs/Validator.php';
class HostingGuardValidationTest extends TestCaseSymconValidation
{
    public function testValidateHostingGuard(): void
    {
        $this->validateLibrary(__DIR__ . '/..');
    }

    public function testValidateHostingGuardModule(): void
    {
        $this->validateModule(__DIR__ . '/../HostingGuard');
    }
}