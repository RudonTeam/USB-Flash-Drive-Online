<?php


    /* * * 
     * U盘 - 超简单PHP在线文件管理脚本
     * 
     * # 只支持管理指定目录的文件(非遍历) 
     * # 可限制上传文件类型
     * # 可增删改目录中的文件
     * 
     * 
     * 开发计划:
     * 1)  智能右键   http://www.htmleaf.com/jQuery/Menu-Navigation/201506292129.html
     * 2) 
     * 
     * 
     * @Author Rudon<285744011@qq.com>
     * @link 参考自http://demo.mycodes.net/daima/windows-iframe
     * 
     */


    /**
     * Pre-set
     */
    $upan_folder_name = pathinfo(dirname(__FILE__), PATHINFO_BASENAME);
    $upan_path_from_root_to_files_dir = substr($_SERVER['REQUEST_URI'], 0, strpos($_SERVER['REQUEST_URI'], $upan_folder_name)) . $upan_folder_name . '/'; // => /more/upan/ and without `index.php`
    

    /* 配置 */
    $config = array();
    $config['upan_folder_name'] = $upan_folder_name;
    $config['folder'] = dirname(__FILE__) . '/files/';              // U盘目录 绝对路径
    $config['url'] = '../../../../../..'.$upan_path_from_root_to_files_dir.'files/';                // U盘目录 对应网址
    $config['disk'] = dirname(__FILE__); // 显示磁盘可用空间
    
    // 禁止上传文件的类型
    $config['forbidden'] = array( 'exe','php','asp','jsp','js','html','htm','shtml' );
    $config['ignore'] = array(                                      // 在U盘中隐藏以下文件
        //     '/upan\.php/i',  //删除本行,如果你将本PHP脚本放在folder之外 可以忽略这个文件
        //     '/index\.php/i', //删除本行,如果你将本PHP脚本放在folder之外 可以忽略这个文件
        '/.*\.gitignore/i',
        '/.*\.htaccess/i',
        '/.*\.DS_Store/i',
    );
    
    $config['assets'] = './assets/';                                // 配套图片目录
    $config['body_bg'] = $config['assets'] . 'bg14.jpg';      // 配套图片目录
    $config['prefix_of_ext_img'] = $config['assets']. 'icon_win7/'; // 配套图片目录
    

    
    
    
    
    
    
    


    /* Pre Check */
    if(!is_dir($config['folder'])){
        mkdir($config['folder'], 0755, true);
        //die('无效U盘路径: '. $config['folder']);
    }
    $config['folder'] = rtrim(rtrim($config['folder'],'/'), '\\') . '/';
    //$config['free_space'] = (is_dir($config['disk']))? '可用空间:'.floor(disk_free_space($config['disk']) / 1048576) . 'MB': '';
    $config['free_space'] = (is_dir($config['disk']))? '可用空间: 0MB': '';
    
    $http = (isset( $_SERVER["HTTPS"] ) && strtolower( $_SERVER["HTTPS"] ) == "on")?'https':'http';
    $full_url_for_cur_script = $_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
    if(stripos($full_url_for_cur_script, '://') === false){
        $full_url_for_cur_script = $http . '://' . $full_url_for_cur_script;
    }


    /* 辅助函数 */
    function a($v){
        header('Content-Type: text/css; charset=utf-8');
        print_r($v);
        die;
    }
    /**
     +----------------------------------------------------------
     * 字符串截取，支持中文和其他编码
     +----------------------------------------------------------
     * @static
     * @access public
     +----------------------------------------------------------
     * @param string $str 需要转换的字符串
     * @param string $start 开始位置
     * @param string $length 截取长度
     * @param string $charset 编码格式 | "utf-8"
     +----------------------------------------------------------
     * @return string
     +----------------------------------------------------------
     */
    function msubstr($str, $start, $length = NULL, $charset = NULL) {
        if(function_exists("mb_substr")){
            $length = ($length === NULL)? mb_strlen($str): intval($length);
            $charset = ($charset === NULL)? mb_detect_encoding($str): $charset;
            $slice = mb_substr($str, $start, $length, $charset);
            
        }elseif(function_exists('iconv_substr')) {
            $length = ($length === NULL)? iconv_strlen($str): intval($length);
            $charset = ($charset === NULL)? 'UTF-8': $charset;
            $slice = iconv_substr($str,$start,$length,$charset);
            if(false === $slice) {
                $slice = '';
            }
        }else{
            $length = ($length === NULL)? strlen($str): intval($length);
            $charset = ($charset === NULL)? 'UTF-8': $charset;
            $re['utf-8']   = "/[\x01-\x7f]|[\xc2-\xdf][\x80-\xbf]|[\xe0-\xef][\x80-\xbf]{2}|[\xf0-\xff][\x80-\xbf]{3}/";
            $re['gb2312'] = "/[\x01-\x7f]|[\xb0-\xf7][\xa0-\xfe]/";
            $re['gbk']    = "/[\x01-\x7f]|[\x81-\xfe][\x40-\xfe]/";
            $re['big5']   = "/[\x01-\x7f]|[\x81-\xfe]([\x40-\x7e]|\xa1-\xfe])/";
            preg_match_all($re[$charset], $str, $match);
            $slice = join("",array_slice($match[0], $start, $length));
        }
        return $slice;
    }
    
    function make_name_shorter ($str, $max_length, $suffix = '...') {
        if(function_exists("mb_substr")){
            /* 多字节处理 */
            $strlen = mb_strlen($str, mb_detect_encoding($str));
            if($strlen > $max_length){
                $strlen_suffix = mb_strlen($suffix, mb_detect_encoding($suffix));
                $length_each_side = floor(($max_length - $strlen_suffix) / 2);
                $length_each_side = max(1, $length_each_side);
                $left = msubstr($str, 0, $length_each_side);
                $right = msubstr($str, $strlen - $length_each_side);
                return $left . $suffix . $right;
            } else {
                return $str;
            }
        } else {
            /* 没办法,系统没能拆分多字节字符 */
            $length_each_side = floor(($max_length - strlen($suffix)) / 2);
            $res = (strlen($str) <= $max_length)? $str : substr($str, 0, $length_each_side) . $suffix . substr($str, -$length_each_side);
            return $res;
        }
        
    }
    

    /* Ajax处理 */
    if(isset($_POST['action'])){
        $return = array(
            'success' => false,
            'message' => '',
            'data' => array()
        );
        if($_POST['action'] == 'delete'){
            /* 删除处理 */
            $f = $_POST['name'];
            $d_f = $config['folder'].$f;
            if(is_file($d_f)){
                unlink($d_f);
                $return['success'] = true;
            } else {
                $return['success'] = false;
                $return['message'] = 'No such file';
            }
            
        } elseif($_POST['action'] == 'rename') {
            /* 重命名 */
            $from_name = $_POST['name'];
            $to_name = $_POST['name_to_be'];
            if(is_file($config['folder'].$to_name)){
                $return['success'] = false;
                $return['message'] = 'Repeat';
            } elseif(!is_file($config['folder'].$from_name)){
                $return['success'] = false;
                $return['message'] = 'No such file';
            } else {
                rename($config['folder'].$from_name, $config['folder'].$to_name);
                $return['success'] = true;
                
                
                $return['data']['file_short_from'] = make_name_shorter($from_name, 13);
                $return['data']['file_short_to'] = make_name_shorter($to_name, 13);
            }
        }
        
        echo json_encode($return);
        die;
    }

    
    
    
    /* 上传处理 */
    $after_uploaded = false;
    if(isset($_FILES) && count(($_FILES))){
        foreach($_FILES as $k=>$v){
            if($v['error'] == 0){
                $cur_ext = strtolower(substr($v['name'], strrpos($v['name'], '.')+1));
                if(!in_array($cur_ext, $config['forbidden'])){
                    $from = $v['tmp_name'];
                    $to = $config['folder'].$v['name'];
                    $after_uploaded = true;
                    move_uploaded_file($from, $to);
                }
            } else if($v['error'] != 4) {
                a('Error happened when files were uploading...');
            }
        }
    }
    

    
    
    

    /* 文件列表 */
    function file_list ($folder, $url = '', $ignore_files = array()) {
        $return = array();
        $folder = rtrim(rtrim($folder,'/'), '\\') . '/';

        /*  */
        if(is_dir($folder)){
            $dh = opendir($folder);
            while($file = readdir($dh)){
                if(!in_array($file, array('.','..'))){
                    if(is_file($folder.$file)){
                        $cur_file_is_allowed = true;
                        foreach ($ignore_files as $p_rule) {
                            if(preg_match($p_rule, $file)){
                                $cur_file_is_allowed = false;
                            }
                        }
                        
                        if($cur_file_is_allowed){
                            /* Allowed */
                            $full = $folder . $file;
                            
                            
                            
                            $file_9 = make_name_shorter($file, 9);
                            $file_11 = make_name_shorter($file, 11);
                            $file_13 = make_name_shorter($file, 13);
                            $file_15 = make_name_shorter($file, 15);
                                
                            $return[] = array(
                                'file' => $file,
                                'path' => $full,
                                'ext' => strtolower(pathinfo($full, PATHINFO_EXTENSION)),
                                'name' => strtolower(pathinfo($full, PATHINFO_FILENAME)),
                                'url' => $url . $file,
                                'file_9' => $file_9,
                                'file_11' => $file_11,
                                'file_13' => $file_13,
                                'file_15' => $file_15,
                            );
                        }
                    }
                }
            }
            closedir($dh);
        }

        return $return;
    }



    



    /* 显示列表 */
    $file_list = file_list($config['folder'], $config['url'], $config['ignore']);
 


