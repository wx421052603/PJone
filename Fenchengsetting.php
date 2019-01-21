<?php
use lwmf\services\ServiceFactory;
use lwm\services\SrvType;

class FenchengsettingController extends Controller

{
    public $layout = '//layouts/main';
    public $FirstMenu = '统计';
    public $SecondMenu = '外卖统计';

    /**
     * @return array action filters
     */
    public function filters()
    {
        return array(
                'accessControl', // perform access control for CRUD operations
                'postOnly + delete', // we only allow deletion via POST request
        );
    }

    /**
     * Specifies the access control rules.
     * This method is used by the 'accessControl' filter.
     * @return array access control rules
     */
    public function accessRules()
    {
        return array(
                array(
                        'allow',  // allow all users to perform 'index' and 'view' actions
                        'actions' => array('index','judgewithdraw','shopcheck'),
                        'users'   => array('@'),
                ),
        );
    }

    public function fenchenghassetting($obj)
    {
        if($obj->is_confirm == 1) {
            echo "<span style='color:#6CBC4E'>是</span>";;
        }elseif($obj->is_confirm == 0) {
            echo "<span style='color:#DA4F4A'>否</span>";;
        }
    }

    public function actionIndex()
    {
        $admin_id = Yii::app()->user->_id;
        $shop_ids = array();
        //如果没有记录，创建原始记录
//      $configModel = Config::model()->findAll("admin_id = ".$admin_id);
        $sql = 'SELECT id from wx_config where admin_id = '.$admin_id;
        $configModel = Yii::app()->db->createCommand($sql)->queryAll();
        if($configModel){
            foreach ($configModel as $config){
                $tichModel = FenchengSetting::model()->find('admin_id = '.$admin_id." AND shop_id = ".$config['id']);

                $shop_ids[] = $config['id'];
                //如果不存在，就创建
                if(!$tichModel){
                    $fenchengModel = new FenchengSetting();
                    $fenchengModel->admin_id = $admin_id;
                    $fenchengModel->shop_id = $config['id'];
                    if(!$fenchengModel->save()){
                        LewaimaiDebug::LogModelError($fenchengModel);
                        throw new CHttpException(403, '创建店铺失败！');
                    }
                }
            }
        }else{
            throw new CHttpException(403, '您还没有创建店铺，请先创建店铺！');
        }
        $shopids = array();
        //判断是员工账号的情况
        $employee_shopids = LewaimaiEmployee::CheckAccount();
        if($employee_shopids && $employee_shopids[0] != -9999){
            foreach ($employee_shopids as $val){
                if(in_array($val, $shop_ids)){
                    $shopids[] = $val;
                }
            }
        }
        if(!$shopids){
            $shopids = $shop_ids;
        }

        if($employee_shopids[0] == -9999) {
            $shopids = -9999;
        }

        $model=new FenchengSetting("search");
        $model->unsetAttributes();  // clear any default values
        $model->admin_id = $admin_id;
        $model->shop_ids = $shopids;
        if(isset($_GET['FenchengSetting']))
            $model->attributes=$_GET['FenchengSetting'];
        $this->render('index',array(
            'model'=>$model,
        ));
    }

    public function actionJudgehassetshop()
    {
        $admin_id = Yii::app()->user->_id;
        $sql = 'SELECT count(*) as count from {{config}} where admin_id = '.$admin_id.' and is_delete = 0';
        $count = Yii::app()->db->createCommand($sql)->queryRow()['count'];
        echo $count;
    }

    /**
     * 同步店铺设置
     */
    public function actionSetshop()
    {
        $admin_id = Yii::app()->user->_id;
        if ($shopids = LewaimaiEmployee::CheckAccount())
        {
            $shopids = implode(',', $shopids);
            $shoplists = Config::model()->findAll([
              'select' => 'id,shopname',
              'condition' => 'admin_id=' . $admin_id . ' AND id IN (' . $shopids . ') and is_delete = 0',//过滤掉删除的店铺
            ]);
        }
        else
        {
            $shoplists = Config::model()->findAll([
              'select' => 'id,shopname',
              'condition' => 'admin_id=:admin_id and is_delete = 0',//过滤掉删除的店铺
              'params' => [':admin_id' => $admin_id],
            ]);
        }
        if (isset($_POST['SetShop']))
        {
            if (isset($_POST['SetShop']['syn_type']) && 1 == $_POST['SetShop']['syn_type']) // 全部店铺
            {
                $shop_ids = [];
                foreach ($shoplists as $v)
                {
                    $shop_ids[] = $v->id;
                }
            }
            else // 部分店铺
            {
                $shop_ids = isset($_POST['SetShop_ids']) ? $_POST['SetShop_ids'] : [];
            }

            $shop_id = isset($_POST['SetShop']['shop_id']) ? $_POST['SetShop']['shop_id'] : [];
            $select_setting = isset($_POST['select_setting']) ? $_POST['select_setting'] : [];
            if(empty($shop_id)){
                exit(json_encode(array('status' => 1,'message' => '需要同步设置的源店铺不能为空')));
            }
            if(empty($shop_ids)){
                exit(json_encode(array('status' => 1,'message' => '目标店铺不能为空')));
            }
            if(empty($select_setting)){
                exit(json_encode(array('status' => 1,'message' => '需要同步的设置选项不能为空')));
            }
            $fenchengsettingModel = FenchengSetting::model()->find("admin_id = {$admin_id} AND shop_id = {$shop_id}");
            if(empty($fenchengsettingModel)){
                exit(json_encode(array('status' => 1,'message' => '请先设置源店铺的同步设置项')));
            }

            foreach ($shop_ids as $shopid)
            {
                $fenchengModel = FenchengSetting::model()->find("admin_id = {$admin_id} AND shop_id = {$shopid}");
                $is_update = true;
                if(empty($fenchengModel)){
                    $fenchengModel = new FenchengSetting();
                    $is_update = false;
                    //给默认项
                    $fenchengModel -> admin_id = $admin_id;
                    $fenchengModel -> shop_id = $shopid;
                    $fenchengModel -> foodprice_pt = 40;//平台提成默认40
                    $fenchengModel -> foodprice_sj = 60;//商家提成默认60，有同步设置，再进行修改
                    $fenchengModel -> is_confirm = 1;//默认确认提成比例设置正确
                }
                //收入项
                if(in_array('1',$select_setting)) {
                    $fengcheng_pt = $fenchengsettingModel->foodprice_pt;
                    $fengcheng_sj = $fenchengsettingModel->foodprice_sj;
                    $fenchengModel->foodprice_pt = $fengcheng_pt;
                    $fenchengModel->foodprice_sj = $fengcheng_sj;
                    $fenchengModel->delivery_pt = $fenchengsettingModel->delivery_pt;
                    $fenchengModel->delivery_sj = $fenchengsettingModel->delivery_sj;
                    $fenchengModel->dabao_pt = $fenchengsettingModel->dabao_pt;
                    $fenchengModel->dabao_sj = $fenchengsettingModel->dabao_sj;
                    $fenchengModel->addservice_pt = $fenchengsettingModel->addservice_pt;
                    $fenchengModel->addservice_sj = $fenchengsettingModel->addservice_sj;
                    $fenchengModel->order_field_fee_pt = $fenchengsettingModel->order_field_fee_pt;
                    $fenchengModel->order_field_fee_sj = $fenchengsettingModel->order_field_fee_sj;
                }
                //优惠分摊
                /*if(in_array('2',$select_setting)) {*/
                if(false) {//去掉店铺同步的优惠分摊项
                    $fenchengModel->discount_pt = $fenchengsettingModel->discount_pt;
                    $fenchengModel->discount_sj = $fenchengsettingModel->discount_sj;
                    $fenchengModel->promotion_pt = $fenchengsettingModel->promotion_pt;
                    $fenchengModel->promotion_sj = $fenchengsettingModel->promotion_sj;
                    $fenchengModel->member_pt = $fenchengsettingModel->member_pt;
                    $fenchengModel->member_sj = $fenchengsettingModel->member_sj;
                    $fenchengModel->coupon_pt = $fenchengsettingModel->coupon_pt;
                    $fenchengModel->coupon_sj = $fenchengsettingModel->coupon_sj;
                    $fenchengModel->firstdiscount_pt = $fenchengsettingModel->firstdiscount_pt;
                    $fenchengModel->firstdiscount_sj = $fenchengsettingModel->firstdiscount_sj;
                }
                //其他调整扣除项
                if(in_array('3',$select_setting)) {
                    $fenchengModel->is_deduct_offline = $fenchengsettingModel->is_deduct_offline;
                }
                //其他设置
                if(in_array('4',$select_setting)) {
                    $fenchengModel->is_todakuan = $fenchengsettingModel->is_todakuan;
                    $fenchengModel->is_confirm = $fenchengsettingModel->is_confirm;
                }
                if($is_update){
                    $result = $fenchengModel->update();
                }else{
                    $result = $fenchengModel->save();
                }
                if(!$result){
                    exit(json_encode(array('status' => 1,'message' => '同步失败')));
                }
            }

            exit(json_encode(array('status' => 2,'message' => '同步成功')));
            /*$this->redirect(array('fenchengsetting/index'));
            return;*/
        }

        $this->render('setshop',array(
            'shoplists' => $shoplists,
            'adminaccount' => AdminAccount::model()->findByPk($admin_id)
        ));
    }

    /**
     * 打款设置
     * @author wangsixiao
     * @param int $setWay -设置的打款方式编号[1:天付宝支付到银行卡 2：微信支付到银行卡 3：微信支付到微信零钱]
     */
     public function actionDakuanSet(){
        $admin_id = Yii::app()->user->_id;
        $param = $_GET;
        if(isset($_POST) && !empty($_POST)){
            $param = $_POST;
        }
        //判断商家是否已经设置过打款方式
        $accountModel = AdminAccount::model()->findByPk($admin_id);

        if(!empty($param)){
                if(2 == $param['setWay']||3 == $param['setWay']){
                    $accountModel->dakuan_type = $param['setWay'];
                    $accountModel->update();
                    exit(json_encode(array('errno' => '99999','msg' => '保存成功')));
                }else{
                    exit(json_encode(array('errno' => '99999','msg' => '请选择一种打款方式')));
                }
        }else{
            $this->render('dakuanset',array(
                'accountModel'=>$accountModel,
            ));
        }

//        if(isset($param['setWay'])){
//            if($param['setWay'] == 1){
//                //天付宝支付到银行卡
////                $this->actionSetTianfubaoHandel($param);
//                $this->actionSetwechatpayHandel($param);
//            }else if($param['setWay'] == 2){
//                //微信支付到银行卡或者到微信钱包
//                $this->actionSetwechatpayHandel($param);
//            }elseif($param['setWay'] == 3) {
//                //微信支付到微信零钱
//                $this->actionSetwechatmoneypayHandel($param);
////                $accountModel->dakuan_type = 3;
////                $accountModel->update();
////                $this->render('dakuanset',array(
////                    'accountModel'=>$accountModel
////                ));
//            }else{
//                $this->render('dakuanset',array(
//                    'accountModel'=>$accountModel
//                ));
//            }
//        }else{
//            //若已经设置过，显示已设置过的信息
//            if($accountModel->dakuan_type == 1){
//                $this->actionSetwechatpayHandel($param);
//                //天付宝支付到银行卡
////                $this->actionSetTianfubaoHandel($param);
//            }else if($accountModel->dakuan_type == 2){
//                //微信支付到银行卡或者到微信零钱
//                $this->actionSetwechatpayHandel($param);
//            }elseif($accountModel->dakuan_type == 3) {
//                //微信支付到微信零钱
//                $this->actionSetwechatmoneypayHandel($param);
//            }else{
//                $this->render('dakuanset',array(
//                    'accountModel'=>$accountModel
//                ));
//            }
//        }
    }

    /*
     * 设置商家一键打款的天付宝账号句柄
     *
     * @author wangsixiao
     * **/
    public function actionSetTianfubaoHandel($param)
    {
//        throw new CHttpException(500,'该打款方式已下架！');
        $admin_id = Yii::app()->user->_id;
        //获取天付宝打款商户信息
        $tianxiaApply = TianxiaApply::model()->findAll("admin_id={$admin_id} AND status=3");
        $accountModel = AdminAccount::model()->findByPk($admin_id);

        if(isset($param['AdminAccount'])){
            $mchid = $param['AdminAccount']['tianxiazhifu_mchid'];
            $accountModel->tianxiazhifu_mchid = $mchid;
            $accountModel->dakuan_type = 1;//天付宝到银行卡
            $accountModel->update();
            //$this->redirect(array('statistics/financenews'));
            return;
        }
        if(isset($param['setWay'])){
            $way = $param['setWay'];
        }else{
            $way = isset($accountModel->dakuan_type)&&!empty($accountModel->dakuan_type) ? $accountModel->dakuan_type : 0;
        }
        $this->render('settianfubao',array(
            'tianxiaApply' => $tianxiaApply,
            'accountModel' => $accountModel,
            'setWay' => $way,
        ));
    }

    /*
     * 设置微信打款参数句柄
     *
     * @author wangsixiao
     *
     * **/
    public function actionSetwechatpayHandel($param){
        $adminId = Yii::app()->user->_id;
        //获取微信打款配置信息和微信打款方式
        $dakuanSrv = \lwmf\services\ServiceFactory::getService(\lwm\services\SrvType::COMMON_MERCHANT_SETTINGS_WXDKACCOUNT);
        $accountModel = AdminAccount::model()->findByPk($adminId);
        $model = $dakuanSrv->getInfoByadminId($adminId);
        if (!$model)
        {
            $model = array();
            $model['admin_id'] = $adminId;
            $model['mchid'] = "";
            $model['key'] = "";
            $model['apiclient_cert'] = "";
            $model['apiclient_key'] = "";
            $res = $dakuanSrv->add($adminId,$model);
            $model['id'] = $res;
        }
        //更新微信配置信息
        $msg = '';
        if (isset($param["WeixindakuanAccount"]))
        {
            $model['mchid'] = $param["WeixindakuanAccount"]['mchid'];
            $model['key'] = $param["WeixindakuanAccount"]['key'];
            $model['apiclient_cert'] = $param["WeixindakuanAccount"]['apiclient_cert'];
            $model['apiclient_key'] = $param["WeixindakuanAccount"]['apiclient_key'];
            $dakuanSrv->updateById($model['id'], $model);
            //更新打款方式
            $setWay = $param['setWay'];
            if($accountModel->dakuan_type != $setWay){
                $accountModel->dakuan_type = $setWay;
                $accountModel->update();
            }
            $msg = '保存成功';
        }

        if(isset($param['setWay'])){
            $way = $param['setWay'];
        }else{
            $way = isset($accountModel->dakuan_type)&&!empty($accountModel->dakuan_type) ? $accountModel->dakuan_type : 0;
        }
        $this->render('setweixin',array(
            'model'=>$model,
            'accountModel'=>$accountModel,
            'setWay' => $way,
            'msg' => $msg,
        ));
    }

    /*
     * 设置微信打款参数句柄
     *
     * @author wangsixiao
     *
     * **/
    public function actionSetwechatmoneypayHandel($param){
        $adminId = Yii::app()->user->_id;
        //获取微信打款配置信息和微信打款方式
        $dakuanSrv = \lwmf\services\ServiceFactory::getService(\lwm\services\SrvType::COMMON_MERCHANT_SETTINGS_WXDKACCOUNT);
        $accountModel = AdminAccount::model()->findByPk($adminId);
        $model = $dakuanSrv->getInfoByadminId($adminId);
        if (!$model)
        {
            $model = array();
            $model['admin_id'] = $adminId;
            $res = $dakuanSrv->add($model);
            $model['id'] = $res;
        }
        //更新微信配置信息
        $msg = '';
        if (isset($param["WeixindakuanAccount"]))
        {
            $model['appid_money'] = $param["WeixindakuanAccount"]['appid'];
//            $model['appsecret_money'] = $param["WeixindakuanAccount"]['appsecret'];
            $model['mchid_money'] = $param["WeixindakuanAccount"]['mchid'];
            $model['key_money'] = $param["WeixindakuanAccount"]['key'];
            $model['apiclient_cert_money'] = $param["WeixindakuanAccount"]['apiclient_cert'];
            $model['apiclient_key_money'] = $param["WeixindakuanAccount"]['apiclient_key'];
            $dakuanSrv->updateById($model['id'], $model);
            //更新打款方式
            $setWay = $param['setWay'];
            if($accountModel->dakuan_type != $setWay){
                $accountModel->dakuan_type = $setWay;
                $accountModel->update();
            }
            $msg = '保存成功';
        }

        if(isset($param['setWay'])){
            $way = $param['setWay'];
        }else{
            $way = isset($accountModel->dakuan_type)&&!empty($accountModel->dakuan_type) ? $accountModel->dakuan_type : 0;
        }
        $this->render('setweixinmoney',array(
            'model'=>$model,
            'accountModel'=>$accountModel,
            'setWay' => $way,
            'msg' => $msg,
        ));
    }

    /*
     * 单独设置天赋宝商户号(不修改打款方式)
     *
     * **/
    public function actionSetTianfubao(){
        $admin_id = Yii::app()->user->_id;
        //获取天付宝打款商户信息
        $tianxiaApply = TianxiaApply::model()->findAll("admin_id={$admin_id} AND status=3");
        $accountModel = AdminAccount::model()->findByPk($admin_id);

        if(isset($_POST['AdminAccount'])){
            $mchid = $_POST['AdminAccount']['tianxiazhifu_mchid'];
            $accountModel->tianxiazhifu_mchid = $mchid;
           // $accountModel->dakuan_type = 1;//天付宝到银行卡
            $accountModel->update();

            $this->redirect(array('statistics/financenews'));
            return;
        }
        /*if(isset($param['setWay'])){
            $way = $param['setWay'];
        }else{
            $way = isset($accountModel->dakuan_type)&&!empty($accountModel->dakuan_type) ? $accountModel->dakuan_type : 0;
        }*/
        $this->render('tianfubaodakuan',array(
            'tianxiaApply' => $tianxiaApply,
            'accountModel' => $accountModel,
        ));
    }

    public function actionDelete()
    {
        //先判断这个id的店铺，有没有权限访问
        $adminId = Yii::app()->user->_id;
        $id = isset($_POST['id'])?$_POST['id']:'';
        $fenchengsettingModel = FenchengSetting::model()->findByPk($id);

        if (!$fenchengsettingModel || $fenchengsettingModel->admin_id != $adminId)
        {
            throw new CHttpException(403, '您没有权限！');
        }

        //判断员工账号是否有操作店铺的权限
        if(LewaimaiEmployee::CheckAccounts($fenchengsettingModel->shop_id)){
            throw new CHttpException(403, '无权操作！');
        }
        $this->loadModel($id)->delete();
        // if AJAX request (triggered by deletion via admin grid view), we should not redirect the browser
        if(!isset($_GET['ajax'])){
            //  $this->redirect(isset($_POST['returnUrl']) ? $_POST['returnUrl'] : array('index'));
            $url = isset($_POST['returnUrl']) ? $_POST['returnUrl'] : Yii::app()->createUrl('fenchengsetting/index');
            $msg = array('status' =>false ,'message'=> $url);
            echo CJSON::encode($msg);
            return;
        }
    }

    public function actionCreate(){
        $admin_id = Yii::app()->user->_id;
        //店铺列表
        $shopids = LewaimaiEmployee::CheckAccount();
        if($shopids){
            $shopids = implode(',',$shopids);
            $shoplists = Config::model()->findAll(array(
                      'select'=>array('id','shopname'),
                      'condition' => 'admin_id='.$admin_id.' AND id in ('.$shopids.')',
                    ));
        }else{
            $shoplists = Config::model()->findAll(array(
                      'select'=>array('id','shopname'),
                      'condition' => 'admin_id=:admin_id',
                      'params' => array(':admin_id'=>$admin_id),
                    ));
        }
        $this->render('create',array(
                'shoplists' => $shoplists,
        ));

    }

    public function actionCreateshop()
    {
        //先判断这个id的店铺，有没有权限访问
        $admin_id = Yii::app()->user->_id;
        $shop_id = isset($_POST['shop_id'])?intval($_POST['shop_id']):'';

        $shopModel = Config::model()->findByPk($shop_id);
        if (!$shopModel || $shopModel->admin_id != $admin_id)
        {
            $result["status"] = "error";
            $result["message"] = "您没有权限！";
            $JsonString = JSON($result);
            echo $JsonString;
            exit();
        }

        //判断员工账号是否有操作店铺的权限
        if(LewaimaiEmployee::CheckAccounts($shop_id)){
            $result["status"] = "error";
            $result["message"] = "无权操作！";
            $JsonString = JSON($result);
            echo $JsonString;
            exit();
        }
        //新增数据
        $fenchengsettingModel = FenchengSetting::model()->find("admin_id = ".$admin_id.' AND shop_id = '.$shop_id);
        if($fenchengsettingModel){
            $result["status"] = "error";
            $result["message"] = "该店铺已存在，请勿重复添加！";
            $JsonString = JSON($result);
            echo $JsonString;
            exit();
        }
        $fenchengModel = new FenchengSetting();
        $fenchengModel->admin_id = $admin_id;
        $fenchengModel->shop_id = $shop_id;
        if($fenchengModel->save()){
            $result["status"] = "success";
            $result["message"] = "成功";
            $JsonString = JSON($result);
            echo $JsonString;
            exit();
        }else{
            $result["status"] = "error";
            $result["message"] = "新增失败，请重试！";
            $JsonString = JSON($result);
            echo $JsonString;
            exit();
        }
    }

    public function actionUpdate($id){
        //先判断这个id的店铺，有没有权限访问
        $admin_id = Yii::app()->user->_id;
        $fenchengsettingModel = FenchengSetting::model()->findByPk($id);

        if (!$fenchengsettingModel || $fenchengsettingModel->admin_id != $admin_id)
        {
            throw new CHttpException(403, '您没有权限访问该店铺！');
        }
        //判断员工账号是否有操作店铺的权限
        if(LewaimaiEmployee::CheckAccounts($fenchengsettingModel->shop_id)){
            throw new CHttpException(403, '您无权操作操作该店铺！');
        }

        $model=$this->loadModel($id);

        if(isset($_POST['FenchengSetting']))
        {
            $foodprice_pt = $_POST['FenchengSetting']['foodprice_pt'];
            $foodprice_sj = $_POST['FenchengSetting']['foodprice_sj'];
            if($_POST['FenchengSetting']['is_confirm'] != 1) {
                $model->addError('is_confirm','如已正确设置提成比例请在下方勾选');
            }elseif($foodprice_pt < 0 ||  $foodprice_pt > 40 ) {
                $model->addError('foodprice_pt','商品原价平台分成比例必须在0%-40%');
            }elseif($foodprice_sj < 60 || $foodprice_sj > 100) {
                $model->addError('foodprice_sj','商品原价商家分成比例必须在60%-100%');
            }else{
                //保存分层修改历史记录
                $fenchengHistory = new FenchengSettingHistory();
                $employee_id = Yii::app()->user->getState('employee_id');
                $fenchengHistory -> admin_id = $admin_id;
                $fenchengHistory -> employee_id = $employee_id;
                $fenchengHistory -> shop_id = $model -> shop_id;
                $fenchengHistory -> init_date = date('Y-m-d H:i:s',time());
                $data = array(
                    'foodprice_pt' => $model->foodprice_pt,
                    'foodprice_sj' => $model->foodprice_sj,
                    'delivery_pt' => $model->delivery_pt,
                    'delivery_sj' => $model->delivery_sj,
                    'dabao_pt' => $model->dabao_pt,
                    'dabao_sj' => $model->dabao_sj,
                    'addservice_pt' => $model->addservice_pt,
                    'addservice_sj' => $model->addservice_sj,
                    'discount_pt' => $model->discount_pt,
                    'discount_sj' => $model->discount_sj,
                    'promotion_pt' => $model->promotion_pt,
                    'promotion_sj' => $model->promotion_sj,
                    'member_pt' => $model->member_pt,
                    'member_sj' => $model->member_sj,
                    'coupon_pt' => $model->coupon_pt,
                    'coupon_sj' => $model->coupon_sj,
                    'firstdiscount_pt' => $model->firstdiscount_pt,
                    'firstdiscount_sj' => $model->firstdiscount_sj,
                    'order_field_fee_pt' => $model->order_field_fee_pt,
                    'order_field_fee_sj' => $model->order_field_fee_sj,
                    'is_todakuan' => $model->is_todakuan,
                );
                $fenchengHistory -> data = json_encode($data);
                if($fenchengHistory -> save()){
                $model->attributes = $_POST['FenchengSetting'];
                    if($model->save()){
                $this->redirect(array('fenchengsetting/index'));
            }
        }
            }
        }
        $this -> render('update',array(
                'model' => $model,
        ));
    }

