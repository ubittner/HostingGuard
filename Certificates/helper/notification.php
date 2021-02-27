<?php

/** @noinspection DuplicatedCode */

declare(strict_types=1);

trait HGCA_notification
{
    public function Notify(bool $DailyReport): bool
    {
        $this->SendDebug(__FUNCTION__, 'Methode wird ausgeführt', 0);
        if ($this->CheckMaintenanceMode()) {
            return false;
        }
        $result1 = $this->SendWebFrontNotification($DailyReport);
        $result2 = $this->SendMobileDeviceNotification($DailyReport);
        $result3 = $this->SendMailNotification($DailyReport);
        if (!$result1 || !$result2 || !$result3) {
            return false;
        }
        return true;
    }

    #################### Private

    private function SendWebFrontNotification(bool $DailyReport): bool
    {
        $this->SendDebug(__FUNCTION__, 'Methode wird ausgeführt', 0);
        if ($this->CheckMaintenanceMode()) {
            return false;
        }
        $webFronts = json_decode($this->ReadPropertyString('WebFrontNotification'));
        if (empty($webFronts)) {
            return false;
        }
        $stateList = json_decode($this->ReadAttributeString('StateList'));
        if (empty($stateList)) {
            return false;
        }
        if (!$DailyReport && !$this->ReadPropertyBoolean('UseImmediateWebFrontNotification')) {
            return false;
        }
        if ($DailyReport && !$this->ReadPropertyBoolean('UseDailyWebFrontNotification')) {
            return false;
        }
        $stateList = array_reverse($stateList);
        foreach ($webFronts as $webFront) {
            if ($webFront->Use) {
                $id = $webFront->ID;
                if ($id != 0 && @IPS_ObjectExists($id)) {
                    foreach ($stateList as $element) {
                        $notification = true;
                        $date = substr($element->endDate, 0, 10);
                        $date = strtotime($date);
                        $date = date('d.m.Y', $date);
                        $time = substr($element->endDate, 11, 8);
                        $daysLeft = $element->daysLeft;
                        if (!$DailyReport) {
                            if (!$element->statusChanged) {
                                continue;
                            }
                            switch ($element->actualStatus) {
                                case 1: #threshold exceeded
                                    $unicode = json_decode('"\u26a0\ufe0f"'); #warning
                                    $message = $unicode . ' Warnung: SSL Zertifikat ' . $element->commonName . ' läuft in ' . (int) $element->daysLeft . ' Tagen am ' . $date . ' um ' . $time . ' ab!';
                                    if (!$this->ReadPropertyBoolean('UseImmediateNotificationThresholdExceeded')) {
                                        $notification = false;
                                    }
                                    break;

                                case 2: #critical condition
                                    $unicode = json_decode('"\u2757"'); #heavy_exclamation_mark
                                    $message = $unicode . ' Kritischer Zustand: SSL Zertifikat ' . $element->commonName . ' läuft in ' . (int) $element->daysLeft . ' Tagen am ' . $date . ' um ' . $time . ' ab!';
                                    $maxDays = $this->ReadPropertyInteger('ImmediateNotificationCriticalConditionDays');
                                    if ($daysLeft < $maxDays) {
                                        $notification = false;
                                    }
                                    if (!$this->ReadPropertyBoolean('UseImmediateNotificationCriticalCondition')) {
                                        $notification = false;
                                    }
                                    break;

                                case 3: #revoked
                                    $unicode = json_decode('"\ud83d\udeab"'); #no_entry_sign
                                    $message = $unicode . ' Zurückgezogen: SSL Zertifikat ' . $element->commonName . ' wurde zurückgezogen!';
                                    $maxDays = $this->ReadPropertyInteger('ImmediateNotificationRevokedDays');
                                    if ($daysLeft < $maxDays) {
                                        $notification = false;
                                    }
                                    if (!$this->ReadPropertyBoolean('UseImmediateNotificationRevoked')) {
                                        $notification = false;
                                    }
                                    break;

                                default: #ok
                                    $unicode = json_decode('"\u2705"'); #white_check_mark
                                    $message = $unicode . ' Status OK: SSL Zertifikat ' . $element->commonName . ' läuft in ' . (int) $element->daysLeft . ' Tagen am ' . $date . ' um ' . $time . ' ab!';
                                    if (!$this->ReadPropertyBoolean('UseImmediateNotificationNormalState')) {
                                        $notification = false;
                                    }
                            }
                        }
                        if ($DailyReport) {
                            switch ($element->actualStatus) {
                                case 1: #threshold exceeded
                                    $unicode = json_decode('"\u26a0\ufe0f"'); #warning
                                    $message = $unicode . ' Warnung: SSL Zertifikat ' . $element->commonName . ' läuft in ' . (int) $element->daysLeft . ' Tagen am ' . $date . ' um ' . $time . ' ab!';
                                    if (!$this->ReadPropertyBoolean('UseDailyNotificationThresholdExceeded')) {
                                        $notification = false;
                                    }
                                    break;

                                case 2: #critical condition
                                    $unicode = json_decode('"\u2757"'); #heavy_exclamation_mark
                                    $message = $unicode . ' Kritischer Zustand: SSL Zertifikat ' . $element->commonName . ' läuft in ' . (int) $element->daysLeft . ' Tagen am ' . $date . ' um ' . $time . ' ab!';
                                    $maxDays = $this->ReadPropertyInteger('DailyNotificationCriticalConditionDays');
                                    if ($daysLeft < $maxDays) {
                                        $notification = false;
                                    }
                                    if (!$this->ReadPropertyBoolean('UseDailyNotificationCriticalCondition')) {
                                        $notification = false;
                                    }
                                    break;

                                case 3: #revoked
                                    $unicode = json_decode('"\ud83d\udeab"'); #no_entry_sign
                                    $message = $unicode . ' Zurückgezogen: SSL Zertifikat ' . $element->commonName . ' wurde zurückgezogen!';
                                    $maxDays = $this->ReadPropertyInteger('DailyNotificationRevokedDays');
                                    if ($daysLeft < $maxDays) {
                                        $notification = false;
                                    }
                                    if (!$this->ReadPropertyBoolean('UseDailyNotificationRevoked')) {
                                        $notification = false;
                                    }
                                    break;

                                default: #ok
                                    $unicode = json_decode('"\u2705"'); #white_check_mark
                                    $message = $unicode . ' Status OK: SSL Zertifikat ' . $element->commonName . ' läuft in ' . (int) $element->daysLeft . ' Tagen am ' . $date . ' um ' . $time . ' ab!';
                                    if (!$this->ReadPropertyBoolean('UseDailyNotificationNormalState')) {
                                        $notification = false;
                                    }
                            }
                        }
                        if ($notification) {
                            @WFC_SendNotification($id, 'Hosting Guard', "\n" . $message, '', 0);
                        }
                    }
                }
            }
        }
        return true;
    }

