<?php
/**
 * @author gaobinzhan <gaobinzhan@gmail.com>
 */


namespace EasySwoole\HttpClient\Handler\Swoole;


use EasySwoole\HttpClient\Bean\CURLFile;
use EasySwoole\HttpClient\Bean\Response;
use EasySwoole\HttpClient\Handler\AbstractClient;
use EasySwoole\HttpClient\HttpClient;
use Swoole\Coroutine\Http\Client as SwooleHttpClient;

class Client extends AbstractClient
{

    /** @var \Swoole\Coroutine\Http\Client */
    protected $client;

    public function createClient(string $host, int $port = 80, bool $ssl = false)
    {
        $this->client = new SwooleHttpClient($host, $port, $ssl);
    }

    public function getClient(): SwooleHttpClient
    {
        $url = $this->parserUrlInfo();
        if ($this->client instanceof SwooleHttpClient) {
            $this->client->host = $url->getHost();
            $this->client->port = $url->getPort();
            $this->client->ssl = $url->getIsSsl();
            $this->client->set($this->getRequest()->getClientSetting());
            return $this->client;
        }
        $this->createClient($url->getHost(), $url->getPort(), $url->getIsSsl());;
        $this->client->set($this->getRequest()->getClientSetting());
        return $this->getClient();
    }

    public function closeClient(): bool
    {
        if ($this->client instanceof SwooleHttpClient) {
            $result = $this->client->close();
            $this->client = null;
            return $result;
        }
        return false;
    }

    public function upgrade(bool $mask = true): bool
    {
        $request = $this->getRequest();
        $request->setClientSetting('websocket_mask', $mask);

        $client = $this->getClient();
        $request->getCookies() && $client->setCookies($request->getCookies());
        $request->getHeader() && $client->setHeaders($request->getHeader());
        return $client->upgrade($this->url->getFullPath());
    }

    public function push($data, int $opcode = WEBSOCKET_OPCODE_TEXT, bool $finish = true)
    {
        return $this->getClient()->push($data, $opcode, $finish);
    }

    public function recv(float $timeout = 1.0)
    {
        return $this->getClient()->recv($timeout);
    }

    public function download(string $filename, int $offset = 0, $httpMethod = HttpClient::METHOD_GET, $rawData = null, $contentType = null)
    {

        $client = $this->getClient();
        $client->setMethod($httpMethod);
        $request = $this->getRequest();

        // ????????????????????????????????????x-www-form-urlencoded????????????
        if (is_array($rawData)) {
            $rawData = http_build_query($rawData);
            $request->setContentType(HttpClient::CONTENT_TYPE_X_WWW_FORM_URLENCODED);
        }

        // ???????????????????????? (??????????????????????????????????????????Helper???????????????)
        if (!empty($rawData)) {
            $client->setData($rawData);
            $request->setHeader('Content-Length', strlen($rawData));
        }

        // ??????ContentType(??????????????????????????????)
        if (!empty($contentType)) {
            $request->setContentType($contentType);
        }

        $client->setHeaders($request->getHeader());

        $response = $client->download($this->url->getFullPath(), $filename, $offset);
        return $response ? $this->createHttpResponse($client) : false;
    }


    private function createHttpResponse(SwooleHttpClient $client): Response
    {
        $response = new Response((array)$client);
        $response->setClient($client);
        return $response;
    }

    public function setMethod($method)
    {
        $this->getClient()->setMethod($method);
        $this->getRequest()->setMethod($method);
    }

    public function rawRequest($httpMethod = HttpClient::METHOD_GET, $rawData = null, $contentType = null): Response
    {
        $client = $this->getClient();
        $request = $this->getRequest();
        //??????????????????cookie ???header
        $request->setMethod($httpMethod);
        $client->setMethod($httpMethod);

        $cookies = (array)$request->getCookies() + (array)$client->cookies;
        if ($cookies) {
            $client->setCookies($cookies);
        }


        if ($httpMethod == HttpClient::METHOD_POST) {
            if (is_array($rawData)) {
                foreach ($rawData as $key => $item) {
                    if ($item instanceof \CURLFile) {
                        $client->addFile($item->getFilename(), $key, $item->getMimeType(), $item->getPostFilename());
                        unset($rawData[$key]);
                    }
                    if ($item instanceof CURLFile) {
                        $client->addFile($item->getPath(), $item->getName(), $item->getType(), $item->getFilename(), $item->getOffset(), $item->getLength());
                        unset($rawData[$key]);
                    }
                }
                $client->setData($rawData);
            } else if ($rawData !== null) {
                $client->setData($rawData);
            }
        } else if ($rawData !== null) {
            $client->setData($rawData);
        }
        if (is_string($rawData)) {
            $request->setHeader('Content-Length', strlen($rawData));
        }
        if (!empty($contentType)) {
            $request->setContentType($contentType);
        }

        $headers = $request->getHeader();
        if ($headers){
            $client->setHeaders($headers);
        }

        $client->execute($this->url->getFullPath());
        // ?????????????????????????????????????????????????????????
        if (!isset($request->getClientSetting()['keep_alive']) || $request->getClientSetting()['keep_alive'] !== true) {
            $client->close();
        }
        // ???????????????
        $redirected = $request->getRedirected();
        $followLocation = $request->getFollowLocation();
        if (($client->statusCode == 301 || $client->statusCode == 302) && (($followLocation > 0) && ($redirected < $followLocation))) {
            $request->setRedirected(++$redirected);
            $location = $client->headers['location'];
            $info = parse_url($location);
            // scheme ?????? ????????????
            if (empty($info['scheme']) && empty($info['host'])) {
                $this->url->setPath($location);
                $this->parserUrlInfo();
            } else {
                // ??????//?????????????????????
                $location = ltrim($location, '//');
                $this->setUrl($location);
                $this->client = null;
            }
            return $this->rawRequest($httpMethod, $rawData, $contentType);
        } else {
            $request->setRedirected(0);
        }
        return $this->createHttpResponse($client);
    }
}
