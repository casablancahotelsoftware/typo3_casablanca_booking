<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\Service\Api;

use Casablanca\CasablancaBooking\Compatibility\Typo3Adapter;
use Casablanca\CasablancaBooking\Domain\Dto\SiteConfigurationDto;
use Psr\Log\LoggerInterface;
use RuntimeException;
use TYPO3\CMS\Core\Http\Client\GuzzleClientFactory;
use TYPO3\CMS\Core\Http\RequestFactory;

/**
 * Authenticated HTTP wrapper for the CASABLANCA IBE v2 API.
 */
final class CasablancaHttpClient
{
    /** @var RequestFactory */
    private $requestFactory;

    /** @var GuzzleClientFactory */
    private $guzzleClientFactory;

    /** @var LoggerInterface */
    private $logger;

    /** @var SiteConfigurationDto */
    private $config;

    public function __construct(
        RequestFactory $requestFactory,
        GuzzleClientFactory $guzzleClientFactory,
        LoggerInterface $logger,
        SiteConfigurationDto $config
    ) {
        $this->requestFactory = $requestFactory;
        $this->guzzleClientFactory = $guzzleClientFactory;
        $this->logger = $logger;
        $this->config = $config;
    }

    /**
     * @param array<string, scalar|null> $query
     * @return array<string, mixed>|array<int, array<string, mixed>>
     */
    public function getJson(string $path, array $query = []): array
    {
        return $this->sendJson('GET', $path, $query, null);
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, scalar|null> $query
     * @return array<string, mixed>|array<int, array<string, mixed>>
     */
    public function postJson(string $path, array $body, array $query = []): array
    {
        return $this->sendJson('POST', $path, $query, $body);
    }

    /**
     * @param array<string, scalar|null> $query
     * @return iterable<int, array<int, array<string, mixed>>>
     */
    public function paginate(string $path, array $query = []): iterable
    {
        $skip = 0;
        $top = $this->config->paginationTop;

        do {
            $pageQuery = $query;
            $pageQuery['$skip'] = $skip;
            $pageQuery['$top'] = $top;
            $page = $this->getJson($path, $pageQuery);

            if (!Typo3Adapter::isArrayList($page) && array_key_exists('values', $page)) {
                $values = (array)$page['values'];
                if ($values === []) {
                    break;
                }
                yield $values;

                if (!isset($page['@odata.nextLink']) || $page['@odata.nextLink'] === '') {
                    break;
                }
                $skip += count($values);
                continue;
            }

            yield is_array($page) ? $page : [];
            break;
        } while (true);
    }

    /**
     * @param array<string, scalar|null> $query
     * @param array<string, mixed>|null $body
     * @return array<string, mixed>|array<int, array<string, mixed>>
     */
    private function sendJson(string $method, string $path, array $query, ?array $body): array
    {
        $url = $this->buildUrl($path, $query);
        $headers = [
            'Authorization' => 'Bearer ' . $this->config->apiKey,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'User-Agent' => 'CASABLANCA-TYPO3-Booking/1.0',
        ];

        $json = null;
        if ($body !== null) {
            $json = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($json === false) {
                throw new RuntimeException('Failed to encode CASABLANCA request body.');
            }
        }

        try {
            $response = $this->sendHttpRequest($method, $url, $headers, $json);
        } catch (\Throwable $e) {
            $this->logger->error(
                'CASABLANCA request failed: {method} {path} — {error}',
                [
                    'method' => $method,
                    'path' => $path,
                    'error' => $e->getMessage(),
                    'site' => $this->config->siteIdentifier,
                ]
            );
            throw new RuntimeException(
                'CASABLANCA API transport failure: ' . $e->getMessage(),
                0,
                $e
            );
        }

        $status = $response->getStatusCode();
        $raw = (string)$response->getBody();

        if ($status >= 400) {
            $this->logger->error(
                'CASABLANCA API returned {status}: {method} {path} — {body}',
                [
                    'status' => $status,
                    'method' => $method,
                    'path' => $path,
                    'body' => mb_substr($raw, 0, 500),
                    'site' => $this->config->siteIdentifier,
                ]
            );
            throw new RuntimeException(sprintf(
                'CASABLANCA API error %d on %s %s',
                $status,
                $method,
                $path
            ));
        }

        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException(
                'CASABLANCA API returned invalid JSON: ' . json_last_error_msg()
            );
        }

        if (!is_array($decoded)) {
            throw new RuntimeException('CASABLANCA API returned a non-array JSON root.');
        }

        return $decoded;
    }

    /**
     * @param array<string, scalar|null> $query
     */
    private function buildUrl(string $path, array $query): string
    {
        $normalisedPath = '/' . ltrim($path, '/');
        $servicePrefix = $this->config->servicePath !== ''
            ? '/' . rawurlencode($this->config->servicePath)
            : '';
        $url = sprintf(
            '%s%s/%s/%s%s',
            $this->config->apiBaseUrl,
            $servicePrefix,
            rawurlencode($this->config->tenantId),
            rawurlencode($this->config->urlFriendlyIbeContextId),
            $normalisedPath
        );

        $filtered = array_filter(
            $query,
            static function ($value): bool {
                return $value !== null && $value !== '';
            }
        );
        if ($filtered !== []) {
            $url .= '?' . http_build_query($filtered);
        }

        return $url;
    }

    /**
     * @param array<string, string> $headers
     */
    private function sendHttpRequest(string $method, string $url, array $headers, ?string $bodyContent)
    {
        $request = $this->requestFactory->createRequest($method, $url);
        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }
        if ($bodyContent !== null) {
            $request = $request->withBody($this->createBodyStream($bodyContent));
        }

        $client = $this->guzzleClientFactory->getClient();
        if (method_exists($client, 'sendRequest')) {
            return $client->sendRequest($request);
        }

        $options = [
            'headers' => $headers,
            'http_errors' => false,
        ];
        if ($bodyContent !== null) {
            $options['body'] = $bodyContent;
        }

        return $client->request($method, $url, $options);
    }

    private function createBodyStream(string $content)
    {
        $resource = fopen('php://temp', 'rb+');
        if ($resource === false) {
            throw new RuntimeException('Failed to open in-memory stream for CASABLANCA request body.');
        }
        fwrite($resource, $content);
        rewind($resource);

        if (class_exists(\TYPO3\CMS\Core\Http\Stream::class)) {
            return new \TYPO3\CMS\Core\Http\Stream($resource);
        }

        return new \GuzzleHttp\Psr7\Stream($resource);
    }
}