    public function actionShopbank(){

        $admin_id = Yii::app()->user->_id;
        if (isset($_GET['shop_id']))
        {
            $shop_id = addslashes($_GET['shop_id']);
        }
        else
        {
            throw new CHttpException(404, '您访问的页面不存在！');
        }
        //这里用来验证店铺id是否在该账号下
        $configModel = Config::model()->findByPk($shop_id);
        if($configModel->admin_id != $admin_id){
            throw new CHttpException(404, '无权操作该店铺！');
        }
       $model = ShopAccount::model()->find("admin_id =".$admin_id." AND shop_id =".$shop_id);
       $error = array();
       if(isset($model-> bankname_no) && !empty($model-> bankname_no) && isset($model -> bankcard_no) && !empty($model -> bankcard_no)){
           $issubmit = true;
       }else{
        $issubmit = false;
       }
       $success = array();
       if(isset($_POST['headbankname'])){
           if(!$issubmit){
               $headbankname =  trim(htmlspecialchars($_POST['headbankname']));
               $province =  trim(htmlspecialchars($_POST['province']));
               $city =  trim(htmlspecialchars($_POST['city']));
               $bankname =  trim(htmlspecialchars($_POST['bankname']));
               $bankname_no =  trim(htmlspecialchars($_POST['bankname_no']));
               $bankusername =  trim(htmlspecialchars($_POST['bankusername']));
               $bankcard_no =  trim(htmlspecialchars($_POST['bankcard_no']));
               $bankcard_no =  str_replace(' ', '', $bankcard_no);

               $queren_bankcard_no =  trim(htmlspecialchars($_POST['queren_bankcard_no']));
               $queren_bankcard_no =  str_replace(' ', '', $queren_bankcard_no);
               $bank_type =  trim(htmlspecialchars($_POST['bank_type']));
               if($headbankname == '' or $headbankname === 0 or $headbankname === '0'){
                   $error[] = '要选择银行类型';
               }
               if($province == '' or $province === 0 or $province === '0'){
                   $error[] = '要选择省份';
               }
               if($city == '' or $city === 0 or $city === '0'){
                   $error[] = '要选择城市';
               }
               if($bankname == '' or $bankname === 0 or $bankname === '0'){
                   $error[] = '要选择开户行网点';
               }
               if($bankname_no == ''){
                   $error[] = '联行号不能为空';
               }
               if($bankusername == ''){
                   $error[] = '银行开户名不能为空';
               }

               $length = strlen($bankcard_no);
               if($bankcard_no == '' || $length < 1){
                   $error[] = '银行卡号不能为空';
               }
               for ($i=0; $i < $length; $i++) {
                   if (!is_numeric($bankcard_no[$i])) {
                       $error[] = '银行卡号不能非数字';
                       break;
                   }
               }
               if($bankcard_no != $queren_bankcard_no){
                   $error[] = '银行卡号和确认银行卡号不一致';
               }
               if(!in_array($bank_type,array(0,1))){
                   exit;
               }

               //收款人姓名验证
               if ($bank_type == 0) {
                   //私人
                   //判断是否是中文
                    if(!preg_match("/^[\x{4e00}-\x{9fa5}|\.|\·|·]+$/u",$bankusername)){
                       $error[] = "银行开户名只支持中文";
                   }
                   //判断姓名长度
                    if(!preg_match("/^[\x{4e00}-\x{9fa5}|\.|\·|·]{2,30}$/u",$bankusername)){
                       $error[] = "银行开户名最少为2个字，最多为30个字";
                    }
                    if (strpos($bankusername, '有限') !== false || strpos($bankusername, '公司') !== false) {
                        $error[] = "个人类型的银行卡，不能包含有限，公司等词";
                    }
               } else {
                   //对公
                   //判断是否是中文
                   if(!preg_match("/^[\x{4e00}-\x{9fa5}]|&#xFF08;&#xFF09;+$/u",$bankusername)){
                       echo '银行开户名只支持中文';
                       $error[] = "银行开户名只支持中文";
                   }
                   //判断姓名长度
                   if(!preg_match("/^[\x{4e00}-\x{9fa5}]|&#xFF08;&#xFF09;{8,40}$/u",$bankusername)){
                       $error[] = "银行开户名最少为8个字";
                   }
               }
               if(count($error) == 0){
                   if($model){
                       $model->headbankname = $headbankname;
                       $model->province = $province;
                       $model->city = $city;
                       $model->bankname = $bankname;
                       $model->bankname_no = $bankname_no;
                       $model->bankusername = $bankusername;
                       $model->bankcard_no = $bankcard_no;
                       $model->bank_type = $bank_type;
                       if($model->save()){
                           $success[] = '设置成功';
                           $issubmit = true;
                       }else{
                           $error[] = '设置失败';
                       }
                   }else{
                       $model = new ShopAccount();
                       $model->admin_id = $admin_id;
                       $model->shop_id = $shop_id;
                       $model->province = $province;
                       $model->city = $city;
                       $model->headbankname = $headbankname;
                       $model->bankname = $bankname;
                       $model->bankname_no = $bankname_no;
                       $model->bankusername = $bankusername;
                       $model->bankcard_no = $bankcard_no;
                       $model->bank_type = $bank_type;
                       if($model->save()){
                           $success[] = '设置成功';
                           $issubmit = true;
                       }else{
                           $error[] = '设置失败';
                       }
                   }
                   //更新分成设置的银行卡是否绑定字段信息
                   $fenchengmodel = FenchengSetting::model()->find("admin_id = ".$admin_id." AND shop_id = ".$shop_id);
                   $fenchengmodel->is_blindcard = 1;
                   $fenchengmodel->update();
                   //更新汇付天下申请列表的银行卡是否绑定字段信息
                   $huifumodel = HuifuShopApply::model()->find("admin_id = ".$admin_id." AND shop_id = ".$shop_id);
                   if($huifumodel){
                       $huifumodel->is_blindcard = 1;
                       $huifumodel->update();
                   }else{
                       $huifuModel = new HuifuShopApply();
                       $huifuModel->admin_id = $admin_id;
                       $huifuModel->shop_id = $shop_id;
                       $huifuModel->is_blindcard = 1;
                       $huifuModel->save();
                   }
               }
           }
       }
        $this -> render('shopbank',array(
                'model' => $model,
                'error' => $error,
                'issubmit' => $issubmit,
                'success' => $success,
                'configModel' => $configModel,
                 'shop_id' => $shop_id
        ));
    }

    /*
     * 发送短信验证码
     * method:get 请求方式
     * tel:手机号  参数
     * scene：场景【1或者不传：换绑银行卡短信验证 2绑定手机号短信验证】 参数
     *
     * **/
    public function actionSendCode(){
        $admin_id = Yii::app()->user->_id;
        $shop_id = $_GET['shopId'];
        $smsType = isset($_GET['smstype']) ? intval($_GET['smstype']) : 30; //smsType 短信类型->32:打款账号设置 30:您正在绑定提现手机号操作 @MaWei:2018-06-08 15:22:14
        if(!isset($shop_id) || empty($shop_id)){
            exit(json_encode(array('status'=>1,'msg'=>'请求参数有误')));
        }
        //判断手机号不能为空
        if(!isset($_GET['tel']) || empty($_GET['tel'])){
            exit(json_decode(array('status'=>1,'errorMsg'=>'手机号为空')));
        }else{
            $phone = $_GET['tel'];
        }
        //判断有效期内是否已经发送过验证码
        $smsCacheSrv = ServiceFactory::getService(SrvType::COMMON_SMS_CACHE_SMSCACHE);
//        if(isset($_GET['scene'])&&$_GET['scene'] == 2){
//            $smsCode = $smsCacheSrv -> getShopBindPhoneVerifyCode($shop_id);
//        }else{
//            $smsCode = $smsCacheSrv -> getChangeBindBankCardVerifyCode($shop_id);
//        }
//
//        if(!empty($smsCode)){
//            exit(json_encode(array('status' => 1,'msg' => '已发送验证码还在有效期内可重复验证')));
//        }
        //判断商家的短信数量是否足够
        $sql = "SELECT sms_quota FROM wx_admin WHERE id = " . $admin_id . " LIMIT 1 FOR UPDATE";
        $row = LewaimaiDB::queryRow($sql);
        if(!$row){
            exit(json_encode(array('status' => 2,'msg' => '请求失败')));
        }
        $smsNum = $row['sms_quota'];//剩余的可发送短信条数
        $StringQuota = 1;//单条发送
        if($smsNum < $StringQuota){
            exit(json_encode(array('status' => 2,'msg' => '短信余额不足')));
        }
        //获取验证码
        $verifyCode = LewaimaiString::create_randnum(6);
        //发送验证码
        //$send = new \lwm\services\modules\common\sms\imps\Sms();
        $userIp = \lwm\commons\base\Helper::getUserHostIp();

       // $smsCacheSrv = ServiceFactory::getService(SrvType::COMMON_SMS_CACHE_SMSCACHE);
        //缓存绑定手机号验证码
        if(isset($_GET['scene'])&&$_GET['scene'] == 2){
           // echo "1";
            $result = $smsCacheSrv -> setShopBindPhoneVerifyCode($shop_id,$verifyCode);
        }else{
           // echo "2";
            $result = $smsCacheSrv->setChangeBindBankCardVerifyCode($shop_id,$verifyCode);
        }

      //  echo "<pre>";
      //  print_r($result);
      //  $smsCode2 = $smsCacheSrv -> getChangeBindBankCardVerifyCode($shop_id);
      //  var_dump($smsCode2);

        if($result){
          //发送验证码
          //
            /*****@Start**短信通道修改*******@MaWei:2018-06-08 14:29:00*******@Start*********/
                // $result = \lwm\services\modules\common\sms\imps\Sms::sendSmsVerifyCodeForBackend($admin_id,$phone, $verifyCode, $userIp, 7,'30分钟');
                $result = \lwm\services\modules\common\sms\imps\Sms::sendSmsVerifyCodeForBackend($admin_id,$phone, $verifyCode, $userIp, 7,'30分钟',$smsType); //短信类型->32:打款账号设置 30:您正在绑定提现手机号操作
            /*****@End****短信通道修改*******@MaWei:2018-06-08 14:29:00*******@End*******/

            //$result = \lwmf\base\MessageServer::getInstance()->dispatch(\config\constants\WorkerTypes::SEND_SMS_VERIFYCODE_FOR_BACKEND,[$admin_id,$phone, $verifyCode, $userIp, 4]);
            if($result){
                //验证码发送成功，扣除商家短信余额
                $sql = "UPDATE wx_admin SET sms_quota = sms_quota - " . $StringQuota . " WHERE id = " . $admin_id;
                $dec = LewaimaiDB::execute($sql);
                if($dec){
                    //记录短信发送历史
                    $info = array(
                        'admin_id'=>$admin_id,
                        'content'=>$verifyCode,
                        'phone'=>$phone,
                        'ip'=>$userIp,
                        'lewaimai_customer_id'=>0,
                        'number'=>1,
                        'broadcast_type'=>0,
                        'employee_id'=>\Yii::app()->user->getState('usertype') == 0 ? 0 : \Yii::app()->user->getState('employee_id'),
                    );
                    if(isset($_GET['scene'])&&$_GET['scene'] == 2){
                        $info['type'] = 20;
                    }else{
                        $info['type'] = 21;
                    }
                    $phoneMessageHistorySrv = ServiceFactory::getService(SrvType::COMMON_SMS_PHONE_MESSAGE_HISTORY);
                    $res = $phoneMessageHistorySrv->add($admin_id, $info);
                    if($res){
                       // echo "3330";
                    exit(json_encode(array('status' => 2,'msg' => '验证码发送成功，请注意查收')));
                }
                }
            }else{

                //echo "5";
                //删除缓存的验证码值
                if(isset($_GET['scene'])&&$_GET['scene'] == 2){
                    $smsCacheSrv -> deleteShopBindPhoneVerifyCode($shop_id);
                }else{
                    $smsCacheSrv->deleteChangeBindBankCardVerifyCode($shop_id);
                }
                exit(json_encode(array('status' => 1,'msg' => '验证码发送失败')));
            }
        }else{
            exit(json_encode(array('status' => 1,'msg' => '验证码失败')));
        }

    }

    /*
     * 短信验证码验证
     *
     * scene：场景【1或者不传：换绑银行卡短信验证 2绑定手机号短信验证】 参数
     *
     * **/
    public function actionCheckCode(){
        $admin_id = Yii::app()->user->_id;
        $shop_id = $_GET['shopId'];
        if(!isset($_GET['code']) || empty($_GET['code'])){
            exit(json_encode(array('status' => 1,'msg' => '请输入验证码')));
        }else{
            $code = $_GET['code'];
        }
        //从缓存中获取缓存的验证码信息
        $smsCacheSrv = ServiceFactory::getService(SrvType::COMMON_SMS_CACHE_SMSCACHE);
        if(isset($_GET['scene'])&&$_GET['scene'] == 2){
            $smsCode = $smsCacheSrv -> getShopBindPhoneVerifyCode($shop_id);
        }else{
            $smsCode = $smsCacheSrv -> getChangeBindBankCardVerifyCode($shop_id);
        }

        if(empty($smsCode)){
            exit(json_encode(array('status' => 1,'msg' => '验证码已过期')));
        }
        if($code != $smsCode){
            exit(json_encode(array('status' => 1,'msg' => '验证码错误')));
        }
        exit(json_encode(array('status' => 2,'msg' => '验证通过，请填写完整信息，点击保存')));
    }

    public function actionEditbank(){
        $admin_id = Yii::app()->user->_id;
        //判断来源 1来自账户管理 其他来自修改银行卡
        $flag = isset($_GET['flag']);
        if (isset($_GET['shop_id']))
        {
            $shop_id = addslashes($_GET['shop_id']);
        }
        else
        {
            throw new CHttpException(404, '您访问的页面不存在！');
        }
        //这里用来验证店铺id是否在该账号下
        $configModel = Config::model()->findByPk($shop_id);
        if($configModel->admin_id != $admin_id){
            throw new CHttpException(404, '无权操作该店铺！');
        }
       $model = ShopAccount::model()->find("admin_id =".$admin_id." AND shop_id =".$shop_id);
       $error = array();
       $success = array();
       if(isset($_POST['headbankname'])){
               $headbankname =  trim(htmlspecialchars($_POST['headbankname']));
               $province =  trim(htmlspecialchars($_POST['province']));
               $city =  trim(htmlspecialchars($_POST['city']));
               $bankname =  trim(htmlspecialchars($_POST['bankname']));
               $bankname_no =  trim(htmlspecialchars($_POST['bankname_no']));
               $bankusername =  trim(htmlspecialchars($_POST['bankusername']));
               $bankcard_no =  trim(htmlspecialchars($_POST['bankcard_no']));
               $bankcard_no =  str_replace(' ', '', $bankcard_no);

               $queren_bankcard_no =  trim(htmlspecialchars($_POST['queren_bankcard_no']));
               $queren_bankcard_no =  str_replace(' ', '', $queren_bankcard_no);
               $bank_type =  trim(htmlspecialchars($_POST['bank_type']));
               if($headbankname == '' or $headbankname === 0 or $headbankname === '0'){
                   $error[] = '要选择银行类型';
               }
               if($province == '' or $province === 0 or $province === '0'){
                   $error[] = '要选择省份';
               }
               if($city == '' or $city === 0 or $city === '0'){
                   $error[] = '要选择城市';
               }
               if($bankname == '' or $bankname === 0 or $bankname === '0'){
                   $error[] = '要选择开户行网点';
               }
               if($bankname_no == ''){
                   $error[] = '联行号不能为空';
               }
               if($bankusername == ''){
                   $error[] = '银行开户名不能为空';
               }

               $length = strlen($bankcard_no);
               if($bankcard_no == '' || $length < 1){
                   $error[] = '银行卡号不能为空';
               }
               for ($i=0; $i < $length; $i++) {
                   if (!is_numeric($bankcard_no[$i])) {
                       $error[] = '银行卡号不能非数字';
                       break;
                   }
               }
               if($bankcard_no != $queren_bankcard_no){
                   $error[] = '银行卡号和确认银行卡号不一致';
               }
               if(!in_array($bank_type,array(0,1))) {
                   exit;
               }

               //收款人姓名验证
               if ($bank_type == 0) {
                   //私人
                   //判断是否是中文
                    if(!preg_match("/^[\x{4e00}-\x{9fa5}|\.|\·|·]+$/u",$bankusername)){
                       $error[] = "银行开户名只支持中文";
                   }
                   //判断姓名长度
                    if(!preg_match("/^[\x{4e00}-\x{9fa5}|\.|\·|·]{2,30}$/u",$bankusername)){
                       $error[] = "银行开户名最少为2个字，最多为30个字";
                    }
                    if (strpos($bankusername, '有限') !== false || strpos($bankusername, '公司') !== false) {
                        $error[] = "个人类型的银行卡，不能包含有限，公司等词";
                    }
               } else {
                   //对公
                   //判断是否是中文
                   if(!preg_match("/^[\x{4e00}-\x{9fa5}]|&#xFF08;&#xFF09;+$/u",$bankusername)){
                       echo '银行开户名只支持中文';
                       $error[] = "银行开户名只支持中文";
                   }
                   //判断姓名长度
                   if(!preg_match("/^[\x{4e00}-\x{9fa5}]|&#xFF08;&#xFF09;{8,40}$/u",$bankusername)){
                       $error[] = "银行开户名最少为8个字";
                   }
               }
               if(count($error) == 0){
                       $model->headbankname = $headbankname;
                       $model->province = $province;
                       $model->city = $city;
                       $model->bankname = $bankname;
                       $model->bankname_no = $bankname_no;
                       $model->bankusername = $bankusername;
                       $model->bankcard_no = $bankcard_no;
                       $model->bank_type = $bank_type;
                       if($model->save()){
                           //更新分成设置的银行卡是否绑定字段信息
                           $fenchengmodel = FenchengSetting::model()->find("admin_id = ".$admin_id." AND shop_id = ".$shop_id);
                           $fenchengmodel->is_blindcard = 1;
                           $fenchengmodel->update();

                           $huifushopapply = HuifuShopApply::model() -> find(" admin_id = ".$admin_id." AND shop_id=".$shop_id);
                           if( $huifushopapply -> status == 2 ) {
                               $shopaccount    = ShopAccount::model()    -> find(" admin_id = ".$admin_id." AND shop_id=".$shop_id);
                               $adminaccount   = AdminAccount::model()   -> find(" admin_id = ".$admin_id);
                               $huifucitycode  = HuifuCitycode::model()  -> find(' city_name= "'.$shopaccount -> city.'"');
                               $isValidata = $this -> validateData($huifushopapply,$shopaccount,$adminaccount,$huifucitycode); //数据验证
                               if(!$isValidata)  $this->redirect(['huifu/shopindex']);//
                               $huiFuParams = $this -> gethuiFuParams($huifushopapply,$shopaccount,$adminaccount,$huifucitycode);
                               $huifu = new \lwm\commons\pay\channel\agent\HuiFu();
                               $res = $huifu -> bindingcard($huiFuParams);
                               LewaimaiDebug::Log("================================");
                               LewaimaiDebug::LogArray($res);
                               if($res['code'] == 400) {

                                   LewaimaiDebug::Log("重新获取汇付返回值");
                                   LewaimaiDebug::LogArray($res);
                                   $hsa = HuifuShopApply::model()->findByPk($huifushopapply -> id);
                                   if($res['data'] -> resp_code == 104000)  {
                                       $hsa -> huifu_cash_bind_card_id = $res['data'] -> cash_bind_card_id;//银行卡绑定ID,取现接口需要用到此ID,由汇付返回
                                       $hsa -> is_blindcard = 1;//是否绑定银行卡 0否 1是
                                       if(!$hsa -> save()) {
                                           LewaimaiDebug::Log("重新获取huifushopapply、shopaccount");
                                           LewaimaiDebug::LogArray($hsa);
                                       }
                                   }else {
                                       $hsa -> bindingcard_fail_reason = $res['data'] -> resp_desc;
                                       $hsa -> save();
                                       //exit(json_encode(array('status' => 0,'msg' => $res['data'] -> resp_desc)));
                                   }

//                                   $this->redirect(['huifu/shopindex']);
                               }
                           }

                           if($flag==1) {
                               $this->redirect(yii::app()->createUrl('fenchengsetting/shopaccountmanagement',array('shop_id'=>$shop_id)));
                            exit();
                       }else{
                               $this->redirect(yii::app()->createUrl('fenchengsetting/shopbank',array('shop_id'=>$shop_id)));
                               exit();
                           }

                       }else{
                           $error[] = '设置失败';
                       }
               }
       }
        $this -> render('editbank',array(
                'model' => $model,
                'error' => $error,
                'success' => $success,
                'configModel' => $configModel,
                 'shop_id' => $shop_id
        ));
    }

    /*
     * 提现店铺首次绑定手机号--绑定
     *
     * **/
    public function actionShopBindPhone(){

        $admin_id = Yii::app()->user->_id;
        $param = $_GET;
        if(isset($_POST) && !empty($_POST)){
            $param = $_POST;
        }
        $shopAccount = ShopAccount::model()->find('admin_id=:adminID and shop_id=:shopID', array(':adminID'=>$admin_id,':shopID'=>$param['shopId']));
        if(isset($param['tel']) && !empty($param['tel'])){
            //添加操作
            //先验证两次输入手机号的一致性
            if($param['tel'] != $param['telNew']){
                exit(json_encode(array('status'=>1,'msg'=>'两次手机号输入不一致')));
            }
            //验证手机号格式是否正确
            $pattern = '/^1[3456789]{1}\d{9}$/';
            if(!preg_match($pattern,$param['tel'])){
                exit(json_encode(array('status'=>1,'msg'=>'手机号格式输入有误')));
            }
            //验证验证码是否正确
            if(!isset($param['code']) || empty($param['code'])){
                exit(json_encode(array('status' => 1,'msg' => '请输入验证码')));
            }

            $smsCacheSrv = ServiceFactory::getService(SrvType::COMMON_SMS_CACHE_SMSCACHE);
            $smsCode = $smsCacheSrv -> getShopBindPhoneVerifyCode($param['shopId']);

            if(empty($smsCode)){
                exit(json_encode(array('status' => 1,'msg' => '验证码已过期')));
            }
            if($smsCode != $param['code']){
                exit(json_encode(array('status' => 1,'msg' => '验证码输入错误')));
            }
            //一个手机号只能绑定五个商铺
            $count = ShopAccount::model() -> count('admin_id=:adminID and phone=:phone',array(':adminID' => $admin_id,':phone' => $param['tel']));
            if($count >= 5){
                exit(json_encode(array('status' => 1,'msg' => '该手机号绑定次数已超限，请更换其他手机号绑定')));
            }
            //判断表里面是否已经有记录，有的话修改，没的话添加
            if(isset($shopAccount -> shop_id) && !empty($shopAccount -> shop_id)){
                $shopAccount->phone = $param['tel'];
                if($shopAccount->update()){
                    $info = array();
                    $user_type = Yii::app()->user->getState('usertype');
                    if($user_type != 0) {
                        $info['employee_id'] = Yii::app()->user->employee_id;
                    }
                    $info['shop_id'] = $param['shopId'];
                    $info['act_type'] = 2;
                    $info['dynamic'] = '首次绑定手机号，绑定的手机号为：'.$param['tel'];
                    $info['act_date'] = date('Y-m-d H:i:s');

                    $service = \lwmf\services\ServiceFactory::getService(\lwm\services\SrvType::TRADE_ORDER_WITHDRAW_LOG);
                    $service->addWithdrawactlog($admin_id, $info);

                    exit(json_encode(array('status' => 2,'msg' => '保存成功')));
                }
            }else{
                $shopAccount = new ShopAccount();
                $shopAccount -> admin_id = $admin_id;
                $shopAccount -> shop_id = $param['shopId'];
                $shopAccount -> phone = $param['tel'];
                if($shopAccount -> save()){
                    $info = array();
                    $user_type = Yii::app()->user->getState('usertype');
                    if($user_type != 0) {
                        $info['employee_id'] = Yii::app()->user->employee_id;
                    }
                    $info['shop_id'] = $param['shopId'];
                    $info['act_type'] = 2;
                    $info['dynamic'] = '首次绑定手机号，绑定的手机号为：'.$param['tel'];
                    $info['act_date'] = date('Y-m-d H:i:s');

                    $service = \lwmf\services\ServiceFactory::getService(\lwm\services\SrvType::TRADE_ORDER_WITHDRAW_LOG);
                    $service->addWithdrawactlog($admin_id, $info);

                    exit(json_encode(array('status' => 2,'msg' => '保存成功')));
                }
            }
        }else{
            //获取店铺名称
            $shopInfo = Config::model() -> findByPk($param['shopId']);
            //若没有设置过获取添加页面，若是已经设置，获取显示页面
            if(isset($shopAccount -> phone) && !empty($shopAccount -> phone)){
                $this -> render('shopbindphone',array(
                    'shop_id' => $param['shopId'],
                    'shop_name' => $shopInfo -> shopname,
                    'tel' => $shopAccount -> phone,
                ));
            }else{
                $this -> render('shopbindphone',array(
                    'shop_id' => $param['shopId'],
                    'shop_name' => $shopInfo -> shopname,
                ));
            }
        }
    }

    /*
     * 体现商铺更换绑定手机号--更换
     *
     * **/
    public function actionChangeShopBindPhone(){
        $param = $_GET;
        if(isset($_POST) && !empty($_POST)){
            $param = $_POST;
    }
        $admin_id = Yii::app()->user->_id;
        $shopAccount = ShopAccount::model()->find('admin_id=:adminID and shop_id=:shopID', array(':adminID'=>$admin_id,':shopID'=>$param['shopId']));
        if(isset($param['telNew']) && !empty($param['telNew'])){
            //更换提交，保存新的手机号
            //判断验证码
            $smsCacheSrv = ServiceFactory::getService(SrvType::COMMON_SMS_CACHE_SMSCACHE);
            $shopId = $param['tel'].'-'.$param['shopId'];
            $smsCode = $smsCacheSrv -> getShopBindPhoneVerifyCode($shopId);
            if(empty($smsCode)){
                exit(json_encode(array('status' => 1,'msg' => '原手机号验证码已过期')));
            }
            if($smsCode != $param['code']){
                exit(json_encode(array('status' => 1,'msg' => '原手机号验证码输入错误')));
            }
            $shopIds = $param['telNew'].'-'.$param['shopId'];
            $smsCodeNew = $smsCacheSrv -> getShopBindPhoneVerifyCode($shopIds);
            if(empty($smsCodeNew)){
                exit(json_encode(array('status' => 1,'msg' => '新手机号验证码已过期')));
            }
            if($smsCodeNew != $param['codeNew']){
                exit(json_encode(array('status' => 1,'msg' => '新手机号验证码输入错误')));
            }
            //验证手机号格式是否正确
            $pattern = '/^1[3456789]{1}\d{9}$/';
            if(!preg_match($pattern,$param['telNew'])){
                exit(json_encode(array('status'=>1,'msg'=>'新手机号格式输入有误')));
            }
            //保存更换的手机号
            $shopAccount -> phone = $param['telNew'];
            $result = $shopAccount -> update();
            if($result){
                $info = array();
                $user_type = Yii::app()->user->getState('usertype');
                if($user_type != 0) {
                    $info['employee_id'] = Yii::app()->user->employee_id;
                }
                $info['shop_id'] = $param['shopId'];
                $info['act_type'] = 2;
                $info['dynamic'] = '更换绑定手机号，原手机号为：'.$param['tel'].'更改为'.$param['telNew'];
                $info['act_date'] = date('Y-m-d H:i:s');

                $service = \lwmf\services\ServiceFactory::getService(\lwm\services\SrvType::TRADE_ORDER_WITHDRAW_LOG);
                $service->addWithdrawactlog($admin_id, $info);

                exit(json_encode(array('status' => 2,'msg' => '保存成功')));
            }

        }else{
            //获取显示的更换页面
            //获取店铺名称
            $shopInfo = Config::model() -> findByPk($param['shopId']);
            $this -> render('changeshopbindphone',array(
                'shop_id' => $shopAccount -> shop_id,
                'phone' => $shopAccount -> phone,
                'shop_name' => $shopInfo -> shopname,
            ));
        }
    }

