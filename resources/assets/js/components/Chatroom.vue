<template>
    <div class="container chatRoom">
        <a href="?room_id=1" class="btn btn-danger">工作交流</a>
        <a href="?room_id=2" class="btn btn-primary">闲聊唠嗑</a>
        <hr class="divider">
        <div class="row">
            <div class="col-md-8">
                <div class="panel panel-default">
                    <div class="panel-heading">当前房间：<span class="roomName"></span></div>
                    <div class="panel-body">
                        <div class="messages">
                            <div class="media" v-for="message in messages">
                                <div class="media-left">
                                    <a href="#">
                                        <img class="media-object img-circle" :src="message.avatar">
                                    </a>
                                </div>
                                <div class="media-body">
                                    <p class="time">{{message.time}}</p>
                                    <h4 class="media-heading">{{message.name}}</h4>
                                    <p v-html="message.content"></p>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="panel panel-default">
                    <div class="panel-heading">在线用户</div>

                    <div class="panel-body">
                        <ul class="list-group">
                            <li class="list-group-item" v-for="user in onlineUsers">
                                <img :src="user.avatar" class="img-circle">
                                {{user.name}}
                            </li>
                        </ul>
                    </div>

                </div>
            </div>
        </div>

        <form @submit.prevent="onSubmit">
            <div class="form-group">
                <label for="user_id">私聊</label>

                <select class="form-control" id="user_id" v-model="user_id">
                    <option value="">所有人</option>
                    <option :value="user.id" v-for="user in onlineUsers">{{user.name}}</option>
                </select>
            </div>

            <div class="form-group">
                <label for="content">内容</label>
                <span id="content" style="text-align:left" v-model="content" ></span>
            </div>

            <button type="submit" class="btn btn-default">提交</button>
        </form>
    </div>



</template>

<script>
    import E from 'wangeditor'

    $(function () {
        let room_name = "工作交流";
        $(".chatRoom a").each(function(){
            let thisRoom = $(this);
            if( thisRoom[0].href === String(window.location)){
                room_name = thisRoom[0].innerHTML;
            }
        });
        $(".roomName").html(room_name);
    });

    let ws = new WebSocket("ws://119.28.85.225:2345");

    export default {

        name: 'editor',
        data() {
            return {
                messages : [],
                onlineUsers : [],
                user_id : '',
                content: '',
                room : [],
            }
        },
        created() {
            ws.onmessage = (e) => {
                // console.log(e.data)

                //字符串转json
                let data = JSON.parse(e.data);
                // console.log(data)

                //如果没有类型，就为空
                let type = data.type || '';
                switch (type) {
                    case 'ping':
                        ws.send('pong');
                        break;
                    case 'init':
                        axios.post('/init', {client_id: data.client_id});
                        break;
                    case 'say':
                        this.messages.push(data.data);
                        this.$nextTick(function () {
                            $('.panel-body').animate({scrollTop: $('.messages').height()});
                        });
                        break;
                    case 'history':
                        this.messages = data.data;
                        break;

                    case 'onlineUsers':
                        this.onlineUsers = data.data;
                        break;
                    case 'logout':
                        this.$delete(this.onlineUsers, data.client_id);
                        break;
                    default:
                        console.log(data)
                }
            }
        },
        methods: {
            onSubmit(){
                //提交发送内容
                if (this.content === ""){
                    return false;
                }
                axios.post('/say', {content: this.content, user_id: this.user_id});
                this.content = ''
            },
        },

        mounted() {
            var editor = new E('#content');
            editor.customConfig.onchange = (html) => {
                this.content = html
            };

            editor.customConfig.uploadImgShowBase64 = true;
            editor.customConfig.showLinkImg = false;
            editor.create();
        }
    }
</script>


<style scoped>
    .panel-body {
        height: 480px;
        overflow: auto;
    }

    .media-object.img-circle {
        width: 64px;
        height: 64px;
    }

    .img-circle {
        width: 48px;
        height: 48px;
    }

    .time {
        float: right;
    }

    .media {
        margin-top: 24px;
    }

    .systemMessage{
        text-align: center;
        font-size: 13px;
        color: #9d9d9d;
    }

    .systemMessage ul {
        list-style: none;
    }
    .systemMessage ul li{
        list-style: none;
        text-align: center;
        margin-top: 2px;
    }
</style>