<?php

/** @noinspection PhpUnused */

declare(strict_types=1);

include_once __DIR__ . '/helper/autoload.php';

class HostingGuard extends IPSModule
{
    //Helper
    use hostingAPI;
    use notification;

    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->RegisterProperties();
        $this->RegisterVariables();
        $this->RegisterTimers();
        $this->RegisterAttributes();
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
        $this->ResetImmediateNotificationLimit();
        if (!$this->ValidateConfiguration()) {
            $this->DisableTimers();
        }
        $this->UpdateData();
        $this->SetTimer_UpdateData();
        $this->SetTimer_ResetImmediateNotificationLimit();
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
        $this->CheckData('WebSpaces');
        $this->CheckData('Databases');
    }

    public function ResetImmediateNotificationLimit(): void
    {
        $this->WriteAttributeString('ImmediateNotificationList', '[]');
        $this->SetTimer_ResetImmediateNotificationLimit();
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
        $this->RegisterPropertyInteger('WebSpaceThresholdExceeded', 80);
        $this->RegisterPropertyInteger('WebSpaceCriticalCondition', 90);
        $this->RegisterPropertyInteger('DatabaseThresholdExceeded', 60);
        $this->RegisterPropertyInteger('DatabaseCriticalCondition', 80);
        $this->RegisterPropertyInteger('WebFront', 0);
        $this->RegisterPropertyInteger('SMTP', 0);
        $this->RegisterPropertyString('MailRecipients', '[]');
        $this->RegisterPropertyBoolean('UseImmediateNotificationThresholdExceeded', true);
        $this->RegisterPropertyBoolean('UseImmediateNotificationCriticalCondition', true);
        $this->RegisterPropertyBoolean('UseImmediateNotificationLimit', true);
        $this->RegisterPropertyString('ResetImmediateNotificationLimitTime', '{"hour":7,"minute":0,"second":0}');
        $this->RegisterPropertyBoolean('UseImmediateNotificationPush', true);
        $this->RegisterPropertyBoolean('UseImmediateNotificationMail', true);
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

    private function RegisterTimers(): void
    {
        $this->RegisterTimer('UpdateData', 0, 'HG_UpdateData(' . $this->InstanceID . ');');
        $this->RegisterTimer('ResetImmediateNotificationLimit', 0, 'HG_ResetImmediateNotificationLimit(' . $this->InstanceID . ');');
    }

    private function DisableTimers(): void
    {
        $this->SetTimerInterval('UpdateData', 0);
        $this->SetTimerInterval('ResetImmediateNotificationLimit', 0);
    }

    private function SetTimer_UpdateData(): void
    {
        $milliseconds = $this->ReadPropertyInteger('UpdateInterval') * 1000 * 60;
        $this->SetTimerInterval('UpdateData', $milliseconds);
    }

    private function SetTimer_ResetImmediateNotificationLimit(): void
    {
        if (!$this->ReadPropertyBoolean('UseImmediateNotificationLimit')) {
            $interval = 0;
        } else {
            $interval = $this->GetInterval('ResetImmediateNotificationLimitTime');
        }
        $this->SetTimerInterval('ResetImmediateNotificationLimit', $interval);
    }

    private function RegisterAttributes(): void
    {
        $this->RegisterAttributeString('ImmediateNotificationList', '[]');
    }

    private function ValidateConfiguration(): bool
    {
        $this->SendDebug(__FUNCTION__, 'Validate configuration', 0);
        $status = 102;
        $result = true;
        //API key
        $apiKey = $this->ReadPropertyString('API_Key');
        if (empty($apiKey)) {
            $status = 201;
            $result = false;
        }
        //Maintenance mode
        $maintenance = $this->CheckMaintenanceMode();
        if ($maintenance) {
            $status = 104;
            $result = false;
        }
        IPS_SetDisabled($this->InstanceID, $maintenance);
        $this->SetStatus($status);
        return $result;
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

    private function GetInterval(string $PropertyName): int
    {
        $now = time();
        $reviewTime = json_decode($this->ReadPropertyString($PropertyName));
        $hour = $reviewTime->hour;
        $minute = $reviewTime->minute;
        $second = $reviewTime->second;
        $definedTime = $hour . ':' . $minute . ':' . $second;
        if (time() >= strtotime($definedTime)) {
            $timestamp = mktime($hour, $minute, $second, (int) date('n'), (int) date('j') + 1, (int) date('Y'));
        } else {
            $timestamp = mktime($hour, $minute, $second, (int) date('n'), (int) date('j'), (int) date('Y'));
        }
        return ($timestamp - $now) * 1000;
    }

    private function CheckData(string $Category): bool
    {
        $this->SetTimer_UpdateData();
        switch ($Category) {
            case 'Databases':
                $categoryData = json_decode($this->GetDatabases(), true);
                if (empty($categoryData)) {
                    return false;
                }
                $categoryName = '(Database)';
                $propertyThresholdExceeded = floatval($this->ReadPropertyInteger('DatabaseThresholdExceeded'));
                $propertyCriticalCondition = floatval($this->ReadPropertyInteger('DatabaseCriticalCondition'));
                $ident = 'DatabasesList';
                break;

            default:
                $categoryData = json_decode($this->GetWebSpaces(), true);
                if (empty($categoryData)) {
                    return false;
                }
                $categoryName = '(WebSpace)';
                $propertyThresholdExceeded = floatval($this->ReadPropertyInteger('WebSpaceThresholdExceeded'));
                $propertyCriticalCondition = floatval($this->ReadPropertyInteger('WebSpaceCriticalCondition'));
                $ident = 'WebSpacesList';
        }
        $table = [];
        if (array_key_exists('response', $categoryData)) {
            $response = $categoryData['response'];
            if (array_key_exists('data', $response)) {
                $data = $response['data'];
                if (!empty($data)) {
                    foreach ($data as $dataElement) {
                        //poolId
                        $poolID = '-';
                        if (array_key_exists('poolId', $dataElement)) {
                            $poolID = $dataElement['poolId'];
                            if (empty($poolID)) {
                                $poolID = '-';
                            }
                        }
                        //name
                        $name = '-';
                        if (array_key_exists('name', $dataElement)) {
                            $name = $dataElement['name'];
                            if (empty($name)) {
                                $name = '-';
                            }
                        }
                        //storageQuota
                        $storageQuota = '-';
                        if (array_key_exists('storageQuota', $dataElement)) {
                            $storageQuota = $dataElement['storageQuota'];
                            if (empty($storageQuota)) {
                                $storageQuota = '-';
                            }
                        }
                        //storageUsed
                        $storageUsed = '-';
                        if (array_key_exists('storageUsed', $dataElement)) {
                            $storageUsed = $dataElement['storageUsed'];
                            if (empty($storageUsed)) {
                                $storageUsed = '-';
                            }
                        }
                        //storageQuotaUsedRatio
                        $storageQuotaUsedRatio = '-';
                        if (array_key_exists('storageQuotaUsedRatio', $dataElement)) {
                            $storageQuotaUsedRatio = $dataElement['storageQuotaUsedRatio'];
                            if (empty($storageQuotaUsedRatio)) {
                                $storageQuotaUsedRatio = '-';
                            }
                        }
                        array_push($table, [
                            'poolId'                => $poolID,
                            'name'                  => $name,
                            'storageQuota'          => $storageQuota,
                            'storageUsed'           => $storageUsed,
                            'storageQuotaUsedRatio' => $storageQuotaUsedRatio]);
                    }
                }
            }
        }
        $string = "<table style='width: 100%; border-collapse: collapse;'>";
        $string .= '<tr><td><b>Status</b></td><td><b>poolId</b></td><td><b>name</b></td><td><b>storageQuota</b></td><td><b>storageUsed</b></td><td><b>storageQuotaUsedRatio</b></td></tr>';
        usort($table, function ($a, $b)
        {
            return $a['poolId'] <=> $b['poolId'];
        });
        $table = array_values($table);
        $messages = [];
        if (!empty($table)) {
            foreach ($table as $tableElement) {
                $notification = false;
                $unicode = json_decode('"\u2705"'); # white_check_mark
                $immediateNotificationList = json_decode($this->ReadAttributeString('ImmediateNotificationList'), true);
                $key = array_search($tableElement['name'], array_column($immediateNotificationList, 'name'));
                //Critical condition
                if ($tableElement['storageQuotaUsedRatio'] >= $propertyCriticalCondition) {
                    $unicode = json_decode('"\u2757"'); # heavy_exclamation_mark
                    if ($key === false) {
                        $notification = true;
                        array_push($immediateNotificationList, ['name' => $tableElement['name'], 'thresholdExceeded' => false, 'criticalCondition' => true]);
                    }
                    if ($key !== false) {
                        $thresholdExceeded = $immediateNotificationList[$key]['thresholdExceeded'];
                        $criticalCondition = $immediateNotificationList[$key]['criticalCondition'];
                        if (!$criticalCondition) {
                            $notification = true;
                        }
                        $immediateNotificationList[$key] = ['name' => $tableElement['name'], 'thresholdExceeded' => $thresholdExceeded, 'criticalCondition' => true];
                    }
                    if (!$this->ReadPropertyBoolean('UseImmediateNotificationLimit')) {
                        $notification = true;
                    }
                    if ($notification) {
                        $message = $unicode . ' Kritischer Zustand für ' . $tableElement['name'] . ' ' . $tableElement['storageQuotaUsedRatio'] . '% ' . $categoryName;
                        array_push($messages, $message);
                    }
                }
                //Threshold exceeded
                if (($tableElement['storageQuotaUsedRatio'] > $propertyThresholdExceeded) && ($tableElement['storageQuotaUsedRatio'] < $propertyCriticalCondition)) {
                    $unicode = json_decode('"\u26a0\ufe0f"'); # warning
                    if ($key === false) {
                        $notification = true;
                        array_push($immediateNotificationList, ['name' => $tableElement['name'], 'thresholdExceeded' => true, 'criticalCondition' => false]);
                    }
                    if ($key !== false) {
                        $thresholdExceeded = $immediateNotificationList[$key]['thresholdExceeded'];
                        $criticalCondition = $immediateNotificationList[$key]['criticalCondition'];
                        if (!$thresholdExceeded) {
                            $notification = true;
                        }
                        $immediateNotificationList[$key] = ['name' => $tableElement['name'], 'thresholdExceeded' => true, 'criticalCondition' => $criticalCondition];
                    }
                    if (!$this->ReadPropertyBoolean('UseImmediateNotificationLimit')) {
                        $notification = true;
                    }
                    if ($notification) {
                        $message = $unicode . ' Schwellenwert überschritten für ' . $tableElement['name'] . ' ' . $tableElement['storageQuotaUsedRatio'] . '% ' . $categoryName;
                        array_push($messages, $message);
                    }
                }
                $string .= '<tr><td>' . $unicode . '</td><td>' . $tableElement['poolId'] . '</td><td>' . $tableElement['name'] . '</td><td>' . $tableElement['storageQuota'] . '</td><td>' . $tableElement['storageUsed'] . '</td><td>' . $tableElement['storageQuotaUsedRatio'] . '</td></tr>';
                $this->SendDebug(__FUNCTION__, json_encode($immediateNotificationList), 0);
                $this->WriteAttributeString('ImmediateNotificationList', json_encode($immediateNotificationList));
            }
        }
        $string .= '</table>';
        $this->SetValue($ident, $string);
        $this->SetValue('LastUpdate', date('d.m.Y H:i:s'));
        if (!empty($messages)) {
            $this->SendImmediateNotification(json_encode($messages));
        }
        return true;
    }
}