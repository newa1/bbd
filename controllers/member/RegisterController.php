<?php

class RegisterController extends Member {
	public $member;
	public $member_per;
	public $member_com;
	public $member_lietou;
    
    public function __construct() {
		parent::__construct();
		$this->member=$this->model('member');
		$this->member_per=$this->model('member_per');
		$this->member_com=$this->model('member_com');
		$this->member_lietou=$this->model('member_lietou');

	}
	
	/**
	 * 注册
	 */
	public function indexAction() {

		if (!$this->isLogin(1)) $this->msg(lang('m-reg-1'), url('member/'));
	    if ($this->isPostForm()) {
			$data = $_POST['data'];
			$usertype=$data['user_type'];
			//再次验证短信验证码
			if ($this->post('code') && !$this->checkNum($this->post('code'))) $this->memberMsg("短信验证码不正确");


            //内部验证
			$this->check($data,$usertype);

			//开始注册
			if($usertype==1){

				$uid=$this->reg($data,$usertype);
				//0代表浏览器关闭及清除cookie值
				setcookie('member_per_id', $uid, 0); //登录cookie
				setcookie('member_per_code', substr(md5(SITE_MEMBER_COOKIE . $uid), 5, 20), 0);
			}
			if($usertype==2){
				$uid=$this->reg($data,$usertype);
				setcookie('member_com_id', $uid, 0); //登录cookie
				setcookie('member_com_code', substr(md5(SITE_MEMBER_COOKIE . $uid), 5, 20), 0);
			}
			if($usertype==3){
				$uid=$this->reg($data,$usertype);
				setcookie('member_lietou_id', $uid, 0); //登录cookie
				setcookie('member_lietou_code', substr(md5(SITE_MEMBER_COOKIE . $uid), 5, 20), 0);
			}
			if (empty($uid)) $this->memberMsg(lang('m-reg-2'));
//			$this->regEmail($data); //注册邮件提示
			$this->memberMsg(lang('m-reg-3'), url('member/login'), 1);


		}

		$this->assign(array(

			'meta_title'	=> lang('m-reg-4') . '-' . '布布丁',


		));
		$type=$_GET['type'];
		//个人类型
		if($type==1){
			$this->display('member/register');
		}
		//企业类型
		if($type==2){
			$this->display('member/register_qiye');
		}
		//猎头类型
		if($type==3){
			$this->display('member/register_lt');
		}

	}
	
	/**
	 * 一键登录返回
	 */
	public function callbackAction() {
	    $oauthconfig  = $this->loadOauth();
		if (!$oauthconfig) $this->memberMsg(lang('m-log-15'));
	    oauth_callback($oauthconfig);
		$oauth_data  = $this->session->get('oauth_data');
		$oauth_name  = $this->session->get('oauth_name');
		$memberinfo  = get_user_info($oauthconfig, $oauth_data);
		if (empty($oauth_data['oauth_openid']) || empty($oauth_name)) $this->memberMsg(lang('m-log-12'));
		if (empty($memberinfo['name']) && empty($memberinfo['avatar'])) $this->memberMsg(lang('a-mod-200'));
		//查询是否已经绑定
		$omember = $this->member->from('oauth')->where("oauth_openid = '" . $oauth_data['oauth_openid'] . "' AND oauth_name = '" . $oauth_name . "'")->select(false);
		if (empty($omember)) {  //绑定用户,v1.7.7修改为直接注册
			//注册会员
			$pwd = rand(0, 9999);
			$data = array(
				'username'	=> $oauth_name.time().rand(0, 999),
				'password'	=> $pwd,
				'password2'	=> $pwd,
				'email'		=> $oauth_name.$oauth_data['oauth_openid'].'@ .com',
				'nickname'	=> (string)$memberinfo['name'],
				'avatar'	=> $memberinfo['avatar']
			);
			$uid = $this->reg($data);
			if (empty($uid)) $this->memberMsg(lang('m-reg-2'));
			$data['id'] = $uid;
			$this->bang($data);
			$this->regEmail($data); //注册邮件提示
			//登录cookie
            set_cookie('member_id', $uid, 24*3600);
            set_cookie('member_code', substr(md5(SITE_MEMBER_COOKIE . $uid), 5, 20), $time);
		    $this->memberMsg(lang('m-log-4'), url('member/'), 1);
		} else { //验证成功,判断用户表
			$member = $this->member->where('username=?', $omember['username'])->select(false);;
			if (empty($member)) $this->memberMsg(lang('m-log-14'), url('member/login'));
			$oauth = $this->model('oauth');
			//更新登录时间
			$oauth->update(array('logintime'=>time(), 'logintimes'=>$omember['logintime'], 'oauth_data'=>array2string($oauth_data)), 'id=' . $omember['id']);
			$this->update_login_info($member);
            set_cookie('member_id', $member['id'], 24*3600); //保存会话24小时。
            set_cookie('member_code', substr(md5(SITE_MEMBER_COOKIE . $member['id']), 5, 20), 24*3600);
			if ($this->memberconfig['uc_use'] == 1) {
			    list($uid) = uc_get_user($member['username']);
				if ($uid > 0) {
				    $ucsynlogin = uc_user_synlogin($uid);
					$this->memberMsg(lang('m-log-4') . $ucsynlogin, url('member/'), 1);
				}
			}
		    $this->memberMsg(lang('m-log-4'), url('member/'), 1);
		}
	}
	
