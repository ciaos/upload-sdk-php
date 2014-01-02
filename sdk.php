<?php

/**
 * @desc     SDK
 * @package  dbank 
 * @author   xieqingdong (xieqingdong@huawei.com)
 * @version  1.0.0
 */
#require_once dirname ( __FILE__ ) . '/' . 'RequestCore.class.php';

/**
 * @desc Huawei Cloud Storage*/
class HuaweiDbankCloud
{
    const VERSION              = '1.0.0';
    const DOWNLOAD_HOST        = 'cs.dbank.com'; 
    const USER_AGENT_PREFIX    = 'PHP-SDK-';        # 
    const UP_SEG_SIZE          = 52428800;          # 50MB
    #const API_DBANK_COM        = '122.11.38.23';      # api.dbank.com
    const API_DBANK_COM        = 'api.dbank.com';      # api.dbank.com
    const UPLOAD_HOST_EXPTIME  = 1800;                 # 0.5hour
    
    private $_app_id;
    private $_app_appname;
    private $_app_secret;
    private $_upload_host;
    private $_upload_host_exptime;
    private $_download_host;
    
    protected $endpoint;        # 
    
    public function __construct($appid, $appname, $appsecret)
    {
        $this->_app_id = $appid;
        $this->_app_appname = $appname; 
        $this->_app_secret = $appsecret; 

        $this->get_upload_host(true);
        //$this->_upload_host = '10.6.2.50';
    }

    public function upload ($uri, $file, $callback_url = NULL, $callback_status = NULL)
    {
        $this->get_upload_host();
        if (substr($uri, -1, 1) === '/') {
            return array('status' => false, 'msg' => 'bad request.');
        } else if (!is_file($file)) {
            return array('status' => false, 'msg' => "$file not exist.");
        }

        $file_size = filesize($file); 
        
        $failed_count = 0;
        $uploaded_size = 0;
        $i = 1;

        $ret = $this->_do_upload('init', $uri, $file, $callback_url, $callback_status);
        if (is_array($ret) && isset($ret['http_code']) && $ret['http_code'] === 200) {  # feisu success
            return array('status' => true, 'msg' => $ret['body']);
        } elseif (is_array($ret) && isset($ret['http_code']) && $ret['http_code'] === 201) {    # duandian success
            $uploaded_point = json_decode($ret['body'], true);
            if ($uploaded_point['upload_status'] == 1) 
                $uploaded_size = $uploaded_point['completed_range'][0][1] + 1;  
        } else {
            $failed_count ++;
        } 

        while ($uploaded_size != $file_size && $failed_count <= 4) {
            $start = $uploaded_size;
            $end = ($uploaded_size + self::UP_SEG_SIZE) > ($file_size - 1) 
                        ? ($file_size - 1) : ($uploaded_size + self::UP_SEG_SIZE);
            $range = "$start-$end";
            $ret = $this->_do_upload($range, $uri, $file, $callback_url, $callback_status);
            if (is_array($ret) && isset($ret['http_code']) && $ret['http_code'] === 200) {
                return array('status' => true, 'msg' => $ret['body']);
            } elseif (is_array($ret) && isset($ret['http_code']) && $ret['http_code'] === 201) {
                $uploaded_point = json_decode($ret['body'], true);
                if ($uploaded_point['upload_status'] == 1)
                    $uploaded_size = $uploaded_point['completed_range'][0][1] + 1;
            } else {
                $failed_count ++;
            }
            $i ++;
        }

        $this->get_upload_host(true);
        return array('status' => false, 'msg' => 'upload fail.');
    }
    
    public function _do_upload ($upload_mode = NULL, $uri, $file, $callback_url = NULL, $callback_status = NULL)
    {
        $UPLOAD_SUCCESS = array('status' => 'ok', 'msg' => '');
        $UPLOAD_FAIL = array('status' => 'fail', 'msg' => '');

        $set_headers = array();
        if (!is_file($file)) {
            return $UPLOAD_FAIL;
        }
        $fd = fopen($file, 'r'); 
       
        $file_md5 = md5_file($file);
        $file_size = filesize($file); 
        
        # get upload host;
        # $this->_upload_host = 'upload.dbank.com';
        #$this->_upload_host = '10.6.2.52';

        $method_type = 'PUT';

        $option = array();
        if (!is_null($upload_mode)) {           # NULL, init, resume, 100-588,
            if ($upload_mode === 'init') {
                $option['mode'] = 'init';
                $option['range'] = array(0, 0);
                $uri = $uri . '?init';
            } elseif ($upload_mode === 'resume') {
                $option['mode'] = 'resume';
                $option['range'] = array(0, 0);
                $uri = $uri . '?resume';
            } else {    # range: 100-588
                $set_headers['nsp-content-range'] = $upload_mode . '/' . $file_size;
                $option['mode'] = NULL;
                $tmp = explode('-', $upload_mode);      # array(100, 588);
                $option['range'] = $tmp;
            }
        } else {
            #$set_headers['nsp-content-range'] = $upload_mode . '/' . $file_size;
        }

        $set_headers["nsp-ts"] = time();
        $set_headers["nsp-file-md5"] = $file_md5;
        $set_headers["nsp-file-size"] = $file_size;
        if (!empty($callback_url) && !empty($callback_status)) {
            $set_headers["nsp-callback"] = $callback_url;
            $set_headers["nsp-callback-status"] = $callback_status;
        }
        $set_headers["nsp-content-md5"] = $this->calc_content_md5($file_md5, $file, 2);

        $params = "";
        ksort($set_headers);
        foreach ($set_headers as $h => $v) {
            $params .= $h.'='.$v.'&';
        }
        $params = substr($params, 0, strlen($params) - 1);
        
        #
        # 靠?靠靠靠&靠靠靠
        # HTTP靠靠 & urlencode(URI) & urlencode(a=x&b=y&...)
        # 靠urlencode靠靠靠?& 靠? uri ?key縱alue 靠urlencode
        #
        $temp_secret = hash_hmac("sha1", $set_headers["nsp-ts"], $this->_app_secret);
        $source_string = $method_type.'&'.urlencode($uri).'&'.urlencode($params);
        $nsp_sig = base64_encode(hash_hmac("sha1", $source_string, $temp_secret, true));

        $header = array();
        $header[] = 'nsp-sig: '.$nsp_sig;
        $header[] = 'Expect: 100-continue'; # 100-continue

        foreach ($set_headers as $h => $v) {
            $header[] = "$h: $v";
        }

        return $this->http('PUT', $this->_upload_host, $uri, $header, NULL, $fd, $option, $upload_mode);

    }

