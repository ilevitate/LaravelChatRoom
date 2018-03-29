<?php

namespace App\Http\Controllers;

use App\Message;
use App\User;
use Illuminate\Http\Request;
use Auth;
use GatewayClient\Gateway;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');

        // 设置GatewayWorker服务的Register服务ip和端口
        Gateway::$registerAddress = '127.0.0.1:1238';
    }

    public function init(Request $request)
    {
        //绑定用户
        $this->bindUser($request);

        //进入聊天室
        $this->login();

        //历史记录
        $this->history();

        //在线用户
        $this->onlineUsers();
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
//        $room = [
//            'room_id' => $request->room_id ? $request->room_id : '1',
//            'room_name' => $request->room_name ? $request->room_name : '工作交流',
//        ];
//        session()->put('room_id', $room['room_id']);
        $room_id = $request->room_id ? $request->room_id : '1';
        session()->put('room_id', $room_id);

        return view('home');
    }



    /**
     * 绑定client_id 与 user id
     */
    private function bindUser($request)
    {
        $id = Auth::id();
        $client_id = $request->client_id;
        //绑定用户
        Gateway::bindUid($client_id, $id);
        //存入session
        Gateway::setSession($client_id, [
            'id' => $id,
            'avatar' => Auth::user()->avatar(),
            'name' => Auth::user()->name
        ]);

        //加入房间
        Gateway::joinGroup($client_id, session('room_id'));
    }


    /**
     * 提示进入聊天室
     */
    private function login()
    {
        $data = [
            'type' => 'say',
            'data' => [
                'avatar' => '/system_avatar.jpg',
                'name' => '系统消息',
                'content' => '欢迎用户 ' . Auth::user()->name . ' 进入聊天室~',
                'time' => date("Y-m-d H:i:s", time())
            ]
        ];
        Gateway::sendToGroup(session('room_id'), json_encode($data));
    }

    /**
     * 最新的5条聊天历史信息
     */
    private function history()
    {
        $data = ['type' => 'history'];

        $messages = Message::with('user')->where('room_id', session('room_id'))->orderBy('created_at','desc')->limit(3)->get();
        $data['data'] = $messages->map(function ($item, $key) {
            return [
                'avatar' => $item->user->avatar(),
                'name' => $item->user->name,
                'content' => $item->content,
                'time' => $item->created_at->format("Y-m-d H:i:s")
            ];
        });
        //将数据反转排序，time越小的key越小。
        $data['data'] = array_reverse($data['data']->toArray());

        Gateway::sendToUid(Auth::id(), json_encode($data));
    }


    /**
     * 当前在线用户
     */
    private function onlineUsers()
    {
        $data = [
            'type' => 'onlineUsers',
            //只查询当前组的用户
            'data' => Gateway::getClientSessionsByGroup(session('room_id'))
        ];

        Gateway::sendToGroup(session('room_id'), json_encode($data));
    }


    //接收用户发送消息，推送给所有人
    public function say(Request $request)
    {
        $data = [
            'type' => 'say',
            'data' => [
                'avatar' => Auth::user()->avatar(),
                'name' => Auth::user()->name,
                'content' => $request->input('content'),
                'time' => date("Y-m-d H:i:s", time())
            ]
        ];

        //私聊
        if ($request->user_id and $request->user_id != "") {
            $data['data']['name'] = Auth::user()->name . ' 对 ' . User::find($request->user_id)->name . ' 说：';
            Gateway::sendToUid($request->user_id, json_encode($data));
            Gateway::sendToUid(Auth::id(), json_encode($data));

            //私聊信息，只发给对应用户，不存数据库了
            return;
        }


        Gateway::sendToGroup(session('room_id'), json_encode($data));

        //存入数据库，以后可以查询聊天记录
        Message::create([
            'room_id' => session('room_id'),
            'user_id' => Auth::id(),
            'content' => $request->input('content')
        ]);
    }

}