    //修改订单的银行卡信息
    public function actionEdititembank($id){
        $admin_id = Yii::app()->user->_id;
        $model = DakuanOrderItem::model()->findByPk($id);
        if(!$model){
            throw new CHttpException(404, '您访问的页面不存在！');
        }
        $shop_id = $model->shop_id;
        if($model->admin_id != $admin_id){
            throw new CHttpException(404, '无权操作该店铺！');
        }
       $error = array();
       $success = array();
       if(isset($_POST['headbankname'])){
               $headbankname =  trim(htmlspecialchars($_POST['headbankname']));
               $province =  trim(htmlspecialchars($_POST['province']));
               $city =  trim(htmlspecialchars($_POST['city']));
               $bankname =  trim(htmlspecialchars($_POST['bankname']));
               $bankname_no =  trim(htmlspecialchars($_POST['bankname_no']));
               $bankusername =  trim(htmlspecialchars($_POST['bankusername']));
               $bankcard_no =  trim(htmlspecialchars($_POST['bankcard_no']));
               $bankcard_no =  str_replace(' ', '', $bankcard_no);

               $queren_bankcard_no =  trim(htmlspecialchars($_POST['queren_bankcard_no']));
               $queren_bankcard_no =  str_replace(' ', '', $queren_bankcard_no);
               $bank_type =  trim(htmlspecialchars($_POST['bank_type']));
               if($headbankname == '' or $headbankname === 0 or $headbankname === '0'){
                   $error[] = '要选择银行类型';
               }
               if($province == '' or $province === 0 or $province === '0'){
                   $error[] = '要选择省份';
               }
               if($city == '' or $city === 0 or $city === '0'){
                   $error[] = '要选择城市';
               }
               if($bankname == '' or $bankname === 0 or $bankname === '0'){
                   $error[] = '要选择开户行网点';
               }
               if($bankname_no == ''){
                   $error[] = '联行号不能为空';
               }
               if($bankusername == ''){
                   $error[] = '银行开户名不能为空';
               }

               $length = strlen($bankcard_no);
               if($bankcard_no == '' || $length < 1){
                   $error[] = '银行卡号不能为空';
               }
               for ($i=0; $i < $length; $i++) {
                   if (!is_numeric($bankcard_no[$i])) {
                       $error[] = '银行卡号不能非数字';
                       break;
                   }
               }
               if($bankcard_no != $queren_bankcard_no){
                   $error[] = '银行卡号和确认银行卡号不一致';
               }
               if(!in_array($bank_type,array(0,1))){
                   exit;
               }

               //收款人姓名验证
               if ($bank_type == 0) {
                   //私人
                   //判断是否是中文
                    if(!preg_match("/^[\x{4e00}-\x{9fa5}|\.|\·|·]+$/u",$bankusername)){
                       $error[] = "银行开户名只支持中文";
                   }
                   //判断姓名长度
                    if(!preg_match("/^[\x{4e00}-\x{9fa5}|\.|\·|·]{2,30}$/u",$bankusername)){
                       $error[] = "银行开户名最少为2个字，最多为30个字";
                    }
                    if (strpos($bankusername, '有限') !== false || strpos($bankusername, '公司') !== false) {
                        $error[] = "个人类型的银行卡，不能包含有限，公司等词";
                    }
               } else {
                   //对公
                   //判断是否是中文
                   if(!preg_match("/^[\x{4e00}-\x{9fa5}]|&#xFF08;&#xFF09;+$/u",$bankusername)){
                       echo '银行开户名只支持中文';
                       $error[] = "银行开户名只支持中文";
                   }
                   //判断姓名长度
                   if(!preg_match("/^[\x{4e00}-\x{9fa5}]|&#xFF08;&#xFF09;{8,40}$/u",$bankusername)){
                       $error[] = "银行开户名最少为8个字";
                   }
               }
               if(count($error) == 0){
                       $model->headbankname = $headbankname;
                       $model->bankname = $bankname;
                       $model->bankname_no = $bankname_no;
                       $model->bankusername = $bankusername;
                       $model->bankcard_no = $bankcard_no;
                       $model->bank_type = $bank_type;
                       if($model->update()){                $this->redirect(yii::app()->createUrl('fenchengsetting/dakuanitem',array('id'=>$model->order_id)));
                            exit();
                       }else{
                           $error[] = '设置失败';
                       }
               }
       }
        $this -> render('edititembank',array(
                'model' => $model,
                'error' => $error,
                'success' => $success,
        ));




    }
    public function actionDakuan(){
//      $admin_id = Yii::app()->user->_id;
//这里要显示所以参与一键打款的店铺，不能用ShopAccount，要用fenchengsetting
//      $model = new ShopAccount();
//      $model->unsetAttributes();
//      $model->admin_id = $admin_id;

//      $sql = "SELECT id,shop_id FROM {{shop_account}} WHERE admin_id = " . $admin_id;
//      $res = Yii::app()->db->createCommand($sql)->queryAll();
//      $shopaccount = array();
//      if($res){
//          foreach ($res as $rs){
//              $shopaccount[$rs['id']]=$rs['shop_id'];
//          }
//      }
//      $shop_account = json_encode($shopaccount);
//      $this->render('dakuan',array(
//          'model' => $model,
//          'beginDate' => $beginDate,
//          'endDate' => $endDate,
//          'shop_account' => $shop_account,
//      ));
        $admin_id = Yii::app()->user->_id;
        $beginDate = isset($_GET['beginDate'])?$_GET['beginDate']:date('Y-m-d',strtotime('-7 day'));
        $endDate = isset($_GET['endDate'])?$_GET['endDate']:date('Y-m-d');
        $area_id = isset($_GET['area_id'])?$_GET['area_id']:-1;
        $model = new FenchengSetting();
        $model->unsetAttributes();
        $model->admin_id = $admin_id;
        $model->is_todakuan = 1;
        $model->area_id = $area_id;

        $sql = "SELECT id,shop_id FROM {{fencheng_setting}} WHERE admin_id = " . $admin_id." AND is_todakuan = 1";
        $res = Yii::app()->db->createCommand($sql)->queryAll();
        $shopaccount = array();
        if($res){
            foreach ($res as $rs){
                $shopaccount[$rs['id']]=$rs['shop_id'];
            }
        }
        $shop_account = json_encode($shopaccount);

        $areaName = '';
        if($area_id == -1){
            if(Yii::app()->user->getState('usertype') == 1){
                //员工账号
                $employeeModel = EmployeesAccount::model()->findByPk(Yii::app()->user->getState('employee_id'));
                if($employeeModel && $employeeModel->role_type == 2){
                    $area_id = $employeeModel->area_id;
                }
            }
        }

        $areaModel = Area::model()->findByPk($area_id);
        if($areaModel){
            $areaName = $areaModel->area_name;
        }else{
            $area_id = 0;
        }


        $adminaccountModel = AdminAccount::model()->findByPk($admin_id);
        if($adminaccountModel->dakuan_type == 3){
            $this->render('wechatdakuan',array(
                'model' => $model,
                'beginDate' => $beginDate,
                'endDate' => $endDate,
                'shop_account' => $shop_account,
                'areaName'  => $areaName,
                'area_id'   => $area_id,
            ));
        }else{
            $this->render('dakuan',array(
                'model' => $model,
                'beginDate' => $beginDate,
                'endDate' => $endDate,
                'shop_account' => $shop_account,
                'areaName'  => $areaName,
                'area_id'   => $area_id,
            ));
        }

    }

    public function actionTianxiadakuan($beginDate,$endDate,$area_id){
//这里要显示所以参与一键打款的店铺，不能用ShopAccount，要用fenchengsetting
//      $model = new ShopAccount();
//      $model->unsetAttributes();
//      $model->admin_id = $admin_id;

//      $sql = "SELECT id,shop_id FROM {{shop_account}} WHERE admin_id = " . $admin_id;
//      $res = Yii::app()->db->createCommand($sql)->queryAll();
//      $shopaccount = array();
//      if($res){
//          foreach ($res as $rs){
//              $shopaccount[$rs['id']]=$rs['shop_id'];
//          }
//      }
//      $shop_account = json_encode($shopaccount);
//      $this->render('dakuan',array(
//          'model' => $model,
//          'beginDate' => $beginDate,
//          'endDate' => $endDate,
//          'shop_account' => $shop_account,
//      ));
        $admin_id = Yii::app()->user->_id;
        if($admin_id != 85663){
            throw new CHttpException(500,'该打款方式已下架！');
        }
        $model = new FenchengSetting();
        $model->unsetAttributes();
        $model->admin_id = $admin_id;
        $model->is_todakuan = 1;
        $model->area_id = $area_id;

        $sql = "SELECT id,shop_id FROM {{fencheng_setting}} WHERE admin_id = " . $admin_id." AND is_todakuan = 1";
        $res = Yii::app()->db->createCommand($sql)->queryAll();
        $shopaccount = array();
        if($res){
            foreach ($res as $rs){
                $shopaccount[$rs['id']]=$rs['shop_id'];
            }
        }
        $shop_account = json_encode($shopaccount);

        $areaName = '';
        if($area_id == -1){
            if(Yii::app()->user->getState('usertype') == 1){
                //员工账号
                $employeeModel = EmployeesAccount::model()->findByPk(Yii::app()->user->getState('employee_id'));
                if($employeeModel && $employeeModel->role_type == 2){
                    $area_id = $employeeModel->area_id;
                }
            }
        }

        $areaModel = Area::model()->findByPk($area_id);
        if($areaModel){
            $areaName = $areaModel->area_name;
        }else{
            $area_id = 0;
        }

        $this->render('tianxiadakuan',array(
            'model' => $model,
            'beginDate' => $beginDate,
            'endDate' => $endDate,
            'shop_account' => $shop_account,
            'areaName'  => $areaName,
            'area_id'   => $area_id,
        ));

    }

    //确认打款
    public function actionDakuanconfirm($area_id){
        $admin_id = Yii::app()->user->_id;
        $adminaccountModel = AdminAccount::model()->findByPk($admin_id);
        if($adminaccountModel->dakuan_type == 2){
            $this->render('dakuanconfirm',array(
                'admin_id' => $admin_id,
                'area_id'  => $area_id,
                'adminaccountModel' => $adminaccountModel,
            ));
        }elseif($adminaccountModel->dakuan_type == 3){
            $this->render('wechatdakuanconfirm',array(
                'admin_id' => $admin_id,
                'area_id'  => $area_id,
                'adminaccountModel' => $adminaccountModel,
            ));
        }

    }

    //确认打款
    public function actionTianxiadakuanconfirm($area_id){
        $admin_id = Yii::app()->user->_id;
        if($admin_id != 85663){
        throw new CHttpException(500,'该打款方式已下架！');
        }
        if($admin_id != 85663){
            throw new CHttpException(500,'该打款方式已下架！');
        }
        $adminaccountModel = AdminAccount::model()->findByPk($admin_id);
        $this->render('tianxiadakuanconfirm',array(
            'admin_id' => $admin_id,
            'area_id'  => $area_id,
            'adminaccountModel' => $adminaccountModel,
        ));

    }

    //开始进行打款

    public function actionTodakuan(){
        $result = array('status' =>"error" ,'message'=> "该打款方式已下架！");
        echo CJSON::encode($result);
        exit();
        $admin_id = Yii::app()->user->_id;
        LewaimaiDebug::LogArray("商家乐刷打款 ".$admin_id);
        // if($admin_id == 19){
        //     LewaimaiDebug::LogArray("一键打款调试ddddd");
        // }
        // if($admin_id != 19){
        //     $result = array('status' =>"error" ,'message'=> "正在维护，暂时无法打款！");
        // echo CJSON::encode($result);
        // exit();
        // }
        $result = array();
        if(!isset($_POST['dakuan_id']) || !isset($_POST['dakuan_money']) || !isset($_POST['password'])){
              $result = array('status' =>"error" ,'message'=> "参数错误！");
              echo CJSON::encode($result);
              exit();
         }
        $adminModel = Admin::model()->findByPk($admin_id);
        if($adminModel->level < 2){
              $result = array('status' =>"error" ,'message'=> "您的会员等级不够，无法进行一键打款，请先升级会员！");
              echo CJSON::encode($result);
              exit();
        }
        $shopids = $_POST['dakuan_id'];
        $dakuan_money = $_POST['dakuan_money'];
        $password = $_POST['password'];
        $area_id = intval($_POST['area_id']);
        LewaimaiDebug::LogArray($area_id);
        $memo = $_POST['memo'];
        if(count($shopids) <=0 || count($dakuan_money) <=0){
            $result = array('status' =>"error" ,'message'=> "无相关打款数据，请重新操作！");
            echo CJSON::encode($result);
            exit();
        }
        if(count($shopids) != count($dakuan_money)){
            $result = array('status' =>"error" ,'message'=> "打款数据存在错误，请重新操作！");
            echo CJSON::encode($result);
            exit();
        }
        $shopid_arr = implode(",", $shopids);
        $date = date("Y-m-d H:i:s",strtotime("-1 minute"));
        $sql = "SELECT id FROM wx_dakuan_order_item WHERE admin_id = " . $admin_id . " AND init_date >= '".$date."' AND shop_id in (".$shopid_arr.")";
        $row = LewaimaiDB::queryRow($sql);
        if($row){
            $result = array('status' =>"error" ,'message'=> "一分钟内请勿频繁对同一个店铺进行打款！");
            echo CJSON::encode($result);
            exit();
        }
        $key = "fenchengsetting_dakuan".$admin_id;
        $value = "dakuan";
        $expire = 60;
        $redis = \lwmf\datalevels\Redis::getInstance()->setnx($key, $value, $expire);
        LewaimaiDebug::LogArray("redis测试".$admin_id);
        LewaimaiDebug::LogArray($redis);
        if(!$redis){
            $result = array('status' =>"error" ,'message'=> "一分钟内请勿频繁对同一个店铺进行打款1！");
            echo CJSON::encode($result);
            exit();
        }
        $paramArray = array();

        $paramArray["dakuan_id"] = json_encode($shopids);
        $paramArray["dakuan_money"] = json_encode($dakuan_money);
        $paramArray["password"] = $password;
        $paramArray["area_id"] = $area_id;
        $paramArray["memo"] = $memo;

        LewaimaiDebug::LogArray($paramArray);
        $retArray = LewaimaiRequestApi::Send("/withdraw/dakuan", $paramArray);
        Yii::log("aaa");
        LewaimaiDebug::LogArray($retArray);
        if (!$retArray)
        {
            $result = array('status' =>"error" ,'message'=> "服务器错误，请重新操作！");
            echo CJSON::encode($result);
            exit();
        }
        if ($retArray["errcode"] == 0)
        {
            $result = array('status' =>"success" ,'message'=> "打款成功！");
            echo CJSON::encode($result);
            exit();
        }
        else
        {
            $result = array('status' =>"error" ,'message'=> $retArray["errmsg"]);
            echo CJSON::encode($result);
            exit();
        }
    }


    //开始进行打款
    public function actionTotianxiadakuan(){
//        $result = array('status' =>"error" ,'message'=> "该功能暂时无法使用！");
//        echo CJSON::encode($result);
//        exit();
        $admin_id = Yii::app()->user->_id;
        if($admin_id != 85663){
            throw new CHttpException(500,'该打款方式已下架！');
        }
        LewaimaiDebug::LogArray("商家天下支付打款 ".$admin_id);
        // if($admin_id == 19){
        //     LewaimaiDebug::LogArray("一键打款调试ddddd");
        // }
        // if($admin_id != 19){
        //     $result = array('status' =>"error" ,'message'=> "正在维护，暂时无法打款！");
        // echo CJSON::encode($result);
        // exit();
        // }
        $result = array();
        if(!isset($_POST['dakuan_id']) || !isset($_POST['dakuan_money']) || !isset($_POST['password'])){
            $result = array('status' =>"error" ,'message'=> "参数错误！");
            echo CJSON::encode($result);
            exit();
        }
        $adminModel = Admin::model()->findByPk($admin_id);
        if($adminModel->level < 2){
            $result = array('status' =>"error" ,'message'=> "您的会员等级不够，无法进行一键打款，请先升级会员！");
            echo CJSON::encode($result);
            exit();
        }

        $log_string = "操作日志-天下支付打款 admin_id=".$admin_id." 员工账号id=".Yii::app()->user->getState('employee_id')." 请求参数 ".json_encode($_POST);
        \lwmf\base\Logger::info($log_string);

        $shopids = $_POST['dakuan_id'];
        $dakuan_money = $_POST['dakuan_money'];
        $password = $_POST['password'];
        $area_id = intval($_POST['area_id']);
        LewaimaiDebug::LogArray($area_id);
        $memo = $_POST['memo'];
        if(count($shopids) <=0 || count($dakuan_money) <=0){
            $result = array('status' =>"error" ,'message'=> "无相关打款数据，请重新操作！");
            echo CJSON::encode($result);
            exit();
        }
        if(count($shopids) != count($dakuan_money)){
            $result = array('status' =>"error" ,'message'=> "打款数据存在错误，请重新操作！");
            echo CJSON::encode($result);
            exit();
        }
        $shopid_arr = implode(",", $shopids);
        $date = date("Y-m-d H:i:s",strtotime("-1 minute"));
        $sql = "SELECT id FROM wx_dakuan_order_item WHERE admin_id = " . $admin_id . " AND init_date >= '".$date."' AND shop_id in (".$shopid_arr.")";
        $row = LewaimaiDB::queryRow($sql);
        if($row){
            $result = array('status' =>"error" ,'message'=> "一分钟内请勿频繁对同一个店铺进行打款！");
            echo CJSON::encode($result);
            exit();
        }

        $dakuan_id = $shopids;
        $employee_id = Yii::app()->user->getState('employee_id');
        //先检测提现帐号是否已经设置
//        $transaction = LewaimaiDB::GetTransaction();

        $transaction = \lwmf\datalevels\RdbTransaction::getInstance();
        $transaction->begin();
        try {
            $sql = "SELECT balance, password, tianxiazhifu_mchid FROM wx_admin_account WHERE admin_id = " . $admin_id . " LIMIT 1 FOR UPDATE";
            $row = LewaimaiDB::queryRow($sql);
            if (!$row)
            {
                $transaction->rollback();
                $result = array('status' =>"error" ,'message'=> "乐外卖帐号账户不存在！");
                echo CJSON::encode($result);
                exit();
            }
            if(empty($row['tianxiazhifu_mchid'])){

                $transaction->rollback();
                $result = array('status' =>"error" ,'message'=> "天下支付账号未设置！");
                echo CJSON::encode($result);
                exit();
            }
            $tianxiazhifu_mchid = $row['tianxiazhifu_mchid'];

            $account_password = $row["password"];
            //检测提现密码是否正确
            if ($account_password != md5($password))
            {
                $transaction->rollback();
                $result = array('status' =>"error" ,'message'=> "提现密码错误！");
                echo CJSON::encode($result);
                exit();
            }
            $key = "fenchengsetting_dakuan".$admin_id;
            $value = "dakuan";
            $expire = 60;
            $redis = \lwmf\datalevels\Redis::getInstance()->setnx($key, $value, $expire);
            LewaimaiDebug::LogArray("redis测试".$admin_id);
            LewaimaiDebug::LogArray($redis);
            if(!$redis){
                $transaction->rollback();
                $result = array('status' =>"error" ,'message'=> "一分钟内请勿频繁对同一个店铺进行打款1！");
                echo CJSON::encode($result);
                exit();
            }
            //total_money打款总额
            $total_money = 0;
            foreach ($dakuan_money as $val){
                $val = round($val,2);
                $total_money += $val;
            }
            //这里是每个店铺需要打款金额。shop_id为key，金额为val
            $shop_dakuan = array();
            foreach ($dakuan_id as $key=>$val){
                $shop_dakuan[$val] = $dakuan_money[$key];
            }
            //这里找到需要打款的店铺
            $shop_str = implode(",", $dakuan_id);
            $sql = "SELECT id,shop_id,headbankname,bankusername,bankcard_no,bank_type,bankname,bankname_no FROM {{shop_account}} WHERE admin_id = " . $admin_id." AND shop_id in (".$shop_str.")";
            $res = Yii::app()->db->createCommand($sql)->queryAll();
            if(!$res){
                $transaction->rollback();
                $result = array('status' =>"error" ,'message'=> "无相关打款店铺数据，请重新操作！");
                echo CJSON::encode($result);
                exit();
            }
            $init_date = date("Y-m-d H:i:s");
            //新建打款订单
            $dakuanorderModel = new DakuanOrder();
            $dakuanorderModel->admin_id = $admin_id;
            $dakuanorderModel->total_money = $total_money;
            $dakuanorderModel->init_date = $init_date;
            $dakuanorderModel->shop_count = count($dakuan_id);
            $dakuanorderModel->employee_id = $employee_id;
            $dakuanorderModel->area_id = $area_id;
            $dakuanorderModel->memo = $memo;
            $dakuanorderModel->pingtai_type = 12;
            if(!$dakuanorderModel->save()){
                $transaction->rollback();

                LewaimaiDebug::LogModelError($dakuanorderModel);
                $transaction->rollback();
                $result = array('status' =>"error" ,'message'=> "服务器错误，打款失败！");
                echo CJSON::encode($result);
                exit();
            }
            //打款订单的order_id
            $dakuan_order_arr = array();
            //新建打款订单详情
            foreach ($res as $rs){
                $dakuan_arr = array();
                //检测打款金额是否正确
                if (!is_numeric($shop_dakuan[$rs['shop_id']]))
                {
                    $transaction->rollback();
                    $result = array('status' =>"error" ,'message'=> "打款金额格式错误！");
                    echo CJSON::encode($result);
                    exit();
                }

//              if ($shop_dakuan[$rs['shop_id']] < 100)
//              {
//                  $transaction->rollback();
//                    $transaction->rollback();
//                    $result = array('status' =>"error" ,'message'=> "每次打款金额不能小于100");
//                    echo CJSON::encode($result);
//              }
//                if ($shop_dakuan[$rs['shop_id']] < 1)
//                {
//                    $transaction->rollback();
//                    $result = array('status' =>"error" ,'message'=> "每次打款金额不能小于1元！");
//                    echo CJSON::encode($result);
//                    exit();
//                }
                //这里需要加入店铺的名称，防止出现店铺删除找不到的情况
                //检测余额变化表最后一次新余额跟当前账号余额是否一致，如果不一致就是有bug或者被人恶意篡改
                $sql1 = "SELECT id,shopname FROM {{config}} where id = " . $rs['shop_id']." and is_delete=0";
                $row1 = LewaimaiDB::queryRow($sql1);
                if (!$row1)
                {
                    $transaction->rollback();
                    $result = array('status' =>"error" ,'message'=> "非法的打款请求，有店铺已删除！");
                    echo CJSON::encode($result);
                    exit();
                }
                //提现订单号，D开头表示乐外卖账号平台给商家打款
                $out_trade_no = "11" . LewaimaiString::GetUniqueTradeNo(20);
                $dakuanorderitemModel = new DakuanOrderItem();
                $dakuanorderitemModel->order_id = $dakuanorderModel->id;
                $dakuanorderitemModel->admin_id = $admin_id;
                $dakuanorderitemModel->shop_id = $rs['shop_id'];
                $dakuanorderitemModel->shopname = $row1['shopname'];
                $dakuanorderitemModel->headbankname = $rs['headbankname'];
                $dakuanorderitemModel->bankusername = $rs['bankusername'];
                $dakuanorderitemModel->bankcard_no = $rs['bankcard_no'];
                $dakuanorderitemModel->bankname = $rs['bankname'];
                $dakuanorderitemModel->bankname_no = $rs['bankname_no'];
                $dakuanorderitemModel->money = $shop_dakuan[$rs['shop_id']];
                $dakuanorderitemModel->init_date = $init_date;
                $dakuanorderitemModel->status = 0;
                $dakuanorderitemModel->employee_id = $employee_id;
                $dakuanorderitemModel->out_trade_no = $out_trade_no;
                $dakuanorderitemModel->pingtai_type = 12;
                $dakuanorderitemModel->bank_type = $rs['bank_type'];
                $dakuanorderitemModel->dakuan_mchid = $tianxiazhifu_mchid;
                if(!$dakuanorderitemModel->save()){
                    $transaction->rollback();

                    LewaimaiDebug::LogModelError($dakuanorderitemModel);
                    $transaction->rollback();
                    $result = array('status' =>"error" ,'message'=> "服务器错误，打款失败！");
                    echo CJSON::encode($result);
                    exit();
                }
                //添加提现订单提交记录
                $info = array();
                $info['admin_id'] = $admin_id;
                $info['value'] = $shop_dakuan[$rs['shop_id']];
                $info['init_date'] = $init_date;
                $info['headbankname'] = $rs['headbankname'];
                $info['bankname'] = $rs['bankname'];
                $info['bankname_no'] = $rs['bankname_no'];
                $info['bankusername'] = $rs['bankusername'];
                $info['bankcard_no'] = $rs['bankcard_no'];
                $info['admin_describe'] = "一键给商家打款";
                //打款平台：0官方渠道10:乐刷12:天下支付13:顺丰支付14：民生银行
                $info['pingtai_type'] = 12;
                $info['order_id'] = $dakuanorderitemModel->id;
                $info['out_trade_no'] = $out_trade_no;
                $info['dakuan_type'] = 2;
                $info['dakuan_mchid'] = $tianxiazhifu_mchid;
                $dakuan_res = \lwmf\services\ServiceFactory::getService(\lwm\services\SrvType::TRADE_ORDER_WITHDRAW_HISTORY)->add($admin_id,$info);

                if (!$dakuan_res)
                {
                    $transaction->rollback();
                    $result = array('status' =>"error" ,'message'=> "服务器错误3！");
                    echo CJSON::encode($result);
                    exit();
                }
                //这里其实可以考虑把$dakuanorderitemModel直接放入$dakuan_order_id里面，这样就不用再后面查数据了，但是考虑如果数据较多有可能把数组撑爆，还是直接放id
                $dakuan_arr['order_id'] = $dakuanorderitemModel->id;
                $dakuan_arr['out_trade_no'] = $out_trade_no;
                $dakuan_arr['value'] = $shop_dakuan[$rs['shop_id']];
                $dakuan_arr['bankusername'] = $rs['bankusername'];
                $dakuan_arr['bankname_no'] = $rs['bankname_no'];
                $dakuan_arr['bankcard_no'] = $rs['bankcard_no'];
                $dakuan_arr['bank_type'] = $rs['bank_type'];
                $dakuan_arr['des'] = "一键给商家打款";
                array_push($dakuan_order_arr, $dakuan_arr);
            }

            //这里就要把事务提交，然后再调用乐刷的接口，不然的话有可能调用接口已经成功，款已经打给顾客了，但是接口超时没有返回，如果这种情况下回滚，就会导致客户白白收到钱，所以这里的记录一定要先存起来才安全，千万不能再提交接口后还回滚
            $transaction->commit();

        } catch (Exception $e) {
            $transaction->rollback();
            $result = array('status' =>"error" ,'message'=> "服务器错误！");
            echo CJSON::encode($result);
            exit();
        }
        LewaimaiDebug::LogArray("时间测试");
        LewaimaiDebug::LogArray(date("Y-m-d H:i:s"));
        if(count($dakuan_order_arr) > 0){
            LewaimaiDebug::LogArray($dakuan_order_arr);
            if(count($dakuan_order_arr) > 100){
                //大于100，需要分页，不然会造成消息队列过大，程序崩溃
                $i = ceil(count($dakuan_order_arr)/100);
                LewaimaiDebug::LogArray($i);
                for($j=0;$j<$i;$j++){
                    $arr = array();
                    foreach ($dakuan_order_arr as $key=>$val){
                        if($key >= $j*100 && $key < ($j+1)*100){
                            array_push($arr, $dakuan_order_arr[$key]);
                        }
                    }
                    if($arr){
                        LewaimaiDebug::LogArray($arr);
                        \lwmf\base\MessageServer::getInstance()->dispatch(\config\constants\WorkerTypes::MERCHANT_SETTING_TIANXIA, array($admin_id,$arr));
                        unset($arr);
                    }
                }
            }else{
                \lwmf\base\MessageServer::getInstance()->dispatch(\config\constants\WorkerTypes::MERCHANT_SETTING_TIANXIA, array($admin_id,$dakuan_order_arr));
            }
            LewaimaiDebug::LogArray(date("Y-m-d H:i:s"));
            LewaimaiDebug::LogArray("dsadas");
//            $config = \lwmf\services\ServiceFactory::getService(\lwm\services\SrvType::TRADE_PAY_CONFIG)->getTianxiaConfig($admin_id,\lwm\commons\pay\BisTypeDef::SHANGHU_TIANXIA_AGENT,0);
//            LewaimaiDebug::LogArray($config);
//            $payChannel = \lwm\commons\pay\PayFactory::getInstance()->getAgentChannel($config);
//            $timestamp = time();
//            LewaimaiDebug::LogArray(date("Y-m-d H:i:s"));
//            foreach ($dakuan_order_arr as $val){
//                //调用开始代付的接口
//                $params = new \lwm\commons\pay\channel\AgentSinglePayParam();
//                $params->outTradeNo = $val['out_trade_no'];
//                $params->amount = $val['value'];
//                $params->transTime = $timestamp;
//                $params->payType = \lwm\commons\pay\channel\AgentSinglePayParam::PAY_TYPE_BALANCE;
//                if($val['bank_type'] == 0){
//                    //私人账户
//                    $params->bankAccountType = \lwm\commons\pay\channel\AgentSinglePayParam::BANK_ACCOUNT_TYPE_DEBIT_CARD;
//
//                }elseif($val['bank_type'] == 1){
//                    //对公账户
//                    $params->bankAccountType = \lwm\commons\pay\channel\AgentSinglePayParam::BANK_ACCOUNT_TYPE_PUBLIC;
//                    $params->bankSettleNo = $val['bankname_no'];
//                }
//                $params->bankUserName =  $val['bankusername'];
//                $params->bankNo = $val['bankcard_no'];
//                $params->memo = $val['des'];
//                $res = $payChannel->singlePay($params);
//                if($res && isset($res['status'])){
//                    $dakuanitemSrv = \lwmf\services\ServiceFactory::getService(\lwm\services\SrvType::TRADE_ORDER_DAKUAN_ITEM);
//                    if($res['status'] == \lwm\commons\pay\channel\IAgentChannel::PAY_STATUS_SUCCESS)
//                    {
//                        //这里表示已经打款成功，把订单状态设置为成功状态
//                        $info = array();
//                        $info['status'] = 1;
//                        $info['complete_date'] = date("Y-m-d H:i:s");
//                        $dakuanitemSrv->updateById($admin_id, $val['order_id'], $info);
//                    }elseif ($res['status'] == \lwm\commons\pay\channel\IAgentChannel::PAY_STATUS_FAIL){
//                        //返回失败的都当做处理中，以查询接口返回的状态为准
//
////                        $info = array();
////                        $info['status'] = 2;
////                        $info['complete_date'] = date("Y-m-d H:i:s");
////                        $info['memo'] = $res['errmsg'];
////                        $dakuanitemSrv->updateById($admin_id, $val['order_id'], $info);
//                    }
//
//                }
//                LewaimaiDebug::LogArray("是否发送请求1");
//                LewaimaiDebug::LogArray($res);
//            }
        }
        LewaimaiDebug::LogArray(date("Y-m-d H:i:s"));
        $result = array('status' =>"success" ,'message'=> "打款请求提交成功！");
        echo CJSON::encode($result);
        exit();
    }

