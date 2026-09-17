<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\Service\Sync;

use Casablanca\CasablancaBooking\Domain\Dto\RateDto;
use Casablanca\CasablancaBooking\Domain\Dto\SiteConfigurationDto;
use Casablanca\CasablancaBooking\Domain\Model\Rate;
use Casablanca\CasablancaBooking\Domain\Repository\RateRepository;
use Casablanca\CasablancaBooking\Utility\RoomSlugGenerator;

/**
 * Upserts Rate and package metadata into the mirror table.
 */
final class RateWriter
{
    /** @var RateRepository */
    private $rateRepository;

    /** @var RoomSlugGenerator */
    private $slugGenerator;

    public function __construct(
        RateRepository $rateRepository,
        RoomSlugGenerator $slugGenerator
    ) {
        $this->rateRepository = $rateRepository;
        $this->slugGenerator = $slugGenerator;
    }

    /**
     * @param RateDto[] $dtos
     */
    public function write(SiteConfigurationDto $config, array $dtos): int
    {
        $connection = $this->rateRepository->getConnection();
        $now = time();
        $count = 0;

        foreach ($dtos as $dto) {
            $existing = $this->rateRepository->findOneBySiteAndRateId(
                $config->siteIdentifier,
                $dto->id
            );

            $slug = '';
            if ($dto->isPackage) {
                $slug = $this->resolveSlug($config->siteIdentifier, $dto->name, $existing);
            }

            $data = [
                'tstamp' => $now,
                'site_identifier' => $config->siteIdentifier,
                'tenant_id' => $config->tenantId,
                'ibe_context_id' => $config->urlFriendlyIbeContextId,
                'rate_id' => $dto->id,
                'name' => $dto->name,
                'slug' => $slug,
                'description' => $dto->description,
                'image_url' => $dto->imageUrl,
                'is_package' => $dto->isPackage ? 1 : 0,
                'catering_type' => $dto->cateringType,
                'sort_order' => $dto->sort,
            ];

            if ($existing === null) {
                $data['crdate'] = $now;
                $data['pid'] = 0;
                $connection->insert(RateRepository::TABLE, $data);
            } else {
                $connection->update(
                    RateRepository::TABLE,
                    $data,
                    ['uid' => $existing->getUid()]
                );
            }
            $count++;
        }

        return $count;
    }

    /**
     * @param Rate|null $existing
     */
    private function resolveSlug(string $siteIdentifier, string $name, ?Rate $existing): string
    {
        $excludeUid = $existing !== null ? $existing->getUid() : 0;

        if ($existing !== null && $existing->getName() === $name && $existing->getSlug() !== '') {
            return $existing->getSlug();
        }

        $baseSlug = $this->slugGenerator->generate($name);
        $slug = $baseSlug;
        $suffix = 2;

        while ($this->rateRepository->slugExistsForSite($siteIdentifier, $slug, $excludeUid)) {
            $slug = $baseSlug . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }
}
