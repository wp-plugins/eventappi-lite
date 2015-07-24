<?php namespace EventAppi;

use EventAppi\Helpers\Logger;
use EventAppi\Helpers\Options;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use PhilipBrown\Signature\Request as HmacRequest;
use PhilipBrown\Signature\Token as HmacToken;

class ApiClient implements ApiClientInterface
{

    private static $singleton   = null;
    private        $httpClient  = null;
    private        $apiEndpoint = null;
    private        $method;
    private        $function;

    private function __construct(HttpClient $client)
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        $this->httpClient = $client;
    }

    public static function instance(HttpClient $client = null)
    {

        if (is_null(self::$singleton)) {

            if (is_null($client)) {
                $client = new HttpClient();
            }

            self::$singleton = new self($client);
        }

        return self::$singleton;
    }

    private function hmacEncode(array $data = [])
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        $license = Options::instance()->getPluginOption('license_key');
        $token   = new HmacToken($license, 'eventappikey');
        $request = new HmacRequest($this->method, 'api/v1/' . $this->function, $data);
        $auth    = $request->sign($token);

        $data = array_merge($auth, $data);

        return $data;
    }

    private function filterForNullValues(&$item, $key)
    {
        if (is_null($item)) {
            $item = '';
        }
    }

    private function sendRequest(array $data = [])
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        $key = 'body'; // put parameters in the request body
        if (strtolower($this->method) === 'get' or strtolower($this->method) === 'delete') {
            // unless we're sending a GET or DELETE request, in which case we use the QueryString
            $key = 'query';
        }

        array_walk($data, array($this, 'filterForNullValues'));
        $query = array(
            $key => $this->hmacEncode($data)
        );

        try {
            $request = $this->httpClient->createRequest(
                $this->method,
                $this->getApiEndpoint() . '/' . $this->function,
                $query
            );

            return $this->httpClient->send($request)->json();
        } catch (ClientException $e) {
            // The API returned an error response, so we should get the response JSON object
            $br   = '';
            $body = $e->getResponse()->getBody();
            while (!$body->eof()) {
                $br .= $body->read(1024);
            }
            $apiResponse = json_decode($br, true);

            Logger::instance()->log(
                __FILE__,
                __FUNCTION__,
                [
                    'result'   => 'API Request failed.',
                    'function' => $this->function,
                    'method'   => $this->method,
                    'code'     => $apiResponse['code'],
                    'message'  => $apiResponse['error']['message']
                ],
                Logger::LOG_LEVEL_WARNING
            );

            return $apiResponse;

        } catch (RequestException $e) {
            Logger::instance()->log(
                __FILE__,
                __FUNCTION__,
                [
                    'result'   => 'API Request failed.',
                    'function' => $this->function,
                    'method'   => $this->method,
                    'query'    => $query,
                    'code'     => $e->getCode(),
                    'message'  => $e->getMessage()
                ],
                Logger::LOG_LEVEL_WARNING
            );

            return [
                'error'   => $e->getCode(),
                'message' => $e->getMessage()
            ];
        }
    }

    public function setApiEndpoint($url)
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        $this->apiEndpoint = $url;
    }

    public function getApiEndpoint()
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        if (is_null($this->apiEndpoint)) {
            $ep = Options::instance()->getPluginOption('api_endpoint');
            $this->setApiEndpoint($ep);
        }

        return $this->apiEndpoint;
    }

    public function addLicenseKey($keyData)
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        $this->method   = 'post';
        $this->function = 'license-key';

        return $this->sendRequest($keyData);
    }

    public function checkEventAppiLicenseKey($apiKey)
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        if ($apiKey === false) {
            return 'invalid';
        }

        $this->method   = 'get';
        $this->function = "license-key/{$apiKey}";

        return $this->sendRequest();
    }

    public function listAllCountries()
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        $this->method   = 'get';
        $this->function = 'country';

        return $this->sendRequest();
    }

    public function showCountry($id)
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        $this->method   = 'get';
        $this->function = "country/{$id}";

        return $this->sendRequest();
    }

    public function showEventStats()
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        $this->method   = 'get';
        $this->function = 'event/stats';

        return $this->sendRequest();
    }

    public function showEventTicketSales($fromDate, $toDate)
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        $this->method   = 'get';
        $this->function = "event/stats-ticket-sales/{$fromDate}/{$toDate}";

        return $this->sendRequest();
    }

    public function listAllEvents()
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        $this->method   = 'get';
        $this->function = 'event';

        return $this->sendRequest();
    }

    public function createEvent()
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        $this->method   = 'get';
        $this->function = 'event/create';

        return $this->sendRequest();
    }

    public function storeEvent($data)
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        $this->method   = 'post';
        $this->function = 'event';

        return $this->sendRequest($data);
    }

    public function showEvent($id)
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        $this->method   = 'get';
        $this->function = "event/{$id}";

        return $this->sendRequest();
    }

    public function editEvent($id)
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        $this->method   = 'get';
        $this->function = "event/{$id}/edit";

        return $this->sendRequest();
    }

    public function updateEvent($id, $data)
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        $this->method   = 'put';
        $this->function = "event/{$id}";

        return $this->sendRequest($data);
    }

    public function destroyEvent($id)
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        $this->method   = 'delete';
        $this->function = "event/{$id}";

        return $this->sendRequest();
    }

    public function showEventVenue($event)
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        $this->method   = 'get';
        $this->function = "event/{$event}/venue";

        return $this->sendRequest();
    }

    public function incrementEventViewCount($event)
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        $this->method   = 'post';
        $this->function = "event/{$event}/views";

        return $this->sendRequest();
    }

    public function listAllVenues()
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        $this->method   = 'get';
        $this->function = 'venue';

        return $this->sendRequest();
    }

    public function createVenue()
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        $this->method   = 'get';
        $this->function = 'venue/create';

        return $this->sendRequest();
    }

    public function storeVenue($data)
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        $this->method   = 'post';
        $this->function = 'venue';

        return $this->sendRequest($data);
    }

    public function showVenue($id)
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        $this->method   = 'get';
        $this->function = "venue/{$id}";

        return $this->sendRequest();
    }

    public function editVenue($id)
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        $this->method   = 'get';
        $this->function = "venue/{$id}/edit";

        return $this->sendRequest();
    }

    public function updateVenue($id, $data)
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        $this->method   = 'put';
        $this->function = "venue/{$id}";

        return $this->sendRequest($data);
    }

    public function destroyVenue($id)
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        $this->method   = 'delete';
        $this->function = "venue/{$id}";

        return $this->sendRequest();
    }

    public function listAllEventsAtVenue($venue)
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        $this->method   = 'get';
        $this->function = "venue/{$venue}/event";

        return $this->sendRequest();
    }

    public function listAllTickets($event)
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        $this->method   = 'get';
        $this->function = "event/{$event}/ticket";

        return $this->sendRequest();
    }

    public function createTicket($event = 0)
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        $this->method   = 'get';
        $this->function = "event/{$event}/ticket/create";

        return $this->sendRequest();
    }

    public function storeTicket($event, $data)
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        $this->method   = 'post';
        $this->function = "event/{$event}/ticket";

        return $this->sendRequest($data);
    }

    public function showTicket($event, $id)
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        $this->method   = 'get';
        $this->function = "event/{$event}/ticket/{$id}";

        return $this->sendRequest();
    }

    public function editTicket($event, $id)
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        $this->method   = 'get';
        $this->function = "event/{$event}/ticket/{$id}/edit";

        return $this->sendRequest();
    }

    public function updateTicket($event, $id, $data)
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        $this->method   = 'put';
        $this->function = "event/{$event}/ticket/{$id}";

        return $this->sendRequest($data);
    }

    public function destroyTicket($event, $id)
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        $this->method   = 'delete';
        $this->function = "event/{$event}/ticket/{$id}";

        return $this->sendRequest();
    }

    public function createPurchase()
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        $this->method   = 'get';
        $this->function = 'purchase/create';

        return $this->sendRequest();
    }

    public function storePurchase($data)
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        $this->method   = 'post';
        $this->function = 'purchase';

        return $this->sendRequest($data);
    }

    public function showPurchase($id)
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        $this->method   = 'get';
        $this->function = "purchase/{$id}";

        return $this->sendRequest();
    }

    public function editPurchase($id)
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        $this->method   = 'get';
        $this->function = "purchase/{$id}/edit";

        return $this->sendRequest();
    }

    public function updatePurchase($id, $data)
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        $this->method   = 'put';
        $this->function = "purchase/{$id}";

        return $this->sendRequest($data);
    }

    public function destroyPurchase($id)
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        $this->method   = 'delete';
        $this->function = "purchase/{$id}";

        return $this->sendRequest();
    }

    public function emailAllPurchasedTickets($purchase)
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        $this->method   = 'post';
        $this->function = "email/purchase/$purchase";

        return $this->sendRequest();
    }

    public function emailPurchasedTicket($ticket, $recipientData = null)
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        $this->method   = 'post';
        $this->function = "email/purchasedticket/{$ticket}";

        if (!is_null($recipientData)) {
            return $this->sendRequest($recipientData);
        }

        return $this->sendRequest();
    }

    public function sendTicketToThirdParty($ticket, $recipientData)
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        $this->method   = 'post';
        $this->function = "email/thirdpartysend/{$ticket}";

        return $this->sendRequest($recipientData);
    }

    public function listEventAttendees($organiser, $event)
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        $this->method   = 'get';
        $this->function = "organiser/{$organiser}/event/{$event}/attendees";

        return $this->sendRequest();
    }

    public function setAttendeeCheckinStatus($organiser, $event, $attendee, $status = 'in')
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        $this->method   = 'put';
        $this->function = "organiser/{$organiser}/event/{$event}/attendee/{$attendee}/status";

        return $this->sendRequest([
            'checkedIn' => ($status === 'in') ? 'true' : 'false'
        ]);
    }

    public function listAllUsers()
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        $this->method   = 'get';
        $this->function = 'user';

        $args = func_get_args();
        if (is_array($args) and count($args) == 1) {
            $args = $args[0];
        }

        return $this->sendRequest($args);
    }

    public function createUser()
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        $this->method   = 'get';
        $this->function = 'user/create';

        return $this->sendRequest();
    }

    public function storeUser($data)
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        $this->method   = 'post';
        $this->function = 'user';

        return $this->sendRequest($data);
    }

    public function showUser($id)
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        $this->method   = 'get';
        $this->function = "user/{$id}";

        return $this->sendRequest();
    }

    public function editUser($id)
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        $this->method   = 'get';
        $this->function = "user/{$id}/edit";

        return $this->sendRequest();
    }

    public function updateUser($id, $data)
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        $this->method   = 'put';
        $this->function = "user/{$id}";

        return $this->sendRequest($data);
    }

    public function destroyUser($id)
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        $this->method   = 'delete';
        $this->function = "user/{$id}";

        return $this->sendRequest();
    }

    public function checkUserPassword($id, $plaintextPassword)
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        $this->method   = 'get';
        $this->function = "user/{$id}/check-password/{$plaintextPassword}";

        return $this->sendRequest();
    }

    public function checkUserHash($id, $hashedPassword)
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        $this->method   = 'get';
        $this->function = "user/{$id}/check-hashed-password/{$hashedPassword}";

        return $this->sendRequest();
    }

    public function resetUserPassword($id)
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        $this->method   = 'get';
        $this->function = "user/{$id}/reset-password";

        return $this->sendRequest();
    }

    public function confirmUserEmail($id, $key)
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        $this->method   = 'get';
        $this->function = "user/{$id}/confirm-email/{$key}";

        return $this->sendRequest();
    }

    public function listAllUserFields()
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        $this->method   = 'get';
        $this->function = 'userField';

        $args = func_get_args();
        if (is_array($args) and count($args) == 1) {
            $args = $args[0];
        }

        return $this->sendRequest($args);
    }

    public function createUserField()
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        $this->method   = 'get';
        $this->function = 'userField/create';

        return $this->sendRequest();
    }

    public function storeUserField($data)
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        $this->method   = 'post';
        $this->function = 'userField';

        return $this->sendRequest($data);
    }

    public function showUserField($id)
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        $this->method   = 'get';
        $this->function = "userField/{$id}";

        return $this->sendRequest();
    }

    public function editUserField($id)
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        $this->method   = 'get';
        $this->function = "userField/{$id}/edit";

        return $this->sendRequest();
    }

    public function updateUserField($id, $data)
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        $this->method   = 'put';
        $this->function = "userField/{$id}";

        return $this->sendRequest($data);
    }

    public function destroyUserField($id)
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        $this->method   = 'delete';
        $this->function = "userField/{$id}";

        return $this->sendRequest();
    }

    public function listAllUserTypes()
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        $this->method   = 'get';
        $this->function = 'userType';

        return $this->sendRequest();
    }

    public function createUserType()
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        $this->method   = 'get';
        $this->function = 'userType/create';

        return $this->sendRequest();
    }

    public function storeUserType($data)
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        $this->method   = 'post';
        $this->function = 'userType';

        return $this->sendRequest($data);
    }

    public function showUserType($id)
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        $this->method   = 'get';
        $this->function = "userType/{$id}";

        return $this->sendRequest();
    }

    public function editUserType($id)
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        $this->method   = 'get';
        $this->function = "userType/{$id}/edit";

        return $this->sendRequest();
    }

    public function updateUserType($id, $data)
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        $this->method   = 'put';
        $this->function = "userType/{$id}";

        return $this->sendRequest($data);
    }

    public function destroyUserType($id)
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        $this->method   = 'delete';
        $this->function = "userType/{$id}";

        return $this->sendRequest();
    }
}