    private function SendMobileDeviceNotification(bool $DailyReport): bool
    {
        $this->SendDebug(__FUNCTION__, 'Methode wird ausgeführt', 0);
        if ($this->CheckMaintenanceMode()) {
            return false;
        }
        $devices = json_decode($this->ReadPropertyString('MobileDeviceNotification'));
        if (empty($devices)) {
            return false;
        }
        $stateList = json_decode($this->ReadAttributeString('StateList'));
        if (empty($stateList)) {
            return false;
        }
        if (!$DailyReport && !$this->ReadPropertyBoolean('UseImmediateMobileDeviceNotification')) {
            return false;
        }
        if ($DailyReport && !$this->ReadPropertyBoolean('UseDailyMobileDeviceNotification')) {
            return false;
        }
        foreach ($devices as $device) {
            if ($device->Use) {
                $id = $device->ID;
                if ($id != 0 && @IPS_ObjectExists($id)) {
                    foreach ($stateList as $element) {
                        $notification = true;
                        $date = substr($element->endDate, 0, 10);
                        $date = strtotime($date);
                        $date = date('d.m.Y', $date);
                        $time = substr($element->endDate, 11, 8);
                        $daysLeft = $element->daysLeft;
                        $sound = 'alarm';
                        if (!$DailyReport) {
                            if (!$element->statusChanged) {
                                continue;
                            }
                            switch ($element->actualStatus) {
                                case 1: #threshold exceeded
                                    $unicode = json_decode('"\u26a0\ufe0f"'); #warning
                                    $message = $unicode . ' Warnung: SSL Zertifikat ' . $element->commonName . ' läuft in ' . (int) $element->daysLeft . ' Tagen am ' . $date . ' um ' . $time . ' ab!';
                                    if (!$this->ReadPropertyBoolean('UseImmediateNotificationThresholdExceeded')) {
                                        $notification = false;
                                    }
                                    break;

                                case 2: #critical condition
                                    $unicode = json_decode('"\u2757"'); #heavy_exclamation_mark
                                    $message = $unicode . ' Kritischer Zustand: SSL Zertifikat ' . $element->commonName . ' läuft in ' . (int) $element->daysLeft . ' Tagen am ' . $date . ' um ' . $time . ' ab!';
                                    $maxDays = $this->ReadPropertyInteger('ImmediateNotificationCriticalConditionDays');
                                    if ($daysLeft < $maxDays) {
                                        $notification = false;
                                    }
                                    if (!$this->ReadPropertyBoolean('UseImmediateNotificationCriticalCondition')) {
                                        $notification = false;
                                    }
                                    break;

                                case 3: #revoked
                                    $unicode = json_decode('"\ud83d\udeab"'); #no_entry_sign
                                    $message = $unicode . ' Zurückgezogen: SSL Zertifikat ' . $element->commonName . ' wurde zurückgezogen!';
                                    $maxDays = $this->ReadPropertyInteger('ImmediateNotificationRevokedDays');
                                    if ($daysLeft < $maxDays) {
                                        $notification = false;
                                    }
                                    if (!$this->ReadPropertyBoolean('UseImmediateNotificationRevoked')) {
                                        $notification = false;
                                    }
                                    break;

                                default: #ok
                                    $unicode = json_decode('"\u2705"'); #white_check_mark
                                    $message = $unicode . ' Status OK: SSL Zertifikat ' . $element->commonName . ' läuft in ' . (int) $element->daysLeft . ' Tagen am ' . $date . ' um ' . $time . ' ab!';
                                    $sound = '';
                                    if (!$this->ReadPropertyBoolean('UseImmediateNotificationNormalState')) {
                                        $notification = false;
                                    }
                            }
                        }
                        if ($DailyReport) {
                            switch ($element->actualStatus) {
                                case 1: #threshold exceeded
                                    $unicode = json_decode('"\u26a0\ufe0f"'); #warning
                                    $message = $unicode . ' Warnung: SSL Zertifikat ' . $element->commonName . ' läuft in ' . (int) $element->daysLeft . ' Tagen am ' . $date . ' um ' . $time . ' ab!';
                                    if (!$this->ReadPropertyBoolean('UseDailyNotificationThresholdExceeded')) {
                                        $notification = false;
                                    }
                                    break;

                                case 2: #critical condition
                                    $unicode = json_decode('"\u2757"'); #heavy_exclamation_mark
                                    $message = $unicode . ' Kritischer Zustand: SSL Zertifikat ' . $element->commonName . ' läuft in ' . (int) $element->daysLeft . ' Tagen am ' . $date . ' um ' . $time . ' ab!';
                                    $maxDays = $this->ReadPropertyInteger('DailyNotificationCriticalConditionDays');
                                    if ($daysLeft < $maxDays) {
                                        $notification = false;
                                    }
                                    if (!$this->ReadPropertyBoolean('UseDailyNotificationCriticalCondition')) {
                                        $notification = false;
                                    }
                                    break;

                                case 3: #revoked
                                    $unicode = json_decode('"\ud83d\udeab"'); #no_entry_sign
                                    $message = $unicode . ' Zurückgezogen: SSL Zertifikat ' . $element->commonName . ' wurde zurückgezogen!';
                                    $maxDays = $this->ReadPropertyInteger('DailyNotificationRevokedDays');
                                    if ($daysLeft < $maxDays) {
                                        $notification = false;
                                    }
                                    if (!$this->ReadPropertyBoolean('UseDailyNotificationRevoked')) {
                                        $notification = false;
                                    }
                                    break;

                                default: #ok
                                    $unicode = json_decode('"\u2705"'); #white_check_mark
                                    $message = $unicode . ' Status OK: SSL Zertifikat ' . $element->commonName . ' läuft in ' . (int) $element->daysLeft . ' Tagen am ' . $date . ' um ' . $time . ' ab!';
                                    $sound = '';
                                    if (!$this->ReadPropertyBoolean('UseDailyNotificationNormalState')) {
                                        $notification = false;
                                    }
                            }
                        }
                        if ($notification) {
                            @WFC_PushNotification($id, 'Hosting Guard', "\n" . $message, $sound, 0);
                        }
                    }
                }
            }
        }
        return true;
    }

