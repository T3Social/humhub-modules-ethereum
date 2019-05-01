<?php
/**
 * @link https://coinsence.org/
 * @copyright Copyright (c) 2018 Coinsence
 * @license https://www.humhub.com/licences
 *
 * @author Daly Ghaith <daly.ghaith@gmail.com>
 */

namespace humhub\modules\ethereum\component;

use humhub\components\Event;
use humhub\modules\space\models\Space;
use humhub\modules\xcoin\models\Account;

/**
 * Class Utils
 *
 * @author Daly Ghaith <daly.ghaith@gmail.com>
 */
class Utils
{
    const COIN_SUFFIX = 'Coin';
    const COIN_DECIMALS = 18;

    public static function getCapitalizedSpaceName($spaceName)
    {
        return ucwords($spaceName) . ' ' . self::COIN_SUFFIX;
    }

    public static function getCoinSymbol($coinName)
    {
        $symbol = '';
        foreach (explode(' ', $coinName) as $word) {
            $symbol .= strtoupper($word[0]);
        }

        return $symbol;
    }

    public static function getDefaultDescHash()
    {
        return str_pad('0x', 66, "0", STR_PAD_RIGHT);
    }

    public static function createDefaultAccount($entity)
    {
        if ($entity instanceof Space) {

                $account = new Account();
                $account->title = 'Default';
                $account->space_id = $entity->id;
                $account->account_type = Account::TYPE_DEFAULT;
                $account->save();

                Event::trigger(Account::class, Account::EVENT_DEFAULT_SPACE_ACCOUNT_CREATED, new Event(['sender' => $entity]));
        } else {
                $account = new Account();
                $account->title = 'Default';
                $account->user_id = $entity->id;
                $account->account_type = Account::TYPE_DEFAULT;
                $account->save();
        }
    }
}