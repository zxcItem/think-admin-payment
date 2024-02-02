<?php

declare (strict_types=1);

namespace app\payment\model;

use app\account\model\Abs;
use app\account\model\AccountUser;
use app\payment\service\UserTransfer;
use think\model\relation\HasOne;

/**
 * 用户提现模型
 * @class PaymentTransfer
 * @package app\payment\model
 */
class PaymentTransfer extends Abs
{
    /**
     * 自动显示类型名称
     * @return array
     */
    public function toArray(): array
    {
        $data = parent::toArray();
        if (isset($data['type'])) {
            $data['type_name'] = UserTransfer::types($data['type']);
        }
        return $data;
    }

    /**
     * 关联用户数据
     * @return HasOne
     */
    public function user(): HasOne
    {
        return $this->hasOne(AccountUser::class, 'id', 'unid');
    }
}