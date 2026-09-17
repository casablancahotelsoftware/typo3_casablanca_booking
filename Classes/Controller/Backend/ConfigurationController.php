<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\Controller\Backend;

use Casablanca\CasablancaBooking\Domain\Dto\RoomOccupancyDto;
use Casablanca\CasablancaBooking\Domain\Dto\SiteConfigurationDto;
use Casablanca\CasablancaBooking\Domain\IbeLinkStyle;
use Casablanca\CasablancaBooking\Domain\Model\Configuration;
use Casablanca\CasablancaBooking\Domain\Repository\ConfigurationRepository;
use Casablanca\CasablancaBooking\Domain\Repository\RateRepository;
use Casablanca\CasablancaBooking\Domain\Repository\RoomTypeRepository;
use Casablanca\CasablancaBooking\Service\Configuration\ConfigurationService;
use Casablanca\CasablancaBooking\Service\Sync\SyncService;
use Casablanca\CasablancaBooking\Service\UrlBuilder\IbeUrlBuilder;
use DateTimeImmutable;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Backend module for per-site CASABLANCA credentials and sync controls.
 */
class ConfigurationController extends ActionController
{
    /** @var ConfigurationRepository */
    private $configurationRepository;

    /** @var ConfigurationService */
    private $configurationService;

    /** @var SyncService */
    private $syncService;

    /** @var RoomTypeRepository */
    private $roomTypeRepository;

    /** @var RateRepository */
    private $rateRepository;

    /** @var IbeUrlBuilder */
    private $ibeUrlBuilder;

    /** @var ModuleTemplateFactory|null */
    private $moduleTemplateFactory;

    /** @var ModuleTemplate|null */
    private $moduleTemplate;

    public function __construct(
        ConfigurationRepository $configurationRepository,
        ConfigurationService $configurationService,
        SyncService $syncService,
        RoomTypeRepository $roomTypeRepository,
        RateRepository $rateRepository,
        IbeUrlBuilder $ibeUrlBuilder
    ) {
        $this->configurationRepository = $configurationRepository;
        $this->configurationService = $configurationService;
        $this->syncService = $syncService;
        $this->roomTypeRepository = $roomTypeRepository;
        $this->rateRepository = $rateRepository;
        $this->ibeUrlBuilder = $ibeUrlBuilder;

        if (class_exists(ModuleTemplateFactory::class)) {
            $this->moduleTemplateFactory = GeneralUtility::makeInstance(ModuleTemplateFactory::class);
        }
    }

    protected function initializeAction(): void
    {
        if ($this->moduleTemplateFactory === null) {
            return;
        }

        $this->moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $this->moduleTemplate->setTitle(
            LocalizationUtility::translate('LLL:EXT:casablanca_booking/Resources/Private/Language/locallang_mod.xlf:mlang_tabs_tab')
                ?: 'CASABLANCA Booking'
        );
        if (method_exists($this, 'getFlashMessageQueue')) {
            $this->moduleTemplate->setFlashMessageQueue($this->getFlashMessageQueue());
        }

        if (method_exists($this->moduleTemplate, 'getPageRenderer')) {
            $pageRenderer = $this->moduleTemplate->getPageRenderer();
            $pageRenderer->addCssFile('EXT:casablanca_booking/Resources/Public/Css/backend.css');
            $pageRenderer->addJsFile('EXT:casablanca_booking/Resources/Public/JavaScript/backend-config.js');
        }
    }

    /**
     * @return ResponseInterface|void
     */
    public function indexAction()
    {
        $configurations = $this->configurationRepository->findAll();
        $this->view->assign('configurations', $configurations);

        return $this->renderModuleResponse('Backend/Configuration/Index');
    }

    /**
     * @return ResponseInterface|void
     */
    public function editAction(int $uid = 0, string $tab = 'config')
    {
        $configuration = $uid > 0
            ? $this->configurationRepository->findByUid($uid)
            : null;

        if ($configuration === null && $uid > 0) {
            $this->enqueueFlashMessage(
                LocalizationUtility::translate('module.error.not_found', 'casablanca_booking') ?: 'Configuration not found.',
                '',
                ContextualFeedbackSeverity::ERROR
            );

            return $this->redirect('index');
        }

        if ($tab === 'mapping' && ($configuration === null || !$configuration->isConnectionOk())) {
            $tab = 'config';
        }

        $this->view->assign('configuration', $configuration);
        $this->view->assign('isNew', $configuration === null);
        $this->view->assign('activeTab', $tab);
        $this->view->assign('isConfigTab', $tab === 'config');
        $this->view->assign('isMappingTab', $tab === 'mapping');
        $this->view->assign(
            'siteIdentifiers',
            $this->configurationRepository->getAvailableSiteIdentifiers($configuration === null)
        );
        $this->view->assign('ibeLinkStyleOptions', $this->buildIbeLinkStyleOptions());
        $this->view->assign(
            'bookingEngineUrlPreview',
            $configuration !== null ? $this->buildBookingEngineUrlPreview($configuration) : ''
        );
        if ($configuration !== null && $tab === 'mapping') {
            $this->assignMappingCodes($configuration);
        }

        return $this->renderModuleResponse('Backend/Configuration/Edit');
    }

