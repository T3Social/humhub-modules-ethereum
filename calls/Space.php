<?php
/**
 * @link https://coinsence.org/
 * @copyright Copyright (c) 2018 Coinsence
 * @license https://www.humhub.com/licences
 *
 * @author Daly Ghaith <daly.ghaith@gmail.com>
 */

namespace humhub\modules\ethereum\calls;

use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use humhub\components\Event;
use humhub\modules\ethereum\component\HttpStatus;
use humhub\modules\ethereum\component\Utils;
use humhub\modules\ethereum\Endpoints;
use humhub\modules\ethereum\jobs\CreateWallets;
use humhub\modules\user\models\User;
use humhub\modules\xcoin\models\Account;
use humhub\modules\space\models\Space as BaseSpace;
use humhub\modules\xcoin\models\Asset;
use humhub\modules\xcoin\models\Transaction;
use Yii;
use yii\base\Exception;
use yii\web\HttpException;

/**
 * Class Space
 */
class Space
{
    /**
     * @param $event
     * @throws GuzzleException
     * @throws HttpException
     * @throws Exception
     */
    public static function addMember($event)
    {
        $space = $event->space;
        $member = $event->user;

        if (
            !$space instanceof BaseSpace ||
            !$member instanceof User ||
            !$space->isModuleEnabled('xcoin') ||
            $space->id == 1 // space with id = 1 is "Welcome Space" (this is the best way to check since it's the first space automatically created)
        ) {
            return;
        }

        $userDefaultAccount = Account::findOne([
            'user_id' => $member->id,
            'account_type' => Account::TYPE_DEFAULT,
            'space_id' => null
        ]);

        if (!$userDefaultAccount) {
            $userDefaultAccount = Utils::createDefaultAccount($member);
        } elseif (!$userDefaultAccount->ethereum_address) {
            Wallet::createWallet(new Event(['sender' => $userDefaultAccount]));
        }

        $spaceDefaultAccount = Account::findOne([
            'space_id' => $space->id,
            'account_type' => Account::TYPE_DEFAULT
        ]);

        if (!$spaceDefaultAccount) {
            $spaceDefaultAccount = Utils::createDefaultAccount($space);
        } elseif (!$spaceDefaultAccount->ethereum_address) {
            Wallet::createWallet(new Event(['sender' => $spaceDefaultAccount]));
        }

        BaseCall::__init();

        $response = BaseCall::$httpClient->request('POST', Endpoints::ENDPOINT_SPACE_ADD_MEMBER, [
            RequestOptions::JSON => [
                'accountId' => $spaceDefaultAccount->guid,
                'dao' => $space->dao_address,
                'members' => [$userDefaultAccount->ethereum_address]
            ]
        ]);

        if ($response->getStatusCode() != HttpStatus::CREATED) {
            throw new HttpException(
                $response->getStatusCode(),
                'Could not add member to this space, will fix this ASAP !'
            );
        }
    }

    /**
     * @param $event
     * @throws GuzzleException
     * @throws HttpException
     */
    public static function leaveSpace($event)
    {
        $space = $event->space;
        $member = $event->user;

        if (
            !$space instanceof BaseSpace ||
            !$member instanceof User ||
            !$space->isModuleEnabled('xcoin') ||
            $space->id == 1 // space with id = 1 is "Welcome Space" (this is the best way to check since it's the first space automatically created)
        ) {
            return;
        }

        $userDefaultAccount = Account::findOne([
            'user_id' => $member->id,
            'account_type' => Account::TYPE_DEFAULT,
            'space_id' => null
        ]);

        BaseCall::__init();

        $response = BaseCall::$httpClient->request('POST', Endpoints::ENDPOINT_SPACE_LEAVE_SPACE, [
            RequestOptions::JSON => [
                'accountId' => $userDefaultAccount->guid,
                'dao' => $space->dao_address,
            ]
        ]);

        if ($response->getStatusCode() != HttpStatus::CREATED) {
            throw new HttpException(
                $response->getStatusCode(),
                'Could not remove member from this space, will fix this ASAP !'
            );
        }
    }

    /**
     * Enable ethereum integration for already existing spaces
     *
     * @param $event
     * @throws GuzzleException
     * @throws HttpException
     * @throws Exception
     */
    public function enable($event)
    {
        $space = $event->sender;

        if (!$space instanceof BaseSpace) {
            return;
        }

        $spaceDefaultAccount = Account::findOne([
            'space_id' => $space->id,
            'account_type' => Account::TYPE_DEFAULT
        ]);

        if (!$spaceDefaultAccount) {
            Utils::createDefaultAccount($space);
        } else {
            Wallet::createWallet(new Event(['sender' => $spaceDefaultAccount]));
            Dao::createDao($event);
        }

        Yii::$app->queue->delay(Utils::DELAY_TWO_MINUTES)->push(new CreateWallets(['spaceId' => $space->id]));
    }

    /**
     * @param $space
     * @throws GuzzleException
     */
    public static function migrate($space)
    {
        if (!is_array($space)) {
            return;
        }

        BaseCall::__init();

        $response = BaseCall::$httpClient->request('POST', Endpoints::ENDPOINT_SPACE_MIGRATE, [
            RequestOptions::JSON => $space
        ]);

        if ($response->getStatusCode() != HttpStatus::CREATED) {
            Yii::error("error migrating when migrating space : {$response->getBody()}", 'cron');
        }
    }

    /**
     * @param $event
     * @throws Exception
     * @throws GuzzleException
     * @throws HttpException
     */
    public static function migrateMissingTransactions($event)
    {
        $space = $event->sender;

        if (
            !$space instanceof BaseSpace ||
            !$space->isModuleEnabled('xcoin') ||
            $space->id == 1 // space with id = 1 is "Welcome Space" (this is the best way to check since it's the first space automatically created)
        ) {
            return;
        }

        $asset = Asset::findOne(['space_id' => $space->id]);

        $transactions = Transaction::find()
            ->where(['asset_id' => $asset->id, 'eth_hash' => null])
            ->andWhere([
                'or',
                'transaction_type =' . Transaction::TRANSACTION_TYPE_TRANSFER,
                'transaction_type =' . Transaction::TRANSACTION_TYPE_TASK_PAYMENT,
            ]);

        foreach ($transactions as $transaction) {
            Coin::transferCoin(new Event(['sender' => $transaction]));
        }
    }
}
