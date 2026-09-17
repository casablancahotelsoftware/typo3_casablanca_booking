<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\Domain\Repository;

use Casablanca\CasablancaBooking\Compatibility\Typo3Adapter;
use Casablanca\CasablancaBooking\Domain\IbeLinkStyle;
use Casablanca\CasablancaBooking\Domain\Model\Configuration;
use RuntimeException;
use TYPO3\CMS\Core\Crypto\Random;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Repository for backend-stored site configuration including encrypted API keys.
 */
final class ConfigurationRepository
{
    public const TABLE = 'tx_casablancabooking_configuration';
    private const DEFAULT_SYNC_RANGE_DAYS = 365;
    private const DEFAULT_SYNC_CHUNK_DAYS = 31;
    private const DEFAULT_PAGINATION_TOP = 100;

    /** @var ConnectionPool */
    private $connectionPool;

    public function __construct(ConnectionPool $connectionPool)
    {
        $this->connectionPool = $connectionPool;
    }

    public function findBySiteIdentifier(string $siteIdentifier): ?Configuration
    {
        if ($siteIdentifier === '') {
            return null;
        }

        $qb = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $row = $qb->select('*')
            ->from(self::TABLE)
            ->where(
                $qb->expr()->eq(
                    'site_identifier',
                    $qb->createNamedParameter($siteIdentifier)
                )
            )
            ->setMaxResults(1);

        $rows = Typo3Adapter::fetchAllAssociative(Typo3Adapter::executeQuery($qb));
        if ($rows === []) {
            return null;
        }

        return $this->hydrate($rows[0]);
    }

    /**
     * @return Configuration[]
     */
    public function findByUid(int $uid): ?Configuration
    {
        if ($uid <= 0) {
            return null;
        }

        $qb = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $rows = Typo3Adapter::fetchAllAssociative(
            Typo3Adapter::executeQuery(
                $qb->select('*')
                    ->from(self::TABLE)
                    ->where(
                        $qb->expr()->eq('uid', $qb->createNamedParameter($uid, Connection::PARAM_INT))
                    )
                    ->setMaxResults(1)
            )
        );

        return $rows === [] ? null : $this->hydrate($rows[0]);
    }