    protected function initializeSaveAction(): void
    {
        // Form data is read manually from POST; skip Extbase object validation.
    }

    /**
     * @return ResponseInterface|void
     */
    public function saveAction()
    {
        $data = $this->resolveConfigurationFormData();

        if (trim((string)($data['siteIdentifier'] ?? '')) === '' || trim((string)($data['tenantId'] ?? '')) === '') {
            $this->enqueueFlashMessage(
                LocalizationUtility::translate('module.error.missingRequired', 'casablanca_booking')
                    ?: 'Site identifier and Tenant ID are required.',
                '',
                ContextualFeedbackSeverity::ERROR
            );

            $redirectUid = (int)($data['uid'] ?? 0);
            if ($redirectUid > 0) {
                return $this->redirect('edit', null, null, ['uid' => $redirectUid]);
            }

            return $this->redirect('edit');
        }

        $uidBeforeSave = (int)($data['uid'] ?? 0);
        $siteIdentifier = trim((string)($data['siteIdentifier'] ?? ''));
        $existingBeforeSave = $uidBeforeSave > 0
            ? $this->configurationRepository->findByUid($uidBeforeSave)
            : $this->configurationRepository->findBySiteIdentifier($siteIdentifier);
        $isNewConfiguration = $existingBeforeSave === null;

        try {
            $uid = $this->configurationService->saveConfiguration($data);
            $this->configurationService->ensureDailySyncTaskAtCurrentTime();

            $this->enqueueFlashMessage(
                LocalizationUtility::translate('module.success.saved', 'casablanca_booking') ?: 'Configuration saved.',
                '',
                ContextualFeedbackSeverity::OK
            );

            $ibeLinkStyle = IbeLinkStyle::normalize((string)($data['ibeLinkStyle'] ?? IbeLinkStyle::FULL_PATH));
            $useCustomIbe = !empty($data['useCustomIbeDomain']);
            if (
                !$useCustomIbe
                && in_array($ibeLinkStyle, [IbeLinkStyle::TENANT_ONLY, IbeLinkStyle::CULTURE_ONLY], true)
            ) {
                $this->enqueueFlashMessage(
                    LocalizationUtility::translate(
                        'module.warning.linkStyleDefaultDomain',
                        'casablanca_booking'
                    ) ?: 'Tenant-only and culture-only link styles are intended for custom IBE domains.',
                    '',
                    ContextualFeedbackSeverity::WARNING
                );
            }

            $saved = $this->configurationRepository->findByUid($uid);
            if ($saved !== null) {
                $connectionSucceeded = false;
                try {
                    $testResult = $this->syncService->testConnection($saved->getSiteIdentifier());
                    $connectionSucceeded = !empty($testResult['success']);
                    $this->enqueueFlashMessage(
                        (string)($testResult['message'] ?? 'Connection check finished.'),
                        LocalizationUtility::translate('module.connection.title', 'casablanca_booking')
                            ?: 'Connection check',
                        $connectionSucceeded
                            ? ContextualFeedbackSeverity::OK
                            : ContextualFeedbackSeverity::ERROR
                    );
                } catch (\Throwable $connectionException) {
                    $this->enqueueFlashMessage(
                        $connectionException->getMessage(),
                        LocalizationUtility::translate('module.connection.title', 'casablanca_booking')
                            ?: 'Connection check',
                        ContextualFeedbackSeverity::ERROR
                    );
                }

                if ($isNewConfiguration && $connectionSucceeded) {
                    $exitCode = $this->syncService->sync($saved->getSiteIdentifier(), false, null);
                    if ($exitCode === 0) {
                        $this->enqueueFlashMessage(
                            LocalizationUtility::translate('module.success.sync', 'casablanca_booking')
                                ?: 'Sync completed successfully.',
                            '',
                            ContextualFeedbackSeverity::OK
                        );
                    } else {
                        $this->enqueueFlashMessage(
                            LocalizationUtility::translate('module.error.sync', 'casablanca_booking')
                                ?: 'Sync failed or partially failed.',
                            '',
                            ContextualFeedbackSeverity::ERROR
                        );
                    }
                }
            }

            return $this->redirect('edit', null, null, ['uid' => $uid]);
        } catch (\Throwable $exception) {
            $this->enqueueFlashMessage($exception->getMessage(), '', ContextualFeedbackSeverity::ERROR);

            $redirectUid = (int)($data['uid'] ?? 0);
            if ($redirectUid > 0) {
                return $this->redirect('edit', null, null, ['uid' => $redirectUid]);
            }

            return $this->redirect('edit');
        }
    }

