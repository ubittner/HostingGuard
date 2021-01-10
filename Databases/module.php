<?php

/** @noinspection DuplicatedCode */
/** @noinspection PhpUnused */

declare(strict_types=1);

include_once __DIR__ . '/../libs/hostingAPI.php';
include_once __DIR__ . '/helper/autoload.php';

class HostingGuardDatabases extends IPSModule
{
    //Helper
    use HG_hostingAPI;
    use HGDB_notification;

    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->RegisterProperties();
        $this->RegisterVariables();
        $this->RegisterTimers();
        $this->RegisterAttributeString('StateList', '[]');
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
        if (!$this->ValidateConfiguration()) {
            $this->DisableTimers();
            return;
        }
        $this->SetResetStateListTimer();
        $this->UpdateData(true);
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
        $webFronts = json_decode($this->ReadPropertyString('WebFrontNotification'));
        if (!empty($webFronts)) {
            foreach ($webFronts as $webFront) {
                $formData['elements'][4]['items'][0]['values'][] = [
                    'Use'   => $webFront->Use,
                    'ID'    => $webFront->ID,
                    'Name'  => IPS_GetName($webFront->ID)];
            }
        }
        $mobileDevices = json_decode($this->ReadPropertyString('MobileDeviceNotification'));
        if (!empty($mobileDevices)) {
            foreach ($mobileDevices as $mobileDevice) {
                $formData['elements'][4]['items'][1]['values'][] = [
                    'Use'   => $mobileDevice->Use,
                    'ID'    => $mobileDevice->ID,
                    'Name'  => IPS_GetName($mobileDevice->ID)];
            }
        }
        $recipients = json_decode($this->ReadPropertyString('MailNotification'));
        if (!empty($recipients)) {
            foreach ($recipients as $recipient) {
                $formData['elements'][4]['items'][2]['values'][] = [
                    'Use'       => $recipient->Use,
                    'ID'        => $recipient->ID,
                    'Name'      => IPS_GetName($recipient->ID),
                    'Recipient' => $recipient->Recipient,
                    'Address'   => $recipient->Address];
            }
        }
        return json_encode($formData);
    }

    public function ShowStateList(): void
    {
        print_r(json_decode($this->ReadAttributeString('StateList'), true));
    }

    public function ResetStateList(): void
    {
        $this->WriteAttributeString('StateList', '[]');
        $this->UpdateData(true);
    }

    public function UpdateData(bool $UseNotification): bool
    {
        if ($this->CheckMaintenanceMode()) {
            return false;
        }
        if ($this->GetStatus() != 102) {
            return false;
        }
        $this->SetTimerInterval('UpdateData', $this->ReadPropertyInteger('UpdateInterval') * 1000 * 60);
        $stateList = json_decode($this->ReadAttributeString('StateList'), true);
        $databases = json_decode($this->GetDatabases(), true);
        if (empty($databases)) {
            return false;
        }
        $timestamp = (string) date('d.m.Y, H:i:s');
        if (array_key_exists('response', $databases)) {
            $response = $databases['response'];
            if (array_key_exists('data', $response)) {
                $data = $response['data'];
                if (!empty($data)) {
                    foreach ($data as $dataElement) {
                        //name
                        $name = '-';
                        if (array_key_exists('name', $dataElement)) {
                            $name = $dataElement['name'];
                        }
                        //storageQuota
                        $storageQuota = intval(0);
                        if (array_key_exists('storageQuota', $dataElement)) {
                            $storageQuota = intval($dataElement['storageQuota']);
                        }
                        //storageUsed
                        $storageUsed = intval(0);
                        if (array_key_exists('storageUsed', $dataElement)) {
                            $storageUsed = intval($dataElement['storageUsed']);
                        }
                        //storageQuotaUsedRatio
                        $storageQuotaUsedRatio = floatval(0);
                        if (array_key_exists('storageQuotaUsedRatio', $dataElement)) {
                            $storageQuotaUsedRatio = floatval($dataElement['storageQuotaUsedRatio']);
                        }
                        if ($name != '-') {
                            $status = 0;
                            $statusChanged = false;
                            if (($storageQuotaUsedRatio > floatval($this->ReadPropertyInteger('ThresholdExceeded'))) && ($storageQuotaUsedRatio < floatval($this->ReadPropertyInteger('CriticalCondition')))) {
                                $status = 1;
                                $statusChanged = true;
                            }
                            if ($storageQuotaUsedRatio >= $this->ReadPropertyInteger('CriticalCondition')) {
                                $status = 2;
                                $statusChanged = true;
                            }
                            $key = array_search($name, array_column($stateList, 'name'));
                            if ($key === false) {
                                array_push($stateList, [
                                    'name'                  => $name,
                                    'storageQuota'          => $storageQuota,
                                    'storageUsed'           => $storageUsed,
                                    'storageQuotaUsedRatio' => $storageQuotaUsedRatio,
                                    'actualStatus'          => $status,
                                    'lastStatus'            => $status,
                                    'statusChanged'         => $statusChanged,
                                    'timestamp'             => (string) date('d.m.Y, H:i:s')]);
                            }
                            if ($key !== false) {
                                $lastStatus = $stateList[$key]['actualStatus'];
                                $statusChanged = false;
                                if ($status != $lastStatus) {
                                    $statusChanged = true;
                                }
                                $stateList[$key] = [
                                    'name'                  => $name,
                                    'storageQuota'          => $storageQuota,
                                    'storageUsed'           => $storageUsed,
                                    'storageQuotaUsedRatio' => $storageQuotaUsedRatio,
                                    'actualStatus'          => $status,
                                    'lastStatus'            => $lastStatus,
                                    'statusChanged'         => $statusChanged,
                                    'timestamp'             => $timestamp];
                            }
                            $this->WriteAttributeString('StateList', json_encode($stateList));
                        }
                    }
                }
            }
        }
        $string = "<table style='width: 100%; border-collapse: collapse;'>";
        $string .= '<tr><td><b>Status</b></td><td><b>Name</b></td><td><b>Zugewiesener Speicher</b></td><td><b>Verbrauchter Speicher</b></td><td><b>Belegter Speicher</b></td><td><b>Letzte Aktualisierung</b></td></tr>';
        $stateList = json_decode($this->ReadAttributeString('StateList'), true);
        if (!empty($stateList)) {
            foreach ($stateList as $key => $element) {
                $actualStatus = $element['actualStatus'];
                switch ($actualStatus) {
                    case 1:
                        $unicode = json_decode('"\u26a0\ufe0f"'); # warning
                    break;

                    case 2:
                        $unicode = json_decode('"\u2757"'); # heavy_exclamation_mark
                    break;

                    default:
                        $unicode = json_decode('"\u2705"'); # white_check_mark
                }
                $string .= '<tr><td>' . $unicode . '</td><td>' . $element['name'] . '</td><td>' . $element['storageQuota'] . ' MB</td><td>' . $element['storageUsed'] . ' MB</td><td>' . $element['storageQuotaUsedRatio'] . ' %</td><td>' . $timestamp . '</td></tr>';
            }
        }
        $string .= '</table>';
        $this->SetValue('StateList', $string);
        if ($UseNotification) {
            $this->Notify(true);
        }
        return true;
    }

    #################### Private

    private function KernelReady(): void
    {
        $this->ApplyChanges();
    }

    private function RegisterProperties(): void
    {
        $this->RegisterPropertyString('ApiKey', '');
        $this->RegisterPropertyInteger('Timeout', 5000);
        $this->RegisterPropertyBoolean('MaintenanceMode', false);
        $this->RegisterPropertyInteger('ThresholdExceeded', 80);
        $this->RegisterPropertyInteger('CriticalCondition', 90);
        $this->RegisterPropertyInteger('UpdateInterval', 10);
        $this->RegisterPropertyString('WebFrontNotification', '[]');
        $this->RegisterPropertyString('MobileDeviceNotification', '[]');
        $this->RegisterPropertyString('MailNotification', '[]');
        $this->RegisterPropertyBoolean('Notify', true);
        $this->RegisterPropertyBoolean('NotifyThresholdExceeded', true);
        $this->RegisterPropertyBoolean('NotifyCriticalCondition', true);
        $this->RegisterPropertyBoolean('UseWebFrontNotification', true);
        $this->RegisterPropertyBoolean('UseMobileDeviceNotification', true);
        $this->RegisterPropertyBoolean('UseMailNotification', true);
        $this->RegisterPropertyString('ResetStateListTime', '{"hour":7,"minute":0,"second":0}');
    }

    private function RegisterVariables(): void
    {
        $id = @$this->GetIDForIdent('StateList');
        $this->RegisterVariableString('StateList', 'Datenbanken', 'HTMLBox', 10);
        if ($id == false) {
            IPS_SetIcon($this->GetIDForIdent('StateList'), 'Database');
        }
    }

    private function RegisterTimers(): void
    {
        $this->RegisterTimer('UpdateData', 0, 'HGDB_UpdateData(' . $this->InstanceID . ', true);');
        $this->RegisterTimer('ResetStateList', 0, 'HGDB_ResetStateList(' . $this->InstanceID . ');');
    }

    private function DisableTimers(): void
    {
        $this->SetTimerInterval('UpdateData', 0);
        $this->SetTimerInterval('ResetStateList', 0);
    }

    private function SetResetStateListTimer(): void
    {
        $now = time();
        $time = json_decode($this->ReadPropertyString('ResetStateListTime'));
        $hour = $time->hour;
        $minute = $time->minute;
        $second = $time->second;
        $definedTime = $hour . ':' . $minute . ':' . $second;
        if (time() >= strtotime($definedTime)) {
            $timestamp = mktime($hour, $minute, $second, (int) date('n'), (int) date('j') + 1, (int) date('Y'));
        } else {
            $timestamp = mktime($hour, $minute, $second, (int) date('n'), (int) date('j'), (int) date('Y'));
        }
        $this->SetTimerInterval('ResetStateList', ($timestamp - $now) * 1000);
    }

    private function ValidateConfiguration(): bool
    {
        $this->SendDebug(__FUNCTION__, 'Validate configuration', 0);
        $status = 102;
        $result = true;
        //API key
        $apiKey = $this->ReadPropertyString('ApiKey');
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
}