	/**
	 * 一键登录绑定会员
	 */
	public function bangAction() {
	    $type = $this->post('type');
		$data = $this->post('data');
		if ($type == 'bang') {
		    //绑定会员
			$member = $this->member->where('username=?', $data['username'])->select(false);
			if ($member) {
				if ($this->memberconfig['uc_use'] == 1) {
					list($uid, $username, $password, $email) = uc_user_login($data['username'], $data['password']);
					if (!$uid > 0) $this->memberMsg(lang('m-reg-6'));
				} elseif (md5(md5($data['password']) . $member['salt'] . md5($data['password'])) != $member['password']) {
					$this->memberMsg(lang('m-reg-6'));
				}
			    $config = $this->loadOauth();
		        $row    = get_user_info($config, $this->session->get('oauth_data'));
				$row['username'] = $member['username'];
				$row['nickname'] = (string)$row['name'];
			    $this->bang($row);
				//登录cookie
                set_cookie('member_id', $member['id'], 24*3600);
                set_cookie('member_code', substr(md5(SITE_MEMBER_COOKIE . $member['id']), 5, 20), $time);
				$this->memberMsg(lang('m-reg-5'), url('member'), 1);
			} else {
			    $this->memberMsg(lang('m-reg-6'));
			}
		} elseif ($type == 'reg') {
		    //注册会员
			$this->check($data);
			$uid = $this->reg($data);
			if (empty($uid)) $this->memberMsg(lang('m-reg-2'));
			$data['id'] = $uid;
			$this->bang($data);
			$this->regEmail($data); //注册邮件提示
			//登录cookie
            set_cookiet('member_id', $uid, 24*3600);
            set_cookie('member_code', substr(md5(SITE_MEMBER_COOKIE . $uid), 5, 20), $time);
			$this->memberMsg(lang('m-reg-3'), url('member'), 1);
		} else {
		    $this->memberMsg(lang('m-pms-8'));
		}
	}
	
	/**
	 * 会员名验证
	 */
	public function checkuserAction() {
		$username = $this->get('username');
		if (empty($username)) exit($this->ajaxMsg(lang('m-reg-7'), 0));

		$member = $this->member->from(null, 'uid')->where('username=?', $username)->select(false);
		if ($member) exit($this->ajaxMsg(lang('m-reg-8'), 0));
		exit($this->ajaxMsg('√', 1));
	}
	/*
	 * 发送短信验证码
	 */
	public function sendcaptchaAction(){
		//再次验证
		$username = $this->get('moblie');
		if (empty($username)) exit($this->ajaxMsg(lang('m-reg-7'), 0));
		$member = $this->member_per->from(null, 'uid')->where('username=?', $username)->select(false);
		if ($member) exit($this->ajaxMsg('用户名已存在', 0));


		//把验证码储存在session中；

		$code=$this->captcha();
		$this->session->set('num',$code);


		exit($this->ajaxMsg('发送成功',1));





	}



	//生成验证码
	public function captcha(){

		$str='0123456789QWERTYUIOPLKJHGFDSAZXCVBNM';
		$code='';
		$strlength=strlen($str);
		$long=4;
		for($i=0;$i<$long;$i++){
			$code.=$str[mt_rand(0,$strlength)];
		}
		return $code;

	}
	
	/**
	 * Email验证
	 */
	public function checkemailAction() {

	    $email = $this->get('email');
		if (!check::is_email($email)) exit($this->ajaxMsg(lang('m-reg-9'), 0));
		$member = $this->member->from(null, 'id')->where('email=?', $email)->select(false);
		if ($member) exit($this->ajaxMsg(lang('m-reg-10'), 0));
		exit($this->ajaxMsg('√', 1));
	}
	
	private function ajaxMsg($msg, $id) {
	    $msg = $id == 0 ? '<span class="form-tip tip-error">' . $msg . '<br></span>' : '<span class="form-tip tip-success">' . $msg . '</span>';
		return json_encode(array('result' => $id, 'msg' => $msg));
	}
	
