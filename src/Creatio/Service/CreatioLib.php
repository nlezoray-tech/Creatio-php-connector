<?php
/**
 * CreatioLib class file
 *
 * Abstract base class for Creatio API access services
 *
 * PHP Version 7.4
 *
 * @category Service
 * @package  Creatio
 * @author   Nicolas Lézoray <nlezoray@gmail.com>
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link     http://api
 */
namespace Nlezoray\Creatio\Service;

use Nlezoray\Creatio\Adapter\CreatioAdapterInterface;

abstract class CreatioLib
{
    /** @var CreatioAdapterInterface */
    protected $adapter;

    public function setCreatioInstance(CreatioAdapterInterface $adapter): void
    {
        $this->adapter = $adapter;
    }   

    /**
     * Retrieves an order from its ID
     *
     * @param string $id UUID de la commande
     * @return array|null Données de la commande ou null si non trouvée
     */
    public function initOrderById( string $Id ) {
        $api_query = array('$select'=>'Id,Number,OwnerId,AccountId,Date,StatusId,ActualDate,Amount',
            '$filter' => urlencode("Id eq guid'{$Id}'")
        );
        $response = $this->adapter->get('OrderCollection', $api_query);
        $result = json_decode($response, true);

        if (!is_array($result) || empty($result['d']['results'][0]))
            return null;

        return $result['d']['results'][0];
    }

    /**
     * Retrieves an order from its Creatio number
     *
     * @param string $number numéro du devis
     * @return array|null Données du devis ou null si non trouvée
     */
    public function initOrderByNumber( string $number ) {
        $api_query = array(
            '$select' => 'Id,Number,OwnerId,AccountId',
            '$filter' => urlencode("Number eq '{$number}'")
        );
        $response = $this->adapter->get('OrderCollection', $api_query);
        $result = json_decode($response, true);

        if (!is_array($result) || empty($result['d']['results'][0]))
            return null;

        return $result['d']['results'][0];
    }

    /**
     * Retrieves the name of an order's status from its ID
     *
     * @param string $Id UUID du statut de commande.
     * @return string|null Nom du statut ou null si non trouvé.
     */
    public function getOrderStatutById($Id)
    {
        $api_query = [
            '$select' => 'Name',
            '$filter' => urlencode("Id eq guid'{$Id}'"),
        ];

        $response = $this->adapter->get('OrderStatusCollection', $api_query);
        $result = json_decode($response, true);

        if (!is_array($result) || empty($result['d']['results'][0]['Name'])) {
            return null;
        }

        return $result['d']['results'][0]['Name'];
    }

    /**
     * Add a command in Creatio
     *
     * @param array $tabObject Données de la commande à créer.
     * @return string|null ID de la commande créée ou null en cas d’échec.
     */
    public function addOrder(array $tabObject): ?string
    {
        $response = $this->adapter->post('OrderCollection', $tabObject);
        $result = json_decode($response, true);

        if (!is_array($result) || empty($result['d']['Id'])) {
            return null;
        }

        return $result['d']['Id'];
    }

    /**
     * Updates an existing order in Creatio
     *
     * @param string $id ID de la commande à mettre à jour.
     * @param array $tabObject Données à modifier.
     * @return mixed Résultat brut de la requête PUT.
     */
    public function updateOrder(string $id, array $tabObject)
    {
        return $this->adapter->put('OrderCollection', $id, $tabObject);
    }

    /**
     * Retrieves the list of delivery notes linked to an order for a given company.
     *
     * @param string $orderId ID de la commande.
     * @param string $idSociete ID de la société.
     * @return array Liste des bons de livraison ou tableau vide si aucun trouvé.
     */
    public function getOrderListeBL(string $orderId, string $idSociete): array
    {
        $api_query = array(
            '$select' => 'Id,numBL,dateBL,SocietyId,Quantity,OrderProductId,ProductId,OrderId',
            '$filter' => urlencode("Order/Id eq guid'{$orderId}' and Society/Id eq guid'{$idSociete}'")
        );

        $results = json_decode($this->adapter->get('OrderProductDeliveryCollection', $api_query), true);

        if (!is_array($results) || empty($results['d']['results'])) {
            return [];
        }

        return $results['d']['results'];
    }