    //开始进行微信打款
    public function actionTowechatdakuan(){
//        $result = array('status' =>"error" ,'message'=> "该功能暂时无法使用！");
//        echo CJSON::encode($result);
//        exit();
        $admin_id = Yii::app()->user->_id;
        LewaimaiDebug::LogArray("商家微信打款 ".$admin_id);
        // if($admin_id == 19){
        //     LewaimaiDebug::LogArray("一键打款调试ddddd");
        // }
        // if($admin_id != 19){
        //     $result = array('status' =>"error" ,'message'=> "正在维护，暂时无法打款！");
        // echo CJSON::encode($result);
        // exit();
        // }
        $result = array();
        if(!isset($_POST['dakuan_id']) || !isset($_POST['dakuan_money']) || !isset($_POST['password'])){
            $result = array('status' =>"error" ,'message'=> "参数错误！");
            echo CJSON::encode($result);
            exit();
        }
        $adminModel = Admin::model()->findByPk($admin_id);
        if($adminModel->level < 2){
            $result = array('status' =>"error" ,'message'=> "您的会员等级不够，无法进行一键打款，请先升级会员！");
            echo CJSON::encode($result);
            exit();
        }


        $log_string = "操作日志-微信官方打款 admin_id=".$admin_id." 员工账号id=".Yii::app()->user->getState('employee_id')." 请求参数 ".json_encode($_POST);
        \lwmf\base\Logger::info($log_string);

        $shopids = $_POST['dakuan_id'];
        $dakuan_money = $_POST['dakuan_money'];
        $password = $_POST['password'];
        $area_id = intval($_POST['area_id']);
        LewaimaiDebug::LogArray($area_id);
        $memo = $_POST['memo'];
        if(count($shopids) <=0 || count($dakuan_money) <=0){
            $result = array('status' =>"error" ,'message'=> "无相关打款数据，请重新操作！");
            echo CJSON::encode($result);
            exit();
        }
        if(count($shopids) != count($dakuan_money)){
            $result = array('status' =>"error" ,'message'=> "打款数据存在错误，请重新操作！");
            echo CJSON::encode($result);
            exit();
        }
        $shopid_arr = implode(",", $shopids);
        $date = date("Y-m-d H:i:s",strtotime("-5 minute"));
        $sql = "SELECT id FROM wx_dakuan_order_item WHERE admin_id = " . $admin_id . " AND init_date >= '".$date."' AND shop_id in (".$shopid_arr.")";
        $row = LewaimaiDB::queryRow($sql);
        if($row){
            $result = array('status' =>"error" ,'message'=> "五分钟内请勿频繁对同一个店铺进行打款！");
            echo CJSON::encode($result);
            exit();
        }

        $dakuan_id = $shopids;
        $employee_id = Yii::app()->user->getState('employee_id');
        //先检测提现帐号是否已经设置
//        $transaction = LewaimaiDB::GetTransaction();

        $transaction = \lwmf\datalevels\RdbTransaction::getInstance();
        $transaction->begin();
        try {

            $sql = "SELECT balance, password, dakuan_type FROM wx_admin_account WHERE admin_id = " . $admin_id . " LIMIT 1 FOR UPDATE";
            $row = LewaimaiDB::queryRow($sql);
            if (!$row)
            {
                $transaction->rollback();
                $result = array('status' =>"error" ,'message'=> "乐外卖帐号账户不存在！");
                echo CJSON::encode($result);
                exit();
            }
            $account_password = $row["password"];
            //检测提现密码是否正确
            if ($account_password != md5($password))
            {
                $transaction->rollback();
                $result = array('status' =>"error" ,'message'=> "提现密码错误！");
                echo CJSON::encode($result);
                exit();
            }\lwmf\base\Logger::info($row);
            if($row["dakuan_type"] == 2){
                //微信打款到银行卡
                $pingtai_type = 15;
            }elseif($row["dakuan_type"] == 3){
                //微信打款到零钱
                $pingtai_type = 16;
            }else{
                $transaction->rollback();
                $result = array('status' =>"error" ,'message'=> "打款方式错误，无法进行打款！");
                echo CJSON::encode($result);
                exit();
            }

            if($pingtai_type == 15){
                $sql = "SELECT * FROM wx_weixindakuan_account WHERE admin_id = " . $admin_id . " LIMIT 1 FOR UPDATE";
            }elseif($pingtai_type == 16){
                $sql = "SELECT * FROM wx_weixinzhifu_account WHERE admin_id = " . $admin_id . " LIMIT 1 FOR UPDATE";
            }
            $row = LewaimaiDB::queryRow($sql);
            if (!$row)
            {
                $transaction->rollback();
                $result = array('status' =>"error" ,'message'=> "微信账户未设置或不存在！");
                echo CJSON::encode($result);
                exit();
            }
            if(empty($row['mchid'])){

                $transaction->rollback();
                $result = array('status' =>"error" ,'message'=> "微信打款账号未设置！");
                echo CJSON::encode($result);
                exit();
            }
            $mchid = $row['mchid'];
            //total_money打款总额
            $total_money = 0;
            foreach ($dakuan_money as $val){
                $val = round($val,2);
                $total_money += $val;
            }
            //这里是每个店铺需要打款金额。shop_id为key，金额为val
            $shop_dakuan = array();
            foreach ($dakuan_id as $key=>$val){
                $shop_dakuan[$val] = $dakuan_money[$key];
            }
            //这里找到需要打款的店铺
            $shop_str = implode(",", $dakuan_id);
            $sql = "SELECT id,shop_id,headbankname,bankusername,bankcard_no,bank_type,bankname,bankname_no,openid,wechat_name FROM {{shop_account}} WHERE admin_id = " . $admin_id." AND shop_id in (".$shop_str.")";
            $res = Yii::app()->db->createCommand($sql)->queryAll();
            if(!$res){
                $transaction->rollback();
                $result = array('status' =>"error" ,'message'=> "无相关打款店铺数据，请重新操作！");
                echo CJSON::encode($result);
                exit();
            }
            //屏蔽redis的限制
           /* $key = "fenchengsetting_dakuan".$admin_id;
            $value = "dakuan";
            $expire = 300;
            $redis = \lwmf\datalevels\Redis::getInstance()->setnx($key, $value, $expire);
            LewaimaiDebug::LogArray("redis测试".$admin_id);
            LewaimaiDebug::LogArray($redis);
            if(!$redis){
                $transaction->rollback();
                $result = array('status' =>"error" ,'message'=> "五分钟内请勿频繁对同一个店铺进行打款1！");
                echo CJSON::encode($result);
                exit();
            }*/
            $init_date = date("Y-m-d H:i:s");
            //新建打款订单
            $dakuanorderModel = new DakuanOrder();
            $dakuanorderModel->admin_id = $admin_id;
            $dakuanorderModel->total_money = $total_money;
            $dakuanorderModel->init_date = $init_date;
            $dakuanorderModel->shop_count = count($dakuan_id);
            $dakuanorderModel->employee_id = $employee_id;
            $dakuanorderModel->area_id = $area_id;
            $dakuanorderModel->memo = $memo;
            $dakuanorderModel->pingtai_type = $pingtai_type;
            if(!$dakuanorderModel->save()){
                $transaction->rollback();

                LewaimaiDebug::LogModelError($dakuanorderModel);
                $transaction->rollback();
                $result = array('status' =>"error" ,'message'=> "服务器错误，打款失败！");
                echo CJSON::encode($result);
                exit();
            }
            //打款订单的order_id
            $dakuan_order_arr = array();
            //新建打款订单详情
            foreach ($res as $rs){
                $dakuan_arr = array();
                //检测打款金额是否正确
                if (!is_numeric($shop_dakuan[$rs['shop_id']]))
                {
                    $transaction->rollback();
                    $result = array('status' =>"error" ,'message'=> "打款金额格式错误！");
                    echo CJSON::encode($result);
                    exit();
                }

//              if ($shop_dakuan[$rs['shop_id']] < 100)
//              {
//                  $transaction->rollback();
//                    $transaction->rollback();
//                    $result = array('status' =>"error" ,'message'=> "每次打款金额不能小于100");
//                    echo CJSON::encode($result);
//              }
//                if ($shop_dakuan[$rs['shop_id']] < 1)
//                {
//                    $transaction->rollback();
//                    $result = array('status' =>"error" ,'message'=> "每次打款金额不能小于1元！");
//                    echo CJSON::encode($result);
//                    exit();
//                }
                //这里需要加入店铺的名称，防止出现店铺删除找不到的情况
                $sql1 = "SELECT id,shopname FROM {{config}} where id = " . $rs['shop_id'].' and is_delete=0';
                $row1 = LewaimaiDB::queryRow($sql1);
                if (!$row1)
                {
                    $transaction->rollback();
                    $result = array('status' =>"error" ,'message'=> "非法的打款请求，有店铺已删除！");
                    echo CJSON::encode($result);
                    exit();
                }
                //提现订单号，33表示到银行卡44表示到零钱
                if($pingtai_type == 15){
                if($rs['headbankname'] == "农村信用联社"){
                    $transaction->rollback();
                    $result = array('status' =>"error" ,'message'=> "微信打款不支持农村信用联社卡！");
                    echo CJSON::encode($result);
                    exit();
                }
                    $out_trade_no = "33" . LewaimaiString::GetUniqueTradeNo(20);
                    $dakuanorderitemModel = new DakuanOrderItem();
                    $dakuanorderitemModel->order_id = $dakuanorderModel->id;
                    $dakuanorderitemModel->admin_id = $admin_id;
                    $dakuanorderitemModel->shop_id = $rs['shop_id'];
                    $dakuanorderitemModel->shopname = $row1['shopname'];
                    $dakuanorderitemModel->headbankname = $rs['headbankname'];
                    $dakuanorderitemModel->bankusername = $rs['bankusername'];
                    $dakuanorderitemModel->bankcard_no = $rs['bankcard_no'];
                    $dakuanorderitemModel->bankname = $rs['bankname'];
                    $dakuanorderitemModel->money = $shop_dakuan[$rs['shop_id']];
                    $dakuanorderitemModel->init_date = $init_date;
                    $dakuanorderitemModel->status = 0;
                    $dakuanorderitemModel->employee_id = $employee_id;
                    $dakuanorderitemModel->out_trade_no = $out_trade_no;
                    $dakuanorderitemModel->bank_type = $rs['bank_type'];
                    $dakuanorderitemModel->dakuan_mchid = $mchid;
                    $dakuanorderitemModel->pingtai_type = $pingtai_type;
                }elseif($pingtai_type == 16){
                    if(empty($rs['openid']) || empty($rs['wechat_name'])){
                        $transaction->rollback();
                        $result = array('status' =>"error" ,'message'=> "参数错误，打款失败！");
                        echo CJSON::encode($result);
                        exit();
                    }
                    $out_trade_no = "44" . LewaimaiString::GetUniqueTradeNo(20);
                    $dakuanorderitemModel = new DakuanOrderItem();
                    $dakuanorderitemModel->order_id = $dakuanorderModel->id;
                    $dakuanorderitemModel->admin_id = $admin_id;
                    $dakuanorderitemModel->shop_id = $rs['shop_id'];
                    $dakuanorderitemModel->shopname = $row1['shopname'];
                    $dakuanorderitemModel->headbankname = "无";
                    //打款到零钱时，这个字段表示用户微信账号真实姓名
                    $dakuanorderitemModel->bankusername = $rs['wechat_name'];
                    $dakuanorderitemModel->bankcard_no = "无";
                    $dakuanorderitemModel->bankname = "无";
                    $dakuanorderitemModel->money = $shop_dakuan[$rs['shop_id']];
                    $dakuanorderitemModel->init_date = $init_date;
                    $dakuanorderitemModel->status = 0;
                    $dakuanorderitemModel->employee_id = $employee_id;
                    $dakuanorderitemModel->out_trade_no = $out_trade_no;
                    $dakuanorderitemModel->bank_type = $rs['bank_type'];
                    $dakuanorderitemModel->dakuan_mchid = $mchid;
                    $dakuanorderitemModel->pingtai_type = $pingtai_type;
                    $dakuanorderitemModel->openid = $rs['openid'];
                }
                if(!$dakuanorderitemModel->save()){
                    $transaction->rollback();

                    LewaimaiDebug::LogModelError($dakuanorderitemModel);
                    $transaction->rollback();
                    $result = array('status' =>"error" ,'message'=> "服务器错误，打款失败！");
                    echo CJSON::encode($result);
                    exit();
                }
                \lwmf\base\Logger::info($out_trade_no);
                //添加提现订单提交记录
                $info = array();
                $info['admin_id'] = $admin_id;
                $info['shop_id'] = $dakuanorderitemModel->shop_id;
                $info['value'] = $shop_dakuan[$rs['shop_id']];
                $info['init_date'] = $init_date;
                $info['headbankname'] = $dakuanorderitemModel->headbankname;
                $info['bankname'] = $dakuanorderitemModel->bankname;
                $info['bankname_no'] = $dakuanorderitemModel->bankname_no;
                $info['bankusername'] = $dakuanorderitemModel->bankusername;
                $info['bankcard_no'] = $dakuanorderitemModel->bankcard_no;
                $info['admin_describe'] = "一键给商家打款";
                //打款平台：0官方渠道10:乐刷12:天下支付13:顺丰支付14：民生银行15：微信官方打款到银行卡16微信官方打款到零钱
                $info['pingtai_type'] = $pingtai_type;
                $info['order_id'] = $dakuanorderitemModel->id;
                $info['out_trade_no'] = $out_trade_no;
                $info['dakuan_type'] = 2;
                $info['dakuan_mchid'] = $mchid;
                $info['openid'] = $dakuanorderitemModel->openid;
                $dakuan_res = \lwmf\services\ServiceFactory::getService(\lwm\services\SrvType::TRADE_ORDER_WITHDRAW_HISTORY)->add($admin_id,$info);

                if (!$dakuan_res)
                {
                    $transaction->rollback();
                    $result = array('status' =>"error" ,'message'=> "服务器错误3！");
                    echo CJSON::encode($result);
                    exit();
                }
                //这里其实可以考虑把$dakuanorderitemModel直接放入$dakuan_order_id里面，这样就不用再后面查数据了，但是考虑如果数据较多有可能把数组撑爆，还是直接放id
                $dakuan_arr['order_id'] = $dakuanorderitemModel->id;
                $dakuan_arr['out_trade_no'] = $out_trade_no;
                $dakuan_arr['value'] = $shop_dakuan[$info['shop_id']];
                $dakuan_arr['headbankname'] = $info['headbankname'];
                $dakuan_arr['bankusername'] = $info['bankusername'];
                $dakuan_arr['bankname_no'] = $info['bankname_no'];
                $dakuan_arr['bankcard_no'] = $info['bankcard_no'];
                $dakuan_arr['bank_type'] = $rs['bank_type'];
                $dakuan_arr['openId'] = $info['openid'];
                $dakuan_arr['des'] = "一键给商家打款";
                array_push($dakuan_order_arr, $dakuan_arr);
            }

            //这里就要把事务提交，然后再调用乐刷的接口，不然的话有可能调用接口已经成功，款已经打给顾客了，但是接口超时没有返回，如果这种情况下回滚，就会导致客户白白收到钱，所以这里的记录一定要先存起来才安全，千万不能再提交接口后还回滚
            $transaction->commit();

        } catch (Exception $e) {
            LewaimaiDebug::LogArray($e);
            $transaction->rollback();
            $result = array('status' =>"error" ,'message'=> "服务器错误111！");
            echo CJSON::encode($result);
            exit();
        }
        if(count($dakuan_order_arr) > 0){

            LewaimaiDebug::LogArray($dakuan_order_arr);
            if(count($dakuan_order_arr) > 100){
                //大于100，需要分页，不然会造成消息队列过大，程序崩溃
                $i = ceil(count($dakuan_order_arr)/100);
                LewaimaiDebug::LogArray($i);
                for($j=0;$j<$i;$j++){
                    $arr = array();
                    foreach ($dakuan_order_arr as $key=>$val){
                        if($key >= $j*100 && $key < ($j+1)*100){
                            array_push($arr, $dakuan_order_arr[$key]);
                        }
                    }
                    if($arr){
                        if($pingtai_type == 15){
                            \lwmf\services\ServiceFactory::getService(\lwm\services\SrvType::COMMON_MERCHANT_SETTINGS_WXDKACCOUNT)->setdakuan($admin_id,$arr);
                        }elseif ($pingtai_type == 16){
                            \lwmf\services\ServiceFactory::getService(\lwm\services\SrvType::COMMON_MERCHANT_SETTINGS_WXDKACCOUNT)->setWechatMoneydakuan($admin_id,$arr);
                        }
//                        \lwmf\services\ServiceFactory::getService(\lwm\services\SrvType::COMMON_MERCHANT_SETTINGS_WXDKACCOUNT)->setdakuan($admin_id,$arr);
                        unset($arr);
                    }
                }
            }else{
//                \lwmf\services\ServiceFactory::getService(\lwm\services\SrvType::COMMON_MERCHANT_SETTINGS_WXDKACCOUNT)->setdakuan($admin_id,$dakuan_order_arr);
                if($pingtai_type == 15){
                    \lwmf\services\ServiceFactory::getService(\lwm\services\SrvType::COMMON_MERCHANT_SETTINGS_WXDKACCOUNT)->setdakuan($admin_id,$dakuan_order_arr);
                }elseif ($pingtai_type == 16){\lwmf\services\ServiceFactory::getService(\lwm\services\SrvType::COMMON_MERCHANT_SETTINGS_WXDKACCOUNT)->setWechatMoneydakuan($admin_id,$dakuan_order_arr);
                }

            }
        }
        LewaimaiDebug::LogArray(date("Y-m-d H:i:s"));
        $result = array('status' =>"success" ,'message'=> "打款请求提交成功！");
        echo CJSON::encode($result);
        exit();
    }

    //打款历史订单
    public function actionDakuanorder(){
        $admin_id = Yii::app()->user->_id;
        $model = new DakuanOrder();
        $model->unsetAttributes();
        $model->admin_id = $admin_id;
        $this->render('dakuanorder',array(
            'model' => $model
        ));

    }

    //打款历史订单详情
    public function actionDakuanitem($id,$pingtai_type=0){
        $admin_id = Yii::app()->user->_id;
        $model = new DakuanOrderItem();
        $model->unsetAttributes();
        $model->order_id = $id;
        $model->admin_id = $admin_id;
        if($pingtai_type){
            $model->pingtai_type = $pingtai_type;
        }
        $this->render('dakuanitem',array(
            'model' => $model,
            'pingtai_type'=>$pingtai_type,
        ));
    }

