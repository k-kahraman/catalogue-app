<?php

/**
 * @category    Scandiweb
 * @package     Scandiweb_Test
 * @author      Kaan Kahraman <kaan.kahraman@scandiweb.com>
 * @copyright   Copyright (c) 2022 Scandiweb, Ltd (https://scandiweb.com)
 */

namespace Scandiweb\Test\Setup\Patch\Data;

use Magento\Catalog\Api\CategoryLinkManagementInterface;
use Magento\Catalog\Api\Data\ProductInterfaceFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Eav\Setup\EavSetup;
use Magento\Framework\App\State;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Validation\ValidationException;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryApi\Api\Data\SourceItemInterfaceFactory;
use Magento\InventoryApi\Api\SourceItemsSaveInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;

/**
 * Create Migration product class
 */
class CreateWomenProduct implements DataPatchInterface
{
    /**
     * {@inheritDoc}
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * {@inheritDoc}
     */
    public function getAliases(): array
    {
        return [];
    }

    /**
     * @var LoggerInterface;
     */
    protected LoggerInterface $logger;

    /**
     * @var ModuleDataSetupInterface
     */
    protected ModuleDataSetupInterface $setup;

    /**
     * @var ProductInterfaceFactory
     */
    protected ProductInterfaceFactory $productInterfaceFactory;

    /**
     * @var ProductRepositoryInterface
     */
    protected ProductRepositoryInterface $productRepository;

    /**
     * @var State
     */
    protected State $appState;

    /**
     * @var StoreManagerInterface
     */
    protected StoreManagerInterface $storeManager;

    /**
     * @var SourceItemInterfaceFactory
     */
    protected SourceItemInterfaceFactory $sourceItem;

    /**
     * @var SourceItemsSaveInterface
     */
    protected SourceItemsSaveInterface $sourceItemsSaveInterface;

    /**
     * @var EavSetup
     */
    protected EavSetup $eavSetup;

    /**
     * @var array
     */
    protected array $sourceItems = [];

    /**
     * @var CategoryCollectionFactory
     */
    protected CategoryCollectionFactory $categoryCollectionFactory;

    /**
     * Migration patch constructor.
     *
     * @param ModuleDataSetupInterface $setup,
     * @param ProductInterfaceFactory $productInterfaceFactory,
     * @param ProductRepositoryInterface $productRepository,
     * @param State $appState,
     * @param StoreManagerInterface $storeManager,
     * @param EavSetup $eavSetup,
     * @param SourceItemInterfaceFactory $sourceItem,
     * @param SourceItemsSaveInterface $sourceItemsSaveInterface,
     * @param CategoryLinkManagementInterface $categoryLink,
     * @param LoggerInterface $logger,
     * @param CategoryCollectionFactory $categoryCollectionFactory
     */
    public function __construct(
        ModuleDataSetupInterface $setup,
        ProductInterfaceFactory $productInterfaceFactory,
        ProductRepositoryInterface $productRepository,
        State $appState,
        StoreManagerInterface $storeManager,
        EavSetup $eavSetup,
        SourceItemInterfaceFactory $sourceItem,
        SourceItemsSaveInterface $sourceItemsSaveInterface,
        CategoryLinkManagementInterface $categoryLink,
        LoggerInterface $logger,
        CategoryCollectionFactory $categoryCollectionFactory
    ) {
        $this->appState = $appState;
        $this->productInterfaceFactory = $productInterfaceFactory;
        $this->productRepository = $productRepository;
        $this->setup = $setup;
        $this->eavSetup = $eavSetup;
        $this->storeManager = $storeManager;
        $this->sourceItem = $sourceItem;
        $this->sourceItemsSaveInterface = $sourceItemsSaveInterface;
        $this->categoryLink = $categoryLink;
        $this->logger = $logger;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
    }

    /**
     * Add new product
     */
    public function apply()
    {
        $this->appState->emulateAreaCode('adminhtml', [$this, 'execute']);
    }

    /**
     * @throws CouldNotSaveException
     * @throws InputException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws ValidationException
     */
    public function execute()
    {
        $product = $this->productInterfaceFactory->create();

        if ($product->getIdBySku('high-heel-shoe')) {
            return;
        }
        $attributeSetId = $this->eavSetup->getAttributeSetId(Product::ENTITY, 'Default');
        $websiteIDs = [$this->storeManager->getStore()->getWebsiteId()];
        $product->setTypeId(Type::TYPE_SIMPLE)
            ->setWebsiteIds($websiteIDs)
            ->setAttributeSetId($attributeSetId)
            ->setName('High Heel Shoe')
            ->setUrlKey('highheelshoe')
            ->setSku('high-heel-shoe')
            ->setPrice(44.49)
            ->setVisibility(Visibility::VISIBILITY_BOTH)
            ->setStatus(Status::STATUS_ENABLED)
            ->setStockData(['use_config_manage_stock' => 0, 'is_qty_decimal' => 0, 'is_in_stock' => 1]);
        $product = $this->productRepository->save($product);

        $sourceItem = $this->source$sourceItem->create();
        $sourceItem->setSourceCode('default');
        $sourceItem->setQuantity(20);
        $sourceItem->setSku($product->getSku());
        $sourceItem->setStatus(SourceItemInterface::STATUS_IN_STOCK);
        $this->sourceItems[] = $sourceItem;

        $this->sourceItemsSaveInterface->execute($this->sourceItems);

        // Checkes whether the category women exist
        try 
        {
            $categoryIDs = $this->categoryCollectionFactory->create()->addAttributeToFilter('name', 'Women')->getAllIds();
            if (count($categoryIDs))
            {
                $this->categoryLink->assignProductToCategories($product->getSku(), $categoryIDs);
            }
        } 
        catch (NoSuchEntityException $ex) 
        {
            $this->logger->critical($ex);
        }
    }
}