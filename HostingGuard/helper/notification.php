<?php

declare(strict_types=1);

trait notification
{
    private function SendImmediateNotification(string $Messages): void
    {
        $this->SendDebug(__FUNCTION__, $Messages, 0);
        //Push
        $webFront = $this->ReadPropertyInteger('WebFront');
        if ($webFront != 0 && @IPS_ObjectExists($webFront)) {
            if ($this->ReadPropertyBoolean('UseImmediateNotificationPush')) {
                $this->SendDebug(__FUNCTION__, 'Use push notification', 0);
                if (!empty($Messages)) {
                    foreach (json_decode($Messages, true) as $message) {
                        @WFC_PushNotification($webFront, 'HostingGuard', "\n" . $message, 'alarm', 0);
                    }
                }
            }
        }
        //Mail
        $smtp = $this->ReadPropertyInteger('SMTP');
        if ($smtp != 0 && @IPS_ObjectExists($smtp)) {
            if ($this->ReadPropertyBoolean('UseImmediateNotificationMail')) {
                $recipients = json_decode($this->ReadPropertyString('MailRecipients'));
                if (!empty($recipients)) {
                    foreach ($recipients as $recipient) {
                        if ($recipient->Use) {
                            $address = $recipient->Address;
                            if (!empty($address) && strlen($address) > 3) {
                                $this->SendDebug(__FUNCTION__, 'Use mail notification', 0);
                                if (!empty($Messages)) {
                                    foreach (json_decode($Messages, true) as $message) {
                                        @SMTP_SendMailEx($smtp, $address, 'HostingGuard', $message);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}