    //重新打款
    public function actionRestardakuan(){
        $admin_id = Yii::app()->user->_id;
        LewaimaiDebug::LogArray("调试重新给商家打款 ".$admin_id);
//        if($admin_id != 19){
//            $result = array('status' =>"error" ,'message'=> "暂时无法打款！");
//            echo CJSON::encode($result);
//            exit();
//
//        }
        $result = array();
        if(!isset($_POST['item_id']) || !isset($_POST['item_id'])){
              $result = array('status' =>"error" ,'message'=> "参数错误！");
              echo CJSON::encode($result);
              exit();
         }
        $item_id = $_POST['item_id'];
        //$type = 15 表示微信官方，0表示天下支付和乐刷
        $type = $_POST['pingtai_type'];
        $dakuanitemSrv = \lwmf\services\ServiceFactory::getService(\lwm\services\SrvType::TRADE_ORDER_DAKUAN_ITEM);
        $dakuanitem = $dakuanitemSrv->getInfoById($admin_id, $item_id,"", true);

        if(!$dakuanitem){
            $result = array('status' =>"error" ,'message'=> "该记录不存在，无法重新打款！");
            echo CJSON::encode($result);
            exit();
        }
        if($dakuanitem['pingtai_type'] == 12){
//            $result = array('status' =>"error" ,'message'=> "该功能暂时无法使用！");
//            echo CJSON::encode($result);
//            exit();
            $transaction = \lwmf\datalevels\RdbTransaction::getInstance();
            $transaction->begin();

            try {
                //天下支付
                if ($dakuanitem['status'] != 2) {
                    $transaction->rollback();
                    $result = array('status' =>"error" ,'message'=> "状态错误，无法重新打款！");
                    echo CJSON::encode($result);
                    exit();
                }
                //这里需要重新生成订单和状态
                //提现订单号，D开头表示乐外卖账号平台给商家打款
                $out_trade_no = "D" . LewaimaiString::GetUniqueTradeNo(20);
                $init_date = date("Y-m-d H:i:s");
                $redakuan_info = array();
                $redakuan_info['status'] = 0;
                $redakuan_info['out_trade_no'] = $out_trade_no;
                $redakuan_info['init_date'] = $init_date;
                $redakuan_info['memo'] = "";
                $ret = $dakuanitemSrv->updateById($admin_id, $item_id, $redakuan_info);
                if (!$ret) {
                    $transaction->rollback();
                    $result = array('status' =>"error" ,'message'=> "服务器错误，重新打款失败！");
                    echo CJSON::encode($result);
                    exit();
                }

                //添加提现订单提交记录
                $info = array();
                $info['admin_id'] = $admin_id;
                $info['shop_id'] = $dakuanitem['shop_id'];
                $info['value'] = $dakuanitem['money'];
                $info['init_date'] = $init_date;
                $info['headbankname'] = $dakuanitem['headbankname'];
                $info['bankname'] = $dakuanitem['bankname'];
                $info['bankname_no'] = $dakuanitem['bankname_no'];
                $info['bankusername'] = $dakuanitem['bankusername'];
                $info['bankcard_no'] = $dakuanitem['bankcard_no'];
                $info['admin_describe'] = "重新给商家打款";
                //打款平台：0官方渠道10:乐刷12:天下支付13:顺丰支付14：民生银行
                $info['pingtai_type'] = 12;
                $info['order_id'] = $dakuanitem['id'];
                $info['out_trade_no'] = $out_trade_no;
                $info['dakuan_type'] = 2;
                $dakuan_res = \lwmf\services\ServiceFactory::getService(\lwm\services\SrvType::TRADE_ORDER_WITHDRAW_HISTORY)->add($admin_id, $info);

                if (!$dakuan_res) {
                    LewaimaiDebug::LogArray("新增打款记录失败");
                    $transaction->rollback();
                    $transaction->rollback();
                    $result = array('status' =>"error" ,'message'=> "服务器错误，重新打款失败！");
                    echo CJSON::encode($result);
                    exit();
                }


                $transaction->commit();
            } catch (Exception $e) {
                $transaction->rollback();
                $result = array('status' =>"error" ,'message'=> "服务器错误，请重新操作！");
                echo CJSON::encode($result);
                exit();
            }
            //这里再重新提交一次打款
            $config = \lwmf\services\ServiceFactory::getService(\lwm\services\SrvType::TRADE_PAY_CONFIG)->getTianxiaConfig($admin_id,\lwm\commons\pay\BisTypeDef::SHANGHU_TIANXIA_AGENT,0);
            LewaimaiDebug::LogArray($config);
            $payChannel = \lwm\commons\pay\PayFactory::getInstance()->getAgentChannel($config);
            $timestamp = time();
                //调用开始代付的接口
                $params = new \lwm\commons\pay\channel\AgentSinglePayParam();
                $params->outTradeNo = $info['out_trade_no'];
                $params->amount = $info['value'];
                $params->transTime = $timestamp;
                $params->payType = \lwm\commons\pay\channel\AgentSinglePayParam::PAY_TYPE_BALANCE;
                if($info['bank_type'] == 0){
                    //私人账户
                    $params->bankAccountType = \lwm\commons\pay\channel\AgentSinglePayParam::BANK_ACCOUNT_TYPE_DEBIT_CARD;

                }elseif($info['bank_type'] == 1){
                    //对公账户
                    $params->bankAccountType = \lwm\commons\pay\channel\AgentSinglePayParam::BANK_ACCOUNT_TYPE_PUBLIC;
                    $params->bankSettleNo = $info['bankname_no'];
                }
                $params->bankUserName =  $info['bankusername'];
                $params->bankNo = $info['bankcard_no'];
                $params->memo = "重新打款";
                $res = $payChannel->singlePay($params);
                if($res && isset($res['status'])){
                    if($res['status'] == \lwm\commons\pay\channel\IAgentChannel::PAY_STATUS_SUCCESS)
                    {
                        //这里表示已经打款成功，把订单状态设置为成功状态
                        $info = array();
                        $info['status'] = 1;
                        $info['complete_date'] = date("Y-m-d H:i:s");
                        \lwmf\services\ServiceFactory::getService(\lwm\services\SrvType::TRADE_ORDER_DAKUAN_ITEM)->updateById($admin_id, $item_id, $info);
                    }elseif ($res['status'] == \lwm\commons\pay\channel\IAgentChannel::PAY_STATUS_FAIL){
                        //这里表示打款失败，把订单设置成失败状态
                        //返回失败的都当做处理中，以查询接口返回的状态为准
//                        $info = array();
//                        $info['status'] = 2;
//                        $info['complete_date'] = date("Y-m-d H:i:s");
//                        $info['memo'] = $res['errmsg'];
//                        \lwmf\services\ServiceFactory::getService(\lwm\services\SrvType::TRADE_ORDER_DAKUAN_ITEM)->updateById($admin_id, $item_id, $info);
                    }

                }
                LewaimaiDebug::LogArray("是否发送请求11111");
                LewaimaiDebug::LogArray($res);

            $result = array('status' =>"success" ,'message'=> "重新打款成功！");
            echo CJSON::encode($result);
            exit();

        }elseif ($dakuanitem['pingtai_type'] == 10){
            //乐刷

            $paramArray = array();

            $paramArray["item_id"] = $item_id;

            $retArray = LewaimaiRequestApi::Send("/withdraw/redakuan", $paramArray);
            LewaimaiDebug::LogArray($retArray);
            if (!$retArray)
            {
                $result = array('status' =>"error" ,'message'=> "服务器错误，请重新操作！");
                echo CJSON::encode($result);
                exit();
            }
            if ($retArray["errcode"] == 0)
            {
                $result = array('status' =>"success" ,'message'=> "打款成功！");
                echo CJSON::encode($result);
                exit();
            }
            else
            {
                $result = array('status' =>"error" ,'message'=> $retArray["errmsg"]);
                echo CJSON::encode($result);
                exit();
            }
        }elseif($dakuanitem['pingtai_type'] == 15){
            $dakuan_order_arr = array();
                $transaction = \lwmf\datalevels\RdbTransaction::getInstance();
                $transaction->begin();

                try {
                    //微信官方打款
                    if ($dakuanitem['status'] != 2) {
                        $transaction->rollback();
                        $result = array('status' =>"error" ,'message'=> "状态错误，无法重新打款！");
                        echo CJSON::encode($result);
                        exit();
                    }
                    //这里需要重新生成订单和状态
                    //提现订单号，D开头表示乐外卖账号平台给商家打款
                    $out_trade_no = "D" . LewaimaiString::GetUniqueTradeNo(20);
                    //微信重新打款直接用原订单号发起，不用重新生成，当状态为FAIL时，存在业务结果未明确的情况，所以如果状态y为FAIL，请务必通过查询接口确认此次付款的结果（关注错误码err_code字段）。如果要继续进行这笔付款，请务必用原商户订单号和原参数来重入此接口。
//                    $out_trade_no = $dakuanitem['out_trade_no'];
                    $init_date = date("Y-m-d H:i:s");
                    $redakuan_info = array();
                    $redakuan_info['status'] = 0;
                    $redakuan_info['out_trade_no'] = $out_trade_no;
                    $redakuan_info['init_date'] = $init_date;
                    $redakuan_info['memo'] = "";
                    $ret = $dakuanitemSrv->updateById($admin_id, $item_id, $redakuan_info);
                    if (!$ret) {
                        $transaction->rollback();
                        $result = array('status' =>"error" ,'message'=> "服务器错误，重新打款失败！");
                        echo CJSON::encode($result);
                        exit();
                    }

                    //添加提现订单提交记录
                    $info = array();
                    $info['admin_id'] = $admin_id;
                    $info['shop_id'] = $dakuanitem['shop_id'];
                    $info['value'] = $dakuanitem['money'];
                    $info['init_date'] = $init_date;
                    $info['headbankname'] = $dakuanitem['headbankname'];
                    $info['bankname'] = $dakuanitem['bankname'];
                    $info['bankname_no'] = $dakuanitem['bankname_no'];
                    $info['bankusername'] = $dakuanitem['bankusername'];
                    $info['bankcard_no'] = $dakuanitem['bankcard_no'];
                    $info['admin_describe'] = "重新给商家打款";
                    //打款平台：0官方渠道10:乐刷12:天下支付13:顺丰支付14：民生银行15微信官方
                    $info['pingtai_type'] = 15;
                    $info['order_id'] = $dakuanitem['id'];
                    $info['out_trade_no'] = $out_trade_no;
                    $info['dakuan_type'] = 2;
                    $info['dakuan_mchid'] = $dakuanitem['dakuan_mchid'];
                    $dakuan_res = \lwmf\services\ServiceFactory::getService(\lwm\services\SrvType::TRADE_ORDER_WITHDRAW_HISTORY)->add($admin_id, $info);

                    if (!$dakuan_res) {
                        LewaimaiDebug::LogArray("新增打款记录失败");
                        $transaction->rollback();
                        $transaction->rollback();
                        $result = array('status' =>"error" ,'message'=> "服务器错误，重新打款失败！");
                        echo CJSON::encode($result);
                        exit();
                    }


                    //这里其实可以考虑把$dakuanorderitemModel直接放入$dakuan_order_id里面，这样就不用再后面查数据了，但是考虑如果数据较多有可能把数组撑爆，还是直接放id
                    $dakuan_arr['order_id'] = $dakuanitem['id'];
                    $dakuan_arr['out_trade_no'] = $out_trade_no;
                    $dakuan_arr['value'] = $dakuanitem['money'];
                    $dakuan_arr['headbankname'] = $dakuanitem['headbankname'];
                    $dakuan_arr['bankusername'] = $dakuanitem['bankusername'];
                    $dakuan_arr['bankname_no'] = $dakuanitem['bankname_no'];
                    $dakuan_arr['bankcard_no'] = $dakuanitem['bankcard_no'];
                    $dakuan_arr['des'] = "重新给商家打款";
                    array_push($dakuan_order_arr, $dakuan_arr);


                    $transaction->commit();
                } catch (Exception $e) {
                    $transaction->rollback();
                    $result = array('status' =>"error" ,'message'=> "服务器错误，请重新操作！");
                    echo CJSON::encode($result);
                    exit();
                }
                //这里再重新提交一次打款
                if(count($dakuan_order_arr) > 0){

                    LewaimaiDebug::LogArray($dakuan_order_arr);
    //            \lwmf\base\MessageServer::getInstance()->dispatch(\config\constants\WorkerTypes::MERCHANT_SETTING_WECHAT, array($admin_id,$dakuan_order_arr));
                    \lwmf\services\ServiceFactory::getService(\lwm\services\SrvType::COMMON_MERCHANT_SETTINGS_WXDKACCOUNT)->setdakuan($admin_id,$dakuan_order_arr);
                }

                $result = array('status' =>"success" ,'message'=> "重新打款请求成功！");
                echo CJSON::encode($result);
                exit();

            }elseif($dakuanitem['pingtai_type'] == 16){
            $dakuan_order_arr = array();
            $transaction = \lwmf\datalevels\RdbTransaction::getInstance();
            $transaction->begin();

            try {
                //微信官方打款
                if ($dakuanitem['status'] != 2) {
                    $transaction->rollback();
                    $result = array('status' =>"error" ,'message'=> "状态错误，无法重新打款！");
                    echo CJSON::encode($result);
                    exit();
                }
                //这里需要重新生成订单和状态
                //提现订单号，D开头表示乐外卖账号平台给商家打款
                $out_trade_no = "D" . LewaimaiString::GetUniqueTradeNo(20);
                //微信重新打款直接用原订单号发起，不用重新生成，当状态为FAIL时，存在业务结果未明确的情况，所以如果状态y为FAIL，请务必通过查询接口确认此次付款的结果（关注错误码err_code字段）。如果要继续进行这笔付款，请务必用原商户订单号和原参数来重入此接口。
//                    $out_trade_no = $dakuanitem['out_trade_no'];
                $init_date = date("Y-m-d H:i:s");
                $redakuan_info = array();
                $redakuan_info['status'] = 0;
                $redakuan_info['out_trade_no'] = $out_trade_no;
                $redakuan_info['init_date'] = $init_date;
                $redakuan_info['memo'] = "";
                $ret = $dakuanitemSrv->updateById($admin_id, $item_id, $redakuan_info);
                if (!$ret) {
                    $transaction->rollback();
                    $result = array('status' =>"error" ,'message'=> "服务器错误，重新打款失败！");
                    echo CJSON::encode($result);
                    exit();
                }

                //添加提现订单提交记录
                $info = array();
                $info['admin_id'] = $admin_id;
                $info['shop_id'] = $dakuanitem['shop_id'];
                $info['value'] = $dakuanitem['money'];
                $info['init_date'] = $init_date;
                $info['headbankname'] = $dakuanitem['headbankname'];
                $info['bankname'] = $dakuanitem['bankname'];
                $info['bankname_no'] = $dakuanitem['bankname_no'];
                $info['bankusername'] = $dakuanitem['bankusername'];
                $info['bankcard_no'] = $dakuanitem['bankcard_no'];
                $info['admin_describe'] = "重新给商家打款";
                $info['openid'] = $dakuanitem['openid'];
                //打款平台：0官方渠道10:乐刷12:天下支付13:顺丰支付14：民生银行15微信官方
                $info['pingtai_type'] = 15;
                $info['order_id'] = $dakuanitem['id'];
                $info['out_trade_no'] = $out_trade_no;
                $info['dakuan_type'] = 2;
                $info['dakuan_mchid'] = $dakuanitem['dakuan_mchid'];
                $dakuan_res = \lwmf\services\ServiceFactory::getService(\lwm\services\SrvType::TRADE_ORDER_WITHDRAW_HISTORY)->add($admin_id, $info);

                if (!$dakuan_res) {
                    LewaimaiDebug::LogArray("新增打款记录失败");
                    $transaction->rollback();
                    $transaction->rollback();
                    $result = array('status' =>"error" ,'message'=> "服务器错误，重新打款失败！");
                    echo CJSON::encode($result);
                    exit();
                }


                //这里其实可以考虑把$dakuanorderitemModel直接放入$dakuan_order_id里面，这样就不用再后面查数据了，但是考虑如果数据较多有可能把数组撑爆，还是直接放id
                $dakuan_arr['order_id'] = $dakuanitem['id'];
                $dakuan_arr['out_trade_no'] = $out_trade_no;
                $dakuan_arr['value'] = $dakuanitem['money'];
                $dakuan_arr['headbankname'] = $dakuanitem['headbankname'];
                $dakuan_arr['bankusername'] = $dakuanitem['bankusername'];
                $dakuan_arr['bankname_no'] = $dakuanitem['bankname_no'];
                $dakuan_arr['bankcard_no'] = $dakuanitem['bankcard_no'];
                $dakuan_arr['des'] = "重新给商家打款";
                $dakuan_arr['openId'] = $dakuanitem['openid'];
                array_push($dakuan_order_arr, $dakuan_arr);


                $transaction->commit();
            } catch (Exception $e) {
                $transaction->rollback();
                $result = array('status' =>"error" ,'message'=> "服务器错误，请重新操作！");
                echo CJSON::encode($result);
                exit();
            }
            //这里再重新提交一次打款
            if(count($dakuan_order_arr) > 0){

                LewaimaiDebug::LogArray($dakuan_order_arr);
                    \lwmf\services\ServiceFactory::getService(\lwm\services\SrvType::COMMON_MERCHANT_SETTINGS_WXDKACCOUNT)->setWechatMoneydakuan($admin_id,$dakuan_order_arr);
            }

            $result = array('status' =>"success" ,'message'=> "重新打款请求成功！");
            echo CJSON::encode($result);
            exit();
        }
    }

    public function loadModel($id)
    {
        $model=FenchengSetting::model()->findByPk($id);
        if($model->admin_id != yii::app() -> user -> _id){
            throw new CHttpException(404,'你无权操作');
        }
        if($model===null)
            throw new CHttpException(404,'The requested page does not exist.');
        return $model;
    }

    public function fenchengHandle($data){
        $id = $data->id;
        $str = '<a href='.Yii::app()->createUrl('fenchengsetting/update', array('id'=>$data->id)).' title="修改" class="green">';
        $str .= '<i class="ace-icon fa fa-pencil bigger-130"></i>';
        $str .= '</a>';
        $str .= '<a href='.Yii::app()->createUrl('fenchengsetting/shopbank', array('shop_id'=>$data->shop_id)).' title="修改店铺银行卡信息" style="padding-right:8px;margin-left:5px;">';
        $str .= '<i class="ace-icon fa fa-credit-card bigger-130"></i>';
        $str .= '</a>';
        echo $str;
    }

    public function getshopname($data){
        $sql = "SELECT id,shopname FROM {{config}} WHERE id = " . $data->shop_id;
        $res = Yii::app()->db->createCommand($sql)->queryRow();
        if($res){
            echo $res['shopname'];
        }else{
            echo "店铺不存在或已删除";
        }
    }

    private function getmyshopname($shop_id){
        $sql = "SELECT id,shopname FROM {{config}} WHERE id = " . $shop_id;
        $res = Yii::app()->db->createCommand($sql)->queryRow();
        if($res){
            return $res['shopname'];
        }else{
            return "店铺不存在或已删除";
        }
    }

    public function getdelivery($data){
        $str = "商家：".$data->delivery_sj."%<br/>";
        $str .= "平台：".$data->delivery_pt."%";
        echo $str;
    }
    public function getdabao($data){
        $str = "商家：".$data->dabao_sj."%<br/>";
        $str .= "平台：".$data->dabao_pt."%";
        echo $str;
    }
    public function getaddservice($data){
        $str = "商家：".$data->addservice_sj."%<br/>";
        $str .= "平台：".$data->addservice_pt."%";
        echo $str;
    }
    public function getorderfield($data){
        $str = "商家：".$data->order_field_fee_sj."%<br/>";
        $str .= "平台：".$data->order_field_fee_pt."%";
        echo $str;
    }
    public function getfoodprice($data){
        $str = "商家：".$data->foodprice_sj."%<br/>";
        $str .= "平台：".$data->foodprice_pt."%";
        echo $str;
    }
    public function getdiscount($data){
        $str = "商家：".$data->discount_sj."%<br/>";
        $str .= "平台：".$data->discount_pt."%";
        echo $str;
    }
    public function getpromotion($data){
        $str = "商家：".$data->promotion_sj."%<br/>";
        $str .= "平台：".$data->promotion_pt."%";
        echo $str;
    }
    public function getmember($data){
        $str = "商家：".$data->member_sj."%<br/>";
        $str .= "平台：".$data->member_pt."%";
        echo $str;
    }
    public function getcoupon($data){
        $str = "商家：".$data->coupon_sj."%<br/>";
        $str .= "平台：".$data->coupon_pt."%";
        echo $str;
    }
    public function getfirstdiscount($data){
        $str = "商家：".$data->firstdiscount_sj."%<br/>";
        $str .= "平台：".$data->firstdiscount_pt."%";
        echo $str;
    }
    public function isoffline($data){
        if($data->is_deduct_offline){
            echo "<span style='color:#6CBC4E'>是</span>";
        }else{
            echo "<span style='color:#DA4F4A'>否</span>";
        }
    }
    public function istodakuan($data){
        if($data->is_todakuan){
            echo "<span style='color:#6CBC4E'>是</span>";
        }else{
            echo "<span style='color:#DA4F4A'>否</span>";
        }
    }
    public function isblindcard($data){
        if($data->is_blindcard){
            echo "<span style='color:#6CBC4E'>是</span>";
        }else{
            echo "<span style='color:#DA4F4A'>否</span>";
        }
    }
    public function getshopnames($data){
        $sql = "SELECT id,shopname FROM {{config}} WHERE id = " . $data->shop_id;
        $res = Yii::app()->db->createCommand($sql)->queryRow();
        if($res){
            echo $res['shopname'];
        }else{
            echo "店铺不存在或已删除";
        }
    }
    public function getfencheng($data){
        echo '<input type="text" class="fenchengmoney" id="shopid_'.$data->shop_id.'" value="0" />';
    }
    public function is_blindcard($data){
        if($data->is_blindcard){
            echo "<span style='color:#6CBC4E'>是</span><input type='hidden' value='".$data->is_blindcard."' id='isblindcard".$data->shop_id."' />";
        }else{
            echo "<span style='color:#DA4F4A'>否</span><input type='hidden' value='".$data->is_blindcard."' id='isblindcard".$data->shop_id."' />";
        }
    }
    public function getheadbankname($data){
        $admin_id = Yii::app()->user->_id;
        $sql = "SELECT id,headbankname FROM {{shop_account}} WHERE admin_id = ".$admin_id." AND shop_id = " . $data->shop_id;
        $res = Yii::app()->db->createCommand($sql)->queryRow();
        if($res){
            echo $res['headbankname'];
        }else{
            echo "";
        }
    }

    public function getbankusername($data){
        $admin_id = Yii::app()->user->_id;
        $sql = "SELECT id,bankusername FROM {{shop_account}} WHERE admin_id = ".$admin_id." AND shop_id = " . $data->shop_id;
        $res = Yii::app()->db->createCommand($sql)->queryRow();
        if($res){
            echo $res['bankusername']."";
        }else{
            echo "";
        }
    }

    public function getwechatname($data){
        $admin_id = Yii::app()->user->_id;
        $sql = "SELECT id,wechat_name FROM {{shop_account}} WHERE admin_id = ".$admin_id." AND shop_id = " . $data->shop_id;
        $res = Yii::app()->db->createCommand($sql)->queryRow();
        if($res){
            echo $res['wechat_name']."<input type='hidden' value='".$res['wechat_name']."' id='wechatname".$data->shop_id."' />";
        }else{
            echo "<input type='hidden' value='".$res['wechat_name']."' id='wechatname".$data->shop_id."' />";
        }
    }

    public function getbankcard_no($data){
        $admin_id = Yii::app()->user->_id;
        $sql = "SELECT id,bankcard_no FROM {{shop_account}} WHERE admin_id = ".$admin_id." AND shop_id = " . $data->shop_id;
        $res = Yii::app()->db->createCommand($sql)->queryRow();
        if($res){
            echo $res['bankcard_no'];
        }else{
            echo "";
        }
    }

    //获取打款的相关数据
    public function actionGetdakuaninfo(){
        $admin_id = Yii::app()->user->_id;
        LewaimaiDebug::LogArray("调试啊啊啊啊");
        LewaimaiDebug::LogArray($_POST);
        if(!isset($_POST['dakuan_id']) || !isset($_POST['dakuan_money'])){
            $result = array('status' =>"error" ,'message'=> "无相关打款数据，请重新操作！");
            echo CJSON::encode($result);
            exit();
        }
        $shop_id = $_POST['dakuan_id'];
        $dakuan_money = $_POST['dakuan_money'];
        if(count($shop_id) <=0 || count($dakuan_money) <=0){
            $result = array('status' =>"error" ,'message'=> "无相关打款数据，请重新操作！");
            echo CJSON::encode($result);
            exit();
        }
        if(count($shop_id) != count($dakuan_money)){
            $result = array('status' =>"error" ,'message'=> "打款数据存在错误，请重新操作！");
            echo CJSON::encode($result);
            exit();
        }
        $shop_dakuan = array();
        foreach ($shop_id as $key=>$val){
            $shop_dakuan[$val] = $dakuan_money[$key];
        }
        $shop_str = implode(",", $shop_id);
        $sql = "SELECT id,shop_id,headbankname,bankusername,bankcard_no FROM {{shop_account}} WHERE admin_id = " . $admin_id." AND shop_id in (".$shop_str.")";
        $res = Yii::app()->db->createCommand($sql)->queryAll();
        if(!$res){
            $result = array('status' =>"error" ,'message'=> "无相关打款店铺数据，请重新操作！");
            echo CJSON::encode($result);
            exit();
        }
        $string = "";
        foreach ($res as $rs){
            $sql1 = "SELECT id,shopname FROM {{config}} WHERE id = " . $rs['shop_id'];
            $shopmodel = Yii::app()->db->createCommand($sql1)->queryRow();
            if(!$shopmodel){
                continue;
            }
            $string .= '<tr class="odd"><td>'.$shopmodel['shopname'].'</td><td>'.$shop_dakuan[$rs['shop_id']].'</td><td>'.$rs['headbankname'].'</td><td>'.$rs['bankusername'].'</td><td>'.$rs['bankcard_no'].'</td></tr>';
        }
        $result = array('status' =>"success" ,'message'=> $string);
        echo CJSON::encode($result);
        exit();
    }

