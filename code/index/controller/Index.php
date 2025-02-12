<?php

namespace app\index\controller;

use app\common\controller\Frontend;

class Index extends Frontend
{

    protected $noNeedLogin = '*';
    protected $noNeedRight = '*';
    protected $layout = '';

    public function index()
    {
        // header('https://www.lbssn.com/vzGI13');exit;
        // return $this->view->fetch();
        // return $this->redirect('/wahaha.php/index/login');
        // return $this->redirect('https://www.baidu.com');
    }

}
