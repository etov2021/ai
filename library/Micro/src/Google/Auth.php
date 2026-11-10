<?php

namespace Micro\Google;

use Google\Client;

class Auth
{
    protected $scopes = ['email'];
    
    protected $client = null;
    
    /**
     * @return the $client
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * @param \Google\Client $client
     */
    public function setClient($client)
    {
        $this->client = $client;
    }

    /**
     * @param string $state
     */
    public function setState($state)
    {
        $this->client->setState($state);
    }

    /**
     * @throws \Exception
     * @return \Micro\Google\Auth
     */
    public function __construct()
    {
        $config = config('google', true);

        if(!$config) {
            throw new \Exception('Invalid google data');
        }
        
        if(!isset($config['clientId']) || !$config['clientId']) {
            throw new \Exception('Invalid google data');
        }
        
        if(!isset($config['clientSecret']) || !$config['clientSecret']) {
            throw new \Exception('Invalid google data');
        }
        
        if(!isset($config['redirectUrl']) || !$config['redirectUrl']) {
            throw new \Exception('Invalid redirect url');
        }

        try {
            
            $this->client = new Client();
            
            $this->client->setClientId($config['clientId']);
            $this->client->setClientSecret($config['clientSecret']);
            $this->client->setRedirectUri($config['redirectUrl']);
            $this->client->setAccessType('offline');
            $this->client->setApprovalPrompt ("force");
            
            $this->client->setScopes($this->getScopes());
            
            if (!empty($_SESSION['google_token']) && isset($_SESSION['google_token']['id_token'])) {
                
                $this->client->setAccessToken($_SESSION['google_token']);
            }

        } catch (\Google_Service_Exception $e) {
            file_put_contents(data_path('google.txt'), '[' . date('Y-m-d H:i:s') . '] __construct' . "\n" . 'ERROR: ' . $e->getMessage() . "\n" . str_repeat('-', 50) . "\n", FILE_APPEND);
        }
        
        return $this;
    }
    
    /**
     * @return the $scopes
     */
    public function getScopes()
    {
        return $this->scopes;
    }

    /**
     * @param multitype:string  $scopes
     */
    public function setScopes($scopes)
    {
        $this->scopes = $scopes;
    }

    public function getTokenData()
    {
        $tokenData = false;
        
        if ($this->client->getAccessToken()) {
            $tokenData = $this->client->verifyIdToken();
        }

        if(!$tokenData && $this->client->getRefreshToken()) {
            $refreshToken = $this->client->fetchAccessTokenWithRefreshToken($this->client->getRefreshToken());
            
            $tokenData = $this->client->verifyIdToken();
        }
        
        return $tokenData;
    }
    
    public function verifyResponse($params = [])
    {
        if (isset($params['code']) && $params['code']) {
            
            $token = $this->client->fetchAccessTokenWithAuthCode($params['code']);
            
            $_SESSION['google_token'] = $token;
        }
    }
}
