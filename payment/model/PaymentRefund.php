<?php

declare (strict_types=1);

namespace app\payment\model;

use app\account\model\Abs;
use app\account\model\AccountUser;
use think\model\relation\HasOne;

/**
 * 用户支付退款模型
 * @class PaymentRecord
 * @package app\payment\model
 */
class PaymentRefund extends Abs
{
    /**
     * 关联用户数据
     * @return HasOne
     */
    public function user(): HasOne
    {
        return $this->hasOne(AccountUser::class, 'id', 'unid');
    }

    /**
     * 关联子支付订单
     * @return HasOne
     */
    public function record(): HasOne
    {
        return $this->hasOne(PaymentRecord::class, 'code', 'record_code');
    }

    /**
     * 格式化输出时间
     * @param mixed $value
     * @return string
     */
    public function getRefundTimeAttr($value): string
    {
        return format_datetime($value);
    }

    public function setRefundTimeAttr($value): string
    {
        return $this->setCreateTimeAttr($value);
    }
}