    //获取微信打款到零钱的相关数据
    public function actionGetwechatdakuaninfo(){
        $admin_id = Yii::app()->user->_id;
        LewaimaiDebug::LogArray("调试啊啊啊啊");
        LewaimaiDebug::LogArray($_POST);
        if(!isset($_POST['dakuan_id']) || !isset($_POST['dakuan_money'])){
            $result = array('status' =>"error" ,'message'=> "无相关打款数据，请重新操作！");
            echo CJSON::encode($result);
            exit();
        }
        $shop_id = $_POST['dakuan_id'];
        $dakuan_money = $_POST['dakuan_money'];
        if(count($shop_id) <=0 || count($dakuan_money) <=0){
            $result = array('status' =>"error" ,'message'=> "无相关打款数据，请重新操作！");
            echo CJSON::encode($result);
            exit();
        }
        if(count($shop_id) != count($dakuan_money)){
            $result = array('status' =>"error" ,'message'=> "打款数据存在错误，请重新操作！");
            echo CJSON::encode($result);
            exit();
        }
        $shop_dakuan = array();
        foreach ($shop_id as $key=>$val){
            $shop_dakuan[$val] = $dakuan_money[$key];
        }
        $shop_str = implode(",", $shop_id);
        $sql = "SELECT id,shop_id,headbankname,bankusername,bankcard_no,wechat_name FROM {{shop_account}} WHERE admin_id = " . $admin_id." AND shop_id in (".$shop_str.")";
        $res = Yii::app()->db->createCommand($sql)->queryAll();
        if(!$res){
            $result = array('status' =>"error" ,'message'=> "无相关打款店铺数据，请重新操作！");
            echo CJSON::encode($result);
            exit();
        }
        $string = "";
        foreach ($res as $rs){
            $sql1 = "SELECT id,shopname FROM {{config}} WHERE id = " . $rs['shop_id']." AND is_delete=0";
            $shopmodel = Yii::app()->db->createCommand($sql1)->queryRow();
            if(!$shopmodel){
                continue;
            }
            $string .= '<tr class="odd"><td>'.$shopmodel['shopname'].'</td><td>'.$shop_dakuan[$rs['shop_id']].'</td><td>微信零钱</td><td>'.$rs['wechat_name'].'</td></tr>';
        }
        $result = array('status' =>"success" ,'message'=> $string);
        echo CJSON::encode($result);
        exit();
    }

    /**
     * 搜索打款记录
     */
    public function actionFinancenewsearchhistory()
    {
        $model = new DakuanOrderItem('searchJilu');
        $model->unsetAttributes();  // clear any default values
        $admin_id = Yii::app()->user->_id;
        $model->admin_id = $admin_id;
        $showOrder = false;

        if(isset($_REQUEST['DakuanOrderItem']))
        {
            //var_dump($_REQUEST['DakuanOrderItem']);exit();
            $showOrder = true;
            $model->attributes = $_REQUEST['DakuanOrderItem'];
            $model->pingtai_type = $_REQUEST['DakuanOrderItem']['pingtai_type'];
            // echo '<pre>';
            // var_dump($_REQUEST);

            if(isset($_REQUEST['is_export']) && $_REQUEST['is_export'] == 1)
            {
                $where = ' admin_id = '.$admin_id;

                if($model->shop_id != '-1'){
                    $where .= ' AND shop_id = '.$model->shop_id;
                }

                if(!empty($model->start_time)){
                    $model->start_time = $model->start_time.' 0:0:0';
                    $where .= ' AND init_date >= "'.$model->start_time.'" ';
                }

                if(!empty($model->end_time)){
                    $model->end_time = $model->end_time.' 23:59:59';
                    $where .= ' AND init_date <= "'.$model->end_time.'" ';
                }

                if($model->status != '-1'){

                    $where .= ' AND status = '.$model->status;
                }

                if(!empty($model->bankcard_no)){

                    $where .= ' AND bankcard_no = "'.trim($model->bankcard_no).'" ';
                }

                if(!empty($model->bankusername)){
                    $where .= ' AND bankusername like "%'. trim($model->bankusername) .'%" ';
                }

                if(!empty($model->headbankname)){
                    $where .= ' AND headbankname like "%'. trim($model->headbankname) .'%" ';
                }

                if(!empty($model->pingtai_type)) {
                    $where .= ' AND pingtai_type = '.$model->pingtai_type;
                }

                $sql_num = "SELECT COUNT(id) num FROM {{dakuan_order_item}} WHERE ". $where;

                $sql = "SELECT `id`, `shop_id`, `init_date`, `money`, `headbankname`, `bankusername`, `bankcard_no`, `employee_id`, `status`, `memo` FROM {{dakuan_order_item}} WHERE ". $where . " LIMIT 0,10000";

                $connection = Yii::app()->db;
                $numArr = $connection->createCommand($sql_num)->queryAll();
                if($numArr[0]['num'] < 1){
                    $res['status'] = 'error';
                    $res['message'] = '暂无数据，导出失败';
                    echo json_encode($res);exit();
                }

                if($numArr[0]['num'] > 10000){

                    $res['status'] = 'error';
                    $res['message'] = '每次导出的记录不能大于10000条！！！';
                    echo json_encode($res);exit;
                }

                $data = $connection->createCommand($sql)->queryAll();


                //开始导出
                ob_end_clean();
                ob_start();
                /* PHPExcel */
                require_once 'PHPExcel.php';
                $objPHPExcel = new PHPExcel();
                $objPHPExcel->getProperties()->setCreator("lewaimai")
                                             ->setLastModifiedBy("lewaimai")
                                             ->setTitle("乐外卖打款历史记录")
                                             ->setSubject("乐外卖打款历史记录")
                                             ->setDescription("乐外卖打款历史记录")
                                             ->setKeywords("乐外卖")
                                             ->setCategory("乐外卖");
                    // 创建一个新的工作表
                    $objPHPExcel->createSheet();
                    $objPHPExcel->setActiveSheetIndex(0);
                    $objActSheet = $objPHPExcel->getActiveSheet();
                    $objActSheet->setTitle('乐外卖打款历史记录');

                    $objActSheet->getDefaultStyle()->getAlignment()->setWrapText(true);
                    $objActSheet->getDefaultStyle()->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_TOP);
                    $objActSheet->getDefaultStyle()->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_LEFT);
                    //设置各列的标题
                    $objActSheet->setCellValue('A1', '操作时间')
                                ->setCellValue('B1', '店铺名称')
                                ->setCellValue('C1', '金额')
                                ->setCellValue('D1', '打款账号')
                                ->setCellValue('E1', '操作者')
                                ->setCellValue('F1', '状态');
                    $objActSheet->getStyle('A1:F1')->getFont()->setName('宋体');
                    $objActSheet->getStyle('A1:F1')->getFont()->setSize(12);
                    $objActSheet->getStyle('A1:F1')->getFont()->setBold(true);
                    //设置各列的宽度
                    $objActSheet->getColumnDimension('A')->setWidth(20);
                    $objActSheet->getColumnDimension('B')->setWidth(30);
                    $objActSheet->getColumnDimension('C')->setWidth(20);
                    $objActSheet->getColumnDimension('D')->setWidth(50);
                    $objActSheet->getColumnDimension('E')->setWidth(20);
                    $objActSheet->getColumnDimension('F')->setWidth(20);
                    foreach($data as $key => $val){
                        $showNum = $key+2;

                        $objActSheet->setCellValue('A' . $showNum, $val['init_date'])
                                    ->setCellValue('B' . $showNum, $this->getmyshopname($val['shop_id']))
                                    ->setCellValue('C' . $showNum, $val['money'])
                                    ->setCellValue('D' . $showNum, htmlspecialchars($this->getmydakuanitem($val)))
                                    ->setCellValue('E' . $showNum, $this->getmyemployee($val['employee_id']))
                                    ->setCellValue('F' . $showNum, htmlspecialchars($this->getmystatus($val['status'], $val['memo'])));
                    }
                $objPHPExcel->setActiveSheetIndex(0);

                // Redirect output to a client’s web browser (Excel5)
                $filename = "乐外卖打款历史记录.xls";
                header('Content-Type: application/vnd.ms-excel');
                header('Content-Disposition: attachment;filename='.$filename);
                header('Cache-Control: max-age=0');
                // If you're serving to IE 9, then the following may be needed
                header('Cache-Control: max-age=1');

                // If you're serving to IE over SSL, then the following may be needed
                header ('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
                header ('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT'); // always modified
                header ('Cache-Control: cache, must-revalidate'); // HTTP/1.1
                header ('Pragma: public'); // HTTP/1.0

                $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
                // $objWriter->save('php://output');
                $file_path = Yii::app()->getBasePath().'/data/excel/';
                if(!file_exists($file_path)){
                    mkdir($file_path, 0777);
                }
                $file_path .= $filename;
                $objWriter->save($file_path);

                $file_arr[] = $file_path;
                $dir_name = time();
                // 打包以上生成的excel文件，返回zip包地址
                $last_dir = dirname($file_path).'/';
                $zip_name = $last_dir.$dir_name.'.zip';
                addFileToZip($zip_name,$file_arr);
                // 上传文件到cdn,返回文件地址
                if($cdn_path = LewaimaiCDN::uploadTempCvs($zip_name)){
                    echo json_encode(array('status'=>'success','file_path'=>$cdn_path));
                }else{
                    // Failed upload file to cdn!
                    echo json_encode(array('notice_msg'=>'导出失败，当前时间段没有订单数据 '));
                }
                // 删除文件夹以及文件夹下的文件,删除压缩包
                deldir($last_dir);
                @unlink($zip_name);
                exit();

                return;
            }
        }

        $shoplist = $this->availShopList();

        $this->render('financenewsearchhistory', array(
                'model' => $model,
                'showOrder' => $showOrder,
                'shoplist'  => $shoplist,
            ));

    }

    public function getemployee($data){
        if($data->employee_id == 0){
            echo "主账号";
        }else{
           $admin_id = Yii::app()->user->_id;
           $employeeModel = EmployeesAccount::model()->findByPk($data->employee_id);
           if(!$employeeModel){
               echo "员工账号不存在或已删除";
           }else{
               $employeename = "员工账号：".$employeeModel->account;
               echo $employeename;
           }
        }
    }

    public function getpingtaitype($data)
    {
        $pingtai_type = '';
        if($data->pingtai_type == 10) {
            $pingtai_type = '乐刷';
        }elseif($data->pingtai_type == 12) {
            $pingtai_type = '天下支付（天付宝）';
        }elseif($data->pingtai_type == 13) {
            $pingtai_type = '顺丰支付';
        }elseif($data->pingtai_type == 14) {
            $pingtai_type = '民生银行';
        }elseif($data->pingtai_type == 15) {
            $pingtai_type = '微信打款到银行卡';
        }elseif($data->pingtai_type == 16) {
            $pingtai_type = '微信打款到零钱';
        }
        return $pingtai_type;
    }

    private function getmyemployee($employee_id){
        if($employee_id == 0){
            return "主账号";
        }else{
           $admin_id = Yii::app()->user->_id;
           $employeeModel = EmployeesAccount::model()->findByPk($employee_id);
           if(!$employeeModel){
               return "员工账号不存在或已删除";
           }else{
               $employeename = "员工账号：".$employeeModel->account;
               return $employeename;
           }
        }
    }

    protected function gethandle($data)
    {
        $url = $data->id;
        echo CHtml::Link('<i class="ace-icon fa fa-search bigger-130"></i>', array("fenchengsetting/dakuanitem","id"=>$data->id,"pingtai_type"=>$data->pingtai_type), array('title'=>'查看详情','class'=>'green','style'=>'padding-right:1px;'));
    }

    protected function getdakuanitem($data)
    {
        if($data->headbankname == "无"){
            $data->headbankname = "";
        }
        if($data->bankcard_no == "无"){
            $data->bankcard_no = "";
        }
        echo $data->headbankname."&nbsp;&nbsp;&nbsp;".$data->bankusername."&nbsp;&nbsp;&nbsp;".$data->bankcard_no;
    }

    private function getmydakuanitem($arr)
    {
        return $arr['headbankname']."    ".$arr['bankusername']."    ".$arr['bankcard_no'];
    }

    protected function getstatus($data)
    {
        if($data->status == 0){
            echo "<span style='color:#3399CC;font-weight:bold;'>处理中</span>";
        }else if($data->status == 1){
            echo "<span style='color:#339933;font-weight:bold;'>成功</span>";
        }else if($data->status == 2){
            if($data->memo){
                echo "<span style='color:#CC3300;font-weight:bold;'>失败（".$data->memo."）</span>";
            }else{
                echo "<span style='color:#CC3300;font-weight:bold;'>失败</span>";
            }
        }
    }

    protected function getType($data)
    {
        if($data->pingtai_type == 10){
            return "乐刷";
        }else if($data->pingtai_type == 12){
            return "天下支付";
        }else if($data->pingtai_type == 15){
            return "微信代付";
        }else if($data->pingtai_type == 16){
            return "微信零钱代付";
        }else{
            return "其他";
        }
    }

    protected function getmystatus($status, $memo)
    {
        if($status == 0){
            return "处理中";
        }else if($status == 1){
            return "成功";
        }else if($status == 2){
            if($memo){
                return "失败（".$memo."）";
            }else{
                return "失败";
            }
        }
    }

    protected function getitmehandle($data)
    {
        if($data->status == 2){
            if($data->pingtai_type == 16){
                echo CHtml::Link('<a class="label label-sm label-warning" onclick="redakuan('.$data->id.')">重新打款</a>')."&nbsp;&nbsp;&nbsp;".CHtml::Link('<a class="label label-sm label-info" href="'.Yii::app()->createUrl("fenchengsetting/editwithdrawwechatname",array("id"=>$data->id)).'">修改微信账户信息</a>');
            }else{
            echo CHtml::Link('<a class="label label-sm label-warning" onclick="redakuan('.$data->id.')">重新打款</a>')."&nbsp;&nbsp;&nbsp;".CHtml::Link('<a class="label label-sm label-info" href="'.Yii::app()->createUrl("fenchengsetting/edititembank",array("id"=>$data->id)).'">修改银行卡信息</a>');
            }
        }else{
            echo "";
        }
    }

    protected function getDakuanStatus($data)
    {

        $dakuanOrderItemModel = DakuanOrderItem::model()->findAll('order_id=:order_id', array(':order_id'=>$data->id));

        if(!$dakuanOrderItemModel){
            return '';
        }

        $successNum = 0;        //打款成功
        $doingNum = 0;          //打款处理中
        $failNum = 0;           //打款失败
        $res = '';

        foreach ($dakuanOrderItemModel as $dakuanOrderItem) {

            switch ($dakuanOrderItem->status) {
                case '0':
                    $doingNum++;
                    break;

                case '1':
                    $successNum++;
                    break;

                case '2':
                    $failNum++;
                    break;

                default:

                    break;
            }
        }

        if(!empty($successNum))
        {
            $res .= '成功&nbsp;&nbsp;&nbsp;&nbsp;【'. $successNum .'】<br>';
        }

        if(!empty($doingNum))
        {
            $res .= '处理中【'. $doingNum .'】<br>';
        }

        if(!empty($failNum))
        {
            $res .= '<span style="color:red;">失败&nbsp;&nbsp;&nbsp;&nbsp;【'. $failNum .'】</span>';
        }

        return $res;
    }

    /**
     * 全部店铺列表
     */
    private function availShopList(){
        $admin_id = Yii::app()->user->_id;
        $admin = Admin::model()->findByPk($admin_id);
        $shop = new Config;
        $shop->unsetAttributes();
        $shop->admin_id = $admin_id;
        $criteria = new CDbCriteria;
        $criteria->compare('admin_id',$shop->admin_id);
        $criteria->addCondition('is_delete=0');
        $shop_ids = LewaimaiEmployee::CheckAccount();
        if($shop_ids){
            $criteria->addInCondition('id',$shop_ids);
        }

        $dataProvider = new CActiveDataProvider($shop,array('criteria'=>$criteria,'pagination'=>array('pageSize'=>10000)));
        $shoplist = CHtml::listData($dataProvider->getData(),'id','shopname');
        return $shoplist;
    }

    protected function getAreaName($data)
    {
        $areaName = '无';
        if(!empty($data->area_id)){
            $areaModel = Area::model()->findByPk($data->area_id);
            if($areaModel){
                $areaName = $areaModel->area_name;
            }
        }

        return $areaName;
    }

