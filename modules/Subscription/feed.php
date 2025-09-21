<?php

namespace F3CMS;

/**
 * data feed
 */
class fSubscription extends Feed
{
    const MTB       = 'subscription';
    const MULTILANG = 0;
    const BE_COLS   = 'm.id,m.status,m.name,m.phone,m.email,m.lancode,m.last_ts';

    /**
     * @return mixed
     */
    public static function getEnabled()
    {
        $result = self::exec(
            'SELECT `id`, `status`, `name`, `phone`, `email`, `lancode`, `last_ts` FROM `' .
            self::fmTbl() . "` WHERE `status`='Enabled' ORDER BY `insert_ts` DESC "
        );

        return $result;
    }

    /**
     * @param $email
     */
    public static function cancel($email)
    {
        $rows = self::exec('SELECT `id`, `status` FROM `' . self::fmTbl() . '` WHERE `email`=?', self::_fixAry([$email]));

        if ($rows) {
            parent::change_status($rows[0]['id'], 'Disabled');
        }
    }

    /**
     * @param $email
     */
    public static function confirm($email)
    {
        $rows = self::exec('SELECT `id`, `status` FROM `' . self::fmTbl() . '` WHERE `email`=?', self::_fixAry([$email]));

        if ($rows) {
            parent::change_status($rows[0]['id'], 'Enabled');
        }
    }
}
