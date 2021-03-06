<?php

/** @noinspection DuplicatedCode */
/** @noinspection PhpUnused */

declare(strict_types=1);

include_once __DIR__ . '/../libs/hostingAPI.php';
include_once __DIR__ . '/helper/autoload.php';

class HostingGuardCertificates extends IPSModule
{
    //Helper
    use HG_hostingAPI;
    use HGCA_notification;

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
        $this->SetDailyReportTimer();
        $this->UpdateData(false);
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
                $formData['elements'][5]['items'][0]['values'][] = [
                    'Use'   => $webFront->Use,
                    'ID'    => $webFront->ID,
                    'Name'  => IPS_GetName($webFront->ID)];
            }
        }
        $mobileDevices = json_decode($this->ReadPropertyString('MobileDeviceNotification'));
        if (!empty($mobileDevices)) {
            foreach ($mobileDevices as $mobileDevice) {
                $formData['elements'][5]['items'][1]['values'][] = [
                    'Use'   => $mobileDevice->Use,
                    'ID'    => $mobileDevice->ID,
                    'Name'  => IPS_GetName($mobileDevice->ID)];
            }
        }
        $recipients = json_decode($this->ReadPropertyString('MailNotification'));
        if (!empty($recipients)) {
            foreach ($recipients as $recipient) {
                $formData['elements'][5]['items'][2]['values'][] = [
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

    public function TriggerDailyReport(): void
    {
        $this->SendDebug(__FUNCTION__, 'Methode wird ausgeführt', 0);
        $this->SetDailyReportTimer();
        $this->WriteAttributeString('StateList', '[]');
        $this->UpdateData(true);
    }

    public function UpdateData(bool $DailyReport): bool
    {
        $this->SendDebug(__FUNCTION__, 'Methode wird ausgeführt', 0);
        if ($this->CheckMaintenanceMode()) {
            return false;
        }
        if ($this->GetStatus() != 102) {
            return false;
        }
        $this->SetTimerInterval('UpdateData', $this->ReadPropertyInteger('UpdateInterval') * 60 * 60 * 1000);
        $stateList = json_decode($this->ReadAttributeString('StateList'), true);
        $certificates = json_decode($this->GetCertificates(), true);
        if (empty($certificates)) {
            return false;
        }
        $timestamp = (string) date('d.m.Y, H:i:s');
        if (array_key_exists('response', $certificates)) {
            $response = $certificates['response'];
            if (array_key_exists('data', $response)) {
                $data = $response['data'];
                if (!empty($data)) {
                    foreach ($data as $dataElement) {
                        $id = '';
                        if (array_key_exists('id', $dataElement)) {
                            $id = (string) $dataElement['id'];
                        }
                        $commonName = '-';
                        if (array_key_exists('commonName', $dataElement)) {
                            $commonName = (string) $dataElement['commonName'];
                        }
                        $certificateStatus = '';
                        if (array_key_exists('status', $dataElement)) {
                            $certificateStatus = (string) $dataElement['status'];
                        }
                        $endDate = '';
                        if (array_key_exists('endDate', $dataElement)) {
                            $endDate = (string) $dataElement['endDate'];
                        }
                        $product = '';
                        if (array_key_exists('product', $dataElement)) {
                            $product = (string) $dataElement['product'];
                        }
                        $autoRenew = '';
                        if (array_key_exists('autoRenew', $dataElement)) {
                            $autoRenew = json_encode(boolval($dataElement['autoRenew']));
                        }
                        $orderStatus = '';
                        if (array_key_exists('orderStatus', $dataElement)) {
                            $orderStatus = (string) $dataElement['orderStatus'];
                        }
                        if ($id != '') {
                            $status = 0; #ok
                            $daysLeft = '';
                            if (!empty($endDate)) {
                                $today = new DateTime(date('Y-m-d'));
                                $target = new DateTime(substr($endDate, 0, 10));
                                $interval = $today->diff($target);
                                $daysLeft = $interval->format('%R%a');
                            }
                            if (!empty($daysLeft)) {
                                if (((int) $daysLeft <= intval($this->ReadPropertyInteger('ThresholdExceeded'))) && ((int) $daysLeft > intval($this->ReadPropertyInteger('CriticalCondition')))) {
                                    $status = 1; #threshold exceeded
                                }
                                if ((int) $daysLeft <= $this->ReadPropertyInteger('CriticalCondition')) {
                                    $status = 2; #warning
                                }
                            }
                            if ($certificateStatus == 'revoked' || $certificateStatus != 'active') {
                                $status = 3; #revoked
                            }
                            $key = array_search($id, array_column($stateList, 'id'));
                            //certificate doesn't exist, add to state list
                            if ($key === false) {
                                switch ($status) {
                                    case 1: #threshold exceeded
                                    case 2: #warning
                                    case 3: #revoked
                                        $statusChanged = true;
                                        break;

                                    default: #ok
                                        $statusChanged = false;
                                }
                                array_push($stateList, [
                                    'id'                => $id,
                                    'commonName'        => $commonName,
                                    'certificateStatus' => $certificateStatus,
                                    'endDate'           => $endDate,
                                    'daysLeft'          => $daysLeft,
                                    'product'           => $product,
                                    'autoRenew'         => $autoRenew,
                                    'orderStatus'       => $orderStatus,
                                    'actualStatus'      => $status,
                                    'lastStatus'        => $status,
                                    'statusChanged'     => $statusChanged,
                                    'timestamp'         => $timestamp]);
                            }
                            //certificate already exists
                            if ($key !== false) {
                                $lastStatus = $stateList[$key]['actualStatus'];
                                $statusChanged = false;
                                if ($status != $lastStatus) {
                                    $statusChanged = true;
                                }
                                $stateList[$key] = [
                                    'id'                => $id,
                                    'commonName'        => $commonName,
                                    'certificateStatus' => $certificateStatus,
                                    'endDate'           => $endDate,
                                    'daysLeft'          => $daysLeft,
                                    'product'           => $product,
                                    'autoRenew'         => $autoRenew,
                                    'orderStatus'       => $orderStatus,
                                    'actualStatus'      => $status,
                                    'lastStatus'        => $lastStatus,
                                    'statusChanged'     => $statusChanged,
                                    'timestamp'         => $timestamp];
                            }
                            array_multisort(array_column($stateList, 'daysLeft'), SORT_ASC, $stateList);
                            if (!empty($stateList)) {
                                foreach ($stateList as $key => $element) {
                                    if ($element['certificateStatus'] == 'revoked') {
                                        array_push($stateList, $stateList[$key]);
                                        unset($stateList[$key]);
                                    }
                                }
                            }
                            $stateList = array_values($stateList);
                            $this->WriteAttributeString('StateList', json_encode($stateList));
                        }
                    }
                }
            }
        }
        $string = "<table style='width: 100%; border-collapse: collapse;'>";
        $string .= '<tr><td><b>Status</b></td><td><b>Name</b></td><td><b>Zertifikat</b></td><td><b>Tage</b></td><td><b>Verlängerung</b></td><td><b>Gültig bis</b></td><td><b>Produkt</b></td><td><b>Auftragsstatus</b></td><td><b>Letzte Aktualisierung</b></td></tr>';
        $stateList = json_decode($this->ReadAttributeString('StateList'), true);
        if (!empty($stateList)) {
            foreach ($stateList as $key => $element) {
                $actualStatus = $element['actualStatus'];
                switch ($actualStatus) {
                    case 1: #warning
                        $unicode = json_decode('"\u26a0\ufe0f"'); # warning
                        if (!$this->ReadPropertyBoolean('DisplayThresholdExceeded')) {
                            continue 2;
                        }
                        break;

                    case 2: #critical condition
                        $unicode = json_decode('"\u2757"'); # heavy_exclamation_mark
                        if (!$this->ReadPropertyBoolean('DisplayCriticalCondition')) {
                            continue 2;
                        } else {
                            $daysLeft = $element['daysLeft'];
                            $maxDays = $this->ReadPropertyInteger('DisplayCriticalConditionDays');
                            if ($daysLeft < $maxDays) {
                                continue 2;
                            }
                        }
                    break;

                    case 3: #revoked
                        $unicode = json_decode('"\ud83d\udeab"'); # no_entry_sign
                        if (!$this->ReadPropertyBoolean('DisplayRevoked')) {
                            continue 2;
                        }
                    break;

                    default: #ok
                        $unicode = json_decode('"\u2705"'); # white_check_mark
                        if (!$this->ReadPropertyBoolean('DisplayNormalState')) {
                            continue 2;
                        }
                }
                $string .= '<tr><td>' . $unicode . '</td><td>' . $element['commonName'] . '</td><td>' . $element['certificateStatus'] . '</td><td>' . $element['daysLeft'] . '</td><td>' . $element['autoRenew'] . '</td><td>' . $element['endDate'] . '</td><td>' . $element['product'] . '</td><td>' . $element['orderStatus'] . '</td><td>' . $timestamp . '</td></tr>';
            }
        }
        $string .= '</table>';
        $this->SetValue('StateList', $string);
        $this->Notify($DailyReport);
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
        $this->RegisterPropertyInteger('ThresholdExceeded', 45);
        $this->RegisterPropertyInteger('CriticalCondition', 20);
        $this->RegisterPropertyInteger('UpdateInterval', 6);
        $this->RegisterPropertyBoolean('DisplayCriticalCondition', true);
        $this->RegisterPropertyInteger('DisplayCriticalConditionDays', -7);
        $this->RegisterPropertyBoolean('DisplayThresholdExceeded', true);
        $this->RegisterPropertyBoolean('DisplayNormalState', true);
        $this->RegisterPropertyBoolean('DisplayRevoked', true);
        $this->RegisterPropertyString('WebFrontNotification', '[]');
        $this->RegisterPropertyString('MobileDeviceNotification', '[]');
        $this->RegisterPropertyString('MailNotification', '[]');
        $this->RegisterPropertyBoolean('UseImmediateNotificationNormalState', true);
        $this->RegisterPropertyBoolean('UseImmediateNotificationThresholdExceeded', true);
        $this->RegisterPropertyBoolean('UseImmediateNotificationCriticalCondition', true);
        $this->RegisterPropertyInteger('ImmediateNotificationCriticalConditionDays', -7);
        $this->RegisterPropertyBoolean('UseImmediateNotificationRevoked', true);
        $this->RegisterPropertyInteger('ImmediateNotificationRevokedDays', -7);
        $this->RegisterPropertyBoolean('UseImmediateWebFrontNotification', true);
        $this->RegisterPropertyBoolean('UseImmediateMobileDeviceNotification', true);
        $this->RegisterPropertyBoolean('UseImmediateMailNotification', true);
        $this->RegisterPropertyBoolean('UseDailyNotificationNormalState', false);
        $this->RegisterPropertyBoolean('UseDailyNotificationThresholdExceeded', true);
        $this->RegisterPropertyBoolean('UseDailyNotificationCriticalCondition', true);
        $this->RegisterPropertyInteger('DailyNotificationCriticalConditionDays', -7);
        $this->RegisterPropertyBoolean('UseDailyNotificationRevoked', true);
        $this->RegisterPropertyInteger('DailyNotificationRevokedDays', -7);
        $this->RegisterPropertyBoolean('UseDailyWebFrontNotification', true);
        $this->RegisterPropertyBoolean('UseDailyMobileDeviceNotification', true);
        $this->RegisterPropertyBoolean('UseDailyMailNotification', true);
        $this->RegisterPropertyString('DailyReportTime', '{"hour":7,"minute":0,"second":0}');
    }

    private function RegisterVariables(): void
    {
        $id = @$this->GetIDForIdent('StateList');
        $this->RegisterVariableString('StateList', 'SSL Zertifikate', 'HTMLBox', 10);
        if ($id == false) {
            IPS_SetIcon($this->GetIDForIdent('StateList'), 'Key');
        }
    }

    private function RegisterTimers(): void
    {
        $this->RegisterTimer('UpdateData', 0, 'HGCA_UpdateData(' . $this->InstanceID . ', false);');
        $this->RegisterTimer('DailyReport', 0, 'HGCA_TriggerDailyReport(' . $this->InstanceID . ');');
    }

    private function DisableTimers(): void
    {
        $this->SetTimerInterval('UpdateData', 0);
        $this->SetTimerInterval('DailyReport', 0);
    }

    private function SetDailyReportTimer(): void
    {
        $this->SendDebug(__FUNCTION__, 'Funktion wird ausgeführt', 0);
        $time = json_decode($this->ReadPropertyString('DailyReportTime'));
        $hour = $time->hour;
        $minute = $time->minute;
        $second = $time->second;
        $definedTime = $hour . ':' . $minute . ':' . $second;
        if (time() >= strtotime($definedTime)) {
            $timestamp = mktime($hour, $minute, $second, (int) date('n'), (int) date('j') + 1, (int) date('Y'));
        } else {
            $timestamp = mktime($hour, $minute, $second, (int) date('n'), (int) date('j'), (int) date('Y'));
        }
        $interval = ($timestamp - time()) * 1000;
        $this->SendDebug(__FUNCTION__, 'Timer Interval: ' . $interval, 0);
        $this->SetTimerInterval('DailyReport', $interval);
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