    /**
     * Retrieve a Creatio delivery note from its EBP number.
     *
     * @param string $documentNumber Numéro du BL dans EBP.
     * @param string $idSociete ID de la société.
     * @return array|string Liste des résultats ou chaîne vide si non trouvé.
     */
    public function getCreatioBLByEBPNumber(string $documentNumber, string $idSociete)
    {
        $api_query = array(
            '$select' => 'Id,numBL,dateBL,SocietyId,Quantity,OrderProductId,ProductId,OrderId',
            '$filter' => urlencode("numBL eq '{$documentNumber}' and Society/Id eq guid'{$idSociete}'")
        );

        $results = json_decode($this->adapter->get('OrderProductDeliveryCollection', $api_query), true);

        if (!is_array($results) || empty($results['d']['results'][0]['numBL'])) {
            return '';
        }

        return $results['d']['results'];
    }

    /**
     * Supprime un bon de livraison par son ID.
     *
     * @param string $orderProductDeliveryId ID du bon de livraison.
     * @return mixed Résultat de l’opération de suppression.
     */
    public function deleteOrderBLById(string $orderProductDeliveryId)
    {
        return $this->adapter->delete('OrderProductDeliveryCollection', $orderProductDeliveryId);
    }

    /**
     * Insère un nouvel enregistrement de livraison de produit (BL) dans Creatio.
     *
     * @param array $tabObjet Données du produit à livrer.
     * @return mixed Résultat de l’appel POST à l’API.
     */
    public function insertOrderProductDelivery(array $tabObjet)
    {
        $tabObject = array(
            'OrderId'         => $tabObjet['OrderId'],
            'OrderProductId'  => $tabObjet['OrderProductId'],
            'ProductId'       => $tabObjet['ProductId'],
            'SocietyId'       => $tabObjet['SocietyId'],
            'Quantity'        => (int) $tabObjet['Quantity'],
            'numBL'           => (string) $tabObjet['numBL'],
            'dateBL'          => $tabObjet['dateBL'],
            'numFC'           => (string) $tabObjet['numFC'],
            'dateFC'          => $tabObjet['dateFC'],
            'Description'     => (string) $tabObjet['Description'],
            'PortFact'        => (string) $tabObjet['PortFact'],
            'MargePAPourcent' => (string) $tabObjet['MargePAPourcent'],
            'MargePA'         => (string) $tabObjet['MargePA'],
            'MargePRPourcent' => (string) $tabObjet['MargePRPourcent'],
            'MargePR'         => (string) $tabObjet['MargePR'],
            'PAU'             => (string) $tabObjet['PAU'],
            'PRU'             => (string) $tabObjet['PRU'],
            'PVMU'            => (string) $tabObjet['PVMU'],
            'PA'              => (string) $tabObjet['PA'],
            'PR'              => (string) $tabObjet['PR'],
            'PVHT'            => (string) $tabObjet['PVHT']
        );

        return $this->adapter->post('OrderProductDeliveryCollection', $tabObject);
    }

    /**
     * Récupère l’ID d’un produit dans une commande.
     *
     * @param string $orderId   UUID de la commande
     * @param string $productId UUID du produit
     * @return string|null      ID du lien commande-produit ou null si non trouvé
     */
    public function getOrderProductId($orderId, $productId)
    {
        $api_query = array(
            '$select' => 'Id',
            '$filter' => urlencode("Order/Id eq guid'" . $orderId . "' and Product/Id eq guid'" . $productId . "'")
        );

        $response = $this->adapter->get('OrderProductCollection', $api_query);
        $results = json_decode($response, true);

        if (!is_array($results) || empty($results['d']['results'][0]['Id'])) {
            return null;
        }

        return $results['d']['results'][0]['Id'];
    }

