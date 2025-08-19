<?php
/**
 * CreatioODataAdapter class file
 *
 * Classe d'adaptateur pour la communication avec Creatio via OData
 *
 * PHP Version 7.4
 *
 * @category Adapter
 * @package  Creatio
 * @author   Nicolas Lézoray <nlezoray@gmail.com>
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link     http://api
 */
namespace Nlezoray\Creatio\Adapter;

use Nlezoray\Creatio\Logger\CreatioLogger;

class CreatioODataAdapter implements CreatioAdapterInterface
{
    protected $urlapi;
    protected $logPath;
    protected $cookiePath;
    protected $tokenSecret;
    protected $logger;

    const _TIMEOUT_ = 10;
    const _CONNECTTIMEOUT_ = 10;

    public function __construct(string $env = 'prod')
    {
        $this->logger = new CreatioLogger('C:\\workspace\\api\\var\\logs\\Creatio-oData.log');
        $this->logPath = 'C:\\workspace\\api\\var\\logs\\BPMInt.log';
        $this->cookiePath = 'C:\\Users\\Public\\cookieBPM.txt';
        $this->tokenSecret = 'TOKENSECRET';

        switch ($env) {
            case 'prod':
                $this->urlapi = 'https://yoursite.creatio.com';
                break;
            case 'dev':
                $this->urlapi = 'https://dev-yoursite.creatio.com';
                break;
        }
    }

    public function authentification(): ?bool
    {
        $this->logger->log(date('Y-m-d H:i:s') . "Démarrage authentification oData...");
        $targetUrl = $this->urlapi . '/ServiceModel/AuthService.svc/Login';
        $post = [
            'UserName' => 'UserName',
            'UserPassword' => 'UserPassword',
            'IsDebug' => true
        ];

        $postString = json_encode($post);
        $headers = [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($postString)
        ];

        $callback = function () use ($targetUrl, $headers, $postString) {
            $ch = curl_init($targetUrl);
            curl_setopt_array($ch, [
                CURLOPT_COOKIEJAR => $this->cookiePath,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => $postString,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_SSL_VERIFYPEER => 0,
                CURLOPT_TIMEOUT => self::_TIMEOUT_,
                CURLOPT_CONNECTTIMEOUT => self::_CONNECTTIMEOUT_,
                CURLOPT_HTTPHEADER => $headers,
            ]);

            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            return [$result, $httpCode];
        };

        $result = $this->executeWithRetry($callback, 3, [
            'method' => 'POST',
            'url'    => $targetUrl,
            'body'   => $postString
        ]);

        return $result !== false ? true : false;
    }

    public function get(string $collection, array $apiQuery, int $nbresult = 10000, $orderby = false, int $skip = 0)
    {
        $token = $this->getCookieToken();
        $targetUrl = $this->urlapi . '/0/ServiceModel/EntityDataService.svc/' . $collection;
        //$this->logger->log(date('Y-m-d H:i:s') . " - Début appel GET vers : $targetUrl");

        if (count($apiQuery) > 0) {
            $targetUrl .= '?' . implode('&', array_map(function ($item) {
                return $item[0] . '=' . $item[1];
            }, array_map(null, array_keys($apiQuery), $apiQuery)));
        }

        if ($orderby !== false) {
            $targetUrl .= '&$orderby=' . $orderby . '%20desc';
        }

        $targetUrl .= '&$top=' . $nbresult . '&$skip=' . $skip;

        $headers = [
            'Content-Type: application/json;odata=verbose',
            'Accept: application/json;odata=verbose',
            'BPMCSRF:' . $token,
            'ForceUseSession:true',
            'Connection:keep-alive'
        ];

        $callback = function () use ($targetUrl, $headers) {
            $verbose = fopen($this->logPath, 'w+');
            $ch = curl_init();

            curl_setopt_array($ch, [
                CURLOPT_URL => $targetUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => "GET",
                CURLOPT_COOKIEFILE => $this->cookiePath,
                CURLOPT_ENCODING => '',
                CURLOPT_VERBOSE => true,
                CURLOPT_STDERR => $verbose,
                CURLOPT_TIMEOUT => self::_TIMEOUT_,
                CURLOPT_CONNECTTIMEOUT => self::_CONNECTTIMEOUT_,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_SSL_VERIFYPEER => 0
            ]);

            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            curl_close($ch);
            fclose($verbose);

            return [$result, $httpCode];
        };

        return $this->executeWithRetry($callback, 3, [
            'method' => 'GET',
            'url'    => $targetUrl
        ]);
    }

