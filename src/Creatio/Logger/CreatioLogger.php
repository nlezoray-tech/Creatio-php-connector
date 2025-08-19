<?php
/**
 * CreatioLogger class file
 *
 * Logger simple pour l'écriture de logs dans le cadre de l'intégration Creatio
 *
 * PHP Version 7.4
 *
 * @category Utility
 * @package  Creatio
 * @author   Nicolas Lézoray <nlezoray@gmail.com>
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link     http://api
 */
namespace Nlezoray\Creatio\Logger;

class CreatioLogger
{
    protected $logPath;

    public function __construct(string $logPath)
    {
        $this->logPath = $logPath;
    }

    public function log(string $message): void
    {
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($this->logPath, "[$timestamp] $message\n", FILE_APPEND);
    }

    public function logJson($data, string $prefix = ''): void
    {
        $message = $prefix . json_encode($data, JSON_PRETTY_PRINT);
        $this->log($message);
    }

    public function clear(): void
    {
        file_put_contents($this->logPath, '');
    }
}
