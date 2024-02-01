<?php

declare (strict_types=1);

namespace app\payment\model;

use app\account\model\Abs;
use app\account\model\AccountBind;
use app\account\model\AccountUser;
use app\payment\service\Payment;
use think\model\relation\HasOne;

/**
 * 用户支付行为模型
 * @class PaymentRecord
 * @package app\payment\model
 */
class PaymentRecord extends Abs
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
     * 关联客户端数据
     * @return HasOne
     */
    public function device(): HasOne
    {
        return $this->hasOne(AccountBind::class, 'id', 'usid');
    }

    /**
     * @param $value
     * @return array
     */
    public function getUserAttr($value): array
    {
        return is_array($value) ? $value : [];
    }

    /**
     * 格式化时间
     * @param mixed $value
     * @return string
     */
    public function getAuditTimeAttr($value): string
    {
        return $this->getCreateTimeAttr($value);
    }

    public function setAuditTimeAttr($value): string
    {
        return $this->setCreateTimeAttr($value);
    }

    /**
     * 格式化输出时间
     * @param mixed $value
     * @return string
     */
    public function getPaymentTimeAttr($value): string
    {
        return $this->getCreateTimeAttr($value);
    }

    public function setPaymentTimeAttr($value): string
    {
        return $this->setCreateTimeAttr($value);
    }

    /**
     * 数据输出处理
     * @return array
     */
    public function toArray(): array
    {
        $data = parent::toArray();
        if (isset($data['channel_type'])) {
            $data['channel_type_name'] = Payment::typeName($data['channel_type']);
        }
        return $data;
    }
}