    /**
     * @return ResponseInterface|void
     */
    public function testConnectionAction(string $siteIdentifier = '')
    {
        if ($siteIdentifier === '') {
            $this->enqueueFlashMessage('Site identifier is required.', '', ContextualFeedbackSeverity::ERROR);

            return $this->redirect('index');
        }

        $result = $this->syncService->testConnection($siteIdentifier);
        $severity = !empty($result['success']) ? ContextualFeedbackSeverity::OK : ContextualFeedbackSeverity::ERROR;

        $this->enqueueFlashMessage(
            (string)($result['message'] ?? 'Connection test finished.'),
            LocalizationUtility::translate('module.connection.title', 'casablanca_booking')
                ?: 'Connection check',
            $severity
        );

        $configuration = $this->configurationRepository->findBySiteIdentifier($siteIdentifier);
        if ($configuration !== null) {
            return $this->redirect('edit', null, null, ['uid' => $configuration->getUid()]);
        }

        return $this->redirect('index');
    }

    /**
     * @return ResponseInterface|void
     */
    public function syncNowAction(string $siteIdentifier = '')
    {
        $exitCode = $this->syncService->sync(
            $siteIdentifier !== '' ? $siteIdentifier : null,
            false,
            null
        );

        if ($exitCode === 0) {
            $this->enqueueFlashMessage(
                LocalizationUtility::translate('module.success.sync', 'casablanca_booking') ?: 'Sync completed successfully.',
                '',
                ContextualFeedbackSeverity::OK
            );
        } else {
            $this->enqueueFlashMessage(
                LocalizationUtility::translate('module.error.sync', 'casablanca_booking') ?: 'Sync failed or partially failed.',
                '',
                ContextualFeedbackSeverity::ERROR
            );
        }

        if ($siteIdentifier !== '') {
            $configuration = $this->configurationRepository->findBySiteIdentifier($siteIdentifier);
            if ($configuration !== null) {
                return $this->redirect('edit', null, null, [
                    'uid' => $configuration->getUid(),
                    'tab' => 'mapping',
                ]);
            }
        }

        return $this->redirect('index');
    }

    /**
     * @return ResponseInterface|void
     */
    private function renderModuleResponse(string $templateName)
    {
        if ($this->moduleTemplate !== null && method_exists($this->moduleTemplate, 'renderResponse')) {
            $this->copyViewVariablesToModuleTemplate();

            return $this->moduleTemplate->renderResponse($templateName);
        }

        if (method_exists($this, 'htmlResponse')) {
            return $this->htmlResponse($this->view->render());
        }

        return null;
    }

    private function copyViewVariablesToModuleTemplate(): void
    {
        if ($this->moduleTemplate === null || !method_exists($this->view, 'getRenderingContext')) {
            return;
        }

        $variables = $this->view->getRenderingContext()->getVariableProvider()->getAll();
        if (is_array($variables) && $variables !== []) {
            $this->moduleTemplate->assignMultiple($variables);
        }
    }

    private function assignMappingCodes(Configuration $configuration): void
    {
        $siteIdentifier = $configuration->getSiteIdentifier();
        $roomTypes = $this->roomTypeRepository->findForSite($siteIdentifier);
        $rates = [];
        foreach ($this->rateRepository->findForSite($siteIdentifier) as $rate) {
            if (!$rate->isPackage()) {
                $rates[] = $rate;
            }
        }
        $packages = $this->rateRepository->findPackagesForSite($siteIdentifier);

        $companyId = '';
        if ($roomTypes !== []) {
            $companyId = $roomTypes[0]->getCompanyId();
        }

        $this->view->assign('roomTypes', $roomTypes);
        $this->view->assign('rates', $rates);
        $this->view->assign('packages', $packages);
        $this->view->assign('companyId', $companyId);
    }

