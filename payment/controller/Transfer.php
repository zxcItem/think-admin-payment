<?php

declare (strict_types=1);

namespace app\payment\controller;

use app\account\model\AccountUser;
use app\account\service\Balance;
use app\payment\model\PaymentTransfer;
use app\payment\service\UserTransfer;
use think\admin\Controller;
use think\admin\Exception;
use think\admin\extend\CodeExtend;
use think\admin\helper\QueryHelper;
use think\admin\service\AdminService;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\db\Query;

/**
 * 用户提现管理
 * @class Transfer
 * @package app\payment\controller
 */
class Transfer extends Controller
{
    /**
     * 提现转账方案
     * @var array
     */
    protected $types = [];

    protected function initialize()
    {
        $this->types = UserTransfer::types();
    }

    /**
     * 用户提现配置
     * @throws Exception
     */
    public function config()
    {
        $this->skey = 'payment.transfer.config';
        $this->title = '用户提现配置';
        $this->_sysdata();
    }

    /**
     * 微信转账配置
     * @throws Exception
     */
    public function payment()
    {
        $this->skey = 'payment.transfer.wxpay';
        $this->title = '微信提现配置';
        $this->_sysdata();
    }

    /**
     * 配置数据处理
     * @throws Exception
     */
    private function _sysdata()
    {
        if ($this->request->isGet()) {
            $this->data = sysdata($this->skey);
            $this->fetch('');
        } else {
            sysdata($this->skey, $this->request->post());
            $this->success('配置修改成功');
        }
    }

    /**
     * 用户提现管理
     * @menu true
     * @auth true
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function index()
    {
        PaymentTransfer::mQuery()->layTable(function () {
            $this->title = '用户提现管理';
            $this->transfer = UserTransfer::amount(0);
        }, static function (QueryHelper $query) {
            $query->with(['user']);
            // 用户条件搜索
            $db = AccountUser::mQuery()->like('phone|username|nickname#user')->db();
            if ($db->getOptions('where')) $query->whereRaw("unid in {$db->field('id')->buildSql()}");
            // 数据列表处理
            $query->equal('type,status')->dateBetween('create_time');
        });
    }

    /**
     * 提现审核操作
     * @auth true
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function auditStatus()
    {
        $this->_audit();
    }

    /**
     * 提现打款操作
     * @auth true
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function auditPayment()
    {
        $this->_audit();
    }

    /**
     * 提现审核打款
     * @auth true
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    private function _audit()
    {
        if ($this->request->isGet()) {
            PaymentTransfer::mForm('audit', 'code');
        } else {
            $data = $this->_vali([
                'code.require'        => '打款单号不能为空！',
                'status.require'      => '交易审核操作类型！',
                'status.in:0,1,2,3,4' => '交易审核操作类型！',
                'remark.default'      => '',
            ]);
            $map = ['code' => $data['code']];
            $find = PaymentTransfer::mk()->where($map)->find();
            if (empty($find)) $this->error('不允许操作审核！');
            // 提现状态(0已拒绝, 1待审核, 2已审核, 3打款中, 4已打款, 5已收款)
            if (in_array($data['status'], [0, 1, 2, 3])) {
                if ($data['status'] == 0){
                    Balance::cancel($data['code']);
                    Balance::recount($this->unid);
                }
                $data['last_at'] = date('Y-m-d H:i:s');
            } elseif ($data['status'] == 4) {
                $data['trade_no'] = CodeExtend::uniqidDate(20);
                $data['trade_time'] = date('Y-m-d H:i:s');
                $data['change_time'] = date('Y-m-d H:i:s');
                $data['change_desc'] = ($data['remark'] ?: '线下打款成功') . ' By ' . AdminService::getUserName();
            }
            if (PaymentTransfer::mk()->strict(false)->where($map)->update($data) !== false) {
                $this->success('操作成功');
            } else {
                $this->error('操作失败！');
            }
        }
    }

    /**
     * 后台打款服务
     * @auth true
     */
    public function sync()
    {
        $this->_queue('提现到微信余额定时处理', 'payment:trans', 0, [], 0, 50);
    }
}