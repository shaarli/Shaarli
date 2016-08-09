<?php

require_once 'Controller.php';

/**
 * Class LoginController
 *
 * Handle login page.
 */
class LoginController extends Controller
{
    /**
     * {@inheritdoc}
     */
    public function redirect()
    {
        // No need to login for open Shaarli
        if ($this->conf->get('security.open_shaarli')) {
            header('Location: ?');
            return true;
        }
        return false;
    }
    
    /**
     * {@inheritdoc}
     */
    public function render()
    {
        if (isset($this->get['username'])) {
            $this->tpl->assign('username', escape($this->get['username']));
        }
        $referer = isset($this->server['HTTP_REFERER']) ? escape($this->server['HTTP_REFERER']) : '';
        $this->tpl->assign('returnurl', $referer);
        $this->tpl->renderPage('loginform');
    }
}