	/**
	 * 内部验证
	 */
	private function check($data,$type) {
		if (empty($data['username'])) $this->memberMsg(lang('m-reg-7'));

		if (empty($data['password'])) $this->memberMsg(lang('m-reg-11'));

		if ($data['password'] != $data['password2']) $this->memberMsg(lang('m-reg-12'));

//		if (!check::is_email($data['email'])) $this->memberMsg(lang('m-reg-9'));
		if($type==1){//个人会员
			$member = $this->member_per->where('email=?', $data['email'])->select(false);
			if ($member) $this->memberMsg(lang('m-reg-10'));
			$member = $this->member_per->from(null, 'id')->where('username=?', $data['username'])->select(false);
			if ($member) $this->memberMsg(lang('m-reg-8'));

		}
		if($type==2){//企业会员
			$member = $this->member_com->from(null, 'id')->where('email=?', $data['email'])->select(false);
			if ($member) $this->memberMsg(lang('m-reg-10'));
			$member = $this->member_com->from(null, 'id')->where('username=?', $data['username'])->select(false);
			if ($member) $this->memberMsg(lang('m-reg-8'));
		}
		if($type==3){//猎头会员
			$member = $this->member_lietou->from(null, 'id')->where('email=?', $data['email'])->select(false);
			if ($member) $this->memberMsg(lang('m-reg-10'));
			$member = $this->member_lietou->from(null, 'id')->where('username=?', $data['username'])->select(false);
			if ($member) $this->memberMsg(lang('m-reg-8'));
		}

	}
	
	/**
	 * 绑定
	 */
	private function bang($data) {
		$oauth_data	= $this->session->get('oauth_data');
		$oauth_name	= $this->session->get('oauth_name');
		if (empty($oauth_data['oauth_openid']) || empty($oauth_name)) $this->memberMsg(lang('m-reg-15'), url('member/login'));
		$oauth  = $this->model('oauth');
	    $member	= $oauth->where('oauth_openid=?', $oauth_data['oauth_openid'])->where('oauth_name=?', $oauth_name)->select(false);
		if ($member) $this->memberMsg(lang('m-reg-16'));
		$data['logintime']		= $data['addtime'] = time();
		$data['oauth_data']		= array2string($oauth_data);
		$data['oauth_name']		= $oauth_name;
		$data['oauth_openid']	= $oauth_data['oauth_openid'];
		unset($data['id']);
		$oauth->insert($data);
	}
	
	/**
	 * 注册
	 */
	private function reg($data,$type) {

	    if (empty($data)) return false;

        $data['reg_ip']		= client::get_user_ip();
        $data['status']		=  1 ;
		$data['reg_time']	= time();
		$data['salt']     = substr(md5(time()), 0, 6);
		$data['password'] = md5(md5($data['password']) . $data['salt'] . md5($data['password']));
		$data['login_ip'] = '';
		$data['login_time'] = 0;
		if($type==1){
			$uid = $this->member_per->insert($data);
		}
		if($type==2){
			$uid=$this->member_com->insert($data);
		}
		if($type==3){
			$data['degree'] = 0;
			$data['vip_type'] = 0;
			$data['pid'] = 0;
			$uid=$this->member_lietou->insert($data);

		}

		return $uid;
	}
	
	/**
	 * 激活Ucenter用户
	 */
	public function activeAction() {
	    list($username)	= explode("\t", uc_authcode($this->get('auth'), 'DECODE'));
		if (empty($username)) $this->memberMsg(lang('m-pms-13'));
		if ($this->isPostForm()) {
			$uc_user_info		= uc_get_user($username);
			$data['email']		= $uc_user_info[2];
			$data['regip']		= client::get_user_ip();
			$data['avatar']		= UC_API . '/avatar.php?uid=' . $uc_user_info[0] . '&size=middle';
			$data['status']		= $this->memberconfig['status'] ? 0 : 1;
		    $data['modelid']	= $this->post('modelid');
			$data['modelid']	= (!isset($data['modelid']) || empty($data['modelid'])) ? $this->memberconfig['modelid'] : $data['modelid'];
		    $data['groupid']	= 1;
			$data['regdate']	= time(); 
		    $data['username']	= $username;
			if (!isset($this->membermodel[$data['modelid']])) $this->memberMsg(lang('m-reg-17'));
			if ($member	= $this->member->getOne('username=?', $username, 'id')) {
			    $userid = $member['id'];
			} else {
			    $userid = $this->member->insert($data);
			}
			if ($userid) {
                set_cookie('member_id', $userid, 24*3600);
                set_cookie('member_code', substr(md5(SITE_MEMBER_COOKIE . $userid), 5, 20), $time);
				$this->memberMsg(lang('m-reg-21'), $this->post('back') ? html_entity_decode(urldecode($this->post('back'))) : url('member/'), 1);
			} else {
			    $this->memberMsg(lang('m-reg-22'));
			}
		}
	    $this->view->assign(array(
			'backurl'		=> urlencode($this->get('back')),
			'username'		=> $username,
			'meta_title'	=> lang('m-reg-23') . '-' . $this->site['SITE_NAME'],
		    'membermodel'	=> $this->membermodel
		));
		$this->view->display('member/active');
	}
	
}