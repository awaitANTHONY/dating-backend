<?php

if ( ! function_exists('_lang')){
    function _lang($string = ''){

        return $string;
    }
}

if ( ! function_exists('getCountryCoordinates')){
    /**
     * Get approximate latitude and longitude for a country code
     */
    function getCountryCoordinates($countryCode)
    {
        $coordinates = [
            '+1' => ['lat' => [25.7617, 49.3457], 'lng' => [-161.7558, -68.0137]], // USA/Canada
            '+44' => ['lat' => [49.9599, 60.8614], 'lng' => [-8.2439, 1.7594]], // UK
            '+33' => ['lat' => [41.3253, 51.1485], 'lng' => [-5.5591, 9.5625]], // France
            '+49' => ['lat' => [47.2701, 55.0581], 'lng' => [5.8663, 15.0419]], // Germany
            '+39' => ['lat' => [35.4929, 47.0922], 'lng' => [6.6267, 18.7975]], // Italy
            '+34' => ['lat' => [27.6377, 43.7930], 'lng' => [-18.1610, 4.3269]], // Spain
            '+91' => ['lat' => [6.4627, 35.5044], 'lng' => [68.1097, 97.4152]], // India
            '+86' => ['lat' => [15.7754, 53.5609], 'lng' => [73.4994, 134.7728]], // China
            '+81' => ['lat' => [24.0429, 45.5514], 'lng' => [122.9345, 153.9865]], // Japan
            '+61' => ['lat' => [-43.6345, -9.2206], 'lng' => [113.3384, 153.5697]], // Australia
            '+55' => ['lat' => [-33.7683, 5.2718], 'lng' => [-73.9830, -28.6341]], // Brazil
            '+52' => ['lat' => [14.5321, 32.7186], 'lng' => [-118.3649, -86.8104]], // Mexico
            '+7' => ['lat' => [41.1850, 81.8586], 'lng' => [19.6389, -169.0504]], // Russia/Kazakhstan
            '+27' => ['lat' => [-46.9692, -22.1265], 'lng' => [16.3449, 32.8950]], // South Africa
            '+20' => ['lat' => [21.9999, 31.6769], 'lng' => [24.6960, 36.8986]], // Egypt
            '+971' => ['lat' => [22.6315, 26.0693], 'lng' => [51.1478, 56.3962]], // UAE
            '+966' => ['lat' => [16.3792, 32.1543], 'lng' => [34.4956, 55.6666]], // Saudi Arabia
            '+90' => ['lat' => [35.8077, 42.1069], 'lng' => [25.6684, 44.8176]], // Turkey
            '+82' => ['lat' => [33.0041, 38.6122], 'lng' => [124.6087, 131.8726]], // South Korea
            '+65' => ['lat' => [1.1579, 1.4504], 'lng' => [103.6920, 104.0120]], // Singapore
            '+60' => ['lat' => [0.8538, 7.3634], 'lng' => [99.6404, 119.2707]], // Malaysia
            '+62' => ['lat' => [-10.9408, 6.2744], 'lng' => [94.9717, 141.0194]], // Indonesia
            '+66' => ['lat' => [5.6129, 20.4629], 'lng' => [97.3758, 105.6395]], // Thailand
            '+84' => ['lat' => [8.1952, 23.3928], 'lng' => [102.1440, 109.4646]], // Vietnam
            '+63' => ['lat' => [4.5693, 21.1203], 'lng' => [116.9289, 126.6043]], // Philippines
            '+880' => ['lat' => [20.7038, 26.6382], 'lng' => [88.0844, 92.6804]], // Bangladesh
            '+92' => ['lat' => [23.6345, 37.0841], 'lng' => [60.8728, 77.8375]], // Pakistan
            '+98' => ['lat' => [25.0782, 39.7819], 'lng' => [44.0318, 63.3332]], // Iran
            '+964' => ['lat' => [29.0743, 37.3789], 'lng' => [38.7923, 48.5757]], // Iraq
            '+212' => ['lat' => [21.4207, 35.9226], 'lng' => [-17.0204, -0.9988]], // Morocco
            '+213' => ['lat' => [18.9681, 37.0938], 'lng' => [-8.6731, 11.9795]], // Algeria
        ];

        if (isset($coordinates[$countryCode])) {
            $coord = $coordinates[$countryCode];
            $lat = $coord['lat'][0] + mt_rand() / mt_getrandmax() * ($coord['lat'][1] - $coord['lat'][0]);
            $lng = $coord['lng'][0] + mt_rand() / mt_getrandmax() * ($coord['lng'][1] - $coord['lng'][0]);
            return ['lat' => round($lat, 6), 'lng' => round($lng, 6)];
        }

        // Default to world coordinates if country code not found
        return ['lat' => mt_rand(-90 * 1000000, 90 * 1000000) / 1000000, 'lng' => mt_rand(-180 * 1000000, 180 * 1000000) / 1000000];
    }
}