//设置微信打款参数
    public function actionSetwechatpay()
    {
        $adminId = Yii::app()->user->_id;
        $dakuanSrv = \lwmf\services\ServiceFactory::getService(\lwm\services\SrvType::COMMON_MERCHANT_SETTINGS_WXDKACCOUNT);
        $model = $dakuanSrv->getInfoByadminId($adminId);
        if (!$model)
        {
            $model = array();
            $model['admin_id'] = $adminId;
            $model['mchid'] = "";
            $model['key'] = "";
            $model['apiclient_cert'] = "";
            $model['apiclient_key'] = "";
            $res = $dakuanSrv->add($adminId,$model);
            $model['id'] = $res;
        }
        if (isset($_POST["WeixindakuanAccount"]))
        {
            $model['mchid'] = $_POST["WeixindakuanAccount"]['mchid'];
            $model['key'] = $_POST["WeixindakuanAccount"]['key'];
            $model['apiclient_cert'] = $_POST["WeixindakuanAccount"]['apiclient_cert'];
            $model['apiclient_key'] = $_POST["WeixindakuanAccount"]['apiclient_key'];
            $dakuanSrv->updateById($model['id'], $model);
        }

        $this->render('weixindakuan',array(
            'model'=>$model
        ));
    }

    //设置商家财务结算类型
    public function actionAccountset()
    {
        $admin_id = Yii::app()->user->_id;
        $model = AdminAccount::model()->findByPk($admin_id);
        $user_type = Yii::app()->user->getState('usertype');
        $this->render('accountset',array(
            'model'=>$model,
            'user_type'=>$user_type
        ));

    }

    /**
     * 开启商家提现的判断
     *
     */
    public function actionJudgewithdraw()
    {
        $admin_id = Yii::app()->user->_id;
        $act = $_POST['act'];

       /* if($act == 'has_open') {
            //是否开通支付账户
            $sql = 'select count(*) as count from {{tianxia_apply}} where admin_id=:admin_id and tx_status=2';
            $count = Yii::app()->db->createCommand($sql)->queryRow(true,array(':admin_id'=>$admin_id))['count'];
            echo $count;
        }elseif($act == 'has_setting') {
            //是否已设置
            $sql = 'select * from {{admin_account}} where admin_id=:admin_id';
            $data = Yii::app()->db->createCommand($sql)->queryRow(true,array(':admin_id'=>$admin_id));
            $tianxiazhifu_mchid = $data['tianxiazhifu_mchid'];
            if(!empty($tianxiazhifu_mchid)) {
                echo true;
            }else{
                echo false;
            }*/
        if($act == 'judge'){
            /*
             * 先判断开启自主提现的条件是否具备
             * **/
            //1是否已经设置过打款方式
            $adminAccount = AdminAccount::model() -> findByPk($admin_id);
            if($adminAccount -> dakuan_type == 0){
                exit(json_encode(array('status' => 1,'msg' => '未设置打款方式，暂不能使用商家提现功能')));
            }
            //2旗下的店铺是否全部都设置过提成设置
            //获取admin_id账号下的所有店铺信息(shop_id,shopname)
            //先获取admin_id下面未设置分成设置的shop_id
            $criteriaFen = new CDbCriteria;
            $criteriaFen -> select = 'id,shop_id';

            $criteriaFen -> condition = 'admin_id = :admin_id and (is_confirm = :is_confirm or is_deduct_offline = :is_deduct_offline)';
            $criteriaFen -> params = array(':admin_id' => $admin_id,':is_confirm' => 0,':is_deduct_offline' => 1);
            $fenChengSetting = FenchengSetting::model() -> findAll($criteriaFen);
            $shopIdArr = array();
            $idArr = array();
            foreach($fenChengSetting as $key => $value){
                $shopIdArr[] = $value -> shop_id;
                $idArr[$value -> shop_id] = $value -> id;
            }
            $shopNameArray = array();
            if(!empty($shopIdArr)){
                $shop_ids = implode($shopIdArr,',');

                //获取没有设置过分成设置的商铺名称
                $criteriaConfig = new CDbCriteria;
                $criteriaConfig -> select = 'id,shopname';
                $criteriaConfig -> limit = 14;
                $criteriaConfig -> offset = 0;
                $criteriaConfig -> order = 'init_date desc';
                $criteriaConfig -> condition = 'admin_id = :admin_id and is_delete = 0 and id in('.$shop_ids.')';
                $criteriaConfig -> params = array(':admin_id' => $admin_id);
                $shopConfig = Config::model() -> findAll($criteriaConfig);

                foreach($shopConfig as $key => $value){
                    $shopNameArray[] = array($value -> shopname,$value -> id);
                }
            }

            if(count($shopNameArray) == 0){
                //全部设置过提成设置
                //查询该主账号的所有店铺
                $sql = "select id from {{config}} where admin_id={$admin_id} and shopstatus='OPEN' and is_delete=0";
                $result1 = Yii::app()->db->createCommand($sql)->queryAll();
                //查询该主账号的店铺绑定账号的情况
                $sql = "select shop_id,employee_id from {{shop_account}} where admin_id={$admin_id}";
                $result2 = Yii::app()->db->createCommand($sql)->queryAll();
                foreach ($result1 as $k1 => $v1) {
                    $has_set_sign = false;
                    foreach ($result2 as $k1 => $v2) {
                        if($v1['id'] == $v2['shop_id']) {
                            if(!empty($v2['employee_id'])) {
                                $has_set_sign = true;
                            }
                            break 1;
                        }
                    }
                    if(!$has_set_sign) {
                        //exit(json_encode(array('status' => 4,'msg' => '有店铺没有绑定账户不能开启商家自主提现')));
                    }
                }
                exit(json_encode(array('status' => 2,'msg' => '可以设置')));
            }else{
                //还有商铺乜有设置提成设置
                exit(json_encode(array('status' => 3,'msg' => '还有店铺没有设置提成设置，请先进行设置','data' => $shopNameArray)));
            }
        }elseif($act == 'open_withdraw') {
            //开通自主提现
            $sql = 'update {{admin_account}} set is_open_withdraw=1 where admin_id=:admin_id';
            Yii::app()->db->createCommand($sql)->execute(array(':admin_id'=>$admin_id));
            echo true;
        }
    }

    /*
     * 分页获取
     *
     * **/
    public function actionGetNoSetFenchengShop(){
        $page = $_GET['page'];
        $admin_id = Yii::app()->user->_id;
        //2.1获取admin_id账号下的所有店铺信息(shop_id,shopname)
        $criteriaFen = new CDbCriteria;
        $criteriaFen -> select = 'id,shop_id';

        $criteriaFen -> condition = 'admin_id = :admin_id and (is_confirm = :is_confirm or is_deduct_offline = :is_deduct_offline)';
        $criteriaFen -> params = array(':admin_id' => $admin_id,':is_confirm' => 0,':is_deduct_offline' => 1);
        $fenChengSetting = FenchengSetting::model() -> findAll($criteriaFen);
        $shopIdArr = array();
        $idArr = array();
        foreach($fenChengSetting as $key => $value){
            $shopIdArr[] = $value -> shop_id;
            $idArr[$value -> shop_id] = $value -> id;
        }
        $shopNameArray = array();
        if(!empty($shopIdArr)){
            $shop_ids = implode($shopIdArr,',');
            //获取没有设置过分成设置的商铺名称
            $criteriaConfig = new CDbCriteria;
            $criteriaConfig -> select = 'id,shopname';
            $criteriaConfig -> limit = 15;
            $criteriaConfig -> offset = ($page-1)*15;
            $criteriaConfig -> order = 'init_date desc';
            $criteriaConfig -> condition = 'admin_id = :admin_id and id in('.$shop_ids.')';
            $criteriaConfig -> params = array(':admin_id' => $admin_id);
            $shopConfig = Config::model() -> findAll($criteriaConfig);

            foreach($shopConfig as $key => $value){
                $shopNameArray[] = array($value -> shopname,$idArr[$value -> id]);
            }
        }

        if(count($shopNameArray) == 0){
            exit(json_encode(array('status' => 1,'msg' => '没有数据了')));
        }
        exit(json_encode(array('status' => 2,'data' => $shopNameArray)));
    }

    /**
     * 商家提现
     */
    public function actionWithdraw()
    {
        $model = new StatisticsForm('courier');
        $model->area_id = '-1';
        $admin_id = Yii::app()->user->_id;

        $shopids = LewaimaiEmployee::CheckAccount();

        //全部店铺
        if($shopids === false){
            $shop_id_array = array();
        } else {
            $shop_id_array = $shopids;
        }


        $sql = "SELECT c.`shopname`, c.`admin_id`, c.`id` as `shop_id`, a.`balance`, a.`is_freeze`, ea.`account` FROM `wx_config` as c LEFT OUTER JOIN `wx_shop_account` as a on a.`shop_id`= c.`id` LEFT JOIN `wx_employees_account` as ea on a.`employee_id`= ea.`id` WHERE c.`admin_id`= {$admin_id}";
        //获取店铺信息
        if (!empty($shop_id_array)) {
            $sql .= ' and c.`id` in ('.implode(',', $shop_id_array).')';
        }
        $sql .= ' and c.is_delete = 0';
        $sql .= " order by balance desc";
        $datas = Yii::app()->db->createCommand($sql)->queryAll();

        //绑定提现账号查询
        $sql = 'select username from {{admin}} where id='.$admin_id;
        $admin_list = Yii::app()->db->createCommand($sql)->queryRow();
        $admin_name = $admin_list['username'];


        $array_new = array();
        if(!empty($datas)) {
            $sql = 'select shop_id,count(id) as `count` from {{withdraw_log}} where admin_id = '.$admin_id.' group by shop_id';
            $withdraw_sta = Yii::app()->db->createCommand($sql)->queryAll();
            $withdraw_sta_map = [];
            if (!empty($withdraw_sta)) {
                foreach ($withdraw_sta as $k3 =>$v3) {
                    $withdraw_sta_map[$v3['shop_id']] = $v3['count'];
                }
            }
//
//            //商家应得总金额
//            $sellerTotal = 0;
//            //平台应得金额
//            $terraceTotal = 0;
//            //总金额
//            $salesTotal = 0;
            //可提现金额
            $balance = 0;
            //提现次数
            $total_withdraw_count = 0;
            foreach ($datas as $k1 => $v1) {
                $array_new[$k1] = array(
                    'shop_id' => $v1['shop_id'],
                    'shopName' => $v1['shopname'],
//                    'sellerTotal' => $v1['sellerTotal'],
//                    'terraceTotal' => $v1['terraceTotal'],
//                    'salesTotal' => $v1['salesTotal'],
                    'balance' => empty($v1['balance']) ? "0.00" : $v1['balance'],
                    'bind_employee' => !empty($v1['account']) ? "{$admin_name}:{$v1['account']}" : '',
                    'total_withdraw_count' => isset($withdraw_sta_map[$v1['shop_id']]) ? $withdraw_sta_map[$v1['shop_id']] : 0,
                    'is_freeze' => empty($v1['is_freeze']) ? "0" : $v1['is_freeze'],
                );

                $balance += $v1['balance'];

                if (isset($withdraw_sta_map[$v1['shop_id']])) {
                    $total_withdraw_count += $withdraw_sta_map[$v1['shop_id']];
                }

//                $sellerTotal += $v1['sellerTotal'];
//                $terraceTotal += $v1['terraceTotal'];
//                $salesTotal += $v1['salesTotal'];
                    }

            if(!empty($array_new)) {
                $array_new[] = array(
                    'shop_id' => '',
                    'shopName' => '总计',
//                    'sellerTotal' => $sellerTotal,
//                    'terraceTotal' => $terraceTotal,
//                    'salesTotal' => $salesTotal,
                    'balance' => $balance,
                    'bind_employee' => '',
                    'total_withdraw_count' => $total_withdraw_count,
                    'bind_employee' => '',
                    'is_freeze' => 0,
                );
            }
        }
        //查询是否有待审核的提现记录
        $withdraw_logs = Withdrawlog::model()->findAll(array(
            'select'    => array('id'),
            'condition' => 'admin_id=:admin_id AND check_status=:check_status',
            'params'    => array(':admin_id'=>$admin_id,':check_status'=>0),
        ));
        if(empty($withdraw_logs)){
            $is_have_waiting_log = 0;
        }else{
            $is_have_waiting_log = 1;
        }

        unset($datas);

        $adminModel = AdminAccount::model()->findByPk($admin_id);
        $this->render('withdraw', array(
            'model' => $model,
            'tmp' => $array_new,
//            'fenchengmoney' => $fenchengmoney,
            'admin_id' => $admin_id,
//            'areaArr'  => $areaArr,
            'adminModel' => $adminModel,
            'is_have_waiting_log'=>$is_have_waiting_log,
        ));
    }





    //账户管理
    public function actionShopAccountManagement()
    {
        //绑定账号页面
        $admin_id = Yii::app()->user->_id;

        if (isset($_GET['shop_id'])) {
            $shop_id = addslashes($_GET['shop_id']);
        } else {
            throw new CHttpException(404, '您访问的页面不存在！');
        }

        //是否开启商家提现
        $adminAccount = AdminAccount::model()->findByPk($admin_id);
        //这里用来验证店铺id是否在该账号下
        $configModel = Config::model()->findByPk($shop_id);
        if ($configModel->admin_id != $admin_id) {
            throw new CHttpException(404, '无权操作该店铺！');
        }


        //如果为空新建一条记录
        $model = ShopAccount::model()->find("admin_id =" . $admin_id . " AND shop_id =" . $shop_id);
        if(empty($model)) {
            $model = new ShopAccount();
            $model->admin_id = $admin_id;
            $model->shop_id = $shop_id;
            $model->save();
        }

        $error = array();
        //if($adminAccount->is_open_withdraw == 1) {
            if (isset($model->bankname_no)
                && !empty($model->bankname_no)
                && isset($model->bankcard_no)
                && !empty($model->bankcard_no)
                && isset($model->headbankname)
                && !empty($model->headbankname)
                && isset($model->province)
                && !empty($model->province)
                && isset($model->phone)
                && !empty($model->phone)
                && isset($model->employee_id)
                && !empty($model->employee_id)
            ) {
                $issubmit = true;
            } else {
                $issubmit = false;
            }
       /* }else{
            if (isset($model->bankname_no)
                && !empty($model->bankname_no)
                && isset($model->bankcard_no)
                && !empty($model->bankcard_no)
            ) {
                $issubmit = true;
            } else {
                $issubmit = false;
            }
        }*/


        $success = array();
        $sql = 'select username from {{admin}} where id=' . $admin_id;
        $admin_list = Yii::app()->db->createCommand($sql)->queryRow();
        $admin_name = $admin_list['username'];


        if(!empty($shop_id)) {
            $sql = 'select * from {{shop_account}} where admin_id=:admin_id and shop_id=:shop_id';
            $shop_account_row = Yii::app()->db->createCommand($sql)->queryRow(true,array(':admin_id'=>$admin_id,':shop_id'=>$shop_id));

            $sql = 'select username from {{admin}} where id='.$admin_id;
            $admin_list = Yii::app()->db->createCommand($sql)->queryRow();
            $admin_name = $admin_list['username'];
            //获取未停用的账号信息(登陆账号所在分区下的账号信息)
            if(Yii::app()->user->getState('usertype') == 1){
                //员工账号
                $employeeModel = EmployeesAccount::model()->findByPk(Yii::app()->user->getState('employee_id'));
                if ($employeeModel){
                    if ($employeeModel->role_type == 3){
                        // 全平台管理员显示所有员工账号
                        $sql = 'select id,account,shop_ids from {{employees_account}} where admin_id='.$admin_id.' and role_type=1 and status=1';
                    }
                    elseif ($employeeModel->role_type == 2)
                        // 分区管理员显示该分区下的账号
                        $sql = 'select id,account,shop_ids from {{employees_account}} where admin_id='.$admin_id.' and role_type=1 and status=1 and area_id ='.$employeeModel->area_id;
                }
                elseif ($employeeModel->role_type == 5){
                    // 群组管理员显示该群组下的账号
                    $sql = 'select id,account,shop_ids from {{employees_account}} where admin_id='.$admin_id.' and role_type=1 and status=1 and group_id ='.$employeeModel->group_id;
                }
            }else{
                //主账号显示所有员工账号
                $sql = 'select id,account,shop_ids from {{employees_account}} where admin_id='.$admin_id.' and role_type=1 and status=1';
            }

            $employees_account = Yii::app()->db->createCommand($sql)->queryAll();
            foreach ($employees_account as $k1 => $v1) {
                $employees_account[$k1]['employee_account'] = $admin_name.':'.$v1['account'];
            }

            $sql = 'select employee_id from {{shop_account}} where admin_id='.$admin_id.' and employee_id is not null';
            $has_selected_employee = Yii::app()->db->createCommand($sql)->queryAll();


            $can_select_employees = $employees_account;
            foreach ($employees_account as $k1 => $v1) {
                foreach ($has_selected_employee as $k2 => $v2) {
                    if($v1['id'] == $v2['employee_id']) {
                        //拿出选中的账号
                        if(empty($model->employee_id) || $v1['id']!==$model->employee_id) {
                            unset($can_select_employees[$k1]);
                        }
                    }
                }
            }
        }

        $employee_id = $shop_account_row['employee_id'];
        $shop_employee_id = 0;
        if(!empty($employee_id)) {
            $sql = 'select username from {{admin}} where id='.$admin_id;
            $select_admin_name = Yii::app()->db->createCommand($sql)->queryRow()['username'];
            $sql = 'select account from {{employees_account}} where admin_id='.$admin_id.' and id='.$employee_id;
            $select_employees_name = Yii::app()->db->createCommand($sql)->queryRow()['account'];
            $shop_account_row['employee_name'] = $select_admin_name.':'.$select_employees_name;
            $shop_employee_id = $model->employee_id;
        }else{
            if(!empty($can_select_employees)) {
                foreach ($can_select_employees as $k1 => $v1) {
                    if(!empty($v1['shop_ids'])) {
                        $tmp = explode(',',$v1['shop_ids']);
                        if(!empty($tmp)) {
                            if(in_array($shop_id,$tmp)) {
                                $shop_employee_id = $v1['id'];
                                break;
                            }
                        }
                    }
                }
            }
        }

        if (isset($_POST['headbankname'])) {
            if (!$issubmit) {
                $headbankname = trim(htmlspecialchars($_POST['headbankname']));
                $province = trim(htmlspecialchars($_POST['province']));
                $city = trim(htmlspecialchars($_POST['city']));
                $bankname = trim(htmlspecialchars($_POST['bankname']));
                $bankname_no = trim(htmlspecialchars($_POST['bankname_no']));
                $bankusername = trim(htmlspecialchars($_POST['bankusername']));
                $bankcard_no = trim(htmlspecialchars($_POST['bankcard_no']));
                $bankcard_no = str_replace(' ', '', $bankcard_no);

                $queren_bankcard_no = trim(htmlspecialchars($_POST['queren_bankcard_no']));
                $queren_bankcard_no = str_replace(' ', '', $queren_bankcard_no);
                $bank_type = trim(htmlspecialchars($_POST['bank_type']));

                //判断是否有分配该店铺的权限
                $sql = 'select shop_ids from {{employees_account}} where id=:id';
                $shop_ids = Yii::app()->db->createCommand($sql)->queryRow(true,array(':id'=>$_POST['account']))['shop_ids'];
                if(empty($shop_ids)) {
                    $error[] = '没有分配该店铺的权限';
                }
                $shop_ids_arr = explode(',',$shop_ids);
                if(in_array($shop_id,$shop_ids_arr)) {
                }else{
                    $error[] = '没有分配该店铺的权限';
                }

                //银行卡信息要么不填 要么全部填写完整
                $length = strlen($bankcard_no);
                if(!($headbankname=='' or $headbankname===0 or $headbankname==='0') ||
                   !($province == '' or $province === 0 or $province === '0')   ||
                   !($city == '' or $city === 0 or $city === '0')  ||
                   !($bankname == '' or $bankname === 0 or $bankname === '0') ||
                   !($bankcard_no == '' || $length < 1) ||
                   !($bankusername == '')
                ) {
                    if(
                        ($headbankname == '' or $headbankname === 0 or $headbankname === '0') ||
                        ($province == '' or $province === 0 or $province === '0') ||
                        ($city == '' or $city === 0 or $city === '0') ||
                        ($bankname == '' or $bankname === 0 or $bankname === '0') ||
                        ($bankname_no == '') ||
                        ($bankusername == '')
                    ) {
                        $error[] = '如果填写银行卡信息，请填写完整！';
                    }
                    if ($headbankname == '' or $headbankname === 0 or $headbankname === '0') {
                        $error[] = '要选择银行类型';
                    }
                    if ($province == '' or $province === 0 or $province === '0') {
                        $error[] = '要选择省份';
                    }
                    if ($city == '' or $city === 0 or $city === '0') {
                        $error[] = '要选择城市';
                    }
                    if ($bankname == '' or $bankname === 0 or $bankname === '0') {
                        $error[] = '要选择开户行网点';
                    }
                    if ($bankname_no == '') {
                        $error[] = '联行号不能为空';
                    }
                    if ($bankusername == '') {
                        $error[] = '银行开户名不能为空';
                    }
                    if ($bankcard_no == '' || $length < 1) {
                        $error[] = '银行卡号不能为空';
                    }
                    for ($i = 0; $i < $length; $i++) {
                        if (!is_numeric($bankcard_no[$i])) {
                            $error[] = '银行卡号不能非数字';
                            break;
                        }
                    }
                    if ($bankcard_no != $queren_bankcard_no) {
                        $error[] = '银行卡号和确认银行卡号不一致';
                    }
                    if (!in_array($bank_type, array(0, 1))) {
                        exit;
                    }
                    //收款人姓名验证
                    if ($bank_type == 0) {
                        if($headbankname !== '' && $headbankname !== 0 or $headbankname !== '0') {
                            //私人
                            //判断是否是中文
                            if (!preg_match("/^[\x{4e00}-\x{9fa5}|\.|\·|·]+$/u", $bankusername)) {
                                $error[] = "银行开户名只支持中文";
                            }
                            //判断姓名长度
                            if (!preg_match("/^[\x{4e00}-\x{9fa5}|\.|\·|·]{2,30}$/u", $bankusername)) {
                                $error[] = "银行开户名最少为2个字，最多为30个字";
                            }
                            if (strpos($bankusername, '有限') !== false || strpos($bankusername, '公司') !== false) {
                                $error[] = "个人类型的银行卡，不能包含有限，公司等词";
                            }
                        }
                    } else {
                        if($headbankname !== '' && $headbankname !== 0 or $headbankname !== '0') {
                            //对公
                            //判断是否是中文
                            if (!preg_match("/^[\x{4e00}-\x{9fa5}]|&#xFF08;&#xFF09;+$/u", $bankusername)) {
                                echo '银行开户名只支持中文';
                                $error[] = "银行开户名只支持中文";
                            }
                            //判断姓名长度
                            if (!preg_match("/^[\x{4e00}-\x{9fa5}]|&#xFF08;&#xFF09;{8,40}$/u", $bankusername)) {
                                $error[] = "银行开户名最少为8个字";
                            }
                        }
                    }
                }

                //开启商家提现判断手机和提现账号
               // if($adminAccount->is_open_withdraw==1) {
                //添加操作
                //验证手机号格式是否正确
                $pattern = '/^1[3456789]{1}\d{9}$/';
                if(!preg_match($pattern,$_POST['tel'])){
                    $error[] = "手机号格式输入有误";
                }

                $codes = implode("", $_POST['code']);
                //验证验证码是否正确
                if(empty($codes)){
                    $error[] = "请输入验证码";
                }

                $smsCacheSrv = ServiceFactory::getService(SrvType::COMMON_SMS_CACHE_SMSCACHE);
                $smsCode = $smsCacheSrv->getChangeBindBankCardVerifyCode($shop_id);

                if(empty($smsCode) || empty($codes) || $smsCode != $codes){
                    $error[] = "验证码输入错误";
                }

                //一个手机号只能绑定五个商铺
                $count = ShopAccount::model()->count('admin_id=:adminID and phone=:phone',array(':adminID' => $admin_id,':phone' => $_POST['tel']));
                if($count >= 5) {
                    $error[] = "该手机号绑定次数已超限，请更换其他手机号绑定";
                }
               // }

                if (count($error) == 0) {
                    if ($model) {
                        $model->headbankname = $headbankname;
                        $model->province = $province;
                        $model->city = $city;
                        $model->bankname = $bankname;
                        $model->bankname_no = $bankname_no;
                        $model->bankusername = $bankusername;
                        $model->bankcard_no = $bankcard_no;
                        $model->bank_type = $bank_type;
                        //开启商家提现判断手机和提现账号
                       // if($adminAccount->is_open_withdraw==1) {
                        $model->employee_id = $_POST['account'];
                        $model->phone = $_POST['tel'];
                       // }
                        $phone_type1=0;//标记手机是否首次绑定
                        $employee_type1=0;//标记账号是否首次绑定
                        //添加相关记录
                        $m = ShopAccount::model()->find("admin_id =" . $admin_id . " AND shop_id =" . $shop_id);
                        $_phone = $m->phone;//原电话
                        $_employee_id = $m->employee_id;//原账号
                        if (empty($_phone) && !empty($model->phone)) {
                            $phone_type1 = 1;//标记手机首次绑定
                        }
                        if (($_employee_id == 0 || empty($_employee_id)) && !empty($model->employee_id)) {
                            $employee_type1 = 1;//标记账号首次绑定
                        }
                        if (!empty($_phone) && !empty($model->phone) && $_phone !== $model->phone) {
                            $phone_type1 = 2;//标记手机换绑
                        }
                        if ($_employee_id != 0 && !empty($model->employee_id) && $_employee_id !== $model->employee_id) {
                            $employee_type1 = 2;//标记账号换绑
                        }
                        //echo"<pre>";  echo  $phone_type1,"</br>";  echo $employee_type1,"</br>";   print_r($_POST);   var_dump($issubmit);    exit;
                        if ($model->save()) {
                            $success[] = '设置成功';
                            //$issubmit = true;
                            $info = array();
                            $user_type = Yii::app()->user->getState('usertype');
                            if($user_type != 0) {
                                $info['employee_id'] = Yii::app()->user->employee_id;
                            }
                            $info['shop_id'] = $shop_id;
                            $info['act_date'] = date('Y-m-d H:i:s');
                            $service = \lwmf\services\ServiceFactory::getService(\lwm\services\SrvType::TRADE_ORDER_WITHDRAW_LOG);
                            if($phone_type1==1) {
                                $info['act_type'] = 2;
                                $info['dynamic'] = '首次绑定手机号，绑定的手机号为：'.$_POST['tel'];
                                $service->addWithdrawactlog($admin_id, $info);
                            }else if($phone_type1==2) {
                                $info['act_type'] = 2;
                                $info['dynamic'] = '更换绑定手机号，原手机号为：'.$_phone.'更改为'.$_POST['tel'];
                                $service->addWithdrawactlog($admin_id, $info);
                            }
                            if($employee_type1==1) {
                                $sql = 'select account from {{employees_account}} where admin_id=' . $admin_id . ' and id=' . $model->employee_id;
                                $demployees_name = Yii::app()->db->createCommand($sql)->queryRow()['account'];
                                $info['act_type'] = 3;
                                $info['dynamic'] = '首次绑定账号，绑定的账号为：'.$admin_name.':'.$demployees_name;
                                $service->addWithdrawactlog($admin_id, $info);
                            }else if($employee_type1==2) {
                                $sql1 = 'select account from {{employees_account}} where admin_id=' . $admin_id . ' and id=' . $_employee_id;
                                $sql2 = 'select account from {{employees_account}} where admin_id=' . $admin_id . ' and id=' . $model->employee_id;
                                $demployees_name1 = Yii::app()->db->createCommand($sql1)->queryRow()['account'];
                                $demployees_name2 = Yii::app()->db->createCommand($sql2)->queryRow()['account'];
                                $info['act_type'] = 3;
                                $info['dynamic'] = '更换绑定账号，原账号为'. $admin_name.':'.$demployees_name1 .'更改为'.$admin_name.':'.$demployees_name2;
                                $service->addWithdrawactlog($admin_id, $info);
                            }
                            if(!empty($headbankname) &&
                                !empty($province) &&
                                !empty($city) &&
                                !empty($bankname) &&
                                !empty($bankname_no) &&
                                !empty($bankusername) &&
                                !empty($bankcard_no)
                            ) {
                                //更新分成设置的银行卡是否绑定字段信息
                                $fenchengmodel = FenchengSetting::model()->find("admin_id = " . $admin_id . " AND shop_id = " . $shop_id);
                                if(!empty($fenchengmodel)) {
                                    $fenchengmodel->is_blindcard = 1;
                                    $fenchengmodel->update();
                                }
                            }

                            $huifushopapply = HuifuShopApply::model() -> find(" admin_id = ".$admin_id." AND shop_id=".$shop_id);
                            if( $huifushopapply -> status == 2 ) {
                                $shopaccount    = ShopAccount::model()    -> find(" admin_id = ".$admin_id." AND shop_id=".$shop_id);
                                $adminaccount   = AdminAccount::model()   -> find(" admin_id = ".$admin_id);
                                $huifucitycode  = HuifuCitycode::model()  -> find(' city_name= "'.$shopaccount -> city.'"');
                                $isValidata = $this -> validateData($huifushopapply,$shopaccount,$adminaccount,$huifucitycode); //数据验证
                                if(!$isValidata)  $this->redirect(['huifu/shopindex']);
                                $huiFuParams = $this -> gethuiFuParams($huifushopapply,$shopaccount,$adminaccount,$huifucitycode);
                                $huifu = new \lwm\commons\pay\channel\agent\HuiFu();
                                $res = $huifu -> bindingcard($huiFuParams);
                                LewaimaiDebug::Log("================================");
                                LewaimaiDebug::LogArray($res);
                                if($res['code'] == 400) {

                                    LewaimaiDebug::Log("获取汇付返回值");
                                    LewaimaiDebug::LogArray($res);
                                    $hsa = HuifuShopApply::model()->findByPk($huifushopapply -> id);
                                    if($res['data'] -> resp_code == 104000)  {
                                        $hsa -> huifu_cash_bind_card_id = $res['data'] -> cash_bind_card_id;//银行卡绑定ID,取现接口需要用到此ID,由汇付返回
                                        $hsa -> is_blindcard = 1;//是否绑定银行卡 0否 1是
                                        if(!$hsa -> save()) {
                                            LewaimaiDebug::Log("获取huifushopapply、shopaccount");
                                            LewaimaiDebug::LogArray($hsa);
                                        }
                                    }else {
                                        $hsa -> bindingcard_fail_reason = $res['data'] -> resp_desc;
                                        $hsa -> save();
                                        //exit(json_encode(array('status' => 0,'msg' => $res['data'] -> resp_desc)));
                                    }

                                    $this->redirect(['huifu/shopindex']);
                                }
                            }


                            $this->redirect(['fenchengsetting/withdraw']);
                        } else {
                            $error[] = '设置失败';
                        }
                    } else {
                        $model = new ShopAccount();
                        $model->admin_id = $admin_id;
                        $model->shop_id = $shop_id;
                        $model->province = $province;
                        $model->city = $city;
                        $model->headbankname = $headbankname;
                        $model->bankname = $bankname;
                        $model->bankname_no = $bankname_no;
                        $model->bankusername = $bankusername;
                        $model->bankcard_no = $bankcard_no;
                        $model->bank_type = $bank_type;
                       // if($adminAccount->is_open_withdraw==1) {
                            $model->employee_id = $_POST['account'];
                            $model->phone = $_POST['tel'];
                       // }
                        if ($model->save()) {
                            $success[] = '设置成功';
                            $issubmit = true;
                        } else {
                            $error[] = '设置失败';
                        }
                    }
                    //更新分成设置的银行卡是否绑定字段信息
                    $fenchengmodel = FenchengSetting::model()->find("admin_id = " . $admin_id . " AND shop_id = " . $shop_id);
                    $fenchengmodel->is_blindcard = 1;
                    $fenchengmodel->update();
                }
            }
        }


        $this->render('shopaccountmanagement', array(
            'model' => $model,
            'error' => $error,
            'issubmit' => $issubmit,
            'success' => $success,
            'configModel' => $configModel,
            'shop_id' => $shop_id,
            'can_select_employees'=>$can_select_employees,
            'shop_employee_id'=>$shop_employee_id,
            'admin_name'=>$admin_name,
            'adminAccount'=>$adminAccount
        ));

    }


    //组装huiFuParams
    public function gethuiFuParams($huifushopapply,$shopaccount,$adminaccount,$huifucitycode) {
        if(!$huifushopapply || !$shopaccount || !$adminaccount || !$huifucitycode) {
            exit(json_encode(array('status'=>0,'msg'=>'系统错误')));
        }

        $bankname_no  =  $this -> getBankId($shopaccount -> headbankname);
        $out_trade_no = "BD".\lwm\commons\base\Helper::getUniqueTradeNo(18);
        $huiFuParams  = new \lwm\commons\pay\channel\HuiFuParams();
        $huiFuParams -> pfx_url      = $adminaccount -> huifu_pfx_url;//汇付天下pfx文件路径
        $huiFuParams -> password     = $adminaccount -> huifu_pfx_password;//汇付天下pfx文件密码
        $huiFuParams -> version      = 10;//固定为10，如版本升级，能向前兼容
        $huiFuParams -> cmd_id       = "104";//消息类型
        $huiFuParams -> mer_cust_id  = $huifushopapply -> huifu_merchant_id; //商户的唯一标识
        $huiFuParams -> user_cust_id = $huifushopapply -> huifu_shop_mchid; //由汇付生成，用户的唯一性标识
        $huiFuParams -> order_date   = date("Ymd",time()); //订单时间
        $huiFuParams -> order_id     = $out_trade_no; //订单号
        $huiFuParams -> bank_id      = $bankname_no; //银行代号
        $huiFuParams -> dc_flag      = '0'; //借贷标记
        $huiFuParams -> card_no      = $shopaccount -> bankcard_no; //银行卡号
        $huiFuParams -> card_prov    = $huifucitycode -> province_code; //银行卡开户省份
        $huiFuParams -> card_area    = $huifucitycode -> city_code; //银行卡开户地区
        $huiFuParams -> mer_priv     = ''; //可选	为商户的自定义字段，该字段在交易完成后由本平台原样返回
        $huiFuParams -> extension    = ''; //可选	用于扩展请求参数

        return $huiFuParams;
    }


    public function validateData($huifushopapply,$shopaccount,$adminaccount,$huifucitycode) {
        LewaimaiDebug::Log("获取huifushopapply、shopaccount");
        LewaimaiDebug::LogArray($huifushopapply);
        LewaimaiDebug::LogArray($shopaccount);
        LewaimaiDebug::Log("************获取adminaccount、huifucitycode的值**************");
        LewaimaiDebug::LogArray($adminaccount);
        LewaimaiDebug::LogArray($huifucitycode);
        $bankname_no  =  $this -> getBankId($shopaccount -> headbankname);
        if(!isset($huifushopapply -> huifu_merchant_id) || empty($huifushopapply -> huifu_merchant_id)) return false;
        if(!isset($huifushopapply -> huifu_shop_mchid) || empty($huifushopapply -> huifu_shop_mchid)) return false;
        if(!isset($adminaccount -> huifu_pfx_url) || empty($adminaccount -> huifu_pfx_url)) return false;
        if(!isset($adminaccount -> huifu_pfx_password) || empty($adminaccount -> huifu_pfx_password)) return false;
        if(!isset($shopaccount -> bankcard_no) || empty($shopaccount -> bankcard_no)) return false;
        if(empty($bankname_no)) return false;
        if(!isset($huifucitycode -> province_code) || empty($huifucitycode -> province_code)) return false;
        if(!isset($huifucitycode -> city_code) || empty($huifucitycode -> city_code)) return false;
    }


    //获取对应银行代码
    public function getBankId($name) {

        $bank_name_value = [
            '兴业银行'           => '03090000',
            '华夏银行'	        => '03040000',
            '北京银行'	        => '03130011',
            '招商银行'           => '03080000',
            '中国工商银行'       => '01020000',
            '中国建设银行'       => '01050000',
            '中国农业银行'	    => '01030000',
            '光大银行'	        => '03030000',
            '北京农村商业银行'   => '04020011',
            '中国银行'	        => '01040000',
            '中国邮政储蓄银行'	=> '04030000',
            '南京银行'	        => '03133201',
            '杭州银行'	        => '03133301',
            '浙商银行'	        => '03160000',
            '上海银行'	        => '03130031',
            '渤海银行'	        => '03180000',
            '上海农村商业银行'	=> '04020031',
            '广东发展银行'	    => '03060000',
            '民生银行'	        => '03050000',
            '浦东发展银行'	    => '03100000',
            '平安银行'	        => '03134402',
            '浙江民泰商业银行'   => '03133307',
            '浙江泰隆商业银行'   => '',
            '深圳发展银行'	    => '03070000',
            '中信银行'	        => '03020000',
            '交通银行'	        => '03010000',
        ];
        return $bank_name_value["$name"];
    }


    //提现导出
    public function actionExportwithdraw(){
        $beginDate = $_GET['beginDate'];
        $endDate = $_GET['endDate'];
        $area_id = $_GET['area_id'];
        $admin_id = Yii::app()->user->_id;
        $time = strtotime($beginDate)-strtotime($endDate);
        $time = abs($time);
        $days = round(($time)/3600/24);
        if($days > 31){
            echo json_encode(array('notice_msg'=>'最多只能导出31天的数据信息'));
            exit();
        }

        $shopids = LewaimaiEmployee::CheckAccount();

        //全部店铺
        if($shopids === false){
            $shop_id_array = array();
        } else {
            $shop_id_array = $shopids;
        }

        if(!empty($area_id) && $area_id > 0){
            $configModel = Config::model()->findAll('admin_id=:admin_id AND area_id=:area_id and is_delete=0', array(':admin_id'=>$admin_id, ':area_id'=>$area_id));
            $configIdArr = array();
            if($configModel){
                foreach ($configModel as $val) {
                    $configIdArr[] = $val->id;
                }
            }

            $shop_id_array = empty($configIdArr) ? array('-999') : $configIdArr;
        }

        //获取店铺信息
        $sql = 'SELECT id, shopname from {{config}} where admin_id = '.$admin_id.' and is_delete=0';
        if (!empty($shop_id_array)) {
            $sql .= ' and id in ('.implode(',', $shop_id_array).')';
        }
        $shopDataArray = Yii::app()->db->createCommand($sql)->queryAll();
        $tempArray = array();
        foreach ($shopDataArray as $key => $value) {
            $tempArray[$value['shopname']] = $value['id'];
        }

        //获取数据
        $waimaiSrv = ServiceFactory::getService(SrvType::ANALYSIS_WAIMAI);
        $array = $waimaiSrv->getWithdrawInfo($admin_id,$shop_id_array,$beginDate,$endDate);

        $fenchengmoney = array();
        $temp = array();
        foreach ($array as $key => $value) {
            $temp[$value['shopName']] = $value['sellerTotal'];
            if (isset($tempArray[$value['shopName']])) {
                $fenchengmoney[$tempArray[$value['shopName']]] = $value['sellerTotal'];
            }
        }

        //绑定提现账号查询
        $sql = 'select username from {{admin}} where id='.$admin_id;
        $admin_list = Yii::app()->db->createCommand($sql)->queryRow();
        $admin_name = $admin_list['username'];
        $sql = 'select id,account from {{employees_account}} where admin_id='.$admin_id;
        $employees_list = Yii::app()->db->createCommand($sql)->queryAll();
        $sql = 'select shop_id,balance,employee_id,is_freeze from {{shop_account}} where admin_id='.$admin_id;
        $bind_employee = Yii::app()->db->createCommand($sql)->queryAll();

        foreach ($employees_list as $k1 => $v1) {
            $employees_list[$k1]['bind_employee'] = $admin_name.':'.$v1['account'];
        }

        $freeze_employee = array();
        foreach ($bind_employee as $k1 => $v1) {
            $bind_employee[$k1]['bind_employee'] = '';
            foreach ($employees_list as $k2 => $v2) {
                if($v1['employee_id'] == $v2['id']) {
                    $bind_employee[$k1]['bind_employee'] = $v2['bind_employee'];
                }
            }
            //已冻结的店铺
            if($v1['is_freeze'] == 1) {
                $freeze_employee[] = $v1['shop_id'];
            }
        }

        $array_new = array();
        if(!empty($array)) {

            //可提现金额与提现次数的统计
            $shopWhereField = '';
            if (!empty($shop_id_array))
            {
                $shopWhereField = ' and id in ('.implode(',', $shop_id_array).')';
            }

            $beginDate1 = '\''.$beginDate.'\'';
            $endDate1 = '\''.$endDate.' 23:59:59\'';

            $sql = 'select shop_id,count(*) as count from {{withdraw_log}} where init_date >= '.$beginDate1.' and init_date <= '.$endDate1.$shopWhereField.' group by shop_id';
            $withdraw_sta = Yii::app()->db->createCommand($sql)->queryAll();

            foreach ($array as $k1 => $v1) {
                if(empty($v1['shop_id'])) {
                    continue;
                }

                //过滤已冻结的数据
                if(!empty($freeze_employee)) {
                    if(in_array($v1['shop_id'],$freeze_employee)) {
                        continue;
                    }
                }

                $array_new[$k1] = array(
                    'shop_id' => $v1['shop_id'],
                    'shopName' => $v1['shopName'],
                    'sellerTotal' => $v1['sellerTotal'],
                    'terraceTotal' => $v1['terraceTotal'],
                    'salesTotal' => $v1['salesTotal'],
                    'balance' => 0.00,
                    'bind_employee' => '',
                    'total_withdraw_count' => 0,
                );
                foreach ($bind_employee as $k2 => $v2) {

                    if($v1['shop_id'] == $v2['shop_id']) {
                        $array_new[$k1]['balance'] = $v2['balance'];
                        $array_new[$k1]['bind_employee'] = $v2['bind_employee'];
                        continue;
                    }

                }
                foreach ($withdraw_sta as $k3 =>$v3) {
                    if($v1['shop_id'] == $v3['shop_id']) {
                        $array_new[$k1]['total_withdraw_count'] = $v3['count'];
                    }
                    continue;
                }

            }

        }

        $data = array();
        foreach ($array_new as $k5 => $v5) {
            if(!empty($v5)){
                $data[] = array(
                    0 => $v5['shopName'],
                    1 => $v5['sellerTotal'],
                    2 => $v5['terraceTotal'],
                    3 => $v5['salesTotal'],
                    4 => $v5['balance'],
                    5 => $v5['total_withdraw_count'],
                    6 => $v5['bind_employee'],
                );
            }

        }
        if(count($data) == 0){
            exit(json_encode(array('notice_msg'=>'暂无数据，导出失败')));
        }
        //开始导出
        ob_end_clean();
        ob_start();

        $dir_name = $admin_id.date('YmdHis',time());
        $file_path = Yii::app()->getBasePath().'/data/cvs/'.$dir_name;

        //设置各列的标题
        $head = array('店铺名称', '商家应得金额', '平台应得金额', '总金额', '可提现金额', '提现次数', '绑定提现账号');

        //店铺名作为csv名称
        $shopname = preg_replace('/[\\\*\/\:\?\"\<\>\|\[\]]/','','财务结算');
        $filename = $shopname.'.csv';
        $filename = $file_path.'/'.$filename;

        // 往头部和脚步压入数组
        array_unshift($data, $head);
        LewaimaiFile::write_cvs($data,$filename);
        $file_arr[] = $filename;

        $zip=new ZipArchive();
        $last_dir = dirname($file_path).'/';
        $zip_name = $last_dir.$dir_name.'.zip';
        addFileToZip($zip_name,$file_arr);
        // 上传文件到cdn,返回文件地址
        if($cdn_path = LewaimaiCDN::uploadTempCvs($zip_name)){
            echo json_encode(array('success'=>1,'file_path'=>$cdn_path));
        }else{
            // Failed upload file to cdn!
            echo json_encode(array('notice_msg'=>'导出失败，当前时间段没有统计数据 '));
        }
        // 删除文件夹以及文件夹下的文件,删除压缩包
        deldir($file_path);
        @unlink($zip_name);
        exit();
    }

    /**
     * 点击绑定账户判断是否绑定手机号
     */
    public function actionJudgehasphone()
    {
        $admin_id = Yii::app()->user->_id;
        $shop_id = intval($_POST['shop_id']);
        if(empty($shop_id)) {
            echo json_encode(array('errno'=>'99991','msg'=>'参数错误'));exit;
        }
        $sql = 'select phone from {{shop_account}} where admin_id='.$admin_id.' and shop_id=:shop_id';
        $phone = Yii::app()->db->createCommand($sql)->queryRow(true,array(':shop_id'=>$shop_id))['phone'];
        if(!empty($phone)) {
            echo json_encode(array('errno'=>'0','msg'=>'success','shop_id'=>$shop_id));exit;
        }else{
            echo json_encode(array('errno'=>'99992','msg'=>'未绑定手机号'));exit;
        }
    }

    /**
     * 提现冻结账号
     */
    public function actionWithdrawfreeze()
    {
        $admin_id = Yii::app()->user->_id;
        $shop_id = intval($_POST['shop_id']);
        $sql = 'select id from {{shop_account}} where shop_id='.$shop_id;
        $shop_data = Yii::app()->db->createCommand($sql)->queryRow();
        if(!empty($shop_data)) {
            //跟新
            $sql = 'update {{shop_account}} set is_freeze=1 where id='.$shop_data['id'];
        }else{
            //添加
            $sql = "insert into {{shop_account}}(admin_id,shop_id,is_freeze) value({$admin_id},{$shop_id},1)";
        }
        $sign = Yii::app()->db->createCommand($sql)->execute();
        if($sign) {
            $info = array();
            $user_type = Yii::app()->user->getState('usertype');
            if($user_type != 0) {
                $info['employee_id'] = Yii::app()->user->employee_id;
            }
            $info['shop_id'] = $shop_id;
            $info['act_type'] = 1;
            $info['dynamic'] = '提现冻结';
            $info['act_date'] = date('Y-m-d H:i:s');

            $service = \lwmf\services\ServiceFactory::getService(\lwm\services\SrvType::TRADE_ORDER_WITHDRAW_LOG);
            $service->addWithdrawactlog($admin_id, $info);
            exit(json_encode(array('errno'=>'0','msg'=>'冻结成功')));
        }

    }


    protected function getShopUrl($data){
        $str = "<div class='shopurl' style='background:#82AF6F;text-align:center;color:white;cursor:pointer;width:60px;' onclick='ShopurlShow(".$data->shop_id.",".$data->admin_id.")'>查看<input class='ids' type='hidden' value='".$data->shop_id."'><input class='aids' type='hidden' value='".$data->admin_id."'></div>";
        return $str;
    }
    protected function getShopTicket($data){
        $str = "<div class='shopticket' style='background:#82AF6F;text-align:center;color:white;cursor:pointer;width:60px;' onclick='Shopticket(".$data->shop_id.",".$data->admin_id.")'>查看<input class='mids' type='hidden' value='".$data->shop_id."'><input class='ads' type='hidden' value='".$data->admin_id."'></div>";

        return $str;
    }

    public function actionShopcheck()
    {
        $flag = "all";
        $admin_id = Yii::app()->user->_id;
        $model=new ShopWechatmoneyCheck();
        $model->unsetAttributes();  // clear any default values
        $model->admin_id = $admin_id;
        $auth = "admin";
        $shop_ids = LewaimaiEmployee::CheckAccount();
        if($shop_ids){
            $auth = "employee";
        }else{
            $shop_ids = array();
        }

        if(isset($_GET['status'])){
            $flag = "all".$_GET['status'];
            $model->status = $_GET['status'];
        }

        $res = array();
        $rel = array();
        if(isset($_GET['ShopWechatmoneyCheck'])) {
            $shop_name = isset($_GET['ShopWechatmoneyCheck']['shop_name']) ? trim($_GET['ShopWechatmoneyCheck']['shop_name']) : '' ;
            $sql = "SELECT id,shopname FROM {{config}} WHERE is_delete=0 and shopname like " . "'%".$shop_name."%'";
            $res = Yii::app()->db->createCommand($sql)->queryAll();
            if($res) {
                foreach($res AS $k=>$v) {
                    array_push($rel,$v['id']);
                }
                $model->ids = $rel;
            }
        }

        $this->render('shopcheck',array(
            'model'=>$model,
            'flag'=>$flag,
            'shop_ids'=>$shop_ids,
            'auth'=>$auth,
        ));
    }

    protected function getHandel($data)
    {
        $str = "";
        if($data->status == 0){
            $str = '<a style="width: 60px;margin-left:10px;" class="label label-sm label-info" title="通过" onclick="setStatus('.$data->id.', 1)">通过</a>';
            $str .= '<a style="width: 60px;margin-left:10px;" class="label label-sm label-success" title="失败" onclick="setStatus('.$data->id.', 2)">失败</a>';
        }elseif($data->status == 2){
            $str = '<a style="width: 60px;margin-left:10px;" class="label label-sm label-danger" title="删除" onclick="delCheck('.$data->id.')">删除</a>';
        }elseif($data->status == 1){
            $str = '<a style="width: 60px;margin-left:10px;background-color:#892E65!important;" class="label label-sm" title="修改" href="'.Yii::app()->createUrl('fenchengsetting/editcheck', array('id'=>$data->id)).'">修改</a>
                    <a style="width: 60px;margin-left:10px;" class="label label-sm label-info" title="换绑" onclick=" setBinding('.$data->id.', 2) ">换绑</a>';
            /*$str = '<a style="float: left;width: 55px;margin-left:10px;background-color:#892E65!important;" class="label label-sm" title="修改" href="'.Yii::app()->createUrl('fenchengsetting/editcheck', array('id'=>$data->id)).'">修改</a>
                    <a style="float: left;width: 55px;margin-left:10px;" class="label label-sm label-info" title="换绑" onclick=" setBinding('.$data->id.', 2) ">换绑</a>';
            */
        }
        return $str;
    }



    public function actionSetbinding()
    {
        if(!isset($_POST['id'])){
            $result = array('status' =>"error" ,'message'=> "参数错误！");
            echo CJSON::encode($result);
            exit();
        }
        $connection = Yii::app()->db;
        $admin_id = Yii::app()->user->_id;
        $id = $_POST['id'];

        $sql = 'select * from wx_shop_wechatmoney_check where id='.$id.' AND admin_id = '.$admin_id;
        $row =  $connection->createCommand($sql)->queryRow();
        if(!$row){
            $result = array('status' =>"error" ,'message'=> "参数错误！");
            echo CJSON::encode($result);
            exit();
        }
        $is_binding = $row['is_binding'];
        if($is_binding==1){
            $msg = "已解绑，请尽快发送二维码或者链接商家填写";
            $result = array('status' =>"error" ,'message'=> $msg);
            echo CJSON::encode($result);
            exit();
        }

        $sql1 = 'UPDATE wx_shop_wechatmoney_check set is_binding = 1 where id = '.$id." AND admin_id=".$admin_id;
        $res = $connection->createCommand($sql1)->execute();
        if(!$res){
            $result = array('status' =>"error" ,'message'=> "服务器错误，部分审核处理失败");
            echo CJSON::encode($result);
            exit();
        }

        $sql2 = 'UPDATE wx_shop_account set openid = NULL , wechat_name = NULL  where admin_id = '.$admin_id.' AND shop_id='.$row['shop_id'];
        $connection->createCommand($sql2)->execute();

        $result = array('status' =>"success" ,'message'=> "解锁成功，请尽快发送二维码或者链接商家填写");
        echo CJSON::encode($result);
        exit();
    }





    /**
     * 批量审核微信零钱商户
     */
    public function actionBatchhandel()
    {
        \lwmf\base\Logger::info("批量处理");
        if(!isset($_POST['status'])){
            $result = array('status' =>"error" ,'message'=> "参数错误！");
            echo CJSON::encode($result);
            exit();
        }
        $admin_id = Yii::app()->user->_id;
        $status = $_POST['status'];

        $connection = Yii::app()->db;
        $sql = 'select * from wx_shop_wechatmoney_check where admin_id = '.$admin_id.' AND status=0';
        $rows =  $connection->createCommand($sql)->queryAll();
        if(!$rows){
            $result = array('status' =>"error" ,'message'=> "没有可以处理的审核！");
            echo CJSON::encode($result);
            exit();
        }
        if($status == 1){
            foreach ($rows as $row){
                $id= $row['id'];
                $openid = $row['openid'];
                $shanghu_name = $row['shanghu_name'];
                $shop_id = $row['shop_id'];
                $telephone = $row['telephone'];
                if(empty($openid) || empty($shanghu_name)){
                    $msg = $shanghu_name."信息错误，无法处理！";
                    $result = array('status' =>"error" ,'message'=> $msg);
                    echo CJSON::encode($result);
                    exit();
                }

                $model = ShopAccount::model()->find("admin_id = ".$admin_id." AND shop_id = ".$shop_id);
                if(!$model){
                    $model = new ShopAccount();
                    $model->admin_id = $admin_id;
                    $model->shop_id = $shop_id;
                    if(!$model->save()){
                        $msg = $shanghu_name."系统异常，请稍后再试！";
                        $result = array('status' =>"error" ,'message'=> $msg);
                        echo CJSON::encode($result);
                        exit();
                    }
                }
                $sql1 = 'UPDATE wx_shop_wechatmoney_check set status = 1 where id = '.$id;
                $res = $connection->createCommand($sql1)->execute();
                if(!$res){
                    $result = array('status' =>"error" ,'message'=> "服务器错误，部分审核处理失败");
                    echo CJSON::encode($result);
                    exit();
                }
                $sql2 = 'UPDATE wx_shop_account set openid = "'.$openid.'", wechat_name="'.$shanghu_name.'", telephone="'.$telephone.'" where admin_id = '.$admin_id.' AND shop_id='.$shop_id;
                $connection->createCommand($sql2)->execute();

            }
        }elseif ($status == 2){
            foreach ($rows as $row){
                $id= $row['id'];
                $sql1 = 'UPDATE wx_shop_wechatmoney_check set status = 2 where id = '.$id;
                $res = $connection->createCommand($sql1)->execute();
                if(!$res){
                    $result = array('status' =>"error" ,'message'=> "服务器错误1，部分审核处理失败");
                    echo CJSON::encode($result);
                    exit();
                }

            }
        }
        $result = array('status' =>"success" ,'message'=> "操作成功！");
        echo CJSON::encode($result);
        exit();
    }

    //获取审核状态
    protected function getCheckstatus($data)
    {
        if($data->status == 0){
            echo "<span style='color:#3399CC;font-weight:bold;'>处理中</span>";
        }else if($data->status == 1){
            echo "<span style='color:#339933;font-weight:bold;'>成功</span>";
        }else if($data->status == 2){
            echo "<span style='color:#CC3300;font-weight:bold;'>失败</span>";
        }
    }

    public function actionSetstatus()
    {
        if(!isset($_POST['status']) || !isset($_POST['id'])){
            $result = array('status' =>"error" ,'message'=> "参数错误！");
            echo CJSON::encode($result);
            exit();
        }
        $connection = Yii::app()->db;
        $admin_id = Yii::app()->user->_id;
        $status = $_POST['status'];
        $id = $_POST['id'];

        $sql = 'select * from wx_shop_wechatmoney_check where id='.$id.' AND admin_id = '.$admin_id.' AND status=0';
        $row =  $connection->createCommand($sql)->queryRow();
        if(!$row){
            $result = array('status' =>"error" ,'message'=> "参数错误！");
            echo CJSON::encode($result);
            exit();
        }
        $openid = $row['openid'];
        $shanghu_name = $row['shanghu_name'];
        $shop_id = $row['shop_id'];
        $telephone = $row['telephone'];
        if(empty($openid) || empty($shanghu_name)){
            $msg = $shanghu_name."信息错误，无法处理！";
            $result = array('status' =>"error" ,'message'=> $msg);
            echo CJSON::encode($result);
            exit();
        }
        $model = ShopAccount::model()->find("admin_id = ".$admin_id." AND shop_id = ".$shop_id);
        if(!$model){
            $model = new ShopAccount();
            $model->admin_id = $admin_id;
            $model->shop_id = $shop_id;
            if(!$model->save()){
                $msg = $shanghu_name."系统异常，请稍后再试！";
                $result = array('status' =>"error" ,'message'=> $msg);
                echo CJSON::encode($result);
                exit();
            }
        }

        $sql1 = 'UPDATE wx_shop_wechatmoney_check set status = '.$status.' where id = '.$id." AND admin_id=".$admin_id;
        $res = $connection->createCommand($sql1)->execute();
        if(!$res){
            $result = array('status' =>"error" ,'message'=> "服务器错误1，部分审核处理失败");
            echo CJSON::encode($result);
            exit();
        }
        if($status == 1){
        $sql2 = 'UPDATE wx_shop_account set openid = "'.$openid.'", wechat_name="'.$shanghu_name.'", telephone="'.$telephone.'" where admin_id = '.$admin_id.' AND shop_id='.$shop_id;
        $connection->createCommand($sql2)->execute();
        }
        $result = array('status' =>"success" ,'message'=> "成功");
        echo CJSON::encode($result);
        exit();
    }

    public function actionDelcheck()
    {
        if(!isset($_POST['id'])){
            $result = array('status' =>"error" ,'message'=> "参数错误！");
            echo CJSON::encode($result);
            exit();
        }
        $admin_id = Yii::app()->user->_id;
        $id = $_POST['id'];
        $model = ShopWechatmoneyCheck::model()->findByPk($id);
        if(!$model){
            $result = array('status' =>"error" ,'message'=> "记录不存在！");
            echo CJSON::encode($result);
            exit();
        }
        if($model->admin_id != $admin_id){
            $result = array('status' =>"error" ,'message'=> "非法请求！");
            echo CJSON::encode($result);
            exit();
        }
        if(!$model->delete()){
            $result = array('status' =>"error" ,'message'=> "删除失败！");
            echo CJSON::encode($result);
            exit();
        }
        $result = array('status' =>"success" ,'message'=> "删除成功");
        echo CJSON::encode($result);
        exit();
    }

    /**
     * 店铺提现账号解冻
     * @dateTime 2018-05-17
     * @author MaWei<www.mawei.live>
     */
    function actionUnfreeze(){
        $shopId = isset($_POST['shop_id']) ? intval($_POST['shop_id']) : 0;
        //判断店铺参数
        if($shopId < 1) exit(CJSON::encode(['status' =>"error" ,'message'=> "请求参数错误!"]));
        //更新账号解冻状态
        $sql = 'update {{shop_account}} set is_freeze=0 where shop_id='.$shopId;
        $ret = Yii::app()->db->createCommand($sql)->execute();
        if(!$ret){
            exit(CJSON::encode(['status' =>"400" ,'message'=> "解冻失败,请恻刷新后重试!"]));
        }
        exit(CJSON::encode(['status' =>"200" ,'message'=> "解冻成功!"]));
    }

    public function actionEditcheck($id)
    {
        $admin_id = Yii::app()->user->_id;
        $model = ShopWechatmoneyCheck::model()->findByPk($id);
        if(!$model){
            throw new CHttpException(403, '记录不存在！');
}
        if($model->admin_id != $admin_id){
            throw new CHttpException(403, '非法请求！');
        }
        if($model->status != 1){
            throw new CHttpException(403, '非法请求！');
        }

        if (isset($_POST['ShopWechatmoneyCheck'])) {
            if(!isset($_POST['ShopWechatmoneyCheck']['shanghu_name'])){
                throw new CHttpException(403, '数据异常！');
            }
            $shanghu_name = $_POST['ShopWechatmoneyCheck']['shanghu_name'];
            $model->shanghu_name = $shanghu_name;
            if(!$model->update()){
                throw new CHttpException(403, '修改失败！');
            }
            //修改店铺账号微信打款名
            $shopAccount = ShopAccount::model()->find("admin_id = ".$admin_id." AND shop_id = ".$model->shop_id);
            if($shopAccount){
                //修改wechat_name和openid
                $shopAccount->wechat_name = $shanghu_name;
                $shopAccount->openid = $model->openid;
                $shopAccount->update();
            }else{
                $shopAccount = new ShopAccount();
                $shopAccount->admin_id = $admin_id;
                $shopAccount->shop_id = $model->shop_id;
                $shopAccount->wechat_name = $shanghu_name;
                $shopAccount->openid = $model->openid;
                $shopAccount->save();
            }
            $this->redirect(['fenchengsetting/shopcheck']);
        }

        $this->render('editcheck', [
            'model' => $model
        ]);
    }

    public function actionEditwithdrawwechatname($id)
    {
        $admin_id = Yii::app()->user->_id;
        $model = DakuanOrderItem::model()->findByPk($id);
        if(!$model){
            throw new CHttpException(403, '记录不存在！');
        }
        if($model->admin_id != $admin_id){
            throw new CHttpException(403, '非法请求！');
        }
        if($model->status != 2){
            throw new CHttpException(403, '非法请求！');
        }
        if($model->pingtai_type != 16){
            throw new CHttpException(403, '非法请求！');
        }
        $shopModel = Config::model()->findByPk($model->shop_id);
        $shopname = $shopModel->shopname;
        if (isset($_POST['DakuanOrderItem'])) {
            if(!isset($_POST['DakuanOrderItem']['bankusername'])){
                throw new CHttpException(403, '数据异常！');
            }
            $bankusername = $_POST['DakuanOrderItem']['bankusername'];
            $model->bankusername = $bankusername;
            if(!$model->update()){
                throw new CHttpException(403, '修改失败！');
            }
            $this->redirect(yii::app()->createUrl('fenchengsetting/dakuanitem',array('id'=>$model->order_id)));
        }

        $this->render('editwithdrawwechatname', [
            'model' => $model,
            'shopname' => $shopname
        ]);
    }


    /*
     * 提现方式设置
     * @author wangsixiao
     *
     * @param int $setWay -设置的打款方式编号[1:天付宝支付到银行卡 2：微信支付到银行卡 3：微信支付到微信零钱]
     *
     *
     * **/
    public function actionWithdrawset(){
        $admin_id = Yii::app()->user->_id;
        $param = $_GET;
        if(isset($_POST) && !empty($_POST)){
            $param = $_POST;
        }
        //判断商家是否已经设置过打款方式
        $accountModel = AdminAccount::model()->findByPk($admin_id);

        if(isset($param['setWay'])){
            if($param['setWay'] == 1){
                throw new CHttpException(400, '天付宝支付到银行卡打款方式已下线');
            }
            $accountModel->withdraw_type = $param['setWay'];
            $accountModel->update();
        }
        $this->render('withdrawset',array(
            'accountModel'=> $accountModel,
            'admin_id'    => $admin_id
        ));
    }
}