    private function SendMailNotification(bool $DailyReport): bool
    {
        $this->SendDebug(__FUNCTION__, 'Methode wird ausgeführt', 0);
        if ($this->CheckMaintenanceMode()) {
            return false;
        }
        $recipients = json_decode($this->ReadPropertyString('MailNotification'));
        if (empty($recipients)) {
            return false;
        }
        $stateList = json_decode($this->ReadAttributeString('StateList'));
        if (empty($stateList)) {
            return false;
        }
        if (!$DailyReport && !$this->ReadPropertyBoolean('UseImmediateMailNotification')) {
            return false;
        }
        if ($DailyReport && !$this->ReadPropertyBoolean('UseDailyMailNotification')) {
            return false;
        }
        foreach ($recipients as $recipient) {
            if ($recipient->Use) {
                $id = $recipient->ID;
                if ($id != 0 && @IPS_ObjectExists($id)) {
                    $address = $recipient->Address;
                    if (!empty($address) && strlen($address) > 3) {
                        $text = "\n";
                        $notification = false;
                        foreach ($stateList as $element) {
                            $daysLeft = $element->daysLeft;
                            if (!$DailyReport) {
                                if (!$element->statusChanged) {
                                    continue;
                                }
                                switch ($element->actualStatus) {
                                    case 1: #threshold exceeded
                                        if (!$this->ReadPropertyBoolean('UseImmediateNotificationThresholdExceeded')) {
                                            continue 2;
                                        }
                                        break;

                                    case 2: #critical condition
                                        $maxDays = $this->ReadPropertyInteger('ImmediateNotificationCriticalConditionDays');
                                        if ($daysLeft < $maxDays) {
                                            continue 2;
                                        }
                                        if (!$this->ReadPropertyBoolean('UseImmediateNotificationCriticalCondition')) {
                                            continue 2;
                                        }
                                        break;

                                    case 3: #revoked
                                        $maxDays = $this->ReadPropertyInteger('ImmediateNotificationRevokedDays');
                                        if ($daysLeft < $maxDays) {
                                            continue 2;
                                        }
                                        if (!$this->ReadPropertyBoolean('UseImmediateNotificationRevoked')) {
                                            continue 2;
                                        }
                                        break;

                                    default: #ok
                                        if (!$this->ReadPropertyBoolean('UseImmediateNotificationNormalState')) {
                                            continue 2;
                                        }
                                }
                            }
                            if ($DailyReport) {
                                switch ($element->actualStatus) {
                                    case 1: #threshold exceeded
                                        if (!$this->ReadPropertyBoolean('UseDailyNotificationThresholdExceeded')) {
                                            continue 2;
                                        }
                                        break;

                                    case 2: #critical condition
                                        $maxDays = $this->ReadPropertyInteger('DailyNotificationCriticalConditionDays');
                                        if ($daysLeft < $maxDays) {
                                            continue 2;
                                        }
                                        if (!$this->ReadPropertyBoolean('UseDailyNotificationCriticalCondition')) {
                                            continue 2;
                                        }
                                        break;

                                    case 3: #revoked
                                        $maxDays = $this->ReadPropertyInteger('DailyNotificationRevokedDays');
                                        if ($daysLeft < $maxDays) {
                                            continue 2;
                                        }
                                        if (!$this->ReadPropertyBoolean('UseDailyNotificationRevoked')) {
                                            continue 2;
                                        }
                                        break;

                                    default: #ok
                                        if (!$this->ReadPropertyBoolean('UseDailyNotificationNormalState')) {
                                            continue 2;
                                        }
                                }
                            }
                            $notification = true;
                            $interval = $this->ReadPropertyInteger('UpdateInterval');
                            $updateInterval = $interval . ' Stunden';
                            if ($interval == 1) {
                                $updateInterval = $interval . ' Stunde';
                            }
                            $date = substr($element->endDate, 0, 10);
                            $date = strtotime($date);
                            $date = date('d.m.Y', $date);
                            $time = substr($element->endDate, 11, 8);
                            switch ($element->actualStatus) {
                                case 1: #threshold exceeded
                                    $unicode = json_decode('"\u26a0\ufe0f"'); #warning
                                    $text .= "------------------------------------------------------------------------------------------------------------------------------------------\n";
                                    $text .= $unicode . ' Warnung: SSL Zertifikat ' . $element->commonName . ' läuft in ' . (int) $element->daysLeft . ' Tagen am ' . $date . ' um ' . $time . " ab!\n";
                                    $text .= "Zertifikate können im Kundencenter verlängert oder neu erstellt werden.\n";
                                    $text .= "\n";
                                    $text .= "Wir Informieren Sie, sobald:\n";
                                    $text .= "- der Zustand wieder in Ordnung ist oder ein neues Zertifikat ausgestellt wurde\n";
                                    $text .= "- der Zustand kritisch wird und dringender Handlungsbedarf besteht\n";
                                    $text .= "\n";
                                    $text .= 'Es kann bis zu ' . $updateInterval . " dauern, bis die Benachrichtigung versendet wird.\n";
                                    $text .= "------------------------------------------------------------------------------------------------------------------------------------------\n\n";
                                    break;

                                case 2: #critical condition
                                    $unicode = json_decode('"\u2757"'); #heavy_exclamation_mark
                                    $text .= "------------------------------------------------------------------------------------------------------------------------------------------\n";
                                    $text .= $unicode . ' Kritischer Zustand: SSL Zertifikat ' . $element->commonName . ' läuft in ' . (int) $element->daysLeft . ' Tagen am ' . $date . ' um ' . $time . " ab!\n";
                                    $text .= "Zertifikate können im Kundencenter eingesehen, verlängert oder neu erstellt werden.\n";
                                    $text .= "\n";
                                    $text .= "Wir benachrichtigen Sie, sobald der Zustand wieder in Ordnung ist oder \n";
                                    $text .= "ein neues Zertifikat ausgestellt wurde.\n";
                                    $text .= "\n";
                                    $text .= 'Es kann bis zu ' . $updateInterval . " dauern, bis die Benachrichtigung versendet wird.\n";
                                    $text .= "------------------------------------------------------------------------------------------------------------------------------------------\n\n";
                                    break;

                                case 3: #revoked
                                    $unicode = json_decode('"\ud83d\udeab"'); #no_entry_sign
                                    $text .= "------------------------------------------------------------------------------------------------------------------------------------------\n";
                                    $text .= $unicode . ' Zurückgezogen: SSL Zertifikat ' . $element->commonName . " wurde zurückgezogen!\n";
                                    $text .= "Es besteht kein Handlungsbedarf!\n";
                                    $text .= "------------------------------------------------------------------------------------------------------------------------------------------\n\n";
                                    break;

                                default: #ok
                                    $unicode = json_decode('"\u2705"'); #white_check_mark
                                    $text .= "------------------------------------------------------------------------------------------------------------------------------------------\n";
                                    $text .= $unicode . ' Status OK: SSL Zertifikat ' . $element->commonName . ' läuft in ' . (int) $element->daysLeft . ' Tagen am ' . $date . ' um ' . $time . " ab!\n";
                                    $text .= "Es besteht kein Handlungsbedarf!\n";
                                    $text .= "------------------------------------------------------------------------------------------------------------------------------------------\n\n";

                            }
                        }
                        if ($notification) {
                            @SMTP_SendMailEx($id, $address, $recipient->Subject, $text);
                        }
                    }
                }
            }
        }
        return true;
    }
}