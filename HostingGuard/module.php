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
        $this->RegisterTimer('UpdateWebSpaces', 0, 'HG_UpdateWebSpacesList(' . $this->InstanceID . ');');
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
        $this->SetUpdateWebSpacesTimer();
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
                if ($webSpace['storageQuotaUsedRatio'] > $this->ReadPropertyInteger('CriticalCondition')) {
                    $unicode = json_decode('"\u26a0\ufe0f"'); # warning
                }
                $string .= '<tr><td>' . $unicode . '</td><td>' . $webSpace['poolId'] . '</td><td>' . $webSpace['name'] . '</td><td>' . $webSpace['storageQuota'] . '</td><td>' . $webSpace['storageUsed'] . '</td><td>' . $webSpace['storageQuotaUsedRatio'] . '</td></tr>';
            }
        }
        $string .= '</table>';
        $this->SetValue('WebSpacesList', $string);
        if ($success) {
            $this->SetValue('LastUpdate', date('d.m.Y H:i:s'));
        }
        $this->SetUpdateWebSpacesTimer();
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
        $this->RegisterPropertyInteger('CriticalCondition', 80);
    }

    private function RegisterVariables(): void
    {
        //Web spaces list
        $id = @$this->GetIDForIdent('WebSpacesList');
        $this->RegisterVariableString('WebSpacesList', 'Web Spaces', 'HTMLBox', 10);
        if ($id == false) {
            IPS_SetIcon($this->GetIDForIdent('WebSpacesList'), 'Cloud');
        }
        //Last update
        $id = @$this->GetIDForIdent('LastUpdate');
        $this->RegisterVariableString('LastUpdate', 'Letzte Aktualisierung', '', 20);
        if ($id == false) {
            IPS_SetIcon($this->GetIDForIdent('LastUpdate'), 'Clock');
            $this->SetValue('LastUpdate', '-');
        }
    }

    private function SetUpdateWebSpacesTimer(): void
    {
        $milliseconds = $this->ReadPropertyInteger('UpdateInterval') * 1000 * 60;
        $this->SetTimerInterval('UpdateWebSpaces', $milliseconds);
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