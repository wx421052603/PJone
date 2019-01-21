<?php
use lwmf\services\ServiceFactory;
use lwm\services\SrvType;

class FenchengsettingController extends Controller

{
    public $layout = '//layouts/main';
    public $FirstMenu = 'ͳ��';
    public $SecondMenu = '����ͳ��';

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
            echo "<span style='color:#6CBC4E'>��</span>";;
        }elseif($obj->is_confirm == 0) {
            echo "<span style='color:#DA4F4A'>��</span>";;
        }
    }

    public function actionIndex()
    {
        $admin_id = Yii::app()->user->_id;
        $shop_ids = array();
        //���û�м�¼������ԭʼ��¼
//      $configModel = Config::model()->findAll("admin_id = ".$admin_id);
        $sql = 'SELECT id from wx_config where admin_id = '.$admin_id;
        $configModel = Yii::app()->db->createCommand($sql)->queryAll();
        if($configModel){
            foreach ($configModel as $config){
                $tichModel = FenchengSetting::model()->find('admin_id = '.$admin_id." AND shop_id = ".$config['id']);

                $shop_ids[] = $config['id'];
                //��������ڣ��ʹ���
                if(!$tichModel){
                    $fenchengModel = new FenchengSetting();
                    $fenchengModel->admin_id = $admin_id;
                    $fenchengModel->shop_id = $config['id'];
                    if(!$fenchengModel->save()){
                        LewaimaiDebug::LogModelError($fenchengModel);
                        throw new CHttpException(403, '��������ʧ�ܣ�');
                    }
                }
            }
        }else{
            throw new CHttpException(403, '����û�д������̣����ȴ������̣�');
        }
        $shopids = array();
        //�ж���Ա���˺ŵ����
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
     * ͬ����������
     */
    public function actionSetshop()
    {
        $admin_id = Yii::app()->user->_id;
        if ($shopids = LewaimaiEmployee::CheckAccount())
        {
            $shopids = implode(',', $shopids);
            $shoplists = Config::model()->findAll([
              'select' => 'id,shopname',
              'condition' => 'admin_id=' . $admin_id . ' AND id IN (' . $shopids . ') and is_delete = 0',//���˵�ɾ���ĵ���
            ]);
        }
        else
        {
            $shoplists = Config::model()->findAll([
              'select' => 'id,shopname',
              'condition' => 'admin_id=:admin_id and is_delete = 0',//���˵�ɾ���ĵ���
              'params' => [':admin_id' => $admin_id],
            ]);
        }
        if (isset($_POST['SetShop']))
        {
            if (isset($_POST['SetShop']['syn_type']) && 1 == $_POST['SetShop']['syn_type']) // ȫ������
            {
                $shop_ids = [];
                foreach ($shoplists as $v)
                {
                    $shop_ids[] = $v->id;
                }
            }
            else // ���ֵ���
            {
                $shop_ids = isset($_POST['SetShop_ids']) ? $_POST['SetShop_ids'] : [];
            }

            $shop_id = isset($_POST['SetShop']['shop_id']) ? $_POST['SetShop']['shop_id'] : [];
            $select_setting = isset($_POST['select_setting']) ? $_POST['select_setting'] : [];
            if(empty($shop_id)){
                exit(json_encode(array('status' => 1,'message' => '��Ҫͬ�����õ�Դ���̲���Ϊ��')));
            }
            if(empty($shop_ids)){
                exit(json_encode(array('status' => 1,'message' => 'Ŀ����̲���Ϊ��')));
            }
            if(empty($select_setting)){
                exit(json_encode(array('status' => 1,'message' => '��Ҫͬ��������ѡ���Ϊ��')));
            }
            $fenchengsettingModel = FenchengSetting::model()->find("admin_id = {$admin_id} AND shop_id = {$shop_id}");
            if(empty($fenchengsettingModel)){
                exit(json_encode(array('status' => 1,'message' => '��������Դ���̵�ͬ��������')));
            }

            foreach ($shop_ids as $shopid)
            {
                $fenchengModel = FenchengSetting::model()->find("admin_id = {$admin_id} AND shop_id = {$shopid}");
                $is_update = true;
                if(empty($fenchengModel)){
                    $fenchengModel = new FenchengSetting();
                    $is_update = false;
                    //��Ĭ����
                    $fenchengModel -> admin_id = $admin_id;
                    $fenchengModel -> shop_id = $shopid;
                    $fenchengModel -> foodprice_pt = 40;//ƽ̨���Ĭ��40
                    $fenchengModel -> foodprice_sj = 60;//�̼����Ĭ��60����ͬ�����ã��ٽ����޸�
                    $fenchengModel -> is_confirm = 1;//Ĭ��ȷ����ɱ���������ȷ
                }
                //������
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
                //�Żݷ�̯
                /*if(in_array('2',$select_setting)) {*/
                if(false) {//ȥ������ͬ�����Żݷ�̯��
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
                //���������۳���
                if(in_array('3',$select_setting)) {
                    $fenchengModel->is_deduct_offline = $fenchengsettingModel->is_deduct_offline;
                }
                //��������
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
                    exit(json_encode(array('status' => 1,'message' => 'ͬ��ʧ��')));
                }
            }

            exit(json_encode(array('status' => 2,'message' => 'ͬ���ɹ�')));
            /*$this->redirect(array('fenchengsetting/index'));
            return;*/
        }

        $this->render('setshop',array(
            'shoplists' => $shoplists,
            'adminaccount' => AdminAccount::model()->findByPk($admin_id)
        ));
    }

    /**
     * �������
     * @author wangsixiao
     * @param int $setWay -���õĴ�ʽ���[1:�츶��֧�������п� 2��΢��֧�������п� 3��΢��֧����΢����Ǯ]
     */
     public function actionDakuanSet(){
        $admin_id = Yii::app()->user->_id;
        $param = $_GET;
        if(isset($_POST) && !empty($_POST)){
            $param = $_POST;
        }
        //�ж��̼��Ƿ��Ѿ����ù���ʽ
        $accountModel = AdminAccount::model()->findByPk($admin_id);

        if(!empty($param)){
                if(2 == $param['setWay']||3 == $param['setWay']){
                    $accountModel->dakuan_type = $param['setWay'];
                    $accountModel->update();
                    exit(json_encode(array('errno' => '99999','msg' => '����ɹ�')));
                }else{
                    exit(json_encode(array('errno' => '99999','msg' => '��ѡ��һ�ִ�ʽ')));
                }
        }else{
            $this->render('dakuanset',array(
                'accountModel'=>$accountModel,
            ));
        }

//        if(isset($param['setWay'])){
//            if($param['setWay'] == 1){
//                //�츶��֧�������п�
////                $this->actionSetTianfubaoHandel($param);
//                $this->actionSetwechatpayHandel($param);
//            }else if($param['setWay'] == 2){
//                //΢��֧�������п����ߵ�΢��Ǯ��
//                $this->actionSetwechatpayHandel($param);
//            }elseif($param['setWay'] == 3) {
//                //΢��֧����΢����Ǯ
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
//            //���Ѿ����ù�����ʾ�����ù�����Ϣ
//            if($accountModel->dakuan_type == 1){
//                $this->actionSetwechatpayHandel($param);
//                //�츶��֧�������п�
////                $this->actionSetTianfubaoHandel($param);
//            }else if($accountModel->dakuan_type == 2){
//                //΢��֧�������п����ߵ�΢����Ǯ
//                $this->actionSetwechatpayHandel($param);
//            }elseif($accountModel->dakuan_type == 3) {
//                //΢��֧����΢����Ǯ
//                $this->actionSetwechatmoneypayHandel($param);
//            }else{
//                $this->render('dakuanset',array(
//                    'accountModel'=>$accountModel
//                ));
//            }
//        }
    }

    /*
     * �����̼�һ�������츶���˺ž��
     *
     * @author wangsixiao
     * **/
    public function actionSetTianfubaoHandel($param)
    {
//        throw new CHttpException(500,'�ô�ʽ���¼ܣ�');
        $admin_id = Yii::app()->user->_id;
        //��ȡ�츶������̻���Ϣ
        $tianxiaApply = TianxiaApply::model()->findAll("admin_id={$admin_id} AND status=3");
        $accountModel = AdminAccount::model()->findByPk($admin_id);

        if(isset($param['AdminAccount'])){
            $mchid = $param['AdminAccount']['tianxiazhifu_mchid'];
            $accountModel->tianxiazhifu_mchid = $mchid;
            $accountModel->dakuan_type = 1;//�츶�������п�
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
     * ����΢�Ŵ��������
     *
     * @author wangsixiao
     *
     * **/
    public function actionSetwechatpayHandel($param){
        $adminId = Yii::app()->user->_id;
        //��ȡ΢�Ŵ��������Ϣ��΢�Ŵ�ʽ
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
        //����΢��������Ϣ
        $msg = '';
        if (isset($param["WeixindakuanAccount"]))
        {
            $model['mchid'] = $param["WeixindakuanAccount"]['mchid'];
            $model['key'] = $param["WeixindakuanAccount"]['key'];
            $model['apiclient_cert'] = $param["WeixindakuanAccount"]['apiclient_cert'];
            $model['apiclient_key'] = $param["WeixindakuanAccount"]['apiclient_key'];
            $dakuanSrv->updateById($model['id'], $model);
            //���´�ʽ
            $setWay = $param['setWay'];
            if($accountModel->dakuan_type != $setWay){
                $accountModel->dakuan_type = $setWay;
                $accountModel->update();
            }
            $msg = '����ɹ�';
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
     * ����΢�Ŵ��������
     *
     * @author wangsixiao
     *
     * **/
    public function actionSetwechatmoneypayHandel($param){
        $adminId = Yii::app()->user->_id;
        //��ȡ΢�Ŵ��������Ϣ��΢�Ŵ�ʽ
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
        //����΢��������Ϣ
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
            //���´�ʽ
            $setWay = $param['setWay'];
            if($accountModel->dakuan_type != $setWay){
                $accountModel->dakuan_type = $setWay;
                $accountModel->update();
            }
            $msg = '����ɹ�';
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
     * ���������츳���̻���(���޸Ĵ�ʽ)
     *
     * **/
    public function actionSetTianfubao(){
        $admin_id = Yii::app()->user->_id;
        //��ȡ�츶������̻���Ϣ
        $tianxiaApply = TianxiaApply::model()->findAll("admin_id={$admin_id} AND status=3");
        $accountModel = AdminAccount::model()->findByPk($admin_id);

        if(isset($_POST['AdminAccount'])){
            $mchid = $_POST['AdminAccount']['tianxiazhifu_mchid'];
            $accountModel->tianxiazhifu_mchid = $mchid;
           // $accountModel->dakuan_type = 1;//�츶�������п�
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
        //���ж����id�ĵ��̣���û��Ȩ�޷���
        $adminId = Yii::app()->user->_id;
        $id = isset($_POST['id'])?$_POST['id']:'';
        $fenchengsettingModel = FenchengSetting::model()->findByPk($id);

        if (!$fenchengsettingModel || $fenchengsettingModel->admin_id != $adminId)
        {
            throw new CHttpException(403, '��û��Ȩ�ޣ�');
        }

        //�ж�Ա���˺��Ƿ��в������̵�Ȩ��
        if(LewaimaiEmployee::CheckAccounts($fenchengsettingModel->shop_id)){
            throw new CHttpException(403, '��Ȩ������');
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
        //�����б�
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
        //���ж����id�ĵ��̣���û��Ȩ�޷���
        $admin_id = Yii::app()->user->_id;
        $shop_id = isset($_POST['shop_id'])?intval($_POST['shop_id']):'';

        $shopModel = Config::model()->findByPk($shop_id);
        if (!$shopModel || $shopModel->admin_id != $admin_id)
        {
            $result["status"] = "error";
            $result["message"] = "��û��Ȩ�ޣ�";
            $JsonString = JSON($result);
            echo $JsonString;
            exit();
        }

        //�ж�Ա���˺��Ƿ��в������̵�Ȩ��
        if(LewaimaiEmployee::CheckAccounts($shop_id)){
            $result["status"] = "error";
            $result["message"] = "��Ȩ������";
            $JsonString = JSON($result);
            echo $JsonString;
            exit();
        }
        //��������
        $fenchengsettingModel = FenchengSetting::model()->find("admin_id = ".$admin_id.' AND shop_id = '.$shop_id);
        if($fenchengsettingModel){
            $result["status"] = "error";
            $result["message"] = "�õ����Ѵ��ڣ������ظ���ӣ�";
            $JsonString = JSON($result);
            echo $JsonString;
            exit();
        }
        $fenchengModel = new FenchengSetting();
        $fenchengModel->admin_id = $admin_id;
        $fenchengModel->shop_id = $shop_id;
        if($fenchengModel->save()){
            $result["status"] = "success";
            $result["message"] = "�ɹ�";
            $JsonString = JSON($result);
            echo $JsonString;
            exit();
        }else{
            $result["status"] = "error";
            $result["message"] = "����ʧ�ܣ������ԣ�";
            $JsonString = JSON($result);
            echo $JsonString;
            exit();
        }
    }

    public function actionUpdate($id){
        //���ж����id�ĵ��̣���û��Ȩ�޷���
        $admin_id = Yii::app()->user->_id;
        $fenchengsettingModel = FenchengSetting::model()->findByPk($id);

        if (!$fenchengsettingModel || $fenchengsettingModel->admin_id != $admin_id)
        {
            throw new CHttpException(403, '��û��Ȩ�޷��ʸõ��̣�');
        }
        //�ж�Ա���˺��Ƿ��в������̵�Ȩ��
        if(LewaimaiEmployee::CheckAccounts($fenchengsettingModel->shop_id)){
            throw new CHttpException(403, '����Ȩ���������õ��̣�');
        }

        $model=$this->loadModel($id);

        if(isset($_POST['FenchengSetting']))
        {
            $foodprice_pt = $_POST['FenchengSetting']['foodprice_pt'];
            $foodprice_sj = $_POST['FenchengSetting']['foodprice_sj'];
            if($_POST['FenchengSetting']['is_confirm'] != 1) {
                $model->addError('is_confirm','������ȷ������ɱ��������·���ѡ');
            }elseif($foodprice_pt < 0 ||  $foodprice_pt > 40 ) {
                $model->addError('foodprice_pt','��Ʒԭ��ƽ̨�ֳɱ���������0%-40%');
            }elseif($foodprice_sj < 60 || $foodprice_sj > 100) {
                $model->addError('foodprice_sj','��Ʒԭ���̼ҷֳɱ���������60%-100%');
            }else{
                //����ֲ��޸���ʷ��¼
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
            throw new CHttpException(404, '�����ʵ�ҳ�治���ڣ�');
        }
        //����������֤����id�Ƿ��ڸ��˺���
        $configModel = Config::model()->findByPk($shop_id);
        if($configModel->admin_id != $admin_id){
            throw new CHttpException(404, '��Ȩ�����õ��̣�');
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
                   $error[] = 'Ҫѡ����������';
               }
               if($province == '' or $province === 0 or $province === '0'){
                   $error[] = 'Ҫѡ��ʡ��';
               }
               if($city == '' or $city === 0 or $city === '0'){
                   $error[] = 'Ҫѡ�����';
               }
               if($bankname == '' or $bankname === 0 or $bankname === '0'){
                   $error[] = 'Ҫѡ�񿪻�������';
               }
               if($bankname_no == ''){
                   $error[] = '���кŲ���Ϊ��';
               }
               if($bankusername == ''){
                   $error[] = '���п���������Ϊ��';
               }

               $length = strlen($bankcard_no);
               if($bankcard_no == '' || $length < 1){
                   $error[] = '���п��Ų���Ϊ��';
               }
               for ($i=0; $i < $length; $i++) {
                   if (!is_numeric($bankcard_no[$i])) {
                       $error[] = '���п��Ų��ܷ�����';
                       break;
                   }
               }
               if($bankcard_no != $queren_bankcard_no){
                   $error[] = '���п��ź�ȷ�����п��Ų�һ��';
               }
               if(!in_array($bank_type,array(0,1))){
                   exit;
               }

               //�տ���������֤
               if ($bank_type == 0) {
                   //˽��
                   //�ж��Ƿ�������
                    if(!preg_match("/^[\x{4e00}-\x{9fa5}|\.|\��|��]+$/u",$bankusername)){
                       $error[] = "���п�����ֻ֧������";
                   }
                   //�ж���������
                    if(!preg_match("/^[\x{4e00}-\x{9fa5}|\.|\��|��]{2,30}$/u",$bankusername)){
                       $error[] = "���п���������Ϊ2���֣����Ϊ30����";
                    }
                    if (strpos($bankusername, '����') !== false || strpos($bankusername, '��˾') !== false) {
                        $error[] = "�������͵����п������ܰ������ޣ���˾�ȴ�";
                    }
               } else {
                   //�Թ�
                   //�ж��Ƿ�������
                   if(!preg_match("/^[\x{4e00}-\x{9fa5}]|&#xFF08;&#xFF09;+$/u",$bankusername)){
                       echo '���п�����ֻ֧������';
                       $error[] = "���п�����ֻ֧������";
                   }
                   //�ж���������
                   if(!preg_match("/^[\x{4e00}-\x{9fa5}]|&#xFF08;&#xFF09;{8,40}$/u",$bankusername)){
                       $error[] = "���п���������Ϊ8����";
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
                           $success[] = '���óɹ�';
                           $issubmit = true;
                       }else{
                           $error[] = '����ʧ��';
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
                           $success[] = '���óɹ�';
                           $issubmit = true;
                       }else{
                           $error[] = '����ʧ��';
                       }
                   }
                   //���·ֳ����õ����п��Ƿ���ֶ���Ϣ
                   $fenchengmodel = FenchengSetting::model()->find("admin_id = ".$admin_id." AND shop_id = ".$shop_id);
                   $fenchengmodel->is_blindcard = 1;
                   $fenchengmodel->update();
                   //���»㸶���������б�����п��Ƿ���ֶ���Ϣ
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
     * ���Ͷ�����֤��
     * method:get ����ʽ
     * tel:�ֻ���  ����
     * scene��������1���߲������������п�������֤ 2���ֻ��Ŷ�����֤�� ����
     *
     * **/
    public function actionSendCode(){
        $admin_id = Yii::app()->user->_id;
        $shop_id = $_GET['shopId'];
        $smsType = isset($_GET['smstype']) ? intval($_GET['smstype']) : 30; //smsType ��������->32:����˺����� 30:�����ڰ������ֻ��Ų��� @MaWei:2018-06-08 15:22:14
        if(!isset($shop_id) || empty($shop_id)){
            exit(json_encode(array('status'=>1,'msg'=>'�����������')));
        }
        //�ж��ֻ��Ų���Ϊ��
        if(!isset($_GET['tel']) || empty($_GET['tel'])){
            exit(json_decode(array('status'=>1,'errorMsg'=>'�ֻ���Ϊ��')));
        }else{
            $phone = $_GET['tel'];
        }
        //�ж���Ч�����Ƿ��Ѿ����͹���֤��
        $smsCacheSrv = ServiceFactory::getService(SrvType::COMMON_SMS_CACHE_SMSCACHE);
//        if(isset($_GET['scene'])&&$_GET['scene'] == 2){
//            $smsCode = $smsCacheSrv -> getShopBindPhoneVerifyCode($shop_id);
//        }else{
//            $smsCode = $smsCacheSrv -> getChangeBindBankCardVerifyCode($shop_id);
//        }
//
//        if(!empty($smsCode)){
//            exit(json_encode(array('status' => 1,'msg' => '�ѷ�����֤�뻹����Ч���ڿ��ظ���֤')));
//        }
        //�ж��̼ҵĶ��������Ƿ��㹻
        $sql = "SELECT sms_quota FROM wx_admin WHERE id = " . $admin_id . " LIMIT 1 FOR UPDATE";
        $row = LewaimaiDB::queryRow($sql);
        if(!$row){
            exit(json_encode(array('status' => 2,'msg' => '����ʧ��')));
        }
        $smsNum = $row['sms_quota'];//ʣ��Ŀɷ��Ͷ�������
        $StringQuota = 1;//��������
        if($smsNum < $StringQuota){
            exit(json_encode(array('status' => 2,'msg' => '��������')));
        }
        //��ȡ��֤��
        $verifyCode = LewaimaiString::create_randnum(6);
        //������֤��
        //$send = new \lwm\services\modules\common\sms\imps\Sms();
        $userIp = \lwm\commons\base\Helper::getUserHostIp();

       // $smsCacheSrv = ServiceFactory::getService(SrvType::COMMON_SMS_CACHE_SMSCACHE);
        //������ֻ�����֤��
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
          //������֤��
          //
            /*****@Start**����ͨ���޸�*******@MaWei:2018-06-08 14:29:00*******@Start*********/
                // $result = \lwm\services\modules\common\sms\imps\Sms::sendSmsVerifyCodeForBackend($admin_id,$phone, $verifyCode, $userIp, 7,'30����');
                $result = \lwm\services\modules\common\sms\imps\Sms::sendSmsVerifyCodeForBackend($admin_id,$phone, $verifyCode, $userIp, 7,'30����',$smsType); //��������->32:����˺����� 30:�����ڰ������ֻ��Ų���
            /*****@End****����ͨ���޸�*******@MaWei:2018-06-08 14:29:00*******@End*******/

            //$result = \lwmf\base\MessageServer::getInstance()->dispatch(\config\constants\WorkerTypes::SEND_SMS_VERIFYCODE_FOR_BACKEND,[$admin_id,$phone, $verifyCode, $userIp, 4]);
            if($result){
                //��֤�뷢�ͳɹ����۳��̼Ҷ������
                $sql = "UPDATE wx_admin SET sms_quota = sms_quota - " . $StringQuota . " WHERE id = " . $admin_id;
                $dec = LewaimaiDB::execute($sql);
                if($dec){
                    //��¼���ŷ�����ʷ
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
                    exit(json_encode(array('status' => 2,'msg' => '��֤�뷢�ͳɹ�����ע�����')));
                }
                }
            }else{

                //echo "5";
                //ɾ���������֤��ֵ
                if(isset($_GET['scene'])&&$_GET['scene'] == 2){
                    $smsCacheSrv -> deleteShopBindPhoneVerifyCode($shop_id);
                }else{
                    $smsCacheSrv->deleteChangeBindBankCardVerifyCode($shop_id);
                }
                exit(json_encode(array('status' => 1,'msg' => '��֤�뷢��ʧ��')));
            }
        }else{
            exit(json_encode(array('status' => 1,'msg' => '��֤��ʧ��')));
        }

    }

    /*
     * ������֤����֤
     *
     * scene��������1���߲������������п�������֤ 2���ֻ��Ŷ�����֤�� ����
     *
     * **/
    public function actionCheckCode(){
        $admin_id = Yii::app()->user->_id;
        $shop_id = $_GET['shopId'];
        if(!isset($_GET['code']) || empty($_GET['code'])){
            exit(json_encode(array('status' => 1,'msg' => '��������֤��')));
        }else{
            $code = $_GET['code'];
        }
        //�ӻ����л�ȡ�������֤����Ϣ
        $smsCacheSrv = ServiceFactory::getService(SrvType::COMMON_SMS_CACHE_SMSCACHE);
        if(isset($_GET['scene'])&&$_GET['scene'] == 2){
            $smsCode = $smsCacheSrv -> getShopBindPhoneVerifyCode($shop_id);
        }else{
            $smsCode = $smsCacheSrv -> getChangeBindBankCardVerifyCode($shop_id);
        }

        if(empty($smsCode)){
            exit(json_encode(array('status' => 1,'msg' => '��֤���ѹ���')));
        }
        if($code != $smsCode){
            exit(json_encode(array('status' => 1,'msg' => '��֤�����')));
        }
        exit(json_encode(array('status' => 2,'msg' => '��֤ͨ��������д������Ϣ���������')));
    }

    public function actionEditbank(){
        $admin_id = Yii::app()->user->_id;
        //�ж���Դ 1�����˻����� ���������޸����п�
        $flag = isset($_GET['flag']);
        if (isset($_GET['shop_id']))
        {
            $shop_id = addslashes($_GET['shop_id']);
        }
        else
        {
            throw new CHttpException(404, '�����ʵ�ҳ�治���ڣ�');
        }
        //����������֤����id�Ƿ��ڸ��˺���
        $configModel = Config::model()->findByPk($shop_id);
        if($configModel->admin_id != $admin_id){
            throw new CHttpException(404, '��Ȩ�����õ��̣�');
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
                   $error[] = 'Ҫѡ����������';
               }
               if($province == '' or $province === 0 or $province === '0'){
                   $error[] = 'Ҫѡ��ʡ��';
               }
               if($city == '' or $city === 0 or $city === '0'){
                   $error[] = 'Ҫѡ�����';
               }
               if($bankname == '' or $bankname === 0 or $bankname === '0'){
                   $error[] = 'Ҫѡ�񿪻�������';
               }
               if($bankname_no == ''){
                   $error[] = '���кŲ���Ϊ��';
               }
               if($bankusername == ''){
                   $error[] = '���п���������Ϊ��';
               }

               $length = strlen($bankcard_no);
               if($bankcard_no == '' || $length < 1){
                   $error[] = '���п��Ų���Ϊ��';
               }
               for ($i=0; $i < $length; $i++) {
                   if (!is_numeric($bankcard_no[$i])) {
                       $error[] = '���п��Ų��ܷ�����';
                       break;
                   }
               }
               if($bankcard_no != $queren_bankcard_no){
                   $error[] = '���п��ź�ȷ�����п��Ų�һ��';
               }
               if(!in_array($bank_type,array(0,1))) {
                   exit;
               }

               //�տ���������֤
               if ($bank_type == 0) {
                   //˽��
                   //�ж��Ƿ�������
                    if(!preg_match("/^[\x{4e00}-\x{9fa5}|\.|\��|��]+$/u",$bankusername)){
                       $error[] = "���п�����ֻ֧������";
                   }
                   //�ж���������
                    if(!preg_match("/^[\x{4e00}-\x{9fa5}|\.|\��|��]{2,30}$/u",$bankusername)){
                       $error[] = "���п���������Ϊ2���֣����Ϊ30����";
                    }
                    if (strpos($bankusername, '����') !== false || strpos($bankusername, '��˾') !== false) {
                        $error[] = "�������͵����п������ܰ������ޣ���˾�ȴ�";
                    }
               } else {
                   //�Թ�
                   //�ж��Ƿ�������
                   if(!preg_match("/^[\x{4e00}-\x{9fa5}]|&#xFF08;&#xFF09;+$/u",$bankusername)){
                       echo '���п�����ֻ֧������';
                       $error[] = "���п�����ֻ֧������";
                   }
                   //�ж���������
                   if(!preg_match("/^[\x{4e00}-\x{9fa5}]|&#xFF08;&#xFF09;{8,40}$/u",$bankusername)){
                       $error[] = "���п���������Ϊ8����";
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
                           //���·ֳ����õ����п��Ƿ���ֶ���Ϣ
                           $fenchengmodel = FenchengSetting::model()->find("admin_id = ".$admin_id." AND shop_id = ".$shop_id);
                           $fenchengmodel->is_blindcard = 1;
                           $fenchengmodel->update();

                           $huifushopapply = HuifuShopApply::model() -> find(" admin_id = ".$admin_id." AND shop_id=".$shop_id);
                           if( $huifushopapply -> status == 2 ) {
                               $shopaccount    = ShopAccount::model()    -> find(" admin_id = ".$admin_id." AND shop_id=".$shop_id);
                               $adminaccount   = AdminAccount::model()   -> find(" admin_id = ".$admin_id);
                               $huifucitycode  = HuifuCitycode::model()  -> find(' city_name= "'.$shopaccount -> city.'"');
                               $isValidata = $this -> validateData($huifushopapply,$shopaccount,$adminaccount,$huifucitycode); //������֤
                               if(!$isValidata)  $this->redirect(['huifu/shopindex']);//
                               $huiFuParams = $this -> gethuiFuParams($huifushopapply,$shopaccount,$adminaccount,$huifucitycode);
                               $huifu = new \lwm\commons\pay\channel\agent\HuiFu();
                               $res = $huifu -> bindingcard($huiFuParams);
                               LewaimaiDebug::Log("================================");
                               LewaimaiDebug::LogArray($res);
                               if($res['code'] == 400) {

                                   LewaimaiDebug::Log("���»�ȡ�㸶����ֵ");
                                   LewaimaiDebug::LogArray($res);
                                   $hsa = HuifuShopApply::model()->findByPk($huifushopapply -> id);
                                   if($res['data'] -> resp_code == 104000)  {
                                       $hsa -> huifu_cash_bind_card_id = $res['data'] -> cash_bind_card_id;//���п���ID,ȡ�ֽӿ���Ҫ�õ���ID,�ɻ㸶����
                                       $hsa -> is_blindcard = 1;//�Ƿ�����п� 0�� 1��
                                       if(!$hsa -> save()) {
                                           LewaimaiDebug::Log("���»�ȡhuifushopapply��shopaccount");
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
                           $error[] = '����ʧ��';
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
     * ���ֵ����״ΰ��ֻ���--��
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
            //��Ӳ���
            //����֤���������ֻ��ŵ�һ����
            if($param['tel'] != $param['telNew']){
                exit(json_encode(array('status'=>1,'msg'=>'�����ֻ������벻һ��')));
            }
            //��֤�ֻ��Ÿ�ʽ�Ƿ���ȷ
            $pattern = '/^1[3456789]{1}\d{9}$/';
            if(!preg_match($pattern,$param['tel'])){
                exit(json_encode(array('status'=>1,'msg'=>'�ֻ��Ÿ�ʽ��������')));
            }
            //��֤��֤���Ƿ���ȷ
            if(!isset($param['code']) || empty($param['code'])){
                exit(json_encode(array('status' => 1,'msg' => '��������֤��')));
            }

            $smsCacheSrv = ServiceFactory::getService(SrvType::COMMON_SMS_CACHE_SMSCACHE);
            $smsCode = $smsCacheSrv -> getShopBindPhoneVerifyCode($param['shopId']);

            if(empty($smsCode)){
                exit(json_encode(array('status' => 1,'msg' => '��֤���ѹ���')));
            }
            if($smsCode != $param['code']){
                exit(json_encode(array('status' => 1,'msg' => '��֤���������')));
            }
            //һ���ֻ���ֻ�ܰ��������
            $count = ShopAccount::model() -> count('admin_id=:adminID and phone=:phone',array(':adminID' => $admin_id,':phone' => $param['tel']));
            if($count >= 5){
                exit(json_encode(array('status' => 1,'msg' => '���ֻ��Ű󶨴����ѳ��ޣ�����������ֻ��Ű�')));
            }
            //�жϱ������Ƿ��Ѿ��м�¼���еĻ��޸ģ�û�Ļ����
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
                    $info['dynamic'] = '�״ΰ��ֻ��ţ��󶨵��ֻ���Ϊ��'.$param['tel'];
                    $info['act_date'] = date('Y-m-d H:i:s');

                    $service = \lwmf\services\ServiceFactory::getService(\lwm\services\SrvType::TRADE_ORDER_WITHDRAW_LOG);
                    $service->addWithdrawactlog($admin_id, $info);

                    exit(json_encode(array('status' => 2,'msg' => '����ɹ�')));
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
                    $info['dynamic'] = '�״ΰ��ֻ��ţ��󶨵��ֻ���Ϊ��'.$param['tel'];
                    $info['act_date'] = date('Y-m-d H:i:s');

                    $service = \lwmf\services\ServiceFactory::getService(\lwm\services\SrvType::TRADE_ORDER_WITHDRAW_LOG);
                    $service->addWithdrawactlog($admin_id, $info);

                    exit(json_encode(array('status' => 2,'msg' => '����ɹ�')));
                }
            }
        }else{
            //��ȡ��������
            $shopInfo = Config::model() -> findByPk($param['shopId']);
            //��û�����ù���ȡ���ҳ�棬�����Ѿ����ã���ȡ��ʾҳ��
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
     * �������̸������ֻ���--����
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
            //�����ύ�������µ��ֻ���
            //�ж���֤��
            $smsCacheSrv = ServiceFactory::getService(SrvType::COMMON_SMS_CACHE_SMSCACHE);
            $shopId = $param['tel'].'-'.$param['shopId'];
            $smsCode = $smsCacheSrv -> getShopBindPhoneVerifyCode($shopId);
            if(empty($smsCode)){
                exit(json_encode(array('status' => 1,'msg' => 'ԭ�ֻ�����֤���ѹ���')));
            }
            if($smsCode != $param['code']){
                exit(json_encode(array('status' => 1,'msg' => 'ԭ�ֻ�����֤���������')));
            }
            $shopIds = $param['telNew'].'-'.$param['shopId'];
            $smsCodeNew = $smsCacheSrv -> getShopBindPhoneVerifyCode($shopIds);
            if(empty($smsCodeNew)){
                exit(json_encode(array('status' => 1,'msg' => '���ֻ�����֤���ѹ���')));
            }
            if($smsCodeNew != $param['codeNew']){
                exit(json_encode(array('status' => 1,'msg' => '���ֻ�����֤���������')));
            }
            //��֤�ֻ��Ÿ�ʽ�Ƿ���ȷ
            $pattern = '/^1[3456789]{1}\d{9}$/';
            if(!preg_match($pattern,$param['telNew'])){
                exit(json_encode(array('status'=>1,'msg'=>'���ֻ��Ÿ�ʽ��������')));
            }
            //����������ֻ���
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
                $info['dynamic'] = '�������ֻ��ţ�ԭ�ֻ���Ϊ��'.$param['tel'].'����Ϊ'.$param['telNew'];
                $info['act_date'] = date('Y-m-d H:i:s');

                $service = \lwmf\services\ServiceFactory::getService(\lwm\services\SrvType::TRADE_ORDER_WITHDRAW_LOG);
                $service->addWithdrawactlog($admin_id, $info);

                exit(json_encode(array('status' => 2,'msg' => '����ɹ�')));
            }

        }else{
            //��ȡ��ʾ�ĸ���ҳ��
            //��ȡ��������
            $shopInfo = Config::model() -> findByPk($param['shopId']);
            $this -> render('changeshopbindphone',array(
                'shop_id' => $shopAccount -> shop_id,
                'phone' => $shopAccount -> phone,
                'shop_name' => $shopInfo -> shopname,
            ));
        }
    }

    //�޸Ķ��������п���Ϣ
    public function actionEdititembank($id){
        $admin_id = Yii::app()->user->_id;
        $model = DakuanOrderItem::model()->findByPk($id);
        if(!$model){
            throw new CHttpException(404, '�����ʵ�ҳ�治���ڣ�');
        }
        $shop_id = $model->shop_id;
        if($model->admin_id != $admin_id){
            throw new CHttpException(404, '��Ȩ�����õ��̣�');
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
                   $error[] = 'Ҫѡ����������';
               }
               if($province == '' or $province === 0 or $province === '0'){
                   $error[] = 'Ҫѡ��ʡ��';
               }
               if($city == '' or $city === 0 or $city === '0'){
                   $error[] = 'Ҫѡ�����';
               }
               if($bankname == '' or $bankname === 0 or $bankname === '0'){
                   $error[] = 'Ҫѡ�񿪻�������';
               }
               if($bankname_no == ''){
                   $error[] = '���кŲ���Ϊ��';
               }
               if($bankusername == ''){
                   $error[] = '���п���������Ϊ��';
               }

               $length = strlen($bankcard_no);
               if($bankcard_no == '' || $length < 1){
                   $error[] = '���п��Ų���Ϊ��';
               }
               for ($i=0; $i < $length; $i++) {
                   if (!is_numeric($bankcard_no[$i])) {
                       $error[] = '���п��Ų��ܷ�����';
                       break;
                   }
               }
               if($bankcard_no != $queren_bankcard_no){
                   $error[] = '���п��ź�ȷ�����п��Ų�һ��';
               }
               if(!in_array($bank_type,array(0,1))){
                   exit;
               }

               //�տ���������֤
               if ($bank_type == 0) {
                   //˽��
                   //�ж��Ƿ�������
                    if(!preg_match("/^[\x{4e00}-\x{9fa5}|\.|\��|��]+$/u",$bankusername)){
                       $error[] = "���п�����ֻ֧������";
                   }
                   //�ж���������
                    if(!preg_match("/^[\x{4e00}-\x{9fa5}|\.|\��|��]{2,30}$/u",$bankusername)){
                       $error[] = "���п���������Ϊ2���֣����Ϊ30����";
                    }
                    if (strpos($bankusername, '����') !== false || strpos($bankusername, '��˾') !== false) {
                        $error[] = "�������͵����п������ܰ������ޣ���˾�ȴ�";
                    }
               } else {
                   //�Թ�
                   //�ж��Ƿ�������
                   if(!preg_match("/^[\x{4e00}-\x{9fa5}]|&#xFF08;&#xFF09;+$/u",$bankusername)){
                       echo '���п�����ֻ֧������';
                       $error[] = "���п�����ֻ֧������";
                   }
                   //�ж���������
                   if(!preg_match("/^[\x{4e00}-\x{9fa5}]|&#xFF08;&#xFF09;{8,40}$/u",$bankusername)){
                       $error[] = "���п���������Ϊ8����";
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
                           $error[] = '����ʧ��';
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
//����Ҫ��ʾ���Բ���һ�����ĵ��̣�������ShopAccount��Ҫ��fenchengsetting
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
                //Ա���˺�
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
//����Ҫ��ʾ���Բ���һ�����ĵ��̣�������ShopAccount��Ҫ��fenchengsetting
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
            throw new CHttpException(500,'�ô�ʽ���¼ܣ�');
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
                //Ա���˺�
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

    //ȷ�ϴ��
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

    //ȷ�ϴ��
    public function actionTianxiadakuanconfirm($area_id){
        $admin_id = Yii::app()->user->_id;
        if($admin_id != 85663){
        throw new CHttpException(500,'�ô�ʽ���¼ܣ�');
        }
        if($admin_id != 85663){
            throw new CHttpException(500,'�ô�ʽ���¼ܣ�');
        }
        $adminaccountModel = AdminAccount::model()->findByPk($admin_id);
        $this->render('tianxiadakuanconfirm',array(
            'admin_id' => $admin_id,
            'area_id'  => $area_id,
            'adminaccountModel' => $adminaccountModel,
        ));

    }

    //��ʼ���д��

    public function actionTodakuan(){
        $result = array('status' =>"error" ,'message'=> "�ô�ʽ���¼ܣ�");
        echo CJSON::encode($result);
        exit();
        $admin_id = Yii::app()->user->_id;
        LewaimaiDebug::LogArray("�̼���ˢ��� ".$admin_id);
        // if($admin_id == 19){
        //     LewaimaiDebug::LogArray("һ��������ddddd");
        // }
        // if($admin_id != 19){
        //     $result = array('status' =>"error" ,'message'=> "����ά������ʱ�޷���");
        // echo CJSON::encode($result);
        // exit();
        // }
        $result = array();
        if(!isset($_POST['dakuan_id']) || !isset($_POST['dakuan_money']) || !isset($_POST['password'])){
              $result = array('status' =>"error" ,'message'=> "��������");
              echo CJSON::encode($result);
              exit();
         }
        $adminModel = Admin::model()->findByPk($admin_id);
        if($adminModel->level < 2){
              $result = array('status' =>"error" ,'message'=> "���Ļ�Ա�ȼ��������޷�����һ��������������Ա��");
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
            $result = array('status' =>"error" ,'message'=> "����ش�����ݣ������²�����");
            echo CJSON::encode($result);
            exit();
        }
        if(count($shopids) != count($dakuan_money)){
            $result = array('status' =>"error" ,'message'=> "������ݴ��ڴ��������²�����");
            echo CJSON::encode($result);
            exit();
        }
        $shopid_arr = implode(",", $shopids);
        $date = date("Y-m-d H:i:s",strtotime("-1 minute"));
        $sql = "SELECT id FROM wx_dakuan_order_item WHERE admin_id = " . $admin_id . " AND init_date >= '".$date."' AND shop_id in (".$shopid_arr.")";
        $row = LewaimaiDB::queryRow($sql);
        if($row){
            $result = array('status' =>"error" ,'message'=> "һ����������Ƶ����ͬһ�����̽��д�");
            echo CJSON::encode($result);
            exit();
        }
        $key = "fenchengsetting_dakuan".$admin_id;
        $value = "dakuan";
        $expire = 60;
        $redis = \lwmf\datalevels\Redis::getInstance()->setnx($key, $value, $expire);
        LewaimaiDebug::LogArray("redis����".$admin_id);
        LewaimaiDebug::LogArray($redis);
        if(!$redis){
            $result = array('status' =>"error" ,'message'=> "һ����������Ƶ����ͬһ�����̽��д��1��");
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
            $result = array('status' =>"error" ,'message'=> "���������������²�����");
            echo CJSON::encode($result);
            exit();
        }
        if ($retArray["errcode"] == 0)
        {
            $result = array('status' =>"success" ,'message'=> "���ɹ���");
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


    //��ʼ���д��
    public function actionTotianxiadakuan(){
//        $result = array('status' =>"error" ,'message'=> "�ù�����ʱ�޷�ʹ�ã�");
//        echo CJSON::encode($result);
//        exit();
        $admin_id = Yii::app()->user->_id;
        if($admin_id != 85663){
            throw new CHttpException(500,'�ô�ʽ���¼ܣ�');
        }
        LewaimaiDebug::LogArray("�̼�����֧����� ".$admin_id);
        // if($admin_id == 19){
        //     LewaimaiDebug::LogArray("һ��������ddddd");
        // }
        // if($admin_id != 19){
        //     $result = array('status' =>"error" ,'message'=> "����ά������ʱ�޷���");
        // echo CJSON::encode($result);
        // exit();
        // }
        $result = array();
        if(!isset($_POST['dakuan_id']) || !isset($_POST['dakuan_money']) || !isset($_POST['password'])){
            $result = array('status' =>"error" ,'message'=> "��������");
            echo CJSON::encode($result);
            exit();
        }
        $adminModel = Admin::model()->findByPk($admin_id);
        if($adminModel->level < 2){
            $result = array('status' =>"error" ,'message'=> "���Ļ�Ա�ȼ��������޷�����һ��������������Ա��");
            echo CJSON::encode($result);
            exit();
        }

        $log_string = "������־-����֧����� admin_id=".$admin_id." Ա���˺�id=".Yii::app()->user->getState('employee_id')." ������� ".json_encode($_POST);
        \lwmf\base\Logger::info($log_string);

        $shopids = $_POST['dakuan_id'];
        $dakuan_money = $_POST['dakuan_money'];
        $password = $_POST['password'];
        $area_id = intval($_POST['area_id']);
        LewaimaiDebug::LogArray($area_id);
        $memo = $_POST['memo'];
        if(count($shopids) <=0 || count($dakuan_money) <=0){
            $result = array('status' =>"error" ,'message'=> "����ش�����ݣ������²�����");
            echo CJSON::encode($result);
            exit();
        }
        if(count($shopids) != count($dakuan_money)){
            $result = array('status' =>"error" ,'message'=> "������ݴ��ڴ��������²�����");
            echo CJSON::encode($result);
            exit();
        }
        $shopid_arr = implode(",", $shopids);
        $date = date("Y-m-d H:i:s",strtotime("-1 minute"));
        $sql = "SELECT id FROM wx_dakuan_order_item WHERE admin_id = " . $admin_id . " AND init_date >= '".$date."' AND shop_id in (".$shopid_arr.")";
        $row = LewaimaiDB::queryRow($sql);
        if($row){
            $result = array('status' =>"error" ,'message'=> "һ����������Ƶ����ͬһ�����̽��д�");
            echo CJSON::encode($result);
            exit();
        }

        $dakuan_id = $shopids;
        $employee_id = Yii::app()->user->getState('employee_id');
        //�ȼ�������ʺ��Ƿ��Ѿ�����
//        $transaction = LewaimaiDB::GetTransaction();

        $transaction = \lwmf\datalevels\RdbTransaction::getInstance();
        $transaction->begin();
        try {
            $sql = "SELECT balance, password, tianxiazhifu_mchid FROM wx_admin_account WHERE admin_id = " . $admin_id . " LIMIT 1 FOR UPDATE";
            $row = LewaimaiDB::queryRow($sql);
            if (!$row)
            {
                $transaction->rollback();
                $result = array('status' =>"error" ,'message'=> "�������ʺ��˻������ڣ�");
                echo CJSON::encode($result);
                exit();
            }
            if(empty($row['tianxiazhifu_mchid'])){

                $transaction->rollback();
                $result = array('status' =>"error" ,'message'=> "����֧���˺�δ���ã�");
                echo CJSON::encode($result);
                exit();
            }
            $tianxiazhifu_mchid = $row['tianxiazhifu_mchid'];

            $account_password = $row["password"];
            //������������Ƿ���ȷ
            if ($account_password != md5($password))
            {
                $transaction->rollback();
                $result = array('status' =>"error" ,'message'=> "�����������");
                echo CJSON::encode($result);
                exit();
            }
            $key = "fenchengsetting_dakuan".$admin_id;
            $value = "dakuan";
            $expire = 60;
            $redis = \lwmf\datalevels\Redis::getInstance()->setnx($key, $value, $expire);
            LewaimaiDebug::LogArray("redis����".$admin_id);
            LewaimaiDebug::LogArray($redis);
            if(!$redis){
                $transaction->rollback();
                $result = array('status' =>"error" ,'message'=> "һ����������Ƶ����ͬһ�����̽��д��1��");
                echo CJSON::encode($result);
                exit();
            }
            //total_money����ܶ�
            $total_money = 0;
            foreach ($dakuan_money as $val){
                $val = round($val,2);
                $total_money += $val;
            }
            //������ÿ��������Ҫ����shop_idΪkey�����Ϊval
            $shop_dakuan = array();
            foreach ($dakuan_id as $key=>$val){
                $shop_dakuan[$val] = $dakuan_money[$key];
            }
            //�����ҵ���Ҫ���ĵ���
            $shop_str = implode(",", $dakuan_id);
            $sql = "SELECT id,shop_id,headbankname,bankusername,bankcard_no,bank_type,bankname,bankname_no FROM {{shop_account}} WHERE admin_id = " . $admin_id." AND shop_id in (".$shop_str.")";
            $res = Yii::app()->db->createCommand($sql)->queryAll();
            if(!$res){
                $transaction->rollback();
                $result = array('status' =>"error" ,'message'=> "����ش��������ݣ������²�����");
                echo CJSON::encode($result);
                exit();
            }
            $init_date = date("Y-m-d H:i:s");
            //�½�����
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
                $result = array('status' =>"error" ,'message'=> "���������󣬴��ʧ�ܣ�");
                echo CJSON::encode($result);
                exit();
            }
            //������order_id
            $dakuan_order_arr = array();
            //�½���������
            foreach ($res as $rs){
                $dakuan_arr = array();
                //��������Ƿ���ȷ
                if (!is_numeric($shop_dakuan[$rs['shop_id']]))
                {
                    $transaction->rollback();
                    $result = array('status' =>"error" ,'message'=> "������ʽ����");
                    echo CJSON::encode($result);
                    exit();
                }

//              if ($shop_dakuan[$rs['shop_id']] < 100)
//              {
//                  $transaction->rollback();
//                    $transaction->rollback();
//                    $result = array('status' =>"error" ,'message'=> "ÿ�δ�����С��100");
//                    echo CJSON::encode($result);
//              }
//                if ($shop_dakuan[$rs['shop_id']] < 1)
//                {
//                    $transaction->rollback();
//                    $result = array('status' =>"error" ,'message'=> "ÿ�δ�����С��1Ԫ��");
//                    echo CJSON::encode($result);
//                    exit();
//                }
                //������Ҫ������̵����ƣ���ֹ���ֵ���ɾ���Ҳ��������
                //������仯�����һ����������ǰ�˺�����Ƿ�һ�£������һ�¾�����bug���߱��˶���۸�
                $sql1 = "SELECT id,shopname FROM {{config}} where id = " . $rs['shop_id']." and is_delete=0";
                $row1 = LewaimaiDB::queryRow($sql1);
                if (!$row1)
                {
                    $transaction->rollback();
                    $result = array('status' =>"error" ,'message'=> "�Ƿ��Ĵ�������е�����ɾ����");
                    echo CJSON::encode($result);
                    exit();
                }
                //���ֶ����ţ�D��ͷ��ʾ�������˺�ƽ̨���̼Ҵ��
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
                    $result = array('status' =>"error" ,'message'=> "���������󣬴��ʧ�ܣ�");
                    echo CJSON::encode($result);
                    exit();
                }
                //������ֶ����ύ��¼
                $info = array();
                $info['admin_id'] = $admin_id;
                $info['value'] = $shop_dakuan[$rs['shop_id']];
                $info['init_date'] = $init_date;
                $info['headbankname'] = $rs['headbankname'];
                $info['bankname'] = $rs['bankname'];
                $info['bankname_no'] = $rs['bankname_no'];
                $info['bankusername'] = $rs['bankusername'];
                $info['bankcard_no'] = $rs['bankcard_no'];
                $info['admin_describe'] = "һ�����̼Ҵ��";
                //���ƽ̨��0�ٷ�����10:��ˢ12:����֧��13:˳��֧��14����������
                $info['pingtai_type'] = 12;
                $info['order_id'] = $dakuanorderitemModel->id;
                $info['out_trade_no'] = $out_trade_no;
                $info['dakuan_type'] = 2;
                $info['dakuan_mchid'] = $tianxiazhifu_mchid;
                $dakuan_res = \lwmf\services\ServiceFactory::getService(\lwm\services\SrvType::TRADE_ORDER_WITHDRAW_HISTORY)->add($admin_id,$info);

                if (!$dakuan_res)
                {
                    $transaction->rollback();
                    $result = array('status' =>"error" ,'message'=> "����������3��");
                    echo CJSON::encode($result);
                    exit();
                }
                //������ʵ���Կ��ǰ�$dakuanorderitemModelֱ�ӷ���$dakuan_order_id���棬�����Ͳ����ٺ���������ˣ����ǿ���������ݽ϶��п��ܰ�����ű�������ֱ�ӷ�id
                $dakuan_arr['order_id'] = $dakuanorderitemModel->id;
                $dakuan_arr['out_trade_no'] = $out_trade_no;
                $dakuan_arr['value'] = $shop_dakuan[$rs['shop_id']];
                $dakuan_arr['bankusername'] = $rs['bankusername'];
                $dakuan_arr['bankname_no'] = $rs['bankname_no'];
                $dakuan_arr['bankcard_no'] = $rs['bankcard_no'];
                $dakuan_arr['bank_type'] = $rs['bank_type'];
                $dakuan_arr['des'] = "һ�����̼Ҵ��";
                array_push($dakuan_order_arr, $dakuan_arr);
            }

            //�����Ҫ�������ύ��Ȼ���ٵ�����ˢ�Ľӿڣ���Ȼ�Ļ��п��ܵ��ýӿ��Ѿ��ɹ������Ѿ�����˿��ˣ����ǽӿڳ�ʱû�з��أ������������»ع����ͻᵼ�¿ͻ��װ��յ�Ǯ����������ļ�¼һ��Ҫ�ȴ������Ű�ȫ��ǧ�������ύ�ӿں󻹻ع�
            $transaction->commit();

        } catch (Exception $e) {
            $transaction->rollback();
            $result = array('status' =>"error" ,'message'=> "����������");
            echo CJSON::encode($result);
            exit();
        }
        LewaimaiDebug::LogArray("ʱ�����");
        LewaimaiDebug::LogArray(date("Y-m-d H:i:s"));
        if(count($dakuan_order_arr) > 0){
            LewaimaiDebug::LogArray($dakuan_order_arr);
            if(count($dakuan_order_arr) > 100){
                //����100����Ҫ��ҳ����Ȼ�������Ϣ���й��󣬳������
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
//                //���ÿ�ʼ�����Ľӿ�
//                $params = new \lwm\commons\pay\channel\AgentSinglePayParam();
//                $params->outTradeNo = $val['out_trade_no'];
//                $params->amount = $val['value'];
//                $params->transTime = $timestamp;
//                $params->payType = \lwm\commons\pay\channel\AgentSinglePayParam::PAY_TYPE_BALANCE;
//                if($val['bank_type'] == 0){
//                    //˽���˻�
//                    $params->bankAccountType = \lwm\commons\pay\channel\AgentSinglePayParam::BANK_ACCOUNT_TYPE_DEBIT_CARD;
//
//                }elseif($val['bank_type'] == 1){
//                    //�Թ��˻�
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
//                        //�����ʾ�Ѿ����ɹ����Ѷ���״̬����Ϊ�ɹ�״̬
//                        $info = array();
//                        $info['status'] = 1;
//                        $info['complete_date'] = date("Y-m-d H:i:s");
//                        $dakuanitemSrv->updateById($admin_id, $val['order_id'], $info);
//                    }elseif ($res['status'] == \lwm\commons\pay\channel\IAgentChannel::PAY_STATUS_FAIL){
//                        //����ʧ�ܵĶ����������У��Բ�ѯ�ӿڷ��ص�״̬Ϊ׼
//
////                        $info = array();
////                        $info['status'] = 2;
////                        $info['complete_date'] = date("Y-m-d H:i:s");
////                        $info['memo'] = $res['errmsg'];
////                        $dakuanitemSrv->updateById($admin_id, $val['order_id'], $info);
//                    }
//
//                }
//                LewaimaiDebug::LogArray("�Ƿ�������1");
//                LewaimaiDebug::LogArray($res);
//            }
        }
        LewaimaiDebug::LogArray(date("Y-m-d H:i:s"));
        $result = array('status' =>"success" ,'message'=> "��������ύ�ɹ���");
        echo CJSON::encode($result);
        exit();
    }

    //��ʼ����΢�Ŵ��
    public function actionTowechatdakuan(){
//        $result = array('status' =>"error" ,'message'=> "�ù�����ʱ�޷�ʹ�ã�");
//        echo CJSON::encode($result);
//        exit();
        $admin_id = Yii::app()->user->_id;
        LewaimaiDebug::LogArray("�̼�΢�Ŵ�� ".$admin_id);
        // if($admin_id == 19){
        //     LewaimaiDebug::LogArray("һ��������ddddd");
        // }
        // if($admin_id != 19){
        //     $result = array('status' =>"error" ,'message'=> "����ά������ʱ�޷���");
        // echo CJSON::encode($result);
        // exit();
        // }
        $result = array();
        if(!isset($_POST['dakuan_id']) || !isset($_POST['dakuan_money']) || !isset($_POST['password'])){
            $result = array('status' =>"error" ,'message'=> "��������");
            echo CJSON::encode($result);
            exit();
        }
        $adminModel = Admin::model()->findByPk($admin_id);
        if($adminModel->level < 2){
            $result = array('status' =>"error" ,'message'=> "���Ļ�Ա�ȼ��������޷�����һ��������������Ա��");
            echo CJSON::encode($result);
            exit();
        }


        $log_string = "������־-΢�Źٷ���� admin_id=".$admin_id." Ա���˺�id=".Yii::app()->user->getState('employee_id')." ������� ".json_encode($_POST);
        \lwmf\base\Logger::info($log_string);

        $shopids = $_POST['dakuan_id'];
        $dakuan_money = $_POST['dakuan_money'];
        $password = $_POST['password'];
        $area_id = intval($_POST['area_id']);
        LewaimaiDebug::LogArray($area_id);
        $memo = $_POST['memo'];
        if(count($shopids) <=0 || count($dakuan_money) <=0){
            $result = array('status' =>"error" ,'message'=> "����ش�����ݣ������²�����");
            echo CJSON::encode($result);
            exit();
        }
        if(count($shopids) != count($dakuan_money)){
            $result = array('status' =>"error" ,'message'=> "������ݴ��ڴ��������²�����");
            echo CJSON::encode($result);
            exit();
        }
        $shopid_arr = implode(",", $shopids);
        $date = date("Y-m-d H:i:s",strtotime("-5 minute"));
        $sql = "SELECT id FROM wx_dakuan_order_item WHERE admin_id = " . $admin_id . " AND init_date >= '".$date."' AND shop_id in (".$shopid_arr.")";
        $row = LewaimaiDB::queryRow($sql);
        if($row){
            $result = array('status' =>"error" ,'message'=> "�����������Ƶ����ͬһ�����̽��д�");
            echo CJSON::encode($result);
            exit();
        }

        $dakuan_id = $shopids;
        $employee_id = Yii::app()->user->getState('employee_id');
        //�ȼ�������ʺ��Ƿ��Ѿ�����
//        $transaction = LewaimaiDB::GetTransaction();

        $transaction = \lwmf\datalevels\RdbTransaction::getInstance();
        $transaction->begin();
        try {

            $sql = "SELECT balance, password, dakuan_type FROM wx_admin_account WHERE admin_id = " . $admin_id . " LIMIT 1 FOR UPDATE";
            $row = LewaimaiDB::queryRow($sql);
            if (!$row)
            {
                $transaction->rollback();
                $result = array('status' =>"error" ,'message'=> "�������ʺ��˻������ڣ�");
                echo CJSON::encode($result);
                exit();
            }
            $account_password = $row["password"];
            //������������Ƿ���ȷ
            if ($account_password != md5($password))
            {
                $transaction->rollback();
                $result = array('status' =>"error" ,'message'=> "�����������");
                echo CJSON::encode($result);
                exit();
            }\lwmf\base\Logger::info($row);
            if($row["dakuan_type"] == 2){
                //΢�Ŵ����п�
                $pingtai_type = 15;
            }elseif($row["dakuan_type"] == 3){
                //΢�Ŵ���Ǯ
                $pingtai_type = 16;
            }else{
                $transaction->rollback();
                $result = array('status' =>"error" ,'message'=> "��ʽ�����޷����д�");
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
                $result = array('status' =>"error" ,'message'=> "΢���˻�δ���û򲻴��ڣ�");
                echo CJSON::encode($result);
                exit();
            }
            if(empty($row['mchid'])){

                $transaction->rollback();
                $result = array('status' =>"error" ,'message'=> "΢�Ŵ���˺�δ���ã�");
                echo CJSON::encode($result);
                exit();
            }
            $mchid = $row['mchid'];
            //total_money����ܶ�
            $total_money = 0;
            foreach ($dakuan_money as $val){
                $val = round($val,2);
                $total_money += $val;
            }
            //������ÿ��������Ҫ����shop_idΪkey�����Ϊval
            $shop_dakuan = array();
            foreach ($dakuan_id as $key=>$val){
                $shop_dakuan[$val] = $dakuan_money[$key];
            }
            //�����ҵ���Ҫ���ĵ���
            $shop_str = implode(",", $dakuan_id);
            $sql = "SELECT id,shop_id,headbankname,bankusername,bankcard_no,bank_type,bankname,bankname_no,openid,wechat_name FROM {{shop_account}} WHERE admin_id = " . $admin_id." AND shop_id in (".$shop_str.")";
            $res = Yii::app()->db->createCommand($sql)->queryAll();
            if(!$res){
                $transaction->rollback();
                $result = array('status' =>"error" ,'message'=> "����ش��������ݣ������²�����");
                echo CJSON::encode($result);
                exit();
            }
            //����redis������
           /* $key = "fenchengsetting_dakuan".$admin_id;
            $value = "dakuan";
            $expire = 300;
            $redis = \lwmf\datalevels\Redis::getInstance()->setnx($key, $value, $expire);
            LewaimaiDebug::LogArray("redis����".$admin_id);
            LewaimaiDebug::LogArray($redis);
            if(!$redis){
                $transaction->rollback();
                $result = array('status' =>"error" ,'message'=> "�����������Ƶ����ͬһ�����̽��д��1��");
                echo CJSON::encode($result);
                exit();
            }*/
            $init_date = date("Y-m-d H:i:s");
            //�½�����
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
                $result = array('status' =>"error" ,'message'=> "���������󣬴��ʧ�ܣ�");
                echo CJSON::encode($result);
                exit();
            }
            //������order_id
            $dakuan_order_arr = array();
            //�½���������
            foreach ($res as $rs){
                $dakuan_arr = array();
                //��������Ƿ���ȷ
                if (!is_numeric($shop_dakuan[$rs['shop_id']]))
                {
                    $transaction->rollback();
                    $result = array('status' =>"error" ,'message'=> "������ʽ����");
                    echo CJSON::encode($result);
                    exit();
                }

//              if ($shop_dakuan[$rs['shop_id']] < 100)
//              {
//                  $transaction->rollback();
//                    $transaction->rollback();
//                    $result = array('status' =>"error" ,'message'=> "ÿ�δ�����С��100");
//                    echo CJSON::encode($result);
//              }
//                if ($shop_dakuan[$rs['shop_id']] < 1)
//                {
//                    $transaction->rollback();
//                    $result = array('status' =>"error" ,'message'=> "ÿ�δ�����С��1Ԫ��");
//                    echo CJSON::encode($result);
//                    exit();
//                }
                //������Ҫ������̵����ƣ���ֹ���ֵ���ɾ���Ҳ��������
                $sql1 = "SELECT id,shopname FROM {{config}} where id = " . $rs['shop_id'].' and is_delete=0';
                $row1 = LewaimaiDB::queryRow($sql1);
                if (!$row1)
                {
                    $transaction->rollback();
                    $result = array('status' =>"error" ,'message'=> "�Ƿ��Ĵ�������е�����ɾ����");
                    echo CJSON::encode($result);
                    exit();
                }
                //���ֶ����ţ�33��ʾ�����п�44��ʾ����Ǯ
                if($pingtai_type == 15){
                if($rs['headbankname'] == "ũ����������"){
                    $transaction->rollback();
                    $result = array('status' =>"error" ,'message'=> "΢�Ŵ�֧��ũ���������翨��");
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
                        $result = array('status' =>"error" ,'message'=> "�������󣬴��ʧ�ܣ�");
                        echo CJSON::encode($result);
                        exit();
                    }
                    $out_trade_no = "44" . LewaimaiString::GetUniqueTradeNo(20);
                    $dakuanorderitemModel = new DakuanOrderItem();
                    $dakuanorderitemModel->order_id = $dakuanorderModel->id;
                    $dakuanorderitemModel->admin_id = $admin_id;
                    $dakuanorderitemModel->shop_id = $rs['shop_id'];
                    $dakuanorderitemModel->shopname = $row1['shopname'];
                    $dakuanorderitemModel->headbankname = "��";
                    //����Ǯʱ������ֶα�ʾ�û�΢���˺���ʵ����
                    $dakuanorderitemModel->bankusername = $rs['wechat_name'];
                    $dakuanorderitemModel->bankcard_no = "��";
                    $dakuanorderitemModel->bankname = "��";
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
                    $result = array('status' =>"error" ,'message'=> "���������󣬴��ʧ�ܣ�");
                    echo CJSON::encode($result);
                    exit();
                }
                \lwmf\base\Logger::info($out_trade_no);
                //������ֶ����ύ��¼
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
                $info['admin_describe'] = "һ�����̼Ҵ��";
                //���ƽ̨��0�ٷ�����10:��ˢ12:����֧��13:˳��֧��14����������15��΢�Źٷ������п�16΢�Źٷ�����Ǯ
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
                    $result = array('status' =>"error" ,'message'=> "����������3��");
                    echo CJSON::encode($result);
                    exit();
                }
                //������ʵ���Կ��ǰ�$dakuanorderitemModelֱ�ӷ���$dakuan_order_id���棬�����Ͳ����ٺ���������ˣ����ǿ���������ݽ϶��п��ܰ�����ű�������ֱ�ӷ�id
                $dakuan_arr['order_id'] = $dakuanorderitemModel->id;
                $dakuan_arr['out_trade_no'] = $out_trade_no;
                $dakuan_arr['value'] = $shop_dakuan[$info['shop_id']];
                $dakuan_arr['headbankname'] = $info['headbankname'];
                $dakuan_arr['bankusername'] = $info['bankusername'];
                $dakuan_arr['bankname_no'] = $info['bankname_no'];
                $dakuan_arr['bankcard_no'] = $info['bankcard_no'];
                $dakuan_arr['bank_type'] = $rs['bank_type'];
                $dakuan_arr['openId'] = $info['openid'];
                $dakuan_arr['des'] = "һ�����̼Ҵ��";
                array_push($dakuan_order_arr, $dakuan_arr);
            }

            //�����Ҫ�������ύ��Ȼ���ٵ�����ˢ�Ľӿڣ���Ȼ�Ļ��п��ܵ��ýӿ��Ѿ��ɹ������Ѿ�����˿��ˣ����ǽӿڳ�ʱû�з��أ������������»ع����ͻᵼ�¿ͻ��װ��յ�Ǯ����������ļ�¼һ��Ҫ�ȴ������Ű�ȫ��ǧ�������ύ�ӿں󻹻ع�
            $transaction->commit();

        } catch (Exception $e) {
            LewaimaiDebug::LogArray($e);
            $transaction->rollback();
            $result = array('status' =>"error" ,'message'=> "����������111��");
            echo CJSON::encode($result);
            exit();
        }
        if(count($dakuan_order_arr) > 0){

            LewaimaiDebug::LogArray($dakuan_order_arr);
            if(count($dakuan_order_arr) > 100){
                //����100����Ҫ��ҳ����Ȼ�������Ϣ���й��󣬳������
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
        $result = array('status' =>"success" ,'message'=> "��������ύ�ɹ���");
        echo CJSON::encode($result);
        exit();
    }

    //�����ʷ����
    public function actionDakuanorder(){
        $admin_id = Yii::app()->user->_id;
        $model = new DakuanOrder();
        $model->unsetAttributes();
        $model->admin_id = $admin_id;
        $this->render('dakuanorder',array(
            'model' => $model
        ));

    }

    //�����ʷ��������
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

    //���´��
    public function actionRestardakuan(){
        $admin_id = Yii::app()->user->_id;
        LewaimaiDebug::LogArray("�������¸��̼Ҵ�� ".$admin_id);
//        if($admin_id != 19){
//            $result = array('status' =>"error" ,'message'=> "��ʱ�޷���");
//            echo CJSON::encode($result);
//            exit();
//
//        }
        $result = array();
        if(!isset($_POST['item_id']) || !isset($_POST['item_id'])){
              $result = array('status' =>"error" ,'message'=> "��������");
              echo CJSON::encode($result);
              exit();
         }
        $item_id = $_POST['item_id'];
        //$type = 15 ��ʾ΢�Źٷ���0��ʾ����֧������ˢ
        $type = $_POST['pingtai_type'];
        $dakuanitemSrv = \lwmf\services\ServiceFactory::getService(\lwm\services\SrvType::TRADE_ORDER_DAKUAN_ITEM);
        $dakuanitem = $dakuanitemSrv->getInfoById($admin_id, $item_id,"", true);

        if(!$dakuanitem){
            $result = array('status' =>"error" ,'message'=> "�ü�¼�����ڣ��޷����´�");
            echo CJSON::encode($result);
            exit();
        }
        if($dakuanitem['pingtai_type'] == 12){
//            $result = array('status' =>"error" ,'message'=> "�ù�����ʱ�޷�ʹ�ã�");
//            echo CJSON::encode($result);
//            exit();
            $transaction = \lwmf\datalevels\RdbTransaction::getInstance();
            $transaction->begin();

            try {
                //����֧��
                if ($dakuanitem['status'] != 2) {
                    $transaction->rollback();
                    $result = array('status' =>"error" ,'message'=> "״̬�����޷����´�");
                    echo CJSON::encode($result);
                    exit();
                }
                //������Ҫ�������ɶ�����״̬
                //���ֶ����ţ�D��ͷ��ʾ�������˺�ƽ̨���̼Ҵ��
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
                    $result = array('status' =>"error" ,'message'=> "�������������´��ʧ�ܣ�");
                    echo CJSON::encode($result);
                    exit();
                }

                //������ֶ����ύ��¼
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
                $info['admin_describe'] = "���¸��̼Ҵ��";
                //���ƽ̨��0�ٷ�����10:��ˢ12:����֧��13:˳��֧��14����������
                $info['pingtai_type'] = 12;
                $info['order_id'] = $dakuanitem['id'];
                $info['out_trade_no'] = $out_trade_no;
                $info['dakuan_type'] = 2;
                $dakuan_res = \lwmf\services\ServiceFactory::getService(\lwm\services\SrvType::TRADE_ORDER_WITHDRAW_HISTORY)->add($admin_id, $info);

                if (!$dakuan_res) {
                    LewaimaiDebug::LogArray("��������¼ʧ��");
                    $transaction->rollback();
                    $transaction->rollback();
                    $result = array('status' =>"error" ,'message'=> "�������������´��ʧ�ܣ�");
                    echo CJSON::encode($result);
                    exit();
                }


                $transaction->commit();
            } catch (Exception $e) {
                $transaction->rollback();
                $result = array('status' =>"error" ,'message'=> "���������������²�����");
                echo CJSON::encode($result);
                exit();
            }
            //�����������ύһ�δ��
            $config = \lwmf\services\ServiceFactory::getService(\lwm\services\SrvType::TRADE_PAY_CONFIG)->getTianxiaConfig($admin_id,\lwm\commons\pay\BisTypeDef::SHANGHU_TIANXIA_AGENT,0);
            LewaimaiDebug::LogArray($config);
            $payChannel = \lwm\commons\pay\PayFactory::getInstance()->getAgentChannel($config);
            $timestamp = time();
                //���ÿ�ʼ�����Ľӿ�
                $params = new \lwm\commons\pay\channel\AgentSinglePayParam();
                $params->outTradeNo = $info['out_trade_no'];
                $params->amount = $info['value'];
                $params->transTime = $timestamp;
                $params->payType = \lwm\commons\pay\channel\AgentSinglePayParam::PAY_TYPE_BALANCE;
                if($info['bank_type'] == 0){
                    //˽���˻�
                    $params->bankAccountType = \lwm\commons\pay\channel\AgentSinglePayParam::BANK_ACCOUNT_TYPE_DEBIT_CARD;

                }elseif($info['bank_type'] == 1){
                    //�Թ��˻�
                    $params->bankAccountType = \lwm\commons\pay\channel\AgentSinglePayParam::BANK_ACCOUNT_TYPE_PUBLIC;
                    $params->bankSettleNo = $info['bankname_no'];
                }
                $params->bankUserName =  $info['bankusername'];
                $params->bankNo = $info['bankcard_no'];
                $params->memo = "���´��";
                $res = $payChannel->singlePay($params);
                if($res && isset($res['status'])){
                    if($res['status'] == \lwm\commons\pay\channel\IAgentChannel::PAY_STATUS_SUCCESS)
                    {
                        //�����ʾ�Ѿ����ɹ����Ѷ���״̬����Ϊ�ɹ�״̬
                        $info = array();
                        $info['status'] = 1;
                        $info['complete_date'] = date("Y-m-d H:i:s");
                        \lwmf\services\ServiceFactory::getService(\lwm\services\SrvType::TRADE_ORDER_DAKUAN_ITEM)->updateById($admin_id, $item_id, $info);
                    }elseif ($res['status'] == \lwm\commons\pay\channel\IAgentChannel::PAY_STATUS_FAIL){
                        //�����ʾ���ʧ�ܣ��Ѷ������ó�ʧ��״̬
                        //����ʧ�ܵĶ����������У��Բ�ѯ�ӿڷ��ص�״̬Ϊ׼
//                        $info = array();
//                        $info['status'] = 2;
//                        $info['complete_date'] = date("Y-m-d H:i:s");
//                        $info['memo'] = $res['errmsg'];
//                        \lwmf\services\ServiceFactory::getService(\lwm\services\SrvType::TRADE_ORDER_DAKUAN_ITEM)->updateById($admin_id, $item_id, $info);
                    }

                }
                LewaimaiDebug::LogArray("�Ƿ�������11111");
                LewaimaiDebug::LogArray($res);

            $result = array('status' =>"success" ,'message'=> "���´��ɹ���");
            echo CJSON::encode($result);
            exit();

        }elseif ($dakuanitem['pingtai_type'] == 10){
            //��ˢ

            $paramArray = array();

            $paramArray["item_id"] = $item_id;

            $retArray = LewaimaiRequestApi::Send("/withdraw/redakuan", $paramArray);
            LewaimaiDebug::LogArray($retArray);
            if (!$retArray)
            {
                $result = array('status' =>"error" ,'message'=> "���������������²�����");
                echo CJSON::encode($result);
                exit();
            }
            if ($retArray["errcode"] == 0)
            {
                $result = array('status' =>"success" ,'message'=> "���ɹ���");
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
                    //΢�Źٷ����
                    if ($dakuanitem['status'] != 2) {
                        $transaction->rollback();
                        $result = array('status' =>"error" ,'message'=> "״̬�����޷����´�");
                        echo CJSON::encode($result);
                        exit();
                    }
                    //������Ҫ�������ɶ�����״̬
                    //���ֶ����ţ�D��ͷ��ʾ�������˺�ƽ̨���̼Ҵ��
                    $out_trade_no = "D" . LewaimaiString::GetUniqueTradeNo(20);
                    //΢�����´��ֱ����ԭ�����ŷ��𣬲����������ɣ���״̬ΪFAILʱ������ҵ����δ��ȷ��������������״̬yΪFAIL�������ͨ����ѯ�ӿ�ȷ�ϴ˴θ���Ľ������ע������err_code�ֶΣ������Ҫ����������ʸ���������ԭ�̻������ź�ԭ����������˽ӿڡ�
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
                        $result = array('status' =>"error" ,'message'=> "�������������´��ʧ�ܣ�");
                        echo CJSON::encode($result);
                        exit();
                    }

                    //������ֶ����ύ��¼
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
                    $info['admin_describe'] = "���¸��̼Ҵ��";
                    //���ƽ̨��0�ٷ�����10:��ˢ12:����֧��13:˳��֧��14����������15΢�Źٷ�
                    $info['pingtai_type'] = 15;
                    $info['order_id'] = $dakuanitem['id'];
                    $info['out_trade_no'] = $out_trade_no;
                    $info['dakuan_type'] = 2;
                    $info['dakuan_mchid'] = $dakuanitem['dakuan_mchid'];
                    $dakuan_res = \lwmf\services\ServiceFactory::getService(\lwm\services\SrvType::TRADE_ORDER_WITHDRAW_HISTORY)->add($admin_id, $info);

                    if (!$dakuan_res) {
                        LewaimaiDebug::LogArray("��������¼ʧ��");
                        $transaction->rollback();
                        $transaction->rollback();
                        $result = array('status' =>"error" ,'message'=> "�������������´��ʧ�ܣ�");
                        echo CJSON::encode($result);
                        exit();
                    }


                    //������ʵ���Կ��ǰ�$dakuanorderitemModelֱ�ӷ���$dakuan_order_id���棬�����Ͳ����ٺ���������ˣ����ǿ���������ݽ϶��п��ܰ�����ű�������ֱ�ӷ�id
                    $dakuan_arr['order_id'] = $dakuanitem['id'];
                    $dakuan_arr['out_trade_no'] = $out_trade_no;
                    $dakuan_arr['value'] = $dakuanitem['money'];
                    $dakuan_arr['headbankname'] = $dakuanitem['headbankname'];
                    $dakuan_arr['bankusername'] = $dakuanitem['bankusername'];
                    $dakuan_arr['bankname_no'] = $dakuanitem['bankname_no'];
                    $dakuan_arr['bankcard_no'] = $dakuanitem['bankcard_no'];
                    $dakuan_arr['des'] = "���¸��̼Ҵ��";
                    array_push($dakuan_order_arr, $dakuan_arr);


                    $transaction->commit();
                } catch (Exception $e) {
                    $transaction->rollback();
                    $result = array('status' =>"error" ,'message'=> "���������������²�����");
                    echo CJSON::encode($result);
                    exit();
                }
                //�����������ύһ�δ��
                if(count($dakuan_order_arr) > 0){

                    LewaimaiDebug::LogArray($dakuan_order_arr);
    //            \lwmf\base\MessageServer::getInstance()->dispatch(\config\constants\WorkerTypes::MERCHANT_SETTING_WECHAT, array($admin_id,$dakuan_order_arr));
                    \lwmf\services\ServiceFactory::getService(\lwm\services\SrvType::COMMON_MERCHANT_SETTINGS_WXDKACCOUNT)->setdakuan($admin_id,$dakuan_order_arr);
                }

                $result = array('status' =>"success" ,'message'=> "���´������ɹ���");
                echo CJSON::encode($result);
                exit();

            }elseif($dakuanitem['pingtai_type'] == 16){
            $dakuan_order_arr = array();
            $transaction = \lwmf\datalevels\RdbTransaction::getInstance();
            $transaction->begin();

            try {
                //΢�Źٷ����
                if ($dakuanitem['status'] != 2) {
                    $transaction->rollback();
                    $result = array('status' =>"error" ,'message'=> "״̬�����޷����´�");
                    echo CJSON::encode($result);
                    exit();
                }
                //������Ҫ�������ɶ�����״̬
                //���ֶ����ţ�D��ͷ��ʾ�������˺�ƽ̨���̼Ҵ��
                $out_trade_no = "D" . LewaimaiString::GetUniqueTradeNo(20);
                //΢�����´��ֱ����ԭ�����ŷ��𣬲����������ɣ���״̬ΪFAILʱ������ҵ����δ��ȷ��������������״̬yΪFAIL�������ͨ����ѯ�ӿ�ȷ�ϴ˴θ���Ľ������ע������err_code�ֶΣ������Ҫ����������ʸ���������ԭ�̻������ź�ԭ����������˽ӿڡ�
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
                    $result = array('status' =>"error" ,'message'=> "�������������´��ʧ�ܣ�");
                    echo CJSON::encode($result);
                    exit();
                }

                //������ֶ����ύ��¼
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
                $info['admin_describe'] = "���¸��̼Ҵ��";
                $info['openid'] = $dakuanitem['openid'];
                //���ƽ̨��0�ٷ�����10:��ˢ12:����֧��13:˳��֧��14����������15΢�Źٷ�
                $info['pingtai_type'] = 15;
                $info['order_id'] = $dakuanitem['id'];
                $info['out_trade_no'] = $out_trade_no;
                $info['dakuan_type'] = 2;
                $info['dakuan_mchid'] = $dakuanitem['dakuan_mchid'];
                $dakuan_res = \lwmf\services\ServiceFactory::getService(\lwm\services\SrvType::TRADE_ORDER_WITHDRAW_HISTORY)->add($admin_id, $info);

                if (!$dakuan_res) {
                    LewaimaiDebug::LogArray("��������¼ʧ��");
                    $transaction->rollback();
                    $transaction->rollback();
                    $result = array('status' =>"error" ,'message'=> "�������������´��ʧ�ܣ�");
                    echo CJSON::encode($result);
                    exit();
                }


                //������ʵ���Կ��ǰ�$dakuanorderitemModelֱ�ӷ���$dakuan_order_id���棬�����Ͳ����ٺ���������ˣ����ǿ���������ݽ϶��п��ܰ�����ű�������ֱ�ӷ�id
                $dakuan_arr['order_id'] = $dakuanitem['id'];
                $dakuan_arr['out_trade_no'] = $out_trade_no;
                $dakuan_arr['value'] = $dakuanitem['money'];
                $dakuan_arr['headbankname'] = $dakuanitem['headbankname'];
                $dakuan_arr['bankusername'] = $dakuanitem['bankusername'];
                $dakuan_arr['bankname_no'] = $dakuanitem['bankname_no'];
                $dakuan_arr['bankcard_no'] = $dakuanitem['bankcard_no'];
                $dakuan_arr['des'] = "���¸��̼Ҵ��";
                $dakuan_arr['openId'] = $dakuanitem['openid'];
                array_push($dakuan_order_arr, $dakuan_arr);


                $transaction->commit();
            } catch (Exception $e) {
                $transaction->rollback();
                $result = array('status' =>"error" ,'message'=> "���������������²�����");
                echo CJSON::encode($result);
                exit();
            }
            //�����������ύһ�δ��
            if(count($dakuan_order_arr) > 0){

                LewaimaiDebug::LogArray($dakuan_order_arr);
                    \lwmf\services\ServiceFactory::getService(\lwm\services\SrvType::COMMON_MERCHANT_SETTINGS_WXDKACCOUNT)->setWechatMoneydakuan($admin_id,$dakuan_order_arr);
            }

            $result = array('status' =>"success" ,'message'=> "���´������ɹ���");
            echo CJSON::encode($result);
            exit();
        }
    }

    public function loadModel($id)
    {
        $model=FenchengSetting::model()->findByPk($id);
        if($model->admin_id != yii::app() -> user -> _id){
            throw new CHttpException(404,'����Ȩ����');
        }
        if($model===null)
            throw new CHttpException(404,'The requested page does not exist.');
        return $model;
    }

    public function fenchengHandle($data){
        $id = $data->id;
        $str = '<a href='.Yii::app()->createUrl('fenchengsetting/update', array('id'=>$data->id)).' title="�޸�" class="green">';
        $str .= '<i class="ace-icon fa fa-pencil bigger-130"></i>';
        $str .= '</a>';
        $str .= '<a href='.Yii::app()->createUrl('fenchengsetting/shopbank', array('shop_id'=>$data->shop_id)).' title="�޸ĵ������п���Ϣ" style="padding-right:8px;margin-left:5px;">';
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
            echo "���̲����ڻ���ɾ��";
        }
    }

    private function getmyshopname($shop_id){
        $sql = "SELECT id,shopname FROM {{config}} WHERE id = " . $shop_id;
        $res = Yii::app()->db->createCommand($sql)->queryRow();
        if($res){
            return $res['shopname'];
        }else{
            return "���̲����ڻ���ɾ��";
        }
    }

    public function getdelivery($data){
        $str = "�̼ң�".$data->delivery_sj."%<br/>";
        $str .= "ƽ̨��".$data->delivery_pt."%";
        echo $str;
    }
    public function getdabao($data){
        $str = "�̼ң�".$data->dabao_sj."%<br/>";
        $str .= "ƽ̨��".$data->dabao_pt."%";
        echo $str;
    }
    public function getaddservice($data){
        $str = "�̼ң�".$data->addservice_sj."%<br/>";
        $str .= "ƽ̨��".$data->addservice_pt."%";
        echo $str;
    }
    public function getorderfield($data){
        $str = "�̼ң�".$data->order_field_fee_sj."%<br/>";
        $str .= "ƽ̨��".$data->order_field_fee_pt."%";
        echo $str;
    }
    public function getfoodprice($data){
        $str = "�̼ң�".$data->foodprice_sj."%<br/>";
        $str .= "ƽ̨��".$data->foodprice_pt."%";
        echo $str;
    }
    public function getdiscount($data){
        $str = "�̼ң�".$data->discount_sj."%<br/>";
        $str .= "ƽ̨��".$data->discount_pt."%";
        echo $str;
    }
    public function getpromotion($data){
        $str = "�̼ң�".$data->promotion_sj."%<br/>";
        $str .= "ƽ̨��".$data->promotion_pt."%";
        echo $str;
    }
    public function getmember($data){
        $str = "�̼ң�".$data->member_sj."%<br/>";
        $str .= "ƽ̨��".$data->member_pt."%";
        echo $str;
    }
    public function getcoupon($data){
        $str = "�̼ң�".$data->coupon_sj."%<br/>";
        $str .= "ƽ̨��".$data->coupon_pt."%";
        echo $str;
    }
    public function getfirstdiscount($data){
        $str = "�̼ң�".$data->firstdiscount_sj."%<br/>";
        $str .= "ƽ̨��".$data->firstdiscount_pt."%";
        echo $str;
    }
    public function isoffline($data){
        if($data->is_deduct_offline){
            echo "<span style='color:#6CBC4E'>��</span>";
        }else{
            echo "<span style='color:#DA4F4A'>��</span>";
        }
    }
    public function istodakuan($data){
        if($data->is_todakuan){
            echo "<span style='color:#6CBC4E'>��</span>";
        }else{
            echo "<span style='color:#DA4F4A'>��</span>";
        }
    }
    public function isblindcard($data){
        if($data->is_blindcard){
            echo "<span style='color:#6CBC4E'>��</span>";
        }else{
            echo "<span style='color:#DA4F4A'>��</span>";
        }
    }
    public function getshopnames($data){
        $sql = "SELECT id,shopname FROM {{config}} WHERE id = " . $data->shop_id;
        $res = Yii::app()->db->createCommand($sql)->queryRow();
        if($res){
            echo $res['shopname'];
        }else{
            echo "���̲����ڻ���ɾ��";
        }
    }
    public function getfencheng($data){
        echo '<input type="text" class="fenchengmoney" id="shopid_'.$data->shop_id.'" value="0" />';
    }
    public function is_blindcard($data){
        if($data->is_blindcard){
            echo "<span style='color:#6CBC4E'>��</span><input type='hidden' value='".$data->is_blindcard."' id='isblindcard".$data->shop_id."' />";
        }else{
            echo "<span style='color:#DA4F4A'>��</span><input type='hidden' value='".$data->is_blindcard."' id='isblindcard".$data->shop_id."' />";
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

    //��ȡ�����������
    public function actionGetdakuaninfo(){
        $admin_id = Yii::app()->user->_id;
        LewaimaiDebug::LogArray("���԰�������");
        LewaimaiDebug::LogArray($_POST);
        if(!isset($_POST['dakuan_id']) || !isset($_POST['dakuan_money'])){
            $result = array('status' =>"error" ,'message'=> "����ش�����ݣ������²�����");
            echo CJSON::encode($result);
            exit();
        }
        $shop_id = $_POST['dakuan_id'];
        $dakuan_money = $_POST['dakuan_money'];
        if(count($shop_id) <=0 || count($dakuan_money) <=0){
            $result = array('status' =>"error" ,'message'=> "����ش�����ݣ������²�����");
            echo CJSON::encode($result);
            exit();
        }
        if(count($shop_id) != count($dakuan_money)){
            $result = array('status' =>"error" ,'message'=> "������ݴ��ڴ��������²�����");
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
            $result = array('status' =>"error" ,'message'=> "����ش��������ݣ������²�����");
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

    //��ȡ΢�Ŵ���Ǯ���������
    public function actionGetwechatdakuaninfo(){
        $admin_id = Yii::app()->user->_id;
        LewaimaiDebug::LogArray("���԰�������");
        LewaimaiDebug::LogArray($_POST);
        if(!isset($_POST['dakuan_id']) || !isset($_POST['dakuan_money'])){
            $result = array('status' =>"error" ,'message'=> "����ش�����ݣ������²�����");
            echo CJSON::encode($result);
            exit();
        }
        $shop_id = $_POST['dakuan_id'];
        $dakuan_money = $_POST['dakuan_money'];
        if(count($shop_id) <=0 || count($dakuan_money) <=0){
            $result = array('status' =>"error" ,'message'=> "����ش�����ݣ������²�����");
            echo CJSON::encode($result);
            exit();
        }
        if(count($shop_id) != count($dakuan_money)){
            $result = array('status' =>"error" ,'message'=> "������ݴ��ڴ��������²�����");
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
            $result = array('status' =>"error" ,'message'=> "����ش��������ݣ������²�����");
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
            $string .= '<tr class="odd"><td>'.$shopmodel['shopname'].'</td><td>'.$shop_dakuan[$rs['shop_id']].'</td><td>΢����Ǯ</td><td>'.$rs['wechat_name'].'</td></tr>';
        }
        $result = array('status' =>"success" ,'message'=> $string);
        echo CJSON::encode($result);
        exit();
    }

    /**
     * ��������¼
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
                    $res['message'] = '�������ݣ�����ʧ��';
                    echo json_encode($res);exit();
                }

                if($numArr[0]['num'] > 10000){

                    $res['status'] = 'error';
                    $res['message'] = 'ÿ�ε����ļ�¼���ܴ���10000��������';
                    echo json_encode($res);exit;
                }

                $data = $connection->createCommand($sql)->queryAll();


                //��ʼ����
                ob_end_clean();
                ob_start();
                /* PHPExcel */
                require_once 'PHPExcel.php';
                $objPHPExcel = new PHPExcel();
                $objPHPExcel->getProperties()->setCreator("lewaimai")
                                             ->setLastModifiedBy("lewaimai")
                                             ->setTitle("�����������ʷ��¼")
                                             ->setSubject("�����������ʷ��¼")
                                             ->setDescription("�����������ʷ��¼")
                                             ->setKeywords("������")
                                             ->setCategory("������");
                    // ����һ���µĹ�����
                    $objPHPExcel->createSheet();
                    $objPHPExcel->setActiveSheetIndex(0);
                    $objActSheet = $objPHPExcel->getActiveSheet();
                    $objActSheet->setTitle('�����������ʷ��¼');

                    $objActSheet->getDefaultStyle()->getAlignment()->setWrapText(true);
                    $objActSheet->getDefaultStyle()->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_TOP);
                    $objActSheet->getDefaultStyle()->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_LEFT);
                    //���ø��еı���
                    $objActSheet->setCellValue('A1', '����ʱ��')
                                ->setCellValue('B1', '��������')
                                ->setCellValue('C1', '���')
                                ->setCellValue('D1', '����˺�')
                                ->setCellValue('E1', '������')
                                ->setCellValue('F1', '״̬');
                    $objActSheet->getStyle('A1:F1')->getFont()->setName('����');
                    $objActSheet->getStyle('A1:F1')->getFont()->setSize(12);
                    $objActSheet->getStyle('A1:F1')->getFont()->setBold(true);
                    //���ø��еĿ��
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

                // Redirect output to a client��s web browser (Excel5)
                $filename = "�����������ʷ��¼.xls";
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
                // ����������ɵ�excel�ļ�������zip����ַ
                $last_dir = dirname($file_path).'/';
                $zip_name = $last_dir.$dir_name.'.zip';
                addFileToZip($zip_name,$file_arr);
                // �ϴ��ļ���cdn,�����ļ���ַ
                if($cdn_path = LewaimaiCDN::uploadTempCvs($zip_name)){
                    echo json_encode(array('status'=>'success','file_path'=>$cdn_path));
                }else{
                    // Failed upload file to cdn!
                    echo json_encode(array('notice_msg'=>'����ʧ�ܣ���ǰʱ���û�ж������� '));
                }
                // ɾ���ļ����Լ��ļ����µ��ļ�,ɾ��ѹ����
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
            echo "���˺�";
        }else{
           $admin_id = Yii::app()->user->_id;
           $employeeModel = EmployeesAccount::model()->findByPk($data->employee_id);
           if(!$employeeModel){
               echo "Ա���˺Ų����ڻ���ɾ��";
           }else{
               $employeename = "Ա���˺ţ�".$employeeModel->account;
               echo $employeename;
           }
        }
    }

    public function getpingtaitype($data)
    {
        $pingtai_type = '';
        if($data->pingtai_type == 10) {
            $pingtai_type = '��ˢ';
        }elseif($data->pingtai_type == 12) {
            $pingtai_type = '����֧�����츶����';
        }elseif($data->pingtai_type == 13) {
            $pingtai_type = '˳��֧��';
        }elseif($data->pingtai_type == 14) {
            $pingtai_type = '��������';
        }elseif($data->pingtai_type == 15) {
            $pingtai_type = '΢�Ŵ����п�';
        }elseif($data->pingtai_type == 16) {
            $pingtai_type = '΢�Ŵ���Ǯ';
        }
        return $pingtai_type;
    }

    private function getmyemployee($employee_id){
        if($employee_id == 0){
            return "���˺�";
        }else{
           $admin_id = Yii::app()->user->_id;
           $employeeModel = EmployeesAccount::model()->findByPk($employee_id);
           if(!$employeeModel){
               return "Ա���˺Ų����ڻ���ɾ��";
           }else{
               $employeename = "Ա���˺ţ�".$employeeModel->account;
               return $employeename;
           }
        }
    }

    protected function gethandle($data)
    {
        $url = $data->id;
        echo CHtml::Link('<i class="ace-icon fa fa-search bigger-130"></i>', array("fenchengsetting/dakuanitem","id"=>$data->id,"pingtai_type"=>$data->pingtai_type), array('title'=>'�鿴����','class'=>'green','style'=>'padding-right:1px;'));
    }

    protected function getdakuanitem($data)
    {
        if($data->headbankname == "��"){
            $data->headbankname = "";
        }
        if($data->bankcard_no == "��"){
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
            echo "<span style='color:#3399CC;font-weight:bold;'>������</span>";
        }else if($data->status == 1){
            echo "<span style='color:#339933;font-weight:bold;'>�ɹ�</span>";
        }else if($data->status == 2){
            if($data->memo){
                echo "<span style='color:#CC3300;font-weight:bold;'>ʧ�ܣ�".$data->memo."��</span>";
            }else{
                echo "<span style='color:#CC3300;font-weight:bold;'>ʧ��</span>";
            }
        }
    }

    protected function getType($data)
    {
        if($data->pingtai_type == 10){
            return "��ˢ";
        }else if($data->pingtai_type == 12){
            return "����֧��";
        }else if($data->pingtai_type == 15){
            return "΢�Ŵ���";
        }else if($data->pingtai_type == 16){
            return "΢����Ǯ����";
        }else{
            return "����";
        }
    }

    protected function getmystatus($status, $memo)
    {
        if($status == 0){
            return "������";
        }else if($status == 1){
            return "�ɹ�";
        }else if($status == 2){
            if($memo){
                return "ʧ�ܣ�".$memo."��";
            }else{
                return "ʧ��";
            }
        }
    }

    protected function getitmehandle($data)
    {
        if($data->status == 2){
            if($data->pingtai_type == 16){
                echo CHtml::Link('<a class="label label-sm label-warning" onclick="redakuan('.$data->id.')">���´��</a>')."&nbsp;&nbsp;&nbsp;".CHtml::Link('<a class="label label-sm label-info" href="'.Yii::app()->createUrl("fenchengsetting/editwithdrawwechatname",array("id"=>$data->id)).'">�޸�΢���˻���Ϣ</a>');
            }else{
            echo CHtml::Link('<a class="label label-sm label-warning" onclick="redakuan('.$data->id.')">���´��</a>')."&nbsp;&nbsp;&nbsp;".CHtml::Link('<a class="label label-sm label-info" href="'.Yii::app()->createUrl("fenchengsetting/edititembank",array("id"=>$data->id)).'">�޸����п���Ϣ</a>');
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

        $successNum = 0;        //���ɹ�
        $doingNum = 0;          //������
        $failNum = 0;           //���ʧ��
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
            $res .= '�ɹ�&nbsp;&nbsp;&nbsp;&nbsp;��'. $successNum .'��<br>';
        }

        if(!empty($doingNum))
        {
            $res .= '�����С�'. $doingNum .'��<br>';
        }

        if(!empty($failNum))
        {
            $res .= '<span style="color:red;">ʧ��&nbsp;&nbsp;&nbsp;&nbsp;��'. $failNum .'��</span>';
        }

        return $res;
    }

    /**
     * ȫ�������б�
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
        $areaName = '��';
        if(!empty($data->area_id)){
            $areaModel = Area::model()->findByPk($data->area_id);
            if($areaModel){
                $areaName = $areaModel->area_name;
            }
        }

        return $areaName;
    }

//����΢�Ŵ�����
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

    //�����̼Ҳ����������
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
     * �����̼����ֵ��ж�
     *
     */
    public function actionJudgewithdraw()
    {
        $admin_id = Yii::app()->user->_id;
        $act = $_POST['act'];

       /* if($act == 'has_open') {
            //�Ƿ�֧ͨ���˻�
            $sql = 'select count(*) as count from {{tianxia_apply}} where admin_id=:admin_id and tx_status=2';
            $count = Yii::app()->db->createCommand($sql)->queryRow(true,array(':admin_id'=>$admin_id))['count'];
            echo $count;
        }elseif($act == 'has_setting') {
            //�Ƿ�������
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
             * ���жϿ����������ֵ������Ƿ�߱�
             * **/
            //1�Ƿ��Ѿ����ù���ʽ
            $adminAccount = AdminAccount::model() -> findByPk($admin_id);
            if($adminAccount -> dakuan_type == 0){
                exit(json_encode(array('status' => 1,'msg' => 'δ���ô�ʽ���ݲ���ʹ���̼����ֹ���')));
            }
            //2���µĵ����Ƿ�ȫ�������ù��������
            //��ȡadmin_id�˺��µ����е�����Ϣ(shop_id,shopname)
            //�Ȼ�ȡadmin_id����δ���÷ֳ����õ�shop_id
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

                //��ȡû�����ù��ֳ����õ���������
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
                //ȫ�����ù��������
                //��ѯ�����˺ŵ����е���
                $sql = "select id from {{config}} where admin_id={$admin_id} and shopstatus='OPEN' and is_delete=0";
                $result1 = Yii::app()->db->createCommand($sql)->queryAll();
                //��ѯ�����˺ŵĵ��̰��˺ŵ����
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
                        //exit(json_encode(array('status' => 4,'msg' => '�е���û�а��˻����ܿ����̼���������')));
                    }
                }
                exit(json_encode(array('status' => 2,'msg' => '��������')));
            }else{
                //��������ؿ�������������
                exit(json_encode(array('status' => 3,'msg' => '���е���û������������ã����Ƚ�������','data' => $shopNameArray)));
            }
        }elseif($act == 'open_withdraw') {
            //��ͨ��������
            $sql = 'update {{admin_account}} set is_open_withdraw=1 where admin_id=:admin_id';
            Yii::app()->db->createCommand($sql)->execute(array(':admin_id'=>$admin_id));
            echo true;
        }
    }

    /*
     * ��ҳ��ȡ
     *
     * **/
    public function actionGetNoSetFenchengShop(){
        $page = $_GET['page'];
        $admin_id = Yii::app()->user->_id;
        //2.1��ȡadmin_id�˺��µ����е�����Ϣ(shop_id,shopname)
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
            //��ȡû�����ù��ֳ����õ���������
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
            exit(json_encode(array('status' => 1,'msg' => 'û��������')));
        }
        exit(json_encode(array('status' => 2,'data' => $shopNameArray)));
    }

    /**
     * �̼�����
     */
    public function actionWithdraw()
    {
        $model = new StatisticsForm('courier');
        $model->area_id = '-1';
        $admin_id = Yii::app()->user->_id;

        $shopids = LewaimaiEmployee::CheckAccount();

        //ȫ������
        if($shopids === false){
            $shop_id_array = array();
        } else {
            $shop_id_array = $shopids;
        }


        $sql = "SELECT c.`shopname`, c.`admin_id`, c.`id` as `shop_id`, a.`balance`, a.`is_freeze`, ea.`account` FROM `wx_config` as c LEFT OUTER JOIN `wx_shop_account` as a on a.`shop_id`= c.`id` LEFT JOIN `wx_employees_account` as ea on a.`employee_id`= ea.`id` WHERE c.`admin_id`= {$admin_id}";
        //��ȡ������Ϣ
        if (!empty($shop_id_array)) {
            $sql .= ' and c.`id` in ('.implode(',', $shop_id_array).')';
        }
        $sql .= ' and c.is_delete = 0';
        $sql .= " order by balance desc";
        $datas = Yii::app()->db->createCommand($sql)->queryAll();

        //�������˺Ų�ѯ
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
//            //�̼�Ӧ���ܽ��
//            $sellerTotal = 0;
//            //ƽ̨Ӧ�ý��
//            $terraceTotal = 0;
//            //�ܽ��
//            $salesTotal = 0;
            //�����ֽ��
            $balance = 0;
            //���ִ���
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
                    'shopName' => '�ܼ�',
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
        //��ѯ�Ƿ��д���˵����ּ�¼
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





    //�˻�����
    public function actionShopAccountManagement()
    {
        //���˺�ҳ��
        $admin_id = Yii::app()->user->_id;

        if (isset($_GET['shop_id'])) {
            $shop_id = addslashes($_GET['shop_id']);
        } else {
            throw new CHttpException(404, '�����ʵ�ҳ�治���ڣ�');
        }

        //�Ƿ����̼�����
        $adminAccount = AdminAccount::model()->findByPk($admin_id);
        //����������֤����id�Ƿ��ڸ��˺���
        $configModel = Config::model()->findByPk($shop_id);
        if ($configModel->admin_id != $admin_id) {
            throw new CHttpException(404, '��Ȩ�����õ��̣�');
        }


        //���Ϊ���½�һ����¼
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
            //��ȡδͣ�õ��˺���Ϣ(��½�˺����ڷ����µ��˺���Ϣ)
            if(Yii::app()->user->getState('usertype') == 1){
                //Ա���˺�
                $employeeModel = EmployeesAccount::model()->findByPk(Yii::app()->user->getState('employee_id'));
                if ($employeeModel){
                    if ($employeeModel->role_type == 3){
                        // ȫƽ̨����Ա��ʾ����Ա���˺�
                        $sql = 'select id,account,shop_ids from {{employees_account}} where admin_id='.$admin_id.' and role_type=1 and status=1';
                    }
                    elseif ($employeeModel->role_type == 2)
                        // ��������Ա��ʾ�÷����µ��˺�
                        $sql = 'select id,account,shop_ids from {{employees_account}} where admin_id='.$admin_id.' and role_type=1 and status=1 and area_id ='.$employeeModel->area_id;
                }
                elseif ($employeeModel->role_type == 5){
                    // Ⱥ�����Ա��ʾ��Ⱥ���µ��˺�
                    $sql = 'select id,account,shop_ids from {{employees_account}} where admin_id='.$admin_id.' and role_type=1 and status=1 and group_id ='.$employeeModel->group_id;
                }
            }else{
                //���˺���ʾ����Ա���˺�
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
                        //�ó�ѡ�е��˺�
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

                //�ж��Ƿ��з���õ��̵�Ȩ��
                $sql = 'select shop_ids from {{employees_account}} where id=:id';
                $shop_ids = Yii::app()->db->createCommand($sql)->queryRow(true,array(':id'=>$_POST['account']))['shop_ids'];
                if(empty($shop_ids)) {
                    $error[] = 'û�з���õ��̵�Ȩ��';
                }
                $shop_ids_arr = explode(',',$shop_ids);
                if(in_array($shop_id,$shop_ids_arr)) {
                }else{
                    $error[] = 'û�з���õ��̵�Ȩ��';
                }

                //���п���ϢҪô���� Ҫôȫ����д����
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
                        $error[] = '�����д���п���Ϣ������д������';
                    }
                    if ($headbankname == '' or $headbankname === 0 or $headbankname === '0') {
                        $error[] = 'Ҫѡ����������';
                    }
                    if ($province == '' or $province === 0 or $province === '0') {
                        $error[] = 'Ҫѡ��ʡ��';
                    }
                    if ($city == '' or $city === 0 or $city === '0') {
                        $error[] = 'Ҫѡ�����';
                    }
                    if ($bankname == '' or $bankname === 0 or $bankname === '0') {
                        $error[] = 'Ҫѡ�񿪻�������';
                    }
                    if ($bankname_no == '') {
                        $error[] = '���кŲ���Ϊ��';
                    }
                    if ($bankusername == '') {
                        $error[] = '���п���������Ϊ��';
                    }
                    if ($bankcard_no == '' || $length < 1) {
                        $error[] = '���п��Ų���Ϊ��';
                    }
                    for ($i = 0; $i < $length; $i++) {
                        if (!is_numeric($bankcard_no[$i])) {
                            $error[] = '���п��Ų��ܷ�����';
                            break;
                        }
                    }
                    if ($bankcard_no != $queren_bankcard_no) {
                        $error[] = '���п��ź�ȷ�����п��Ų�һ��';
                    }
                    if (!in_array($bank_type, array(0, 1))) {
                        exit;
                    }
                    //�տ���������֤
                    if ($bank_type == 0) {
                        if($headbankname !== '' && $headbankname !== 0 or $headbankname !== '0') {
                            //˽��
                            //�ж��Ƿ�������
                            if (!preg_match("/^[\x{4e00}-\x{9fa5}|\.|\��|��]+$/u", $bankusername)) {
                                $error[] = "���п�����ֻ֧������";
                            }
                            //�ж���������
                            if (!preg_match("/^[\x{4e00}-\x{9fa5}|\.|\��|��]{2,30}$/u", $bankusername)) {
                                $error[] = "���п���������Ϊ2���֣����Ϊ30����";
                            }
                            if (strpos($bankusername, '����') !== false || strpos($bankusername, '��˾') !== false) {
                                $error[] = "�������͵����п������ܰ������ޣ���˾�ȴ�";
                            }
                        }
                    } else {
                        if($headbankname !== '' && $headbankname !== 0 or $headbankname !== '0') {
                            //�Թ�
                            //�ж��Ƿ�������
                            if (!preg_match("/^[\x{4e00}-\x{9fa5}]|&#xFF08;&#xFF09;+$/u", $bankusername)) {
                                echo '���п�����ֻ֧������';
                                $error[] = "���п�����ֻ֧������";
                            }
                            //�ж���������
                            if (!preg_match("/^[\x{4e00}-\x{9fa5}]|&#xFF08;&#xFF09;{8,40}$/u", $bankusername)) {
                                $error[] = "���п���������Ϊ8����";
                            }
                        }
                    }
                }

                //�����̼������ж��ֻ��������˺�
               // if($adminAccount->is_open_withdraw==1) {
                //��Ӳ���
                //��֤�ֻ��Ÿ�ʽ�Ƿ���ȷ
                $pattern = '/^1[3456789]{1}\d{9}$/';
                if(!preg_match($pattern,$_POST['tel'])){
                    $error[] = "�ֻ��Ÿ�ʽ��������";
                }

                $codes = implode("", $_POST['code']);
                //��֤��֤���Ƿ���ȷ
                if(empty($codes)){
                    $error[] = "��������֤��";
                }

                $smsCacheSrv = ServiceFactory::getService(SrvType::COMMON_SMS_CACHE_SMSCACHE);
                $smsCode = $smsCacheSrv->getChangeBindBankCardVerifyCode($shop_id);

                if(empty($smsCode) || empty($codes) || $smsCode != $codes){
                    $error[] = "��֤���������";
                }

                //һ���ֻ���ֻ�ܰ��������
                $count = ShopAccount::model()->count('admin_id=:adminID and phone=:phone',array(':adminID' => $admin_id,':phone' => $_POST['tel']));
                if($count >= 5) {
                    $error[] = "���ֻ��Ű󶨴����ѳ��ޣ�����������ֻ��Ű�";
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
                        //�����̼������ж��ֻ��������˺�
                       // if($adminAccount->is_open_withdraw==1) {
                        $model->employee_id = $_POST['account'];
                        $model->phone = $_POST['tel'];
                       // }
                        $phone_type1=0;//����ֻ��Ƿ��״ΰ�
                        $employee_type1=0;//����˺��Ƿ��״ΰ�
                        //�����ؼ�¼
                        $m = ShopAccount::model()->find("admin_id =" . $admin_id . " AND shop_id =" . $shop_id);
                        $_phone = $m->phone;//ԭ�绰
                        $_employee_id = $m->employee_id;//ԭ�˺�
                        if (empty($_phone) && !empty($model->phone)) {
                            $phone_type1 = 1;//����ֻ��״ΰ�
                        }
                        if (($_employee_id == 0 || empty($_employee_id)) && !empty($model->employee_id)) {
                            $employee_type1 = 1;//����˺��״ΰ�
                        }
                        if (!empty($_phone) && !empty($model->phone) && $_phone !== $model->phone) {
                            $phone_type1 = 2;//����ֻ�����
                        }
                        if ($_employee_id != 0 && !empty($model->employee_id) && $_employee_id !== $model->employee_id) {
                            $employee_type1 = 2;//����˺Ż���
                        }
                        //echo"<pre>";  echo  $phone_type1,"</br>";  echo $employee_type1,"</br>";   print_r($_POST);   var_dump($issubmit);    exit;
                        if ($model->save()) {
                            $success[] = '���óɹ�';
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
                                $info['dynamic'] = '�״ΰ��ֻ��ţ��󶨵��ֻ���Ϊ��'.$_POST['tel'];
                                $service->addWithdrawactlog($admin_id, $info);
                            }else if($phone_type1==2) {
                                $info['act_type'] = 2;
                                $info['dynamic'] = '�������ֻ��ţ�ԭ�ֻ���Ϊ��'.$_phone.'����Ϊ'.$_POST['tel'];
                                $service->addWithdrawactlog($admin_id, $info);
                            }
                            if($employee_type1==1) {
                                $sql = 'select account from {{employees_account}} where admin_id=' . $admin_id . ' and id=' . $model->employee_id;
                                $demployees_name = Yii::app()->db->createCommand($sql)->queryRow()['account'];
                                $info['act_type'] = 3;
                                $info['dynamic'] = '�״ΰ��˺ţ��󶨵��˺�Ϊ��'.$admin_name.':'.$demployees_name;
                                $service->addWithdrawactlog($admin_id, $info);
                            }else if($employee_type1==2) {
                                $sql1 = 'select account from {{employees_account}} where admin_id=' . $admin_id . ' and id=' . $_employee_id;
                                $sql2 = 'select account from {{employees_account}} where admin_id=' . $admin_id . ' and id=' . $model->employee_id;
                                $demployees_name1 = Yii::app()->db->createCommand($sql1)->queryRow()['account'];
                                $demployees_name2 = Yii::app()->db->createCommand($sql2)->queryRow()['account'];
                                $info['act_type'] = 3;
                                $info['dynamic'] = '�������˺ţ�ԭ�˺�Ϊ'. $admin_name.':'.$demployees_name1 .'����Ϊ'.$admin_name.':'.$demployees_name2;
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
                                //���·ֳ����õ����п��Ƿ���ֶ���Ϣ
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
                                $isValidata = $this -> validateData($huifushopapply,$shopaccount,$adminaccount,$huifucitycode); //������֤
                                if(!$isValidata)  $this->redirect(['huifu/shopindex']);
                                $huiFuParams = $this -> gethuiFuParams($huifushopapply,$shopaccount,$adminaccount,$huifucitycode);
                                $huifu = new \lwm\commons\pay\channel\agent\HuiFu();
                                $res = $huifu -> bindingcard($huiFuParams);
                                LewaimaiDebug::Log("================================");
                                LewaimaiDebug::LogArray($res);
                                if($res['code'] == 400) {

                                    LewaimaiDebug::Log("��ȡ�㸶����ֵ");
                                    LewaimaiDebug::LogArray($res);
                                    $hsa = HuifuShopApply::model()->findByPk($huifushopapply -> id);
                                    if($res['data'] -> resp_code == 104000)  {
                                        $hsa -> huifu_cash_bind_card_id = $res['data'] -> cash_bind_card_id;//���п���ID,ȡ�ֽӿ���Ҫ�õ���ID,�ɻ㸶����
                                        $hsa -> is_blindcard = 1;//�Ƿ�����п� 0�� 1��
                                        if(!$hsa -> save()) {
                                            LewaimaiDebug::Log("��ȡhuifushopapply��shopaccount");
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
                            $error[] = '����ʧ��';
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
                            $success[] = '���óɹ�';
                            $issubmit = true;
                        } else {
                            $error[] = '����ʧ��';
                        }
                    }
                    //���·ֳ����õ����п��Ƿ���ֶ���Ϣ
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


    //��װhuiFuParams
    public function gethuiFuParams($huifushopapply,$shopaccount,$adminaccount,$huifucitycode) {
        if(!$huifushopapply || !$shopaccount || !$adminaccount || !$huifucitycode) {
            exit(json_encode(array('status'=>0,'msg'=>'ϵͳ����')));
        }

        $bankname_no  =  $this -> getBankId($shopaccount -> headbankname);
        $out_trade_no = "BD".\lwm\commons\base\Helper::getUniqueTradeNo(18);
        $huiFuParams  = new \lwm\commons\pay\channel\HuiFuParams();
        $huiFuParams -> pfx_url      = $adminaccount -> huifu_pfx_url;//�㸶����pfx�ļ�·��
        $huiFuParams -> password     = $adminaccount -> huifu_pfx_password;//�㸶����pfx�ļ�����
        $huiFuParams -> version      = 10;//�̶�Ϊ10����汾����������ǰ����
        $huiFuParams -> cmd_id       = "104";//��Ϣ����
        $huiFuParams -> mer_cust_id  = $huifushopapply -> huifu_merchant_id; //�̻���Ψһ��ʶ
        $huiFuParams -> user_cust_id = $huifushopapply -> huifu_shop_mchid; //�ɻ㸶���ɣ��û���Ψһ�Ա�ʶ
        $huiFuParams -> order_date   = date("Ymd",time()); //����ʱ��
        $huiFuParams -> order_id     = $out_trade_no; //������
        $huiFuParams -> bank_id      = $bankname_no; //���д���
        $huiFuParams -> dc_flag      = '0'; //������
        $huiFuParams -> card_no      = $shopaccount -> bankcard_no; //���п���
        $huiFuParams -> card_prov    = $huifucitycode -> province_code; //���п�����ʡ��
        $huiFuParams -> card_area    = $huifucitycode -> city_code; //���п���������
        $huiFuParams -> mer_priv     = ''; //��ѡ	Ϊ�̻����Զ����ֶΣ����ֶ��ڽ�����ɺ��ɱ�ƽ̨ԭ������
        $huiFuParams -> extension    = ''; //��ѡ	������չ�������

        return $huiFuParams;
    }


    public function validateData($huifushopapply,$shopaccount,$adminaccount,$huifucitycode) {
        LewaimaiDebug::Log("��ȡhuifushopapply��shopaccount");
        LewaimaiDebug::LogArray($huifushopapply);
        LewaimaiDebug::LogArray($shopaccount);
        LewaimaiDebug::Log("************��ȡadminaccount��huifucitycode��ֵ**************");
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


    //��ȡ��Ӧ���д���
    public function getBankId($name) {

        $bank_name_value = [
            '��ҵ����'           => '03090000',
            '��������'	        => '03040000',
            '��������'	        => '03130011',
            '��������'           => '03080000',
            '�й���������'       => '01020000',
            '�й���������'       => '01050000',
            '�й�ũҵ����'	    => '01030000',
            '�������'	        => '03030000',
            '����ũ����ҵ����'   => '04020011',
            '�й�����'	        => '01040000',
            '�й�������������'	=> '04030000',
            '�Ͼ�����'	        => '03133201',
            '��������'	        => '03133301',
            '��������'	        => '03160000',
            '�Ϻ�����'	        => '03130031',
            '��������'	        => '03180000',
            '�Ϻ�ũ����ҵ����'	=> '04020031',
            '�㶫��չ����'	    => '03060000',
            '��������'	        => '03050000',
            '�ֶ���չ����'	    => '03100000',
            'ƽ������'	        => '03134402',
            '�㽭��̩��ҵ����'   => '03133307',
            '�㽭̩¡��ҵ����'   => '',
            '���ڷ�չ����'	    => '03070000',
            '��������'	        => '03020000',
            '��ͨ����'	        => '03010000',
        ];
        return $bank_name_value["$name"];
    }


    //���ֵ���
    public function actionExportwithdraw(){
        $beginDate = $_GET['beginDate'];
        $endDate = $_GET['endDate'];
        $area_id = $_GET['area_id'];
        $admin_id = Yii::app()->user->_id;
        $time = strtotime($beginDate)-strtotime($endDate);
        $time = abs($time);
        $days = round(($time)/3600/24);
        if($days > 31){
            echo json_encode(array('notice_msg'=>'���ֻ�ܵ���31���������Ϣ'));
            exit();
        }

        $shopids = LewaimaiEmployee::CheckAccount();

        //ȫ������
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

        //��ȡ������Ϣ
        $sql = 'SELECT id, shopname from {{config}} where admin_id = '.$admin_id.' and is_delete=0';
        if (!empty($shop_id_array)) {
            $sql .= ' and id in ('.implode(',', $shop_id_array).')';
        }
        $shopDataArray = Yii::app()->db->createCommand($sql)->queryAll();
        $tempArray = array();
        foreach ($shopDataArray as $key => $value) {
            $tempArray[$value['shopname']] = $value['id'];
        }

        //��ȡ����
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

        //�������˺Ų�ѯ
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
            //�Ѷ���ĵ���
            if($v1['is_freeze'] == 1) {
                $freeze_employee[] = $v1['shop_id'];
            }
        }

        $array_new = array();
        if(!empty($array)) {

            //�����ֽ�������ִ�����ͳ��
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

                //�����Ѷ��������
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
            exit(json_encode(array('notice_msg'=>'�������ݣ�����ʧ��')));
        }
        //��ʼ����
        ob_end_clean();
        ob_start();

        $dir_name = $admin_id.date('YmdHis',time());
        $file_path = Yii::app()->getBasePath().'/data/cvs/'.$dir_name;

        //���ø��еı���
        $head = array('��������', '�̼�Ӧ�ý��', 'ƽ̨Ӧ�ý��', '�ܽ��', '�����ֽ��', '���ִ���', '�������˺�');

        //��������Ϊcsv����
        $shopname = preg_replace('/[\\\*\/\:\?\"\<\>\|\[\]]/','','�������');
        $filename = $shopname.'.csv';
        $filename = $file_path.'/'.$filename;

        // ��ͷ���ͽŲ�ѹ������
        array_unshift($data, $head);
        LewaimaiFile::write_cvs($data,$filename);
        $file_arr[] = $filename;

        $zip=new ZipArchive();
        $last_dir = dirname($file_path).'/';
        $zip_name = $last_dir.$dir_name.'.zip';
        addFileToZip($zip_name,$file_arr);
        // �ϴ��ļ���cdn,�����ļ���ַ
        if($cdn_path = LewaimaiCDN::uploadTempCvs($zip_name)){
            echo json_encode(array('success'=>1,'file_path'=>$cdn_path));
        }else{
            // Failed upload file to cdn!
            echo json_encode(array('notice_msg'=>'����ʧ�ܣ���ǰʱ���û��ͳ������ '));
        }
        // ɾ���ļ����Լ��ļ����µ��ļ�,ɾ��ѹ����
        deldir($file_path);
        @unlink($zip_name);
        exit();
    }

    /**
     * ������˻��ж��Ƿ���ֻ���
     */
    public function actionJudgehasphone()
    {
        $admin_id = Yii::app()->user->_id;
        $shop_id = intval($_POST['shop_id']);
        if(empty($shop_id)) {
            echo json_encode(array('errno'=>'99991','msg'=>'��������'));exit;
        }
        $sql = 'select phone from {{shop_account}} where admin_id='.$admin_id.' and shop_id=:shop_id';
        $phone = Yii::app()->db->createCommand($sql)->queryRow(true,array(':shop_id'=>$shop_id))['phone'];
        if(!empty($phone)) {
            echo json_encode(array('errno'=>'0','msg'=>'success','shop_id'=>$shop_id));exit;
        }else{
            echo json_encode(array('errno'=>'99992','msg'=>'δ���ֻ���'));exit;
        }
    }

    /**
     * ���ֶ����˺�
     */
    public function actionWithdrawfreeze()
    {
        $admin_id = Yii::app()->user->_id;
        $shop_id = intval($_POST['shop_id']);
        $sql = 'select id from {{shop_account}} where shop_id='.$shop_id;
        $shop_data = Yii::app()->db->createCommand($sql)->queryRow();
        if(!empty($shop_data)) {
            //����
            $sql = 'update {{shop_account}} set is_freeze=1 where id='.$shop_data['id'];
        }else{
            //���
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
            $info['dynamic'] = '���ֶ���';
            $info['act_date'] = date('Y-m-d H:i:s');

            $service = \lwmf\services\ServiceFactory::getService(\lwm\services\SrvType::TRADE_ORDER_WITHDRAW_LOG);
            $service->addWithdrawactlog($admin_id, $info);
            exit(json_encode(array('errno'=>'0','msg'=>'����ɹ�')));
        }

    }


    protected function getShopUrl($data){
        $str = "<div class='shopurl' style='background:#82AF6F;text-align:center;color:white;cursor:pointer;width:60px;' onclick='ShopurlShow(".$data->shop_id.",".$data->admin_id.")'>�鿴<input class='ids' type='hidden' value='".$data->shop_id."'><input class='aids' type='hidden' value='".$data->admin_id."'></div>";
        return $str;
    }
    protected function getShopTicket($data){
        $str = "<div class='shopticket' style='background:#82AF6F;text-align:center;color:white;cursor:pointer;width:60px;' onclick='Shopticket(".$data->shop_id.",".$data->admin_id.")'>�鿴<input class='mids' type='hidden' value='".$data->shop_id."'><input class='ads' type='hidden' value='".$data->admin_id."'></div>";

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
            $str = '<a style="width: 60px;margin-left:10px;" class="label label-sm label-info" title="ͨ��" onclick="setStatus('.$data->id.', 1)">ͨ��</a>';
            $str .= '<a style="width: 60px;margin-left:10px;" class="label label-sm label-success" title="ʧ��" onclick="setStatus('.$data->id.', 2)">ʧ��</a>';
        }elseif($data->status == 2){
            $str = '<a style="width: 60px;margin-left:10px;" class="label label-sm label-danger" title="ɾ��" onclick="delCheck('.$data->id.')">ɾ��</a>';
        }elseif($data->status == 1){
            $str = '<a style="width: 60px;margin-left:10px;background-color:#892E65!important;" class="label label-sm" title="�޸�" href="'.Yii::app()->createUrl('fenchengsetting/editcheck', array('id'=>$data->id)).'">�޸�</a>
                    <a style="width: 60px;margin-left:10px;" class="label label-sm label-info" title="����" onclick=" setBinding('.$data->id.', 2) ">����</a>';
            /*$str = '<a style="float: left;width: 55px;margin-left:10px;background-color:#892E65!important;" class="label label-sm" title="�޸�" href="'.Yii::app()->createUrl('fenchengsetting/editcheck', array('id'=>$data->id)).'">�޸�</a>
                    <a style="float: left;width: 55px;margin-left:10px;" class="label label-sm label-info" title="����" onclick=" setBinding('.$data->id.', 2) ">����</a>';
            */
        }
        return $str;
    }



    public function actionSetbinding()
    {
        if(!isset($_POST['id'])){
            $result = array('status' =>"error" ,'message'=> "��������");
            echo CJSON::encode($result);
            exit();
        }
        $connection = Yii::app()->db;
        $admin_id = Yii::app()->user->_id;
        $id = $_POST['id'];

        $sql = 'select * from wx_shop_wechatmoney_check where id='.$id.' AND admin_id = '.$admin_id;
        $row =  $connection->createCommand($sql)->queryRow();
        if(!$row){
            $result = array('status' =>"error" ,'message'=> "��������");
            echo CJSON::encode($result);
            exit();
        }
        $is_binding = $row['is_binding'];
        if($is_binding==1){
            $msg = "�ѽ���뾡�췢�Ͷ�ά����������̼���д";
            $result = array('status' =>"error" ,'message'=> $msg);
            echo CJSON::encode($result);
            exit();
        }

        $sql1 = 'UPDATE wx_shop_wechatmoney_check set is_binding = 1 where id = '.$id." AND admin_id=".$admin_id;
        $res = $connection->createCommand($sql1)->execute();
        if(!$res){
            $result = array('status' =>"error" ,'message'=> "���������󣬲�����˴���ʧ��");
            echo CJSON::encode($result);
            exit();
        }

        $sql2 = 'UPDATE wx_shop_account set openid = NULL , wechat_name = NULL  where admin_id = '.$admin_id.' AND shop_id='.$row['shop_id'];
        $connection->createCommand($sql2)->execute();

        $result = array('status' =>"success" ,'message'=> "�����ɹ����뾡�췢�Ͷ�ά����������̼���д");
        echo CJSON::encode($result);
        exit();
    }





    /**
     * �������΢����Ǯ�̻�
     */
    public function actionBatchhandel()
    {
        \lwmf\base\Logger::info("��������");
        if(!isset($_POST['status'])){
            $result = array('status' =>"error" ,'message'=> "��������");
            echo CJSON::encode($result);
            exit();
        }
        $admin_id = Yii::app()->user->_id;
        $status = $_POST['status'];

        $connection = Yii::app()->db;
        $sql = 'select * from wx_shop_wechatmoney_check where admin_id = '.$admin_id.' AND status=0';
        $rows =  $connection->createCommand($sql)->queryAll();
        if(!$rows){
            $result = array('status' =>"error" ,'message'=> "û�п��Դ������ˣ�");
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
                    $msg = $shanghu_name."��Ϣ�����޷�����";
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
                        $msg = $shanghu_name."ϵͳ�쳣�����Ժ����ԣ�";
                        $result = array('status' =>"error" ,'message'=> $msg);
                        echo CJSON::encode($result);
                        exit();
                    }
                }
                $sql1 = 'UPDATE wx_shop_wechatmoney_check set status = 1 where id = '.$id;
                $res = $connection->createCommand($sql1)->execute();
                if(!$res){
                    $result = array('status' =>"error" ,'message'=> "���������󣬲�����˴���ʧ��");
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
                    $result = array('status' =>"error" ,'message'=> "����������1��������˴���ʧ��");
                    echo CJSON::encode($result);
                    exit();
                }

            }
        }
        $result = array('status' =>"success" ,'message'=> "�����ɹ���");
        echo CJSON::encode($result);
        exit();
    }

    //��ȡ���״̬
    protected function getCheckstatus($data)
    {
        if($data->status == 0){
            echo "<span style='color:#3399CC;font-weight:bold;'>������</span>";
        }else if($data->status == 1){
            echo "<span style='color:#339933;font-weight:bold;'>�ɹ�</span>";
        }else if($data->status == 2){
            echo "<span style='color:#CC3300;font-weight:bold;'>ʧ��</span>";
        }
    }

    public function actionSetstatus()
    {
        if(!isset($_POST['status']) || !isset($_POST['id'])){
            $result = array('status' =>"error" ,'message'=> "��������");
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
            $result = array('status' =>"error" ,'message'=> "��������");
            echo CJSON::encode($result);
            exit();
        }
        $openid = $row['openid'];
        $shanghu_name = $row['shanghu_name'];
        $shop_id = $row['shop_id'];
        $telephone = $row['telephone'];
        if(empty($openid) || empty($shanghu_name)){
            $msg = $shanghu_name."��Ϣ�����޷�����";
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
                $msg = $shanghu_name."ϵͳ�쳣�����Ժ����ԣ�";
                $result = array('status' =>"error" ,'message'=> $msg);
                echo CJSON::encode($result);
                exit();
            }
        }

        $sql1 = 'UPDATE wx_shop_wechatmoney_check set status = '.$status.' where id = '.$id." AND admin_id=".$admin_id;
        $res = $connection->createCommand($sql1)->execute();
        if(!$res){
            $result = array('status' =>"error" ,'message'=> "����������1��������˴���ʧ��");
            echo CJSON::encode($result);
            exit();
        }
        if($status == 1){
        $sql2 = 'UPDATE wx_shop_account set openid = "'.$openid.'", wechat_name="'.$shanghu_name.'", telephone="'.$telephone.'" where admin_id = '.$admin_id.' AND shop_id='.$shop_id;
        $connection->createCommand($sql2)->execute();
        }
        $result = array('status' =>"success" ,'message'=> "�ɹ�");
        echo CJSON::encode($result);
        exit();
    }

    public function actionDelcheck()
    {
        if(!isset($_POST['id'])){
            $result = array('status' =>"error" ,'message'=> "��������");
            echo CJSON::encode($result);
            exit();
        }
        $admin_id = Yii::app()->user->_id;
        $id = $_POST['id'];
        $model = ShopWechatmoneyCheck::model()->findByPk($id);
        if(!$model){
            $result = array('status' =>"error" ,'message'=> "��¼�����ڣ�");
            echo CJSON::encode($result);
            exit();
        }
        if($model->admin_id != $admin_id){
            $result = array('status' =>"error" ,'message'=> "�Ƿ�����");
            echo CJSON::encode($result);
            exit();
        }
        if(!$model->delete()){
            $result = array('status' =>"error" ,'message'=> "ɾ��ʧ�ܣ�");
            echo CJSON::encode($result);
            exit();
        }
        $result = array('status' =>"success" ,'message'=> "ɾ���ɹ�");
        echo CJSON::encode($result);
        exit();
    }

    /**
     * ���������˺Žⶳ
     * @dateTime 2018-05-17
     * @author MaWei<www.mawei.live>
     */
    function actionUnfreeze(){
        $shopId = isset($_POST['shop_id']) ? intval($_POST['shop_id']) : 0;
        //�жϵ��̲���
        if($shopId < 1) exit(CJSON::encode(['status' =>"error" ,'message'=> "�����������!"]));
        //�����˺Žⶳ״̬
        $sql = 'update {{shop_account}} set is_freeze=0 where shop_id='.$shopId;
        $ret = Yii::app()->db->createCommand($sql)->execute();
        if(!$ret){
            exit(CJSON::encode(['status' =>"400" ,'message'=> "�ⶳʧ��,����ˢ�º�����!"]));
        }
        exit(CJSON::encode(['status' =>"200" ,'message'=> "�ⶳ�ɹ�!"]));
    }

    public function actionEditcheck($id)
    {
        $admin_id = Yii::app()->user->_id;
        $model = ShopWechatmoneyCheck::model()->findByPk($id);
        if(!$model){
            throw new CHttpException(403, '��¼�����ڣ�');
}
        if($model->admin_id != $admin_id){
            throw new CHttpException(403, '�Ƿ�����');
        }
        if($model->status != 1){
            throw new CHttpException(403, '�Ƿ�����');
        }

        if (isset($_POST['ShopWechatmoneyCheck'])) {
            if(!isset($_POST['ShopWechatmoneyCheck']['shanghu_name'])){
                throw new CHttpException(403, '�����쳣��');
            }
            $shanghu_name = $_POST['ShopWechatmoneyCheck']['shanghu_name'];
            $model->shanghu_name = $shanghu_name;
            if(!$model->update()){
                throw new CHttpException(403, '�޸�ʧ�ܣ�');
            }
            //�޸ĵ����˺�΢�Ŵ����
            $shopAccount = ShopAccount::model()->find("admin_id = ".$admin_id." AND shop_id = ".$model->shop_id);
            if($shopAccount){
                //�޸�wechat_name��openid
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
            throw new CHttpException(403, '��¼�����ڣ�');
        }
        if($model->admin_id != $admin_id){
            throw new CHttpException(403, '�Ƿ�����');
        }
        if($model->status != 2){
            throw new CHttpException(403, '�Ƿ�����');
        }
        if($model->pingtai_type != 16){
            throw new CHttpException(403, '�Ƿ�����');
        }
        $shopModel = Config::model()->findByPk($model->shop_id);
        $shopname = $shopModel->shopname;
        if (isset($_POST['DakuanOrderItem'])) {
            if(!isset($_POST['DakuanOrderItem']['bankusername'])){
                throw new CHttpException(403, '�����쳣��');
            }
            $bankusername = $_POST['DakuanOrderItem']['bankusername'];
            $model->bankusername = $bankusername;
            if(!$model->update()){
                throw new CHttpException(403, '�޸�ʧ�ܣ�');
            }
            $this->redirect(yii::app()->createUrl('fenchengsetting/dakuanitem',array('id'=>$model->order_id)));
        }

        $this->render('editwithdrawwechatname', [
            'model' => $model,
            'shopname' => $shopname
        ]);
    }


    /*
     * ���ַ�ʽ����
     * @author wangsixiao
     *
     * @param int $setWay -���õĴ�ʽ���[1:�츶��֧�������п� 2��΢��֧�������п� 3��΢��֧����΢����Ǯ]
     *
     *
     * **/
    public function actionWithdrawset(){
        $admin_id = Yii::app()->user->_id;
        $param = $_GET;
        if(isset($_POST) && !empty($_POST)){
            $param = $_POST;
        }
        //�ж��̼��Ƿ��Ѿ����ù���ʽ
        $accountModel = AdminAccount::model()->findByPk($admin_id);

        if(isset($param['setWay'])){
            if($param['setWay'] == 1){
                throw new CHttpException(400, '�츶��֧�������п���ʽ������');
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
