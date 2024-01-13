<?php

use App\Models\{
    Admin,
    ClassRepLog,
    ClassRep,
    Setting,
    User
};
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{
    Route,
    Storage
};
use Jenssegers\Agent\Agent as Agent;
use Illuminate\Support\Str;

if (! function_exists('is_uuid')) {
    function is_uuid($uuid) : bool {
        return Str::isUuid($uuid);
    }
}

if (! function_exists("validate_whatsapp")) {
    function validate_whatsapp(string $whatsapp) : bool {
        $secret = env('TRENALYZE_SECRET');
        $account_id = get_trenalyze_id();
        $verify = file_get_contents("https://trenalyze.com/api/validate/whatsapp?secret=$secret&unique=$account_id&phone=$whatsapp");
        $verify = json_decode($verify);
        if (isset($verify->status) && $verify->status == 200) {
            return true;
        }
        return false;
    }
}


if (!function_exists('validate_phone')) {
    function validate_phone($network, $phone) : bool {
        $v4 = substr($phone, 0, 4);
        $v5 = substr($phone, 0, 5);
        $network_name = $network;
        $resp = true;
        if ($network_name == 'MTN') {
            $md4 = ['0803', '0806', '0703', '0706', '0813', '0816', '0810', '0814', '0903', '0906', '0913', '0916', '0704'];
            if (!in_array($v4, $md4)) {
                $md5 = ['07025', '07026'];
                if (!in_array($v5, $md5)) {
                    $resp = false;
                }
            }
        } elseif ($network_name == 'GLO') {
            $gd4 = ['0805', '0807', '0705', '0815', '0811', '0905', '0915'];
            if (!in_array($v4, $gd4)) {
                $resp = false;
            }
        } elseif ($network_name == 'AIRTEL') {
            $ad4 = ['0802', '0808', '0708', '0812', '0701', '0902', '0901', '0904', '0907', '0912'];
            if (!in_array($v4, $ad4)) {
                $resp = false;
            }
        } elseif ($network_name == '9MOBILE') {
            $ed4 = ['0809', '0818', '0817', '0909', '0908'];
            if (!in_array($v4, $ed4)) {
                $resp = false;
            }
        }
        return $resp;
    }
}

if (!function_exists('csc_group')) {
    function csc_group() : string {
        return env('OAU_CSC');
    }
}

if (! function_exists('get_trenalyze_id')) {
    function get_trenalyze_id() : string {
        $ids = env('TRENALYZE_ACCOUNT_ID'); // get_setting('trenalyzeUniqueIds');
        $uid = 'xxxxxxx';
        if (!empty($ids)) {
            $ids = str_replace(' ', '', $ids);
            if (str_contains($ids, ',')) {
                $arr = explode(',', $ids);
                $uid = $arr[array_rand($arr)];
            } else {
                $uid = $ids;
            }
        }
        return $uid;
    }
}


if (! function_exists('tippy') ) {
    function tippy($tip = '', $content = '', $id = '') {
        $html = "<span class='toolTip onTop' data-tippy-content='$tip' id='$id'>$content</span>";
        return $html;
    }
}

if (! function_exists('info_tip') ) {
    function info_tip($message = '') {
        $tippy = tippy($message, icon('material-symbols:info-outline'));
        return $tippy;
    }
}

if (! function_exists('calculate_percentage') ) {
    function calculate_percentage(float|int $amount, $percentage) : float|int {
        $calc = ($percentage * $amount) / 100;
        return (float) round($calc,2,2);
    }
}

if (! function_exists("select_dropdown")) {
    function select_dropdown($item, $selected = null) : string {
        return ($item == $selected) ? 'selected' : '';
    }
}

if (! function_exists('status') ) {
    function status($status) : string {
        $sucess = ['successful', 'responded', 'called', 'active', 'credit'];
        if (in_array($status, $sucess)) {
            return 'success';
        }
        $warning = ['pending'];
        if (in_array($status, $warning)) {
            return 'warning';
        }
        $danger = ['failed', 'inactive', 'banned', 'debit'];
        if (in_array($status, $danger)) {
            return 'danger';
        }
        return 'success';
    }
}

if (! function_exists('mask') ) {
    function mask($str, $last, $repeat) : string {
        $show = substr($str, -$last);
        $masked = str_repeat($repeat, strlen($str) - $last) . $show;
        return $masked;
    }
}