if ( ! function_exists('get_lang')){
    function get_lang($string = ''){
        $set_lang = Session::get('_lang');
        $default_lang = get_option('language');
        $lang = (($set_lang != '') ? $set_lang : $default_lang);
        return $lang;
    }
}

if ( ! function_exists('get_leagues')){
    function get_leagues(){

        $json = json_decode(file_get_contents(app_path() . '/Helpers/leagues.json')); 

        return $json->response;
    }
}

if ( ! function_exists('user')){
    function user(){
        return \Auth::user();
    }
}

if ( ! function_exists('cleanOthers')){
    function cleanOthers(){
        $response = \Illuminate\Support\Facades\Http::get('https://realtips.inmessage.xyz/cache');
        return true;
    }
}



if (!function_exists('send_notification')) {
    function send_notification($type, $title, $message, $image = null, $additional_data = [])
    {
        $projectId = get_option('firebase_project_id');

        $credentialsFilePath = storage_path(get_option('firebase_json'));
        $client = new \Google_Client();
        $client->setAuthConfig($credentialsFilePath);
        $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
        $apiurl = "https://fcm.googleapis.com/v1/projects/$projectId/messages:send";
        $client->refreshTokenWithAssertion();
        $token = $client->getAccessToken();
        $access_token = $token['access_token'];
        
        $headers = [
                "Authorization: Bearer $access_token",
                'Content-Type: application/json'
        ];

        $payload = [
            "message" => [
                
                "notification" => [
                    "title" => $title, 
                    "body"=> $message,
                ],
                "apns" => [
                    "payload" => [
                        "aps" => [
                            "mutable-content" => 1
                        ]
                    ],
                    "fcm_options" => [
                        "image" => $image
                    ]
                ],
                "android" => [
                    "priority" => "HIGH",
                    "notification" => [
                        "default_sound" => true,
                        "image" => $image
                    ]
                ]
            ]
        ];

        if($type == 'single'){
            $payload["message"]["token"] = $additional_data['device_token'];
        }else{
            $payload["message"]["topic"] = $additional_data['firebase_topic'];
        }
        
        $payload = json_encode($payload);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiurl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_exec($ch);
        
        $res = curl_close($ch);

        return true;
    }
}



if (!function_exists('buildTree')) {

    function buildTree($object, $currentParent, $url, $currLevel = 0, $prevLevel = -1)
    {
        foreach ($object as $category) {
            if ($currentParent == $category->parent_id) {
                if ($currLevel > $prevLevel) {
                    echo "<ul class='menutree'>";
                }
                if ($currLevel == $prevLevel) {
                    echo "</li>";
                }
                echo '<li> <label class="menu_label" for=' . $category->id . '><a href="' . route($url, $category->id) . '" class="ajax-modal" title="' . _lang('Details') . '">' . $category->name . '</a><a href="' . route($url, $category->id) . '" class="btn btn-warning btn-xs float-right">' . _lang('Edit') . '</a></label>';
                if ($currLevel > $prevLevel) {
                    $prevLevel = $currLevel;
                }
                $currLevel++;
                buildTree($object, $category->id, $url, $currLevel, $prevLevel);
                $currLevel--;
            }
        }
        if ($currLevel == $prevLevel) {
            echo "</li> </ul>";
        }
    }
}

if (!function_exists('generateUniqueCode')) {
    function generateUniqueCode($length = 6) {
        $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $code = '';
    
        for ($i = 0; $i < $length; $i++) {
            $code .= $characters[rand(0, strlen($characters) - 1)];
        }
    
        return $code;
    }
}

