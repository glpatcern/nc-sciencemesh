<?php
/**
 * ownCloud - sciencemesh
 *
 * This file is licensed under the MIT License. See the LICENCE file.
 * @license MIT
 * @copyright Sciencemesh 2020 - 2023
 *
 * @author Michiel De Jong <michiel@pondersource.com>
 * @author Hugo Gonzalez Labrador <github@hugo.labkode.com>
 * @author Mohammad Mahdi Baghbani Pourvahid <mahdi-baghbani@azadehafzar.ir>
 */

namespace OCA\ScienceMesh\Controller;

use Exception;
use OC;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\Http\Client\IClientService;
use OCP\IDBConnection;
use OCP\ILogger;
use OCP\IRequest;

class PageController extends Controller
{
    /** @var IDBConnection */
    protected IDBConnection $connection;

    /** @var ILogger */
    private ILogger $logger;

    /** @var string */
    private string $userId;

    /** @var IClientService */
    private IClientService $httpClientService;

    public function __construct(
        string         $AppName,
        IRequest       $request,
        string         $userId,
        IDBConnection  $connection,
        IClientService $httpClientService,
        ILogger        $logger
    )
    {

        parent::__construct($AppName, $request);
        $this->userId = $userId;
        $this->connection = $connection;
        $this->httpClientService = $httpClientService;
        $this->logger = $logger;
    }

    /**
     * CAUTION: the @Stuff turns off security checks; for this page no admin is
     *          required and no CSRF check. If you don't know what CSRF is, read
     *          it up in the docs, or you might create a security hole. This is
     *          basically the only required method to add this exemption, don't
     *          add it to any other method if you don't exactly know what it does
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function index(): TemplateResponse
    {
        $params = ['user' => $this->userId];
        return new TemplateResponse('sciencemesh', 'main', $params);  // templates/main.php
    }

    /**
     * Simply method that posts back the payload of the request
     * @NoAdminRequired
     */
    public function doEcho($echo): DataResponse
    {
        return new DataResponse(['echo' => $echo]);
    }

    /**
     * @PublicPage
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getMetrics()
    {
        // for localhost requests is needed to add
        // 'allow_local_remote_servers' => true,
        // to config.php
        $settings = $this->loadSettings();
        if (!$settings) {
            return new JSONResponse(["error" => "error loading settings"]);
        }

        $client = $this->httpClientService->newClient();
        try {
            $iopurl = $settings['iopurl'];
            $response = $client->get("$iopurl/metrics", [
                'timeout' => 10,
                'connect_timeout' => 10,
            ]);

            if ($response->getStatusCode() === Http::STATUS_OK) {
                //$result = json_decode($response->getBody(), true);
                //return (is_array($result)) ? $result : [];
                echo($response->getBody());
                return new Http\Response();
            } else {
                $this->logger->error("sciencemesh: error getting metrics from iop");
                return new DataResponse(['error' => 'error getting metrics from iop'], Http::STATUS_INTERNAL_SERVER_ERROR);
            }
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
            return new DataResponse(['error' => 'error getting metrics from iop'], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

    private function loadSettings()
    {
        $query = OC::$server->getDatabaseConnection()->getQueryBuilder();
        $query->select('*')->from('sciencemesh');
        $result = $query->execute();
        $row = $result->fetch();
        $result->closeCursor();
        $row['numusers'] = intval($row['numusers']);
        $row['numfiles'] = intval($row['numfiles']);
        $row['numstorage'] = intval($row['numstorage']);
        unset($row['apikey']); // Remove the private API key from the exposed settings
        return $row;
    }

    /**
     * @PublicPage
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getInternalMetrics(): JSONResponse
    {
        //$metrics = $this->getInternal();
        $settings = $this->loadSettings();
        if (!$settings) {
            return new JSONResponse([]);
        }

        $metrics = [
            "numusers" => $settings['numusers'],
            "numfiles" => $settings['numfiles'],
            "numstorage" => $settings['numstorage']
        ];

        $payload = ["metrics" => $metrics, "settings" => $settings];
        return new JSONResponse($payload);
    }

    /* to get them from system rather than manual input */
    /*
    private function getInternal() {
        $queryBuilder = $this->connection->getQueryBuilder();
        $queryBuilder->select($queryBuilder->createFunction('count(*)'))
            ->from('users');
        $result = $queryBuilder->execute();
        $count = $result->fetchColumn();
        $hostname = \OCP\Util::getServerHostName();
        $params = [
            'total_users' => intval($count),
        ];
        return $params;
    }
     */

}