if (! function_exists('generate_string')) {
    function generate_string(int $length, string $type = 'alpha', string $case = 'lower', string $prefix = '', string $suffix = '') : string {
        $chars = '';
        if ($type == 'alpha') {
          $chars .= $case == 'lower' ? 'abcdefghijklmnopqrstuvwxyz' : 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        } elseif ($type == 'mixed') {
          $chars .= $case == 'lower' ? 'abcdefghijklmnopqrstuvwxyz0123456789' : ($case == 'upper' ? 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789' : 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789');
        } elseif ($type == 'numeric') {
          $chars .= '0123456789';
        }
        $str = '';
        for ($i = 0; $i < $length; $i++) {
          $str .= $chars[rand(0, strlen($chars) - 1)]; 
        }
        return $prefix . $str . $suffix;
    }
}

if (! function_exists('is_mobile') ) {
    function is_mobile() : bool {
        $agent = new Agent();
        if ($agent->isMobile()) return true;
        return false;
    }
}


if (! function_exists('trenalyze') ) {
    function trenalyze ($receiver, $msg, $type = 'text', $addfile = false, $document = []) : bool {
        $secret = env('TRENALYZE_SECRET');
        $url = "https://trenalyze.com/api/send/whatsapp";
        $curl = curl_init($url);
        $tries = 0;
        do {
            $account_id = get_trenalyze_id();
            $curl = curl_init($url);
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            $chat = [
                "secret" => $secret, 
                "account" => $account_id,
                "recipient" => $receiver,
                "type" => $type,
                "message" => $msg,
                "priority" => 1
            ];
            if ($addfile) {
                $chat['document_url'] = $document['document_url'];
                $chat['document_type'] = $document['document_type'];
                $chat['document_name'] = $document['document_name'];
            }
            curl_setopt($curl, CURLOPT_POSTFIELDS, $chat);
            //for debug only!
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            $resp = curl_exec($curl);
            curl_close($curl);
            $resp = json_decode($resp);

            // info(json_encode($resp)); // Log the response for debugging
            
            // Check if the status is set and is 200
            if (isset($resp->status) && $resp->status === 200) {
                return true; // Exit the loop if the status is 200
            } elseif (isset($resp->status) && $resp->status !== 200) {
                info(json_encode($resp));
            }
            $tries++;
        } while ($tries < 3);
        return false;
    }
}

if (! function_exists('icon') ) {
    function icon($key, $size = '15', $class = 'nav-icon') {
        $html = '<iconify-icon class=" ' . $class . '" icon="' . $key . '" style="font-size:' . $size . 'px"></iconify-icon>';
        return $html;
    }
}

if (! function_exists('load_file') ) {
    function load_file($path) : string {
        $newPath = str_replace('public/', 'public/storage/', $path);
        return (server_name() == '0.0.0.0') ? Storage::url($path) : url($newPath);
    }
}

if (! function_exists('server_name') ) {
    function server_name() : string {
        return $_SERVER['SERVER_NAME'];
    }
}

if (! function_exists('check_file_exist') ) {
    function check_file_exist($path) : bool {
        return (Storage::exists($path)) ? true : false;
    }
}

if (! function_exists('nbsp') ) {
    function nbsp($count = 1) : string {
        $str = '';
        for ($i=0; $i < $count; $i++) { 
            $str .= '&nbsp';
        }
        return $str;
    }
}

if (! function_exists('content_wrapper') ) {
    function content_wrapper() : string {
        $html = '<div class="content-wrapper transition-all duration-150 ltr:ml-[248px] rtl:mr-[248px]" id="content_wrapper">
        <div class="page-content">
          <div class="transition-all duration-150 container-fluid" id="page_layout">
            <div id="content_layout">';
        return $html;
    }
}

if (! function_exists('assets') ) {
    function assets($path) : string {
        return (server_name() == '127.0.0.1') ? url('/') . "/public/assets/$path" : url('/') . "/assets/$path";
    }
}

if (! function_exists('route_name') ) {
    function route_name() : string {
        return Route::currentRouteName();
    }
}

if (! function_exists('active_menu') ) {
    function active_menu($route) : string {
        return route_name() == $route ? 'active' : '';
    }
}

if (! function_exists('current_guard') ) {
    function current_guard($guard) {
        $middlewares = Route::current()->middleware();
        $auth = 'auth:';
        $guard = $auth . $guard;
        if (in_array($guard, $middlewares)) {
            return true;
        }
        return false;
    }
}





