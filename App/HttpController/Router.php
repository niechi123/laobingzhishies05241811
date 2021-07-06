<?php


namespace App\HttpController;


use EasySwoole\Http\AbstractInterface\AbstractRouter;
use EasySwoole\Http\Request;
use EasySwoole\Http\Response;
use FastRoute\RouteCollector;

class Router extends AbstractRouter
{
    function initialize(RouteCollector $route)
    {
        //全部拦截，所有路由必须自定义
        $this->setGlobalMode(true);
        $this->setMethodNotAllowCallBack(function (Request $request,Response $response){
            $response->write(json_encode(['code'=>4040, 'msg' => '路由未匹配.', 'result' => null], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return false;
        });
        $this->setRouterNotFoundCallBack(function (Request $request,Response $response){
            $response->write(json_encode(['code'=>404, 'msg' => '路由未匹配。', 'result' => null], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return false;//此处也可以  return '指定路由';
        });

        //支持分组
        $route->addGroup('/esapi/', function (RouteCollector $route){
            $route->post('send_sms', 'user/send_sms');
            $route->post('login', 'user/login');
//            $route->post('info_jump', 'user/info_jump');
//            $route->post('wechat_up_info', 'user/wechat_up_info');
            $route->post('up_info', 'user/up_info');
            $route->get('user_info', 'user/user_info');
            $route->get('identity_list', 'user/identity_list');
            $route->get('update_index', 'user/update_index');
            $route->get('answer_count', 'user/answer_count');
            $route->get('home_page', 'user/home_page');

            $route->addGroup('question/', function (RouteCollector $route){
                $route->get('question_activity_content', 'question/question_activity_content');
                $route->get('question_config', 'question/question_config');
                $route->get('question_list', 'question/question_list');
                $route->post('answer_submit', 'question/answer_submit');
                $route->get('ranking', 'question/ranking');
                $route->get('area_rank', 'question/area_rank');

                $route->get('badge_list', 'question/badge_list');
            });

            $route->get('test', 'test/test');

            $route->get('luck_draw', 'Draw/luck_draw');
            $route->get('draw_user', 'Draw/draw_user');
            $route->post('win_draw', 'Draw/win_draw');
        });
    }


}