    public function post(string $collection, array $tabObject)
    {
        $token = $this->getCookieToken();
        $targetUrl = $this->urlapi . "/0/ServiceModel/EntityDataService.svc/" . $collection . "/";
        $postData = json_encode($tabObject);

        $headers = array(
            'Content-Type: application/json;odata=verbose',
            'Accept: application/json;odata=verbose',
            'BPMCSRF:' . $token,
            'ForceUseSession:true',
            'Connection:keep-alive',
            'Content-Length: ' . strlen($postData)
        );

        $callback = function () use ($targetUrl, $headers, $postData) {
            $verbose = fopen($this->logPath, 'w+');
            $ch = curl_init($targetUrl);

            curl_setopt_array($ch, array(
                CURLOPT_COOKIEFILE => $this->cookiePath,
                CURLOPT_COOKIEJAR => $this->cookiePath,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => self::_CONNECTTIMEOUT_,
                CURLOPT_TIMEOUT => self::_TIMEOUT_,
                CURLOPT_POSTFIELDS => $postData,
                CURLOPT_VERBOSE => true,
                CURLOPT_STDERR => $verbose,
                CURLOPT_ENCODING => '',
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_SSL_VERIFYPEER => 0,
                CURLOPT_HTTPHEADER => $headers
            ));

            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            curl_close($ch);
            fclose($verbose);

            return [$result, $httpCode];
        };

        return $this->executeWithRetry($callback, 3, [
            'method' => 'POST',
            'url'    => $targetUrl,
            'body'   => $postData
        ]);
    }

    public function put(string $collection, string $id, array $tabObject)
    {
        $token = $this->getCookieToken();
        $targetUrl = $this->urlapi . "/0/ServiceModel/EntityDataService.svc/" . $collection . "(guid'" . $id . "')";
        $putData = json_encode($tabObject);

        $headers = array(
            'Content-Type: application/json;odata=verbose',
            'Accept: application/json;odata=verbose',
            'BPMCSRF:' . $token,
            'ForceUseSession:true',
            'Connection:keep-alive',
            'Content-Length: ' . strlen($putData)
        );

        $callback = function () use ($targetUrl, $headers, $putData) {
            $verbose = fopen($this->logPath, 'w+');
            $ch = curl_init();

            curl_setopt_array($ch, array(
                CURLOPT_URL => $targetUrl,
                CURLOPT_COOKIEFILE => $this->cookiePath,
                CURLOPT_CUSTOMREQUEST => "PUT",
                CURLOPT_POSTFIELDS => $putData,
                CURLOPT_VERBOSE => true,
                CURLOPT_STDERR => $verbose,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_TIMEOUT => self::_TIMEOUT_,
                CURLOPT_CONNECTTIMEOUT => self::_CONNECTTIMEOUT_,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_SSL_VERIFYPEER => 0,
                CURLOPT_HTTPHEADER => $headers
            ));

            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            curl_close($ch);
            fclose($verbose);

            return [$result, $httpCode];
        };

        return $this->executeWithRetry($callback, 3, [
            'method' => 'PUT',
            'url'    => $targetUrl,
            'body'   => $putData
        ]);
    }

    public function delete(string $collection, string $id)
    {
        $token = $this->getCookieToken();
        $targetUrl = $this->urlapi . "/0/ServiceModel/EntityDataService.svc/" . $collection . "(guid'" . $id . "')";

        $headers = array(
            'Content-Type: application/json;odata=verbose',
            'Accept: application/json;odata=verbose',
            'BPMCSRF:' . $token,
            'ForceUseSession:true',
            'Connection:keep-alive'
        );

        $callback = function () use ($targetUrl, $headers) {
            $verbose = fopen($this->logPath, 'w+');
            $ch = curl_init();

            curl_setopt_array($ch, array(
                CURLOPT_URL => $targetUrl,
                CURLOPT_COOKIEFILE => $this->cookiePath,
                CURLOPT_CUSTOMREQUEST => "DELETE",
                CURLOPT_VERBOSE => true,
                CURLOPT_STDERR => $verbose,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_TIMEOUT => self::_TIMEOUT_,
                CURLOPT_CONNECTTIMEOUT => self::_CONNECTTIMEOUT_,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_SSL_VERIFYPEER => 0,
                CURLOPT_HTTPHEADER => $headers
            ));

            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            curl_close($ch);
            fclose($verbose);

            return [$result, $httpCode];
        };

        return $this->executeWithRetry($callback, 3, [
            'method' => 'DELETE',
            'url'    => $targetUrl
        ]);
    }

    private function executeWithRetry(callable $callback, int $maxAttempts = 3, array $context = [])
        {
        $attempts = 0;
        $result = false;
        $httpCode = 0;

        do {
            $attempts++;
            try {
                list($result, $httpCode) = $callback();            

                if ($result !== false || $httpCode !== 0) {
                    break;
                }
            } catch (\Throwable $e) {
                file_put_contents($this->logPath, date('Y-m-d H:i:s') . ' - Erreur HTTP Creatio: ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
                return false;
            }
        } while ($attempts < $maxAttempts);

        return ($result !== false) ? $result : false;
    }

    public function getCookieToken()
    {
        $cookieContent = file_get_contents($this->cookiePath);
        $lines = explode("\n", $cookieContent);

        foreach ($lines as $line) {
            if (strpos($line, "BPMCSRF") !== false) {
                return trim(substr($line, strpos($line, "BPMCSRF") + strlen("BPMCSRF")));
            }
        }

        return false;
    }
}
