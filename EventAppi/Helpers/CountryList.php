<?php namespace EventAppi\Helpers;

use EventAppi\ApiClient;
use EventAppi\ApiClientInterface;

/**
 * Class CountryList
 *
 * @package EventAppi\Helpers
 */
class CountryList
{

    /**
     * @var CountryList|null
     */
    private static $singleton = null;

    /**
     * @var ApiClientInterface|null
     */
    private static $apiClient = null;

    /**
     * @var array|null
     */
    private static $countryList = null;

    /**
     * @var int|null
     */
    private static $listTimer = null;

    /**
     * @param ApiClientInterface $client
     */
    private function __construct(ApiClientInterface $client)
    {
        self::$apiClient   = $client;
        self::$countryList = array();
        self::$listTimer   = 1426768000;
    }

    /**
     * @param ApiClientInterface $client
     *
     * @return CountryList|null
     */
    public static function instance(ApiClientInterface $client = null)
    {
        if (is_null(self::$singleton)) {
            if (is_null($client)) {
                // use concrete implementation of the ApiClientInterface
                $client = ApiClient::instance();
            }

            self::$singleton = new self($client);
        }

        return self::$singleton;
    }

    /**
     * @return array
     */
    public function getCountryList()
    {
        if (((time() - self::$listTimer) / 60) <= 600 && is_array(self::$countryList)) {
            return self::$countryList;
        }

        $countries = self::$apiClient->listAllCountries();
        $countries = $countries['data'];

        $countryCode = array();
        $countryName = array();
        foreach ($countries as $key => $row) {
            $countryCode[$key] = $row['code'];
            $countryName[$key] = $row['name'];
        }
        array_multisort($countryName, SORT_ASC, $countryCode, SORT_ASC, $countries);
        foreach ($countries as $key => $row) {
            self::$countryList[$row['code']] = $row['name'];
        }

        return self::$countryList;
    }
}
