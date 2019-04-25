<?php
/**
 * @link https://coinsence.org/
 * @copyright Copyright (c) 2018 Coinsence
 * @license https://www.humhub.com/licences
 *
 * @author Daly Ghaith <daly.ghaith@gmail.com>
 */


namespace humhub\modules\ethereum\calls;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use humhub\modules\ethereum\component\HttpStatus;
use humhub\modules\ethereum\Endpoints;
use humhub\modules\space\models\Space;
use humhub\modules\xcoin\models\Account;
use yii\web\HttpException;


/**
 * Class Wallet
 */
class Dao
{
    /**
     * @param $event
     * @throws GuzzleException
     * @throws HttpException
     */
    public static function createDao($event)
    {
        $space = $event->sender;

        if ($space instanceof Space) {

            $defaultAccount = Account::findOne([
                'space_id' => $space->id,
                'account_type' => Account::TYPE_DEFAULT
            ]);

            $httpClient = new Client(['base_uri' => Endpoints::ENDPOINT_BASE_URI, 'http_errors' => false]);

            $response = $httpClient->request('POST', Endpoints::ENDPOINT_DAO, [
                RequestOptions::JSON => [
                    'accountId' => $defaultAccount->guid,
                    'name' => $space->name,
                    'descHash' => str_pad('0x', 66, "0", STR_PAD_RIGHT)
                ]
            ]);

            if ($response->getStatusCode() == HttpStatus::CREATED) {
                $body = json_decode($response->getBody()->getContents());
                $space->updateAttributes(['dao_address' => $body->daoAddress]);
            } else {
                throw new HttpException($response->getStatusCode(),'Could not create DAO for this space, will fix this ASAP !');
            }
        }
    }
}
