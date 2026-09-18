<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\Service\Sync;

use Casablanca\CasablancaBooking\Domain\Dto\RoomTypeDto;
use Casablanca\CasablancaBooking\Domain\Dto\SiteConfigurationDto;
use Casablanca\CasablancaBooking\Domain\Repository\RoomTypeRepository;
use Casablanca\CasablancaBooking\Utility\RoomSlugGenerator;

/**
 * Upserts RoomType metadata into the mirror table.
 */
final class RoomTypeWriter
{
    /** @var RoomTypeRepository */
    private $roomTypeRepository;

    /** @var RoomSlugGenerator */
    private $slugGenerator;

    public function __construct(
        RoomTypeRepository $roomTypeRepository,
        RoomSlugGenerator $slugGenerator
    ) {
        $this->roomTypeRepository = $roomTypeRepository;
        $this->slugGenerator = $slugGenerator;
    }

    /**
     * @param RoomTypeDto[] $dtos
     */
    public function write(SiteConfigurationDto $config, array $dtos): int
    {
        $connection = $this->roomTypeRepository->getConnection();
        $now = time();
        $count = 0;

        foreach ($dtos as $dto) {
            $existing = $this->roomTypeRepository->findOneBySiteAndRoomTypeId(
                $config->siteIdentifier,
                $dto->id
            );

            $imagesJson = json_encode($dto->images, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($imagesJson === false) {
                $imagesJson = '[]';
            }

            $slug = $this->resolveSlug($config->siteIdentifier, $dto->name, $existing);

            $data = [
                'tstamp' => $now,
                'site_identifier' => $config->siteIdentifier,
                'tenant_id' => $config->tenantId,
                'ibe_context_id' => $config->urlFriendlyIbeContextId,
                'room_type_id' => $dto->id,
                'company_id' => $dto->companyId,
                'name' => $dto->name,
                'slug' => $slug,
                'description' => $dto->description,
                'short_description' => $dto->shortDescription,
                'image_url' => $dto->imageUrl,
                'images' => $imagesJson,
                'standard_occupancy' => $dto->standardOccupancy,
                'min_occupancy' => $dto->minOccupancy,
                'max_occupancy' => $dto->maxOccupancy,
                'sort_order' => $dto->sort,
            ];

            if ($existing === null) {
                $data['crdate'] = $now;
                $data['pid'] = 0;
                $connection->insert(RoomTypeRepository::TABLE, $data);
            } else {
                $connection->update(
                    RoomTypeRepository::TABLE,
                    $data,
                    ['uid' => $existing->getUid()]
                );
            }
            $count++;
        }

        return $count;
    }

    /**
     * @param \Casablanca\CasablancaBooking\Domain\Model\RoomType|null $existing
     */
    private function resolveSlug(string $siteIdentifier, string $name, $existing): string
    {
        $excludeUid = $existing !== null ? $existing->getUid() : 0;

        if ($existing !== null && $existing->getName() === $name && $existing->getSlug() !== '') {
            return $existing->getSlug();
        }

        $baseSlug = $this->slugGenerator->generate($name);
        $slug = $baseSlug;
        $suffix = 2;

        while ($this->roomTypeRepository->slugExistsForSite($siteIdentifier, $slug, $excludeUid)) {
            $slug = $baseSlug . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }
}