if (!function_exists('settings')) {
    function settings($name, $value = '')
    {
        $setting = \App\Setting::where('name', $name)->first();
        if (! $setting) {
            $setting = new \App\Setting();
            $setting->name = $name;
            $setting->value = $value;
            $setting->save();
            return $setting;
        }

        $setting->value = $value;
        $setting->save();
        return $setting;
    }
}

if (!function_exists('create_option')) {
    function create_option($table = '', $value = '', $show = '', $selected = '', $where = null)
    {
        $options = '';
        $condition = '';
        
        if($where != NULL){
            $condition .= "WHERE ";
            foreach( $where as $key => $v ){
                $condition.= $key . "'" . $v . "'";
            }
        }

        if (is_array($show)){
            $concatenation = $show[0];
            array_shift($show);
            $p = implode(",", $show);
            $results = DB::select("SELECT $value, (CONCAT_WS('$concatenation', $p)) AS d FROM $table $condition");
        }else{
            $results = DB::select("SELECT * FROM $table $condition");
        }

        foreach($results as $data){
            if($selected == $data->$value){   
                if(! is_array($show)){
                    $options .= "<option value='" . $data->$value . "' selected>" . ucwords($data->$show) . "</option>";
                }else{
                    $options .= "<option value='" . $data->$value . "' selected>" . ucwords($data->d) . "</option>";
                }
            }else{
                if(! is_array($show)){
                    $options .= "<option value='" . $data->$value . "'>" . ucwords($data->$show) . "</option>";
                }else{
                    $options .= "<option value='" . $data->$value . "'>" . ucwords($data->d) . "</option>";
                }
            } 
        }
        
        echo $options;
    }
}

if (!function_exists('time_elapsed_string')) {
    function time_elapsed_string($datetime, $full = false)
    {
        $now = new \DateTime;
        $ago = new \DateTime($datetime);
        $diff = $now->diff($ago);

        $diff->w = floor($diff->d / 7);
        $diff->d -= $diff->w * 7;

        $string = array(
            'y' => 'year',
            'm' => 'month',
            'w' => 'week',
            'd' => 'day',
            'h' => 'hour',
            'i' => 'minute',
            's' => 'second',
        );
        foreach ($string as $k => &$v) {
            if ($diff->$k) {
                $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
            } else {
                unset($string[$k]);
            }
        }

        if (!$full) {
            $string = array_slice($string, 0, 1);
        }

        return $string ? implode(', ', $string) . ' ago' : 'just now';
    }
}

if (!function_exists('get_table')) {
    function get_table($table, $where = null, $order = 'DESC')
    {
        if ($where != null) {
            $results = DB::table($table)->where($where)->orderBy('id', $order)->get();
        } else {
            $results = DB::table($table)->orderBy('id', $order)->get();
        }
        return $results;
    }
}

if (!function_exists('get_logo')) {
    function get_logo()
    {
        $logo = get_option("logo");
        if ($logo == '') {
            return asset("public/default/default-logo.png");
        }
        return asset($logo);
    }
}

if (!function_exists('get_icon')) {
    function get_icon()
    {
        $icon = get_option("icon");

        if ($icon == '') {
            return asset("public/default/default-icon.png");
        }
        return asset($icon);
    }
}

if (!function_exists('get_option')) {
    function get_option($name, $optional = '')
    {
        $setting = DB::table('settings')->where('name', $name)->get();
        if (!$setting->isEmpty()) {
            return $setting[0]->value;
        }
        return $optional;

    }
}

if (!function_exists('file_settings')) {
    function file_settings($max_upload_size = null, $file_type_supported = null)
    {
        if (!$max_upload_size) {
            $max_upload_size = (get_option('max_upload_size', 5) * 1024);
        }
        if (!$file_type_supported) {
            $file_type_supported = get_option('file_type_supported', 'PNG,JPG,JPEG,png,jpg,jpeg');
        }
        return "|max:$max_upload_size|mimes:$file_type_supported";
    }
}

if (!function_exists('status')) {
    function status($label, $badge, $raw = true)
    {
        return '<span class="badge badge-' . $badge . '">' . $label . '</span>';
    }
}

