<?php

namespace Coxorange\CustomLayout\Model\Catalog\Category;

use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Catalog\Model\Category;
use Magento\Framework\App\Area;
use Magento\Framework\DataObject;
use Magento\Framework\View\Design\Theme\FlyweightFactory;
use Magento\Framework\View\DesignInterface;
use Magento\Framework\View\Model\Layout\Merge as LayoutProcessor;
use Magento\Framework\View\Model\Layout\MergeFactory as LayoutProcessorFactory;

/**
 * Manage available layout updates for categories.
 */
class LayoutUpdateManager
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
     * @var LayoutProcessorFactory
     */
    protected $layoutProcessorFactory;

    /**
     * @var LayoutProcessor|null
     */
    protected $layoutProcessor;

    /**
     * @param FlyweightFactory $themeFactory
     * @param DesignInterface $design
     * @param LayoutProcessorFactory $layoutProcessorFactory
     */
    public function __construct(
        FlyweightFactory $themeFactory,
        DesignInterface $design,
        LayoutProcessorFactory $layoutProcessorFactory
    ) {
        $this->themeFactory = $themeFactory;
        $this->design = $design;
        $this->layoutProcessorFactory = $layoutProcessorFactory;
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
     * Fetch list of available files/handles for the category.
     *
     * @param CategoryInterface $category
     * @return string[]
     */
    public function fetchAvailableFiles(CategoryInterface $category): array
    {
        if (!$category->getId()) {
            return [];
        }

        $handles = $this->getLayoutProcessor()->getAvailableHandles();
        $pattern = '/^catalog_category_view_selectable_(' . $category->getId() . '|all)_([a-z0-9]+)/i';

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
     * Extract custom layout attribute value.
     *
     * @param CategoryInterface $category
     * @return mixed
     */
    protected function extractAttributeValue(CategoryInterface $category)
    {
        if ($category instanceof Category && !$category->hasData(CategoryInterface::CUSTOM_ATTRIBUTES)) {
            return $category->getData('custom_layout_update_file');
        }
        if ($attr = $category->getCustomAttribute('custom_layout_update_file')) {
            return $attr->getValue();
        }

        return null;
    }

    /**
     * Extract selected custom layout settings.
     *
     * If no update is selected none will apply.
     *
     * @param CategoryInterface $category
     * @param DataObject $intoSettings
     * @return void
     */
    public function extractCustomSettings(CategoryInterface $category, DataObject $intoSettings): void
    {
        if ($category->getId() && $value = $this->extractAttributeValue($category)) {
            $handles = $intoSettings->getPageLayoutHandles() ?? [];
            $handles = array_merge_recursive(
                $handles,
                ['selectable' => $category->getId() . '_' . $value, 'selectable_all' => $value]
            );
            $intoSettings->setPageLayoutHandles($handles);
        }
    }
}
