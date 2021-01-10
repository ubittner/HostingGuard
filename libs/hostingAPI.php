<?php

/** @noinspection DuplicatedCode */
/** @noinspection PhpUnused */

declare(strict_types=1);

trait HG_hostingAPI
{
    /**
     * Gets all available virtual machines.
     *
     * @return string
     */
    public function GetVirtualMachines(): string
    {
        $endpoint = 'https://secure.hosting.de/api/machine/v1/json/virtualMachinesFind';
        return $this->SendDataToEndpoint($endpoint, 'POST');
    }

    /**
     * Gets all available web spaces.
     *
     * @return string
     */
    public function GetWebSpaces(): string
    {
        $endpoint = 'https://secure.hosting.de/api/webhosting/v1/json/webspacesFind';
        return $this->SendDataToEndpoint($endpoint, 'POST');
    }

    /**
     * Gets all available databases.
     */
    public function GetDatabases(): string
    {
        $endpoint = 'https://secure.hosting.de/api/database/v1/json/databasesFind';
        return $this->SendDataToEndpoint($endpoint, 'POST');
    }

    /**
     * Gets all available certificates.
     * @return string
     */
    public function GetCertificates(): string
    {
        $endpoint = 'https://secure.hosting.de/api/ssl/v1/json/certificatesFind';
        return $this->SendDataToEndpoint($endpoint, 'POST');
    }

    #################### Private

    /**
     * Sends the request to the endpoint of the hosting.de API.
     *
     * @param string $Endpoint
     * @param string $CustomRequest
     * @return string
     */
    private function SendDataToEndpoint(string $Endpoint, string $CustomRequest): string
    {
        $this->SendDebug(__FUNCTION__, 'Endpoint: ' . $Endpoint, 0);
        $this->SendDebug(__FUNCTION__, 'CustomRequest: ' . $CustomRequest, 0);
        $body = '';
        $apiKey = $this->ReadPropertyString('ApiKey');
        if (empty($apiKey)) {
            return $body;
        }
        $postfields = json_encode(['authToken' => $apiKey, 'limit' => 100]);
        $timeout = round($this->ReadPropertyInteger('Timeout') / 1000);
        //Send data to endpoint
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST   => $CustomRequest,
            CURLOPT_URL             => $Endpoint,
            CURLOPT_HEADER          => true,
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_FAILONERROR     => true,
            CURLOPT_CONNECTTIMEOUT  => $timeout,
            CURLOPT_TIMEOUT         => 60,
            CURLOPT_POSTFIELDS      => $postfields]);
        $response = curl_exec($ch);
        if (!curl_errno($ch)) {
            switch ($http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE)) {
                case 200:  # OK
                    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
                    $header = substr($response, 0, $header_size);
                    $body = substr($response, $header_size);
                    $this->SendDebug(__FUNCTION__, 'Header: ' . $header, 0);
                    $this->SendDebug(__FUNCTION__, 'Body: ' . $body, 0);
                    //Check for errors
                    $errorCode = 0;
                    $errorText = 'Es ist ein Fehler aufgetreten!';
                    $errors = json_decode($body, true);
                    if (array_key_exists('errors', $errors)) {
                        $errors = $errors['errors'];
                        if (!empty($errors)) {
                            foreach ($errors as $error) {
                                if (array_key_exists('code', $error)) {
                                    $errorCode = $error['code'];
                                }
                                if (array_key_exists('text', $error)) {
                                    $errorText = $error['text'];
                                }
                            }
                            $message = 'Hosting Guard ID ' . $this->InstanceID . ', Fehler: ' . $errorCode . ', ' . $errorText;
                            $this->SendDebug(__FUNCTION__, $message, 0);
                            $this->LogMessage($message, KL_ERROR);
                        }
                    }
                    break;

                default:
                    $this->SendDebug(__FUNCTION__, 'HTTP Code: ' . $http_code, 0);
            }
        } else {
            $error_msg = curl_error($ch);
            $this->SendDebug(__FUNCTION__, 'An error has occurred: ' . json_encode($error_msg), 0);
            $this->LogMessage('An error has occurred: ' . json_encode($error_msg), KL_ERROR);
        }
        curl_close($ch);
        return $body;
    }
}