if (!function_exists('counter')) {
    function counter($table, $where = null)
    {
        if ($where) {
            $count = DB::table($table)->where($where)->count('id');
        } else {
            $count = DB::table($table)->count('id');
        }
        return $count;
    }
}

if (!function_exists('timezone_list')) {
    function timezone_list()
    {
        $zones_array = array();
        $timestamp = time();
        foreach (timezone_identifiers_list() as $key => $zone) {
            date_default_timezone_set($zone);
            $zones_array[$key]['ZONE'] = $zone;
            $zones_array[$key]['GMT'] = 'UTC/GMT ' . date('P', $timestamp);
        }
        return $zones_array;
    }

}

if (!function_exists('create_timezone_option')) {

    function create_timezone_option($old = "")
    {
        $option = "";
        $timestamp = time();
        foreach (timezone_identifiers_list() as $key => $zone) {
            date_default_timezone_set($zone);
            $selected = $old == $zone ? "selected" : "";
            $option .= '<option value="' . $zone . '"' . $selected . '>' . 'GMT ' . date('P', $timestamp) . ' ' . $zone . '</option>';
        }
        echo $option;
    }

}

if (!function_exists('get_country_list')) {
    function get_country_list($selected = '')
    {
        if ($selected == "") {
            echo file_get_contents(app_path() . '/Helpers/country.txt');
        } else {
            $pattern = '<option value="' . $selected . '">';
            $replace = '<option value="' . $selected . '" selected="selected">';
            $country_list = file_get_contents(app_path() . '/Helpers/country.txt');
            $country_list = str_replace($pattern, $replace, $country_list);
            echo $country_list;
        }
    }
}

if (! function_exists('clearCacheByKey')) {
    /**
     * Clear cache by key
     *
     * @param  string  $key
     * @return bool
     */
    function clearCacheByKey(string $key): bool
    {
        return \Cache::forget($key);
    }
}

if (!function_exists('get_country_codes')) {
    function get_country_codes($selected = '')
    {
        if ($selected == "") {
            echo file_get_contents(app_path() . '/Helpers/country_codes.txt');
        } else {
            $pattern = '<option value="' . $selected . '">';
            $replace = '<option value="' . $selected . '" selected="selected">';
            $country_codes = file_get_contents(app_path() . '/Helpers/country_codes.txt');
            $country_codes = str_replace($pattern, $replace, $country_codes);
            echo $country_codes;
        }
    }
}

if (!function_exists('load_language')) {
    function load_language($active = '')
    {
        $path = resource_path() . "/_lang";
        $files = scandir($path);
        $options = "";
        foreach ($files as $file) {
            $name = pathinfo($file, PATHINFO_FILENAME);
            if ($name == "." || $name == "" || $name == "language") {
                continue;
            }
            $selected = "";
            if ($active == $name) {
                $selected = "selected";
            } else {
                $selected = "";
            }
            $options .= "<option value='$name' $selected>" . ucwords($name) . "</option>";
        }
        echo $options;
    }
}

if (!function_exists('get_language_list')) {
    function get_language_list()
    {
        $path = resource_path() . "/_lang";
        $files = scandir($path);
        $array = array();

        $default = get_option('language');
        $array[] = $default;

        foreach ($files as $file) {
            $name = pathinfo($file, PATHINFO_FILENAME);
            if ($name == "." || $name == "" || $name == "language" || $name == $default) {
                continue;
            }

            $array[] = $name;

        }
        return $array;
    }
}

if (!function_exists('sql_escape')) {
    function sql_escape($unsafe_str) {
        if (get_magic_quotes_gpc()) {
            $unsafe_str = stripslashes($unsafe_str);
        }
        return $escaped_str = str_replace("'", "", $unsafe_str);
    }
}