    protected function calc_content_md5 ($file_md5, $file, $count)
    {
        $ret_array = array();
        $file_size = filesize($file); 
        $i = 1;
        while ($i <= $count) {
            $crc32 = sprintf("%u", crc32($file_md5 . $i));
            $n = floor($crc32 / $file_size);
            $start = $crc32 - ($n * $file_size);
            $end = ($start + 1024*1024) > ($file_size - 1) ? ($file_size - 1) : ($start + 1024*1024);
            $f = fopen($file, 'r');
            fseek($f, $start);
            $str2 = fread($f, $end - $start + 1);
            $ret_array[] = md5($str2);
            $i ++;
        }

        return json_encode($ret_array);
    }

    protected function get_upload_host ($flag = false)
    {
        if ($flag !== true && isset($this->_upload_host) && isset($this->_upload_host_exptime) && time() < $this->_upload_host_exptime) {
            return true;
        }

        $ret = $this->api('nsp.ping.getupsrvip', array('rip' => '',)); 
        if (isset($ret['http_code']) && $ret['http_code'] === 200) {
            $resp_body = json_decode($ret['body'], true);
            if (isset($resp_body['succ']) && ($resp_body['succ'] === "true" || $resp_body['succ'] === true)) {
                $this->_upload_host = $resp_body['ip'];
                $this->_upload_host_exptime = time() + self::UPLOAD_HOST_EXPTIME;
            }
        }
        
        return false; 
    }

    protected function get_nsp_key ($secret, $params)
    {
        $str = $secret;
        ksort($params);
        foreach ($params as $k => $v) {
            $str = $str.$k.$v;
        }
        
        return strtoupper(md5($str));
    }

    public function api ($interface, $interface_param)         # VFS interfaces
    {
        $ts = time();
        $params = array (
            'nsp_app' => $this->_app_id, 
            'nsp_ts' => $ts, 
            'nsp_fmt' => 'JSON', 
            'nsp_ver' => '1.0',
            'nsp_svc' => $interface,
        );
        $params = array_merge($params, $interface_param);
        $nsp_key = $this->get_nsp_key($this->_app_secret, $params);
        $params['nsp_key'] = $nsp_key;

        $str = "";
        foreach ($params as $k => $v) {
            $v = urlencode($v);
            $str = $str . "$k=$v" . '&';
        }
        $str = substr($str, 0, strlen($str) - 1);
    
        return $this->http('POST', self::API_DBANK_COM, '/rest.php', NULL, $str, NULL, NULL);
    }


    protected function http ($method, $host, $uri, $headers = NULL, $body = NULL, $file_handle = NULL, $option = NULL)
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $host . $uri);
        if ($method === 'PUT') {
            if (is_array($option) && ($option['mode'] === 'init' || $option['mode'] === 'resume')) {
                curl_setopt($ch, CURLOPT_PUT, 1);
                curl_setopt($ch, CURLOPT_INFILE, $file_handle);
                curl_setopt($ch, CURLOPT_INFILESIZE, 0);
            } else if (empty($option)) {
                curl_setopt($ch, CURLOPT_PUT, 1);
                fseek($file_handle, 0, SEEK_END);
                $length = ftell($file_handle);
                fseek($file_handle, 0);
                curl_setopt($ch, CURLOPT_INFILE, $file_handle);
                curl_setopt($ch, CURLOPT_INFILESIZE, $length);
            } else {
                $length = $option['range'][1] - $option['range'][0] + 1;
                $req_body = '';
                fseek($file_handle, $option['range'][0]);
                $req_body = fread($file_handle, $length);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $req_body);
                $headers[] = 'Content-Length: ' . $length;
            }
        }

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        if (is_array($headers))
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 1*60*60);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_USERAGENT, self::USER_AGENT_PREFIX . self::VERSION);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        $ret = curl_exec($ch);
        $body = explode("\r\n\r\n", $ret, 3);

        $return_array = array();
        $return_array['body'] = end($body);   
        if ($error = curl_error($ch)) {
            curl_close($ch);
            return false;
        }
        $ret_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $return_array['http_code'] = $ret_code;
        curl_close($ch);

        return $return_array; 

    }
}

