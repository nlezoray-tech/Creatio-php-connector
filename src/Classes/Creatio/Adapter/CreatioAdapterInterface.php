<?php
/**
 * CreatioAdapterInterface interface file
 *
 * Interface defining the methods needed by any Creatio adapter
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
     * Authenticates the connector (oData or oAuth)
     * @return bool|null true si OK, false ou null sinon
     */
    public function authentification(): ?bool;

    /**
     * Sends a GET request to the Creatio API
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
     * Sends a POST request to the Creatio API
     *
     * @param string $collection
     * @param array $tabObject
     * @return mixed
     */
    public function post(string $collection, array $tabObject);

    /**
     * Sends a PUT request to the Creatio API
     *
     * @param string $collection
     * @param string $id
     * @param array $tabObject
     * @return mixed
     */
    public function put(string $collection, string $id, array $tabObject);

    /**
     * Sends a DELETE request to the Creatio API
     *
     * @param string $collection
     * @param string $id
     * @return mixed
     */
    public function delete(string $collection, string $id);

    /**
     * Return the token BPMCSRF (oData)
     * @return string|false
     */
    public function getCookieToken();
}
