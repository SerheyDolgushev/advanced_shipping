<?php
/**
 * @package AdvancedShipping
 * @class   advancedShippingHandler
 * @author  Serhey Dolgushev <dolgushev.serhey@gmail.com>
 * @date    27 Nov 2012
 **/

class advancedShippingHandler
{
	private static $optionsMapping = array(
		'Description'        => 'description',
		'RequiredOrderTotal' => 'required_order_total',
		'DefaultCost'        => 'default_cost',
		'PerItemCost'        => 'per_item_cost',
		'MinCost'            => 'min_cost',
		'MaxCost'            => 'max_cost'
	);
	private $rules = array();

	public function __construct() {
		$ini = eZINI::instance( 'shipping.ini' );
		if( $ini->hasVariable( 'Shipping', 'Rules' ) ) {
			$rules = (array) $ini->variable( 'Shipping', 'Rules' );
			foreach( $rules as $ruleName ) {
				$ruleIniGroup = 'Rule_' . $ruleName;
				if( $ini->hasSection( $ruleIniGroup ) === false ) {
					continue;
				}

				$ruleOptions = array();
				foreach( self::$optionsMapping as $iniVariable => $option ) {
					if( $ini->hasVariable( $ruleIniGroup, $iniVariable ) ) {
						$ruleOptions[ $option ] = $ini->variable( $ruleIniGroup, $iniVariable );
					}
				}

				if(
					isset( $ruleOptions['required_order_total'] ) === false
					|| (
						isset( $ruleOptions['default_cost'] ) === false
						&& isset( $ruleOptions['per_item_cost'] ) === false
					)
				) {
					continue;
				}

				$this->rules[ $ruleName ] = $ruleOptions;
			}
		}
	}

	public function getShippingInfo( $productCollectionID ) {
		$rule = $this->getCurrentRule();
		if( $rule === false ) {
			return null;
		}

		$cost = 0;
		if( isset( $rule['per_item_cost'] ) ) {
			$itemsCount = 0;
			$items      = eZProductCollection::fetch( $productCollectionID )->itemList();
			foreach( $items as $item ) {
				$itemsCount += $item->attribute( 'item_count' );
			}
			$cost = $itemsCount * (float) $rule['per_item_cost'];
		} else {
			$cost = (float) $rule['default_cost'];
		}

		if( isset( $rule['min_cost'] ) ) {
			$cost = max( $cost, (float) $rule['min_cost'] );
		}
		if( isset( $rule['max_cost'] ) ) {
			$cost = min( $cost, (float) $rule['max_cost'] );
		}

		return array(
			'description' => $rule['description'],
			'cost'        => $cost,
			'vat_value'   => false,
			'is_vat_inc'  => 1
		);
	}

	public function updateShippingInfo( $productCollectionID ) {
		return null;
	}

	public function purgeShippingInfo( $productCollectionID ) {
		return null;
	}

	private function getCurrentRule() {
		$requiredOrderTotals = array();
		foreach( $this->rules as $name => $rule ) {
			$requiredOrderTotals[ $name ] = (float) $rule['required_order_total'];
		}
		arsort( $requiredOrderTotals );

		$total = eZBasket::currentBasket()->attribute( 'total_inc_vat' );
		foreach( $requiredOrderTotals as $ruleName => $requiredOrderTotal ) {
			if( $total >= $requiredOrderTotal ) {
				return $this->rules[ $ruleName ];
			}
		}
	}
}
