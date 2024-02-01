<?php


declare (strict_types=1);

namespace app\payment\service\contract;

/**
 * 支付响应对象
 * @class PaymentResponse
 * @package app\payment\service\contract
 */
class PaymentResponse extends \stdClass
{
    public $record;
    public $status;
    public $params;
    public $message;

    public $channleCode = '';
    public $channelType = '';

    public function __construct(bool $status = true, string $message = "创建支付成功", array $record = [], array $params = [])
    {
        $this->record = $record;
        $this->status = $status;
        $this->params = $params;
        $this->message = $message;
    }

    /**
     * 更新返回内容
     * @param bool $status
     * @param string $message
     * @param array $record
     * @param array $params
     * @return $this
     */
    public function set(bool $status = true, string $message = "创建支付成功", array $record = [], array $params = []): PaymentResponse
    {
        $this->record = $record;
        $this->status = $status;
        $this->params = $params;
        $this->message = $message;
        return $this;
    }

    /**
     * 输出数组数据
     * @return array
     */
    public function toArray(): array
    {
        return [
            'record'  => $this->record,
            'params'  => $this->params,
            'channel' => [
                'type' => $this->channelType,
                'code' => $this->channleCode,
            ]
        ];
    }

    /**
     * 创建支付响应对象
     * @param boolean $status
     * @param string $message
     * @param array $record
     * @param array $params
     * @return PaymentResponse
     */
    public static function mk(bool $status = true, string $message = "创建支付成功", array $record = [], array $params = []): PaymentResponse
    {
        return new static($status, $message, $record, $params);
    }
}