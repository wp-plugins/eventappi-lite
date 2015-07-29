<?php
namespace EventAppi;

class Currency
{
    /**
    * @var Currency|null
    */
    private static $singleton = null;

    /**
    *
    */
    private function __construct()
    {
    }

    /**
    * @return Currency|null
    */
    public static function instance()
    {
        if (is_null(self::$singleton)) {
            self::$singleton = new self();
        }

        return self::$singleton;
    }

    public function getCurrencies()
    {
		return array_unique(array(
			'AED' => __('United Arab Emirates Dirham', EVENTAPPI_PLUGIN_NAME),
			'AUD' => __('Australian Dollars', EVENTAPPI_PLUGIN_NAME),
			'BDT' => __('Bangladeshi Taka', EVENTAPPI_PLUGIN_NAME),
			'BRL' => __('Brazilian Real', EVENTAPPI_PLUGIN_NAME),
			'BGN' => __('Bulgarian Lev', EVENTAPPI_PLUGIN_NAME),
			'CAD' => __('Canadian Dollars', EVENTAPPI_PLUGIN_NAME),
			'CLP' => __('Chilean Peso', EVENTAPPI_PLUGIN_NAME),
			'CNY' => __('Chinese Yuan', EVENTAPPI_PLUGIN_NAME),
			'COP' => __('Colombian Peso', EVENTAPPI_PLUGIN_NAME),
			'CZK' => __('Czech Koruna', EVENTAPPI_PLUGIN_NAME),
			'DKK' => __('Danish Krone', EVENTAPPI_PLUGIN_NAME),
			'DOP' => __('Dominican Peso', EVENTAPPI_PLUGIN_NAME),
			'EUR' => __('Euros', EVENTAPPI_PLUGIN_NAME),
			'HKD' => __('Hong Kong Dollar', EVENTAPPI_PLUGIN_NAME),
			'HRK' => __('Croatia kuna', EVENTAPPI_PLUGIN_NAME),
			'HUF' => __('Hungarian Forint', EVENTAPPI_PLUGIN_NAME),
			'ISK' => __('Icelandic krona', EVENTAPPI_PLUGIN_NAME),
			'IDR' => __('Indonesia Rupiah', EVENTAPPI_PLUGIN_NAME),
			'INR' => __('Indian Rupee', EVENTAPPI_PLUGIN_NAME),
			'NPR' => __('Nepali Rupee', EVENTAPPI_PLUGIN_NAME),
			'ILS' => __('Israeli Shekel', EVENTAPPI_PLUGIN_NAME),
			'JPY' => __('Japanese Yen', EVENTAPPI_PLUGIN_NAME),
			'KIP' => __('Lao Kip', EVENTAPPI_PLUGIN_NAME),
			'KRW' => __('South Korean Won', EVENTAPPI_PLUGIN_NAME),
			'MYR' => __('Malaysian Ringgits', EVENTAPPI_PLUGIN_NAME),
			'MXN' => __('Mexican Peso', EVENTAPPI_PLUGIN_NAME),
			'NGN' => __('Nigerian Naira', EVENTAPPI_PLUGIN_NAME),
			'NOK' => __('Norwegian Krone', EVENTAPPI_PLUGIN_NAME),
			'NZD' => __('New Zealand Dollar', EVENTAPPI_PLUGIN_NAME),
			'PYG' => __('Paraguayan Guaraní', EVENTAPPI_PLUGIN_NAME),
			'PHP' => __('Philippine Pesos', EVENTAPPI_PLUGIN_NAME),
			'PLN' => __('Polish Zloty', EVENTAPPI_PLUGIN_NAME),
			'GBP' => __('Pounds Sterling', EVENTAPPI_PLUGIN_NAME),
			'RON' => __('Romanian Leu', EVENTAPPI_PLUGIN_NAME),
			'RUB' => __('Russian Ruble', EVENTAPPI_PLUGIN_NAME),
			'SGD' => __('Singapore Dollar', EVENTAPPI_PLUGIN_NAME),
			'ZAR' => __('South African rand', EVENTAPPI_PLUGIN_NAME),
			'SEK' => __('Swedish Krona', EVENTAPPI_PLUGIN_NAME),
			'CHF' => __('Swiss Franc', EVENTAPPI_PLUGIN_NAME),
			'TWD' => __('Taiwan New Dollars', EVENTAPPI_PLUGIN_NAME),
			'THB' => __('Thai Baht', EVENTAPPI_PLUGIN_NAME),
			'TRY' => __('Turkish Lira', EVENTAPPI_PLUGIN_NAME),
			'UAH' => __('Ukrainian Hryvnia', EVENTAPPI_PLUGIN_NAME),
			'USD' => __('US Dollars', EVENTAPPI_PLUGIN_NAME),
			'VND' => __('Vietnamese Dong', EVENTAPPI_PLUGIN_NAME),
			'EGP' => __('Egyptian Pound', EVENTAPPI_PLUGIN_NAME),
		));
	}