    /**
     * Met à jour le statut et la date d’expédition d’une commande.
     *
     * @param string    $orderId   UUID de la commande
     * @param string    $state     ID du statut
     * @param \DateTime $dateExpe  Date d’expédition
     * @return mixed               Résultat de l’appel PUT
     */
    public function SetOrderStateByOrderId($orderId, $state, \DateTime $dateExpe)
    {
        $tabObject = array(
            'StatusId'       => $state,
        );

        return $this->adapter->put('OrderCollection', $orderId, $tabObject);
    }

    /**
     * Met à jour le statut, la date d’expédition et la date de facturation d’une commande.
     *
     * @param string    $orderId   UUID de la commande
     * @param string    $state     ID du statut
     * @param \DateTime $dateFact  Date de facturation
     * @param \DateTime $dateExpe  Date d’expédition
     * @return mixed               Résultat de l’appel PUT
     */
    public function SetOrderStateAndDateFactByOrderId($orderId, $state, \DateTime $dateFact, \DateTime $dateExpe)
    {
        $tabObject = array(
            'StatusId'        => $state,
            'ActualDate'      => $dateFact->format('Y-m-d\TH:i:s')
        );

        return $this->adapter->put('OrderCollection', $orderId, $tabObject);
    }

    //Account
    /**
     * Récupère les informations d’un compte à partir de son ID.
     *
     * @param string $Id UUID du compte
     * @return array|null Données du compte ou null si non trouvé
     */
    public function initAccountById($Id)
    {
        $api_query = array(
            '$select' => 'Id,Name,OwnerId,PrimaryContactId,Phone,GPSN,GPSE',
            '$filter' => urlencode("Id eq guid'" . $Id . "'")
        );

        $response = $this->adapter->get('AccountCollection', $api_query);
        $result = json_decode($response, true);

        if (!is_array($result) || empty($result['d']['results'][0])) {
            return null;
        }

        return $result['d']['results'][0];
    }

    /**
     * Récupère l’ID d’un compte à partir de son nom.
     *
     * @param string $AccountName Nom du compte
     * @return string|null UUID du compte ou null si non trouvé
     */
    public function getAccountIdByName($AccountName)
    {
        $api_query = array(
            '$select' => 'Id',
            '$filter' => $this->str("Name eq '%s'", $AccountName)
        );

        $response = $this->adapter->get('AccountCollection', $api_query);
        $result = json_decode($response, true);

        if (!is_array($result) || empty($result['d']['results'][0]['Id'])) {
            return null;
        }

        return $result['d']['results'][0]['Id'];
    }

    /**
     * Récupère l’ID du contact principal associé à un compte.
     *
     * @param string $accountId UUID du compte
     * @return string|null UUID du contact principal ou null si non trouvé
     */
    public function getAccountPrimaryContactId($accountId)
    {
        $api_query = array(
            '$select' => 'PrimaryContactId',
            '$filter' => urlencode("Id eq guid'" . $accountId . "'")
        );

        $response = $this->adapter->get('AccountCollection', $api_query);
        $result = json_decode($response, true);

        if (!is_array($result) || empty($result['d']['results'][0]['PrimaryContactId'])) {
            return null;
        }

        return $result['d']['results'][0]['PrimaryContactId'];
    }

    public function addAccount($tabObject) {
        $result = json_decode($this->adapter->post('AccountCollection', $tabObject), true);
        
        if (isset($result['d']['Id']))
            return $result['d']['Id'];
        else
            return '';
    }

    /**
     * Met à jour les informations d’un compte.
     *
     * @param string $accountId UUID du compte à mettre à jour
     * @param array $tabObject Données à mettre à jour
     * @return array Résultat de la requête
     */
    public function updateAccount($accountId, $tabObject)
    {
        $response = $this->adapter->put('AccountCollection', $accountId, $tabObject);
        return json_decode($response, true);
    }

    /**
     * Récupère l'identifiant de l'adresse de facturation associée à une commande.
     *
     * @param string $orderId UUID de la commande
     * @return string UUID de l’adresse ou chaîne vide si non trouvée
     */
    public function getOrderAdresseFact($orderId)
    {
        $apiQuery = [
            '$select' => '*',
            '$filter' => urlencode("orderId eq guid'" . $orderId . "'")
        ];
        $results = json_decode($this->adapter->get('AccountAddressCollection', $apiQuery), true);

        if (isset($results['d']['results'][0]['Id'])) {
            return $results['d']['results'][0]['Id'];
        }

        return '';
    }