?>


<html>
    <head>
        <title>我的U盘</title>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <style type="text/css">

        </style>

        <style type="text/css">
            ul, ol, ul li, ol li {
                list-style: none;
            }
            
            .oneFile {
                cursor: pointer;
                margin: 15px;
                float: left;
                width: 100px;
                /*overflow: hidden;*/
                position: relative;
            }
            .ext { width: 100px; height: 100px; display2: inline-block; }
            .ext img { max-width: 100%; max-height: 100%; }
            .oneFile .title {  
                background: none repeat scroll 0 0 rgba(0, 0, 0, 0.3);
                border-radius: 10px 10px 10px 10px;
                filter: none;
                color: #fff;
                display: inline-block;
                height: 20px;
                line-height: 20px;
                margin-top: 5px;
                overflow: hidden;
                padding: 0 8px;
                text-align: center;
                text-overflow: ellipsis;
                white-space: nowrap;
                z-index: 1; 
                font-size: 14px;
            }
            
            .oneFile div.handle {
                position: absolute;
                left: 1px;
                /*background: white;*/
                
                border-radius: 10px 10px 10px 10px;
                height: 16px;
                z-index: 100;
                display: none;
                padding: 2px 8px;
                cursor: default;
                width: 80px;
                
                
                top: 105px;/*125px*/
                /*background: none repeat scroll 0 0 rgba(230, 230, 230, 0.1);*/
                background: none repeat scroll 0 0 rgba(255, 255, 255, 0.9);
            }
            .oneFile div.handle a {
                color: black;
                font-size: 14px;
                text-decoration: none;
                cursor: pointer;
            }
            .oneFile:hover div.handle {
                display: block;
            }
            .handle a.rename {
                float: left;
            }
            .handle a.del {
                color: red!important;
                float: right;
            }
            .handle a:hover {
                color: gray;
                font-weight: bold;
            }
            
            
            body {
                
            }
            #upload_wrap {
                position: fixed;
                padding: 3px 23px;
                border-radius: 15px;    
                bottom: 5px; right: 5px;
                background: none repeat scroll 0 0 rgba(255, 255, 255, 0.3);
            }
            
            #upload_wrap .uf {
                display: block;
                width: 130px;
            }
            
            .btn {
                display: inline-block;*
                display: inline;
                padding: 3px 6px;
                margin-bottom: 0;*
                margin-left: .3em;
                font-size: 14px;
                line-height: 10px;
                color: #333;
                text-align: center;
                text-shadow: 0 1px 1px rgba(255, 255, 255, 0.75);
                vertical-align: middle;
                cursor: pointer;
                background-color: #f5f5f5;*
                background-color: #e6e6e6;
                background-image: -moz-linear-gradient(top, #fff, #e6e6e6);
                background-image: -webkit-gradient(linear, 0 0, 0 100%, from(#fff), to(#e6e6e6));
                background-image: -webkit-linear-gradient(top, #fff, #e6e6e6);
                background-image: -o-linear-gradient(top, #fff, #e6e6e6);
                background-image: linear-gradient(to bottom, #fff, #e6e6e6);
                background-repeat: repeat-x;
                border: 1px solid #ccc;*
                border: 0;
                border-color: #e6e6e6 #e6e6e6 #bfbfbf;
                border-color: rgba(0, 0, 0, 0.1) rgba(0, 0, 0, 0.1) rgba(0, 0, 0, 0.25);
                border-bottom-color: #b3b3b3;
                -webkit-border-radius: 4px;
                -moz-border-radius: 4px;
                border-radius: 4px;
                filter: progid: DXImageTransform.Microsoft.gradient(startColorstr='#ffffffff', endColorstr='#ffe6e6e6', GradientType=0);
                filter: progid: DXImageTransform.Microsoft.gradient(enabled=false);*
                zoom: 1;
                -webkit-box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.2), 0 1px 2px rgba(0, 0, 0, 0.05);
                -moz-box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.2), 0 1px 2px rgba(0, 0, 0, 0.05);
                box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.2), 0 1px 2px rgba(0, 0, 0, 0.05);
            }
            
            .hovershowbaby:hover .hovershowblock {
                display: block!important;
            }
        </style>

    </head>


    <body style="background:url(<?=$config['body_bg'];?>); background-repeat:no-repeat;background-size: cover;">
        
        <div id="upload_wrap" class="hovershowbaby">
            <div style="position: relative; height: 1px;">
                <div style="position: absolute; display: none; bottom: 10px; left: -20px;" class="hovershowblock">
                    <div style="padding: 10px; border-radius: 10px;font-size: 16px; background: none repeat scroll 0 0 rgba(255, 255, 255, 0.3); font-weight: bold; float: right; clear: both; min-width: 170px; cursor: default;">
                        <small>
                            <?php echo $config['free_space']; ?>
                        </small>
                        <ul style="list-style: none!important; margin-left: 0; padding-left: 0;margin-bottom: 10px;  font-size: 12px;">
                    
                            <li>1.文件大小上限: <?php echo ini_get('upload_max_filesize'); ?></li>
                            <li>2.上传的文件名称不能带空格</li>
                            <li>3.允许中文,但不允许中文标点</li>

                        </ul>
                    </div>
                </div>
            </div>
            <h4 style="margin-top: 14px;margin-bottom: 8px;">
                上传新文件
                <button class="btn" title="上传更多" onclick="$('#more_files_input').slideToggle('fast');" id="more_files_input_btn">+</button>
                <button class="btn" style="margin-left: -8px; font-size: 12px;" onclick="go_upload();" id="go_upload_btn">开始</button>
            </h4>
            <form action="" method="post" enctype="multipart/form-data" id="form_upload">
                <div style="clear: both;"></div>
                <input type="file" name="uf_1" class="uf" value=""/>
                <input type="file" name="uf_2" class="uf" value=""/>
                <input type="file" name="uf_3" class="uf" value=""/>
                <div id="more_files_input" style="display: none;">
                    <input type="file" name="uf_4" class="uf" value=""/>
                    <input type="file" name="uf_5" class="uf" value=""/>
                    <input type="file" name="uf_6" class="uf" value=""/>
                    <input type="file" name="uf_7" class="uf" value=""/>
                    <input type="file" name="uf_8" class="uf" value=""/>
                    <input type="file" name="uf_9" class="uf" value=""/>
                    <input type="file" name="uf_10" class="uf" value=""/>
                </div>
                
            </form>
            <div style="color: white; display: none; margin-bottom: 10px;" id="uploading">
                Uploading...
            </div>
            <?php if($after_uploaded): ?>
            <div style="color: black;">
                
            </div>
            <?php endif; ?>
        </div>
        
        <div id="desk">
            <ul>
                <?php foreach ($file_list as $oneFile): ?>
                <li class="oneFile" data-cur-file="<?=$oneFile['file'];?>" title="<?=$oneFile['file'];?>">
                    <div class="handle">
                        <div class="act_wrap">
                            <a onclick="onclick_rename(this);" class="Rename" title="<?=$oneFile['file'];?>">Rename</a>
                            <a onclick="onclick_del(this);" class="del" title="Delete">X</a>
                        </div>
                        <div class="act_name" style="display: none;">
                            <input type="text" value="<?=$oneFile['file'];?>" style="margin-top: -4px;width: 125%; padding: 3px; margin-left: -10px;" class="rename_tobe"/>
                            <a class="btn" style="margin: 0; display: block; margin-left: -10px; width: 108%;" onclick="onclick_rename_real(this);">
                                OK
                            </a>
                        </div>
                    </div>
                    <a target="_blank" href="<?=$oneFile['url'];?>"><div class="ext" data-ext="<?=$oneFile['ext'];?>"></div><div class="title" title="<?=$oneFile['file'];?>"><?=$oneFile['file_13'];?></div></a><em></em></li>
                <?php endforeach; ?>
            </ul>
        </div>
        
        
        
        





        <script src="https://libs.baidu.com/jquery/2.0.0/jquery.min.js"></script>
        <script type="text/javascript">
            $(document).ready(function () {
                $('.ext').each(function () {
                    var ext = $(this).attr('data-ext');
                    var img_url = '<?=$config['prefix_of_ext_img'];?>' + ext + '.ico';
                    $(this).append('<img src="' + img_url + '" alt="' + ext + '"  onerror="this.src=\'<?=$config['prefix_of_ext_img'];?>file.png\';"/>');
                });
            });
            
            
            function onclick_rename (a) {
                var cur_file_name = $(a).closest('.oneFile').attr('data-cur-file');
                $(a).parent('.act_wrap').hide();
                $(a).parent().parent().children('.act_name').show();
            }
            
            
            function onclick_rename_real (a){
                var cur_file_name = $(a).closest('.oneFile').attr('data-cur-file');
                var tobe = $(a).parent().children('.rename_tobe').val();
                $.ajax({
                    url: '<?php echo $full_url_for_cur_script; ?>',
                    method: 'post',
                    dataType: 'json',
                    data: {
                        'action': 'rename',
                        'name': cur_file_name,
                        'name_to_be': tobe
                    },
                    success: function(res){
                        if(!res.success){
                            if(res.message == 'Repeat'){
                                $(a).parent('.act_name').hide();
                                $(a).parent().parent().children('.act_wrap').show();
                            } else {
                                alert(res.message);
                            }
                            
                        } else {
                            $(a).parent('.act_name').hide();
                            $(a).parent().parent().children('.act_wrap').show();
                            /* change content */
                            var old_li = $('li.oneFile[data-cur-file="'+cur_file_name+'"]');
                            var old_html = $(old_li).html();
                            var new_html = old_html.replace(new RegExp(cur_file_name,"gm"),tobe);
                            
                            var file_short_from = res.data.file_short_from;
                            var file_short_to = res.data.file_short_to;
                            new_html = new_html.replace(new RegExp(file_short_from,"gm"),file_short_to);
            
                            $(old_li).attr('data-cur-file', tobe);
                            $(old_li).html(new_html);
                        }
                    },
                    error: function () {
                        alert('Failed!');
                    }
                });
            }
            
            function onclick_del (a) {
                var cur_file_name = $(a).closest('.oneFile').attr('data-cur-file');
                if(!confirm('确定要删除'+cur_file_name)){
                    return;
                }
                $.ajax({
                    url: '<?php echo $full_url_for_cur_script; ?>',
                    method: 'post',
                    dataType: 'json',
                    data: {
                        'action': 'delete',
                        'name': cur_file_name
                    },
                    success: function(){
                        $(a).closest('.oneFile').remove();
                    },
                    error: function () {}
                });
                
            }
            
            function go_upload () {
                $('#go_upload_btn').hide();
                $('#form_upload').hide();
                $('#more_files_input_btn').hide();
                
                $('#uploading').show();
                $('#form_upload').submit();
            }
        </script>

    </body>

</html>