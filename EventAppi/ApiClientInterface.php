<?php namespace EventAppi;

interface ApiClientInterface
{
    // Success codes
    const RESPONSE_OK      = 1000;
    const RESPONSE_CREATED = 1001;
    const RESPONSE_UPDATED = 1002;
    const RESPONSE_DELETED = 1003;

    // 'Expected' error codes
    const RESPONSE_INVALID_LOGIN                = 2000;
    const RESPONSE_ALREADY_CHECKED_IN           = 2001;
    const RESPONSE_NOT_AVAILABLE_FOR_LITE_USERS = 2002;
    const RESPONSE_EVENT_TICKET_LIMIT_REACHED   = 2003;

    // Unexpected error codes (bug)
    const RESPONSE_ERROR                = 3000;
    const RESPONSE_SERVER_ERROR         = 3001;
    const RESPONSE_BAD_REQUEST          = 3002;
    const RESPONSE_NOT_FOUND            = 3003;
    const RESPONSE_NOT_AUTHORISED       = 3004;
    const RESPONSE_INVALID_RELATIONSHIP = 3005;

    public function setApiEndpoint($url);

    public function getApiEndpoint();

    public function addLicenseKey($keyData);

    public function checkEventAppiLicenseKey($apiKey);

    public function listAllCountries();

    public function showCountry($id);

    public function showEventStats();

    public function showEventTicketSales($fromDate, $toDate);

    public function listAllEvents();

    public function createEvent();

    public function storeEvent($data);

    public function showEvent($id);

    public function editEvent($id);

    public function updateEvent($id, $data);

    public function destroyEvent($id);

    public function showEventVenue($event);

    public function incrementEventViewCount($event);

    public function listAllVenues();

    public function createVenue();

    public function storeVenue($data);

    public function showVenue($id);

    public function editVenue($id);

    public function updateVenue($id, $data);

    public function destroyVenue($id);

    public function listAllEventsAtVenue($venue);

    public function listAllTickets($event);

    public function createTicket($event);

    public function storeTicket($event, $data);

    public function showTicket($event, $id);

    public function editTicket($event, $id);

    public function updateTicket($event, $id, $data);

    public function destroyTicket($event, $id);

    public function createPurchase();

    public function storePurchase($data);

    public function showPurchase($id);

    public function editPurchase($id);

    public function updatePurchase($id, $data);

    public function destroyPurchase($id);

    public function emailAllPurchasedTickets($purchase);

    public function emailPurchasedTicket($ticket, $recipientData = null);

    public function sendTicketToThirdParty($ticket, $recipientData);

    public function listEventAttendees($organiser, $event);

    public function setAttendeeCheckinStatus($organiser, $event, $attendee, $status = 'in');

    public function listAllUsers();

    public function createUser();

    public function storeUser($data);

    public function showUser($id);

    public function editUser($id);

    public function updateUser($id, $data);

    public function destroyUser($id);

    public function checkUserPassword($id, $plaintextPassword);

    public function checkUserHash($id, $hashedPassword);

    public function resetUserPassword($id);

    public function confirmUserEmail($id, $key);

    public function listAllUserFields();

    public function createUserField();

    public function storeUserField($data);

    public function showUserField($id);

    public function editUserField($id);

    public function updateUserField($id, $data);

    public function destroyUserField($id);

    public function listAllUserTypes();

    public function createUserType();

    public function storeUserType($data);

    public function showUserType($id);

    public function editUserType($id);

    public function updateUserType($id, $data);

    public function destroyUserType($id);
}