    /**
     * Initialise les données complètes d'une adresse à partir de son identifiant.
     *
     * @param string $addressId UUID de l’adresse
     * @return array|string Données de l’adresse ou chaîne vide si non trouvée
     */
    public function initAccountAddressById($addressId)
    {
        $apiQuery = [
            '$select' => 'Id,AddressTypeId,CountryId,RegionId,CityId,Address,Zip',
            '$filter' => urlencode("Id eq guid'" . $addressId . "'")
        ];
        $result = json_decode($this->adapter->get('AccountAddressCollection', $apiQuery), true);

        if (isset($result['d']['results'][0]['Id'])) {
            return $result['d']['results'][0];
        }

        return '';
    }

    /**
     * Ajoute une nouvelle adresse (équivalent de insertAdresse).
     *
     * @param array $tabObject Données de l'adresse à insérer
     * @return string|null ID de l'adresse créée ou null si erreur
     */
    public function addAddress(array $tabObject)
    {
        $result = json_decode($this->adapter->post('AccountAddressCollection', $tabObject), true);

        if (isset($result['d']['Id'])) {
            return $result['d']['Id'];
        }

        return null;
    }

    /**
     * Alias de addAddress() – pour compatibilité éventuelle avec des appels existants.
     *
     * @param array $tabObject Données de l'adresse à insérer
     * @return string|null ID de l'adresse créée ou null si erreur
     */
    public function insertAdresse(array $tabObject)
    {
        return $this->addAddress($tabObject);
    }

    /**
     * Récupère l'identifiant du pays via son code ISO (Alpha-3 dans Creatio).
     *
     * @param string $IsoCode Code ISO (FR, BE, etc.)
     * @return string|null ID du pays ou null si non trouvé
     */
    public function getPaysByCountryIsoCode($IsoCode)
    {
        // Conversion ISO-2 vers ISO-3 si nécessaire
        if (strtoupper($IsoCode) === 'FR') {
            $IsoCode = 'FRA';
        }

        $apiQuery = [
            '$select' => 'Id',
            '$filter' => urlencode("Code eq '" . $IsoCode . "'")
        ];

        $result = json_decode($this->adapter->get('CountryCollection', $apiQuery), true);

        if (isset($result['d']['results'][0]['Id'])) {
            return $result['d']['results'][0]['Id'];
        }

        return null;
    }

    /**
     * Récupère le nom du pays à partir de son identifiant.
     *
     * @param string $id UUID du pays
     * @return string|null Nom du pays ou null si non trouvé
     */
    public function initCountryNameById($id)
    {
        $apiQuery = [
            '$select' => '*',
            '$filter' => urlencode("Id eq guid'" . $id . "'")
        ];

        $result = json_decode($this->adapter->get('CountryCollection', $apiQuery), true);

        if (isset($result['d']['results'][0])) {
            return $result['d']['results'][0]['Name'];
        }

        return null;
    }

    /**
     * Récupère l'ID d'une ville à partir de son nom.
     *
     * @param string $cityName Nom de la ville
     * @return string|null ID de la ville ou null si non trouvée
     */
    public function getCityByName($cityName)
    {
        $apiQuery = [
            '$select' => '*',
            '$filter' => $this->str("Name eq '%s'", $cityName)
        ];

        $results = json_decode($this->adapter->get('CityCollection', $apiQuery), true);

        if (isset($results['d']['results'][0]['Id'])) {
            return $results['d']['results'][0]['Id'];
        }

        return null;
    }