    public function getCurrencySymbol($currency)
    {
        switch ($currency) {
			case 'AED':
				$currencySymbol = 'د.إ';
				break;
			case 'AUD':
			case 'CAD':
			case 'CLP':
			case 'COP':
			case 'HKD':
			case 'MXN':
			case 'NZD':
			case 'SGD':
			case 'USD':
				$currencySymbol = '&#36;';
				break;
			case 'BDT':
				$currencySymbol = '&#2547;&nbsp;';
				break;
			case 'BGN':
				$currencySymbol = '&#1083;&#1074;.';
				break;
			case 'BRL':
				$currencySymbol = '&#82;&#36;';
				break;
			case 'CHF':
				$currencySymbol = '&#67;&#72;&#70;';
				break;
			case 'CNY':
			case 'JPY':
			case 'RMB':
				$currencySymbol = '&yen;';
				break;
			case 'CZK':
				$currencySymbol = '&#75;&#269;';
				break;
			case 'DKK':
				$currencySymbol = 'kr.';
				break;
			case 'DOP':
				$currencySymbol = 'RD&#36;';
				break;
			case 'EGP':
				$currencySymbol = 'EGP';
				break;
			case 'EUR':
				$currencySymbol = '&euro;';
				break;
			case 'GBP':
				$currencySymbol = '&pound;';
				break;
			case 'HRK':
				$currencySymbol = 'Kn';
				break;
			case 'HUF':
				$currencySymbol = '&#70;&#116;';
				break;
			case 'IDR':
				$currencySymbol = 'Rp';
				break;
			case 'ILS':
				$currencySymbol = '&#8362;';
				break;
			case 'INR':
				$currencySymbol = 'Rs.';
				break;
			case 'ISK':
				$currencySymbol = 'Kr.';
				break;
			case 'KIP':
				$currencySymbol = '&#8365;';
				break;
			case 'KRW':
				$currencySymbol = '&#8361;';
				break;
			case 'MYR':
				$currencySymbol = '&#82;&#77;';
				break;
			case 'NGN':
				$currencySymbol = '&#8358;';
				break;
			case 'NOK':
				$currencySymbol = '&#107;&#114;';
				break;
			case 'NPR':
				$currencySymbol = 'Rs.';
				break;
			case 'PHP':
				$currencySymbol = '&#8369;';
				break;
			case 'PLN':
				$currencySymbol = '&#122;&#322;';
				break;
			case 'PYG':
				$currencySymbol = '&#8370;';
				break;
			case 'RON':
				$currencySymbol = 'lei';
				break;
			case 'RUB':
				$currencySymbol = '&#1088;&#1091;&#1073;.';
				break;
			case 'SEK':
				$currencySymbol = '&#107;&#114;';
				break;
			case 'THB':
				$currencySymbol = '&#3647;';
				break;
			case 'TRY':
				$currencySymbol = '&#8378;';
				break;
			case 'TWD':
				$currencySymbol = '&#78;&#84;&#36;';
				break;
			case 'UAH':
				$currencySymbol = '&#8372;';
				break;
			case 'VND':
				$currencySymbol = '&#8363;';
				break;
			case 'ZAR':
				$currencySymbol = '&#82;';
				break;
			default :
				$currencySymbol = '';
				break;
		}
		return $currencySymbol;
	}

	public function getList() {
		$list = array();

		foreach ($this->getCurrencies() as $currencyCode => $currencyName) {
			$list[$currencyCode] = $currencyName . ' ('.$this->getCurrencySymbol($currencyCode).')';
		}

		return $list;
	}
}
