<?php

namespace Coxorange\CustomLayout\Model\Cms\Page;

use Magento\Cms\Api\Data\PageInterface;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Cms\Model\Page\CustomLayout\Data\CustomLayoutSelectedInterface;
use Magento\Cms\Model\Page\CustomLayoutManagerInterface;
use Magento\Cms\Model\Page\IdentityMap;
use Magento\Framework\App\Area;
use Magento\Framework\View\Design\Theme\FlyweightFactory;
use Magento\Framework\View\DesignInterface;
use Magento\Framework\View\Model\Layout\Merge as LayoutProcessor;
use Magento\Framework\View\Model\Layout\MergeFactory as LayoutProcessorFactory;
use Magento\Framework\View\Result\Page as PageLayout;

class CustomLayoutManager implements CustomLayoutManagerInterface
{
    /**
     * @var FlyweightFactory
     */
    protected $themeFactory;

    /**
     * @var DesignInterface
     */
    protected $design;

    /**
     * @var PageRepositoryInterface
     */
    protected $pageRepository;

    /**
     * @var LayoutProcessorFactory
     */
    protected $layoutProcessorFactory;

    /**
     * @var LayoutProcessor|null
     */
    protected $layoutProcessor;

    /**
     * @var IdentityMap
     */
    protected $identityMap;

    /**
     * @param FlyweightFactory $themeFactory
     * @param DesignInterface $design
     * @param PageRepositoryInterface $pageRepository
     * @param LayoutProcessorFactory $layoutProcessorFactory
     * @param IdentityMap $identityMap
     */
    public function __construct(
        FlyweightFactory $themeFactory,
        DesignInterface $design,
        PageRepositoryInterface $pageRepository,
        LayoutProcessorFactory $layoutProcessorFactory,
        IdentityMap $identityMap
    ) {
        $this->themeFactory = $themeFactory;
        $this->design = $design;
        $this->pageRepository = $pageRepository;
        $this->layoutProcessorFactory = $layoutProcessorFactory;
        $this->identityMap = $identityMap;
    }

    /**
     * Adopt page's identifier to be used as layout handle.
     *
     * @param PageInterface $page
     * @return string
     */
    protected function sanitizeIdentifier(PageInterface $page): string
    {
        return str_replace('/', '_', $page->getIdentifier());
    }

    /**
     * Get the processor instance.
     *
     * @return LayoutProcessor
     */
    protected function getLayoutProcessor(): LayoutProcessor
    {
        if (!$this->layoutProcessor) {
            $this->layoutProcessor = $this->layoutProcessorFactory->create([
                'theme' => $this->themeFactory->create($this->design->getConfigurationDesignTheme(Area::AREA_FRONTEND))
            ]);
            $this->themeFactory = null;
            $this->design = null;
        }

        return $this->layoutProcessor;
    }

    /**
     * @inheritDoc
     */
    public function fetchAvailableFiles(PageInterface $page): array
    {
        $identifier = $this->sanitizeIdentifier($page);
        $handles = $this->getLayoutProcessor()->getAvailableHandles();
        $pattern = '/^cms_page_view_selectable_(' . preg_quote($identifier, null) . '|all)_([a-z0-9]+)/i';

        return array_filter(
            array_map(
                static function (string $handle) use ($pattern) : ?string {
                    preg_match($pattern, $handle, $selectable);
                    if (!empty($selectable[2])) {
                        return $selectable[2];
                    }

                    return null;
                },
                $handles
            )
        );
    }

    /**
     * @inheritDoc
     */
    public function applyUpdate(PageLayout $layout, CustomLayoutSelectedInterface $layoutSelected): void
    {
        $page = $this->identityMap->get($layoutSelected->getPageId());
        if (!$page) {
            $page = $this->pageRepository->getById($layoutSelected->getPageId());
        }

        $layout->addPageLayoutHandles([
            'selectable'     => $this->sanitizeIdentifier($page) . '_' . $layoutSelected->getLayoutFileId(),
            'selectable_all' => $layoutSelected->getLayoutFileId(),
        ]);
    }
}