    /**
     * Récupère le nom d'une ville à partir de son ID.
     *
     * @param string $cityId UUID de la ville
     * @return string|null Nom de la ville ou null si non trouvée
     */
    public function initCityById($cityId)
    {
        $apiQuery = [
            '$select' => 'Id,Name',
            '$filter' => urlencode("Id eq guid'" . $cityId . "'")
        ];

        $results = json_decode($this->adapter->get('CityCollection', $apiQuery), true);

        if (isset($results['d']['results'][0]['Id'])) {
            return $results['d']['results'][0]['Name'];
        }

        return null;
    }

    /**
     * Récupère les informations de statut de commande à partir de son ID.
     *
     * @param string $statusId UUID du statut
     * @return array|string Tableau de données ou chaîne vide si non trouvé
     */
    public function getOrderStatusById($statusId)
    {
        $apiQuery = [
            '$select' => '*',
            '$filter' => urlencode("Id eq guid'" . $statusId . "'")
        ];

        $results = json_decode($this->adapter->get('OrderStatusCollection', $apiQuery), true);

        if (!empty($results['d']['results'])) {
            return $results['d']['results'][0];
        }

        return '';
    }
    
    /**
    * Récupère le nom du statut de commande à partir de son ID.
    *
    * @param string $statusId UUID du statut
    * @return string Nom du statut ou chaîne vide si non trouvé
    */
    public function getOrderStatusNameById($statusId)
    {
        $apiQuery = [
            '$select' => 'Name',
            '$filter' => urlencode("Id eq guid'" . $statusId . "'")
        ];

        $results = json_decode($this->adapter->get('OrderStatusCollection', $apiQuery), true);

        if (!empty($results['d']['results'])) {
            return $results['d']['results'][0]['Name'];
        }

        return '';
    }    

    /**
     * Return $nbresults contacts
     *
     * @param string $nbresults Nb results to get
     * @return array|string Data table or empty string if not found
     */
    public function getContacts($nbresults)
    {
        $apiQuery = [
            '$select' => 'Id,Name,AccountId,Phone,MobilePhone,Email,Surname,GivenName,MiddleName'
        ];

        $results = json_decode($this->adapter->get('ContactCollection', $apiQuery, $nbresults), true);
        //echo "<xmp>".print_r($results,1)."</xmp>";exit();
        
        if (!empty($results['d']['results'])) {
            return $results['d']['results'];
        }

        return '';
    }    

    /**
     * Initialise un contact à partir de son ID.
     *
     * @param string $contactId UUID du contact
     * @return array|string Tableau des données ou chaîne vide si non trouvé
     */
    public function initContactById($contactId)
    {
        $apiQuery = [
            '$select' => 'Id,Name,AccountId,Phone,MobilePhone,Email,Surname,GivenName,MiddleName',
            '$filter' => urlencode("Id eq guid'" . $contactId . "'")
        ];

        $results = json_decode($this->adapter->get('ContactCollection', $apiQuery), true);

        if (!empty($results['d']['results'])) {
            return $results['d']['results'][0];
        }

        return '';
    }

    /**
     * Récupère les contacts associés à un compte donné.
     *
     * @param string $accountId UUID du compte
     * @return array|string Liste des contacts ou chaîne vide si aucun
     */
    public function getContactsByAccountId($accountId)
    {
        $apiQuery = [
            '$select' => '*',
            '$filter' => urlencode("Account/Id eq guid'" . $accountId . "'")
        ];

        $results = json_decode($this->adapter->get('ContactCollection', $apiQuery), true);

        if (!empty($results['d']['results'])) {
            return $results['d']['results'];
        }

        return '';
    }