    /**
     * @return array<string, string>
     */
    private function buildIbeLinkStyleOptions(): array
    {
        return [
            IbeLinkStyle::FULL_PATH => LocalizationUtility::translate(
                'module.field.ibeLinkStyle.full_path',
                'casablanca_booking'
            ) ?: 'Full path (culture/tenant/space)',
            IbeLinkStyle::TENANT_ONLY => LocalizationUtility::translate(
                'module.field.ibeLinkStyle.tenant_only',
                'casablanca_booking'
            ) ?: 'Tenant only (culture/tenant)',
            IbeLinkStyle::CULTURE_ONLY => LocalizationUtility::translate(
                'module.field.ibeLinkStyle.culture_only',
                'casablanca_booking'
            ) ?: 'Culture only (culture)',
        ];
    }

    /**
     * @return list<string>
     */
    private function configurationFormFieldNames(): array
    {
        return [
            'uid',
            'siteIdentifier',
            'tenantId',
            'apiKey',
            'useCustomIbeDomain',
            'ibeBaseUrl',
            'urlFriendlyIbeContextId',
            'ibeLinkStyle',
            'defaultCulture',
            'apiBaseUrl',
            'servicePath',
            'defaultAdults',
            'defaultChildrenAges',
        ];
    }

    /**
     * @param array<string, mixed> $source
     * @param array<string, mixed> $target
     *
     * @return array<string, mixed>
     */
    private function mergeConfigurationFormFields(array $source, array $target): array
    {
        foreach ($this->configurationFormFieldNames() as $fieldName) {
            if (!array_key_exists($fieldName, $source)) {
                continue;
            }

            $value = $source[$fieldName];
            if ($fieldName === 'useCustomIbeDomain') {
                $target[$fieldName] = $value !== '' && $value !== '0' && $value !== 0 && $value !== false ? 1 : 0;
                continue;
            }

            $target[$fieldName] = $value;
        }

        return $target;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveConfigurationFormData(): array
    {
        $data = [];

        if ($this->request->hasArgument('configuration')) {
            $data = $this->mergeConfigurationFormFields(
                (array)$this->request->getArgument('configuration'),
                $data
            );
        }

        foreach ($this->configurationFormFieldNames() as $fieldName) {
            if ($this->request->hasArgument($fieldName)) {
                $data = $this->mergeConfigurationFormFields(
                    [$fieldName => $this->request->getArgument($fieldName)],
                    $data
                );
            }
        }

        if (strtoupper($this->request->getMethod()) === 'POST') {
            $parsedBody = $this->request->getParsedBody();
            if (is_array($parsedBody)) {
                if (isset($parsedBody['configuration']) && is_array($parsedBody['configuration'])) {
                    $data = $this->mergeConfigurationFormFields($parsedBody['configuration'], $data);
                }

                // Legacy double-nested POST from earlier form templates.
                if (
                    isset($parsedBody['configuration']['configuration'])
                    && is_array($parsedBody['configuration']['configuration'])
                    && trim((string)($data['siteIdentifier'] ?? '')) === ''
                ) {
                    $data = $this->mergeConfigurationFormFields(
                        $parsedBody['configuration']['configuration'],
                        $data
                    );
                }

                $data = $this->mergeConfigurationFormFields($parsedBody, $data);
            }
        }

        if (isset($data['apiKey'])) {
            $data['apiKey'] = trim((string)$data['apiKey']);
        }

        if (isset($data['uid'])) {
            $data['uid'] = (int)$data['uid'];
        }

        return $data;
    }

    private function buildBookingEngineUrlPreview(Configuration $configuration): string
    {
        try {
            $dto = new SiteConfigurationDto(
                $configuration->getSiteIdentifier(),
                $configuration->getTenantId(),
                $configuration->getUrlFriendlyIbeContextId(),
                'preview',
                $configuration->getApiBaseUrl(),
                $configuration->getIbeBaseUrl(),
                $configuration->getDefaultCulture(),
                $configuration->getSyncRangeDays(),
                $configuration->getSyncChunkDays(),
                $configuration->getPaginationTop(),
                new RoomOccupancyDto(2),
                $configuration->getServicePath(),
                $configuration->getIbeLinkStyle()
            );

            return $this->ibeUrlBuilder->buildForRooms(
                $dto,
                new DateTimeImmutable('2026-06-01'),
                new DateTimeImmutable('2026-06-08'),
                [new RoomOccupancyDto(2)]
            );
        } catch (\Throwable $exception) {
            return '';
        }
    }

    protected function enqueueFlashMessage(string $message, string $title, ContextualFeedbackSeverity $severity): void
    {
        if (method_exists($this, 'addFlashMessage') && is_callable([get_parent_class($this), 'addFlashMessage'])) {
            parent::addFlashMessage($message, $title, $severity);
            return;
        }

        $flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);
        $queue = $flashMessageService->getMessageQueueByIdentifier();
        $queue->addMessage(new FlashMessage($message, $title, $severity));
    }
}
