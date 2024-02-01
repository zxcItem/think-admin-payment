<?php


declare (strict_types=1);

namespace app\payment\service\contract;

use app\account\service\contract\AccountInterface;
use think\Response;

/**
 * 支付通道标准接口
 * @class PaymentInterface
 * @package app\payment\service\contract
 */
interface PaymentInterface
{

    /**
     * 获取支付参数
     * @return array
     */
    public function config(): array;

    /**
     * 创建支付订单
     * @param AccountInterface $account 支付账号
     * @param string $orderNo 交易订单单号
     * @param string $orderTitle 交易订单标题
     * @param string $orderAmount 订单支付金额（元）
     * @param string $payAmount 本次交易金额
     * @param string $payRemark 交易订单描述
     * @param string $payReturn 支付回跳地址
     * @param string $payImages 支付凭证图片
     * @return PaymentResponse
     */
    public function create(AccountInterface $account, string $orderNo, string $orderTitle, string $orderAmount, string $payAmount, string $payRemark = '', string $payReturn = '', string $payImages = ''): PaymentResponse;

    /**
     * 主动查询订单支付
     * @param string $pcode
     * @return array
     */
    public function query(string $pcode): array;

    /**
     * 支付通知处理
     * @param array $data
     * @param ?array $notify
     * @return Response
     */
    public function notify(array $data = [], ?array $notify = null): Response;

    /**
     * 发起支付退款
     * @param string $pcode 支付单号
     * @param string $amount 退款金额
     * @param string $reason 退款原因
     * @return array [状态, 消息]
     */
    public function refund(string $pcode, string $amount, string $reason = ''): array;
}