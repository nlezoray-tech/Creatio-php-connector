<?php
/**
 * CreatioOAuthAdapter class file
 *
 * Adapter class for communication with Creatio via oAuth
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

use GuzzleHttp\Client;
use Nlezoray\Creatio\Adapter\CreatioAdapterInterface;
use Nlezoray\Creatio\Logger\CreatioLogger;

class CreatioOAuthAdapter implements CreatioAdapterInterface
{
    private string $urlapi;
    private string $oauthurl;
    private string $clientId;
    private string $clientSecret;
    private $logger;

    private ?string $tokenData = null;
    private ?int $tokenTTL = null;
    private ?int $tokenTimestamp = null;

    public function __construct(string $env = 'prod')
    {
        $this->logger = new CreatioLogger('C:\\workspace\\api\\var\\logs\\Creatio-OAuth.log');
        switch ($env) {
            case 'dev':
                $this->urlapi = 'https://dev-yoursite.creatio.com';
                $this->oauthurl = 'https://dev-yoursite-is.creatio.com';
                $this->clientId = 'CLIENTID';
                $this->clientSecret = 'CLIENTSECRET';
                break;
            case 'prod':
            default:
                $this->urlapi = 'https://yoursite.creatio.com';
                $this->oauthurl = 'https://yoursite-is.creatio.com';
                $this->clientId = 'CLIENTID';
                $this->clientSecret = 'CLIENTSECRET';
                break;
        }
    }

    public function authentification(): ?bool
    {
        //$this->logger->log("Démarrage authentification OAuth...");
        if ($this->tokenData !== null && $this->tokenTimestamp !== null) {
            if (time() < $this->tokenTimestamp + $this->tokenTTL - 30) {
                return true;
            }
        }

        //$this->logger->log("Génération du token oAuth.");
        $tokenResponse = $this->generateCreatioToken();

        if (isset($tokenResponse->access_token)) {
            $this->tokenData = $tokenResponse->access_token;
            $this->tokenTTL = isset($tokenResponse->expires_in) ? (int) $tokenResponse->expires_in : 3600;
            $this->tokenTimestamp = time();
        } else {
            $this->logger->log(date('Y-m-d H:i:s') . " - OAuth Erreur de récupération du token !");
        }

        return true;
    }

    public function get(string $collection, array $apiQuery = [], int $nbresult = 10000, $orderby = false, int $skip = 0)
    {
        $this->authentification();
        $token = $this->tokenData;

        $client = new Client();
        $headers = [
            'Accept'        => 'application/json; odata=verbose',
            'Authorization' => 'Bearer ' . $token,
            'User-Agent'    => ''
        ];

        $targetUrl = $this->urlapi . '/0/ServiceModel/EntityDataService.svc/' . $collection;

        if (count($apiQuery) > 0) {
            $targetUrl .= '?' . implode('&', array_map(function ($item) {
                return $item[0] . '=' . $item[1];
            }, array_map(null, array_keys($apiQuery), $apiQuery)));
        }

        if ($orderby !== false) {
            $targetUrl .= '&$orderby=' . $orderby . '%20desc';
        }

        $targetUrl .= '&$top=' . $nbresult . '&$skip=' . $skip;

        $callback = function () use ($client, $targetUrl, $headers) {
            $response = $client->request('GET', $targetUrl, ['headers' => $headers]);
            return (string) $response->getBody();
        };

        return $this->executeWithRetry($callback);
    }

    public function post(string $collection, array $tabObject)
    {
        $this->authentification();
        $token = $this->tokenData;

        $client = new Client();
        $headers = [
            'Accept' => 'application/atom+xml; type=entry',
            'Content-Type' => 'application/json; odata=verbose',
            'Authorization' => 'Bearer ' . $token
        ];

        $targetUrl = $this->urlapi . "/0/ServiceModel/EntityDataService.svc/" . $collection . "/";

        $callback = function () use ($client, $targetUrl, $headers, $tabObject) {
            $response = $client->request('POST', $targetUrl, [
                'headers' => $headers,
                'body' => json_encode($tabObject)
            ]);
            return (string) $response->getBody();
        };

        return $this->executeWithRetry($callback);
    }

    public function put(string $collection, string $id, array $tabObject)
    {
        $this->authentification();
        $token = $this->tokenData;

        $client = new Client();
        $targetUrl = $this->urlapi . "/0/ServiceModel/EntityDataService.svc/" . $collection . "(guid'" . $id . "')";
        $headers = [
            'Content-Type' => 'application/json;odata=verbose',
            'Accept' => 'application/json;odata=verbose',
            'Authorization' => 'Bearer ' . $token
        ];

        $callback = function () use ($client, $targetUrl, $headers, $tabObject) {
            $response = $client->request('PUT', $targetUrl, [
                'headers' => $headers,
                'body' => json_encode($tabObject)
            ]);
            return (string) $response->getBody();
        };

        return $this->executeWithRetry($callback);
    }

    public function delete(string $collection, string $id)
    {
        $this->authentification();
        $token = $this->tokenData;

        $client = new Client();
        $targetUrl = $this->urlapi . "/0/ServiceModel/EntityDataService.svc/" . $collection . "(guid'" . $id . "')";
        $headers = [
            'Accept' => 'application/json;odata=verbose',
            'Authorization' => 'Bearer ' . $token
        ];

        $callback = function () use ($client, $targetUrl, $headers) {
            $response = $client->request('DELETE', $targetUrl, ['headers' => $headers]);
            return (string) $response->getBody();
        };

        return $this->executeWithRetry($callback);
    }

    private function generateCreatioToken()
    {
        $callback = function () {
            $client = new Client();

            $response = $client->post($this->oauthurl . '/connect/token', [
                'form_params' => [
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'grant_type' => 'client_credentials'
                ],
                'headers' => [
                    'User-Agent' => ''
                ]
            ]);

            return json_decode((string) $response->getBody());
        };

        return $this->executeWithRetry($callback);
    }

    private function executeWithRetry(callable $callback, int $maxAttempts = 3)
    {
        $attempts = 0;
        $result = false;

        do {
            $attempts++;
            try {
                $result = $callback();
                if ($result !== false) {
                    break;
                }
            } catch (\Throwable $e) {
                $this->logger->log(date('Y-m-d H:i:s') . ' - Erreur OAuth: ' . $e->getMessage());
                return false;
            }
        } while ($attempts < $maxAttempts);

        return ($result !== false) ? $result : false;
    }

    public function getCookieToken()
    {
        // Pas utilisé en OAuth, retourne toujours false
        return false;
    }
}