    /**
     * Récupère l'identifiant d'un contact à partir de son compte et de son nom complet.
     * Effectue une recherche de secours si le prénom ou nom est vide.
     *
     * @param string $accountId UUID du compte
     * @param string $FirstName Prénom du contact
     * @param string $Name Nom du contact
     * @return string UUID du contact ou chaîne vide si non trouvé
     */
    public function getContactByAccountIdAndName($accountId, $FirstName, $Name)
    {
        $apiQuery = [
            '$select' => 'Id,AccountId',
            '$filter' => $this->str(
                "Account/Id eq guid'" . $accountId . "' and Name eq '%s'",
                $FirstName . " " . $Name
            )
        ];
        $result = json_decode($this->adapter->get('ContactCollection', $apiQuery), true);

        if (isset($result['d']['results'][0]['Id'])) {
            return $result['d']['results'][0]['Id'];
        }

        // Requête de secours si prénom ou nom est vide
        $prenomContact = empty($FirstName) ? 'Prénom' : $FirstName;
        $nomContact = empty($Name) ? 'Nom' : $Name;

        $apiQuery = [
            '$select' => 'Id,AccountId',
            '$filter' => $this->str(
                "Account/Id eq guid'" . $accountId . "' and Name eq '%s'",
                $prenomContact . " " . $nomContact
            )
        ];
        $result = json_decode($this->adapter->get('ContactCollection', $apiQuery), true);

        if (isset($result['d']['results'][0]['Id'])) {
            return $result['d']['results'][0]['Id'];
        }

        return '';
    }

    /**
     * Ajoute un contact à la collection ContactCollection.
     *
     * @param array $tabObject Données du contact
     * @return string UUID du contact créé ou chaîne vide
     */
    public function addContact($tabObject)
    {
        $result = json_decode($this->adapter->post('ContactCollection', $tabObject), true);

        return $result['d']['Id'] ?? '';
    }

    /**
     * Alias de addContact (ajout d’un contact).
     *
     * @param array $tabObject Données du contact
     * @return string UUID du contact ou chaîne vide
     */
    public function insertContact($tabObject)
    {
        return $this->addContact($tabObject);
    }

    /**
     * Met à jour les informations d’un contact existant par son ID.
     *
     * @param string $id UUID du contact
     * @param array $tabObject Données à mettre à jour
     * @return mixed Résultat de la requête PUT
     */
    public function addContactInfosById($id, $tabObject)
    {
        return $this->adapter->put('ContactCollection', $id, $tabObject);
    }

    /**
     * Récupère les produits associés à une commande.
     *
     * @param string $orderId ID de la commande
     * @return array          Tableau des produits ou tableau vide si aucun
     */
    public function getOrderProducts($orderId) {
        $api_query = array(
            '$select' => 'Id,ProductId',
            '$filter' => urlencode("Order/Id eq guid'".$orderId."'")
        );
        $results = json_decode($this->adapter->get('OrderProductCollection', $api_query), true);

        if (isset($results['d']['results'][0])) {
            return $results['d']['results'];
        }

        return [];
    }

    /**
     * Initialise un produit à partir de son ID.
     *
     * @param string $productId ID du produit
     * @return array            Données du produit ou tableau vide si non trouvé
     */
    public function initProductById($productId) {
        $apiQuery = array(
            '$select' => 'Id,Code,Name,UnitId,Price,TaxId,Description',
            '$filter' => urlencode("Id eq guid'" . $productId . "'")
        );
        $results = json_decode($this->adapter->get('ProductCollection', $apiQuery), true);

        if (isset($results['d']['results'][0]['Id'])) {
            return $results['d']['results'][0];
        }

        return [];
    }

    /**
     * Ajoute un produit à une commande.
     *
     * @param array $tabObject Données du produit à ajouter
     * @return array           Données du produit ajouté
     */
    public function addProductToOrder($tabObject) {
        $orderProduct = json_decode($this->adapter->post('OrderProductCollection', $tabObject), true);
        return $orderProduct['d'];
    }

    /**
     * Met à jour un produit.
     *
     * @param string $productId ID du produit
     * @param array  $tabObject Données à mettre à jour
     * @return mixed            Résultat de la requête PUT
     */
    public function updateProduct($productId, $tabObject) {
        return $this->adapter->put('ProductCollection', $productId, $tabObject);
    }

    /**
     * Crée un nouveau produit.
     *
     * @param array $tabObject Données du produit à créer
     * @return mixed           Résultat de la requête POST
     */
    public function createProduct($tabObject) {
        return $this->adapter->post('ProductCollection', $tabObject);
    }

