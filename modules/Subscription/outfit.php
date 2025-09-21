<?php

namespace F3CMS;

/**
 * for render page
 */
class oSubscription extends Outfit
{
    /**
     * @param $args
     */
    public function cancel($args)
    {
        if ($args['email']) {
            fSubscription::cancel($args['email']);
        }

        parent::wrapper('/cancel_me.html', '取消訂閱', '/cancel_me');
    }

    /**
     * @param $args
     */
    public function confirm($args)
    {
        if ($args['email']) {
            fSubscription::confirm($args['email']);
        }

        parent::wrapper('/confirm_me.html', '確認訂閱', '/confirm_me');
    }
}
