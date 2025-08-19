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
use Nlezoray\Creatio\Service\CreatioLibQuestionnaire;

class Creatio extends CreatioLibQuestionnaire
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
     * Méthodes de délégation vers l'adaptateur Creatio.
     *
     * Ces méthodes permettent d'appeler directement les fonctionnalités principales
     * de l'adaptateur (authentification, requêtes CRUD) depuis la classe instanciable `Creatio`.
     * Cela permet de simplifier l'utilisation externe de l'adaptateur, sans exposer directement
     * ses détails d'implémentation, tout en respectant l'interface `CreatioAdapterInterface`.
     */
    public function getAdapter(): CreatioAdapterInterface
    {
        return $this->adapter;
    }

    /**
     * Authentifie la session auprès de Creatio via l'adaptateur.
     */
    public function authentification(): ?bool
    {
        return $this->adapter->authentification();
    }

    /**
     * Exécute une requête GET sur la collection spécifiée, avec paramètres.
     */
    public function get(string $collection, array $query, int $nbresult = 10000, $orderby = false, int $skip = 0)
    {
        return $this->adapter->get($collection, $query, $nbresult, $orderby, $skip);
    }

    /**
     * Exécute une requête POST (création d'entité) sur la collection donnée.
     */
    public function post(string $collection, array $tabObject)
    {
        return $this->adapter->post($collection, $tabObject);
    }

    /**
     * Exécute une requête PUT (mise à jour) sur une entité identifiée par son ID.
     */
    public function put(string $collection, string $id, array $tabObject)
    {
        return $this->adapter->put($collection, $id, $tabObject);
    }

    /**
     * Exécute une requête DELETE pour supprimer une entité identifiée par son ID.
     */    
    public function delete(string $collection, string $id)
    {
        return $this->adapter->delete($collection, $id);
    }
}