    /**
     * Site identifiers for Fluid form selects (value => label).
     *
     * @return array<string, string>
     */
    public function getAvailableSiteIdentifiers(bool $excludeConfigured = false): array
    {
        $identifiers = [];
        try {
            /** @var SiteFinder $siteFinder */
            $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
            foreach ($siteFinder->getAllSites() as $site) {
                $identifier = $site->getIdentifier();
                $identifiers[$identifier] = $identifier;
            }
        } catch (\Throwable $exception) {
            // Site configuration may be unavailable during early setup.
        }

        if ($excludeConfigured && $identifiers !== []) {
            $configured = [];
            foreach ($this->findAll() as $configuration) {
                $configured[$configuration->getSiteIdentifier()] = true;
            }
            $identifiers = array_diff_key($identifiers, $configured);
        }

        ksort($identifiers);

        return $identifiers;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function save(array $data): int
    {
        $siteIdentifier = trim((string)($data['siteIdentifier'] ?? ''));
        $tenantId = trim((string)($data['tenantId'] ?? ''));
        if ($siteIdentifier === '' || $tenantId === '') {
            throw new RuntimeException('Site identifier and Tenant ID are required.');
        }

        $uid = (int)($data['uid'] ?? 0);
        $existing = $uid > 0 ? $this->findByUid($uid) : $this->findBySiteIdentifier($siteIdentifier);

        $apiKeyPlain = trim((string)($data['apiKey'] ?? ''));
        $apiKeyEncrypted = $existing !== null ? $existing->getApiKeyEncrypted() : '';
        if ($apiKeyPlain !== '') {
            $apiKeyEncrypted = $this->encryptApiKey($apiKeyPlain);
        }
        if ($apiKeyEncrypted === '') {
            throw new RuntimeException('API key is required.');
        }

        $useCustomIbe = !empty($data['useCustomIbeDomain']);
        $ibeBaseUrl = $useCustomIbe
            ? rtrim(trim((string)($data['ibeBaseUrl'] ?? '')), '/')
            : 'https://bookingengine.casablanca.at';
        if ($ibeBaseUrl === '') {
            throw new RuntimeException('IBE base URL is required when using a custom domain.');
        }

        $spaceName = trim((string)($data['urlFriendlyIbeContextId'] ?? 'bookingengine'));
        $ibeLinkStyle = IbeLinkStyle::normalize((string)($data['ibeLinkStyle'] ?? IbeLinkStyle::FULL_PATH));
        if ($ibeLinkStyle === IbeLinkStyle::FULL_PATH && $spaceName === '') {
            throw new RuntimeException('Space name is required when using the full path link style.');
        }

        $row = [
            'tstamp' => time(),
            'site_identifier' => $siteIdentifier,
            'tenant_id' => $tenantId,
            'url_friendly_ibe_context_id' => $spaceName,
            'api_key_encrypted' => $apiKeyEncrypted,
            'api_base_url' => rtrim(trim((string)($data['apiBaseUrl'] ?? 'https://api.casablanca.at')), '/'),
            'ibe_base_url' => $ibeBaseUrl,
            'ibe_link_style' => $ibeLinkStyle,
            'use_custom_ibe_domain' => $useCustomIbe ? 1 : 0,
            'service_path' => trim((string)($data['servicePath'] ?? 'ibe')),
            'default_culture' => trim((string)($data['defaultCulture'] ?? 'de')),
            'sync_range_days' => self::DEFAULT_SYNC_RANGE_DAYS,
            'sync_chunk_days' => self::DEFAULT_SYNC_CHUNK_DAYS,
            'pagination_top' => self::DEFAULT_PAGINATION_TOP,
            'default_adults' => max(1, (int)($data['defaultAdults'] ?? 2)),
            'default_children_ages' => trim((string)($data['defaultChildrenAges'] ?? '')),
        ];

        $connection = $this->getConnection();
        if ($existing !== null) {
            $connection->update(self::TABLE, $row, ['uid' => $existing->getUid()]);

            return $existing->getUid();
        }

        $row['crdate'] = time();
        $row['pid'] = 0;
        $connection->insert(self::TABLE, $row);

        return (int)$connection->lastInsertId();
    }

    public function findAll(): array
    {
        $qb = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $rows = Typo3Adapter::fetchAllAssociative(
            Typo3Adapter::executeQuery(
                $qb->select('*')
                    ->from(self::TABLE)
                    ->orderBy('site_identifier', 'ASC')
            )
        );

        $result = [];
        foreach ($rows as $row) {
            $result[] = $this->hydrate($row);
        }

        return $result;
    }

    public function encryptApiKey(string $plainText): string
    {
        if ($plainText === '') {
            return '';
        }

        if (function_exists('sodium_crypto_secretbox')) {
            $key = $this->deriveEncryptionKey();
            if (method_exists(Random::class, 'generateRandomBytes')) {
                /** @var Random $random */
                $random = GeneralUtility::makeInstance(Random::class);
                $nonce = $random->generateRandomBytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            } else {
                $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            }
            $cipher = sodium_crypto_secretbox($plainText, $nonce, $key);

            return 'sodium:' . base64_encode($nonce . $cipher);
        }

        return 'b64:' . base64_encode($this->xorWithEncryptionKey($plainText));
    }

    public function decryptApiKey(string $encrypted): string
    {
        if ($encrypted === '') {
            return '';
        }

        if (strpos($encrypted, 'sodium:') === 0 && function_exists('sodium_crypto_secretbox_open')) {
            $payload = base64_decode(substr($encrypted, 7), true);
            if ($payload === false || strlen($payload) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
                throw new RuntimeException('Invalid encrypted API key payload.');
            }

            $nonce = substr($payload, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $cipher = substr($payload, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $plain = sodium_crypto_secretbox_open($cipher, $nonce, $this->deriveEncryptionKey());

            if ($plain === false) {
                throw new RuntimeException('Failed to decrypt CASABLANCA API key.');
            }

            return $plain;
        }

        if (strpos($encrypted, 'b64:') === 0) {
            $decoded = base64_decode(substr($encrypted, 4), true);
            if ($decoded === false) {
                throw new RuntimeException('Invalid base64-encoded API key.');
            }

            return $this->xorWithEncryptionKey($decoded);
        }

        throw new RuntimeException('Unsupported API key encryption format.');
    }

    public function getConnection(): Connection
    {
        return $this->connectionPool->getConnectionForTable(self::TABLE);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Configuration
    {
        /** @var Configuration $entity */
        $entity = GeneralUtility::makeInstance(Configuration::class);

        $childrenAges = [];
        if (!empty($row['default_children_ages'])) {
            $childrenAges = array_map(
                'intval',
                array_filter(array_map('trim', explode(',', (string)$row['default_children_ages'])))
            );
        }

        $bind = function (array $r) use ($childrenAges): void {
            /** @var Configuration $this */
            $this->_setProperty('uid', (int)($r['uid'] ?? 0));
            $this->_setProperty('pid', (int)($r['pid'] ?? 0));
            $this->siteIdentifier = (string)($r['site_identifier'] ?? '');
            $this->tenantId = (string)($r['tenant_id'] ?? '');
            $this->urlFriendlyIbeContextId = (string)($r['url_friendly_ibe_context_id'] ?? 'bookingengine');
            $this->apiKeyEncrypted = (string)($r['api_key_encrypted'] ?? '');
            $this->apiBaseUrl = (string)($r['api_base_url'] ?? 'https://api.casablanca.at');
            $this->ibeBaseUrl = (string)($r['ibe_base_url'] ?? 'https://bookingengine.casablanca.at');
            $this->ibeLinkStyle = IbeLinkStyle::normalize(
                (string)($r['ibe_link_style'] ?? IbeLinkStyle::FULL_PATH)
            );
            $this->useCustomIbeDomain = (bool)($r['use_custom_ibe_domain'] ?? false);
            $this->servicePath = (string)($r['service_path'] ?? 'ibe');
            $this->defaultCulture = (string)($r['default_culture'] ?? 'de');
            $this->syncRangeDays = (int)($r['sync_range_days'] ?? 365);
            $this->syncChunkDays = (int)($r['sync_chunk_days'] ?? 31);
            $this->paginationTop = (int)($r['pagination_top'] ?? 100);
            $this->defaultAdults = (int)($r['default_adults'] ?? 2);
            $this->defaultChildrenAges = $childrenAges;
            $this->connectionStatus = (string)($r['connection_status'] ?? 'unknown');
            $this->connectionCheckedAt = (int)($r['connection_checked_at'] ?? 0);
            $this->connectionMessage = (string)($r['connection_message'] ?? '');
        };

        $bind->call($entity, $row);

        return $entity;
    }

    public function updateConnectionStatus(
        string $siteIdentifier,
        string $status,
        string $message
    ): void {
        if ($siteIdentifier === '') {
            return;
        }

        $allowed = ['unknown', 'ok', 'error'];
        if (!in_array($status, $allowed, true)) {
            $status = 'unknown';
        }

        $connection = $this->getConnection();
        $connection->update(
            self::TABLE,
            [
                'tstamp' => time(),
                'connection_status' => $status,
                'connection_checked_at' => time(),
                'connection_message' => $message,
            ],
            ['site_identifier' => $siteIdentifier]
        );
    }

    private function deriveEncryptionKey(): string
    {
        $encryptionKey = (string)($GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] ?? '');
        if ($encryptionKey === '') {
            throw new RuntimeException(
                'TYPO3 encryptionKey is empty — cannot encrypt/decrypt CASABLANCA API keys.'
            );
        }

        return hash('sha256', $encryptionKey, true);
    }

    private function xorWithEncryptionKey(string $input): string
    {
        $key = $this->deriveEncryptionKey();
        $keyLength = strlen($key);
        $output = '';

        for ($i = 0, $len = strlen($input); $i < $len; $i++) {
            $output .= $input[$i] ^ $key[$i % $keyLength];
        }

        return $output;
    }
}