    /**
     * Récupère la liste des prix spéciaux associés à un tarif EBP.
     *
     * @param string $tarifId ID du tarif EBP
     * @return array|string   Liste des prix spéciaux ou chaîne vide si aucun
     */
    public function getPrixSpeciauxByTarifId($tarifId) {
        $api_query = array(
            '$select' => 'Id',
            '$filter' => urlencode("CodeTarifEBP eq '".$tarifId."'")
        );

        $results = json_decode($this->adapter->get('AccountRangeCollection', $api_query), true);

        if (isset($results['d']['results'][0]['Id'])) {
            return $results['d']['results'];
        }

        return '';
    }

    /**
     * Récupère les lignes de prix associées à un tarif EBP.
     *
     * @param string $tarifId ID du tarif EBP
     * @return array|string   Liste des résultats ou chaîne vide
     */
    public function getCodeTarifById($tarifId) {
        $api_query = array(
            '$select' => 'Id',
            '$filter' => urlencode("CodeTarifEBP eq '".$tarifId."'")
        );

        $results = json_decode($this->adapter->get('AccountRangeCollection', $api_query), true);

        if (isset($results['d']['results'][0]['Id'])) {
            return $results['d']['results'];
        }

        return '';
    }

    /**
     * Ajoute un prix sur mesure dans Creatio.
     *
     * @param array $tabObject Données du prix à ajouter
     * @return mixed           Résultat de la requête POST
     */
    public function ajoutPrixSurMesure($tabObject) {
        return $this->adapter->post('AccountRangeCollection', $tabObject);
    }

    /**
     * Supprime un prix spécial dans Creatio à partir de son ID.
     *
     * @param string $prixSpecialId ID du prix spécial
     * @return mixed                Résultat de la requête DELETE
     */
    public function supprimePrixSpecial($prixSpecialId) {
        return $this->adapter->delete('AccountRangeCollection', $prixSpecialId);
    }

    /**
     * Met à jour les notes d'une activité dans Creatio.
     *
     * @param string $idActivity ID de l'activité
     * @param string $Notes      Contenu des notes
     * @return mixed             Résultat de la requête PUT
     */
    public function updateActivityNotes($idActivity, $Notes) {
        $tabObject = array('Notes' => $Notes);
        return $this->adapter->put('ActivityCollection', $idActivity, $tabObject);
    }

    /**
     * Récupère le nom d'un objet générique à partir de son ID et de son type.
     *
     * @param string $Id    ID de l'objet
     * @param string $Objet Nom de l'objet Creatio (sans suffixe "Collection")
     * @return string       Nom de l'objet ou chaîne vide si non trouvé
     */
    public function getObjetNameById($Id, $Objet) {
        $api_query = array(
            '$select' => 'Name',
            '$filter' => urlencode("Id eq guid'".$Id."'")
        );

        $results = json_decode($this->adapter->get($Objet . 'Collection', $api_query), true);

        if (isset($results['d']['results'][0]['Name'])) {
            return $results['d']['results'][0]['Name'];
        }

        return '';
    }

    /**
     * Convertit une date au format JSON de Creatio en objet DateTime.
     *
     * @param string $varDate Chaîne de type /Date(1652707200000)/
     * @return \DateTime|null Objet DateTime ou null en cas d’erreur
     */
    public function formatCreatiooDataDate($varDate) {
        $timestamp = substr(str_replace([')/', '/Date('], '', $varDate), 0, -3);
        $dateStr = date('d/m/Y', (int)$timestamp);
        return \DateTime::createFromFormat('d/m/Y', $dateStr) ?: null;
    }

    /**
     * Encode une chaîne de requête OData en doublant les simples quotes et en appliquant urlencode.
     *
     * @param string $str   Chaîne de format avec placeholders (ex: "Name eq '%s'")
     * @param string|array $args Argument(s) à insérer dans la chaîne
     * @return string Chaîne formatée et encodée pour OData
     */
    public function str($str, $args) {
        if (!is_array($args)) {
            $args = [$args];
        }

        foreach ($args as $i => $arg) {
            $args[$i] = str_replace("'", "''", $arg);
        }

        return urlencode(vsprintf($str, $args));
    }
}