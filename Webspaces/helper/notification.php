<?php

/** @noinspection DuplicatedCode */

declare(strict_types=1);

trait HGWS_notification
{
    public function Notify(bool $OnlyStateChanges): bool
    {
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
        foreach ($webFronts as $webFront) {
            if ($webFront->Use) {
                $id = $webFront->ID;
                if ($id != 0 && @IPS_ObjectExists($id)) {
                    foreach ($stateList as $element) {
                        $notification = true;
                        if ($OnlyStateChanges) {
                            if (!$element->statusChanged) {
                                $notification = false;
                            }
                        }
                        switch ($element->actualStatus) {
                            case 1:
                                $unicode = json_decode('"\u26a0\ufe0f"'); # warning
                                $message = $unicode . ' Schwellenwert überschritten: Webspace ' . $element->name . ' ' . $element->storageQuotaUsedRatio . '% Speicherplatz belegt! (' . $element->timestamp . ')';
                                if (!$this->ReadPropertyBoolean('NotifyThresholdExceeded')) {
                                    $notification = false;
                                }
                                break;

                            case 2:
                                $unicode = json_decode('"\u2757"'); # heavy_exclamation_mark
                                $message = $unicode . ' Kritischer Zustand: Webspace ' . $element->name . ' ' . $element->storageQuotaUsedRatio . '% Speicherplatz belegt! (' . $element->timestamp . ')';
                                if (!$this->ReadPropertyBoolean('NotifyCriticalCondition')) {
                                    $notification = false;
                                }
                                break;

                            default:
                                $unicode = json_decode('"\u2705"'); # white_check_mark
                                $message = $unicode . ' Status OK: Webspace ' . $element->name . ' ' . $element->storageQuotaUsedRatio . '% Speicherplatz belegt! (' . $element->timestamp . ')';
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
                        $notification = true;
                        if ($OnlyStateChanges) {
                            if (!$element->statusChanged) {
                                $notification = false;
                            }
                        }
                        switch ($element->actualStatus) {
                            case 1:
                                $unicode = json_decode('"\u26a0\ufe0f"'); # warning
                                $message = $unicode . ' Schwellenwert überschritten: Webspace ' . $element->name . ' ' . $element->storageQuotaUsedRatio . '% Speicherplatz belegt! (' . $element->timestamp . ')';
                                $sound = 'alarm';
                                if (!$this->ReadPropertyBoolean('NotifyThresholdExceeded')) {
                                    $notification = false;
                                }
                                break;

                            case 2:
                                $unicode = json_decode('"\u2757"'); # heavy_exclamation_mark
                                $message = $unicode . ' Kritischer Zustand: Webspace ' . $element->name . ' ' . $element->storageQuotaUsedRatio . '% Speicherplatz belegt! (' . $element->timestamp . ')';
                                $sound = 'alarm';
                                if (!$this->ReadPropertyBoolean('NotifyCriticalCondition')) {
                                    $notification = false;
                                }
                                break;

                            default:
                                $unicode = json_decode('"\u2705"'); # white_check_mark
                                $message = $unicode . ' Status OK: Webspace ' . $element->name . ' ' . $element->storageQuotaUsedRatio . '% Speicherplatz belegt! (' . $element->timestamp . ')';
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
                        foreach ($stateList as $element) {
                            $notification = true;
                            if ($OnlyStateChanges) {
                                if (!$element->statusChanged) {
                                    $notification = false;
                                }
                            }
                            $interval = $this->ReadPropertyInteger('UpdateInterval');
                            $updateInterval = $interval . ' Minuten';
                            if ($interval == 1) {
                                $updateInterval = $interval . ' Minute';
                            }
                            switch ($element->actualStatus) {
                                case 1:
                                    $unicode = json_decode('"\u26a0\ufe0f"'); # warning
                                    $stateText = $unicode . ' Schwellenwert überschritten: Webspace ' . $element->name . ' ' . $element->storageQuotaUsedRatio . '% Speicherplatz belegt!';
                                    $storageText = 'Zugewiesener Speicher ' . $element->storageQuota . ' MB, davon verbraucht ' . $element->storageUsed . " MB\n";
                                    $storageText .= "\n";
                                    $storageText .= "Bitte löschen Sie zeitnah temporäre Dateien und nicht mehr benötigte Daten oder\n";
                                    $storageText .= "buchen Sie zusätzlichen Speicherplatz im Kundencenter.\n";
                                    $storageText .= "Im Kundencenter haben Sie jederzeit Zugriff auf die Server-Ressourcen in Real-Time.\n";
                                    $storageText .= "\n";
                                    $storageText .= "Wir Informieren Sie, sobald:\n";
                                    $storageText .= "- der Zustand wieder in Ordnung ist\n";
                                    $storageText .= "- der Zustand kritisch wird\n";
                                    $storageText .= "\n";
                                    $storageText .= 'Es kann bis zu ' . $updateInterval . ' dauern, bis die Benachrichtigung versendet wird.';
                                    if (!$this->ReadPropertyBoolean('NotifyThresholdExceeded')) {
                                        $notification = false;
                                    }
                                    break;

                                case 2:
                                    $unicode = json_decode('"\u2757"'); # heavy_exclamation_mark
                                    $stateText = $unicode . ' Kritischer Zustand: Webspace ' . $element->name . ' ' . $element->storageQuotaUsedRatio . '% Speicherplatz belegt!';
                                    $storageText = 'Zugewiesener Speicher ' . $element->storageQuota . ' MB, davon verbraucht ' . $element->storageUsed . " MB\n";
                                    $storageText .= "\n";
                                    $storageText .= "Bitte buchen Sie zusätzlichen Speicherplatz im Kundencenter, um einen Serverausfall zu vermeiden.\n";
                                    $storageText .= "Im Kundencenter sind alle Server-Ressourcen in Real-Time einsehbar.\n";
                                    $storageText .= "\n";
                                    $storageText .= "Wir Informieren Sie, sobald der Zustand wieder in Ordnung ist.\n";
                                    $storageText .= "\n";
                                    $storageText .= 'Es kann bis zu ' . $updateInterval . ' dauern, bis die Benachrichtigung versendet wird.';
                                    if (!$this->ReadPropertyBoolean('NotifyCriticalCondition')) {
                                        $notification = false;
                                    }
                                    break;

                                default:
                                    $unicode = json_decode('"\u2705"'); # white_check_mark
                                    $stateText = $unicode . ' Status OK: Webspace ' . $element->name . ' ' . $element->storageQuotaUsedRatio . '% Speicherplatz belegt!';
                                    $storageText = 'Zugewiesener Speicher ' . $element->storageQuota . ' MB, davon verbraucht ' . $element->storageUsed . ' MB';
                                    if (!$this->ReadPropertyBoolean('Notify')) {
                                        $notification = false;
                                    }
                            }
                            if ($notification) {
                                $subject = 'Hosting Guard WebSpace';
                                $text = "------------------------------------------------------------------------------------------------------------------------------------------\n";
                                $text .= $stateText . "\n";
                                $text .= "\n";
                                $text .= $storageText . "\n";
                                $text .= "------------------------------------------------------------------------------------------------------------------------------------------\n";
                                @SMTP_SendMailEx($id, $address, $subject, $text);
                            }
                        }
                    }
                }
            }
        }
        return true;
    }
}