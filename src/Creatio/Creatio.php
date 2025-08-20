<?php
/**
 * Creatio class file
 *
 * PHP Version 7.4
 *
 * @category Service
 * @package  Creatio
 * @author   Nicolas Lézoray <nlezoray@gmail.com>
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link     http://api
 */

namespace Nlezoray\Creatio;

use Nlezoray\Creatio\Adapter\CreatioAdapterInterface;
use Nlezoray\Creatio\Adapter\CreatioODataAdapter;
use Nlezoray\Creatio\Adapter\CreatioOAuthAdapter;
use Nlezoray\Creatio\Service\CreatioLib;

class Creatio extends CreatioLib
{
    /** @var CreatioAdapterInterface */
    protected $adapter;

    /**
     * Constructeur
     *
     * @param string $env   Environnement : 'dev' ou 'prod'
     * @param string $mode  Mode de connexion : 'oData' ou 'oAuth'
     */    
    public function __construct(string $env = 'prod', string $mode = 'odata')
    { 
        switch ($mode) {
            case 'oauth':
                $this->adapter = new CreatioOAuthAdapter($env);
                break;
            case 'odata':
            default:
                $this->adapter = new CreatioODataAdapter($env);
                break;
        }
        $this->adapter->authentification();
    }

    /**
     * Delegate methods to the Creatio adapter.
     *
     * These methods allow you to directly call the adapter's main
     * functions (authentication, CRUD requests) from the instantiable `Creatio` class.
     * This simplifies external use of the adapter without directly exposing
     * its implementation details, while still respecting the `CreatioAdapterInterface` interface.
     */
    public function getAdapter(): CreatioAdapterInterface
    {
        return $this->adapter;
    }

    /**
     * Authenticates the session to Creatio via the adapter.
     */
    public function authentification(): ?bool
    {
        return $this->adapter->authentification();
    }

    /**
     * Performs a GET request on the specified collection, with parameters.
     */
    public function get(string $collection, array $query, int $nbresult = 10000, $orderby = false, int $skip = 0)
    {
        return $this->adapter->get($collection, $query, $nbresult, $orderby, $skip);
    }

    /**
     * Performs a POST (entity creation) request on the given collection.
     */
    public function post(string $collection, array $tabObject)
    {
        return $this->adapter->post($collection, $tabObject);
    }

    /**
     * Performs a PUT (update) request on an entity identified by its ID.
     */
    public function put(string $collection, string $id, array $tabObject)
    {
        return $this->adapter->put($collection, $id, $tabObject);
    }

    /**
     * Executes a DELETE query to delete an entity identified by its ID.
     */    
    public function delete(string $collection, string $id)
    {
        return $this->adapter->delete($collection, $id);
    }
}
