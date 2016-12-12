<?php

class LoginController extends Member {
    
    public function __construct() {
		parent::__construct();
	}
	
	/**
	 * 登录
	 */
	public function indexAction() {



	    if (! $this->isLogin(1)) {
			$type=$this->session->get('type');
			$this->memberMsg('您不能重复登陆', 'index.php?s=member&c=index&type='.$type);
        }

	    if ($this->isPostForm()) {
		    $data = $this->post('data');
			if (empty($data['username']) || empty($data['password'])) {
                $this->memberMsg('用户名和密码不能为空');
            }
			$member = $this->model('member')->where('username=?', $data['username'])->select(false);
			$user_type=$member['user_type'];

			$time = empty($data['cookie']) ? 0 : time()+360*24*3600; //会话保存时间。
			$backurl = $data['back'] ? urldecode($data['back']) : '?s=member&c=index&type='.$user_type;


			if (empty($member)) {
                $this->memberMsg('会员名不能为空');
            }

			if ($member['password'] != md5(md5($data['password']) .$member['salt']. md5($data['password']))) {
                $this->memberMsg('密码错误');
            }
			$this->session->set('member_id', $member['username']);
			$this->session->set('type', $member['user_type']);
			$this->memberMsg('登陆成功', $backurl, 1);
		}

		$backurl = $this->get('back') ? $this->get('back') : (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '?s=member&c=index&type='.$user_type);
	    $this->view->assign(array(
			'meta_title' => lang('m-log-9') . '-' . $this->site['SITE_NAME'],
			'backurl'    => urlencode($backurl),
		));
		$this->view->display('member/login');
	}
	
	/**
	 * 一键登录
	 */
	public function oauthAction() {
	    $oauthname   = $this->get('name');
		if ($this->memberconfig['isoauth'] && $oauthname) {
		    $config  = $this->loadOauth($oauthname);
		    oauth_login($config);
		} else {
		    $this->memberMsg(lang('m-log-10'), url('member/login'));
		}
	}
	
	/**
	 * 退出登录
	 */
	public function outAction() {
		$oauthconfig = $this->loadOauth();
		if ($oauthconfig) oauth_logout();
		if ($this->session->is_set('member_id'))    $this->session->unset_userdata('member_id');
		if ($this->session->is_set('oauth_openid')) $this->session->unset_userdata('oauth_openid');
		if ($this->session->is_set('oauth_name'))   $this->session->unset_userdata('oauth_name');
		if (get_cookie('member_id'))            set_cookie('member_id', 0);
		if (get_cookie('member_code'))          set_cookie('member_code', 0);
		$this->memberMsg(lang('m-log-11') . ($this->memberconfig['uc_use'] == 1 ? uc_user_synlogout() : ''), SITE_URL, 1);
	}
	
}