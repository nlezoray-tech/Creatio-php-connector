<?php
/**
 * CreatioAdapterInterface interface file
 *
 * Interface définissant les méthodes nécessaires à tout adaptateur Creatio
 *
 * PHP Version 7.4
 *
 * @category Interfaces
 * @package  Creatio
 * @author   Nicolas Lézoray <nlezoray@gmail.com>
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link     http://api
 */
namespace Nlezoray\Creatio\Adapter;

interface CreatioAdapterInterface
{
    /**
     * Authentifie le connecteur (oData ou oAuth)
     * @return bool|null true si OK, false ou null sinon
     */
    public function authentification(): ?bool;

    /**
     * Envoie une requête GET à l'API Creatio
     *
     * @param string $collection
     * @param array $apiQuery
     * @param int $nbresult
     * @param string|false $orderby
     * @param int $skip
     * @return mixed
     */
    public function get(string $collection, array $apiQuery, int $nbresult = 10000, $orderby = false, int $skip = 0);

    /**
     * Envoie une requête POST à l'API Creatio
     *
     * @param string $collection
     * @param array $tabObject
     * @return mixed
     */
    public function post(string $collection, array $tabObject);

    /**
     * Envoie une requête PUT à l'API Creatio
     *
     * @param string $collection
     * @param string $id
     * @param array $tabObject
     * @return mixed
     */
    public function put(string $collection, string $id, array $tabObject);

    /**
     * Envoie une requête DELETE à l'API Creatio
     *
     * @param string $collection
     * @param string $id
     * @return mixed
     */
    public function delete(string $collection, string $id);

    /**
     * Retourne le token BPMCSRF (oData)
     * @return string|false
     */
    public function getCookieToken();
}
