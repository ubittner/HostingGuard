<?php

/** @noinspection DuplicatedCode */

declare(strict_types=1);

trait HGCA_notification
{
    public function Notify(bool $OnlyStateChanges): bool
    {
        $this->SendDebug(__FUNCTION__, 'Methode wird ausgeführt', 0);
        if ($this->CheckMaintenanceMode()) {
            return false;
        }
        $result1 = $this->SendWebFrontNotification($OnlyStateChanges);
        $result2 = $this->SendMobileDeviceNotification($OnlyStateChanges);
        $result3 = $this->SendMailNotification($OnlyStateChanges);
        if (!$result1 || !$result2 || !$result3) {
            return false;
        }
        return true;
    }

    #################### Private

    private function SendWebFrontNotification(bool $OnlyStateChanges): bool
    {
        $this->SendDebug(__FUNCTION__, 'Methode wird ausgeführt', 0);
        if ($this->CheckMaintenanceMode()) {
            return false;
        }
        if (!$this->ReadPropertyBoolean('UseWebFrontNotification')) {
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
        $stateList = array_reverse($stateList);
        foreach ($webFronts as $webFront) {
            if ($webFront->Use) {
                $id = $webFront->ID;
                if ($id != 0 && @IPS_ObjectExists($id)) {
                    foreach ($stateList as $element) {
                        if (!$element->notification) {
                            continue;
                        }
                        $notification = true;
                        if ($OnlyStateChanges) {
                            if (!$element->statusChanged) {
                                $notification = false;
                            }
                        }
                        $date = substr($element->endDate, 0, 10);
                        $date = strtotime($date);
                        $date = date('d.m.Y', $date);
                        $time = substr($element->endDate, 11, 8);
                        switch ($element->actualStatus) {
                            case 1:
                                $unicode = json_decode('"\u26a0\ufe0f"'); # warning
                                $message = $unicode . ' Warnung: SSL Zertifikat ' . $element->commonName . ' läuft in ' . (int) $element->daysLeft . ' Tagen am ' . $date . ' um ' . $time . ' ab!';
                                if (!$this->ReadPropertyBoolean('NotifyThresholdExceeded')) {
                                    $notification = false;
                                }
                                break;

                            case 2:
                                $unicode = json_decode('"\u2757"'); # heavy_exclamation_mark
                                $message = $unicode . ' Kritischer Zustand: SSL Zertifikat ' . $element->commonName . ' läuft in ' . (int) $element->daysLeft . ' Tagen am ' . $date . ' um ' . $time . ' ab!';
                                if (!$this->ReadPropertyBoolean('NotifyCriticalCondition')) {
                                    $notification = false;
                                }
                                break;

                            default:
                                $unicode = json_decode('"\u2705"'); # white_check_mark
                                $message = $unicode . ' Status OK: SSL Zertifikat ' . $element->commonName . ' läuft in ' . (int) $element->daysLeft . ' Tagen am ' . $date . ' um ' . $time . ' ab!';
                                if (!$this->ReadPropertyBoolean('Notify')) {
                                    $notification = false;
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

    private function SendMobileDeviceNotification(bool $OnlyStateChanges): bool
    {
        $this->SendDebug(__FUNCTION__, 'Methode wird ausgeführt', 0);
        if ($this->CheckMaintenanceMode()) {
            return false;
        }
        if (!$this->ReadPropertyBoolean('UseMobileDeviceNotification')) {
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
        foreach ($devices as $device) {
            if ($device->Use) {
                $id = $device->ID;
                if ($id != 0 && @IPS_ObjectExists($id)) {
                    foreach ($stateList as $element) {
                        if (!$element->notification) {
                            continue;
                        }
                        $notification = true;
                        if ($OnlyStateChanges) {
                            if (!$element->statusChanged) {
                                $notification = false;
                            }
                        }
                        $date = substr($element->endDate, 0, 10);
                        $date = strtotime($date);
                        $date = date('d.m.Y', $date);
                        $time = substr($element->endDate, 11, 8);
                        switch ($element->actualStatus) {
                            case 1:
                                $unicode = json_decode('"\u26a0\ufe0f"'); # warning
                                $message = $unicode . ' Warnung: SSL Zertifikat ' . $element->commonName . ' läuft in ' . (int) $element->daysLeft . ' Tagen am ' . $date . ' um ' . $time . ' ab!';
                                $sound = 'alarm';
                                if (!$this->ReadPropertyBoolean('NotifyThresholdExceeded')) {
                                    $notification = false;
                                }
                                break;

                            case 2:
                                $unicode = json_decode('"\u2757"'); # heavy_exclamation_mark
                                $message = $unicode . ' Kritischer Zustand: SSL Zertifikat ' . $element->commonName . ' läuft in ' . (int) $element->daysLeft . ' Tagen am ' . $date . ' um ' . $time . ' ab!';
                                $sound = 'alarm';
                                if (!$this->ReadPropertyBoolean('NotifyCriticalCondition')) {
                                    $notification = false;
                                }
                                break;

                            default:
                                $unicode = json_decode('"\u2705"'); # white_check_mark
                                $message = $unicode . ' Status OK: SSL Zertifikat ' . $element->commonName . ' läuft in ' . (int) $element->daysLeft . ' Tagen am ' . $date . ' um ' . $time . ' ab!';
                                $sound = '';
                                if (!$this->ReadPropertyBoolean('Notify')) {
                                    $notification = false;
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

    private function SendMailNotification(bool $OnlyStateChanges): bool
    {
        $this->SendDebug(__FUNCTION__, 'Methode wird ausgeführt', 0);
        if ($this->CheckMaintenanceMode()) {
            return false;
        }
        $recipients = json_decode($this->ReadPropertyString('MailNotification'));
        if (empty($recipients)) {
            return false;
        }
        if (!$this->ReadPropertyBoolean('UseMailNotification')) {
            return false;
        }
        $stateList = json_decode($this->ReadAttributeString('StateList'));
        if (empty($stateList)) {
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
                            if (!$element->notification) {
                                continue;
                            }
                            if ($OnlyStateChanges) {
                                if (!$element->statusChanged) {
                                    continue;
                                }
                            }
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
                                case 1:
                                    if ($this->ReadPropertyBoolean('NotifyThresholdExceeded')) {
                                        $notification = true;
                                        $unicode = json_decode('"\u26a0\ufe0f"'); # warning
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
                                    }
                                    break;

                                case 2:
                                    if ($this->ReadPropertyBoolean('NotifyCriticalCondition')) {
                                        $notification = true;
                                        $unicode = json_decode('"\u2757"'); # heavy_exclamation_mark
                                        $text .= "------------------------------------------------------------------------------------------------------------------------------------------\n";
                                        $text .= $unicode . ' Kritischer Zustand: SSL Zertifikat ' . $element->commonName . ' läuft in ' . (int) $element->daysLeft . ' Tagen am ' . $date . ' um ' . $time . " ab!\n";
                                        $text .= "Zertifikate können im Kundencenter eingesehen, verlängert oder neu erstellt werden.\n";
                                        $text .= "\n";
                                        $text .= "Wir benachrichtigen Sie, sobald der Zustand wieder in Ordnung ist oder \n";
                                        $text .= "ein neues Zertifikat ausgestellt wurde.\n";
                                        $text .= "\n";
                                        $text .= 'Es kann bis zu ' . $updateInterval . " dauern, bis die Benachrichtigung versendet wird.\n";
                                        $text .= "------------------------------------------------------------------------------------------------------------------------------------------\n\n";
                                    }
                                    break;

                                default:
                                    if ($this->ReadPropertyBoolean('Notify')) {
                                        $notification = true;
                                        $unicode = json_decode('"\u2705"'); # white_check_mark
                                        $text .= "------------------------------------------------------------------------------------------------------------------------------------------\n";
                                        $text .= $unicode . ' Status OK: SSL Zertifikat ' . $element->commonName . ' läuft in ' . (int) $element->daysLeft . ' Tagen am ' . $date . ' um ' . $time . " ab!\n";
                                        $text .= "Es besteht kein Handlungsbedarf!\n";
                                        $text .= "------------------------------------------------------------------------------------------------------------------------------------------\n\n";
                                    }
                            }
                        }
                        if ($notification) {
                            @SMTP_SendMailEx($id, $address, 'Hosting Guard SSL Zertifikat', $text);
                        }
                    }
                }
            }
        }
        return true;
    }
}