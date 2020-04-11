<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\GoogleAdwords\Test\Unit\Observer;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Magento\GoogleAdwords\Observer\SetConversionValueObserver;
use Magento\Framework\Registry;
use Magento\Sales\Model\ResourceModel\Order\Collection;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\GoogleAdwords\Helper\Data;

class SetConversionValueObserverTest extends TestCase
{
    /**
     * @var MockObject
     */
    protected $_helperMock;

    /**
     * @var MockObject
     */
    protected $_collectionMock;

    /**
     * @var MockObject
     */
    protected $_registryMock;

    /**
     * @var MockObject
     */
    protected $_eventObserverMock;

    /**
     * @var MockObject
     */
    protected $_eventMock;

    /**
     * @var SetConversionValueObserver
     */
    protected $_model;

    protected function setUp(): void
    {
        $this->_helperMock = $this->createMock(Data::class);
        $this->_registryMock = $this->createMock(Registry::class);
        $this->_collectionMock = $this->createMock(Collection::class);
        $this->_eventObserverMock = $this->createMock(Observer::class);
        $this->_eventMock = $this->createPartialMock(Event::class, ['getOrderIds']);

        $objectManager = new ObjectManager($this);
        $this->_model = $objectManager->getObject(
            SetConversionValueObserver::class,
            [
                'helper' => $this->_helperMock,
                'collection' => $this->_collectionMock,
                'registry' => $this->_registryMock
            ]
        );
    }

    /**
     * @return array
     */
    public function dataProviderForDisabled()
    {
        return [[false, false], [false, true], [true, false]];
    }

    /**
     * @param bool $isActive
     * @param bool $isDynamic
     * @dataProvider dataProviderForDisabled
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function testSetConversionValueWhenAdwordsDisabled($isActive, $isDynamic)
    {
        $this->_helperMock->expects(
            $this->once()
        )->method(
            'isGoogleAdwordsActive'
        )->will(
            $this->returnValue($isActive)
        );
        $this->_helperMock->expects($this->any())->method('isDynamicConversionValue')->will(
            $this->returnCallback(
                function () use ($isDynamic) {
                    return $isDynamic;
                }
            )
        );

        $this->_eventMock->expects($this->never())->method('getOrderIds');
        $this->assertSame($this->_model, $this->_model->execute($this->_eventObserverMock));
    }

    /**
     * @return array
     */
    public function dataProviderForOrdersIds()
    {
        return [[[]], ['']];
    }

    /**
     * @param $ordersIds
     * @dataProvider dataProviderForOrdersIds
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function testSetConversionValueWhenAdwordsActiveWithoutOrdersIds($ordersIds)
    {
        $this->_helperMock->expects($this->once())->method('isGoogleAdwordsActive')->will($this->returnValue(true));
        $this->_helperMock->expects($this->once())->method('isDynamicConversionValue')->will($this->returnValue(true));
        $this->_eventMock->expects($this->once())->method('getOrderIds')->will($this->returnValue($ordersIds));
        $this->_eventObserverMock->expects(
            $this->once()
        )->method(
            'getEvent'
        )->will(
            $this->returnValue($this->_eventMock)
        );
        $this->_collectionMock->expects($this->never())->method('addFieldToFilter');

        $this->assertSame($this->_model, $this->_model->execute($this->_eventObserverMock));
    }

    /**
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function testSetConversionValueWhenAdwordsActiveWithOrdersIds()
    {
        $ordersIds = [1, 2, 3];
        $conversionValue = 0;
        $conversionCurrency = 'USD';
        $this->_helperMock->expects($this->once())->method('isGoogleAdwordsActive')->will($this->returnValue(true));
        $this->_helperMock->expects($this->once())->method('isDynamicConversionValue')->will($this->returnValue(true));
        $this->_helperMock->expects($this->once())->method('hasSendConversionValueCurrency')
            ->will($this->returnValue(true));
        $this->_eventMock->expects($this->once())->method('getOrderIds')->will($this->returnValue($ordersIds));
        $this->_eventObserverMock->expects(
            $this->once()
        )->method(
            'getEvent'
        )->will(
            $this->returnValue($this->_eventMock)
        );

        $orderMock = $this->createMock(OrderInterface::class);
        $orderMock->expects($this->once())->method('getOrderCurrencyCode')->willReturn($conversionCurrency);

        $iteratorMock = new \ArrayIterator([$orderMock]);
        $this->_collectionMock->expects($this->any())->method('getIterator')->will($this->returnValue($iteratorMock));
        $this->_collectionMock->expects(
            $this->once()
        )->method(
            'addFieldToFilter'
        )->with(
            'entity_id',
            ['in' => $ordersIds]
        );
        $this->_registryMock->expects(
            $this->atLeastOnce()
        )->method(
            'register'
        )->withConsecutive(
            [
                Data::CONVERSION_VALUE_CURRENCY_REGISTRY_NAME,
                $conversionCurrency
            ],
            [
                Data::CONVERSION_VALUE_REGISTRY_NAME,
                $conversionValue,
            ]
        );

        $this->assertSame($this->_model, $this->_model->execute($this->_eventObserverMock));
    }
}