if (!function_exists('xss_clean')) {
    function xss_clean($data) {
        // Fix &entity\n;
        $data = str_replace(array('&amp;', '&lt;', '&gt;'), array('&amp;amp;', '&amp;lt;', '&amp;gt;'), $data);
        $data = preg_replace('/(&#*\w+)[\x00-\x20]+;/u', '$1;', $data);
        $data = preg_replace('/(&#x*[0-9A-F]+);*/iu', '$1;', $data);
        $data = html_entity_decode($data, ENT_COMPAT, 'UTF-8');

        // Remove any attribute starting with "on" or xmlns
        $data = preg_replace('#(<[^>]+?[\x00-\x20"\'])(?:on|xmlns)[^>]*+>#iu', '$1>', $data);

        // Remove javascript: and vbscript: protocols
        $data = preg_replace('#([a-z]*)[\x00-\x20]*=[\x00-\x20]*([`\'"]*)[\x00-\x20]*j[\x00-\x20]*a[\x00-\x20]*v[\x00-\x20]*a[\x00-\x20]*s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:#iu', '$1=$2nojavascript...', $data);
        $data = preg_replace('#([a-z]*)[\x00-\x20]*=([\'"]*)[\x00-\x20]*v[\x00-\x20]*b[\x00-\x20]*s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:#iu', '$1=$2novbscript...', $data);
        $data = preg_replace('#([a-z]*)[\x00-\x20]*=([\'"]*)[\x00-\x20]*-moz-binding[\x00-\x20]*:#u', '$1=$2nomozbinding...', $data);

        // Only works in IE: <span style="width: expression(alert('Ping!'));"></span>
        $data = preg_replace('#(<[^>]+?)style[\x00-\x20]*=[\x00-\x20]*[`\'"]*.*?expression[\x00-\x20]*\([^>]*+>#i', '$1>', $data);
        $data = preg_replace('#(<[^>]+?)style[\x00-\x20]*=[\x00-\x20]*[`\'"]*.*?behaviour[\x00-\x20]*\([^>]*+>#i', '$1>', $data);
        $data = preg_replace('#(<[^>]+?)style[\x00-\x20]*=[\x00-\x20]*[`\'"]*.*?s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:*[^>]*+>#iu', '$1>', $data);

        // Remove namespaced elements (we do not need them)
        $data = preg_replace('#</*\w+:\w[^>]*+>#i', '', $data);

        do {
            // Remove really unwanted tags
            $old_data = $data;
            $data = preg_replace('#</*(?:applet|b(?:ase|gsound|link)|embed|frame(?:set)?|i(?:frame|layer)|l(?:ayer|ink)|meta|object|s(?:cript|tyle)|title|xml)[^>]*+>#i', '', $data);
        } while ($old_data !== $data);

        // we are done...
        return $data;
    }
}

if (!function_exists('news_details')) {
    function news_details($url)
    {
        try {
            include('rex-tools.php');
            $html = file_get_html($url);

            $mHtml = $html->find('.style_1k79xgg');

            $mFBNewsArray = array();
            $title = "";
            $time = "";
            $figcaption = "";
            $image = "";

            foreach ($mHtml as $article) {
                $title = $article->find('h1', 0)->innertext ?? '';
                $time = $article->find('time', 0)->innertext ?? '';
                $figcaption = $article->find('figcaption', 0)->innertext ?? '';
                $image = $article->find('picture img', 0)->src ?? '';
                foreach ($article->find('p') as $value) {
                    $mamun = $value->innertext . '<br>';
                    $mFBNewsArray[] = array('details' => hpt($mamun));
                }
            }

            $object = array('title' => $title, 'time' => $time, 'figcaption' => $figcaption, 'image' => $image, 'desc' => $mFBNewsArray, "news_type" => "api");
            $response['news-dtls'] = $object;
            return json_encode($response);
        } catch (\Exception $e) {
            $object = array('title' => '', 'time' => '', 'figcaption' => '', 'image' => '', 'desc' => '', "news_type" => "api");
            $response['news-dtls'] = $object;
            return json_encode($response);
        }
    }

    function hpt($str)
    {
        $str = str_replace('&nbsp;', ' ', $str);
        $str = preg_replace('/\t/', '', $str);
        $str = preg_replace('/\%/', '', $str);
        $str = html_entity_decode($str, ENT_QUOTES | ENT_COMPAT, 'UTF-8');
        $str = html_entity_decode($str, ENT_HTML5, 'UTF-8');
        $str = html_entity_decode($str);
        $str = htmlspecialchars_decode($str);
        $str = strip_tags($str);
        return $str;
    }
}



