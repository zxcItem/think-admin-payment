<?php

declare (strict_types=1);

namespace app\payment\service;

use app\payment\model\PaymentTransfer;
use think\admin\Exception;

/**
 * 用户提现数据服务
 * @class UserTransfer
 * @package app\payment\service
 */
class UserTransfer
{
    /**
     * 提现方式配置
     * @var array
     */
    protected static $types = [
        'wechat_wallet'  => '转账到微信零钱（线上）',
        'wechat_banks'   => '转账到银行卡账户（线上）',
        'wechat_qrcode'  => '转账到微信收款码（线下）',
        'alipay_qrcode'  => '转账到支付宝收款码（线下）',
        'alipay_account' => '转账到支付宝账户（线下）',
        'transfer_banks' => '转账到银行卡账户（线下）',
    ];

    /**
     * 微信提现银行
     * @var array
     */
    protected static $banks = [
        ['wseq' => '1002', 'name' => '工商银行'],
        ['wseq' => '1005', 'name' => '农业银行'],
        ['wseq' => '1003', 'name' => '建设银行'],
        ['wseq' => '1026', 'name' => '中国银行'],
        ['wseq' => '1020', 'name' => '交通银行'],
        ['wseq' => '1001', 'name' => '招商银行'],
        ['wseq' => '1066', 'name' => '邮储银行'],
        ['wseq' => '1006', 'name' => '民生银行'],
        ['wseq' => '1010', 'name' => '平安银行'],
        ['wseq' => '1021', 'name' => '中信银行'],
        ['wseq' => '1004', 'name' => '浦发银行'],
        ['wseq' => '1009', 'name' => '兴业银行'],
        ['wseq' => '1022', 'name' => '光大银行'],
        ['wseq' => '1027', 'name' => '广发银行'],
        ['wseq' => '1025', 'name' => '华夏银行'],
        ['wseq' => '1056', 'name' => '宁波银行'],
        ['wseq' => '4836', 'name' => '北京银行'],
        ['wseq' => '1024', 'name' => '上海银行'],
        ['wseq' => '1054', 'name' => '南京银行'],
        ['wseq' => '4216', 'name' => '长沙银行'],
        ['wseq' => '4036', 'name' => '顺德农商银行'],
        ['wseq' => '4753', 'name' => '中原银行'],
        ['wseq' => '4752', 'name' => '衡水银行'],
        ['wseq' => '4756', 'name' => '长治银行'],
        ['wseq' => '4767', 'name' => '大同银行'],
    ];

    /**
     * 获取微信提现银行
     * @param string|null $wsea
     * @return array|string
     */
    public static function banks(?string $wsea = null)
    {
        if (is_null($wsea)) return self::$banks;
        foreach (self::$banks as $bank) if ($bank['wseq'] === $wsea) {
            return $bank['name'];
        }
        return $wsea;
    }

    /**
     * 获取转账类型名称
     * @param string|null $name
     * @return array|string
     */
    public static function types(?string $name = null)
    {
        return is_null($name) ? self::$types : (self::$types[$name] ?? $name);
    }

    /**
     * 同步刷新用户返佣
     * @param integer $unid
     * @return array [total, count, audit, locks]
     */
    public static function amount(int $unid): array
    {
        if ($unid > 0) {
            $locks = abs(PaymentTransfer::mk()->whereRaw("unid='{$unid}' and status=3")->sum('amount'));
            $total = abs(PaymentTransfer::mk()->whereRaw("unid='{$unid}' and status>=1")->sum('amount'));
            $count = abs(PaymentTransfer::mk()->whereRaw("unid='{$unid}' and status>=4")->sum('amount'));
            $audit = abs(PaymentTransfer::mk()->whereRaw("unid='{$unid}' and status>=1 and status<3")->sum('amount'));
        } else {
            $locks = abs(PaymentTransfer::mk()->whereRaw("status=3")->sum('amount'));
            $total = abs(PaymentTransfer::mk()->whereRaw("status>=1")->sum('amount'));
            $count = abs(PaymentTransfer::mk()->whereRaw("status>=4")->sum('amount'));
            $audit = abs(PaymentTransfer::mk()->whereRaw("status>=1 and status<3")->sum('amount'));
        }
        return [$total, $count, $audit, $locks];
    }

    /**
     * 获取提现配置
     * @param ?string $name
     * @return array|string
     * @throws Exception
     */
    public static function config(?string $name = null)
    {
        $ckey = 'payment.transfer.config';
        $data = sysvar($ckey) ?: sysvar($ckey, sysdata($ckey));
        return is_null($name) ? $data : ($data[$name] ?? '');
    }

    /**
     * 获取转账配置
     * @param ?string $name
     * @return array|string
     * @throws Exception
     */
    public static function payment(?string $name = null)
    {
        $ckey = 'payment.transfer.wxpay';
        $data = sysvar($ckey) ?: sysvar($ckey, sysdata($ckey));
        return is_null($name) ? $data : ($data[$name] ?? '');
    }
}