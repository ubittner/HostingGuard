<?php

/** @noinspection PhpUnused */

declare(strict_types=1);

include_once __DIR__ . '/helper/autoload.php';

class HostingGuard extends IPSModule
{
    //Helper
    use HG_hostingAPI;

    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->RegisterProperties();
        $this->RegisterVariables();
        $this->RegisterTimer('UpdateData', 0, 'HG_UpdateData(' . $this->InstanceID . ');');
    }

    public function Destroy()
    {
        //Never delete this line!
        parent::Destroy();
    }

    public function ApplyChanges()
    {
        //Wait until IP-Symcon is started
        $this->RegisterMessage(0, IPS_KERNELSTARTED);
        //Never delete this line!
        parent::ApplyChanges();
        //Check kernel runlevel
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }
        $this->ValidateConfiguration();
        $this->UpdateWebSpacesList();
        $this->UpdateDatabasesList();
        $this->SetUpdateDataTimer();
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        $this->SendDebug('MessageSink', 'SenderID: ' . $SenderID . ', Message: ' . $Message, 0);
        switch ($Message) {
            case IPS_KERNELSTARTED:
                $this->KernelReady();
                break;

        }
    }

    public function GetConfigurationForm()
    {
        $formData = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        return json_encode($formData);
    }

    public function UpdateData(): void
    {
        $this->UpdateWebSpacesList();
        $this->UpdateDatabasesList();
    }

    public function UpdateWebSpacesList(): void
    {
        $success = false;
        $webSpaces = json_decode($this->ListingWebSpaces(), true);
        if (empty($webSpaces)) {
            return;
        }
        $webSpacesList = [];
        if (array_key_exists('response', $webSpaces)) {
            $response = $webSpaces['response'];
            if (array_key_exists('data', $response)) {
                $data = $response['data'];
                if (!empty($data)) {
                    $success = true;
                    foreach ($data as $webSpace) {
                        //poolId
                        $poolID = '-';
                        if (array_key_exists('poolId', $webSpace)) {
                            $poolID = $webSpace['poolId'];
                            if (empty($poolID)) {
                                $poolID = '-';
                            }
                        }
                        //name
                        $name = '-';
                        if (array_key_exists('name', $webSpace)) {
                            $name = $webSpace['name'];
                            if (empty($name)) {
                                $name = '-';
                            }
                        }
                        //storageQuota
                        $storageQuota = '-';
                        if (array_key_exists('storageQuota', $webSpace)) {
                            $storageQuota = $webSpace['storageQuota'];
                            if (empty($storageQuota)) {
                                $storageQuota = '-';
                            }
                        }
                        //storageUsed
                        $storageUsed = '-';
                        if (array_key_exists('storageUsed', $webSpace)) {
                            $storageUsed = $webSpace['storageUsed'];
                            if (empty($storageUsed)) {
                                $storageUsed = '-';
                            }
                        }
                        //storageQuotaUsedRatio
                        $storageQuotaUsedRatio = '-';
                        if (array_key_exists('storageQuotaUsedRatio', $webSpace)) {
                            $storageQuotaUsedRatio = $webSpace['storageQuotaUsedRatio'];
                            if (empty($storageQuotaUsedRatio)) {
                                $storageQuotaUsedRatio = '-';
                            }
                        }
                        array_push($webSpacesList, [
                            'poolId'                => $poolID,
                            'name'                  => $name,
                            'storageQuota'          => $storageQuota,
                            'storageUsed'           => $storageUsed,
                            'storageQuotaUsedRatio' => $storageQuotaUsedRatio]);
                    }
                }
            }
        }
        //Update list
        $string = "<table style='width: 100%; border-collapse: collapse;'>";
        $string .= '<tr><td><b>Status</b></td><td><b>poolId</b></td><td><b>name</b></td><td><b>storageQuota</b></td><td><b>storageUsed</b></td><td><b>storageQuotaUsedRatio</b></td></tr>';
        usort($webSpacesList, function ($a, $b)
        {
            return $a['poolId'] <=> $b['poolId'];
        });
        //Rebase array
        $webSpacesList = array_values($webSpacesList);
        if (!empty($webSpacesList)) {
            foreach ($webSpacesList as $webSpace) {
                $unicode = json_decode('"\u2705"'); # white_check_mark
                if ($webSpace['storageQuotaUsedRatio'] > $this->ReadPropertyInteger('ThresholdExceeded')) {
                    $unicode = json_decode('"\u26a0\ufe0f"'); # warning
                }
                if ($webSpace['storageQuotaUsedRatio'] > $this->ReadPropertyInteger('CriticalCondition')) {
                    $unicode = json_decode('"\u2757"'); # heavy_exclamation_mark
                }
                $string .= '<tr><td>' . $unicode . '</td><td>' . $webSpace['poolId'] . '</td><td>' . $webSpace['name'] . '</td><td>' . $webSpace['storageQuota'] . '</td><td>' . $webSpace['storageUsed'] . '</td><td>' . $webSpace['storageQuotaUsedRatio'] . '</td></tr>';
            }
        }
        $string .= '</table>';
        $this->SetValue('WebSpacesList', $string);
        if ($success) {
            $this->SetValue('LastUpdate', date('d.m.Y H:i:s'));
        }
        $this->SetUpdateDataTimer();
    }

    public function UpdateDatabasesList(): void
    {
        $success = false;
        $databases = json_decode($this->ListingDatabases(), true);
        if (empty($databases)) {
            return;
        }
        $databasesList = [];
        if (array_key_exists('response', $databases)) {
            $response = $databases['response'];
            if (array_key_exists('data', $response)) {
                $data = $response['data'];
                if (!empty($data)) {
                    $success = true;
                    foreach ($data as $database) {
                        //poolId
                        $poolID = '-';
                        if (array_key_exists('poolId', $database)) {
                            $poolID = $database['poolId'];
                            if (empty($poolID)) {
                                $poolID = '-';
                            }
                        }
                        //name
                        $name = '-';
                        if (array_key_exists('name', $database)) {
                            $name = $database['name'];
                            if (empty($name)) {
                                $name = '-';
                            }
                        }
                        //storageQuota
                        $storageQuota = '-';
                        if (array_key_exists('storageQuota', $database)) {
                            $storageQuota = $database['storageQuota'];
                            if (empty($storageQuota)) {
                                $storageQuota = '-';
                            }
                        }
                        //storageUsed
                        $storageUsed = '-';
                        if (array_key_exists('storageUsed', $database)) {
                            $storageUsed = $database['storageUsed'];
                            if (empty($storageUsed)) {
                                $storageUsed = '-';
                            }
                        }
                        //storageQuotaUsedRatio
                        $storageQuotaUsedRatio = '-';
                        if (array_key_exists('storageQuotaUsedRatio', $database)) {
                            $storageQuotaUsedRatio = $database['storageQuotaUsedRatio'];
                            if (empty($storageQuotaUsedRatio)) {
                                $storageQuotaUsedRatio = '-';
                            }
                        }
                        array_push($databasesList, [
                            'poolId'                => $poolID,
                            'name'                  => $name,
                            'storageQuota'          => $storageQuota,
                            'storageUsed'           => $storageUsed,
                            'storageQuotaUsedRatio' => $storageQuotaUsedRatio]);
                    }
                }
            }
        }
        //Update list
        $string = "<table style='width: 100%; border-collapse: collapse;'>";
        $string .= '<tr><td><b>Status</b></td><td><b>poolId</b></td><td><b>name</b></td><td><b>storageQuota</b></td><td><b>storageUsed</b></td><td><b>storageQuotaUsedRatio</b></td></tr>';
        usort($databasesList, function ($a, $b)
        {
            return $a['poolId'] <=> $b['poolId'];
        });
        //Rebase array
        $databasesList = array_values($databasesList);
        if (!empty($databasesList)) {
            foreach ($databasesList as $database) {
                $unicode = json_decode('"\u2705"'); # white_check_mark
                if ($database['storageQuotaUsedRatio'] > $this->ReadPropertyInteger('DatabaseThresholdExceeded')) {
                    $unicode = json_decode('"\u26a0\ufe0f"'); # warning
                }
                if ($database['storageQuotaUsedRatio'] > $this->ReadPropertyInteger('DatabaseCriticalCondition')) {
                    $unicode = json_decode('"\u2757"'); # heavy_exclamation_mark
                }
                $string .= '<tr><td>' . $unicode . '</td><td>' . $database['poolId'] . '</td><td>' . $database['name'] . '</td><td>' . $database['storageQuota'] . '</td><td>' . $database['storageUsed'] . '</td><td>' . $database['storageQuotaUsedRatio'] . '</td></tr>';
            }
        }
        $string .= '</table>';
        $this->SetValue('DatabasesList', $string);
        if ($success) {
            $this->SetValue('LastUpdate', date('d.m.Y H:i:s'));
        }
        $this->SetUpdateDataTimer();
    }

    #################### Private

    private function KernelReady(): void
    {
        $this->ApplyChanges();
    }

    private function RegisterProperties(): void
    {
        $this->RegisterPropertyBoolean('MaintenanceMode', false);
        $this->RegisterPropertyString('API_Key', '');
        $this->RegisterPropertyInteger('Timeout', 5000);
        $this->RegisterPropertyInteger('UpdateInterval', 30);
        $this->RegisterPropertyInteger('ThresholdExceeded', 80);
        $this->RegisterPropertyInteger('CriticalCondition', 90);
        $this->RegisterPropertyInteger('DatabaseThresholdExceeded', 60);
        $this->RegisterPropertyInteger('DatabaseCriticalCondition', 80);
    }

    private function RegisterVariables(): void
    {
        //Web spaces list
        $id = @$this->GetIDForIdent('WebSpacesList');
        $this->RegisterVariableString('WebSpacesList', 'Web Spaces', 'HTMLBox', 10);
        if ($id == false) {
            IPS_SetIcon($this->GetIDForIdent('WebSpacesList'), 'Cloud');
        }
        $id = @$this->GetIDForIdent('DatabasesList');
        $this->RegisterVariableString('DatabasesList', 'Databases', 'HTMLBox', 20);
        if ($id == false) {
            IPS_SetIcon($this->GetIDForIdent('DatabasesList'), 'Database');
        }
        //Last update
        $id = @$this->GetIDForIdent('LastUpdate');
        $this->RegisterVariableString('LastUpdate', 'Letzte Aktualisierung', '', 30);
        if ($id == false) {
            IPS_SetIcon($this->GetIDForIdent('LastUpdate'), 'Clock');
            $this->SetValue('LastUpdate', '-');
        }
    }

    private function SetUpdateDataTimer(): void
    {
        $milliseconds = $this->ReadPropertyInteger('UpdateInterval') * 1000 * 60;
        $this->SetTimerInterval('UpdateData', $milliseconds);
    }

    private function ValidateConfiguration(): void
    {
        $this->SendDebug(__FUNCTION__, 'Validate configuration', 0);
        $status = 102;
        //API key
        $apiKey = $this->ReadPropertyString('API_Key');
        if (empty($apiKey)) {
            $status = 201;
        }
        //Maintenance mode
        $maintenance = $this->CheckMaintenanceMode();
        if ($maintenance) {
            $status = 104;
        }
        IPS_SetDisabled($this->InstanceID, $maintenance);
        $this->SetStatus($status);
    }

    private function CheckMaintenanceMode(): bool
    {
        $result = $this->ReadPropertyBoolean('MaintenanceMode');
        if ($result) {
            $this->SendDebug(__FUNCTION__, 'Abbruch, der Wartungsmodus ist aktiv!', 0);
            $this->LogMessage('ID ' . $this->InstanceID . ', ' . __FUNCTION__ . ', Abbruch, der Wartungsmodus ist aktiv!', KL_WARNING);
        }
        return $result;
    }
}