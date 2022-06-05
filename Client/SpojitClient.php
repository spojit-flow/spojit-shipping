<?php

namespace Spojit\SpojitShipping\Client;

use Exception;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;

class SpojitClient
{
    /**
     * @var string
     */
    protected $authorization;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @param string $authorization
     * @param LoggerInterface $logger
     */
    public function __construct($authorization, $logger)
    {
        $this->authorization = $authorization;
        $this->logger = $logger;
    }

    /**
     * @param string $workflowId
     * @param array $data
     * @param false $debug
     * @return false|mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function sendRequest($workflowId, array $data, $debug = false)
    {
        try {

            if (!$workflowId) {
                throw new Exception('Workflow ID is required.');
            }

            if (!$this->authorization) {
                throw new Exception('Authorization token is required.');
            }

            $url = sprintf('https://app.spojit.com/request/%s', $workflowId);
            $client = new GuzzleClient();
            $body = json_encode($data);
            if ($debug) {
                $this->logger->error(sprintf('SPOJIT REQUEST: %s', $body));
            }
            $response = $client->POST($url, [
                'headers' => ['Authorization' => sprintf('Bearer %s', $this->authorization)],
                'body' => $body,
                'allow_redirects' => true,
                'timeout' => 60
            ]);

        } catch (RequestException $e) {
            $this->logger->error(sprintf('SPOJIT REQUEST ERROR: %s', $e->getMessage()));
            return false;
        } catch (ConnectException $e) {
            $this->logger->error(sprintf('SPOJIT CONNECT ERROR: %s', $e->getMessage()));
            return false;
        }

        if ($debug) {
            $this->logger->error(sprintf('SPOJIT RESPONSE: %s', $response->getBody()));
        }

        return json_decode($response->getBody(), true);
    }
}
