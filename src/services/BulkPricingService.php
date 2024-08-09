<?php
/**
 * Commerce Bulk Pricing plugin for Craft CMS 4.x Commerce 4.x
 *
 * Bulk pricing for products
 *
 * @link      https://webdna.co.uk
 * @copyright Copyright (c) 2022 webdna
 */

namespace webdna\commerce\bulkpricing\services;

use webdna\commerce\bulkpricing\fields\BulkPricingField;

use Craft;
use craft\elements\User;

use craft\commerce\models\LineItem;
use craft\commerce\records\Sale as SaleRecord;
use craft\commerce\events\LineItemEvent;

use yii\base\Component;

/**
 * Bulk Pricing service.
 *
 * @author webdna
 * @since 2.0
 */
class BulkPricingService extends Component
{
    /**
     * @event LineItemEvent The event that is triggered after bulk pricing has been applied to lineitem
     */
    public const EVENT_APPLY_BULK_PRICING = 'applyBulkPricing';

    /**
     * Calculate appropriate bulk price for lineItem
     *
     * @param LineItem $lineItem The line item to to calculate bulk price for.
     * @return LineItem
     */
    public function applyBulkPricing(LineItem $lineItem, ?User $user, string $paymentCurrency): LineItem
    {
        $variants = $lineItem->purchasable->product->getVariants();
        $element = (count($variants) > 1) ? $lineItem->purchasable : $lineItem->purchasable->product;

        if ($element) {
            foreach ($element->getFieldValues() as $key => $field)
            {
                if ( (get_class($f = Craft::$app->getFields()->getFieldByHandle($key)) == BulkPricingField::class) && (is_array($field)) ) {
                    $apply = false;

                    if($user || $f->guestUser){

                        if(is_array($f->userGroups) && count($f->userGroups) > 0) {
                            foreach ($f->userGroups as $group)
                            {
                                if ($user->isInGroup($group)) {
                                    $apply = true;
                                }
                            }
                        } else {
                            $apply = true;
                        }

                        if ($apply && (array_key_exists($paymentCurrency,$field))) {

                            foreach ($field[$paymentCurrency] as $qty => $value)
                            {
                                if ($qty != 'iso' && $lineItem->qty >= $qty && $value != '') {
                                    $lineItem->price = $value;
                                    if ($lineItem->purchasable->getOnPromotion()) {
                                        $newPrice = null;
                                        $newPrice = $lineItem->getSalePrice();

                                        // A newPrice has been set so use it.
                                        if (null !== $newPrice) {
                                            $salePrice = $newPrice;
                                        }

                                        if ($salePrice < 0) {
                                            $salePrice = 0;
                                        }

                                        $lineItem->setPromotionalPrice($salePrice);
                                    } else {
                                        $lineItem->setPromotionalPrice($value);
                                    }

                                    // TODO: This no longer works
                                    // $lineItem->snapshot['taxIncluded'] = (bool)$f->taxIncluded;
                                }
                            }

                            continue;
                        }
                    }
                }
            }

            if ($this->hasEventHandlers(self::EVENT_APPLY_BULK_PRICING)) {
                $this->trigger(self::EVENT_APPLY_BULK_PRICING, new LineItemEvent([
                    'lineItem' => $lineItem,
                    'isNew' => false,
                ]));
            }
        }


        return $lineItem;
    }

}
